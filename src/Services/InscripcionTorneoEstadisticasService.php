<?php

declare(strict_types=1);

namespace FvdPortal\Services;

use PDO;
use PDOException;

/**
 * Conteos por concepto y asociación leyendo solo {@see atletas} para el torneo dado (`torneo_id`).
 * Renglones: afiliacion, anualidad, carnet, traspaso, inscripcion (inscritos).
 * No calcula montos ni deuda.
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
            $st->execute([':db' => (string) $db, ':t' => 'atletas']);

            return (bool) $st->fetchColumn();
        } catch (PDOException $e) {
            error_log('[InscripcionTorneoEstadisticasService] tablaExiste: ' . $e->getMessage());

            return false;
        }
    }

    /**
     * @param array<string, mixed> $params Debe incluir :tid; el alcance delegado añade :fvd_asoc_scope sobre `a.asociacion`
     *
     * @return list<array<string, mixed>>
     */
    public static function estadisticasPorTorneoAgrupadas(PDO $pdo, string $asociacionScopeSql, array $params): array
    {
        $sql = 'SELECT a.asociacion AS asociacion_id,
                COALESCE(NULLIF(TRIM(s.nombre), \'\'), \'Sin nombre\') AS asoc_nombre,
                COUNT(*) AS filas_origen,
                SUM(CASE WHEN COALESCE(a.inscripcion, 0) = 1 AND COALESCE(a.afiliacion, 0) = 0 THEN 1 ELSE 0 END) AS total_inscritos,
                SUM(CASE WHEN COALESCE(a.afiliacion, 0) = 1 THEN 1 ELSE 0 END) AS total_afiliados,
                SUM(CASE WHEN COALESCE(a.anualidad, 0) = 1 AND COALESCE(a.afiliacion, 0) = 1 THEN 1 ELSE 0 END) AS total_anualidad,
                SUM(CASE WHEN COALESCE(a.carnet, 0) = 1 THEN 1 ELSE 0 END) AS total_carnets,
                SUM(CASE WHEN COALESCE(a.traspaso, 0) = 1 THEN 1 ELSE 0 END) AS total_traspasos
            FROM atletas a
            LEFT JOIN asociaciones s ON s.id = a.asociacion
            WHERE a.torneo_id = :tid ' . $asociacionScopeSql . '
            GROUP BY a.asociacion, s.nombre
            ORDER BY asoc_nombre ASC';
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
