<?php

declare(strict_types=1);

namespace FvdPortal\Services;

use PDO;
use PDOException;

/**
 * Cola visible para el administrador general: altas de atletas cargadas por delegados
 * y solicitudes de club (carnet / traspaso / afiliación), sin envío explícito de correos.
 */
final class FvdAdminRevisionPendienteService
{
    public static function ensureAltaDesdeDelegadoColumn(PDO $pdo): void
    {
        try {
            $pdo->exec(
                'ALTER TABLE atletas ADD COLUMN alta_desde_delegado TINYINT(1) NOT NULL DEFAULT 0'
                . " COMMENT '1=alta ingresada por delegado, pendiente validación FVD'"
            );
        } catch (PDOException $e) {
            $msg = $e->getMessage();
            if (stripos($msg, 'Duplicate column') === false && stripos($msg, 'already exists') === false) {
                error_log('[FvdAdminRevisionPendienteService] ensureAltaDesdeDelegadoColumn: ' . $msg);
            }
        }
    }

    public static function marcarAltaDesdeDelegado(PDO $pdo, int $atletaId): void
    {
        if ($atletaId <= 0) {
            return;
        }
        self::ensureAltaDesdeDelegadoColumn($pdo);
        $st = $pdo->prepare('UPDATE atletas SET alta_desde_delegado = 1 WHERE id = :id');
        $st->execute([':id' => $atletaId]);
    }

    /**
     * @return array{nuevas_altas_delegado:int, solicitudes_pendientes:int, total:int}
     */
    public static function conteos(PDO $pdo): array
    {
        require_once __DIR__ . '/DelegadoSolicitudService.php';
        self::ensureAltaDesdeDelegadoColumn($pdo);
        DelegadoSolicitudService::ensureTable($pdo);

        $nAltas = 0;
        try {
            $st = $pdo->query(
                'SELECT COUNT(*) FROM atletas WHERE COALESCE(alta_desde_delegado, 0) = 1 AND COALESCE(estatus, 0) = 0'
            );
            if ($st !== false) {
                $nAltas = (int) $st->fetchColumn();
            }
        } catch (PDOException $e) {
            error_log('[FvdAdminRevisionPendienteService] conteos altas: ' . $e->getMessage());
        }

        $nSol = 0;
        try {
            $st2 = $pdo->query("SELECT COUNT(*) FROM fvd_solicitudes_delegado WHERE estado = 'pendiente'");
            if ($st2 !== false) {
                $nSol = (int) $st2->fetchColumn();
            }
        } catch (PDOException $e) {
            error_log('[FvdAdminRevisionPendienteService] conteos solicitudes: ' . $e->getMessage());
        }

        return [
            'nuevas_altas_delegado'  => $nAltas,
            'solicitudes_pendientes' => $nSol,
            'total'                  => $nAltas + $nSol,
        ];
    }
}
