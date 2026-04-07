<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/FvdModuleController.php';

class AsociacionesController extends FvdModuleController
{
    public const TABLE = 'asociaciones';

    private const SCOPE_COL = 'asociaciones.id';

    /** @var list<string> */
    public const ALLOW_PERSIST = [
        'nombre', 'direccion', 'telefono', 'email', 'numreg', 'providencia', 'delegado', 'indica', 'estatus',
        'fechreg', 'fechprovi', 'ultelECC', 'logo',
    ];

    public function paginateList(int $page, int $perPage, string $q): array
    {
        $params = [];
        $search = '';
        if ($q !== '') {
            $params[':fq'] = '%' . $q . '%';
            $search = ' AND asociaciones.nombre LIKE :fq ';
        }
        $countSql = 'SELECT COUNT(*) FROM asociaciones WHERE 1=1' . $search;
        $dataSql = 'SELECT id, nombre, delegado, telefono, email, estatus, logo FROM asociaciones WHERE 1=1'
            . $search . ' ORDER BY nombre ASC';

        return self::paginateWithAsociacionScope($this->pdo, $countSql, $dataSql, $params, $page, $perPage, self::SCOPE_COL);
    }

    public function find(?int $id): ?array
    {
        if ($id === null) {
            return null;
        }
        $params = [':id' => $id];
        $scope = self::asociacionScopeSql(self::SCOPE_COL, $params);
        $sql = 'SELECT * FROM asociaciones WHERE id = :id ' . $scope;
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function save(?int $id, array $post, array $files): void
    {
        $data = [];
        foreach (self::ALLOW_PERSIST as $col) {
            if ($col === 'logo' || $col === 'indica' || $col === 'estatus') {
                continue;
            }
            if (in_array($col, ['fechreg', 'fechprovi', 'ultelECC'], true)) {
                $v = isset($post[$col]) ? trim((string) $post[$col]) : '';
                $data[$col] = $v === '' ? null : $v;

                continue;
            }
            $data[$col] = isset($post[$col]) ? trim((string) $post[$col]) : null;
        }

        if ($id === null) {
            $data['indica'] = 0;
            $data['estatus'] = 0;
            $prev = null;
        } else {
            $prev = $this->find($id) ?? [];
            $data['indica'] = (int) ($prev['indica'] ?? 0);
            $data['estatus'] = (int) ($prev['estatus'] ?? 0);
        }

        if (!empty($files['logo']['name'])) {
            $dir = $this->projectRoot() . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR;
            if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
                throw new RuntimeException('No se pudo crear el directorio de uploads.');
            }
            $filename = time() . '_' . basename((string) $files['logo']['name']);
            $target = $dir . $filename;
            if (!move_uploaded_file((string) $files['logo']['tmp_name'], $target)) {
                throw new RuntimeException('Error al subir el logo.');
            }
            $data['logo'] = $filename;
        } elseif ($id !== null) {
            $data['logo'] = $prev['logo'] ?? null;
        } else {
            $data['logo'] = null;
        }

        if ($id === null) {
            $this->requireFvdAdminToCreate();
            self::insert($this->pdo, self::TABLE, $data, self::ALLOW_PERSIST);
        } else {
            $this->enforceAsociacionId($id);
            self::update($this->pdo, self::TABLE, $data, self::ALLOW_PERSIST, 'id = :wid', [':wid' => $id]);
        }
    }

    public function delete(int $id): void
    {
        if (AuthService::role() !== AuthService::ROLE_FVD_ADMIN) {
            http_response_code(403);
            exit('Solo el administrador FVD puede eliminar asociaciones.');
        }
        $params = [':id' => $id];
        $scope = self::asociacionScopeSql(self::SCOPE_COL, $params);
        $sql = 'DELETE FROM asociaciones WHERE id = :id ' . $scope;
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
    }
}
