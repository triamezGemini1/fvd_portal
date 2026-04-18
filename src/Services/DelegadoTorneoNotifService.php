<?php

declare(strict_types=1);

namespace FvdPortal\Services;

use PDO;
use PDOException;

/**
 * Notificaciones en panel web para delegados: invitación a torneo y PDF asociado.
 */
final class DelegadoTorneoNotifService
{
    public static function ensureTable(PDO $pdo): void
    {
        $sqlPath = dirname(__DIR__, 2) . '/fvdmasteradmin/sql/install_fvd_delegado_notif_torneo.sql';
        if (!is_readable($sqlPath)) {
            return;
        }
        $sql = file_get_contents($sqlPath);
        if ($sql === false || strpos($sql, 'CREATE TABLE') === false) {
            return;
        }
        try {
            $pdo->exec($sql);
        } catch (PDOException $e) {
            error_log('[DelegadoTorneoNotifService] ensureTable: ' . $e->getMessage());
        }
    }

    /**
     * Añade columnas de token, asociación y PDF de tarjeta (idempotente).
     */
    public static function ensureTokenColumns(PDO $pdo): void
    {
        self::ensureTable($pdo);
        $alters = [
            'ADD COLUMN access_token VARCHAR(64) NULL DEFAULT NULL COMMENT \'Secreto URL; no reutilizar entre clubes\'',
            'ADD COLUMN asociacion_id INT NULL DEFAULT NULL COMMENT \'asociaciones.id\'',
            'ADD COLUMN tarjeta_pdf VARCHAR(255) NULL DEFAULT NULL COMMENT \'PDF generado en uploads/\'',
        ];
        foreach ($alters as $sql) {
            try {
                $pdo->exec('ALTER TABLE fvd_delegado_notif_torneo ' . $sql);
            } catch (PDOException $e) {
                if (stripos($e->getMessage(), 'Duplicate column') === false) {
                    error_log('[DelegadoTorneoNotifService] ensureTokenColumns alter: ' . $e->getMessage());
                }
            }
        }
        try {
            $pdo->exec('ALTER TABLE fvd_delegado_notif_torneo ADD UNIQUE KEY uk_access_token (access_token)');
        } catch (PDOException $e) {
            if (stripos($e->getMessage(), 'Duplicate key name') === false && stripos($e->getMessage(), 'duplicate') === false) {
                error_log('[DelegadoTorneoNotifService] ensureTokenColumns unique: ' . $e->getMessage());
            }
        }
    }

    /**
     * Crea o actualiza avisos para todos los delegados activos (mismo universo que el correo masivo).
     * Incluye token de acceso por fila y asociación para enlaces seguros en la tarjeta PDF.
     *
     * @return int Filas afectadas (INSERT + UPDATE duplicados)
     */
    public static function crearNotificacionesParaTorneo(PDO $pdo, int $torneoId): int
    {
        return self::crearNotificacionesParaTorneoFiltrado($pdo, $torneoId, null);
    }

