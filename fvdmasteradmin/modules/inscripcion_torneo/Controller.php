<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/FvdModuleController.php';

/**
 * Listado de la tabla inscripcion_torneo (volcado fvdmasteradminact).
 */
class InscripcionTorneoController extends FvdModuleController
{
    public const TABLE = 'inscripcion_torneo';

    private const SCOPE_COL = 'i.asociacion_id';

    public function tableExists(): bool
    {
        try {
            $db = $this->pdo->query('SELECT DATABASE()')->fetchColumn();
            if (!is_string($db) || $db === '') {
                return false;
            }
            $st = $this->pdo->prepare(
                'SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = :db AND TABLE_NAME = :t LIMIT 1'
            );
            $st->execute([':db' => $db, ':t' => self::TABLE]);

            return (bool) $st->fetchColumn();
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listTorneosForFilter(): array
    {
        $st = $this->pdo->query('SELECT torneo AS id, nombre FROM torneosact ORDER BY fechator DESC LIMIT 200');

        return $st ? $st->fetchAll(PDO::FETCH_ASSOC) : [];
    }

    /**
     * @return array{total:int, page:int, per_page:int, pages:int, rows:list<array<string,mixed>>}
     */
    public function paginateList(int $page, int $perPage, int $filterTorneoId): array
    {
        $params = [];
        $filterSql = '';
        if ($filterTorneoId > 0) {
            $params[':ftor'] = $filterTorneoId;
            $filterSql = ' AND i.torneo_id = :ftor ';
        }

        $countSql = 'SELECT COUNT(*) FROM inscripcion_torneo i WHERE 1=1' . $filterSql;
        $dataSql = 'SELECT i.*, t.nombre AS torneo_nombre, a.nombre AS asoc_nombre
            FROM inscripcion_torneo i
            LEFT JOIN torneosact t ON i.torneo_id = t.torneo
            LEFT JOIN asociaciones a ON i.asociacion_id = a.id
            WHERE 1=1' . $filterSql . '
            ORDER BY i.fecha_inscripcion DESC';

        return self::paginateWithAsociacionScope($this->pdo, $countSql, $dataSql, $params, $page, $perPage, self::SCOPE_COL);
    }

    /**
     * Listado delegado: atletas con inscripcion=1 y torneo_id (sin tabla inscripcion_torneo).
     *
     * @return array{total:int, page:int, per_page:int, pages:int, rows:list<array<string,mixed>>}
     */
    public function paginateListBandera(int $page, int $perPage, int $filterTorneoId): array
    {
        $params = [];
        $filterSql = '';
        if ($filterTorneoId > 0) {
            $params[':ftor'] = $filterTorneoId;
            $filterSql = ' AND a.torneo_id = :ftor ';
        }
        $countSql = 'SELECT COUNT(*) FROM atletas a WHERE COALESCE(a.inscripcion, 0) = 1' . $filterSql;
        $dataSql = 'SELECT a.id, a.cedula, a.nombre, a.numfvd, a.torneo_id, t.nombre AS torneo_nombre
            FROM atletas a
            LEFT JOIN torneosact t ON t.torneo = a.torneo_id
            WHERE COALESCE(a.inscripcion, 0) = 1' . $filterSql . '
            ORDER BY a.nombre ASC';

        return self::paginateWithAsociacionScope($this->pdo, $countSql, $dataSql, $params, $page, $perPage, 'a.asociacion');
    }
}
