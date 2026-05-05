<?php

declare(strict_types=1);

namespace FvdPortal\Services;

use PDO;
use PDOException;
use Throwable;

/**
 * Avisos de novedad para delegados (`fvd_notificaciones_delegados`).
 * Complementa invitaciones PDF en `fvd_delegado_notif_torneo`.
 */
final class NotificacionesDelegadosService
{
    public static function sqlPath(): string
    {
        return dirname(__DIR__, 2) . '/fvdmasteradmin/sql/install_fvd_campeonatos_notificaciones.sql';
    }

    public static function ensureTables(PDO $pdo): void
    {
        $path = self::sqlPath();
        if (!is_readable($path)) {
            return;
        }
        $sql = file_get_contents($path);
        if ($sql === false || strpos($sql, 'CREATE TABLE') === false) {
            return;
        }
        try {
            $pdo->exec($sql);
        } catch (PDOException $e) {
            error_log('[NotificacionesDelegadosService] ensureTables: ' . $e->getMessage());
        }
    }

    public static function tableExists(PDO $pdo): bool
    {
        try {
            $pdo->query('SELECT 1 FROM fvd_notificaciones_delegados LIMIT 0');

            return true;
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * Un registro por delegado activo (misma regla que convocatorias masivas).
     */
    public static function notificarTorneoNuevo(PDO $pdo, int $torneoId, ?string $nombreTorneo = null, ?int $campeonatoTablaId = null): int
    {
        if ($torneoId <= 0) {
            return 0;
        }
        self::ensureTables($pdo);
        if (!self::tableExists($pdo)) {
            return 0;
        }
        $nom = $nombreTorneo !== null ? trim($nombreTorneo) : '';
        if ($nom === '') {
            try {
                $st = $pdo->prepare('SELECT nombre FROM torneosact WHERE torneo = :t LIMIT 1');
                $st->execute([':t' => $torneoId]);
                $nom = trim((string) ($st->fetchColumn() ?: ''));
            } catch (Throwable $e) {
                $nom = '';
            }
        }
        if ($nom === '') {
            $nom = 'Torneo #' . $torneoId;
        }
        $msg = 'Nuevo torneo disponible: ' . $nom;
        $ins = $pdo->prepare(
            'INSERT INTO fvd_notificaciones_delegados (delegado_id, tipo, mensaje, torneo_id, campeonato_id)
             SELECT d.id, :tipo, :msg, :tid, :cid
             FROM delegados d
             WHERE d.activo = 1 AND d.asociacion_id IS NOT NULL AND d.asociacion_id > 0'
        );
        try {
            $ins->execute([
                ':tipo' => 'torneo_nuevo',
                ':msg' => $msg,
                ':tid' => $torneoId,
                ':cid' => $campeonatoTablaId,
            ]);
        } catch (Throwable $e) {
            error_log('[NotificacionesDelegadosService] notificarTorneoNuevo: ' . $e->getMessage());

            return 0;
        }

        return $ins->rowCount();
    }

    public static function contarNoLeidas(PDO $pdo, int $delegadoUserId): int
    {
        if ($delegadoUserId <= 0) {
            return 0;
        }
        if (!self::tableExists($pdo)) {
            return 0;
        }
        try {
            $st = $pdo->prepare(
                'SELECT COUNT(*) FROM fvd_notificaciones_delegados
                 WHERE delegado_id = :d AND leido_en IS NULL'
            );
            $st->execute([':d' => $delegadoUserId]);

            return (int) $st->fetchColumn();
        } catch (Throwable $e) {
            error_log('[NotificacionesDelegadosService] contarNoLeidas: ' . $e->getMessage());

            return 0;
        }
    }

    public static function marcarTodasLeidas(PDO $pdo, int $delegadoUserId): int
    {
        if ($delegadoUserId <= 0) {
            return 0;
        }
        if (!self::tableExists($pdo)) {
            return 0;
        }
        try {
            $st = $pdo->prepare(
                'UPDATE fvd_notificaciones_delegados SET leido_en = NOW()
                 WHERE delegado_id = :d AND leido_en IS NULL'
            );
            $st->execute([':d' => $delegadoUserId]);

            return $st->rowCount();
        } catch (Throwable $e) {
            error_log('[NotificacionesDelegadosService] marcarTodasLeidas: ' . $e->getMessage());

            return 0;
        }
    }
}