    /**
     * Igual que {@see crearNotificacionesParaTorneo} pero solo delegados cuya asociación está en la lista.
     * Al actualizar una fila existente, se refresca la fecha y se marca como no vista para que el panel muestre el aviso.
     *
     * @param list<int>|null $soloAsociacionIds null = todas las asociaciones con delegado activo
     *
     * @return int Filas afectadas (INSERT + UPDATE duplicados)
     */
    public static function crearNotificacionesParaTorneoFiltrado(PDO $pdo, int $torneoId, ?array $soloAsociacionIds): int
    {
        if ($torneoId <= 0) {
            return 0;
        }
        self::ensureTable($pdo);
        self::ensureTokenColumns($pdo);

        $st = $pdo->prepare('SELECT invitacion FROM torneosact WHERE torneo = :t LIMIT 1');
        $st->execute([':t' => $torneoId]);
        $inv = $st->fetchColumn();
        $invFile = $inv !== false && $inv !== null && trim((string) $inv) !== '' ? trim((string) $inv) : null;

        $sqlBase = 'SELECT d.id, d.asociacion_id FROM delegados d WHERE d.activo = 1 AND d.asociacion_id IS NOT NULL AND d.asociacion_id > 0';
        $stD = null;
        if ($soloAsociacionIds !== null) {
            $ids = [];
            foreach ($soloAsociacionIds as $v) {
                $i = (int) $v;
                if ($i > 0) {
                    $ids[$i] = true;
                }
            }
            $ids = array_keys($ids);
            if ($ids === []) {
                return 0;
            }
            sort($ids, SORT_NUMERIC);
            $ph = [];
            $params = [];
            foreach ($ids as $k => $ida) {
                $p = ':aid' . $k;
                $ph[] = $p;
                $params[$p] = $ida;
            }
            $sql = $sqlBase . ' AND d.asociacion_id IN (' . implode(', ', $ph) . ')';
            $stD = $pdo->prepare($sql);
            $stD->execute($params);
        } else {
            $stD = $pdo->query($sqlBase);
        }
        if ($stD === false) {
            return 0;
        }
        $ins = $pdo->prepare(
            'INSERT INTO fvd_delegado_notif_torneo (delegado_id, torneo_id, invitacion_archivo, access_token, asociacion_id)
             VALUES (:d, :t, :inv, :tok, :a)
             ON DUPLICATE KEY UPDATE
                invitacion_archivo = VALUES(invitacion_archivo),
                asociacion_id = VALUES(asociacion_id),
                creado_en = CURRENT_TIMESTAMP,
                visto_en = NULL,
                access_token = IFNULL(fvd_delegado_notif_torneo.access_token, VALUES(access_token))'
        );
        $n = 0;
        while ($row = $stD->fetch(PDO::FETCH_ASSOC)) {
            $did = (int) ($row['id'] ?? 0);
            $aid = (int) ($row['asociacion_id'] ?? 0);
            if ($did <= 0 || $aid <= 0) {
                continue;
            }
            $token = bin2hex(random_bytes(32));
            try {
                $ins->execute([':d' => $did, ':t' => $torneoId, ':inv' => $invFile, ':tok' => $token, ':a' => $aid]);
                $n += (int) $ins->rowCount();
            } catch (PDOException $e) {
                error_log('[DelegadoTorneoNotifService] crear: ' . $e->getMessage());
            }
        }

        return $n;
    }

    /**
     * @return array<string, mixed>|null Fila de notificación si el token es válido
     */
    public static function notificacionPorAccessToken(PDO $pdo, string $token): ?array
    {
        $t = trim($token);
        if ($t === '' || strlen($t) < 32) {
            return null;
        }
        self::ensureTable($pdo);
        self::ensureTokenColumns($pdo);
        try {
            $st = $pdo->prepare(
                'SELECT n.* FROM fvd_delegado_notif_torneo n
                 WHERE n.access_token IS NOT NULL AND n.access_token = :tok LIMIT 1'
            );
            $st->execute([':tok' => $t]);
            $r = $st->fetch(PDO::FETCH_ASSOC);

            return $r !== false ? $r : null;
        } catch (PDOException $e) {
            error_log('[DelegadoTorneoNotifService] notificacionPorAccessToken: ' . $e->getMessage());

            return null;
        }
    }

