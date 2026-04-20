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
     * Conteos en `atletas` para estimados: cada indicador se cuenta por separado (marca = 1 en su columna).
     * Montos = conteo × tarifa vigente (última fila de `costos`). El desglose por asociación respeta el alcance de sesión
     * (delegado: solo su asociación; admin FVD: todas).
     *
     * @return array{
     *   tarifa: array<string, mixed>|null,
     *   totales: array{
     *     counts: array{total_atletas:int,afiliacion:int,anualidad:int,carnet:int,traspaso:int,inscripcion:int},
     *     montos: array{afiliacion:float,anualidad:float,carnet:float,traspaso:float,inscripcion:float},
     *     monto_total: float
     *   },
     *   por_asociacion: list<array<string, mixed>>
     * }
     */
    public static function indicadoresServicioConCostosEstimados(PDO $pdo): array
    {
        require_once __DIR__ . '/QueryHelper.php';

        $tarifa = self::ultimaTarifaCostos($pdo);
        $countsTot = QueryHelper::aggregateIndicadoresAtletasTotales($pdo);
        $rowsAsoc = QueryHelper::aggregateIndicadoresAtletasPorAsociacion($pdo);

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
     * Carnet pendiente (NULL/0) vs solicitado (exactamente 1) en el ámbito regional actual.
     * Valores distintos de 0, NULL y 1 no incrementan ninguno de los dos contadores (no entran en estadísticas de carnet).
     *
     * @return array{pendiente: int, solicitado: int}
     */
    public static function carnetSolicitudesResumen(PDO $pdo): array
    {
        $params = [];
        $scope = self::scopeAtletas($params);
        $sql = 'SELECT
            SUM(CASE WHEN (a.carnet IS NULL OR a.carnet = 0) THEN 1 ELSE 0 END) AS pendiente,
            SUM(CASE WHEN a.carnet = 1 THEN 1 ELSE 0 END) AS solicitado
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
     * Widget del listado de atletas: mismos límites de alcance que el listado (tipo «normal», sin cédula/nombre).
     * Torneos: filas en <code>torneosact</code> (todos son eventos FVD; sin filtro regional por organizador).
     * Participación: filas en <code>inscripcion_torneo</code> si existe; si no, atletas con <code>inscripcion = 1</code>.
     *
     * @param 'todos'|'asociacion' $alcance
     *
     * @return array{
     *   etiqueta: string,
     *   total_atletas: int,
     *   total_afiliados: int,
     *   sexo_m: int,
     *   sexo_f: int,
     *   sexo_sin: int,
     *   torneos: int,
     *   participacion: int
     * }
     */
    public static function atletasModuloWidgetResumen(PDO $pdo, string $alcance, int $asociacionFiltroId): array
    {
        $out = [
            'etiqueta'         => 'Resumen',
            'total_atletas'    => 0,
            'total_afiliados'  => 0,
            'sexo_m'           => 0,
            'sexo_f'           => 0,
            'sexo_sin'         => 0,
            'torneos'          => 0,
            'participacion'    => 0,
        ];
        if (!in_array($alcance, ['todos', 'asociacion'], true)) {
            $alcance = 'todos';
        }

        $legacyQh = dirname(__DIR__, 2) . '/fvdmasteradmin/services/QueryHelper.php';
        if (!\class_exists('QueryHelper', false)) {
            require_once $legacyQh;
        }
        if (!\class_exists('AuthService', false)) {
            require_once dirname(__DIR__, 2) . '/fvdmasteradmin/services/AuthService.php';
        }
        require_once dirname(__DIR__, 2) . '/src/Services/QueryHelper.php';

        $frag = \FvdPortal\Services\QueryHelper::atletasAdminListFragments('', '', $alcance, 'normal', $asociacionFiltroId);
        $params = $frag['params'];
        $sqlWhereAtletas = '1=1' . $frag['search'] . \QueryHelper::asociacionScopeSql('a.asociacion', $params);

        try {
            $sqlAgg = 'SELECT
                COUNT(*) AS total_atletas,
                SUM(CASE WHEN COALESCE(a.afiliacion, 0) = 1 THEN 1 ELSE 0 END) AS total_afiliados,
                SUM(CASE WHEN COALESCE(a.sexo, 0) = 1 THEN 1 ELSE 0 END) AS sexo_m,
                SUM(CASE WHEN COALESCE(a.sexo, 0) = 2 THEN 1 ELSE 0 END) AS sexo_f,
                SUM(CASE WHEN COALESCE(a.sexo, 0) NOT IN (1, 2) THEN 1 ELSE 0 END) AS sexo_sin
                FROM atletas a WHERE ' . $sqlWhereAtletas;
            $st = $pdo->prepare($sqlAgg);
            self::executeNamed($st, $params);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            if (is_array($row)) {
                $out['total_atletas'] = (int) ($row['total_atletas'] ?? 0);
                $out['total_afiliados'] = (int) ($row['total_afiliados'] ?? 0);
                $out['sexo_m'] = (int) ($row['sexo_m'] ?? 0);
                $out['sexo_f'] = (int) ($row['sexo_f'] ?? 0);
                $out['sexo_sin'] = (int) ($row['sexo_sin'] ?? 0);
            }
        } catch (PDOException $e) {
            error_log('[StatsService] atletasModuloWidgetResumen atletas: ' . $e->getMessage());
        }

        try {
            $sqlT = 'SELECT COUNT(*) FROM torneosact t WHERE 1=1';
            $stT = $pdo->prepare($sqlT);
            $stT->execute();
            $out['torneos'] = (int) $stT->fetchColumn();
        } catch (PDOException $e) {
            error_log('[StatsService] atletasModuloWidgetResumen torneos: ' . $e->getMessage());
        }

        $paramsI = [];
        $extraIns = '';
        if (\AuthService::isSuperAdmin() && $alcance === 'asociacion' && $asociacionFiltroId > 0) {
            $paramsI[':wid_ins_asoc'] = $asociacionFiltroId;
            $extraIns = ' AND it.asociacion_id = :wid_ins_asoc ';
        }
        try {
            $sqlI = 'SELECT COUNT(*) FROM inscripcion_torneo it WHERE 1=1' . $extraIns . \QueryHelper::asociacionScopeSql('it.asociacion_id', $paramsI);
            $stI = $pdo->prepare($sqlI);
            self::executeNamed($stI, $paramsI);
            $out['participacion'] = (int) $stI->fetchColumn();
        } catch (PDOException $e) {
            $fragF = \FvdPortal\Services\QueryHelper::atletasAdminListFragments('', '', $alcance, 'normal', $asociacionFiltroId);
            $paramsF = $fragF['params'];
            $whereF = '1=1' . $fragF['search'] . \QueryHelper::asociacionScopeSql('a.asociacion', $paramsF)
                . ' AND COALESCE(a.inscripcion, 0) = 1 ';
            try {
                $sqlF = 'SELECT COUNT(*) FROM atletas a WHERE ' . $whereF;
                $stF = $pdo->prepare($sqlF);
                self::executeNamed($stF, $paramsF);
                $out['participacion'] = (int) $stF->fetchColumn();
            } catch (PDOException $e2) {
                error_log('[StatsService] atletasModuloWidgetResumen participación fallback: ' . $e2->getMessage());
            }
        }

        if ($alcance === 'asociacion' && $asociacionFiltroId > 0) {
            $out['etiqueta'] = 'Asociación #' . $asociacionFiltroId;
            try {
                $stN = $pdo->prepare('SELECT nombre FROM asociaciones WHERE id = :id LIMIT 1');
                $stN->execute([':id' => $asociacionFiltroId]);
                $nom = $stN->fetchColumn();
                if ($nom !== false && trim((string) $nom) !== '') {
                    $out['etiqueta'] = trim((string) $nom);
                }
            } catch (PDOException $e) {
                error_log('[StatsService] atletasModuloWidgetResumen nombre asoc: ' . $e->getMessage());
            }
        } else {
            $out['etiqueta'] = 'Federación (todos los clubes)';
        }

        return $out;
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

    /**
     * Atletas de una asociación filtrados por métrica de indicadores (misma lógica que los conteos por bandera en 1).
     *
     * @param 'total_atletas'|'afiliacion'|'anualidad'|'carnet'|'traspaso'|'inscripcion' $metricKey
     * @param bool $aplicarAlcanceSesion Si es false, solo se filtra por {@see $asociacionId} (p. ej. informe super admin por club concreto).
     *
     * @return list<array<string, mixed>>
     */
    public static function listadoAtletasPorAsociacionMetrica(
        PDO $pdo,
        int $asociacionId,
        string $metricKey,
        int $limit = 800,
        bool $aplicarAlcanceSesion = true
    ): array {
        $allowed = [
            'total_atletas' => '',
            'afiliacion'    => ' AND COALESCE(a.afiliacion, 0) = 1 ',
            'anualidad'     => ' AND COALESCE(a.anualidad, 0) = 1 ',
            'carnet'        => ' AND COALESCE(a.carnet, 0) = 1 ',
            'traspaso'      => ' AND COALESCE(a.traspaso, 0) = 1 ',
            'inscripcion'   => ' AND COALESCE(a.inscripcion, 0) = 1 ',
        ];
        if ($asociacionId <= 0 || !\array_key_exists($metricKey, $allowed)) {
            return [];
        }
        $limit = max(1, min(5000, $limit));

        $params = [':fvd_rep_aid' => $asociacionId];
        $scope = '';
        if ($aplicarAlcanceSesion) {
            $qh = dirname(__DIR__, 2) . '/fvdmasteradmin/services/QueryHelper.php';
            if (!\class_exists('QueryHelper', false)) {
                require_once $qh;
            }
            $scope = \QueryHelper::asociacionScopeSql('a.asociacion', $params);
        }
        $extra = $allowed[$metricKey];

        $sql = 'SELECT a.id, a.cedula, a.nombre, a.numfvd, a.estatus, a.torneo_id
            FROM atletas a
            WHERE a.asociacion = :fvd_rep_aid' . $extra . $scope . '
            ORDER BY a.nombre ASC, a.id ASC
            LIMIT ' . (int) $limit;

        try {
            $st = $pdo->prepare($sql);
            self::executeNamed($st, $params);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('[StatsService] listadoAtletasPorAsociacionMetrica: ' . $e->getMessage());

            return [];
        }

        return \is_array($rows) ? $rows : [];
    }

    /**
     * Datos consolidados para el panel admin: ficha de asociación, deudas por torneo y pagos recientes.
     *
     * @param bool $incluirDeudas Si es false, no se consulta `deuda_asociaciones` (p. ej. reportes que solo muestran pagos).
     *
     * @return array{asociacion: array<string, mixed>|null, deudas: list<array<string, mixed>>, pagos: list<array<string, mixed>>}|null
     */
    public static function dashboardDetalleAsociacion(PDO $pdo, int $asociacionId, bool $incluirDeudas = true): ?array
    {
        if ($asociacionId <= 0) {
            return null;
        }
        try {
            $st = $pdo->prepare('SELECT * FROM asociaciones WHERE id = :id LIMIT 1');
            $st->execute([':id' => $asociacionId]);
            $asoc = $st->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('[StatsService] dashboardDetalleAsociacion asoc: ' . $e->getMessage());

            return null;
        }
        if ($asoc === false) {
            return null;
        }

        $deudas = [];
        $pagos = [];
        if ($incluirDeudas) {
            try {
                $sd = $pdo->prepare(
                    'SELECT d.*, t.nombre AS torneo_nombre
                    FROM deuda_asociaciones d
                    LEFT JOIN torneosact t ON d.torneo_id = t.torneo
                    WHERE d.asociacion_id = :aid
                    ORDER BY d.fecha_creacion DESC
                    LIMIT 50'
                );
                $sd->execute([':aid' => $asociacionId]);
                $deudas = $sd->fetchAll(PDO::FETCH_ASSOC) ?: [];
            } catch (PDOException $e) {
                error_log('[StatsService] dashboardDetalleAsociacion deudas: ' . $e->getMessage());
            }
        }
        try {
            $sp = $pdo->prepare(
                'SELECT r.id, r.torneo_id, r.asociacion_id, r.fecha, r.monto_dolares, r.monto_total, r.tipo_pago, r.secuencia, r.tasa_cambio, r.referencia,
                    t.nombre AS torneo_nombre
                FROM relacion_pagos r
                LEFT JOIN torneosact t ON r.torneo_id = t.torneo
                WHERE r.asociacion_id = :aid
                ORDER BY r.fecha DESC, r.id DESC
                LIMIT 40'
            );
            $sp->execute([':aid' => $asociacionId]);
            $pagos = $sp->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (PDOException $e) {
            error_log('[StatsService] dashboardDetalleAsociacion pagos: ' . $e->getMessage());
        }

        return [
            'asociacion' => $asoc,
            'deudas'     => $deudas,
            'pagos'      => $pagos,
        ];
    }

    /**
     * Estado de cuenta global por campeonato (grupo_evento_id): suma de deudas y pagos de los torneos vinculados.
     * Desglose por categoría = una fila por torneo del grupo (nombre corto inferido del nombre del evento).
     *
     * @return array{
     *   campeonato_id:int,
     *   asociacion_id:int,
     *   asoc_nombre:string,
     *   totales: array{n_inscritos:int,monto_total_bs:float,monto_total_eur:float|null,pagado_eur:float,saldo_eur:float|null},
     *   por_categoria: list<array{
     *     torneo_id:int,
     *     etiqueta:string,
     *     n_inscritos:int,
     *     n_carnets:int,
     *     n_afiliados:int,
     *     monto_total_bs:float,
     *     monto_total_eur:float|null,
     *     pagado_eur:float,
     *     saldo_eur:float|null
     *   }>
     * }
     */
    public static function obtenerBalanceCampeonato(PDO $pdo, int $campeonato_id, int $asociacion_id): array
    {
        $campeonato_id = max(0, $campeonato_id);
        $asociacion_id = max(0, $asociacion_id);
        $base = [
            'campeonato_id' => $campeonato_id,
            'asociacion_id'   => $asociacion_id,
            'asoc_nombre'     => '',
            'totales'         => [
                'n_inscritos'    => 0,
                'monto_total_bs' => 0.0,
                'monto_total_eur'=> null,
                'pagado_eur'     => 0.0,
                'saldo_eur'      => null,
            ],
            'por_categoria'   => [],
        ];
        if ($campeonato_id <= 0 || $asociacion_id <= 0) {
            return $base;
        }

        $nomAsoc = '';
        try {
            $stN = $pdo->prepare('SELECT nombre FROM asociaciones WHERE id = :id LIMIT 1');
            $stN->execute([':id' => $asociacion_id]);
            $nomAsoc = trim((string) ($stN->fetchColumn() ?: ''));
        } catch (PDOException $e) {
            error_log('[StatsService::obtenerBalanceCampeonato asoc] ' . $e->getMessage());
        }
        $base['asoc_nombre'] = $nomAsoc;

        try {
            $stT = $pdo->prepare(
                'SELECT torneo, nombre FROM torneosact WHERE grupo_evento_id = :g ORDER BY tipo ASC, nombre ASC'
            );
            $stT->execute([':g' => $campeonato_id]);
            $torneos = $stT->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (PDOException $e) {
            error_log('[StatsService::obtenerBalanceCampeonato torneos] ' . $e->getMessage());

            return $base;
        }

        require_once __DIR__ . '/FvdAdminService.php';
        $svc = new FvdAdminService($pdo);

        $sumInsc = 0;
        $sumBs = 0.0;
        $sumEurDeuda = null;
        $sumPag = 0.0;
        $porCat = [];

        foreach ($torneos as $tr) {
            $tid = (int) ($tr['torneo'] ?? 0);
            if ($tid <= 0) {
                continue;
            }
            $etiqueta = $svc->nombreCortaTorneoCampeonato((string) ($tr['nombre'] ?? ''));

            $nInsc = 0;
            $nCarn = 0;
            $nAfi = 0;
            try {
                $stA = $pdo->prepare(
                    'SELECT
                        SUM(CASE WHEN COALESCE(a.inscripcion,0)=1 THEN 1 ELSE 0 END) AS n_insc,
                        SUM(CASE WHEN COALESCE(a.carnet,0)=1 THEN 1 ELSE 0 END) AS n_carn,
                        SUM(CASE WHEN COALESCE(a.afiliacion,0)=1 THEN 1 ELSE 0 END) AS n_afi
                    FROM atletas a
                    WHERE a.torneo_id = :t AND a.asociacion = :a'
                );
                $stA->execute([':t' => $tid, ':a' => $asociacion_id]);
                $rowA = $stA->fetch(PDO::FETCH_ASSOC) ?: [];
                $nInsc = (int) ($rowA['n_insc'] ?? 0);
                $nCarn = (int) ($rowA['n_carn'] ?? 0);
                $nAfi = (int) ($rowA['n_afi'] ?? 0);
            } catch (PDOException $e) {
                error_log('[StatsService::obtenerBalanceCampeonato atletas] ' . $e->getMessage());
            }

            $montoBs = 0.0;
            $montoEur = null;
            try {
                $stD = $pdo->prepare('SELECT monto_total, monto_total_eur FROM deuda_asociaciones WHERE torneo_id = :t AND asociacion_id = :a LIMIT 1');
                $stD->execute([':t' => $tid, ':a' => $asociacion_id]);
                $rowD = $stD->fetch(PDO::FETCH_ASSOC);
                if (is_array($rowD)) {
                    $montoBs = (float) ($rowD['monto_total'] ?? 0);
                    if (isset($rowD['monto_total_eur']) && $rowD['monto_total_eur'] !== null && $rowD['monto_total_eur'] !== '') {
                        $montoEur = (float) $rowD['monto_total_eur'];
                    }
                }
            } catch (PDOException $e) {
                error_log('[StatsService::obtenerBalanceCampeonato deuda] ' . $e->getMessage());
            }

            $pagEur = 0.0;
            try {
                $stP = $pdo->prepare('SELECT COALESCE(SUM(monto_dolares),0) FROM relacion_pagos WHERE torneo_id = :t AND asociacion_id = :a');
                $stP->execute([':t' => $tid, ':a' => $asociacion_id]);
                $pagEur = (float) $stP->fetchColumn();
            } catch (PDOException $e) {
                error_log('[StatsService::obtenerBalanceCampeonato pagos] ' . $e->getMessage());
            }

            $saldoEur = null;
            if ($montoEur !== null && $montoEur > 0) {
                $saldoEur = max(0.0, round($montoEur - $pagEur, 2));
            }

            $sumInsc += $nInsc;
            $sumBs += $montoBs;
            $sumPag += $pagEur;
            if ($montoEur !== null) {
                $sumEurDeuda = ($sumEurDeuda ?? 0.0) + $montoEur;
            }

            $porCat[] = [
                'torneo_id'      => $tid,
                'etiqueta'       => $etiqueta,
                'n_inscritos'    => $nInsc,
                'n_carnets'      => $nCarn,
                'n_afiliados'    => $nAfi,
                'monto_total_bs' => $montoBs,
                'monto_total_eur'=> $montoEur,
                'pagado_eur'     => round($pagEur, 2),
                'saldo_eur'      => $saldoEur,
            ];
        }

        $totSaldo = null;
        if ($sumEurDeuda !== null && $sumEurDeuda > 0) {
            $totSaldo = max(0.0, round((float) $sumEurDeuda - $sumPag, 2));
        }

        $base['totales'] = [
            'n_inscritos'    => $sumInsc,
            'monto_total_bs' => $sumBs,
            'monto_total_eur'=> $sumEurDeuda,
            'pagado_eur'     => round($sumPag, 2),
            'saldo_eur'      => $totSaldo,
        ];
        $base['por_categoria'] = $porCat;

        return $base;
    }

    /**
     * Atletas activos de la asociación que aún no tienen carnet solicitado (pendientes de carnetización).
     *
     * @return list<array<string, mixed>>
     */
    public static function listadoAtletasActivosCarnetPendiente(PDO $pdo, int $asociacionId, int $limit = 500): array
    {
        $asociacionId = max(0, $asociacionId);
        $limit = max(1, min(2000, $limit));
        if ($asociacionId <= 0) {
            return [];
        }
        $sql = 'SELECT a.id, a.cedula, a.nombre, a.numfvd, a.estatus, a.carnet
            FROM atletas a
            WHERE a.asociacion = :aid
            AND COALESCE(a.estatus, 0) = 1
            AND (a.carnet IS NULL OR a.carnet = 0)
            ORDER BY a.nombre ASC, a.id ASC
            LIMIT ' . (int) $limit;
        try {
            $st = $pdo->prepare($sql);
            $st->execute([':aid' => $asociacionId]);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('[StatsService::listadoAtletasActivosCarnetPendiente] ' . $e->getMessage());

            return [];
        }

        return \is_array($rows) ? $rows : [];
    }

    /**
     * Reporte consolidado de deudas agrupado por asociación (suma de todas las filas `deuda_asociaciones`).
     * Afiliación / inscripciones / traspasos corresponden a los montos generados por el sistema de deudas.
     *
     * @return array{
     *   rows: list<array{
     *     asociacion_id:int,
     *     nombre:string,
     *     monto_afiliacion:float,
     *     monto_inscripciones:float,
     *     monto_traspasos:float,
     *     monto_carnets:float,
     *     monto_anualidad:float,
     *     deuda_total:float,
     *     pagos_eur:float,
     *     saldo:float|null
     *   }>,
     *   totales: array{
     *     monto_afiliacion:float,
     *     monto_inscripciones:float,
     *     monto_traspasos:float,
     *     monto_carnets:float,
     *     monto_anualidad:float,
     *     deuda_total:float,
     *     pagos_eur:float,
     *     saldo:float|null
     *   },
     *   usa_eur: bool
     * }
     */
    public static function reporteConsolidadoDeudasPorAsociacion(PDO $pdo): array
    {
        $usaEur = false;
        try {
            $stc = $pdo->query("SHOW COLUMNS FROM deuda_asociaciones LIKE 'monto_total_eur'");
            $usaEur = $stc !== false && $stc->fetch() !== false;
        } catch (PDOException $e) {
            $usaEur = false;
        }
        $exprDeudaAgg = $usaEur
            ? 'COALESCE(SUM(COALESCE(d.monto_total_eur, d.monto_total, 0)), 0)'
            : 'COALESCE(SUM(COALESCE(d.monto_total, 0)), 0)';

        $sql = 'SELECT
                s.id AS asociacion_id,
                COALESCE(NULLIF(TRIM(s.nombre), \'\'), CONCAT(\'Asoc #\', s.id)) AS nombre,
                COALESCE(SUM(COALESCE(d.monto_afiliados, 0)), 0) AS monto_afiliacion,
                COALESCE(SUM(COALESCE(d.monto_inscritos, 0)), 0) AS monto_inscripciones,
                COALESCE(SUM(COALESCE(d.monto_traspasos, 0)), 0) AS monto_traspasos,
                COALESCE(SUM(COALESCE(d.monto_carnets, 0)), 0) AS monto_carnets,
                COALESCE(SUM(COALESCE(d.monto_anualidad, 0)), 0) AS monto_anualidad,
                ' . $exprDeudaAgg . ' AS deuda_total,
                COALESCE(MAX(p.pagos_eur), 0) AS pagos_eur
            FROM asociaciones s
            LEFT JOIN deuda_asociaciones d ON d.asociacion_id = s.id
            LEFT JOIN (
                SELECT asociacion_id, SUM(COALESCE(monto_dolares, 0)) AS pagos_eur
                FROM relacion_pagos
                GROUP BY asociacion_id
            ) p ON p.asociacion_id = s.id
            GROUP BY s.id, s.nombre
            ORDER BY nombre ASC';

        $empty = [
            'rows'    => [],
            'totales' => [
                'monto_afiliacion'     => 0.0,
                'monto_inscripciones'  => 0.0,
                'monto_traspasos'      => 0.0,
                'monto_carnets'        => 0.0,
                'monto_anualidad'      => 0.0,
                'deuda_total'          => 0.0,
                'pagos_eur'            => 0.0,
                'saldo'                => null,
            ],
            'usa_eur' => $usaEur,
        ];

        try {
            $st = $pdo->query($sql);
            $raw = $st ? $st->fetchAll(PDO::FETCH_ASSOC) : [];
        } catch (PDOException $e) {
            error_log('[StatsService::reporteConsolidadoDeudasPorAsociacion] ' . $e->getMessage());

            return $empty;
        }

        $rows = [];
        $tot = $empty['totales'];
        foreach ($raw as $r) {
            $deuda = (float) ($r['deuda_total'] ?? 0);
            $pag = (float) ($r['pagos_eur'] ?? 0);
            $saldo = null;
            if ($usaEur && $deuda > 0) {
                $saldo = round(max(0.0, $deuda - $pag), 2);
            } elseif (!$usaEur && $deuda > 0) {
                $saldo = round(max(0.0, $deuda - $pag), 2);
            }

            $rows[] = [
                'asociacion_id'      => (int) ($r['asociacion_id'] ?? 0),
                'nombre'             => (string) ($r['nombre'] ?? ''),
                'monto_afiliacion'   => (float) ($r['monto_afiliacion'] ?? 0),
                'monto_inscripciones'=> (float) ($r['monto_inscripciones'] ?? 0),
                'monto_traspasos'    => (float) ($r['monto_traspasos'] ?? 0),
                'monto_carnets'      => (float) ($r['monto_carnets'] ?? 0),
                'monto_anualidad'    => (float) ($r['monto_anualidad'] ?? 0),
                'deuda_total'        => $deuda,
                'pagos_eur'          => $pag,
                'saldo'              => $saldo,
            ];

            $tot['monto_afiliacion'] += (float) ($r['monto_afiliacion'] ?? 0);
            $tot['monto_inscripciones'] += (float) ($r['monto_inscripciones'] ?? 0);
            $tot['monto_traspasos'] += (float) ($r['monto_traspasos'] ?? 0);
            $tot['monto_carnets'] += (float) ($r['monto_carnets'] ?? 0);
            $tot['monto_anualidad'] += (float) ($r['monto_anualidad'] ?? 0);
            $tot['deuda_total'] += $deuda;
            $tot['pagos_eur'] += $pag;
        }

        $tot['monto_afiliacion'] = round($tot['monto_afiliacion'], 2);
        $tot['monto_inscripciones'] = round($tot['monto_inscripciones'], 2);
        $tot['monto_traspasos'] = round($tot['monto_traspasos'], 2);
        $tot['monto_carnets'] = round($tot['monto_carnets'], 2);
        $tot['monto_anualidad'] = round($tot['monto_anualidad'], 2);
        $tot['deuda_total'] = round($tot['deuda_total'], 2);
        $tot['pagos_eur'] = round($tot['pagos_eur'], 2);
        if ($tot['deuda_total'] > 0) {
            $tot['saldo'] = round(max(0.0, $tot['deuda_total'] - $tot['pagos_eur']), 2);
        }

        return [
            'rows'    => $rows,
            'totales' => $tot,
            'usa_eur' => $usaEur,
        ];
    }

    /**
     * Indica si las deudas usan columna EUR en `deuda_asociaciones`.
     */
    public static function deudaAsociacionesUsaEur(PDO $pdo): bool
    {
        try {
            $stc = $pdo->query("SHOW COLUMNS FROM deuda_asociaciones LIKE 'monto_total_eur'");

            return $stc !== false && $stc->fetch() !== false;
        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * Torneos con deuda registrada para una asociación (filas en `deuda_asociaciones`).
     *
     * @return list<array{
     *   torneo_id:int,
     *   torneo_nombre:string,
     *   deuda:float,
     *   pagos:float,
     *   saldo:float|null,
     *   estatus_pago:string,
     *   usa_eur:bool
     * }>
     */
    public static function torneosDeudaPorAsociacion(PDO $pdo, int $asociacionId): array
    {
        if ($asociacionId <= 0) {
            return [];
        }
        $usaEur = self::deudaAsociacionesUsaEur($pdo);
        $exprDeuda = $usaEur
            ? 'COALESCE(d.monto_total_eur, d.monto_total, 0)'
            : 'COALESCE(d.monto_total, 0)';

        $sql = 'SELECT
                d.torneo_id,
                COALESCE(NULLIF(TRIM(t.nombre), \'\'), CONCAT(\'Torneo #\', d.torneo_id)) AS torneo_nombre,
                ' . $exprDeuda . ' AS deuda_val,
                COALESCE((
                    SELECT SUM(COALESCE(r.monto_dolares, 0))
                    FROM relacion_pagos r
                    WHERE r.torneo_id = d.torneo_id AND r.asociacion_id = d.asociacion_id
                ), 0) AS pagos_val
            FROM deuda_asociaciones d
            INNER JOIN torneosact t ON t.torneo = d.torneo_id
            WHERE d.asociacion_id = :aid
            ORDER BY t.fechator DESC, d.torneo_id DESC';

        try {
            $st = $pdo->prepare($sql);
            $st->execute([':aid' => $asociacionId]);
            $raw = $st->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('[StatsService::torneosDeudaPorAsociacion] ' . $e->getMessage());

            return [];
        }

        $out = [];
        foreach ($raw as $r) {
            $deuda = round((float) ($r['deuda_val'] ?? 0), 4);
            $pag = round((float) ($r['pagos_val'] ?? 0), 4);
            $saldo = null;
            if ($deuda > 0.0001) {
                $saldo = round(max(0.0, $deuda - $pag), 2);
            }
            $estatus = 'Sin cargo';
            if ($deuda > 0.0001) {
                if ($pag >= $deuda - 0.02) {
                    $estatus = 'Liquidado';
                } elseif ($pag > 0.01) {
                    $estatus = 'Parcial';
                } else {
                    $estatus = 'Pendiente';
                }
            }

            $out[] = [
                'torneo_id'     => (int) ($r['torneo_id'] ?? 0),
                'torneo_nombre' => (string) ($r['torneo_nombre'] ?? ''),
                'deuda'         => round($deuda, 2),
                'pagos'         => round($pag, 2),
                'saldo'         => $saldo,
                'estatus_pago'  => $estatus,
                'usa_eur'       => $usaEur,
            ];
        }

        return $out;
    }

    /**
     * Conteos y montos por segmento (renglones) para un par torneo + asociación.
     *
     * @return array{
     *   afiliados: array{count:int,monto:float},
     *   inscritos: array{count:int,monto:float},
     *   carnets: array{count:int,monto:float}
     * }
     */
    public static function segmentosRenglonesTorneoAsociacion(PDO $pdo, int $torneoId, int $asociacionId): array
    {
        $emptySeg = static function (): array {
            return ['count' => 0, 'monto' => 0.0];
        };
        $out = [
            'afiliados' => $emptySeg(),
            'inscritos' => $emptySeg(),
            'carnets'   => $emptySeg(),
        ];
        if ($torneoId <= 0 || $asociacionId <= 0) {
            return $out;
        }

        try {
            $st = $pdo->prepare(
                'SELECT total_afiliados, monto_afiliados, total_inscritos, monto_inscritos, total_carnets, monto_carnets
                FROM deuda_asociaciones WHERE torneo_id = :t AND asociacion_id = :a LIMIT 1'
            );
            $st->execute([':t' => $torneoId, ':a' => $asociacionId]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('[StatsService::segmentosRenglonesTorneoAsociacion] ' . $e->getMessage());

            return $out;
        }
        if ($row === false) {
            return $out;
        }

        $out['afiliados'] = [
            'count' => (int) ($row['total_afiliados'] ?? 0),
            'monto' => round((float) ($row['monto_afiliados'] ?? 0), 2),
        ];
        $out['inscritos'] = [
            'count' => (int) ($row['total_inscritos'] ?? 0),
            'monto' => round((float) ($row['monto_inscritos'] ?? 0), 2),
        ];
        $out['carnets'] = [
            'count' => (int) ($row['total_carnets'] ?? 0),
            'monto' => round((float) ($row['monto_carnets'] ?? 0), 2),
        ];

        return $out;
    }

    /**
     * Listado nominal por segmento (afiliados / inscritos / carnets) para torneo + asociación.
     *
     * @param 'afiliados'|'inscritos'|'carnets' $segmento
     *
     * @return list<array{id:int|string, cedula:string, nombre:string, monto:float, fecha:string|null}>
     */
    public static function detalleNominalTorneoAsociacion(PDO $pdo, int $torneoId, int $asociacionId, string $segmento): array
    {
        if ($torneoId <= 0 || $asociacionId <= 0) {
            return [];
        }
        $allowed = ['afiliados' => true, 'inscritos' => true, 'carnets' => true];
        $seg = isset($allowed[$segmento]) ? $segmento : '';
        if ($seg === '') {
            return [];
        }

        $costo = DeudaAsociacionGeneratorService::ultimoCosto($pdo);
        if ($costo === null) {
            return [];
        }
        $puAfi = (float) ($costo['afiliacion'] ?? 0);
        $puIns = (float) ($costo['inscripciones'] ?? 0);
        $puCar = (float) ($costo['carnets'] ?? 0);

        $usaIt = DeudaAsociacionGeneratorService::conteosUsanTablaInscripcionTorneo($pdo);

        try {
            if ($usaIt) {
                return self::detalleNominalDesdeInscripcionTorneo($pdo, $torneoId, $asociacionId, $seg, $puAfi, $puIns, $puCar);
            }

            return self::detalleNominalDesdeAtletas($pdo, $torneoId, $asociacionId, $seg, $puAfi, $puIns, $puCar);
        } catch (PDOException $e) {
            error_log('[StatsService::detalleNominalTorneoAsociacion] ' . $e->getMessage());

            return [];
        }
    }



    
    /**
     * @param 'afiliados'|'inscritos'|'carnets' $seg
     *
     * @return list<array{id:int|string, cedula:string, nombre:string, monto:float, fecha:string|null}>
     */
    private static function detalleNominalDesdeAtletas(
        PDO $pdo,
        int $torneoId,
        int $asociacionId,
        string $seg,
        float $puAfi,
        float $puIns,
        float $puCar
    ): array {
        switch ($seg) {
            case 'afiliados':
                $whereExtra = 'COALESCE(a.afiliacion, 0) = 1';
                $pu = $puAfi;
                break;
            case 'inscritos':
                $whereExtra = 'COALESCE(a.inscripcion, 0) = 1 AND COALESCE(a.afiliacion, 0) = 0';
                $pu = $puIns;
                break;
            case 'carnets':
                $whereExtra = 'COALESCE(a.carnet, 0) = 1';
                $pu = $puCar;
                break;
            default:
                $whereExtra = '1=0';
                $pu = 0.0;
        }

        $sql = 'SELECT a.id, a.cedula, a.nombre, a.fechact, a.fechfvd
            FROM atletas a
            WHERE a.torneo_id = :t AND a.asociacion = :a AND ' . $whereExtra . '
            ORDER BY a.nombre ASC, a.id ASC';
        $st = $pdo->prepare($sql);
        $st->execute([':t' => $torneoId, ':a' => $asociacionId]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'id'     => (int) ($r['id'] ?? 0),
                'cedula' => (string) ($r['cedula'] ?? ''),
                'nombre' => (string) ($r['nombre'] ?? ''),
                'monto'  => round($pu, 2),
                'fecha'  => self::normalizarFechaAtleta($r['fechact'] ?? null, $r['fechfvd'] ?? null),
            ];
        }

        return $out;
    }

    /**
     * @param 'afiliados'|'inscritos'|'carnets' $seg
     *
     * @return list<array{id:int|string, cedula:string, nombre:string, monto:float, fecha:string|null}>
     */
    private static function detalleNominalDesdeInscripcionTorneo(
        PDO $pdo,
        int $torneoId,
        int $asociacionId,
        string $seg,
        float $puAfi,
        float $puIns,
        float $puCar
    ): array {
        switch ($seg) {
            case 'afiliados':
                $whereExtra = 'COALESCE(it.afiliacion, 0) = 1';
                $pu = $puAfi;
                break;
            case 'inscritos':
                $whereExtra = 'COALESCE(it.inscripcion, 0) IN (1, 2) AND COALESCE(it.afiliacion, 0) = 0';
                $pu = $puIns;
                break;
            case 'carnets':
                $whereExtra = 'COALESCE(it.carnet, 0) = 1';
                $pu = $puCar;
                break;
            default:
                $whereExtra = '1=0';
                $pu = 0.0;
        }

        $sql = 'SELECT it.id, it.cedula, it.nombre, it.fecha_inscripcion, it.fecha_actualizacion
            FROM inscripcion_torneo it
            WHERE it.torneo_id = :t AND it.asociacion_id = :a AND ' . $whereExtra . '
            ORDER BY it.nombre ASC, it.id ASC';
        $st = $pdo->prepare($sql);
        $st->execute([':t' => $torneoId, ':a' => $asociacionId]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        $out = [];
        foreach ($rows as $r) {
            $fd = $r['fecha_actualizacion'] ?? null;
            $fi = $r['fecha_inscripcion'] ?? null;
            $fecha = self::elegirFechaInscripcionTorneo($fd, $fi);
            $out[] = [
                'id'     => (int) ($r['id'] ?? 0),
                'cedula' => (string) ($r['cedula'] ?? ''),
                'nombre' => (string) ($r['nombre'] ?? ''),
                'monto'  => round($pu, 2),
                'fecha'  => $fecha,
            ];
        }

        return $out;
    }

    /**
     * @param mixed $fechact
     * @param mixed $fechfvd
     */
    private static function normalizarFechaAtleta($fechact, $fechfvd): ?string
    {
        $a = self::fechaSqlValida($fechact) ? (string) $fechact : null;
        $b = self::fechaSqlValida($fechfvd) ? (string) $fechfvd : null;
        if ($a === null && $b === null) {
            return null;
        }
        if ($a === null) {
            return $b;
        }
        if ($b === null) {
            return $a;
        }

        return strtotime($a) >= strtotime($b) ? $a : $b;
    }

    private static function elegirFechaInscripcionTorneo($fd, $fi): ?string
    {
        $d1 = self::fechaSqlValida($fd) ? (string) $fd : null;
        $d2 = self::fechaSqlValida($fi) ? (string) $fi : null;
        if ($d1 === null) {
            return $d2;
        }
        if ($d2 === null) {
            return $d1;
        }

        return strtotime($d1) >= strtotime($d2) ? $d1 : $d2;
    }

    /**
     * @param mixed $v
     */
    private static function fechaSqlValida($v): bool
    {
        if ($v === null || $v === '') {
            return false;
        }
        $s = (string) $v;

        return strpos($s, '0000-00-00') !== 0;
    }
}
