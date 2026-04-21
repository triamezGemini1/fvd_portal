<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/FvdModuleController.php';
require_once dirname(__DIR__, 3) . '/src/Services/NotificacionService.php';

class AtletasController extends FvdModuleController
{
    public const TABLE = 'atletas';

    private const SCOPE_COL = 'a.asociacion';

    /** @var list<string> */
    public const ALLOW_PERSIST = [
        'cedula', 'nombre', 'sexo', 'numfvd', 'asociacion', 'torneo_id', 'estatus',
        'afiliacion', 'anualidad', 'carnet', 'traspaso', 'inscripcion', 'categ',
        'profesion', 'direccion', 'celular', 'email', 'fechnac', 'fechfvd', 'fechact',
        'foto', 'cedula_img',
    ];

    /** Igual que FvdAdminService: fuera del formulario; alta: 0 + fechfvd/fechact hoy. */
    private const FORM_OMITTED = [
        'torneo_id', 'fechfvd', 'fechact', 'afiliacion', 'anualidad', 'carnet', 'traspaso', 'inscripcion',
    ];

    public function paginateList(int $page, int $perPage, string $q): array
    {
        $params = [];
        $search = '';
        if ($q !== '') {
            $params[':fnom'] = '%' . $q . '%';
            $digits = preg_replace('/\D+/', '', $q);
            $ced = $digits !== '' ? $digits . '%' : '%' . $q . '%';
            $params[':fced'] = $ced;
            $search = ' AND (a.cedula LIKE :fced OR a.nombre LIKE :fnom) ';
        }
        $countSql = 'SELECT COUNT(*) FROM atletas a WHERE 1=1' . $search;
        $dataSql = 'SELECT a.id, a.cedula, a.nombre, a.sexo, a.numfvd, a.estatus, a.celular, a.email, a.asociacion,
            s.nombre AS asociacion_nombre
            FROM atletas a
            LEFT JOIN asociaciones s ON a.asociacion = s.id
            WHERE 1=1' . $search . ' ORDER BY a.id DESC';

        return self::paginateWithAsociacionScope($this->pdo, $countSql, $dataSql, $params, $page, $perPage, self::SCOPE_COL);
    }

    public function find(?int $id): ?array
    {
        if ($id === null) {
            return null;
        }
        $params = [':id' => $id];
        $scope = self::asociacionScopeSql(self::SCOPE_COL, $params);
        $sql = 'SELECT a.* FROM atletas a WHERE a.id = :id ' . $scope;
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
        $one = $st->fetch(PDO::FETCH_ASSOC);

        return $one ? [$one] : [];
    }

    public function save(?int $id, array $post, array $files): void
    {
        $prevRow = $id !== null ? $this->find($id) : null;

        $data = [];
        foreach (self::ALLOW_PERSIST as $col) {
            if (in_array($col, ['foto', 'cedula_img'], true)) {
                continue;
            }
            if (!array_key_exists($col, $post)) {
                if (in_array($col, self::FORM_OMITTED, true)) {
                    if ($prevRow !== null && array_key_exists($col, $prevRow)) {
                        $data[$col] = $prevRow[$col];
                    } elseif (in_array($col, ['fechfvd', 'fechact'], true)) {
                        $data[$col] = self::fechaAceptacionFvd();
                    } else {
                        $data[$col] = 0;
                    }
                } else {
                    $data[$col] = null;
                }
                continue;
            }
            $v = $post[$col];
            if (in_array($col, ['sexo', 'estatus', 'numfvd', 'torneo_id', 'afiliacion', 'anualidad', 'carnet', 'traspaso', 'inscripcion', 'categ'], true)) {
                $data[$col] = $v === '' || $v === null ? 0 : (int) $v;
            } elseif ($col === 'asociacion') {
                $data[$col] = $v === '' || $v === null ? null : (int) $v;
            } elseif (in_array($col, ['fechnac', 'fechfvd', 'fechact'], true)) {
                $s = $v === '' || $v === null ? '' : trim((string) $v);
                $data[$col] = $s === '' ? null : $s;
            } else {
                $data[$col] = $v === '' ? null : (string) $v;
            }
        }

        if (AuthService::role() !== AuthService::ROLE_FVD_ADMIN) {
            $mine = AuthService::idAsociacion();
            if ($mine === null) {
                throw new RuntimeException('Sin asociación asignada.');
            }
            $data['asociacion'] = $mine;
        }

        if ($id === null && AuthService::isDelegadoAsociacion()) {
            $data['numfvd'] = 0;
            $data['estatus'] = \FvdAdminService::ATLETA_ESTATUS_PENDIENTE_ADMIN;
        }

        $uploadDir = $this->projectRoot() . DIRECTORY_SEPARATOR . 'crud_atletas' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR;
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
            throw new RuntimeException('No se pudo crear uploads de atletas.');
        }

