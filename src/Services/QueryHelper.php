<?php

declare(strict_types=1);

namespace FvdPortal\Services;

use InvalidArgumentException;
use PDO;
use PDOStatement;

require_once dirname(__DIR__, 2) . '/fvdmasteradmin/config/db.php';
require_once __DIR__ . '/FvdAdminService.php';

/**
 * Consultas PDO genéricas (portal). No colisiona con la clase global {@see \QueryHelper} del master admin.
 */
final class QueryHelper
{
    /**
     * Listado paginado por igualdad en columnas (AND).
     *
     * Si $tabla es "atletas", listado admin (LIKE cédula/nombre, alcance solo por asociación).
     * Claves: __cedula, __nombre, __smart (búsqueda; si no vacía, ignora __cedula/__nombre salvo __smart_mode vacío con modo legacy),
     * __smart_mode (opc.: ''|cedula|email|nombre; con __smart define ramas optimizadas en listado atletas),
     * __alcance (todos|asociacion), __tipo (normal|ultimos|no_activos|bajas), __asociacion_id, __marcador (opc.).
     *
     * @param array<string, scalar|null> $filtros column => valor; columnas [a-zA-Z0-9_], salvo claves __* en modo atletas
     * @return array{registros: list<array<string, mixed>>, total: int, paginas: int}
     */
    public static function selectPaginado(
        string $tabla,
        array $filtros,
        int $pagina,
        int $limite,
        ?PDO $pdo = null
    ): array {
        $pdo = $pdo ?? fvd_db();

        if ($tabla === 'atletas') {
            $cedula = isset($filtros['__cedula']) ? (string) $filtros['__cedula'] : '';
            $nombre = isset($filtros['__nombre']) ? (string) $filtros['__nombre'] : '';
            $smart = isset($filtros['__smart']) ? trim((string) $filtros['__smart']) : '';
            $smartMode = isset($filtros['__smart_mode']) ? trim((string) $filtros['__smart_mode']) : '';
            $alcance = isset($filtros['__alcance']) ? trim((string) $filtros['__alcance']) : 'todos';
            $tipo = isset($filtros['__tipo']) ? trim((string) $filtros['__tipo']) : 'normal';
            $asociacionId = isset($filtros['__asociacion_id']) ? (int) $filtros['__asociacion_id'] : 0;
            $marcador = isset($filtros['__marcador']) ? trim((string) $filtros['__marcador']) : '';

            return self::selectPaginadoAtletasAdmin($cedula, $nombre, $alcance, $tipo, $asociacionId, $pagina, $limite, $pdo, $marcador, $smart, $smartMode);
        }

        if ($tabla === 'fvd_invitaciones') {
            $estadoUi = isset($filtros['__estado']) ? trim((string) $filtros['__estado']) : '';
            $emisorFiltro = array_key_exists('__emisor_id', $filtros) ? $filtros['__emisor_id'] : null;

            return self::selectPaginadoInvitaciones($estadoUi, $emisorFiltro, $pagina, $limite, $pdo);
        }

        $tablaSql = self::identifier($tabla);

        $limite = max(1, min(100, $limite));
        $pagina = max(1, $pagina);
        $offset = ($pagina - 1) * $limite;

        $whereParts = [];
        $params = [];
        $i = 0;
        foreach ($filtros as $col => $val) {
            if (!is_string($col) || $col === '') {
                continue;
            }
            $colSql = self::bareColumn($col);
            $ph = ':f' . $i;
            $whereParts[] = $colSql . ' = ' . $ph;
            $params[$ph] = $val;
            ++$i;
        }

        $whereSql = $whereParts === [] ? '' : ' WHERE ' . implode(' AND ', $whereParts);

        $sqlCount = 'SELECT COUNT(*) FROM ' . $tablaSql . $whereSql;
        $stmtCount = $pdo->prepare($sqlCount);
        self::bindNamed($stmtCount, $params);
        $stmtCount->execute();
        $total = (int) $stmtCount->fetchColumn();

        $sqlData = 'SELECT * FROM ' . $tablaSql . $whereSql
            . ' LIMIT ' . (int) $limite . ' OFFSET ' . (int) $offset;
        $stmt = $pdo->prepare($sqlData);
        self::bindNamed($stmt, $params);
        $stmt->execute();
        $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $paginas = $total > 0 ? (int) ceil($total / $limite) : 1;

        return [
            'registros' => $registros,
            'total'     => $total,
            'paginas'   => $paginas,
        ];
    }

