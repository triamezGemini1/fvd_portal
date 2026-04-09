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
     * Última fila de tarifas (`costos` ordenado por fecha).
     *
     * @return array<string, mixed>|null
     */
    public static function ultimaTarifaCostos(PDO $pdo): ?array
    {
        try {
            $st = $pdo->query(
                'SELECT id, fecha, afiliacion, anualidad, carnets, traspasos, inscripciones
                FROM costos ORDER BY fecha DESC, id DESC LIMIT 1'
            );
            $row = $st ? $st->fetch(PDO::FETCH_ASSOC) : false;
        } catch (PDOException $e) {
            error_log('[StatsService] ultimaTarifaCostos: ' . $e->getMessage());

            return null;
        }

        return $row === false ? null : $row;
    }

    /**
     * Conteos en `atletas` para estimados: anualidad solo con <code>anualidad=1</code> y <code>afiliacion=1</code>;
     * inscripción con <code>inscripcion=1</code> y <code>afiliacion=0</code> (sin duplicar con afiliado).
     * Resto de conceptos: marca = 1. Montos = conteo × tarifa vigente.
     * Misma regla que la generación de deuda: última fila de `costos`.
     *
     * @return array{
     *   tarifa: array<string, mixed>|null,
     *   totales: array{
     *     counts: array{total_atletas:int,afiliacion:int,anualidad:int,carnet:int,traspaso:int,inscripcion:int},
     *     montos: array{afiliacion:float,anualidad:float,carnet:float,traspaso:float,inscripcion:float},
     *     monto_total: float
     *   },
     *   por_asociacion: list<array<string, mixed>> (solo se rellena si la sesión es administrador FVD; si no, lista vacía)
     * }
     */
    public static function indicadoresServicioConCostosEstimados(PDO $pdo): array
    {
        require_once __DIR__ . '/QueryHelper.php';

        $tarifa = self::ultimaTarifaCostos($pdo);
        $countsTot = QueryHelper::aggregateIndicadoresAtletasTotales($pdo);
        $rowsAsoc = [];
        $authSvc = dirname(__DIR__, 2) . '/fvdmasteradmin/services/AuthService.php';
        if (is_file($authSvc)) {
            require_once $authSvc;
            \AuthService::ensureSession();
            if (\AuthService::isSuperAdmin()) {
                $rowsAsoc = QueryHelper::aggregateIndicadoresAtletasPorAsociacion($pdo);
            }
        }

        $totMontos = self::montosIndicadoresDesdeTarifa($tarifa, $countsTot);
        $porAsoc = [];
        foreach ($rowsAsoc as $r) {
            $c = [
                'total_atletas' => (int) ($r['total_atletas'] ?? 0),
                'afiliacion'    => (int) ($r['afiliacion'] ?? 0),
                'anualidad'     => (int) ($r['anualidad'] ?? 0),
                'carnet'        => (int) ($r['carnet'] ?? 0),
                'traspaso'      => (int) ($r['traspaso'] ?? 0),
                'inscripcion'   => (int) ($r['inscripcion'] ?? 0),
            ];
            $m = self::montosIndicadoresDesdeTarifa($tarifa, $c);
            $porAsoc[] = array_merge($r, [
                'montos'      => $m['montos'],
                'monto_total' => $m['monto_total'],
            ]);
        }

        return [
            'tarifa' => $tarifa,
            'totales' => [
                'counts'      => $countsTot,
                'montos'      => $totMontos['montos'],
                'monto_total' => $totMontos['monto_total'],
            ],
            'por_asociacion' => $porAsoc,
        ];
    }

    /**
     * @param array{total_atletas?:int,afiliacion?:int,anualidad?:int,carnet?:int,traspaso?:int,inscripcion?:int} $counts
     * @return array{montos: array{afiliacion:float,anualidad:float,carnet:float,traspaso:float,inscripcion:float}, monto_total: float}
     */
    private static function montosIndicadoresDesdeTarifa(?array $tarifa, array $counts): array
    {
        $z = [
            'afiliacion'  => 0.0,
            'anualidad'   => 0.0,
            'carnet'      => 0.0,
            'traspaso'    => 0.0,
            'inscripcion' => 0.0,
        ];
        if ($tarifa === null) {
            return ['montos' => $z, 'monto_total' => 0.0];
        }

        $pa = (float) ($tarifa['afiliacion'] ?? 0);
        $pan = (float) ($tarifa['anualidad'] ?? 0);
        $pc = (float) ($tarifa['carnets'] ?? 0);
        $pt = (float) ($tarifa['traspasos'] ?? 0);
        $pi = (float) ($tarifa['inscripciones'] ?? 0);

        $na = (int) ($counts['afiliacion'] ?? 0);
        $nan = (int) ($counts['anualidad'] ?? 0);
        $nc = (int) ($counts['carnet'] ?? 0);
        $nt = (int) ($counts['traspaso'] ?? 0);
        $ni = (int) ($counts['inscripcion'] ?? 0);

        $z['afiliacion'] = $na * $pa;
        $z['anualidad'] = $nan * $pan;
        $z['carnet'] = $nc * $pc;
        $z['traspaso'] = $nt * $pt;
        $z['inscripcion'] = $ni * $pi;

        $total = $z['afiliacion'] + $z['anualidad'] + $z['carnet'] + $z['traspaso'] + $z['inscripcion'];

        return ['montos' => $z, 'monto_total' => $total];
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
     *   ultimos_atletas: list,
     *   indicadores_costos: array
     * }
     */
    public static function snapshotDashboard(PDO $pdo): array
    {
        return [
            'activos_inactivos'   => self::atletasActivosInactivos($pdo),
            'top_asociaciones'    => self::topAsociacionesPorAtletas($pdo, 5),
            'ultimos_30_dias'     => self::conteoAtletasUltimosDias($pdo, 30),
            'crecimiento_mensual' => self::crecimientoMensualAtletas($pdo, 12),
            'torta_asociacion'    => self::atletasPorAsociacionParaTorta($pdo, 7),
            'ultimos_atletas'     => self::ultimosAtletasRegistrados($pdo, 8),
            'indicadores_costos'  => self::indicadoresServicioConCostosEstimados($pdo),
        ];
    }

    /**
     * Inscripciones (bandera en atletas) por género y torneo en un año civil.
     * Torneos: año según fechator; si no hay fecha válida, año de created_at en torneosact.
     * Género: M / F; el resto (incl. vacío) cuenta como «otros».
     *
     * @return array{
     *   torneos: list<array{id:int,nombre:string,fecha:?string,m:int,f:int,otros:int,total:int}>,
     *   total_anual: array{m:int,f:int,otros:int,total:int}
     * }
     */
    public static function inscripcionesPorGeneroPorTorneoAno(PDO $pdo, int $year): array
    {
        $year = max(2000, min(2100, $year));
        $empty = [
            'torneos'     => [],
            'total_anual' => ['m' => 0, 'f' => 0, 'otros' => 0, 'total' => 0],
        ];

        $params = [':y' => $year];
        $onScope = self::scopeAtletasJoinOn($params);

        $sql = 'SELECT
                t.torneo AS id,
                COALESCE(NULLIF(TRIM(t.nombre), \'\'), CONCAT(\'Torneo #\', t.torneo)) AS nombre,
                t.fechator AS fecha,
                SUM(CASE WHEN a.id IS NOT NULL AND UPPER(TRIM(COALESCE(a.sexo, \'\'))) = \'M\' THEN 1 ELSE 0 END) AS m,
                SUM(CASE WHEN a.id IS NOT NULL AND UPPER(TRIM(COALESCE(a.sexo, \'\'))) = \'F\' THEN 1 ELSE 0 END) AS f,
                SUM(CASE WHEN a.id IS NOT NULL AND UPPER(TRIM(COALESCE(a.sexo, \'\'))) NOT IN (\'M\', \'F\') THEN 1 ELSE 0 END) AS otros
            FROM torneosact t
            LEFT JOIN atletas a ON a.torneo_id = t.torneo
                AND COALESCE(a.inscripcion, 0) = 1' . $onScope . '
            WHERE YEAR(COALESCE(NULLIF(t.fechator, \'0000-00-00\'), DATE(t.created_at))) = :y
            GROUP BY t.torneo, t.nombre, t.fechator
            ORDER BY t.fechator IS NULL, t.fechator DESC, t.torneo DESC';

        try {
            $st = $pdo->prepare($sql);
            self::executeNamed($st, $params);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('[StatsService] inscripcionesPorGeneroPorTorneoAno: ' . $e->getMessage());

            return $empty;
        }

        $torneos = [];
        $sumM = 0;
        $sumF = 0;
        $sumO = 0;

        foreach ($rows as $r) {
            $m = (int) ($r['m'] ?? 0);
            $f = (int) ($r['f'] ?? 0);
            $otros = (int) ($r['otros'] ?? 0);
            $total = $m + $f + $otros;
            $sumM += $m;
            $sumF += $f;
            $sumO += $otros;
            $fechaRaw = $r['fecha'] ?? null;
            $fechaStr = null;
            if ($fechaRaw !== null && $fechaRaw !== '' && (string) $fechaRaw !== '0000-00-00') {
                $fechaStr = (string) $fechaRaw;
            }
            $torneos[] = [
                'id'     => (int) ($r['id'] ?? 0),
                'nombre' => (string) ($r['nombre'] ?? ''),
                'fecha'  => $fechaStr,
                'm'      => $m,
                'f'      => $f,
                'otros'  => $otros,
                'total'  => $total,
            ];
        }

        return [
            'torneos' => $torneos,
            'total_anual' => [
                'm'     => $sumM,
                'f'     => $sumF,
                'otros' => $sumO,
                'total' => $sumM + $sumF + $sumO,
            ],
        ];
    }

    /**
     * Fragmento para ON de JOIN atletas (mismo criterio regional que scopeAtletas).
     *
     * @param array<string, mixed> $params
     */
    private static function scopeAtletasJoinOn(array &$params): string
    {
        $qh = dirname(__DIR__, 2) . '/fvdmasteradmin/services/QueryHelper.php';
        if (!\class_exists('QueryHelper', false)) {
            require_once $qh;
        }

        if (!\class_exists('AuthService', false)) {
            require_once dirname(__DIR__, 2) . '/fvdmasteradmin/services/AuthService.php';
        }
        \AuthService::ensureSession();

        if (!\AuthService::isAuthenticated()) {
            return ' AND 1=0 ';
        }
        if (\AuthService::isSuperAdmin()) {
            return '';
        }
        $id = \AuthService::idAsociacion();
        if ($id === null) {
            return ' AND 1=0 ';
        }
        $params[':fvd_asoc_scope'] = $id;

        return ' AND a.asociacion = :fvd_asoc_scope ';
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
     * Inscripciones del torneo en el ámbito: atletas con <code>inscripcion = 1</code> y <code>torneo_id</code> (única fuente para estadísticas).
     */
    public static function conteoInscripcionesTorneoAmbito(PDO $pdo, int $torneoId): int
    {
        return self::conteoInscripcionesTorneoAmbitoBandera($pdo, $torneoId);
    }

    /**
     * Inscritos del torneo en el ámbito contando bandera en <code>atletas</code>.
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
     *   inscripciones_torneo: int,
     *   indicadores_costos: array
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
                ? self::conteoInscripcionesTorneoAmbito($pdo, (int) $tid)
                : 0,
            'indicadores_costos'    => self::indicadoresServicioConCostosEstimados($pdo),
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
