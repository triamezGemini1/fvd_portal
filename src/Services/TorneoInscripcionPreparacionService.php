<?php

declare(strict_types=1);

namespace FvdPortal\Services;

use PDO;
use PDOException;

/**
 * Antes de abrir inscripciones: marcar notificaciones de delegados como vistas y
 * sincronizar filas de movimiento en `inscripcion_torneo` desde `atletas`.
 */
final class TorneoInscripcionPreparacionService
{
    /**
     * @return list<int>
     */
    public static function asociacionesConFilasMovimiento(PDO $pdo, int $torneoId): array
    {
        if ($torneoId <= 0) {
            return [];
        }
        try {
            $st = $pdo->prepare(
                'SELECT DISTINCT asociacion_id FROM inscripcion_torneo
                 WHERE torneo_id = :t AND COALESCE(inscripcion, 0) = :mov
                 ORDER BY asociacion_id ASC'
            );
            $st->execute([':t' => $torneoId, ':mov' => InscripcionService::CANAL_INSCRIPCION_MOVIMIENTO]);
            $ids = [];
            while ($x = $st->fetchColumn()) {
                $i = (int) $x;
                if ($i > 0) {
                    $ids[] = $i;
                }
            }

            return $ids;
        } catch (PDOException $e) {
            error_log('[TorneoInscripcionPreparacionService] asociacionesConFilasMovimiento: ' . $e->getMessage());

            return [];
        }
    }

    /**
     * @return array{notificaciones_marcadas:int, filas_sincronizadas:int, asociaciones:int}
     */
    public static function prepararTorneo(PDO $pdo, int $torneoId): array
    {
        if ($torneoId <= 0) {
            return ['notificaciones_marcadas' => 0, 'filas_sincronizadas' => 0, 'asociaciones' => 0];
        }
        $nNotif = DelegadoTorneoNotifService::marcarTodasVistasParaTorneo($pdo, $torneoId);
        $asocs = self::asociacionesConFilasMovimiento($pdo, $torneoId);
        $sum = 0;
        foreach ($asocs as $aid) {
            $sum += InscripcionService::sincronizarMovimientosDesdeAtletas($pdo, $torneoId, $aid);
        }

        return [
            'notificaciones_marcadas' => $nNotif,
            'filas_sincronizadas' => $sum,
            'asociaciones' => count($asocs),
        ];
    }
}
