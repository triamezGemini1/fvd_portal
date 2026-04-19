<?php

declare(strict_types=1);

namespace FvdPortal\Services;

use PDO;
use PDOException;

/**
 * Tabla `fvd_notificaciones`: registro de invitaciones / convocatoria (token de acceso por delegado y torneo).
 */
final class FvdNotificacionesService
{
    public static function ensureTable(PDO $pdo): void
    {
        $sqlPath = dirname(__DIR__, 2) . '/fvdmasteradmin/sql/install_fvd_notificaciones.sql';
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
            error_log('[FvdNotificacionesService] ensureTable: ' . $e->getMessage());
        }
    }

    /**
     * Copia filas desde `fvd_delegado_notif_torneo` (mismo token que access_token).
     *
     * @return int Filas afectadas (aprox.)
     */
    public static function sincronizarDesdeDelegadoNotifTorneo(PDO $pdo, int $torneoId): int
    {
        if ($torneoId <= 0) {
            return 0;
        }
        DelegadoTorneoNotifService::ensureTable($pdo);
        DelegadoTorneoNotifService::ensureTokenColumns($pdo);
        self::ensureTable($pdo);

        $st = $pdo->prepare(
            'SELECT id, delegado_id, torneo_id, access_token, creado_en, visto_en
             FROM fvd_delegado_notif_torneo WHERE torneo_id = :t'
        );
        $st->execute([':t' => $torneoId]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        if ($rows === []) {
            return 0;
        }

        $ins = $pdo->prepare(
            'INSERT INTO fvd_notificaciones (delegado_id, torneo_id, token_acceso, creado_en, visto_en)
             VALUES (:d, :tor, :tok, CURRENT_TIMESTAMP, NULL)
             ON DUPLICATE KEY UPDATE
                token_acceso = VALUES(token_acceso),
                creado_en = CURRENT_TIMESTAMP'
        );
        $n = 0;
        foreach ($rows as $r) {
            $tok = trim((string) ($r['access_token'] ?? ''));
            if ($tok === '') {
                continue;
            }
            $did = (int) ($r['delegado_id'] ?? 0);
            $tid = (int) ($r['torneo_id'] ?? 0);
            if ($did <= 0 || $tid <= 0) {
                continue;
            }
            try {
                $ins->execute([
                    ':d' => $did,
                    ':tor' => $tid,
                    ':tok' => $tok,
                ]);
                ++$n;
            } catch (PDOException $e) {
                error_log('[FvdNotificacionesService] sincronizar upsert: ' . $e->getMessage());
            }
        }

        return $n;
    }
}
