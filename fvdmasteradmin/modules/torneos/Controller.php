<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/FvdModuleController.php';

class TorneosController extends FvdModuleController
{
    public const TABLE = 'torneosact';

    private const SCOPE_COL = 't.organizacion_id';

    /** @var list<string> */
    public const ALLOW_PERSIST = [
        'organizacion_id', 'clavetor', 'nombre', 'lugar', 'fechator', 'tipo', 'clase', 'tiempo',
        'puntos', 'rondas', 'estatus', 'costotor', 'ranking', 'pareclub', 'invitacion', 'afiche',
    ];

    public function paginateList(int $page, int $perPage, string $q): array
    {
        $params = [];
        $search = '';
        if ($q !== '') {
            $params[':fq'] = '%' . $q . '%';
            $search = ' AND (t.nombre LIKE :fq OR t.lugar LIKE :fq) ';
        }
        $countSql = 'SELECT COUNT(*) FROM torneosact t WHERE 1=1' . $search;
        $dataSql = 'SELECT t.torneo, t.nombre, t.lugar, t.fechator, t.estatus, t.organizacion_id, o.nombre AS org_nombre
            FROM torneosact t
            LEFT JOIN asociaciones o ON t.organizacion_id = o.id
            WHERE 1=1' . $search . ' ORDER BY t.fechator DESC';

        return self::paginateWithAsociacionScope($this->pdo, $countSql, $dataSql, $params, $page, $perPage, self::SCOPE_COL);
    }

    public function find(?int $torneo): ?array
    {
        if ($torneo === null) {
            return null;
        }
        $params = [':t' => $torneo];
        $scope = self::asociacionScopeSql(self::SCOPE_COL, $params);
        $sql = 'SELECT t.* FROM torneosact t WHERE t.torneo = :t ' . $scope;
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function listAsociacionesForSelect(): array
    {
        if (AuthService::role() === AuthService::ROLE_FVD_ADMIN) {
            $st = $this->pdo->query('SELECT id, nombre FROM asociaciones ORDER BY nombre ASC');

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

    public function generarClaveTorneo(string $fechaTorneo): string
    {
        $anio = date('Y', strtotime($fechaTorneo));
        $st = $this->pdo->prepare('SELECT COUNT(*) FROM torneosact WHERE YEAR(fechator) = :anio');
        $st->execute([':anio' => $anio]);
        $n = (int) $st->fetchColumn();

        return $anio . '-' . str_pad((string) ($n + 1), 3, '0', STR_PAD_LEFT);
    }

    public function save(?int $torneoId, array $post, array $files): void
    {
        $data = [];
        foreach (self::ALLOW_PERSIST as $col) {
            if (in_array($col, ['invitacion', 'afiche', 'clavetor'], true)) {
                continue;
            }
            if (!array_key_exists($col, $post)) {
                $data[$col] = null;
                continue;
            }
            $v = $post[$col];
            if (in_array($col, ['organizacion_id', 'tipo', 'clase', 'tiempo', 'puntos', 'rondas', 'estatus', 'ranking', 'pareclub'], true)) {
                $data[$col] = $v === '' || $v === null ? null : (int) $v;
            } elseif ($col === 'costotor') {
                $data[$col] = $v === '' ? null : (float) $v;
            } else {
                $data[$col] = $v === '' ? null : (string) $v;
            }
        }

        if (AuthService::role() !== AuthService::ROLE_FVD_ADMIN) {
            $mine = AuthService::idAsociacion();
            if ($mine === null) {
                throw new RuntimeException('Sin asociación asignada.');
            }
            $data['organizacion_id'] = $mine;
        }

        $uploadDir = $this->projectRoot() . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR;
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
            throw new RuntimeException('No se pudo crear uploads.');
        }

        if ($torneoId === null) {
            $fechator = (string) ($data['fechator'] ?? '');
            if ($fechator === '') {
                throw new InvalidArgumentException('Fecha del torneo requerida.');
            }
            $data['clavetor'] = $this->generarClaveTorneo($fechator);
            $data['invitacion'] = null;
            $data['afiche'] = null;
        } else {
            $prev = $this->find($torneoId);
            if ($prev === null) {
                throw new InvalidArgumentException('Torneo no encontrado.');
            }
            $this->enforceAsociacionId(isset($prev['organizacion_id']) ? (int) $prev['organizacion_id'] : null);
            $data['clavetor'] = $prev['clavetor'];
            $data['invitacion'] = $prev['invitacion'];
            $data['afiche'] = $prev['afiche'];
        }

        foreach (['invitacion' => 'invitacion', 'afiche' => 'afiche'] as $fileKey => $col) {
            if (!empty($files[$fileKey]['name'])) {
                $prefix = $fileKey === 'invitacion' ? '_inv_' : '_af_';
                $filename = time() . $prefix . basename((string) $files[$fileKey]['name']);
                if (!move_uploaded_file((string) $files[$fileKey]['tmp_name'], $uploadDir . $filename)) {
                    throw new RuntimeException('Error al subir ' . $col . '.');
                }
                $data[$col] = $filename;
            }
        }

        if ($torneoId === null) {
            self::insert($this->pdo, self::TABLE, $data, self::ALLOW_PERSIST);
        } else {
            self::update($this->pdo, self::TABLE, $data, self::ALLOW_PERSIST, 'torneo = :wid', [':wid' => $torneoId]);
        }
    }

    public function delete(int $torneo): void
    {
        $prev = $this->find($torneo);
        if ($prev === null) {
            return;
        }
        $this->enforceAsociacionId(isset($prev['organizacion_id']) ? (int) $prev['organizacion_id'] : null);
        $params = [':t' => $torneo];
        $scope = self::asociacionScopeSql(self::SCOPE_COL, $params);
        $sql = 'DELETE FROM torneosact t WHERE t.torneo = :t ' . $scope;
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
    }
}