        foreach (['foto' => 'foto', 'cedula_img' => 'cedula_img'] as $key => $field) {
            if (!empty($files[$key]['name'])) {
                $fn = uniqid('', true) . '_' . basename((string) $files[$key]['name']);
                if (!move_uploaded_file((string) $files[$key]['tmp_name'], $uploadDir . $fn)) {
                    throw new RuntimeException('Error al subir ' . $field . '.');
                }
                $data[$field] = $fn;
            } elseif ($prevRow !== null) {
                $data[$field] = $prevRow[$field] ?? null;
            } else {
                $data[$field] = null;
            }
        }

        if ($id === null) {
            self::insert($this->pdo, self::TABLE, $data, self::ALLOW_PERSIST);
            $newAtletaId = (int) $this->pdo->lastInsertId();
            if (AuthService::isDelegadoAsociacion()) {
                $adminId = \FvdPortal\Services\NotificacionService::resolverAdminGeneralId($this->pdo);
                $asocNombre = 'Una asociación';
                try {
                    $mineAsoc = (int) (AuthService::idAsociacion() ?? 0);
                    if ($mineAsoc > 0) {
                        $stA = $this->pdo->prepare('SELECT nombre FROM asociaciones WHERE id = :id LIMIT 1');
                        $stA->execute([':id' => $mineAsoc]);
                        $nm = trim((string) ($stA->fetchColumn() ?: ''));
                        if ($nm !== '') {
                            $asocNombre = $nm;
                        }
                    }
                } catch (Throwable $e) {
                    // fallback
                }
                $nombreAtleta = trim((string) ($data['nombre'] ?? ''));
                if ($nombreAtleta === '') {
                    $nombreAtleta = 'Atleta #' . $newAtletaId;
                }
                \FvdPortal\Services\NotificacionService::crear(
                    $this->pdo,
                    $adminId,
                    'NUEVO_AFILIADO',
                    $asocNombre . ' ha ingresado un nuevo atleta: ' . $nombreAtleta
                );

                try {
                    $stCol = $this->pdo->query("SHOW COLUMNS FROM atletas LIKE 'estatus_verificacion'");
                    if ($stCol !== false && $stCol->fetchColumn() !== false && $newAtletaId > 0) {
                        $stUp = $this->pdo->prepare("UPDATE atletas SET estatus_verificacion = 'PENDIENTE' WHERE id = :id");
                        $stUp->execute([':id' => $newAtletaId]);
                    }
                } catch (Throwable $e) {
                    // compatibilidad
                }
            }
        } else {
            if ($prevRow === null) {
                throw new InvalidArgumentException('Atleta no encontrado.');
            }
            $this->enforceAsociacionId(isset($prevRow['asociacion']) ? (int) $prevRow['asociacion'] : null);
            self::update($this->pdo, self::TABLE, $data, self::ALLOW_PERSIST, 'id = :wid', [':wid' => $id]);
        }
    }

    private static function fechaAceptacionFvd(): string
    {
        $tzName = function_exists('env') ? (string) env('APP_TIMEZONE', 'America/Caracas') : 'America/Caracas';
        try {
            $tz = new \DateTimeZone($tzName);
        } catch (\Exception $e) {
            $tz = new \DateTimeZone('UTC');
        }

        return (new \DateTimeImmutable('now', $tz))->format('Y-m-d');
    }

    public function delete(int $id): void
    {
        $prev = $this->find($id);
        if ($prev === null) {
            return;
        }
        $this->enforceAsociacionId(isset($prev['asociacion']) ? (int) $prev['asociacion'] : null);
        $params = [':id' => $id];
        $scope = self::asociacionScopeSql(self::SCOPE_COL, $params);
        $sql = 'DELETE FROM atletas a WHERE a.id = :id ' . $scope;
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
    }
}
