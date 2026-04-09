<?php

declare(strict_types=1);

namespace FvdPortal\Services;

use InvalidArgumentException;
use PDO;
use PDOStatement;

require_once dirname(__DIR__, 2) . '/fvdmasteradmin/config/db.php';

/**
 * Consultas PDO genéricas (portal). No colisiona con la clase global {@see \QueryHelper} del master admin.
 */
final class QueryHelper
{
    /**
     * Listado paginado por igualdad en columnas (AND).
     *
     * Si $tabla es "atletas", listado admin (LIKE cédula/nombre, alcance solo por asociación).
     * Claves admitidas: __cedula, __nombre.
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

            return self::selectPaginadoAtletasAdmin($cedula, $nombre, $pagina, $limite, $pdo);
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

    private static function selectPaginadoAtletasAdmin(
        string $cedula,
        string $nombre,
        int $pagina,
        int $limite,
        PDO $pdo
    ): array {
        $legacy = dirname(__DIR__, 2) . '/fvdmasteradmin/services/QueryHelper.php';
        if (!\class_exists('QueryHelper', false)) {
            require_once $legacy;
        }

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

        $countSql = 'SELECT COUNT(*) FROM atletas a WHERE 1=1' . $search;
        $dataSql = 'SELECT a.id, a.foto, a.cedula, a.nombre, a.sexo, a.numfvd, a.estatus, a.celular, a.email, a.asociacion, a.categ,
            a.carnet, a.traspaso,
            s.nombre AS asociacion_nombre
            FROM atletas a
            LEFT JOIN asociaciones s ON a.asociacion = s.id
            WHERE 1=1' . $search . ' ORDER BY a.id DESC';

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
     * Todas las filas del listado admin de atletas (mismos filtros y alcance regional), sin paginar.
     *
     * @param int|null $carnetEquals Si es 0 o 1, filtra por `atletas.carnet` (informes de carnets). Null = sin filtro extra.
     * @return list<array<string, mixed>>
     */
    public static function selectAtletasAdminAll(
        string $cedula,
        string $nombre,
        ?PDO $pdo = null,
        ?int $carnetEquals = null
    ): array {
        $pdo = $pdo ?? fvd_db();
        $legacy = dirname(__DIR__, 2) . '/fvdmasteradmin/services/QueryHelper.php';
        if (!\class_exists('QueryHelper', false)) {
            require_once $legacy;
        }

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
        if ($carnetEquals !== null) {
            $params[':carnet_eq'] = $carnetEquals === 1 ? 1 : 0;
            $search .= ' AND COALESCE(a.carnet, 0) = :carnet_eq ';
        }

        $countSql = 'SELECT COUNT(*) FROM atletas a WHERE 1=1' . $search;
        $dataSql = 'SELECT a.id, a.foto, a.cedula, a.nombre, a.sexo, a.numfvd, a.estatus, a.celular, a.email, a.asociacion, a.categ,
            a.carnet, a.traspaso,
            s.nombre AS asociacion_nombre
            FROM atletas a
            LEFT JOIN asociaciones s ON a.asociacion = s.id
            WHERE 1=1' . $search . ' ORDER BY a.id DESC';

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
     * Respeta el alcance regional ({@see \QueryHelper::applyAsociacionScope} sobre `a.asociacion`).
     *
     * @param 'cualquiera'|'todos' $modo
     * @return list<array<string, mixed>>
     */
    public static function selectAtletasPorIndicadoresServicioFull(
        string $modo,
        string $cedula = '',
        string $nombre = '',
        ?PDO $pdo = null
    ): array {
        $pdo = $pdo ?? fvd_db();
        $legacy = dirname(__DIR__, 2) . '/fvdmasteradmin/services/QueryHelper.php';
        if (!\class_exists('QueryHelper', false)) {
            require_once $legacy;
        }

        $modo = $modo === 'todos' ? 'todos' : 'cualquiera';
        if ($modo === 'todos') {
            $indSql = ' AND COALESCE(a.afiliacion, 0) = 1 AND COALESCE(a.anualidad, 0) = 1 AND COALESCE(a.carnet, 0) = 1'
                . ' AND COALESCE(a.traspaso, 0) = 1 AND COALESCE(a.inscripcion, 0) = 1 ';
        } else {
            $indSql = ' AND (COALESCE(a.afiliacion, 0) = 1 OR COALESCE(a.anualidad, 0) = 1 OR COALESCE(a.carnet, 0) = 1'
                . ' OR COALESCE(a.traspaso, 0) = 1 OR COALESCE(a.inscripcion, 0) = 1) ';
        }

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

        $dataSql = 'SELECT a.*, s.nombre AS asociacion_nombre
            FROM atletas a
            LEFT JOIN asociaciones s ON a.asociacion = s.id
            WHERE 1=1' . $indSql . $search . ' ORDER BY a.id ASC';

        $countSql = 'SELECT COUNT(*) FROM atletas a WHERE 1=1' . $indSql . $search;

        \QueryHelper::applyAsociacionScope($countSql, $dataSql, 'a.asociacion', $params);

        $stmt = $pdo->prepare($dataSql);
        self::bindNamed($stmt, $params);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return is_array($rows) ? $rows : [];
    }

    /**
     * Cuantificación global sobre `atletas` en el alcance de sesión: total de filas y conteos por indicador.
     * Anualidad: solo `anualidad = 1` y `afiliacion = 1`. Inscripción: `inscripcion = 1` y `afiliacion = 0` (sin solapar con afiliado).
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
    public static function aggregateIndicadoresAtletasTotales(?PDO $pdo = null): array
    {
        $pdo = $pdo ?? fvd_db();
        $legacy = dirname(__DIR__, 2) . '/fvdmasteradmin/services/QueryHelper.php';
        if (!\class_exists('QueryHelper', false)) {
            require_once $legacy;
        }

        $params = [];
        $base = 'SELECT COUNT(*) AS total_atletas,
            SUM(CASE WHEN COALESCE(a.afiliacion, 0) = 1 THEN 1 ELSE 0 END) AS afiliacion,
            SUM(CASE WHEN COALESCE(a.anualidad, 0) = 1 AND COALESCE(a.afiliacion, 0) = 1 THEN 1 ELSE 0 END) AS anualidad,
            SUM(CASE WHEN COALESCE(a.carnet, 0) = 1 THEN 1 ELSE 0 END) AS carnet,
            SUM(CASE WHEN COALESCE(a.traspaso, 0) = 1 THEN 1 ELSE 0 END) AS traspaso,
            SUM(CASE WHEN COALESCE(a.inscripcion, 0) = 1 AND COALESCE(a.afiliacion, 0) = 0 THEN 1 ELSE 0 END) AS inscripcion
            FROM atletas a
            WHERE 1=1';

        $countSql = 'SELECT COUNT(*) FROM atletas a WHERE 1=1';
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
     * Misma lógica que {@see self::aggregateIndicadoresAtletasTotales}, agrupada por asociación (club regional).
     *
     * @return list<array<string, mixed>>
     */
    public static function aggregateIndicadoresAtletasPorAsociacion(?PDO $pdo = null): array
    {
        $pdo = $pdo ?? fvd_db();
        $legacy = dirname(__DIR__, 2) . '/fvdmasteradmin/services/QueryHelper.php';
        if (!\class_exists('QueryHelper', false)) {
            require_once $legacy;
        }

        $params = [];
        $dataSql = 'SELECT a.asociacion AS asociacion_id,
            COALESCE(s.nombre, \'\') AS asociacion_nombre,
            COUNT(*) AS total_atletas,
            SUM(CASE WHEN COALESCE(a.afiliacion, 0) = 1 THEN 1 ELSE 0 END) AS afiliacion,
            SUM(CASE WHEN COALESCE(a.anualidad, 0) = 1 AND COALESCE(a.afiliacion, 0) = 1 THEN 1 ELSE 0 END) AS anualidad,
            SUM(CASE WHEN COALESCE(a.carnet, 0) = 1 THEN 1 ELSE 0 END) AS carnet,
            SUM(CASE WHEN COALESCE(a.traspaso, 0) = 1 THEN 1 ELSE 0 END) AS traspaso,
            SUM(CASE WHEN COALESCE(a.inscripcion, 0) = 1 AND COALESCE(a.afiliacion, 0) = 0 THEN 1 ELSE 0 END) AS inscripcion
            FROM atletas a
            LEFT JOIN asociaciones s ON a.asociacion = s.id
            WHERE 1=1
            GROUP BY a.asociacion, s.nombre
            ORDER BY asociacion_nombre ASC';

        $countSql = 'SELECT COUNT(*) FROM atletas a WHERE 1=1';
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