    public static function delegadoTieneNotificacionTorneo(PDO $pdo, int $delegadoId, int $torneoId): bool
    {
        if ($delegadoId <= 0 || $torneoId <= 0) {
            return false;
        }
        self::ensureTable($pdo);
        try {
            $st = $pdo->prepare(
                'SELECT 1 FROM fvd_delegado_notif_torneo WHERE delegado_id = :d AND torneo_id = :t LIMIT 1'
            );
            $st->execute([':d' => $delegadoId, ':t' => $torneoId]);

            return (bool) $st->fetchColumn();
        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * Acceso al panel de un torneo: notificación directa o cualquier torneo del mismo grupo_evento_id (misma invitación de circuito).
     */
    public static function delegadoTieneAccesoEventoGrupo(PDO $pdo, int $delegadoId, int $asociacionId, int $torneoId, ?int $grupoEventoId): bool
    {
        if ($delegadoId <= 0 || $torneoId <= 0 || $asociacionId <= 0) {
            return false;
        }
        if (self::delegadoTieneNotificacionTorneo($pdo, $delegadoId, $torneoId)) {
            return true;
        }
        if ($grupoEventoId === null || $grupoEventoId <= 0) {
            return false;
        }
        self::ensureTable($pdo);
        try {
            $st = $pdo->prepare(
                'SELECT 1 FROM fvd_delegado_notif_torneo n
                 INNER JOIN torneosact t ON t.torneo = n.torneo_id
                 INNER JOIN delegados d ON d.id = n.delegado_id
                 WHERE n.delegado_id = :d AND d.asociacion_id = :a AND t.grupo_evento_id = :g LIMIT 1'
            );
            $st->execute([':d' => $delegadoId, ':a' => $asociacionId, ':g' => $grupoEventoId]);

            return (bool) $st->fetchColumn();
        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function notificacionPorIdParaDelegado(PDO $pdo, int $notifId, int $delegadoId): ?array
    {
        if ($notifId <= 0 || $delegadoId <= 0) {
            return null;
        }
        self::ensureTable($pdo);
        $st = $pdo->prepare(
            'SELECT n.*, t.nombre AS torneo_nombre, t.fechator, t.lugar
             FROM fvd_delegado_notif_torneo n
             INNER JOIN torneosact t ON t.torneo = n.torneo_id
             WHERE n.id = :id AND n.delegado_id = :d LIMIT 1'
        );
        $st->execute([':id' => $notifId, ':d' => $delegadoId]);
        $r = $st->fetch(PDO::FETCH_ASSOC);

        return $r !== false ? $r : null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function listarParaDelegado(PDO $pdo, int $delegadoId, int $limite = 30): array
    {
        if ($delegadoId <= 0) {
            return [];
        }
        self::ensureTable($pdo);
        $limite = max(1, min(100, $limite));
        try {
            $st = $pdo->prepare(
                'SELECT n.id, n.torneo_id, n.invitacion_archivo, n.creado_en, n.visto_en,
                    t.nombre AS torneo_nombre, t.fechator, t.lugar
                 FROM fvd_delegado_notif_torneo n
                 INNER JOIN torneosact t ON t.torneo = n.torneo_id
                 WHERE n.delegado_id = :d
                 ORDER BY n.creado_en DESC
                 LIMIT ' . (int) $limite
            );
            $st->execute([':d' => $delegadoId]);

            return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (PDOException $e) {
            error_log('[DelegadoTorneoNotifService] listar: ' . $e->getMessage());

            return [];
        }
    }

    public static function contarNoVistas(PDO $pdo, int $delegadoId): int
    {
        if ($delegadoId <= 0) {
            return 0;
        }
        self::ensureTable($pdo);
        try {
            $st = $pdo->prepare(
                'SELECT COUNT(*) FROM fvd_delegado_notif_torneo WHERE delegado_id = :d AND visto_en IS NULL'
            );
            $st->execute([':d' => $delegadoId]);

            return (int) $st->fetchColumn();
        } catch (PDOException $e) {
            return 0;
        }
    }

    /**
     * @return array<string, mixed>|null Última notificación no vista
     */
    public static function ultimaNoVista(PDO $pdo, int $delegadoId): ?array
    {
        if ($delegadoId <= 0) {
            return null;
        }
        self::ensureTable($pdo);
        $st = $pdo->prepare(
            'SELECT n.id, n.torneo_id, n.invitacion_archivo, n.creado_en, n.visto_en,
                t.nombre AS torneo_nombre, t.fechator, t.lugar
             FROM fvd_delegado_notif_torneo n
             INNER JOIN torneosact t ON t.torneo = n.torneo_id
             WHERE n.delegado_id = :d AND n.visto_en IS NULL
             ORDER BY n.creado_en DESC
             LIMIT 1'
        );
        $st->execute([':d' => $delegadoId]);
        $r = $st->fetch(PDO::FETCH_ASSOC);

        return $r !== false ? $r : null;
    }

    public static function marcarVisto(PDO $pdo, int $notifId, int $delegadoId): void
    {
        if ($notifId <= 0 || $delegadoId <= 0) {
            return;
        }
        self::ensureTable($pdo);
        $st = $pdo->prepare(
            'UPDATE fvd_delegado_notif_torneo SET visto_en = COALESCE(visto_en, NOW()) WHERE id = :id AND delegado_id = :d'
        );
        try {
            $st->execute([':id' => $notifId, ':d' => $delegadoId]);
        } catch (PDOException $e) {
            error_log('[DelegadoTorneoNotifService] marcarVisto: ' . $e->getMessage());
        }
    }

    /**
     * Marca como vistas todas las notificaciones pendientes de un torneo (uso administrador FVD antes de abrir inscripciones).
     */
    public static function marcarTodasVistasParaTorneo(PDO $pdo, int $torneoId): int
    {
        if ($torneoId <= 0) {
            return 0;
        }
        self::ensureTable($pdo);
        try {
            $st = $pdo->prepare(
                'UPDATE fvd_delegado_notif_torneo SET visto_en = COALESCE(visto_en, NOW())
                 WHERE torneo_id = :t AND visto_en IS NULL'
            );
            $st->execute([':t' => $torneoId]);

            return $st->rowCount();
        } catch (PDOException $e) {
            error_log('[DelegadoTorneoNotifService] marcarTodasVistasParaTorneo: ' . $e->getMessage());

            return 0;
        }
    }
}
