<?php
/**
 * Utilidades PDO: búsqueda, inserción/actualización y paginación.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/db.php';
require_once __DIR__ . '/AuthService.php';

class QueryHelper
{
    /**
     * Añade condición (col1 LIKE :sq0 OR col2 LIKE :sq1 ...) para un término de búsqueda.
     *
     * @param array<string, mixed> $params Referencia; se añaden :sq0, :sq1, ...
     * @param list<string> $columns Nombres de columna SQL ya validados por el llamador
     * @return string Fragmento SQL (vacío si $term está vacío)
     */
    public static function searchWhere(string $term, array $columns, array &$params): string
    {
        $term = trim($term);
        if ($term === '' || $columns === []) {
            return '';
        }

        $parts = [];
        $i = 0;
        foreach ($columns as $col) {
            $key = ':sq' . $i;
            $params[$key] = '%' . $term . '%';
            $parts[] = $col . ' LIKE ' . $key;
            ++$i;
        }

        return ' AND (' . implode(' OR ', $parts) . ')';
    }

    /**
     * INSERT; devuelve el último id autoincremental si aplica.
     *
     * @param array<string, mixed> $data column => value (solo columnas permitidas)
     * @param list<string> $allowedColumns whitelist
     */
    public static function insert(PDO $pdo, string $table, array $data, array $allowedColumns): string
    {
        $table = self::identifier($table);
        $filtered = self::filterColumns($data, $allowedColumns);
        if ($filtered === []) {
            throw new InvalidArgumentException('No hay columnas válidas para insertar.');
        }

        $cols = array_keys($filtered);
        $placeholders = array_map(static function (string $c): string {
            return ':' . $c;
        }, $cols);

        $sql = 'INSERT INTO ' . $table . ' (' . implode(', ', $cols) . ') VALUES (' . implode(', ', $placeholders) . ')';
        $stmt = $pdo->prepare($sql);
        foreach ($filtered as $c => $v) {
            $stmt->bindValue(':' . $c, $v);
        }
        $stmt->execute();

        return (string) $pdo->lastInsertId();
    }

    /**
     * UPDATE por una condición parametrizada.
     *
     * @param array<string, mixed> $data
     * @param list<string> $allowedColumns
     * @param array<string, mixed> $whereParams debe incluir todas las claves usadas en $whereSql
     */
    public static function update(
        PDO $pdo,
        string $table,
        array $data,
        array $allowedColumns,
        string $whereSql,
        array $whereParams
    ): int {
        $table = self::identifier($table);
        $filtered = self::filterColumns($data, $allowedColumns);
        if ($filtered === []) {
            throw new InvalidArgumentException('No hay columnas válidas para actualizar.');
        }

        $sets = [];
        foreach (array_keys($filtered) as $c) {
            $sets[] = $c . ' = :u_' . $c;
        }

        $sql = 'UPDATE ' . $table . ' SET ' . implode(', ', $sets) . ' WHERE ' . $whereSql;
        $stmt = $pdo->prepare($sql);
        foreach ($filtered as $c => $v) {
            $stmt->bindValue(':u_' . $c, $v);
        }
        foreach ($whereParams as $k => $v) {
            $key = (string) $k;
            if ($key !== '' && $key[0] !== ':') {
                $key = ':' . ltrim($key, ':');
            }
            $stmt->bindValue($key, $v);
        }
        $stmt->execute();

        return $stmt->rowCount();
    }

    /**
     * Paginación: total de filas + rebanada de datos.
     *
     * @param array<string, mixed> $params Parámetros nombrados para ambas consultas
     * @return array{total:int, page:int, per_page:int, pages:int, rows:list<array<string,mixed>>}
     */
    /**
     * Restringe por id_asociacion según sesión: no aplica a fvd_admin.
     * Sin sesión válida o sin id_asociacion para roles regionales → ninguna fila (AND 1=0).
     *
     * @param array<string, mixed> $params
     */
    public static function asociacionScopeSql(string $qualifiedColumn, array &$params): string
    {
        AuthService::ensureSession();

        if (!AuthService::isAuthenticated()) {
            return ' AND 1=0 ';
        }

        if (AuthService::isSuperAdmin()) {
            return '';
        }

        $id = AuthService::idAsociacion();
        if ($id === null) {
            return ' AND 1=0 ';
        }

        $params[':fvd_asoc_scope'] = $id;

        return ' AND (' . $qualifiedColumn . ' = :fvd_asoc_scope) ';
    }

    /**
     * Aplica el alcance regional a consultas de listado (COUNT y SELECT).
     * En el SELECT inserta el fragmento antes de ORDER BY si existe.
     *
     * @param array<string, mixed> $params
     */
    public static function applyAsociacionScope(
        string &$countSql,
        string &$dataSql,
        string $qualifiedColumn,
        array &$params
    ): void {
        $scope = self::asociacionScopeSql($qualifiedColumn, $params);
        $countSql .= $scope;

        if (preg_match('/\s+ORDER\s+BY\s+/i', $dataSql, $m, PREG_OFFSET_CAPTURE)) {
            $pos = $m[0][1];
            $dataSql = substr($dataSql, 0, $pos) . $scope . ' ' . substr($dataSql, $pos);
        } else {
            $dataSql .= $scope;
        }
    }

    /**
     * Paginación con filtro regional automático (mismo criterio que applyAsociacionScope).
     *
     * @param array<string, mixed> $params
     * @return array{total:int, page:int, per_page:int, pages:int, rows:list<array<string,mixed>>}
     */
    public static function paginateWithAsociacionScope(
        PDO $pdo,
        string $countSql,
        string $dataSql,
        array $params,
        int $page,
        int $perPage,
        string $qualifiedAsociacionColumn
    ): array {
        self::applyAsociacionScope($countSql, $dataSql, $qualifiedAsociacionColumn, $params);

        return self::paginate($pdo, $countSql, $dataSql, $params, $page, $perPage);
    }

    public static function paginate(
        PDO $pdo,
        string $countSql,
        string $dataSql,
        array $params,
        int $page,
        int $perPage
    ): array {
        $perPage = max(1, min(100, $perPage));
        $page = max(1, $page);
        $offset = ($page - 1) * $perPage;

        $stmtCount = $pdo->prepare($countSql);
        self::bindNamed($stmtCount, $params);
        $stmtCount->execute();
        $total = (int) $stmtCount->fetchColumn();

        $sql = $dataSql . ' LIMIT ' . (int) $perPage . ' OFFSET ' . (int) $offset;
        $stmt = $pdo->prepare($sql);
        self::bindNamed($stmt, $params);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $pages = $total > 0 ? (int) ceil($total / $perPage) : 1;

        return [
            'total'    => $total,
            'page'     => $page,
            'per_page' => $perPage,
            'pages'    => $pages,
            'rows'     => $rows,
        ];
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

    /**
     * @param array<string, mixed> $data
     * @param list<string> $allowed
     * @return array<string, mixed>
     */
    private static function filterColumns(array $data, array $allowed): array
    {
        $allowedSet = array_flip($allowed);
        $out = [];
        foreach ($data as $k => $v) {
            if (isset($allowedSet[$k])) {
                $out[$k] = $v;
            }
        }

        return $out;
    }

    private static function identifier(string $name): string
    {
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $name)) {
            throw new InvalidArgumentException('Nombre de tabla no válido.');
        }

        return '`' . str_replace('`', '``', $name) . '`';
    }
}
