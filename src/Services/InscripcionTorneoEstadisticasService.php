<?php

declare(strict_types=1);

namespace FvdPortal\Services;

use PDO;
use PDOException;

/**
 * Conteos por concepto y asociación para un torneo desde la ficha {@see estadisticasDesdeAtletasTorneoId} (`atletas`
 * con `torneo_id` e inscripción), alineado con deudas y reportes. Las consultas sobre `inscripcion_torneo` quedan
 * como utilidades puntuales, no como fuente principal de agregados.
 */
final class InscripcionTorneoEstadisticasService
{
    public static function tablaAtletasExiste(PDO $pdo): bool
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
            error_log('[InscripcionTorneoEstadisticasService] tablaAtletasExiste: ' . $e->getMessage());

            return false;
        }
    }

    public static function tablaInscripcionTorneoExiste(PDO $pdo): bool
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
            error_log('[InscripcionTorneoEstadisticasService] tablaInscripcionTorneoExiste: ' . $e->getMessage());

            return false;
        }
    }

    /**
     * @deprecated Use {@see tablaAtletasExiste}
     */
    public static function tablaExiste(PDO $pdo): bool
    {
        return self::tablaAtletasExiste($pdo);
    }

    /**
     * @param array<string, mixed> $params Debe incluir :tid; el alcance delegado añade :fvd_asoc_scope sobre la columna de asociación
     *
     * @return list<array<string, mixed>>
     */
    public static function estadisticasPorTorneoAgrupadas(PDO $pdo, string $asociacionScopeSql, array $params): array
    {
        require_once dirname(__DIR__, 2) . '/src/Services/QueryHelper.php';

        return self::estadisticasDesdeAtletasTorneoId($pdo, $asociacionScopeSql, $params);
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return list<array<string, mixed>>
     */
    private static function estadisticasDesdeInscripcionTorneo(PDO $pdo, string $asociacionScopeSql, array $params): array
    {
        $metricas = \FvdPortal\Services\QueryHelper::sqlSelectMetricasTorneoPorInscripcionTorneo('it');
        $scopeSql = str_replace('a.asociacion', 'it.asociacion_id', $asociacionScopeSql);
        $sql = 'SELECT it.asociacion_id AS asociacion_id,
                MAX(COALESCE(NULLIF(TRIM(s.nombre), \'\'), \'Sin nombre\')) AS asoc_nombre,
                ' . $metricas . '
            FROM inscripcion_torneo it
            LEFT JOIN asociaciones s ON s.id = it.asociacion_id
            WHERE it.torneo_id = :tid ' . $scopeSql . '
            GROUP BY it.asociacion_id
            ORDER BY asoc_nombre ASC';
        try {
            $st = $pdo->prepare($sql);
            $st->execute($params);

            $rows = $st->fetchAll(PDO::FETCH_ASSOC);

            return is_array($rows) ? $rows : [];
        } catch (PDOException $e) {
            error_log('[InscripcionTorneoEstadisticasService] estadisticasDesdeInscripcionTorneo: ' . $e->getMessage());

            return [];
        }
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return list<array<string, mixed>>
     */
    private static function estadisticasDesdeAtletasTorneoId(PDO $pdo, string $asociacionScopeSql, array $params): array
    {
        $metricas = \FvdPortal\Services\QueryHelper::sqlSelectMetricasTorneoPorAsociacion('a');
        $sql = 'SELECT a.asociacion AS asociacion_id,
                MAX(COALESCE(NULLIF(TRIM(s.nombre), \'\'), \'Sin nombre\')) AS asoc_nombre,
                ' . $metricas . '
            FROM atletas a
            LEFT JOIN asociaciones s ON s.id = a.asociacion
            WHERE a.torneo_id = :tid ' . $asociacionScopeSql . '
            GROUP BY a.asociacion
            ORDER BY asoc_nombre ASC';
        try {
            $st = $pdo->prepare($sql);
            $st->execute($params);

            $rows = $st->fetchAll(PDO::FETCH_ASSOC);

            return is_array($rows) ? $rows : [];
        } catch (PDOException $e) {
            error_log('[InscripcionTorneoEstadisticasService] estadisticasDesdeAtletasTorneoId: ' . $e->getMessage());

            return [];
        }
    }
}
