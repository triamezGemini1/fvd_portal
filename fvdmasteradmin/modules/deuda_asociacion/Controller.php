<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/FvdModuleController.php';

class DeudaAsociacionController extends FvdModuleController
{
    public const TABLE = 'deuda_asociaciones';

    private const SCOPE_COL = 'd.asociacion_id';

    /** @var list<string> */
    public const ALLOW_UPDATE = [
        'total_inscritos', 'monto_inscritos', 'total_afiliados', 'monto_afiliados',
        'total_carnets', 'monto_carnets', 'total_traspasos', 'monto_traspasos',
        'total_anualidad', 'monto_anualidad', 'monto_total',
    ];

    public function paginateList(int $page, int $perPage): array
    {
        $params = [];
        $countSql = 'SELECT COUNT(*) FROM deuda_asociaciones d WHERE 1=1';
        $dataSql = 'SELECT d.*, t.nombre AS torneo_nombre, a.nombre AS asoc_nombre
            FROM deuda_asociaciones d
            LEFT JOIN torneosact t ON d.torneo_id = t.torneo
            LEFT JOIN asociaciones a ON d.asociacion_id = a.id
            WHERE 1=1
            ORDER BY d.fecha_creacion DESC';

        return self::paginateWithAsociacionScope($this->pdo, $countSql, $dataSql, $params, $page, $perPage, self::SCOPE_COL);
    }

    public function find(int $torneoId, int $asociacionId): ?array
    {
        $params = [':tid' => $torneoId, ':aid' => $asociacionId];
        $scope = self::asociacionScopeSql(self::SCOPE_COL, $params);
        $sql = 'SELECT d.* FROM deuda_asociaciones d WHERE d.torneo_id = :tid AND d.asociacion_id = :aid ' . $scope;
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function save(int $torneoId, int $asociacionId, array $post): void
    {
        $row = $this->find($torneoId, $asociacionId);
        if ($row === null) {
            throw new InvalidArgumentException('Deuda no encontrada.');
        }
        $this->enforceAsociacionId($asociacionId);

        $data = [];
        foreach (self::ALLOW_UPDATE as $col) {
            $v = $post[$col] ?? null;
            $data[$col] = $v === '' || $v === null ? 0 : (float) $v;
        }

        $where = 'torneo_id = :t AND asociacion_id = :a';
        $wparams = [':t' => $torneoId, ':a' => $asociacionId];
        self::update($this->pdo, self::TABLE, $data, self::ALLOW_UPDATE, $where, $wparams);

        $st = $this->pdo->prepare('UPDATE deuda_asociaciones SET fecha_actualizacion = NOW() WHERE torneo_id = ? AND asociacion_id = ?');
        $st->execute([$torneoId, $asociacionId]);
    }

    public function delete(int $torneoId, int $asociacionId): void
    {
        if (AuthService::role() !== AuthService::ROLE_FVD_ADMIN) {
            http_response_code(403);
            exit;
        }
        $params = [':tid' => $torneoId, ':aid' => $asociacionId];
        $scope = self::asociacionScopeSql(self::SCOPE_COL, $params);
        $sql = 'DELETE FROM deuda_asociaciones d WHERE d.torneo_id = :tid AND d.asociacion_id = :aid ' . $scope;
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
    }
}
