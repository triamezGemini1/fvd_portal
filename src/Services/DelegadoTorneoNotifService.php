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
    /** @var bool|null Cache por petición: existe columna torneosact.finalizado_en */
    private static ?bool $torneoFinalizadoColumnExists = null;
    /** @var bool|null Cache por petición: existe columna torneosact.es_campeonato */
    private static ?bool $torneoEsCampeonatoColumnExists = null;

    /**
     * Fragmento SQL: torneo aún no dado de baja por cierre (sin columna → sin filtro).
     */
    public static function sqlTorneoNotificacionAbierto(PDO $pdo, string $torneoAlias = 't'): string
    {
        if (self::$torneoFinalizadoColumnExists === null) {
            if (!class_exists(TorneoFinalizacionService::class, false)) {
                require_once __DIR__ . '/TorneoFinalizacionService.php';
            }
            self::$torneoFinalizadoColumnExists = TorneoFinalizacionService::columnaFinalizadoExiste($pdo);
        }
        if (!self::$torneoFinalizadoColumnExists) {
            return '';
        }

        return ' AND (' . $torneoAlias . '.finalizado_en IS NULL)';
    }

    private static function esCampeonatoExpr(PDO $pdo, string $alias = 't'): string
    {
        if (self::$torneoEsCampeonatoColumnExists === null) {
            $ok = false;
            try {
                $st = $pdo->query(
                    "SELECT COUNT(*) FROM information_schema.COLUMNS
                     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'torneosact' AND COLUMN_NAME = 'es_campeonato'"
                );
                if ($st !== false) {
                    $ok = ((int) $st->fetchColumn()) > 0;
                }
            } catch (\Throwable $e) {
                $ok = false;
            }
            if (!$ok) {
                try {
                    $pdo->query('SELECT es_campeonato FROM torneosact LIMIT 0');
                    $ok = true;
                } catch (\Throwable $e2) {
                    $ok = false;
                }
            }
            self::$torneoEsCampeonatoColumnExists = $ok;
        }
        if (self::$torneoEsCampeonatoColumnExists) {
            return 'COALESCE(' . $alias . '.es_campeonato, 0)';
        }

        return '0';
    }

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
     * Tabla maestra de nombre nominal por grupo (vinculación explícita de campeonatos).
     */
    public static function ensureCampeonatoGrupoTable(PDO $pdo): void
    {
        $sqlPath = dirname(__DIR__, 2) . '/fvdmasteradmin/sql/install_fvd_campeonato_grupo.sql';
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
            error_log('[DelegadoTorneoNotifService] ensureCampeonatoGrupoTable: ' . $e->getMessage());
        }
    }

    /**
     * Tras vincular campeonatos al mismo grupo: asegura una notificación por cada torneo del grupo
     * para los delegados que ya tenían al menos una invitación en ese conjunto, y refresca fechas/no visto.
     *
     * @param list<int> $torneoIds
     */
    public static function sincronizarInvitacionesTrasVincularGrupo(PDO $pdo, array $torneoIds): void
    {
        $ids = [];
        foreach ($torneoIds as $x) {
            $n = (int) $x;
            if ($n > 0) {
                $ids[$n] = $n;
            }
        }
        $ids = array_values($ids);
        if (count($ids) < 2) {
            return;
        }
        self::ensureTable($pdo);
        self::ensureTokenColumns($pdo);
        self::ensureAceptacionColumn($pdo);
        $ph = implode(',', array_fill(0, count($ids), '?'));
        try {
            $st = $pdo->prepare("SELECT DISTINCT delegado_id FROM fvd_delegado_notif_torneo WHERE torneo_id IN ($ph)");
            $st->execute($ids);
        } catch (PDOException $e) {
            error_log('[DelegadoTorneoNotifService] sincronizarInvitaciones delegados: ' . $e->getMessage());

            return;
        }
        $delegados = [];
        while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
            $d = (int) ($row['delegado_id'] ?? 0);
            if ($d > 0) {
                $delegados[$d] = $d;
            }
        }
        $delegados = array_values($delegados);
        if ($delegados === []) {
            return;
        }
        $stInv = $pdo->prepare('SELECT invitacion FROM torneosact WHERE torneo = :t LIMIT 1');
        $stAsoc = $pdo->prepare('SELECT asociacion_id FROM delegados WHERE id = :id AND activo = 1 LIMIT 1');
        $ins = $pdo->prepare(
            'INSERT INTO fvd_delegado_notif_torneo (delegado_id, torneo_id, invitacion_archivo, access_token, asociacion_id)
             VALUES (:d, :t, :inv, :tok, :a)
             ON DUPLICATE KEY UPDATE
                invitacion_archivo = VALUES(invitacion_archivo),
                asociacion_id = VALUES(asociacion_id),
                creado_en = CURRENT_TIMESTAMP,
                visto_en = NULL,
                invitacion_aceptada_en = NULL,
                access_token = IFNULL(fvd_delegado_notif_torneo.access_token, VALUES(access_token))'
        );
        foreach ($delegados as $did) {
            $stAsoc->execute([':id' => $did]);
            $aid = (int) $stAsoc->fetchColumn();
            if ($aid <= 0) {
                continue;
            }
            foreach ($ids as $tid) {
                $stInv->execute([':t' => $tid]);
                $inv = $stInv->fetchColumn();
                $invFile = $inv !== false && $inv !== null && trim((string) $inv) !== '' ? trim((string) $inv) : null;
                $token = bin2hex(random_bytes(32));
                try {
                    $ins->execute([':d' => $did, ':t' => $tid, ':inv' => $invFile, ':tok' => $token, ':a' => $aid]);
                } catch (PDOException $e) {
                    error_log('[DelegadoTorneoNotifService] sincronizarInvitaciones upsert: ' . $e->getMessage());
                }
            }
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
     * El delegado debe confirmar la invitación antes de usar el enlace de inscripción.
     */
    public static function ensureAceptacionColumn(PDO $pdo): void
    {
        self::ensureTable($pdo);
        try {
            $pdo->exec(
                'ALTER TABLE fvd_delegado_notif_torneo ADD COLUMN invitacion_aceptada_en TIMESTAMP NULL DEFAULT NULL COMMENT \'Confirmación explícita en panel\''
            );
        } catch (PDOException $e) {
            if (stripos($e->getMessage(), 'Duplicate column') === false) {
                error_log('[DelegadoTorneoNotifService] ensureAceptacionColumn: ' . $e->getMessage());
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
        self::ensureAceptacionColumn($pdo);

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
                invitacion_aceptada_en = NULL,
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
     * Solo delegados activos que aún no tienen fila para este torneo (p. ej. alta posterior al primer despacho).
     *
     * @return array{insertados: int, nuevos_delegado_ids: list<int>}
     */
    public static function crearNotificacionesFaltantesParaTorneo(PDO $pdo, int $torneoId): array
    {
        if ($torneoId <= 0) {
            return ['insertados' => 0, 'nuevos_delegado_ids' => []];
        }
        self::ensureTable($pdo);
        self::ensureTokenColumns($pdo);
        self::ensureAceptacionColumn($pdo);

        $st = $pdo->prepare('SELECT invitacion FROM torneosact WHERE torneo = :t LIMIT 1');
        $st->execute([':t' => $torneoId]);
        $inv = $st->fetchColumn();
        $invFile = $inv !== false && $inv !== null && trim((string) $inv) !== '' ? trim((string) $inv) : null;

        $sql = 'SELECT d.id, d.asociacion_id FROM delegados d
            WHERE d.activo = 1 AND d.asociacion_id IS NOT NULL AND d.asociacion_id > 0
              AND NOT EXISTS (
                  SELECT 1 FROM fvd_delegado_notif_torneo n
                  WHERE n.delegado_id = d.id AND n.torneo_id = :t
              )';
        $stD = $pdo->prepare($sql);
        $stD->execute([':t' => $torneoId]);
        $ins = $pdo->prepare(
            'INSERT INTO fvd_delegado_notif_torneo (delegado_id, torneo_id, invitacion_archivo, access_token, asociacion_id)
             VALUES (:d, :t, :inv, :tok, :a)'
        );
        $n = 0;
        $ids = [];
        while ($row = $stD->fetch(PDO::FETCH_ASSOC)) {
            $did = (int) ($row['id'] ?? 0);
            $aid = (int) ($row['asociacion_id'] ?? 0);
            if ($did <= 0 || $aid <= 0) {
                continue;
            }
            $token = bin2hex(random_bytes(32));
            try {
                $ins->execute([':d' => $did, ':t' => $torneoId, ':inv' => $invFile, ':tok' => $token, ':a' => $aid]);
                if ($ins->rowCount() > 0) {
                    ++$n;
                    $ids[] = $did;
                }
            } catch (PDOException $e) {
                error_log('[DelegadoTorneoNotifService] crearFaltantes: ' . $e->getMessage());
            }
        }

        return ['insertados' => $n, 'nuevos_delegado_ids' => $ids];
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
            $abi = self::sqlTorneoNotificacionAbierto($pdo, 't');
            $st = $pdo->prepare(
                'SELECT n.* FROM fvd_delegado_notif_torneo n
                 INNER JOIN torneosact t ON t.torneo = n.torneo_id
                 WHERE n.access_token IS NOT NULL AND n.access_token = :tok' . $abi . ' LIMIT 1'
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
            $abi = self::sqlTorneoNotificacionAbierto($pdo, 't');
            $st = $pdo->prepare(
                'SELECT 1 FROM fvd_delegado_notif_torneo n
                 INNER JOIN torneosact t ON t.torneo = n.torneo_id
                 WHERE n.delegado_id = :d AND n.torneo_id = :t' . $abi . ' LIMIT 1'
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
            $abi = self::sqlTorneoNotificacionAbierto($pdo, 't');
            $st = $pdo->prepare(
                'SELECT 1 FROM fvd_delegado_notif_torneo n
                 INNER JOIN torneosact t ON t.torneo = n.torneo_id
                 INNER JOIN delegados d ON d.id = n.delegado_id
                 WHERE n.delegado_id = :d AND d.asociacion_id = :a AND t.grupo_evento_id = :g' . $abi . ' LIMIT 1'
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
    public static function notificacionPorIdParaDelegado(PDO $pdo, int $notifId, int $delegadoId, ?int $asociacionId = null): ?array
    {
        if ($notifId <= 0 || ($delegadoId <= 0 && ($asociacionId === null || $asociacionId <= 0))) {
            return null;
        }
        self::ensureTable($pdo);
        self::ensureCampeonatoGrupoTable($pdo);
        $abi = self::sqlTorneoNotificacionAbierto($pdo, 't');
        $esCampExpr = self::esCampeonatoExpr($pdo, 't');
        $selTorneo = 'COALESCE(NULLIF(TRIM(cg.nombre_nominal), \'\'), t.nombre) AS torneo_nombre, t.nombre AS torneo_rama_nombre, COALESCE(t.grupo_evento_id, 0) AS grupo_evento_id, ' . $esCampExpr . ' AS es_campeonato, NULLIF(TRIM(cg.nombre_nominal), \'\') AS campeonato_nominal';
        try {
            if ($asociacionId !== null && $asociacionId > 0) {
                $st = $pdo->prepare(
                    "SELECT n.*, {$selTorneo}, t.fechator, t.lugar
                     FROM fvd_delegado_notif_torneo n
                     INNER JOIN torneosact t ON t.torneo = n.torneo_id
                     LEFT JOIN fvd_campeonato_grupo cg ON cg.grupo_evento_id = t.grupo_evento_id
                     INNER JOIN delegados del ON del.id = n.delegado_id
                     WHERE n.id = :id AND del.asociacion_id = :a AND del.activo = 1" . $abi . ' LIMIT 1'
                );
                $st->execute([':id' => $notifId, ':a' => $asociacionId]);
            } else {
                $st = $pdo->prepare(
                    "SELECT n.*, {$selTorneo}, t.fechator, t.lugar
                     FROM fvd_delegado_notif_torneo n
                     INNER JOIN torneosact t ON t.torneo = n.torneo_id
                     LEFT JOIN fvd_campeonato_grupo cg ON cg.grupo_evento_id = t.grupo_evento_id
                     WHERE n.id = :id AND n.delegado_id = :d" . $abi . ' LIMIT 1'
                );
                $st->execute([':id' => $notifId, ':d' => $delegadoId]);
            }
            $r = $st->fetch(PDO::FETCH_ASSOC);

            return $r !== false ? $r : null;
        } catch (PDOException $e) {
            error_log('[DelegadoTorneoNotifService] notificacionPorIdParaDelegado: ' . $e->getMessage());

            return null;
        }
    }

    /**
     * Alcance de notificaciones: por defecto `delegado_id` = sesión.
     * Si se pasa `asociacionId` > 0, se usa la asociación del delegado (tabla `delegados`): un aviso por club aunque el id de fila no coincida con la sesión.
     *
     * @return list<array<string, mixed>>
     */
    public static function listarParaDelegado(PDO $pdo, int $delegadoId, int $limite = 30, ?int $asociacionId = null): array
    {
        if ($delegadoId <= 0 && ($asociacionId === null || $asociacionId <= 0)) {
            return [];
        }
        self::ensureTable($pdo);
        self::ensureAceptacionColumn($pdo);
        self::ensureCampeonatoGrupoTable($pdo);
        $limite = max(1, min(100, $limite));
        $abi = self::sqlTorneoNotificacionAbierto($pdo, 't');
        $esCampExpr = self::esCampeonatoExpr($pdo, 't');
        try {
            if ($asociacionId !== null && $asociacionId > 0) {
                $st = $pdo->prepare(
                    'SELECT n.id, n.torneo_id, n.invitacion_archivo, n.creado_en, n.visto_en, n.invitacion_aceptada_en,
                        COALESCE(NULLIF(TRIM(cg.nombre_nominal), \'\'), t.nombre) AS torneo_nombre,
                        t.nombre AS torneo_rama_nombre, t.fechator, t.lugar,
                        COALESCE(t.grupo_evento_id, 0) AS grupo_evento_id,
                        ' . $esCampExpr . ' AS es_campeonato,
                        NULLIF(TRIM(cg.nombre_nominal), \'\') AS campeonato_nominal
                     FROM fvd_delegado_notif_torneo n
                     INNER JOIN torneosact t ON t.torneo = n.torneo_id
                     LEFT JOIN fvd_campeonato_grupo cg ON cg.grupo_evento_id = t.grupo_evento_id
                     INNER JOIN delegados del ON del.id = n.delegado_id
                     WHERE del.asociacion_id = :a AND del.activo = 1' . $abi . '
                     ORDER BY n.creado_en DESC
                     LIMIT ' . (int) $limite
                );
                $st->execute([':a' => $asociacionId]);
            } else {
                $st = $pdo->prepare(
                    'SELECT n.id, n.torneo_id, n.invitacion_archivo, n.creado_en, n.visto_en, n.invitacion_aceptada_en,
                        COALESCE(NULLIF(TRIM(cg.nombre_nominal), \'\'), t.nombre) AS torneo_nombre,
                        t.nombre AS torneo_rama_nombre, t.fechator, t.lugar,
                        COALESCE(t.grupo_evento_id, 0) AS grupo_evento_id,
                        ' . $esCampExpr . ' AS es_campeonato,
                        NULLIF(TRIM(cg.nombre_nominal), \'\') AS campeonato_nominal
                     FROM fvd_delegado_notif_torneo n
                     INNER JOIN torneosact t ON t.torneo = n.torneo_id
                     LEFT JOIN fvd_campeonato_grupo cg ON cg.grupo_evento_id = t.grupo_evento_id
                     WHERE n.delegado_id = :d' . $abi . '
                     ORDER BY n.creado_en DESC
                     LIMIT ' . (int) $limite
                );
                $st->execute([':d' => $delegadoId]);
            }

            return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (PDOException $e) {
            error_log('[DelegadoTorneoNotifService] listar: ' . $e->getMessage());

            return [];
        }
    }

    /**
     * Invitaciones sin abrir (JOIN torneosact para grupo), orden reciente.
     *
     * @return list<array<string, mixed>>
     */
    private static function filasNotificacionesSinVista(PDO $pdo, int $delegadoId, ?int $asociacionId, int $limite = 200): array
    {
        if ($delegadoId <= 0 && ($asociacionId === null || $asociacionId <= 0)) {
            return [];
        }
        self::ensureTable($pdo);
        self::ensureCampeonatoGrupoTable($pdo);
        $limite = max(1, min(500, $limite));
        $abi = self::sqlTorneoNotificacionAbierto($pdo, 't');
        $esCampExpr = self::esCampeonatoExpr($pdo, 't');
        try {
            if ($asociacionId !== null && $asociacionId > 0) {
                $st = $pdo->prepare(
                    'SELECT n.id, n.torneo_id, n.invitacion_archivo, n.creado_en, n.visto_en,
                        COALESCE(NULLIF(TRIM(cg.nombre_nominal), \'\'), t.nombre) AS torneo_nombre,
                        t.nombre AS torneo_rama_nombre, t.fechator, t.lugar,
                        COALESCE(t.grupo_evento_id, 0) AS grupo_evento_id,
                        ' . $esCampExpr . ' AS es_campeonato,
                        NULLIF(TRIM(cg.nombre_nominal), \'\') AS campeonato_nominal
                     FROM fvd_delegado_notif_torneo n
                     INNER JOIN torneosact t ON t.torneo = n.torneo_id
                     LEFT JOIN fvd_campeonato_grupo cg ON cg.grupo_evento_id = t.grupo_evento_id
                     INNER JOIN delegados del ON del.id = n.delegado_id
                     WHERE del.asociacion_id = :a AND del.activo = 1 AND n.visto_en IS NULL' . $abi . '
                     ORDER BY n.creado_en DESC
                     LIMIT ' . (int) $limite
                );
                $st->execute([':a' => $asociacionId]);
            } else {
                $st = $pdo->prepare(
                    'SELECT n.id, n.torneo_id, n.invitacion_archivo, n.creado_en, n.visto_en,
                        COALESCE(NULLIF(TRIM(cg.nombre_nominal), \'\'), t.nombre) AS torneo_nombre,
                        t.nombre AS torneo_rama_nombre, t.fechator, t.lugar,
                        COALESCE(t.grupo_evento_id, 0) AS grupo_evento_id,
                        ' . $esCampExpr . ' AS es_campeonato,
                        NULLIF(TRIM(cg.nombre_nominal), \'\') AS campeonato_nominal
                     FROM fvd_delegado_notif_torneo n
                     INNER JOIN torneosact t ON t.torneo = n.torneo_id
                     LEFT JOIN fvd_campeonato_grupo cg ON cg.grupo_evento_id = t.grupo_evento_id
                     WHERE n.delegado_id = :d AND n.visto_en IS NULL' . $abi . '
                     ORDER BY n.creado_en DESC
                     LIMIT ' . (int) $limite
                );
                $st->execute([':d' => $delegadoId]);
            }

            return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (PDOException $e) {
            error_log('[DelegadoTorneoNotifService] filasNotificacionesSinVista: ' . $e->getMessage());

            return [];
        }
    }

    /**
     * Agrupa invitaciones de campeonato: mismo grupo_evento_id + misma fecha de realización + mismo flag es_campeonato.
     */
    private static function claveAgrupacionInvitacion(array $r): string
    {
        $g = (int) ($r['grupo_evento_id'] ?? 0);
        if ($g <= 0) {
            return 's:' . (int) ($r['id'] ?? 0);
        }
        $f = substr((string) ($r['fechator'] ?? ''), 0, 10);
        if ($f === '') {
            $f = '_';
        }
        $c = (int) ($r['es_campeonato'] ?? 0);

        return 'g:' . $g . '|f:' . $f . '|c:' . $c;
    }

    /**
     * @param array<string, mixed> $r Fila ya fusionada o individual
     *
     * @return array<string, mixed>
     */
    private static function enriquecerInvitacionListado(array $r): array
    {
        $tid = (int) ($r['torneo_id'] ?? 0);
        $det = [];
        if (isset($r['detalle_torneos']) && is_array($r['detalle_torneos'])) {
            foreach ($r['detalle_torneos'] as $d) {
                if (!is_array($d)) {
                    continue;
                }
                $det[] = [
                    'id'        => (int) ($d['id'] ?? 0),
                    'torneo_id' => (int) ($d['torneo_id'] ?? 0),
                    'rama'      => trim((string) ($d['rama'] ?? '')),
                ];
            }
        }
        if ($det === []) {
            $det[] = [
                'id'        => (int) ($r['id'] ?? 0),
                'torneo_id' => $tid,
                'rama'      => trim((string) ($r['torneo_rama_nombre'] ?? '')),
            ];
        }
        $tit = trim((string) ($r['titulo_notificacion'] ?? ''));
        if ($tit === '') {
            $cn = trim((string) ($r['campeonato_nominal'] ?? ''));
            if ($cn !== '') {
                $tit = $cn;
            } else {
                $tn = trim((string) ($r['torneo_nombre'] ?? ''));
                $tit = $tn !== '' ? $tn : ($tid > 0 ? 'Torneo #' . $tid : 'Invitación');
            }
        }
        $r['titulo_notificacion'] = $tit;
        $r['detalle_torneos'] = $det;

        return $r;
    }

    /**
     * @param list<array<string, mixed>> $rows
     *
     * @return array<string, mixed>
     */
    private static function fusionarFilasGrupoMismoEvento(array $rows): array
    {
        $ids = [];
        $torneoIds = [];
        $ramas = [];
        $maxCreado = '';
        $invFile = '';
        $tidRep = 0;
        $nidRep = 0;
        $g = 0;
        foreach ($rows as $r) {
            $ids[] = (int) ($r['id'] ?? 0);
            $tid = (int) ($r['torneo_id'] ?? 0);
            if ($tid > 0) {
                $torneoIds[] = $tid;
            }
            $rama = trim((string) ($r['torneo_rama_nombre'] ?? ''));
            if ($rama !== '' && !in_array($rama, $ramas, true)) {
                $ramas[] = $rama;
            }
            $ce = (string) ($r['creado_en'] ?? '');
            if ($ce !== '' && ($maxCreado === '' || $ce > $maxCreado)) {
                $maxCreado = $ce;
            }
            $inv = trim((string) ($r['invitacion_archivo'] ?? ''));
            if ($inv !== '' && $invFile === '') {
                $invFile = $inv;
            }
            $g = (int) ($r['grupo_evento_id'] ?? 0);
        }
        sort($ids);
        $tidRep = $torneoIds !== [] ? (int) min($torneoIds) : 0;
        foreach ($rows as $r) {
            if ((int) ($r['torneo_id'] ?? 0) === $tidRep) {
                $nidRep = (int) ($r['id'] ?? 0);

                break;
            }
        }
        if ($nidRep <= 0 && $ids !== []) {
            $nidRep = (int) min($ids);
        }
        $base = $rows[0];
        $tituloNom = '';
        foreach ($rows as $xr) {
            $cn = trim((string) ($xr['campeonato_nominal'] ?? ''));
            if ($cn !== '') {
                $tituloNom = $cn;
                break;
            }
        }
        if ($tituloNom === '') {
            $fd0 = substr((string) ($rows[0]['fechator'] ?? ''), 0, 10);
            $ec0 = (int) ($rows[0]['es_campeonato'] ?? 0) === 1;
            if ($ec0 && $fd0 !== '') {
                $tituloNom = 'Campeonato — ' . $fd0;
            } else {
                $tituloNom = trim((string) ($rows[0]['torneo_nombre'] ?? ''));
            }
            if ($tituloNom === '') {
                $tituloNom = 'Torneo #' . (int) ($rows[0]['torneo_id'] ?? 0);
            }
        }
        $detalle = [];
        foreach ($rows as $xr) {
            $detalle[] = [
                'id'        => (int) ($xr['id'] ?? 0),
                'torneo_id' => (int) ($xr['torneo_id'] ?? 0),
                'rama'      => trim((string) ($xr['torneo_rama_nombre'] ?? '')),
            ];
        }
        usort(
            $detalle,
            static function (array $a, array $b): int {
                return ($a['torneo_id'] <=> $b['torneo_id']) ?: ($a['id'] <=> $b['id']);
            }
        );

        return array_merge($base, [
            'id' => $nidRep,
            'torneo_id' => $tidRep,
            'torneo_nombre' => $tituloNom,
            'titulo_notificacion' => $tituloNom,
            'detalle_torneos' => $detalle,
            'invitacion_archivo' => $invFile !== '' ? $invFile : ($base['invitacion_archivo'] ?? null),
            'creado_en' => $maxCreado !== '' ? $maxCreado : ($base['creado_en'] ?? ''),
            'visto_en' => null,
            'es_grupo_agrupado' => true,
            'grupo_evento_id' => $g,
            'n_en_grupo' => count($rows),
            'rama_subtitulo' => $ramas !== [] ? implode(' · ', $ramas) : '',
        ]);
    }

    /**
     * Listado para UI: una fila por torneo sin grupo; con grupo, una fila por (grupo + fecha + campeonato).
     *
     * @return list<array<string, mixed>>
     */
    public static function listarParaDelegadoVistaAgrupada(PDO $pdo, int $delegadoId, int $limite = 40, ?int $asociacionId = null): array
    {
        $raw = self::listarParaDelegado($pdo, $delegadoId, max(80, $limite * 4), $asociacionId);
        if ($raw === []) {
            return [];
        }
        $porClave = [];
        foreach ($raw as $r) {
            $k = self::claveAgrupacionInvitacion($r);
            if (!isset($porClave[$k])) {
                $porClave[$k] = [];
            }
            $porClave[$k][] = $r;
        }
        $out = [];
        foreach ($porClave as $pack) {
            if (count($pack) === 1) {
                $out[] = self::enriquecerInvitacionListado(array_merge($pack[0], ['es_grupo_agrupado' => false]));

                continue;
            }
            $out[] = self::enriquecerInvitacionListado(self::fusionarFilasGrupoMismoEvento($pack));
        }
        usort(
            $out,
            static function (array $a, array $b): int {
                $ca = (string) ($a['creado_en'] ?? '');
                $cb = (string) ($b['creado_en'] ?? '');

                return strcmp($cb, $ca);
            }
        );

        return array_slice($out, 0, max(1, $limite));
    }

    /**
     * Pendientes para indicador: una unidad visual por invitación individual o por bloque agrupado.
     */
    public static function contarPendientesVistaAgrupada(PDO $pdo, int $delegadoId, ?int $asociacionId = null): int
    {
        $filas = self::filasNotificacionesSinVista($pdo, $delegadoId, $asociacionId, 400);
        if ($filas === []) {
            return 0;
        }
        $claves = [];
        foreach ($filas as $r) {
            $claves[self::claveAgrupacionInvitacion($r)] = true;
        }

        return count($claves);
    }

    /**
     * @param list<array<string, mixed>> $filasSinVista
     *
     * @return list<array<string, mixed>>
     */
    private static function representantesVisualesDesdeFilasSinVista(array $filasSinVista): array
    {
        if ($filasSinVista === []) {
            return [];
        }
        $porClave = [];
        foreach ($filasSinVista as $r) {
            $k = self::claveAgrupacionInvitacion($r);
            if (!isset($porClave[$k])) {
                $porClave[$k] = [];
            }
            $porClave[$k][] = $r;
        }
        $candidatos = [];
        foreach ($porClave as $pack) {
            $candidatos[] = count($pack) > 1
                ? self::fusionarFilasGrupoMismoEvento($pack)
                : array_merge($pack[0], ['es_grupo_agrupado' => false]);
        }
        foreach ($candidatos as $i => $c) {
            $candidatos[$i] = self::enriquecerInvitacionListado($c);
        }
        usort(
            $candidatos,
            static function (array $a, array $b): int {
                return strcmp((string) ($b['creado_en'] ?? ''), (string) ($a['creado_en'] ?? ''));
            }
        );

        return $candidatos;
    }

    /**
     * Torneo + grupo sugeridos desde invitaciones aún no vistas (p. ej. fijar sesión al abrir el panel).
     *
     * @return array{torneo_id:int, grupo_evento_id:int}
     */
    public static function sugerirContextoDesdeInvitacionesPendientes(PDO $pdo, int $delegadoId, ?int $asociacionId): array
    {
        $filas = self::filasNotificacionesSinVista($pdo, $delegadoId, $asociacionId, 100);
        $candidatos = self::representantesVisualesDesdeFilasSinVista($filas);
        if ($candidatos === []) {
            return ['torneo_id' => 0, 'grupo_evento_id' => 0];
        }
        $top = $candidatos[0];
        $tid = (int) ($top['torneo_id'] ?? 0);
        $gid = (int) ($top['grupo_evento_id'] ?? 0);

        return [
            'torneo_id' => $tid > 0 ? $tid : 0,
            'grupo_evento_id' => $gid > 0 ? $gid : 0,
        ];
    }

    /**
     * Marca vistas las notificaciones del mismo bloque de campeonato: grupo_evento_id y, si se indican, misma fecha de torneo y mismo es_campeonato.
     *
     * @param string|null $fechatorDia Fecha en formato Y-m-d; si null o vacío solo filtra por grupo (compatibilidad).
     * @param int|null    $esCampeonato Si no es null y $fechatorDia es válido, restringe por COALESCE(t.es_campeonato,0).
     */
    public static function marcarVistoTodasMismoGrupo(
        PDO $pdo,
        int $delegadoId,
        ?int $asociacionId,
        int $grupoEventoId,
        ?string $fechatorDia = null,
        ?int $esCampeonato = null
    ): void {
        if ($grupoEventoId <= 0 || ($delegadoId <= 0 && ($asociacionId === null || $asociacionId <= 0))) {
            return;
        }
        self::ensureTable($pdo);
        $fd = $fechatorDia !== null ? trim($fechatorDia) : '';
        $filtraFechaCamp = $fd !== '' && $esCampeonato !== null;
        $esCampExpr = self::esCampeonatoExpr($pdo, 't');
        try {
            if ($asociacionId !== null && $asociacionId > 0) {
                $sql = 'UPDATE fvd_delegado_notif_torneo n
                     INNER JOIN torneosact t ON t.torneo = n.torneo_id
                     INNER JOIN delegados del ON del.id = n.delegado_id
                     SET n.visto_en = COALESCE(n.visto_en, NOW())
                     WHERE del.asociacion_id = :a AND del.activo = 1
                       AND COALESCE(t.grupo_evento_id, 0) = :g';
                if ($filtraFechaCamp) {
                    $sql .= ' AND DATE(t.fechator) = :fd AND ' . $esCampExpr . ' = :ec';
                }
                $st = $pdo->prepare($sql);
                $bind = [':a' => $asociacionId, ':g' => $grupoEventoId];
                if ($filtraFechaCamp) {
                    $bind[':fd'] = $fd;
                    $bind[':ec'] = (int) $esCampeonato;
                }
                $st->execute($bind);
            } else {
                $sql = 'UPDATE fvd_delegado_notif_torneo n
                     INNER JOIN torneosact t ON t.torneo = n.torneo_id
                     SET n.visto_en = COALESCE(n.visto_en, NOW())
                     WHERE n.delegado_id = :d AND COALESCE(t.grupo_evento_id, 0) = :g';
                if ($filtraFechaCamp) {
                    $sql .= ' AND DATE(t.fechator) = :fd AND ' . $esCampExpr . ' = :ec';
                }
                $st = $pdo->prepare($sql);
                $bind = [':d' => $delegadoId, ':g' => $grupoEventoId];
                if ($filtraFechaCamp) {
                    $bind[':fd'] = $fd;
                    $bind[':ec'] = (int) $esCampeonato;
                }
                $st->execute($bind);
            }
        } catch (PDOException $e) {
            error_log('[DelegadoTorneoNotifService] marcarVistoTodasMismoGrupo: ' . $e->getMessage());
        }
    }

    public static function contarNoVistas(PDO $pdo, int $delegadoId, ?int $asociacionId = null): int
    {
        if ($delegadoId <= 0 && ($asociacionId === null || $asociacionId <= 0)) {
            return 0;
        }
        self::ensureTable($pdo);
        $abi = self::sqlTorneoNotificacionAbierto($pdo, 't');
        try {
            if ($asociacionId !== null && $asociacionId > 0) {
                $st = $pdo->prepare(
                    'SELECT COUNT(*) FROM fvd_delegado_notif_torneo n
                     INNER JOIN torneosact t ON t.torneo = n.torneo_id
                     INNER JOIN delegados del ON del.id = n.delegado_id
                     WHERE del.asociacion_id = :a AND del.activo = 1 AND n.visto_en IS NULL' . $abi
                );
                $st->execute([':a' => $asociacionId]);
            } else {
                $st = $pdo->prepare(
                    'SELECT COUNT(*) FROM fvd_delegado_notif_torneo n
                     INNER JOIN torneosact t ON t.torneo = n.torneo_id
                     WHERE n.delegado_id = :d AND n.visto_en IS NULL' . $abi
                );
                $st->execute([':d' => $delegadoId]);
            }

            return (int) $st->fetchColumn();
        } catch (PDOException $e) {
            return 0;
        }
    }

    /**
     * @return array<string, mixed>|null Invitación pendiente más reciente (una sola entrada si los torneos comparten grupo).
     */
    public static function ultimaNoVista(PDO $pdo, int $delegadoId, ?int $asociacionId = null): ?array
    {
        if ($delegadoId <= 0 && ($asociacionId === null || $asociacionId <= 0)) {
            return null;
        }
        $filas = self::filasNotificacionesSinVista($pdo, $delegadoId, $asociacionId, 100);
        $candidatos = self::representantesVisualesDesdeFilasSinVista($filas);

        return $candidatos[0] ?? null;
    }

    public static function marcarVisto(PDO $pdo, int $notifId, int $delegadoId, ?int $asociacionId = null): void
    {
        if ($notifId <= 0 || ($delegadoId <= 0 && ($asociacionId === null || $asociacionId <= 0))) {
            return;
        }
        self::ensureTable($pdo);
        try {
            if ($asociacionId !== null && $asociacionId > 0) {
                $st = $pdo->prepare(
                    'UPDATE fvd_delegado_notif_torneo n
                     INNER JOIN delegados del ON del.id = n.delegado_id
                     SET n.visto_en = COALESCE(n.visto_en, NOW())
                     WHERE n.id = :id AND del.asociacion_id = :a AND del.activo = 1'
                );
                $st->execute([':id' => $notifId, ':a' => $asociacionId]);
            } else {
                $st = $pdo->prepare(
                    'UPDATE fvd_delegado_notif_torneo SET visto_en = COALESCE(visto_en, NOW()) WHERE id = :id AND delegado_id = :d'
                );
                $st->execute([':id' => $notifId, ':d' => $delegadoId]);
            }
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

    /**
     * Hay invitación al torneo en contexto y aún no se pulsó «Aceptar invitación».
     */
    public static function invitacionPendienteDeAceptacion(PDO $pdo, int $delegadoId, int $torneoId, ?int $asociacionId = null): bool
    {
        if ($torneoId <= 0) {
            return false;
        }
        self::ensureTable($pdo);
        self::ensureAceptacionColumn($pdo);
        $abi = self::sqlTorneoNotificacionAbierto($pdo, 't');
        try {
            if ($asociacionId !== null && $asociacionId > 0) {
                $st = $pdo->prepare(
                    'SELECT 1 FROM fvd_delegado_notif_torneo n
                     INNER JOIN torneosact t ON t.torneo = n.torneo_id
                     INNER JOIN delegados del ON del.id = n.delegado_id
                     WHERE n.torneo_id = :t AND del.asociacion_id = :a AND del.activo = 1
                       AND n.invitacion_aceptada_en IS NULL' . $abi . '
                     LIMIT 1'
                );
                $st->execute([':t' => $torneoId, ':a' => $asociacionId]);
            } else {
                if ($delegadoId <= 0) {
                    return false;
                }
                $st = $pdo->prepare(
                    'SELECT 1 FROM fvd_delegado_notif_torneo n
                     INNER JOIN torneosact t ON t.torneo = n.torneo_id
                     WHERE n.delegado_id = :d AND n.torneo_id = :t AND n.invitacion_aceptada_en IS NULL' . $abi . '
                     LIMIT 1'
                );
                $st->execute([':d' => $delegadoId, ':t' => $torneoId]);
            }

            return (bool) $st->fetchColumn();
        } catch (PDOException $e) {
            error_log('[DelegadoTorneoNotifService] invitacionPendienteDeAceptacion: ' . $e->getMessage());

            return false;
        }
    }

    public static function marcarInvitacionAceptada(PDO $pdo, int $delegadoId, int $torneoId, ?int $asociacionId = null): bool
    {
        if ($torneoId <= 0 || ($delegadoId <= 0 && ($asociacionId === null || $asociacionId <= 0))) {
            return false;
        }
        self::ensureTable($pdo);
        self::ensureAceptacionColumn($pdo);
        try {
            if ($asociacionId !== null && $asociacionId > 0) {
                $st = $pdo->prepare(
                    'UPDATE fvd_delegado_notif_torneo n
                     INNER JOIN delegados del ON del.id = n.delegado_id
                     SET n.invitacion_aceptada_en = NOW()
                     WHERE n.torneo_id = :t AND del.asociacion_id = :a AND del.activo = 1'
                );
                $st->execute([':t' => $torneoId, ':a' => $asociacionId]);
            } else {
                $st = $pdo->prepare(
                    'UPDATE fvd_delegado_notif_torneo SET invitacion_aceptada_en = NOW()
                     WHERE delegado_id = :d AND torneo_id = :t'
                );
                $st->execute([':d' => $delegadoId, ':t' => $torneoId]);
            }

            return $st->rowCount() > 0;
        } catch (PDOException $e) {
            error_log('[DelegadoTorneoNotifService] marcarInvitacionAceptada: ' . $e->getMessage());

            return false;
        }
    }

    /**
     * Asegura `access_token` no vacío (≥32 hex) para la fila `fvd_delegado_notif_torneo.id`.
     */
    public static function asegurarAccessTokenParaNotificacion(PDO $pdo, int $notifId): ?string
    {
        if ($notifId <= 0) {
            return null;
        }
        self::ensureTable($pdo);
        self::ensureTokenColumns($pdo);
        try {
            $st = $pdo->prepare('SELECT id, access_token FROM fvd_delegado_notif_torneo WHERE id = :id LIMIT 1');
            $st->execute([':id' => $notifId]);
            $r = $st->fetch(PDO::FETCH_ASSOC);
            if ($r === false) {
                return null;
            }
            $tok = trim((string) ($r['access_token'] ?? ''));
            if ($tok !== '' && strlen($tok) >= 32) {
                return $tok;
            }
            $new = bin2hex(random_bytes(32));
            $up = $pdo->prepare('UPDATE fvd_delegado_notif_torneo SET access_token = :t WHERE id = :id');
            $up->execute([':t' => $new, ':id' => $notifId]);

            return $new;
        } catch (PDOException $e) {
            error_log('[DelegadoTorneoNotifService] asegurarAccessTokenParaNotificacion: ' . $e->getMessage());

            return null;
        }
    }
}
