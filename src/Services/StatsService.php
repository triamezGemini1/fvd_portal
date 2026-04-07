<?php

declare(strict_types=1);

namespace FvdPortal\Services;

use PDO;
use PDOException;

/**
 * Estadísticas rápidas (COUNT / GROUP BY) con alcance regional vía QueryHelper.
 * Sin caché: cada consulta lee el estado actual de la BD (p. ej. tras traspasos).
 */
final class StatsService
{
    /** @var string Expresión MySQL: fecha de referencia por registro (actividad / alta). */
    private const SQL_REF_DATE = 'GREATEST(
        IFNULL(NULLIF(a.fechact, \'0000-00-00 00:00:00\'), \'1970-01-01\'),
        IFNULL(NULLIF(a.fechfvd, \'0000-00-00\'), \'1970-01-01\')
    )';

    /**
     * @return array{activos: int, inactivos: int, total: int}
     * Activos: estatus = 1 (FvdAdminService::ATLETA_ESTATUS_ACTIVO). Resto: inactivos.
     */
    public static function atletasActivosInactivos(PDO $pdo): array
    {
        $params = [];
        $scope = self::scopeAtletas($params);
        $sql = 'SELECT
            SUM(CASE WHEN a.estatus = 1 THEN 1 ELSE 0 END) AS activos,
            SUM(CASE WHEN COALESCE(a.estatus, 0) <> 1 THEN 1 ELSE 0 END) AS inactivos,
            COUNT(*) AS total
            FROM atletas a
            WHERE 1=1' . $scope;

        try {
            $st = $pdo->prepare($sql);
            self::executeNamed($st, $params);
            $row = $st->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('[StatsService] atletasActivosInactivos: ' . $e->getMessage());

            return ['activos' => 0, 'inactivos' => 0, 'total' => 0];
        }

        if ($row === false) {
            return ['activos' => 0, 'inactivos' => 0, 'total' => 0];
        }

        return [
            'activos'   => (int) ($row['activos'] ?? 0),
            'inactivos' => (int) ($row['inactivos'] ?? 0),
            'total'     => (int) ($row['total'] ?? 0),
        ];
    }

    /**
     * Top asociaciones por cantidad de atletas en el ámbito actual.
     *
     * @return list<array{id: int|string|null, nombre: string, cnt: int}>
     */
    public static function topAsociacionesPorAtletas(PDO $pdo, int $limit = 5): array
    {
        $limit = max(1, min(20, $limit));
        $params = [];
        $scope = self::scopeAtletas($params);
        $sql = 'SELECT s.id, COALESCE(NULLIF(TRIM(s.nombre), \'\'), \'Sin nombre\') AS nombre, COUNT(*) AS cnt
            FROM atletas a
            LEFT JOIN asociaciones s ON a.asociacion = s.id
            WHERE 1=1' . $scope . '
            GROUP BY s.id, s.nombre
            ORDER BY cnt DESC
            LIMIT ' . (int) $limit;

        try {
            $st = $pdo->prepare($sql);
            self::executeNamed($st, $params);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('[StatsService] topAsociacionesPorAtletas: ' . $e->getMessage());

            return [];
        }

        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'id'     => $r['id'] ?? null,
                'nombre' => (string) ($r['nombre'] ?? ''),
                'cnt'    => (int) ($r['cnt'] ?? 0),
            ];
        }

        return $out;
    }

    /**
     * Atletas cuya fecha de referencia cae en los últimos N días (actividad / registro FVD).
     */
    public static function conteoAtletasUltimosDias(PDO $pdo, int $dias = 30): int
    {
        $dias = max(1, min(365, $dias));
        $params = [];
        $scope = self::scopeAtletas($params);
        $sql = 'SELECT COUNT(*) FROM atletas a
            WHERE 1=1' . $scope . '
            AND DATE(' . self::SQL_REF_DATE . ') >= DATE_SUB(CURDATE(), INTERVAL ' . (int) $dias . ' DAY)';

        try {
            $st = $pdo->prepare($sql);
            self::executeNamed($st, $params);

            return (int) $st->fetchColumn();
        } catch (PDOException $e) {
            error_log('[StatsService] conteoAtletasUltimosDias: ' . $e->getMessage());

            return 0;
        }
    }

    /**
     * Conteo por mes (YYYY-MM) para gráfico de líneas; últimos $meses meses calendario.
     *
     * @return list<array{mes: string, cnt: int}>
     */
    public static function crecimientoMensualAtletas(PDO $pdo, int $meses = 12): array
    {
        $meses = max(3, min(24, $meses));
        $params = [];
        $scope = self::scopeAtletas($params);
        $sql = 'SELECT DATE_FORMAT(' . self::SQL_REF_DATE . ', \'%Y-%m\') AS ym, COUNT(*) AS cnt
            FROM atletas a
            WHERE 1=1' . $scope . '
            AND ' . self::SQL_REF_DATE . ' >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL ' . (int) ($meses - 1) . ' MONTH), \'%Y-%m-01\')
            GROUP BY ym
            ORDER BY ym ASC';

        try {
            $st = $pdo->prepare($sql);
            self::executeNamed($st, $params);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('[StatsService] crecimientoMensualAtletas: ' . $e->getMessage());

            return [];
        }

        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'mes' => (string) ($r['ym'] ?? ''),
                'cnt' => (int) ($r['cnt'] ?? 0),
            ];
        }

        return $out;
    }

    /**
     * Datos para gráfico de torta: top N asociaciones + "Otros" si aplica.
     *
     * @return array{labels: list<string>, counts: list<int>}
     */
    public static function atletasPorAsociacionParaTorta(PDO $pdo, int $top = 7): array
    {
        $top = max(3, min(15, $top));
        $params = [];
        $scope = self::scopeAtletas($params);

        $sqlTotal = 'SELECT COUNT(*) FROM atletas a WHERE 1=1' . $scope;
        $sqlTop = 'SELECT COALESCE(NULLIF(TRIM(s.nombre), \'\'), \'Sin nombre\') AS nombre, COUNT(*) AS cnt
            FROM atletas a
            LEFT JOIN asociaciones s ON a.asociacion = s.id
            WHERE 1=1' . $scope . '
            GROUP BY s.id, s.nombre
            ORDER BY cnt DESC
            LIMIT ' . (int) $top;

        try {
            $stT = $pdo->prepare($sqlTotal);
            self::executeNamed($stT, $params);
            $grand = (int) $stT->fetchColumn();

            $st = $pdo->prepare($sqlTop);
            self::executeNamed($st, $params);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('[StatsService] atletasPorAsociacionParaTorta: ' . $e->getMessage());

            return ['labels' => [], 'counts' => []];
        }

        $labels = [];
        $counts = [];
        $sumTop = 0;
        foreach ($rows as $r) {
            $c = (int) ($r['cnt'] ?? 0);
            $sumTop += $c;
            $labels[] = (string) ($r['nombre'] ?? '');
            $counts[] = $c;
        }

        $otros = $grand - $sumTop;
        if ($otros > 0) {
            $labels[] = 'Otros';
            $counts[] = $otros;
        }

        return ['labels' => $labels, 'counts' => $counts];
    }

    /**
     * Últimos atletas (miniaturas en dashboard).
     *
     * @return list<array<string, mixed>>
     */
    public static function ultimosAtletasRegistrados(PDO $pdo, int $limite = 8): array
    {
        $limite = max(1, min(24, $limite));
        $params = [];
        $scope = self::scopeAtletas($params);
        $sql = 'SELECT a.id, a.nombre, a.cedula, a.foto
            FROM atletas a
            WHERE 1=1' . $scope . '
            ORDER BY a.id DESC
            LIMIT ' . (int) $limite;

        try {
            $st = $pdo->prepare($sql);
            self::executeNamed($st, $params);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('[StatsService] ultimosAtletasRegistrados: ' . $e->getMessage());

            return [];
        }

        return is_array($rows) ? $rows : [];
    }

    /**
     * Resumen en una sola carga (opcional).
     *
     * @return array{
     *   activos_inactivos: array{activos:int,inactivos:int,total:int},
     *   top_asociaciones: list,
     *   ultimos_30_dias: int,
     *   crecimiento_mensual: list,
     *   torta_asociacion: array{labels:list,counts:list},
     *   ultimos_atletas: list
     * }
     */
    public static function snapshotDashboard(PDO $pdo): array
    {
        return [
            'activos_inactivos'  => self::atletasActivosInactivos($pdo),
            'top_asociaciones'   => self::topAsociacionesPorAtletas($pdo, 5),
            'ultimos_30_dias'    => self::conteoAtletasUltimosDias($pdo, 30),
            'crecimiento_mensual' => self::crecimientoMensualAtletas($pdo, 12),
            'torta_asociacion'   => self::atletasPorAsociacionParaTorta($pdo, 7),
            'ultimos_atletas'    => self::ultimosAtletasRegistrados($pdo, 8),
        ];
    }

    /**
     * Carnet pendiente (0) vs solicitado (1) en el ámbito regional actual.
     *
     * @return array{pendiente: int, solicitado: int}
     */
    public static function carnetSolicitudesResumen(PDO $pdo): array
    {
        $params = [];
        $scope = self::scopeAtletas($params);
        $sql = 'SELECT
            SUM(CASE WHEN COALESCE(a.carnet, 0) = 0 THEN 1 ELSE 0 END) AS pendiente,
            SUM(CASE WHEN COALESCE(a.carnet, 0) = 1 THEN 1 ELSE 0 END) AS solicitado
            FROM atletas a WHERE 1=1' . $scope;
        try {
            $st = $pdo->prepare($sql);
            self::executeNamed($st, $params);
            $row = $st->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('[StatsService] carnetSolicitudesResumen: ' . $e->getMessage());

            return ['pendiente' => 0, 'solicitado' => 0];
        }
        if ($row === false) {
            return ['pendiente' => 0, 'solicitado' => 0];
        }

        return [
            'pendiente'  => (int) ($row['pendiente'] ?? 0),
            'solicitado' => (int) ($row['solicitado'] ?? 0),
        ];
    }

    /**
     * Torneo «actual»: próximo por fecha; si no hay, el último por id.
     */
    public static function torneoActualId(PDO $pdo): ?int
    {
        try {
            $st = $pdo->query(
                'SELECT torneo FROM torneosact WHERE DATE(fechator) >= CURDATE() ORDER BY fechator ASC LIMIT 1'
            );
            $id = $st ? $st->fetchColumn() : false;
            if ($id !== false && $id !== null) {
                return (int) $id;
            }
            $st2 = $pdo->query('SELECT torneo FROM torneosact ORDER BY torneo DESC LIMIT 1');
            $id2 = $st2 ? $st2->fetchColumn() : false;

            return ($id2 !== false && $id2 !== null) ? (int) $id2 : null;
        } catch (PDOException $e) {
            error_log('[StatsService] torneoActualId: ' . $e->getMessage());

            return null;
        }
    }

    /**
     * Inscripciones del torneo en el ámbito (asociación) actual.
     */
    public static function conteoInscripcionesTorneoAmbito(PDO $pdo, int $torneoId): int
    {
        if ($torneoId <= 0) {
            return 0;
        }
        $params = [':tid' => $torneoId];
        $qh = dirname(__DIR__, 2) . '/fvdmasteradmin/services/QueryHelper.php';
        if (!\class_exists('QueryHelper', false)) {
            require_once $qh;
        }
        $scope = \QueryHelper::asociacionScopeSql('it.asociacion_id', $params);
        $sql = 'SELECT COUNT(*) FROM inscripcion_torneo it WHERE it.torneo_id = :tid' . $scope;

        try {
            $st = $pdo->prepare($sql);
            self::executeNamed($st, $params);

            return (int) $st->fetchColumn();
        } catch (PDOException $e) {
            error_log('[StatsService] conteoInscripcionesTorneoAmbito: ' . $e->getMessage());

            return 0;
        }
    }

    /**
     * Inscritos del torneo en el ámbito (asociación) contando bandera en atletas (delegados).
     */
    public static function conteoInscripcionesTorneoAmbitoBandera(PDO $pdo, int $torneoId): int
    {
        if ($torneoId <= 0) {
            return 0;
        }
        $params = [':tid' => $torneoId];
        $qh = dirname(__DIR__, 2) . '/fvdmasteradmin/services/QueryHelper.php';
        if (!\class_exists('QueryHelper', false)) {
            require_once $qh;
        }
        $scope = \QueryHelper::asociacionScopeSql('a.asociacion', $params);
        $sql = 'SELECT COUNT(*) FROM atletas a WHERE COALESCE(a.inscripcion, 0) = 1 AND a.torneo_id = :tid' . $scope;

        try {
            $st = $pdo->prepare($sql);
            self::executeNamed($st, $params);

            return (int) $st->fetchColumn();
        } catch (PDOException $e) {
            error_log('[StatsService] conteoInscripcionesTorneoAmbitoBandera: ' . $e->getMessage());

            return 0;
        }
    }

    /**
     * @return array{
     *   activos_inactivos: array{activos:int,inactivos:int,total:int},
     *   carnet: array{pendiente:int,solicitado:int},
     *   torneo_id: int|null,
     *   torneo_nombre: string,
     *   inscripciones_torneo: int
     * }
     */
    public static function snapshotDelegadoPanel(PDO $pdo): array
    {
        $tid = self::torneoActualId($pdo);
        if (\class_exists('AuthService', false) && \AuthService::isDelegadoAsociacion()) {
            $ctx = \AuthService::delegadoTorneoContextId();
            if ($ctx !== null && $ctx > 0) {
                $tid = $ctx;
            }
        }
        $nom = '';
        if ($tid !== null && $tid > 0) {
            try {
                $st = $pdo->prepare('SELECT nombre FROM torneosact WHERE torneo = :t LIMIT 1');
                $st->execute([':t' => $tid]);
                $nom = (string) ($st->fetchColumn() ?: '');
            } catch (PDOException $e) {
                error_log('[StatsService] snapshotDelegadoPanel nombre: ' . $e->getMessage());
            }
        }

        return [
            'activos_inactivos'     => self::atletasActivosInactivos($pdo),
            'carnet'                => self::carnetSolicitudesResumen($pdo),
            'torneo_id'             => $tid,
            'torneo_nombre'         => $nom,
            'inscripciones_torneo'  => $tid !== null
                ? (\AuthService::isDelegadoAsociacion()
                    ? self::conteoInscripcionesTorneoAmbitoBandera($pdo, (int) $tid)
                    : self::conteoInscripcionesTorneoAmbito($pdo, (int) $tid))
                : 0,
        ];
    }

    /**
     * @param array<string, mixed> $params
     */
    private static function scopeAtletas(array &$params): string
    {
        $qh = dirname(__DIR__, 2) . '/fvdmasteradmin/services/QueryHelper.php';
        if (!\class_exists('QueryHelper', false)) {
            require_once $qh;
        }

        return \QueryHelper::asociacionScopeSql('a.asociacion', $params);
    }

    /**
     * @param array<string, mixed> $params
     */
    private static function executeNamed(\PDOStatement $st, array $params): void
    {
        foreach ($params as $k => $v) {
            $key = (string) $k;
            if ($key !== '' && $key[0] !== ':') {
                $key = ':' . ltrim($key, ':');
            }
            $type = PDO::PARAM_STR;
            if (is_int($v)) {
                $type = PDO::PARAM_INT;
            } elseif (is_bool($v)) {
                $type = PDO::PARAM_BOOL;
            } elseif ($v === null) {
                $type = PDO::PARAM_NULL;
            }
            $st->bindValue($key, $v, $type);
        }
        $st->execute();
    }
}
