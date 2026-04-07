<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/FvdModuleController.php';

class CostosController extends FvdModuleController
{
    public const TABLE = 'costos';

    /** @var list<string> */
    public const ALLOW_PERSIST = ['fecha', 'afiliacion', 'anualidad', 'carnets', 'traspasos', 'inscripciones'];

    public function paginateList(int $page, int $perPage): array
    {
        $params = [];
        $countSql = 'SELECT COUNT(*) FROM costos WHERE 1=1';
        $dataSql = 'SELECT * FROM costos WHERE 1=1 ORDER BY fecha DESC';

        return self::paginate($this->pdo, $countSql, $dataSql, $params, $page, $perPage);
    }

    public function find(?int $id): ?array
    {
        if ($id === null) {
            return null;
        }
        $st = $this->pdo->prepare('SELECT * FROM costos WHERE id = :id LIMIT 1');
        $st->execute([':id' => $id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function save(?int $id, array $post): void
    {
        if (AuthService::role() !== AuthService::ROLE_FVD_ADMIN) {
            throw new RuntimeException('Solo el administrador FVD puede modificar tarifas.');
        }
        $data = [];
        foreach (self::ALLOW_PERSIST as $col) {
            $v = $post[$col] ?? null;
            if ($col === 'fecha') {
                $data[$col] = $v === '' ? null : (string) $v;
            } else {
                $data[$col] = $v === '' || $v === null ? 0 : (float) $v;
            }
        }

        if ($id === null) {
            $this->requireFvdAdminToCreate();
            self::insert($this->pdo, self::TABLE, $data, self::ALLOW_PERSIST);
        } else {
            self::update($this->pdo, self::TABLE, $data, self::ALLOW_PERSIST, 'id = :wid', [':wid' => $id]);
        }
    }

    public function delete(int $id): void
    {
        if (AuthService::role() !== AuthService::ROLE_FVD_ADMIN) {
            http_response_code(403);
            exit;
        }
        $st = $this->pdo->prepare('DELETE FROM costos WHERE id = :id');
        $st->execute([':id' => $id]);
    }
}