    /**
     * Listado paginado de invitaciones (filtro UI por estado + alcance delegado).
     *
     * @param string $estadoUi pendiente|aceptada|expirada|''
     * @param mixed $emisorFiltro null = sin filtro (FVD). int >= 0 = id emisor. int -1 = sin resultados (delegado sin asociación).
     * @return array{registros: list<array<string, mixed>>, total: int, paginas: int}
     */
    private static function selectPaginadoInvitaciones(
        string $estadoUi,
        $emisorFiltro,
        int $pagina,
        int $limite,
        PDO $pdo
    ): array {
        if (!in_array($estadoUi, ['pendiente', 'aceptada', 'expirada', ''], true)) {
            $estadoUi = '';
        }

        $limite = max(1, min(100, $limite));
        $pagina = max(1, $pagina);
        $offset = ($pagina - 1) * $limite;

        $where = [];
        $params = [];

        if ($emisorFiltro !== null) {
            $e = (int) $emisorFiltro;
            if ($e < 0) {
                $where[] = '0=1';
            } elseif ($e > 0) {
                $where[] = 'i.asociacion_emisor_id = :emisor';
                $params[':emisor'] = $e;
            }
        }

        if ($estadoUi === 'pendiente') {
            $where[] = "i.estado = 'pendiente' AND i.expira_en > NOW() AND (i.un_solo_uso = 0 OR i.usada_en IS NULL)";
        } elseif ($estadoUi === 'aceptada') {
            $where[] = "(i.estado = 'aceptada' OR i.usada_en IS NOT NULL)";
        } elseif ($estadoUi === 'expirada') {
            $where[] = "i.estado = 'pendiente' AND i.expira_en <= NOW() AND i.usada_en IS NULL";
        }

        $whereSql = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);

        $sqlCount = 'SELECT COUNT(*) FROM fvd_invitaciones i' . $whereSql;
        $stmtCount = $pdo->prepare($sqlCount);
        self::bindNamed($stmtCount, $params);
        $stmtCount->execute();
        $total = (int) $stmtCount->fetchColumn();

        $sqlData = 'SELECT i.* FROM fvd_invitaciones i' . $whereSql
            . ' ORDER BY i.id DESC LIMIT ' . (int) $limite . ' OFFSET ' . (int) $offset;
        $stmt = $pdo->prepare($sqlData);
        self::bindNamed($stmt, $params);
        $stmt->execute();
        $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $paginas = $total > 0 ? (int) ceil($total / $limite) : 1;

