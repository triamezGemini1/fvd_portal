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
    public static function notificacionPorIdParaDelegado(PDO $pdo, int $notifId, int $delegadoId, ?int $asociacionId = null): ?array
    {
        if ($notifId <= 0 || ($delegadoId <= 0 && ($asociacionId === null || $asociacionId <= 0))) {
            return null;
        }
        self::ensureTable($pdo);
        self::ensureCampeonatoGrupoTable($pdo);
        $selTorneo = 'COALESCE(NULLIF(TRIM(cg.nombre_nominal), \'\'), t.nombre) AS torneo_nombre, t.nombre AS torneo_rama_nombre, COALESCE(t.grupo_evento_id, 0) AS grupo_evento_id';
        try {
            if ($asociacionId !== null && $asociacionId > 0) {
                $st = $pdo->prepare(
                    "SELECT n.*, {$selTorneo}, t.fechator, t.lugar
                     FROM fvd_delegado_notif_torneo n
                     INNER JOIN torneosact t ON t.torneo = n.torneo_id
                     LEFT JOIN fvd_campeonato_grupo cg ON cg.grupo_evento_id = t.grupo_evento_id
                     INNER JOIN delegados del ON del.id = n.delegado_id
                     WHERE n.id = :id AND del.asociacion_id = :a AND del.activo = 1 LIMIT 1"
                );
                $st->execute([':id' => $notifId, ':a' => $asociacionId]);
            } else {
                $st = $pdo->prepare(
                    "SELECT n.*, {$selTorneo}, t.fechator, t.lugar
                     FROM fvd_delegado_notif_torneo n
                     INNER JOIN torneosact t ON t.torneo = n.torneo_id
                     LEFT JOIN fvd_campeonato_grupo cg ON cg.grupo_evento_id = t.grupo_evento_id
                     WHERE n.id = :id AND n.delegado_id = :d LIMIT 1"
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
        try {
            if ($asociacionId !== null && $asociacionId > 0) {
                $st = $pdo->prepare(
                    'SELECT n.id, n.torneo_id, n.invitacion_archivo, n.creado_en, n.visto_en, n.invitacion_aceptada_en,
                        COALESCE(NULLIF(TRIM(cg.nombre_nominal), \'\'), t.nombre) AS torneo_nombre,
                        t.nombre AS torneo_rama_nombre, t.fechator, t.lugar,
                        COALESCE(t.grupo_evento_id, 0) AS grupo_evento_id
                     FROM fvd_delegado_notif_torneo n
                     INNER JOIN torneosact t ON t.torneo = n.torneo_id
                     LEFT JOIN fvd_campeonato_grupo cg ON cg.grupo_evento_id = t.grupo_evento_id
                     INNER JOIN delegados del ON del.id = n.delegado_id
                     WHERE del.asociacion_id = :a AND del.activo = 1
                     ORDER BY n.creado_en DESC
                     LIMIT ' . (int) $limite
                );
                $st->execute([':a' => $asociacionId]);
            } else {
                $st = $pdo->prepare(
                    'SELECT n.id, n.torneo_id, n.invitacion_archivo, n.creado_en, n.visto_en, n.invitacion_aceptada_en,
                        COALESCE(NULLIF(TRIM(cg.nombre_nominal), \'\'), t.nombre) AS torneo_nombre,
                        t.nombre AS torneo_rama_nombre, t.fechator, t.lugar,
                        COALESCE(t.grupo_evento_id, 0) AS grupo_evento_id
                     FROM fvd_delegado_notif_torneo n
                     INNER JOIN torneosact t ON t.torneo = n.torneo_id
                     LEFT JOIN fvd_campeonato_grupo cg ON cg.grupo_evento_id = t.grupo_evento_id
                     WHERE n.delegado_id = :d
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
        try {
            if ($asociacionId !== null && $asociacionId > 0) {
                $st = $pdo->prepare(
                    'SELECT n.id, n.torneo_id, n.invitacion_archivo, n.creado_en, n.visto_en,
                        COALESCE(NULLIF(TRIM(cg.nombre_nominal), \'\'), t.nombre) AS torneo_nombre,
                        t.nombre AS torneo_rama_nombre, t.fechator, t.lugar,
                        COALESCE(t.grupo_evento_id, 0) AS grupo_evento_id
                     FROM fvd_delegado_notif_torneo n
                     INNER JOIN torneosact t ON t.torneo = n.torneo_id
                     LEFT JOIN fvd_campeonato_grupo cg ON cg.grupo_evento_id = t.grupo_evento_id
                     INNER JOIN delegados del ON del.id = n.delegado_id
                     WHERE del.asociacion_id = :a AND del.activo = 1 AND n.visto_en IS NULL
                     ORDER BY n.creado_en DESC
                     LIMIT ' . (int) $limite
                );
                $st->execute([':a' => $asociacionId]);
            } else {
                $st = $pdo->prepare(
                    'SELECT n.id, n.torneo_id, n.invitacion_archivo, n.creado_en, n.visto_en,
                        COALESCE(NULLIF(TRIM(cg.nombre_nominal), \'\'), t.nombre) AS torneo_nombre,
                        t.nombre AS torneo_rama_nombre, t.fechator, t.lugar,
                        COALESCE(t.grupo_evento_id, 0) AS grupo_evento_id
                     FROM fvd_delegado_notif_torneo n
                     INNER JOIN torneosact t ON t.torneo = n.torneo_id
                     LEFT JOIN fvd_campeonato_grupo cg ON cg.grupo_evento_id = t.grupo_evento_id
                     WHERE n.delegado_id = :d AND n.visto_en IS NULL
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

        return array_merge($base, [
            'id' => $nidRep,
            'torneo_id' => $tidRep,
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
     * Listado para UI: torneos no vinculados → una fila cada uno; mismo grupo_evento_id → una sola fila.
     *
     * @return list<array<string, mixed>>
     */
    public static function listarParaDelegadoVistaAgrupada(PDO $pdo, int $delegadoId, int $limite = 40, ?int $asociacionId = null): array
    {
        $raw = self::listarParaDelegado($pdo, $delegadoId, max(60, $limite * 3), $asociacionId);
        if ($raw === []) {
            return [];
        }
        $porGrupo = [];
        $solos = [];
        foreach ($raw as $r) {
            $g = (int) ($r['grupo_evento_id'] ?? 0);
            if ($g <= 0) {
                $solos[] = array_merge($r, ['es_grupo_agrupado' => false]);

                continue;
            }
            if (!isset($porGrupo[$g])) {
                $porGrupo[$g] = [];
            }
            $porGrupo[$g][] = $r;
        }
        $out = [];
        foreach ($porGrupo as $g => $pack) {
            if (count($pack) === 1) {
                $out[] = array_merge($pack[0], ['es_grupo_agrupado' => false]);

                continue;
            }
            $out[] = self::fusionarFilasGrupoMismoEvento($pack);
        }
        foreach ($solos as $s) {
            $out[] = $s;
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
     * Pendientes para badge: sin grupo cuenta 1 por fila; con grupo cuenta 1 por código de grupo.
     */
    public static function contarPendientesVistaAgrupada(PDO $pdo, int $delegadoId, ?int $asociacionId = null): int
    {
        $filas = self::filasNotificacionesSinVista($pdo, $delegadoId, $asociacionId, 400);
        if ($filas === []) {
            return 0;
        }
        $vistosGrupo = [];
        $n = 0;
        foreach ($filas as $r) {
            $g = (int) ($r['grupo_evento_id'] ?? 0);
            if ($g > 0) {
                if (!isset($vistosGrupo[$g])) {
                    $vistosGrupo[$g] = true;
                    ++$n;
                }
            } else {
                ++$n;
            }
        }

        return $n;
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
        $porGrupo = [];
        $solos = [];
        foreach ($filasSinVista as $r) {
            $g = (int) ($r['grupo_evento_id'] ?? 0);
            if ($g <= 0) {
                $solos[] = $r;
            } else {
                if (!isset($porGrupo[$g])) {
                    $porGrupo[$g] = [];
                }
                $porGrupo[$g][] = $r;
            }
        }
        $candidatos = [];
        foreach ($porGrupo as $pack) {
            $candidatos[] = count($pack) > 1
                ? self::fusionarFilasGrupoMismoEvento($pack)
                : array_merge($pack[0], ['es_grupo_agrupado' => false]);
        }
        foreach ($solos as $s) {
            $candidatos[] = array_merge($s, ['es_grupo_agrupado' => false]);
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
     * Marca vistas todas las notificaciones del delegado cuyo torneo comparte el mismo grupo_evento_id.
     */
    public static function marcarVistoTodasMismoGrupo(PDO $pdo, int $delegadoId, ?int $asociacionId, int $grupoEventoId): void
    {
        if ($grupoEventoId <= 0 || ($delegadoId <= 0 && ($asociacionId === null || $asociacionId <= 0))) {
            return;
        }
        self::ensureTable($pdo);
        try {
            if ($asociacionId !== null && $asociacionId > 0) {
                $st = $pdo->prepare(
                    'UPDATE fvd_delegado_notif_torneo n
                     INNER JOIN torneosact t ON t.torneo = n.torneo_id
                     INNER JOIN delegados del ON del.id = n.delegado_id
                     SET n.visto_en = COALESCE(n.visto_en, NOW())
                     WHERE del.asociacion_id = :a AND del.activo = 1
                       AND COALESCE(t.grupo_evento_id, 0) = :g'
                );
                $st->execute([':a' => $asociacionId, ':g' => $grupoEventoId]);
            } else {
                $st = $pdo->prepare(
                    'UPDATE fvd_delegado_notif_torneo n
                     INNER JOIN torneosact t ON t.torneo = n.torneo_id
                     SET n.visto_en = COALESCE(n.visto_en, NOW())
                     WHERE n.delegado_id = :d AND COALESCE(t.grupo_evento_id, 0) = :g'
                );
                $st->execute([':d' => $delegadoId, ':g' => $grupoEventoId]);
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
        try {
            if ($asociacionId !== null && $asociacionId > 0) {
                $st = $pdo->prepare(
                    'SELECT COUNT(*) FROM fvd_delegado_notif_torneo n
                     INNER JOIN delegados del ON del.id = n.delegado_id
                     WHERE del.asociacion_id = :a AND del.activo = 1 AND n.visto_en IS NULL'
                );
                $st->execute([':a' => $asociacionId]);
            } else {
                $st = $pdo->prepare(
                    'SELECT COUNT(*) FROM fvd_delegado_notif_torneo WHERE delegado_id = :d AND visto_en IS NULL'
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
        try {
            if ($asociacionId !== null && $asociacionId > 0) {
                $st = $pdo->prepare(
                    'SELECT 1 FROM fvd_delegado_notif_torneo n
                     INNER JOIN delegados del ON del.id = n.delegado_id
                     WHERE n.torneo_id = :t AND del.asociacion_id = :a AND del.activo = 1
                       AND n.invitacion_aceptada_en IS NULL
                     LIMIT 1'
                );
                $st->execute([':t' => $torneoId, ':a' => $asociacionId]);
            } else {
                if ($delegadoId <= 0) {
                    return false;
                }
                $st = $pdo->prepare(
                    'SELECT 1 FROM fvd_delegado_notif_torneo
                     WHERE delegado_id = :d AND torneo_id = :t AND invitacion_aceptada_en IS NULL
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
