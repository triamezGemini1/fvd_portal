<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/FvdModuleController.php';

/**
 * Administración de inscripciones al torneo: listado principal desde {@see paginateListBandera} (`atletas`);
 * volcado {@see TABLE} opcional para mantenimiento FVD.
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

        /* Canales activos: 1=sitio, 2=movimiento (ver InscripcionService::CANAL_*) */
        $activosSql = ' AND COALESCE(i.inscripcion,0) IN (1, 2) ';
        $fotoSub = '(SELECT at.foto FROM atletas at WHERE at.asociacion = i.asociacion_id
            AND CAST(REPLACE(REPLACE(REPLACE(REPLACE(UPPER(TRIM(COALESCE(at.cedula, \'\'))), \'V\', \'\'), \'E\', \'\'), \'J\', \'\'), \'P\', \'\') AS UNSIGNED) = i.cedula
            LIMIT 1)';

        $countSql = 'SELECT COUNT(*) FROM inscripcion_torneo i WHERE 1=1' . $filterSql . $activosSql;
        $dataSql = 'SELECT i.*, t.nombre AS torneo_nombre, a.nombre AS asoc_nombre, ' . $fotoSub . ' AS atleta_foto
            FROM inscripcion_torneo i
            LEFT JOIN torneosact t ON i.torneo_id = t.torneo
            LEFT JOIN asociaciones a ON i.asociacion_id = a.id
            WHERE 1=1' . $filterSql . $activosSql . '
            ORDER BY i.equipo ASC, i.nombre ASC, i.fecha_inscripcion DESC';

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
        $dataSql = 'SELECT a.id, a.cedula, a.nombre, a.numfvd, a.torneo_id, a.foto, a.sexo,
            COALESCE(a.afiliacion, 0) AS afiliacion, COALESCE(a.anualidad, 0) AS anualidad,
            COALESCE(a.carnet, 0) AS carnet, COALESCE(a.traspaso, 0) AS traspaso,
            COALESCE(a.inscripcion, 0) AS inscripcion,
            t.nombre AS torneo_nombre
            FROM atletas a
            LEFT JOIN torneosact t ON t.torneo = a.torneo_id
            WHERE COALESCE(a.inscripcion, 0) = 1' . $filterSql . '
            ORDER BY a.nombre ASC';

        return self::paginateWithAsociacionScope($this->pdo, $countSql, $dataSql, $params, $page, $perPage, 'a.asociacion');
    }

    /**
     * Fila de inscripcion_torneo con metadatos del torneo; respeta alcance regional.
     *
     * @return array<string, mixed>|null
     */
    public function findByIdScoped(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }
        $params = [':id' => $id];
        $sql = 'SELECT i.*, t.nombre AS torneo_nombre, COALESCE(t.clase, 1) AS torneo_clase, t.pareclub AS torneo_pareclub
            FROM inscripcion_torneo i
            LEFT JOIN torneosact t ON t.torneo = i.torneo_id
            WHERE i.id = :id';
        $scope = \QueryHelper::asociacionScopeSql(self::SCOPE_COL, $params);
        $sql .= $scope . ' LIMIT 1';
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        $r = $st->fetch(\PDO::FETCH_ASSOC);

        return $r === false ? null : $r;
    }

    public function cedulaOcupadaEnTorneo(int $torneoId, int $cedula, int $exceptInscripcionId): bool
    {
        if ($torneoId <= 0 || $cedula <= 0) {
            return false;
        }
        $sql = 'SELECT id FROM inscripcion_torneo WHERE torneo_id = :t AND cedula = :c';
        $params = [':t' => $torneoId, ':c' => $cedula];
        if ($exceptInscripcionId > 0) {
            $sql .= ' AND id <> :xid';
            $params[':xid'] = $exceptInscripcionId;
        }
        $st = $this->pdo->prepare($sql . ' LIMIT 1');
        $st->execute($params);

        return (bool) $st->fetchColumn();
    }

    /**
     * Borra una fila de inscripción al torneo respetando alcance de asociación (fvd_admin sin restricción).
     */
    public function deleteByIdScoped(int $id): int
    {
        if ($id <= 0) {
            return 0;
        }
        $params = [':wid' => $id];
        $scope = \QueryHelper::asociacionScopeSql('asociacion_id', $params);
        $st = $this->pdo->prepare('DELETE FROM ' . self::TABLE . ' WHERE id = :wid' . $scope);
        $st->execute($params);

        return $st->rowCount();
    }
}