        return [
            'registros' => is_array($registros) ? $registros : [],
            'total'     => $total,
            'paginas'   => $paginas,
        ];
    }

    /**
     * @param string $marcadorList carnet|traspaso|afiliacion|anualidad|inscripcion|''
     */
    private static function selectPaginadoAtletasAdmin(
        string $cedula,
        string $nombre,
        string $alcance,
        string $tipo,
        int $asociacionId,
        int $pagina,
        int $limite,
        PDO $pdo,
        string $marcadorList = '',
        string $smart = '',
        string $smartMode = ''
    ): array {
        $legacy = dirname(__DIR__, 2) . '/fvdmasteradmin/services/QueryHelper.php';
        if (!\class_exists('QueryHelper', false)) {
            require_once $legacy;
        }

        $parts = self::atletasAdminListFragments($cedula, $nombre, $alcance, $tipo, $asociacionId, $smart, $smartMode);
        $search = $parts['search'];
        $orderBy = $parts['order_by'];
        $params = $parts['params'];
        $search .= self::atletasListMarcadorSql($marcadorList);
        $search .= self::delegadoSqlFiltroSexoTorneoActivo($pdo);

        $countSql = 'SELECT COUNT(*) FROM atletas a WHERE 1=1' . $search;
        $dataSql = 'SELECT a.id, a.foto, a.cedula, a.nombre, a.sexo, a.numfvd, a.estatus, a.celular, a.email, a.asociacion, a.categ,
            a.carnet, a.traspaso, a.fechnac,
            s.nombre AS asociacion_nombre
            FROM atletas a
            LEFT JOIN asociaciones s ON a.asociacion = s.id
            WHERE 1=1' . $search . ' ' . $orderBy;

        $limite = max(1, min(100, $limite));
        $pagina = max(1, $pagina);

        $out = \QueryHelper::paginateWithAsociacionScope(
            $pdo,
            $countSql,
            $dataSql,
            $params,
            $pagina,
            $limite,
            'a.asociacion'
        );

        return [
            'registros' => $out['rows'],
            'total'     => $out['total'],
            'paginas'   => $out['pages'],
        ];
    }

    /**
     * Fragmentos SQL compartidos entre listado paginado y exportación completa.
     *
     * @param string $alcance todos|asociacion
     * @param string $tipo normal|ultimos|no_activos|bajas
     * @return array{search: string, order_by: string, params: array<string, mixed>}
     */
    public static function atletasAdminListFragments(
        string $cedula,
        string $nombre,
        string $alcance,
        string $tipo,
        int $asociacionId,
        string $smart = '',
        string $smartMode = ''
    ): array {
        $baja = \FvdAdminService::ATLETA_ESTATUS_BAJA;
        $pend = \FvdAdminService::ATLETA_ESTATUS_PENDIENTE;
        $alcanceOk = ['todos', 'asociacion'];
        $tipoOk = ['normal', 'ultimos', 'no_activos', 'bajas'];
        if (!in_array($alcance, $alcanceOk, true)) {
            $alcance = 'todos';
        }
        if (!in_array($tipo, $tipoOk, true)) {
            $tipo = 'normal';
        }

        $params = [];
        $search = '';

        $smartTrim = trim($smart);
        $modeOk = ['cedula', 'email', 'nombre'];
        $mode = in_array($smartMode, $modeOk, true) ? $smartMode : '';

        if ($smartTrim !== '' && $mode !== '') {
            if ($mode === 'cedula') {
                $params[':ced_list_trim'] = $smartTrim;
                $cedParts = ['TRIM(a.cedula) = :ced_list_trim'];
                $digits = preg_replace('/\D+/', '', $smartTrim);
                if ($digits !== '') {
                    $params[':ced_list_dig'] = $digits;
                    $cedParts[] = 'REPLACE(REPLACE(REPLACE(TRIM(a.cedula), \'.\', \'\'), \'-\', \'\'), \' \', \'\') = :ced_list_dig';
                }
                $search .= ' AND (' . implode(' OR ', $cedParts) . ') ';
            } elseif ($mode === 'email') {
                $params[':fsmart_em'] = '%' . $smartTrim . '%';
                $search .= ' AND COALESCE(TRIM(a.email), \'\') LIKE :fsmart_em ';
            } else {
                /** @var list<string> $tokens */
                $tokens = preg_split('/\s+/u', $smartTrim, -1, PREG_SPLIT_NO_EMPTY) ?: [];
                $ti = 0;
                foreach ($tokens as $tk) {
                    $ph = ':fsmart_nom' . $ti;
                    $params[$ph] = '%' . $tk . '%';
                    $search .= ' AND a.nombre LIKE ' . $ph . ' ';
                    ++$ti;
                }
            }
        } elseif ($smartTrim !== '') {
            $params[':fsmart'] = '%' . $smartTrim . '%';
            $search .= ' AND (
                TRIM(a.cedula) LIKE :fsmart
                OR a.nombre LIKE :fsmart
                OR COALESCE(TRIM(a.email), \'\') LIKE :fsmart
            ) ';
        } else {
            if ($cedula !== '') {
                $digits = preg_replace('/\D+/', '', $cedula);
                $params[':fced'] = $digits !== '' ? $digits . '%' : '%' . $cedula . '%';
                $search .= ' AND a.cedula LIKE :fced ';
            }
            if ($nombre !== '') {
                $params[':fnom'] = '%' . $nombre . '%';
                $search .= ' AND a.nombre LIKE :fnom ';
            }
        }

        if ($tipo === 'bajas') {
            $params[':est_baja'] = $baja;
            $search .= ' AND a.estatus = :est_baja ';
        } elseif ($tipo === 'no_activos') {
            $params[':est_pend'] = $pend;
            $search .= ' AND COALESCE(a.estatus,0) = :est_pend ';
        } else {
            $params[':not_baja'] = $baja;
            $search .= ' AND (a.estatus IS NULL OR a.estatus <> :not_baja) ';
        }

        if ($alcance === 'asociacion') {
            if ($asociacionId > 0) {
                $params[':f_aid'] = $asociacionId;
                $search .= ' AND a.asociacion = :f_aid ';
            } else {
                $search .= ' AND 0=1 ';
            }
        }

        $orderBy = 'ORDER BY a.id DESC';
        if ($tipo === 'ultimos') {
            $orderBy = 'ORDER BY COALESCE(a.fechact, a.fechfvd) DESC, a.id DESC';
        }

        return [
            'search'    => $search,
            'order_by'  => $orderBy,
            'params'    => $params,
        ];
    }

    /**
     * Filtro por marcador de servicio (valor 1 en la columna indicada).
     *
     * @param string $marcador carnet|traspaso|afiliacion|anualidad|inscripcion|''
     */
    public static function atletasListMarcadorSql(string $marcador): string
    {
        $m = trim($marcador);
        $map = [
            'carnet'      => ' AND COALESCE(a.carnet, 0) = 1 ',
            'traspaso'    => ' AND COALESCE(a.traspaso, 0) = 1 ',
            'afiliacion'  => ' AND COALESCE(a.afiliacion, 0) = 1 ',
            'anualidad'   => ' AND COALESCE(a.anualidad, 0) = 1 ',
            'inscripcion' => ' AND COALESCE(a.inscripcion, 0) = 1 ',
        ];

        return $m !== '' && isset($map[$m]) ? $map[$m] : '';
    }

    /**
     * Fragmento AND para acotar atletas al género del torneo en contexto (delegado con torneo fijado).
     * Mixto (tipo 3) no añade condición.
     */
    public static function delegadoSqlFiltroSexoTorneoActivo(PDO $pdo): string
    {
        if (!class_exists('AuthService', false) || !\AuthService::isDelegadoAsociacion()) {
            return '';
        }
        $tc = \AuthService::delegadoTorneoContextId();
        $tid = ($tc !== null && (int) $tc > 0) ? (int) $tc : 0;
        if ($tid <= 0) {
            return '';
        }
        $fvd = new \FvdAdminService($pdo);

        return $fvd->sqlAtletasFiltroSexoSegunTorneoTipo($tid, 'a');
    }

    /**
     * Todas las filas del listado admin de atletas (mismos filtros y alcance regional), sin paginar.
     *
     * @param int|null $carnetEquals Solo **1** aplica filtro (`atletas.carnet = 1`). Cualquier otro valor se ignora (el 0 no es indicador de informe).
     * @param string $alcance todos|asociacion
     * @param string $tipo normal|ultimos|no_activos|bajas
     * @return list<array<string, mixed>>
     */
    public static function selectAtletasAdminAll(
        string $cedula,
        string $nombre,
        ?PDO $pdo = null,
        ?int $carnetEquals = null,
        string $alcance = 'todos',
        string $tipo = 'normal',
        int $asociacionId = 0,
        ?string $marcadorLista = null
    ): array {
        $pdo = $pdo ?? fvd_db();
        $legacy = dirname(__DIR__, 2) . '/fvdmasteradmin/services/QueryHelper.php';
        if (!\class_exists('QueryHelper', false)) {
            require_once $legacy;
        }

        $frag = self::atletasAdminListFragments($cedula, $nombre, $alcance, $tipo, $asociacionId, '');
        $search = $frag['search'];
        $orderBy = $frag['order_by'];
        $params = $frag['params'];

        $mar = $marcadorLista !== null ? trim($marcadorLista) : '';
        if ($mar !== '') {
            $search .= self::atletasListMarcadorSql($mar);
        } elseif ($carnetEquals === 1) {
            $params[':carnet_eq'] = 1;
            $search .= ' AND COALESCE(a.carnet, 0) = :carnet_eq ';
        }

        $search .= self::delegadoSqlFiltroSexoTorneoActivo($pdo);

        $dataSql = 'SELECT a.id, a.foto, a.cedula, a.nombre, a.sexo, a.numfvd, a.estatus, a.celular, a.email, a.asociacion, a.categ,
            a.afiliacion, a.anualidad, a.carnet, a.traspaso, a.inscripcion,
            s.nombre AS asociacion_nombre
            FROM atletas a
            LEFT JOIN asociaciones s ON a.asociacion = s.id
            WHERE 1=1' . $search . ' ' . $orderBy;

        $countSql = 'SELECT COUNT(*) FROM atletas a WHERE 1=1' . $search;
        \QueryHelper::applyAsociacionScope($countSql, $dataSql, 'a.asociacion', $params);

        $stmt = $pdo->prepare($dataSql);
        self::bindNamed($stmt, $params);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return is_array($rows) ? $rows : [];
    }

    /**
     * Filas completas de `atletas` (SELECT a.*) con nombre de asociación, según indicadores de servicio.
     *
     * - `cualquiera`: al menos uno de afiliación, anualidad, carnet, traspaso o inscripción está en 1.
     * - `todos`: los cinco están en 1.
     *
     * Si {@see $marcadorFijo} no es null, se listan solo filas con ese marcador (o la pareja) en **1**;
     * en ese caso se ignora {@see $modo}.
     *
     * Respeta el alcance regional ({@see \QueryHelper::applyAsociacionScope} sobre `a.asociacion`).
     *
     * @param 'cualquiera'|'todos' $modo
     * @param 'afiliacion'|'anualidad'|'carnet'|'traspaso'|'inscripcion'|'afiliacion_anualidad'|null $marcadorFijo
     * @return list<array<string, mixed>>
     */
    public static function selectAtletasPorIndicadoresServicioFull(
        string $modo,
        string $cedula = '',
        string $nombre = '',
        ?PDO $pdo = null,
        ?string $marcadorFijo = null,
        int $filtroAsociacionFvd = 0
    ): array {
        $pdo = $pdo ?? fvd_db();
        $legacy = dirname(__DIR__, 2) . '/fvdmasteradmin/services/QueryHelper.php';
        if (!\class_exists('QueryHelper', false)) {
            require_once $legacy;
        }

        $porMarcador = [
            'afiliacion'          => ' AND COALESCE(a.afiliacion, 0) = 1 ',
            'anualidad'           => ' AND COALESCE(a.anualidad, 0) = 1 ',
            'carnet'              => ' AND COALESCE(a.carnet, 0) = 1 ',
            'traspaso'            => ' AND COALESCE(a.traspaso, 0) = 1 ',
            'inscripcion'         => ' AND COALESCE(a.inscripcion, 0) = 1 ',
            'afiliacion_anualidad' => ' AND COALESCE(a.afiliacion, 0) = 1 AND COALESCE(a.anualidad, 0) = 1 ',
        ];
        $mk = $marcadorFijo !== null && $marcadorFijo !== '' ? trim($marcadorFijo) : '';
        if ($mk !== '' && isset($porMarcador[$mk])) {
            $indSql = $porMarcador[$mk];
        } else {
            $modo = $modo === 'todos' ? 'todos' : 'cualquiera';
            if ($modo === 'todos') {
                $indSql = ' AND COALESCE(a.afiliacion, 0) = 1 AND COALESCE(a.anualidad, 0) = 1 AND COALESCE(a.carnet, 0) = 1'
                    . ' AND COALESCE(a.traspaso, 0) = 1 AND COALESCE(a.inscripcion, 0) = 1 ';
            } else {
                $indSql = ' AND (COALESCE(a.afiliacion, 0) = 1 OR COALESCE(a.anualidad, 0) = 1 OR COALESCE(a.carnet, 0) = 1'
                    . ' OR COALESCE(a.traspaso, 0) = 1 OR COALESCE(a.inscripcion, 0) = 1) ';
            }
        }

        $indSql .= self::delegadoSqlFiltroSexoTorneoActivo($pdo);

        $params = [];
        $search = '';
        if ($cedula !== '') {
            $digits = preg_replace('/\D+/', '', $cedula);
            $params[':fced'] = $digits !== '' ? $digits . '%' : '%' . $cedula . '%';
            $search .= ' AND a.cedula LIKE :fced ';
        }
        if ($nombre !== '') {
            $params[':fnom'] = '%' . $nombre . '%';
            $search .= ' AND a.nombre LIKE :fnom ';
        }

        $filtroAsocSql = '';
        if ($filtroAsociacionFvd > 0) {
            $params[':_fvd_rep_asoc'] = $filtroAsociacionFvd;
            $filtroAsocSql = ' AND a.asociacion = :_fvd_rep_asoc ';
        }

        $dataSql = 'SELECT a.*, s.nombre AS asociacion_nombre
            FROM atletas a
            LEFT JOIN asociaciones s ON a.asociacion = s.id
            WHERE 1=1' . $indSql . $search . $filtroAsocSql . ' ORDER BY a.id ASC';

        $countSql = 'SELECT COUNT(*) FROM atletas a WHERE 1=1' . $indSql . $search . $filtroAsocSql;

        \QueryHelper::applyAsociacionScope($countSql, $dataSql, 'a.asociacion', $params);

        $stmt = $pdo->prepare($dataSql);
        self::bindNamed($stmt, $params);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return is_array($rows) ? $rows : [];
    }

    /**
     * Fragmento SQL compartido: SUM por cada bandera en `atletas` (IFNULL(campo,0)=1).
     * Usado en todos los reportes de indicadores globales / por asociación.
     *
     * @return string Lista de expresiones SUM… AS campo (sin COUNT inicial)
     */
    public static function sqlSumCasesIndicadoresAtletas(string $tableAlias = 'a'): string
    {
        self::assertAtletasAlias($tableAlias);

        return 'SUM(CASE WHEN IFNULL(' . $tableAlias . '.afiliacion, 0) = 1 THEN 1 ELSE 0 END) AS afiliacion, '
            . 'SUM(CASE WHEN IFNULL(' . $tableAlias . '.anualidad, 0) = 1 THEN 1 ELSE 0 END) AS anualidad, '
            . 'SUM(CASE WHEN IFNULL(' . $tableAlias . '.carnet, 0) = 1 THEN 1 ELSE 0 END) AS carnet, '
            . 'SUM(CASE WHEN IFNULL(' . $tableAlias . '.traspaso, 0) = 1 THEN 1 ELSE 0 END) AS traspaso, '
            . 'SUM(CASE WHEN IFNULL(' . $tableAlias . '.inscripcion, 0) = 1 THEN 1 ELSE 0 END) AS inscripcion';
    }

    /**
     * SELECT de métricas para estadísticas por torneo (`torneo_id`), agrupadas por asociación.
     * Misma lógica de banderas que {@see sqlSumCasesIndicadoresAtletas}, excepto:
     * inscripción y anualidad usan el mismo conteo (inscripcion=1) para que coincidan:
     * en el primer torneo del año quien está inscrito debe pagar anualidad.
     *
     * @return string Expresiones después de asociacion_id / nombre (incluye filas_origen y métricas)
     */
    public static function sqlSelectMetricasTorneoPorAsociacion(string $tableAlias = 'a'): string
    {
        self::assertAtletasAlias($tableAlias);
        $f = $tableAlias;
        $insc = 'SUM(CASE WHEN IFNULL(' . $f . '.inscripcion, 0) = 1 THEN 1 ELSE 0 END)';

        return 'COUNT(*) AS filas_origen, '
            . $insc . ' AS total_inscritos, '
            . 'SUM(CASE WHEN IFNULL(' . $f . '.afiliacion, 0) = 1 THEN 1 ELSE 0 END) AS total_afiliados, '
            . $insc . ' AS total_anualidad, '
            . 'SUM(CASE WHEN IFNULL(' . $f . '.carnet, 0) = 1 THEN 1 ELSE 0 END) AS total_carnets, '
            . 'SUM(CASE WHEN IFNULL(' . $f . '.traspaso, 0) = 1 THEN 1 ELSE 0 END) AS total_traspasos';
    }

    /**
     * Métricas por torneo desde `inscripcion_torneo` (volcado auxiliar). La deuda y los paneles principales usan
     * {@see sqlSelectMetricasTorneoPorAsociacion} sobre `atletas`.
     *
     * @param string $tableAlias Alias validado (p. ej. it)
     */
    public static function sqlSelectMetricasTorneoPorInscripcionTorneo(string $tableAlias = 'it'): string
    {
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $tableAlias)) {
            throw new InvalidArgumentException('Alias de tabla no válido.');
        }
        $f = $tableAlias;
        $insc = 'SUM(CASE WHEN COALESCE(' . $f . '.inscripcion, 0) IN (1, 2) THEN 1 ELSE 0 END)';

        return 'COUNT(*) AS filas_origen, '
            . $insc . ' AS total_inscritos, '
            . 'SUM(CASE WHEN COALESCE(' . $f . '.afiliacion, 0) = 1 THEN 1 ELSE 0 END) AS total_afiliados, '
            . $insc . ' AS total_anualidad, '
            . 'SUM(CASE WHEN COALESCE(' . $f . '.carnet, 0) = 1 THEN 1 ELSE 0 END) AS total_carnets, '
            . 'SUM(CASE WHEN COALESCE(' . $f . '.traspaso, 0) = 1 THEN 1 ELSE 0 END) AS total_traspasos';
    }

    private static function assertAtletasAlias(string $alias): void
    {
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $alias)) {
            throw new InvalidArgumentException('Alias de tabla no válido.');
        }
    }

    /**
     * Cuantificación global sobre `atletas` en el alcance de sesión: total de filas y conteos por indicador.
     * Cada columna cuenta por separado filas con ese marcador en 1 (sin cruzar condiciones entre columnas).
     *
     * @return array{
     *   total_atletas:int,
     *   afiliacion:int,
     *   anualidad:int,
     *   carnet:int,
     *   traspaso:int,
     *   inscripcion:int
     * }
     */
    public static function aggregateIndicadoresAtletasTotales(?PDO $pdo = null, int $filtroAsociacionFvd = 0): array
    {
        $pdo = $pdo ?? fvd_db();
        $legacy = dirname(__DIR__, 2) . '/fvdmasteradmin/services/QueryHelper.php';
        if (!\class_exists('QueryHelper', false)) {
            require_once $legacy;
        }

        $params = [];
        $filtroAsocSql = '';
        if ($filtroAsociacionFvd > 0) {
            $params[':_fvd_rep_asoc_tot'] = $filtroAsociacionFvd;
            $filtroAsocSql = ' AND a.asociacion = :_fvd_rep_asoc_tot ';
        }
        $sums = self::sqlSumCasesIndicadoresAtletas('a');
        $base = 'SELECT COUNT(*) AS total_atletas, ' . $sums . '
            FROM atletas a
            WHERE 1=1' . $filtroAsocSql;

        $countSql = 'SELECT COUNT(*) FROM atletas a WHERE 1=1' . $filtroAsocSql;
        $dataSql = $base;
        \QueryHelper::applyAsociacionScope($countSql, $dataSql, 'a.asociacion', $params);

        $stmt = $pdo->prepare($dataSql);
        self::bindNamed($stmt, $params);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return [
                'total_atletas' => 0,
                'afiliacion'    => 0,
                'anualidad'     => 0,
                'carnet'        => 0,
                'traspaso'      => 0,
                'inscripcion'   => 0,
            ];
        }

        return [
            'total_atletas' => (int) ($row['total_atletas'] ?? 0),
            'afiliacion'    => (int) ($row['afiliacion'] ?? 0),
            'anualidad'     => (int) ($row['anualidad'] ?? 0),
            'carnet'        => (int) ($row['carnet'] ?? 0),
            'traspaso'      => (int) ($row['traspaso'] ?? 0),
            'inscripcion'   => (int) ($row['inscripcion'] ?? 0),
        ];
    }

    /**
     * Misma lógica que {@see self::aggregateIndicadoresAtletasTotales}, agrupada por asociación.
     * Por cada asociación N, cada columna equivale a contar filas con
     * `WHERE atletas.asociacion = N AND campo = 1` (el alcance de sesión se añade en WHERE).
     *
     * @return list<array<string, mixed>>
     */
    public static function aggregateIndicadoresAtletasPorAsociacion(?PDO $pdo = null, int $filtroAsociacionFvd = 0): array
    {
        $pdo = $pdo ?? fvd_db();
        $legacy = dirname(__DIR__, 2) . '/fvdmasteradmin/services/QueryHelper.php';
        if (!\class_exists('QueryHelper', false)) {
            require_once $legacy;
        }

        $params = [];
        $filtroAsocSql = '';
        if ($filtroAsociacionFvd > 0) {
            $params[':_fvd_rep_asoc_pa'] = $filtroAsociacionFvd;
            $filtroAsocSql = ' AND a.asociacion = :_fvd_rep_asoc_pa ';
        }
        // Una fila por asociacion_id: GROUP BY solo a.asociacion (evita partir el mismo id por s.nombre NULL/distinto).
        // Cada métrica = COUNT equivalente a: SELECT COUNT(*) FROM atletas WHERE asociacion = N AND campo = 1
        $sums = self::sqlSumCasesIndicadoresAtletas('a');
        $dataSql = 'SELECT a.asociacion AS asociacion_id,
            MAX(COALESCE(s.nombre, \'\')) AS asociacion_nombre,
            COUNT(*) AS total_atletas,
            ' . $sums . '
            FROM atletas a
            LEFT JOIN asociaciones s ON s.id = a.asociacion
            WHERE 1=1' . $filtroAsocSql . '
            GROUP BY a.asociacion
            ORDER BY asociacion_nombre ASC';

        $countSql = 'SELECT COUNT(*) FROM atletas a WHERE 1=1' . $filtroAsocSql;
        \QueryHelper::applyAsociacionScope($countSql, $dataSql, 'a.asociacion', $params);

        $stmt = $pdo->prepare($dataSql);
        self::bindNamed($stmt, $params);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return is_array($rows) ? $rows : [];
    }

    /**
     * @param array<string, mixed> $params
     */
    private static function bindNamed(PDOStatement $stmt, array $params): void
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
            $stmt->bindValue($key, $v, $type);
        }
    }

    private static function identifier(string $name): string
    {
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $name)) {
            throw new InvalidArgumentException('Nombre de tabla no válido.');
        }

        return '`' . str_replace('`', '``', $name) . '`';
    }

    private static function bareColumn(string $name): string
    {
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $name)) {
            throw new InvalidArgumentException('Nombre de columna no válido.');
        }

        return '`' . str_replace('`', '``', $name) . '`';
    }
}
