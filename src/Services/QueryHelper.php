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
     * Si $tabla es "atletas", listado admin (JOIN asociaciones, LIKE cédula/nombre, alcance regional).
     * En ese caso $filtros puede incluir __cedula y __nombre (búsqueda).
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
            $fichaFiltro = isset($filtros['__ficha_filtro']) ? trim((string) $filtros['__ficha_filtro']) : '';
            if (!in_array($fichaFiltro, ['sin_carnet', 'carnet_solicitado', 'carnet_emitido', 'ficha_vencida', ''], true)) {
                $fichaFiltro = '';
            }
            if ($fichaFiltro === 'carnet_emitido') {
                $fichaFiltro = 'carnet_solicitado';
            }
            $revisionDelegado = false;
            if (isset($filtros['__revision_delegado'])) {
                $rv = $filtros['__revision_delegado'];
                $revisionDelegado = $rv === '1' || $rv === 1 || $rv === true;
            }

            return self::selectPaginadoAtletasAdmin($cedula, $nombre, $fichaFiltro, $revisionDelegado, $pagina, $limite, $pdo);
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
     * Listado de atletas del panel (misma semántica que el legado en FvdAdminService).
     *
     * @return array{registros: list<array<string, mixed>>, total: int, paginas: int}
     */
    private static function atletasFichaCondicionSql(string $fichaFiltro): string
    {
        if ($fichaFiltro === 'sin_carnet') {
            return ' AND COALESCE(a.carnet, 0) = 0 ';
        }
        if ($fichaFiltro === 'carnet_solicitado') {
            return ' AND COALESCE(a.carnet, 0) = 1 ';
        }
        if ($fichaFiltro === 'ficha_vencida') {
            return " AND (
            a.fechact IS NULL OR TRIM(COALESCE(a.fechact, '')) = '' OR a.fechact = '0000-00-00' OR a.fechact = '0000-00-00 00:00:00'
            OR DATE(a.fechact) < DATE_SUB(CURDATE(), INTERVAL 365 DAY)
        ) ";
        }

        return '';
    }

    private static function atletasRevisionDelegadoSql(bool $solo): string
    {
        if (!$solo) {
            return '';
        }

        return ' AND COALESCE(a.alta_desde_delegado, 0) = 1 AND COALESCE(a.estatus, 0) = 0 ';
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
        string $fichaFiltro,
        bool $revisionDelegado,
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
        $search .= self::atletasFichaCondicionSql($fichaFiltro);
        $search .= self::atletasRevisionDelegadoSql($revisionDelegado);

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
     * @return list<array<string, mixed>>
     */
    public static function selectAtletasAdminAll(
        string $cedula,
        string $nombre,
        ?PDO $pdo = null,
        string $fichaFiltro = '',
        bool $revisionDelegado = false
    ): array {
        $pdo = $pdo ?? fvd_db();
        $legacy = dirname(__DIR__, 2) . '/fvdmasteradmin/services/QueryHelper.php';
        if (!\class_exists('QueryHelper', false)) {
            require_once $legacy;
        }

        $fichaFiltro = trim($fichaFiltro);
        if (!in_array($fichaFiltro, ['sin_carnet', 'carnet_solicitado', 'carnet_emitido', 'ficha_vencida', ''], true)) {
            $fichaFiltro = '';
        }
        if ($fichaFiltro === 'carnet_emitido') {
            $fichaFiltro = 'carnet_solicitado';
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
        $search .= self::atletasFichaCondicionSql($fichaFiltro);
        $search .= self::atletasRevisionDelegadoSql($revisionDelegado);

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
