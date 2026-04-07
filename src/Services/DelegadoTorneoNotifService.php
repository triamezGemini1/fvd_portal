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
        if ($torneoId <= 0) {
            return 0;
        }
        self::ensureTable($pdo);
        self::ensureTokenColumns($pdo);

        $st = $pdo->prepare('SELECT invitacion FROM torneosact WHERE torneo = :t LIMIT 1');
        $st->execute([':t' => $torneoId]);
        $inv = $st->fetchColumn();
        $invFile = $inv !== false && $inv !== null && trim((string) $inv) !== '' ? trim((string) $inv) : null;

        $stD = $pdo->query(
            'SELECT d.id, d.asociacion_id FROM delegados d WHERE d.activo = 1 AND d.asociacion_id IS NOT NULL AND d.asociacion_id > 0'
        );
        if ($stD === false) {
            return 0;
        }
        $ins = $pdo->prepare(
            'INSERT INTO fvd_delegado_notif_torneo (delegado_id, torneo_id, invitacion_archivo, access_token, asociacion_id)
             VALUES (:d, :t, :inv, :tok, :a)
             ON DUPLICATE KEY UPDATE
                invitacion_archivo = VALUES(invitacion_archivo),
                asociacion_id = VALUES(asociacion_id),
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
}
