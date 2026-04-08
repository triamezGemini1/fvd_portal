<?php

declare(strict_types=1);

namespace FvdPortal\Services;

use PDO;
use PDOException;

/**
 * Conteos por concepto y asociación leyendo solo {@see inscripcion_torneo} (origen de inscripciones).
 * No calcula montos ni deuda; sirve para comparar con el destino (`atletas`).
 */
final class InscripcionTorneoEstadisticasService
{
    public static function tablaExiste(PDO $pdo): bool
    {
        try {
            $db = $pdo->query('SELECT DATABASE()')->fetchColumn();
            if ($db === false || $db === null || $db === '') {
                return false;
            }
            $st = $pdo->prepare(
                'SELECT 1 FROM information_schema.tables WHERE table_schema = :db AND table_name = :t LIMIT 1'
            );
            $st->execute([':db' => (string) $db, ':t' => 'inscripcion_torneo']);

            return (bool) $st->fetchColumn();
        } catch (PDOException $e) {
            error_log('[InscripcionTorneoEstadisticasService] tablaExiste: ' . $e->getMessage());

            return false;
        }
    }

    /**
     * @param array<string, mixed> $params Debe incluir :tid; el alcance delegado añade :fvd_asoc_scope vía asociacionScopeSql
     *
     * @return list<array<string, mixed>>
     */
    public static function estadisticasPorTorneoAgrupadas(PDO $pdo, string $asociacionScopeSql, array $params): array
    {
        $sql = 'SELECT i.asociacion_id,
                a.nombre AS asoc_nombre,
                COUNT(*) AS filas_origen,
                SUM(CASE WHEN COALESCE(i.inscripcion, 0) = 1 THEN 1 ELSE 0 END) AS total_inscritos,
                SUM(CASE WHEN COALESCE(i.afiliacion, 0) = 1 THEN 1 ELSE 0 END) AS total_afiliados,
                SUM(CASE WHEN COALESCE(i.anualidad, 0) = 1 THEN 1 ELSE 0 END) AS total_anualidad,
                SUM(CASE WHEN COALESCE(i.carnet, 0) = 1 THEN 1 ELSE 0 END) AS total_carnets,
                SUM(CASE WHEN COALESCE(i.traspaso, 0) = 1 THEN 1 ELSE 0 END) AS total_traspasos
            FROM inscripcion_torneo i
            LEFT JOIN asociaciones a ON a.id = i.asociacion_id
            WHERE i.torneo_id = :tid ' . $asociacionScopeSql . '
            GROUP BY i.asociacion_id, a.nombre
            ORDER BY a.nombre ASC';
        try {
            $st = $pdo->prepare($sql);
            $st->execute($params);

            $rows = $st->fetchAll(PDO::FETCH_ASSOC);

            return is_array($rows) ? $rows : [];
        } catch (PDOException $e) {
            error_log('[InscripcionTorneoEstadisticasService] estadisticasPorTorneoAgrupadas: ' . $e->getMessage());

            return [];
        }
    }
}
