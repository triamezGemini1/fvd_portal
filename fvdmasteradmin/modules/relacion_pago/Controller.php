<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/FvdModuleController.php';

class RelacionPagoController extends FvdModuleController
{
    public const TABLE = 'relacion_pagos';

    private const SCOPE_COL = 'r.asociacion_id';

    /** @var list<string> */
    public const ALLOW_PERSIST = [
        'torneo_id', 'asociacion_id', 'secuencia', 'fecha', 'tasa_cambio', 'tipo_pago', 'moneda',
        'monto_total', 'monto_dolares', 'referencia', 'banco', 'observaciones',
    ];

    public function paginateList(int $page, int $perPage): array
    {
        $params = [];
        $countSql = 'SELECT COUNT(*) FROM relacion_pagos r WHERE 1=1';
        $dataSql = 'SELECT r.*, a.nombre AS asoc_nombre, t.nombre AS torneo_nombre
            FROM relacion_pagos r
            LEFT JOIN asociaciones a ON r.asociacion_id = a.id
            LEFT JOIN torneosact t ON r.torneo_id = t.torneo
            WHERE 1=1
            ORDER BY r.fecha DESC';

        return self::paginateWithAsociacionScope($this->pdo, $countSql, $dataSql, $params, $page, $perPage, self::SCOPE_COL);
    }

    public function find(?int $id): ?array
    {
        if ($id === null) {
            return null;
        }
        $params = [':id' => $id];
        $scope = self::asociacionScopeSql(self::SCOPE_COL, $params);
        $sql = 'SELECT r.* FROM relacion_pagos r WHERE r.id = :id ' . $scope;
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function listAsociacionesForSelect(): array
    {
        if (AuthService::role() === AuthService::ROLE_FVD_ADMIN) {
            $st = $this->pdo->query('SELECT id, nombre FROM asociaciones ORDER BY nombre');

            return $st ? $st->fetchAll(PDO::FETCH_ASSOC) : [];
        }
        $mine = AuthService::idAsociacion();
        if ($mine === null) {
            return [];
        }
        $st = $this->pdo->prepare('SELECT id, nombre FROM asociaciones WHERE id = :id');
        $st->execute([':id' => $mine]);

        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listTorneosForSelect(): array
    {
        $params = [];
        $sql = 'SELECT t.torneo, t.nombre FROM torneosact t WHERE 1=1';
        $scope = self::asociacionScopeSql('t.organizacion_id', $params);
        $sql .= $scope . ' ORDER BY t.fechator DESC LIMIT 200';
        $st = $this->pdo->prepare($sql);
        $st->execute($params);

        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function save(?int $id, array $post): void
    {
        $data = [];
        foreach (self::ALLOW_PERSIST as $col) {
            $v = $post[$col] ?? null;
            if ($col === 'torneo_id') {
                $data[$col] = ($v === '' || $v === null || (int) $v === 0) ? null : (int) $v;
            } elseif ($col === 'asociacion_id') {
                $data[$col] = $v === '' || $v === null ? null : (int) $v;
            } elseif ($col === 'secuencia') {
                $data[$col] = $v === '' || $v === null ? 1 : (int) $v;
            } elseif (in_array($col, ['tasa_cambio', 'monto_total', 'monto_dolares'], true)) {
                $data[$col] = $v === '' || $v === null ? null : (float) $v;
            } else {
                $data[$col] = $v === '' ? null : (string) $v;
            }
        }

        if (AuthService::role() !== AuthService::ROLE_FVD_ADMIN) {
            $mine = AuthService::idAsociacion();
            if ($mine === null) {
                throw new RuntimeException('Sin asociación.');
            }
            $data['asociacion_id'] = $mine;
        }

        if ($id === null) {
            self::insert($this->pdo, self::TABLE, $data, self::ALLOW_PERSIST);
        } else {
            $prev = $this->find($id);
            if ($prev === null) {
                throw new InvalidArgumentException('Pago no encontrado.');
            }
            $this->enforceAsociacionId(isset($prev['asociacion_id']) ? (int) $prev['asociacion_id'] : null);
            self::update($this->pdo, self::TABLE, $data, self::ALLOW_PERSIST, 'id = :wid', [':wid' => $id]);
        }
    }

    public function delete(int $id): void
    {
        $prev = $this->find($id);
        if ($prev === null) {
            return;
        }
        $this->enforceAsociacionId(isset($prev['asociacion_id']) ? (int) $prev['asociacion_id'] : null);
        $params = [':id' => $id];
        $scope = self::asociacionScopeSql(self::SCOPE_COL, $params);
        $sql = 'DELETE FROM relacion_pagos r WHERE r.id = :id ' . $scope;
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
    }
}
