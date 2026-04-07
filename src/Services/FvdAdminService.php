<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/fvdmasteradmin/config/db.php';
require_once dirname(__DIR__, 2) . '/fvdmasteradmin/services/AuthService.php';
require_once dirname(__DIR__, 2) . '/fvdmasteradmin/services/PublicSiteData.php';
require_once dirname(__DIR__, 2) . '/fvdmasteradmin/services/QueryHelper.php';
require_once __DIR__ . '/ImageUploadCompressor.php';
require_once __DIR__ . '/InscripcionService.php';
require_once __DIR__ . '/DelegadoTorneoNotifService.php';
require_once __DIR__ . '/TorneoDelegadoTarjetaService.php';
require_once __DIR__ . '/FvdAdminRevisionPendienteService.php';

/**
 * Operaciones de persistencia para el panel admin (/admin/modules).
 * Toda la interacción con la base de datos para asociaciones, atletas y torneos vive aquí.
 */
final class FvdAdminService
{
    /** Atleta registrado, sin Nº FVD hasta aprobación del administrador general. */
    public const ATLETA_ESTATUS_PENDIENTE = 0;

    /** Atleta con Nº FVD asignado y registro activo. */
    public const ATLETA_ESTATUS_ACTIVO = 1;

    /** Categoría por edad: LIBRE (≥18 años). */
    public const ATLETA_CATEG_LIBRE = 1;

    /** 15–17 años (menor de 18 y mayor de 14). */
    public const ATLETA_CATEG_SUB18 = 2;

    /** 12–14 años (menor de 15 y mayor de 11). */
    public const ATLETA_CATEG_SUB15 = 3;

    /** Menor de 12 años (hasta 11 inclusive). */
    public const ATLETA_CATEG_SUB12 = 4;

    private PDO $pdo;

    private string $projectRoot;

    /** @var list<string> */
    private const ASOCIACIONES_PERSIST = [
        'nombre', 'direccion', 'telefono', 'email', 'numreg', 'providencia', 'delegado', 'indica', 'estatus',
        'fechreg', 'fechprovi', 'ultelECC', 'logo',
    ];

    /** @var list<string> */
    private const ATLETAS_PERSIST = [
        'cedula', 'nombre', 'sexo', 'numfvd', 'asociacion', 'torneo_id', 'estatus',
        'afiliacion', 'anualidad', 'carnet', 'traspaso', 'inscripcion', 'categ',
        'profesion', 'direccion', 'celular', 'email', 'fechnac', 'fechfvd', 'fechact',
        'foto', 'cedula_img', 'alta_desde_delegado',
    ];

    /**
     * No están en el formulario admin. Alta: torneo_id y flags en 0; fechfvd/fechact = fecha del sistema (aceptación FVD).
     * Edición: se conservan los valores ya guardados.
     */
    private const ATLETAS_FORM_OMITTED = [
        'torneo_id', 'fechfvd', 'fechact', 'afiliacion', 'anualidad', 'carnet', 'traspaso', 'inscripcion',
    ];

    /** @var list<string> */
    private const TORNEOS_PERSIST = [
        'organizacion_id', 'clavetor', 'nombre', 'lugar', 'fechator', 'tipo', 'clase', 'tiempo',
        'puntos', 'rondas', 'estatus', 'costotor', 'ranking', 'pareclub', 'invitacion', 'afiche', 'publicar_landing',
    ];

    public function __construct(?PDO $pdo = null, ?string $projectRoot = null)
    {
        $this->pdo = $pdo ?? fvd_db();
        $this->projectRoot = $projectRoot ?? dirname(__DIR__, 2);
    }

    // ——— Asociaciones ———

    /**
     * @return array{total:int,page:int,per_page:int,pages:int,rows:list<array<string,mixed>>}
     */
    public function asociacionesPaginateList(int $page, int $perPage, string $q): array
    {
        $params = [];
        $search = '';
        if ($q !== '') {
            $params[':fq'] = '%' . $q . '%';
            $search = ' AND asociaciones.nombre LIKE :fq ';
        }
        $countSql = 'SELECT COUNT(*) FROM asociaciones WHERE 1=1' . $search;
        $dataSql = 'SELECT id, nombre, delegado, telefono, email, estatus, logo, direccion, numreg FROM asociaciones WHERE 1=1'
            . $search . ' ORDER BY nombre ASC';

        return QueryHelper::paginateWithAsociacionScope(
            $this->pdo,
            $countSql,
            $dataSql,
            $params,
            $page,
            $perPage,
            'asociaciones.id'
        );
    }

    public function asociacionesFind(?int $id): ?array
    {
        if ($id === null) {
            return null;
        }
        $params = [':id' => $id];
        $scope = QueryHelper::asociacionScopeSql('asociaciones.id', $params);
        $sql = 'SELECT * FROM asociaciones WHERE id = :id ' . $scope;
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * @param array<string, mixed> $post
     * @param array<string, mixed> $files
     */
    public function asociacionesSave(?int $id, array $post, array $files): void
    {
        $data = [];
        foreach (self::ASOCIACIONES_PERSIST as $col) {
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
            $prev = $this->asociacionesFind($id) ?? [];
            $data['indica'] = (int) ($prev['indica'] ?? 0);
            $data['estatus'] = (int) ($prev['estatus'] ?? 0);
        }

        if (!empty($files['logo']['name'])) {
            $dir = $this->projectRoot . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR;
            if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
                throw new RuntimeException('No se pudo crear el directorio de uploads.');
            }
            $filename = time() . '_' . basename((string) $files['logo']['name']);
            $target = $dir . $filename;
            if (!move_uploaded_file((string) $files['logo']['tmp_name'], $target)) {
                throw new RuntimeException('Error al subir el logo.');
            }
            ImageUploadCompressor::optimizeIfLarge($target);
            $data['logo'] = $filename;
        } elseif ($id !== null) {
            $data['logo'] = $prev['logo'] ?? null;
        } else {
            $data['logo'] = null;
        }

        if ($id === null) {
            $this->requireFvdAdminToCreate();
            QueryHelper::insert($this->pdo, 'asociaciones', $data, self::ASOCIACIONES_PERSIST);
        } else {
            $this->enforceAsociacionId($id);
            QueryHelper::update($this->pdo, 'asociaciones', $data, self::ASOCIACIONES_PERSIST, 'id = :wid', [':wid' => $id]);
        }
    }

    public function asociacionesDelete(int $id): void
    {
        if (AuthService::role() !== AuthService::ROLE_FVD_ADMIN) {
            http_response_code(403);
            exit('Solo el administrador FVD puede eliminar asociaciones.');
        }
        $params = [':id' => $id];
        $scope = QueryHelper::asociacionScopeSql('asociaciones.id', $params);
        $sql = 'DELETE FROM asociaciones WHERE id = :id ' . $scope;
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
    }

    /** Activa/desactiva asociación (estatus 0 ↔ 1). Solo administrador FVD. */
    public function asociacionesToggleEstatus(int $id): void
    {
        if (AuthService::role() !== AuthService::ROLE_FVD_ADMIN) {
            http_response_code(403);
            exit('Solo el administrador FVD puede cambiar el estatus de la asociación.');
        }
        $row = $this->asociacionesFind($id);
        if ($row === null) {
            return;
        }
        $cur = (int) ($row['estatus'] ?? 0);
        $new = $cur === 1 ? 0 : 1;
        $st = $this->pdo->prepare('UPDATE asociaciones SET estatus = :e WHERE id = :id');
        $st->execute([':e' => $new, ':id' => $id]);
    }

    // ——— Atletas ———

    /**
     * @return array{total:int,page:int,per_page:int,pages:int,rows:list<array<string,mixed>>}
     */
    public function atletasPaginateList(int $page, int $perPage, string $cedula, string $q): array
    {
        require_once __DIR__ . '/QueryHelper.php';
        $p = \FvdPortal\Services\QueryHelper::selectPaginado(
            'atletas',
            [
                '__cedula'       => $cedula,
                '__nombre'       => $q,
                '__ficha_filtro' => '',
            ],
            $page,
            $perPage,
            $this->pdo
        );

        return [
            'total'    => $p['total'],
            'page'     => $page,
            'per_page' => $perPage,
            'pages'    => $p['paginas'],
            'rows'     => $p['registros'],
        ];
    }

    public function atletasFind(?int $id): ?array
    {
        if ($id === null) {
            return null;
        }
        $params = [':id' => $id];
        $scope = QueryHelper::asociacionScopeSql('a.asociacion', $params);
        $sql = 'SELECT a.* FROM atletas a WHERE a.id = :id ' . $scope;
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * Busca por cédula (exacta o solo dígitos) respetando el alcance de asociación del usuario.
     *
     * @return array<string, mixed>|null Fila completa o null
     */
    public function atletasFindByCedula(string $cedula): ?array
    {
        $trim = trim($cedula);
        if ($trim === '') {
            return null;
        }
        $digits = preg_replace('/\D+/', '', $trim);
        $params = [':ced_trim' => $trim];
        $parts = ['TRIM(a.cedula) = :ced_trim'];
        if ($digits !== '') {
            $params[':ced_dig'] = $digits;
            $parts[] = 'REPLACE(REPLACE(REPLACE(TRIM(a.cedula), \'.\', \'\'), \'-\', \'\'), \' \', \'\') = :ced_dig';
        }
        $whereCed = '(' . implode(' OR ', $parts) . ')';
        $scope = QueryHelper::asociacionScopeSql('a.asociacion', $params);
        $sql = 'SELECT a.* FROM atletas a WHERE ' . $whereCed . $scope . ' ORDER BY a.id ASC LIMIT 1';
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function atletasListAsociacionesForSelect(): array
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

    /**
     * @param array<string, mixed> $post
     * @param array<string, mixed> $files
     */
    public function atletasSave(?int $id, array $post, array $files): void
    {
        $prevRow = $id !== null ? $this->atletasFind($id) : null;

        $data = [];
        foreach (self::ATLETAS_PERSIST as $col) {
            if (in_array($col, ['foto', 'cedula_img', 'numfvd', 'estatus', 'categ', 'alta_desde_delegado'], true)) {
                continue;
            }
            if (!array_key_exists($col, $post)) {
                if (in_array($col, self::ATLETAS_FORM_OMITTED, true)) {
                    if ($prevRow !== null && array_key_exists($col, $prevRow)) {
                        $data[$col] = $prevRow[$col];
                    } elseif (in_array($col, ['fechfvd', 'fechact'], true)) {
                        $data[$col] = self::atletasFechaAceptacionFvd();
                    } else {
                        $data[$col] = 0;
                    }
                } else {
                    $data[$col] = null;
                }
                continue;
            }
            $v = $post[$col];
            if (in_array($col, ['sexo', 'torneo_id', 'afiliacion', 'anualidad', 'carnet', 'traspaso', 'inscripcion'], true)) {
                $data[$col] = $v === '' || $v === null ? 0 : (int) $v;
            } elseif ($col === 'asociacion') {
                $data[$col] = $v === '' || $v === null ? null : (int) $v;
            } elseif (in_array($col, ['fechnac', 'fechfvd', 'fechact'], true)) {
                $s = $v === '' || $v === null ? '' : trim((string) $v);
                $data[$col] = $s === '' ? null : $s;
            } elseif ($col === 'cedula') {
                $data[$col] = $v === '' || $v === null ? null : trim((string) $v);
            } elseif ($col === 'email') {
                $em = $v === '' || $v === null ? '' : trim((string) $v);
                $data[$col] = $em === '' ? null : $em;
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

        $fechnacParaCateg = $data['fechnac'] ?? null;
        if (($fechnacParaCateg === null || $fechnacParaCateg === '') && $prevRow !== null) {
            $fechnacParaCateg = isset($prevRow['fechnac']) ? (string) $prevRow['fechnac'] : null;
        }
        $data['categ'] = self::atletasCategoriaCodigoDesdeFechanac(
            $fechnacParaCateg !== null && $fechnacParaCateg !== '' ? $fechnacParaCateg : null
        );

        $deferNumfvd = false;
        if ($id === null) {
            $data['numfvd'] = 0;
            $data['estatus'] = self::ATLETA_ESTATUS_PENDIENTE;
        } else {
            $prevNum = (int) ($prevRow['numfvd'] ?? 0);
            $prevEst = (int) ($prevRow['estatus'] ?? 0);
            $deferNumfvd = AuthService::role() === AuthService::ROLE_FVD_ADMIN
                && !empty($post['fvd_visto_bueno'])
                && !empty($post['fvd_asignar_numfvd'])
                && $prevNum === 0;
            if (!$deferNumfvd) {
                $data['numfvd'] = $prevNum;
                $data['estatus'] = $prevEst;
            }
        }

        if ($id !== null && !$deferNumfvd && AuthService::role() === AuthService::ROLE_FVD_ADMIN && array_key_exists('estatus', $post)) {
            $ev = $post['estatus'];
            $data['estatus'] = $ev === '' || $ev === null ? 0 : (int) $ev;
        }

        if ($id !== null && AuthService::role() === AuthService::ROLE_FVD_ADMIN
            && (int) ($data['estatus'] ?? 0) === self::ATLETA_ESTATUS_ACTIVO) {
            \FvdPortal\Services\FvdAdminRevisionPendienteService::ensureAltaDesdeDelegadoColumn($this->pdo);
            $data['alta_desde_delegado'] = 0;
        }

        $uploadDir = $this->projectRoot . DIRECTORY_SEPARATOR . 'crud_atletas' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR;
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
            throw new RuntimeException('No se pudo crear uploads de atletas.');
        }

        foreach (['foto' => 'foto', 'cedula_img' => 'cedula_img'] as $key => $field) {
            if (!empty($files[$key]['name'])) {
                $fn = uniqid('', true) . '_' . basename((string) $files[$key]['name']);
                $dest = $uploadDir . $fn;
                if (!move_uploaded_file((string) $files[$key]['tmp_name'], $dest)) {
                    throw new RuntimeException('Error al subir ' . $field . '.');
                }
                ImageUploadCompressor::optimizeIfLarge($dest);
                $data[$field] = $fn;
            } elseif ($prevRow !== null) {
                $data[$field] = $prevRow[$field] ?? null;
            } else {
                $data[$field] = null;
            }
        }

        try {
            if ($id === null) {
                QueryHelper::insert($this->pdo, 'atletas', $data, self::ATLETAS_PERSIST);
                if (AuthService::isDelegadoAsociacion()) {
                    \FvdPortal\Services\FvdAdminRevisionPendienteService::marcarAltaDesdeDelegado($this->pdo, (int) $this->pdo->lastInsertId());
                }

                return;
            }

            if ($prevRow === null) {
                throw new InvalidArgumentException('Atleta no encontrado.');
            }
            $this->enforceAsociacionId(isset($prevRow['asociacion']) ? (int) $prevRow['asociacion'] : null);

            if ($deferNumfvd) {
                $this->pdo->beginTransaction();
                try {
                    $st = $this->pdo->query('SELECT COALESCE(MAX(numfvd), 0) AS m FROM atletas FOR UPDATE');
                    $rowM = $st ? $st->fetch(PDO::FETCH_ASSOC) : false;
                    $next = (int) ($rowM['m'] ?? 0) + 1;
                    $data['numfvd'] = $next;
                    $data['carnet'] = 1;
                    $data['afiliacion'] = 1;
                    $data['anualidad'] = 1;
                    $data['estatus'] = self::ATLETA_ESTATUS_ACTIVO;
                    \FvdPortal\Services\FvdAdminRevisionPendienteService::ensureAltaDesdeDelegadoColumn($this->pdo);
                    $data['alta_desde_delegado'] = 0;
                    QueryHelper::update($this->pdo, 'atletas', $data, self::ATLETAS_PERSIST, 'id = :wid', [':wid' => $id]);
                    $this->pdo->commit();
                } catch (\Throwable $e) {
                    if ($this->pdo->inTransaction()) {
                        $this->pdo->rollBack();
                    }
                    throw $e;
                }

                return;
            }

            QueryHelper::update($this->pdo, 'atletas', $data, self::ATLETAS_PERSIST, 'id = :wid', [':wid' => $id]);
        } catch (\PDOException $e) {
            if (self::atletasIsDuplicateKeyError($e)) {
                throw new RuntimeException(
                    'Ya existe un atleta con esa cédula, correo electrónico o número FVD. Revise los datos o use la búsqueda por cédula para abrir la ficha existente.',
                    0,
                    $e
                );
            }
            throw $e;
        }
    }

    private static function atletasIsDuplicateKeyError(\PDOException $e): bool
    {
        if ($e->getCode() === '23000') {
            return true;
        }
        $info = $e->errorInfo;
        if (isset($info[1]) && (int) $info[1] === 1062) {
            return true;
        }

        return stripos($e->getMessage(), 'Duplicate') !== false;
    }

    /** Activo (1) ↔ pendiente/inactivo (0). Solo administrador FVD. */
    public function atletasToggleActivo(int $id): void
    {
        if (AuthService::role() !== AuthService::ROLE_FVD_ADMIN) {
            http_response_code(403);
            exit('Solo el administrador FVD puede activar o desactivar atletas.');
        }
        $prev = $this->atletasFind($id);
        if ($prev === null) {
            return;
        }
        $cur = (int) ($prev['estatus'] ?? 0);
        $new = $cur === self::ATLETA_ESTATUS_ACTIVO ? self::ATLETA_ESTATUS_PENDIENTE : self::ATLETA_ESTATUS_ACTIVO;
        $st = $this->pdo->prepare('UPDATE atletas SET estatus = :e WHERE id = :id');
        $st->execute([':e' => $new, ':id' => $id]);
    }

    public function atletasDelete(int $id): void
    {
        $prev = $this->atletasFind($id);
        if ($prev === null) {
            return;
        }
        $this->enforceAsociacionId(isset($prev['asociacion']) ? (int) $prev['asociacion'] : null);
        $params = [':id' => $id];
        $scope = QueryHelper::asociacionScopeSql('a.asociacion', $params);
        $sql = 'DELETE FROM atletas a WHERE a.id = :id ' . $scope;
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
    }

    /**
     * Actualiza solo la foto del atleta (alcance regional). Para pantalla de elaboración de carnet.
     *
     * @param array<string, mixed> $files Estructura $_FILES (clave «foto»)
     */
    public function atletasActualizarFoto(int $id, array $files): void
    {
        if ($id <= 0) {
            throw new RuntimeException('Identificador no válido.');
        }
        $prev = $this->atletasFind($id);
        if ($prev === null) {
            throw new RuntimeException('Atleta no encontrado o sin acceso.');
        }
        if (empty($files['foto']['tmp_name']) || (int) ($files['foto']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            throw new RuntimeException('Seleccione un archivo de imagen.');
        }
        if ((int) ($files['foto']['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Error al subir la imagen.');
        }
        $uploadDir = $this->projectRoot . DIRECTORY_SEPARATOR . 'crud_atletas' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR;
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
            throw new RuntimeException('No se pudo crear el directorio de fotos.');
        }
        $fn = uniqid('', true) . '_' . basename((string) $files['foto']['name']);
        $dest = $uploadDir . $fn;
        if (!move_uploaded_file((string) $files['foto']['tmp_name'], $dest)) {
            throw new RuntimeException('No se pudo guardar la imagen.');
        }
        ImageUploadCompressor::optimizeIfLarge($dest);

        $params = [':foto' => $fn, ':id' => $id];
        $scope = QueryHelper::asociacionScopeSql('a.asociacion', $params);
        $sql = 'UPDATE atletas a SET a.foto = :foto WHERE a.id = :id ' . $scope;
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        if ($st->rowCount() < 1) {
            throw new RuntimeException('No se pudo actualizar la foto.');
        }
    }

    public static function atletasCategoriaCodigoDesdeFechanac(?string $fechnac): int
    {
        if ($fechnac === null || $fechnac === '') {
            return 0;
        }
        $born = \DateTimeImmutable::createFromFormat('Y-m-d', substr($fechnac, 0, 10));
        if ($born === false) {
            return 0;
        }
        $tz = self::atletasAppTimezone();
        $born = $born->setTimezone($tz);
        $today = new \DateTimeImmutable('today', $tz);
        if ($born > $today) {
            return 0;
        }
        $age = $born->diff($today)->y;
        if ($age >= 18) {
            return self::ATLETA_CATEG_LIBRE;
        }
        if ($age >= 15) {
            return self::ATLETA_CATEG_SUB18;
        }
        if ($age >= 12) {
            return self::ATLETA_CATEG_SUB15;
        }

        return self::ATLETA_CATEG_SUB12;
    }

    public static function atletasCategoriaEtiquetaPorCodigo(int $c): string
    {
        if ($c === self::ATLETA_CATEG_LIBRE) {
            return 'LIBRE';
        }
        if ($c === self::ATLETA_CATEG_SUB18) {
            return 'SUB 18';
        }
        if ($c === self::ATLETA_CATEG_SUB15) {
            return 'SUB 15';
        }
        if ($c === self::ATLETA_CATEG_SUB12) {
            return 'SUB 12';
        }

        return $c > 0 ? ('#' . $c) : '—';
    }

    public static function atletasEstatusEtiqueta(int $e): string
    {
        if ($e === self::ATLETA_ESTATUS_PENDIENTE) {
            return 'Pendiente';
        }
        if ($e === self::ATLETA_ESTATUS_ACTIVO) {
            return 'Activo';
        }

        return (string) $e;
    }

    private static function atletasAppTimezone(): \DateTimeZone
    {
        $tzName = function_exists('env') ? (string) env('APP_TIMEZONE', 'America/Caracas') : 'America/Caracas';
        try {
            return new \DateTimeZone($tzName);
        } catch (\Exception $e) {
            return new \DateTimeZone('UTC');
        }
    }

    /** Fecha local (APP_TIMEZONE) del momento de alta aceptada por FVD para fechfvd / fechact. */
    private static function atletasFechaAceptacionFvd(): string
    {
        return (new \DateTimeImmutable('now', self::atletasAppTimezone()))->format('Y-m-d');
    }

    // ——— Torneos (tabla torneosact, PK torneo) ———

    /**
     * @return array{total:int,page:int,per_page:int,pages:int,rows:list<array<string,mixed>>}
     */
    public function torneosPaginateList(int $page, int $perPage, string $q): array
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

        return QueryHelper::paginateWithAsociacionScope(
            $this->pdo,
            $countSql,
            $dataSql,
            $params,
            $page,
            $perPage,
            't.organizacion_id'
        );
    }

    public function torneosFind(?int $torneo): ?array
    {
        if ($torneo === null) {
            return null;
        }
        $params = [':t' => $torneo];
        $scope = QueryHelper::asociacionScopeSql('t.organizacion_id', $params);
        $sql = 'SELECT t.* FROM torneosact t WHERE t.torneo = :t ' . $scope;
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function torneosListAsociacionesForSelect(): array
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

    public function torneosGenerarClaveTorneo(string $fechaTorneo): string
    {
        $anio = date('Y', strtotime($fechaTorneo));
        $st = $this->pdo->prepare('SELECT COUNT(*) FROM torneosact WHERE YEAR(fechator) = :anio');
        $st->execute([':anio' => $anio]);
        $n = (int) $st->fetchColumn();

        return $anio . '-' . str_pad((string) ($n + 1), 3, '0', STR_PAD_LEFT);
    }

    /**
     * @param array<string, mixed> $post
     * @param array<string, mixed> $files
     */
    public function torneosSave(?int $torneoId, array $post, array $files): int
    {
        $data = [];
        foreach (self::TORNEOS_PERSIST as $col) {
            if (in_array($col, ['invitacion', 'afiche', 'clavetor', 'publicar_landing'], true)) {
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

        if ($torneoId === null) {
            $data['publicar_landing'] = 1;
        } elseif (AuthService::role() === AuthService::ROLE_FVD_ADMIN) {
            $data['publicar_landing'] = !empty($post['publicar_landing']) ? 1 : 0;
        } else {
            $prevPub = $this->torneosFind($torneoId);
            $data['publicar_landing'] = (int) ($prevPub['publicar_landing'] ?? 0);
        }

        if (AuthService::role() !== AuthService::ROLE_FVD_ADMIN) {
            $mine = AuthService::idAsociacion();
            if ($mine === null) {
                throw new RuntimeException('Sin asociación asignada.');
            }
            $data['organizacion_id'] = $mine;
        }

        $uploadDir = $this->projectRoot . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR;
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
            throw new RuntimeException('No se pudo crear uploads.');
        }

        if ($torneoId === null) {
            $fechator = (string) ($data['fechator'] ?? '');
            if ($fechator === '') {
                throw new InvalidArgumentException('Fecha del torneo requerida.');
            }
            $data['clavetor'] = $this->torneosGenerarClaveTorneo($fechator);
            $data['invitacion'] = null;
            $data['afiche'] = null;
        } else {
            $prev = $this->torneosFind($torneoId);
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
                $destTor = $uploadDir . $filename;
                if (!move_uploaded_file((string) $files[$fileKey]['tmp_name'], $destTor)) {
                    throw new RuntimeException('Error al subir ' . $col . '.');
                }
                ImageUploadCompressor::optimizeIfLarge($destTor);
                $data[$col] = $filename;
            }
        }

        if (!PublicSiteData::torneosactPublicarLandingColumnPresent()) {
            unset($data['publicar_landing']);
        }

        if ($torneoId === null) {
            $newId = (int) QueryHelper::insert($this->pdo, 'torneosact', $data, self::TORNEOS_PERSIST);
            $this->torneosPostCreacionInvitacionesDelegados($newId);

            return $newId;
        }

        QueryHelper::update($this->pdo, 'torneosact', $data, self::TORNEOS_PERSIST, 'torneo = :wid', [':wid' => $torneoId]);
        $this->torneosPostSaveInvitarTodas($torneoId, $post);

        return $torneoId;
    }

    /**
     * Tras crear un torneo (solo administrador FVD): convocatoria a todas las asociaciones,
     * notificación web + token por delegado y tarjetas PDF de acceso.
     */
    private function torneosPostCreacionInvitacionesDelegados(int $newTorneoId): void
    {
        if (AuthService::role() !== AuthService::ROLE_FVD_ADMIN || $newTorneoId <= 0) {
            return;
        }
        if ($this->torneosConvocatoriaTableExists()) {
            try {
                $this->torneosConvocatoriaInvitarTodas($newTorneoId);
            } catch (Throwable $e) {
                error_log('[FvdAdminService] torneosPostCreacionInvitacionesDelegados convocatoria: ' . $e->getMessage());
            }
        }
        try {
            \FvdPortal\Services\DelegadoTorneoNotifService::crearNotificacionesParaTorneo($this->pdo, $newTorneoId);
            \FvdPortal\Services\TorneoDelegadoTarjetaService::generarTarjetasParaTorneo($this->pdo, $newTorneoId, $this->projectRoot);
        } catch (Throwable $e) {
            error_log('[FvdAdminService] torneosPostCreacionInvitacionesDelegados notif/pdf: ' . $e->getMessage());
        }
    }

    /**
     * @param array<string, mixed> $post
     */
    private function torneosPostSaveInvitarTodas(int $torneoId, array $post): void
    {
        if (AuthService::role() !== AuthService::ROLE_FVD_ADMIN || empty($post['invitar_todas_al_guardar'])) {
            return;
        }
        if (!$this->torneosConvocatoriaTableExists()) {
            return;
        }
        $this->torneosConvocatoriaInvitarTodas($torneoId);
    }

    public function torneosDelete(int $torneo): void
    {
        $prev = $this->torneosFind($torneo);
        if ($prev === null) {
            return;
        }
        $this->enforceAsociacionId(isset($prev['organizacion_id']) ? (int) $prev['organizacion_id'] : null);
        $params = [':t' => $torneo];
        $scope = QueryHelper::asociacionScopeSql('t.organizacion_id', $params);
        $sql = 'DELETE FROM torneosact t WHERE t.torneo = :t ' . $scope;
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
    }

    private function requireFvdAdminToCreate(): void
    {
        if (AuthService::role() !== AuthService::ROLE_FVD_ADMIN) {
            http_response_code(403);
            echo 'Solo el administrador FVD puede crear este registro.';
            exit;
        }
    }

    private function enforceAsociacionId(?int $asociacionId): void
    {
        if (AuthService::role() === AuthService::ROLE_FVD_ADMIN) {
            return;
        }
        $mine = AuthService::idAsociacion();
        if ($mine === null || (int) $asociacionId !== (int) $mine) {
            http_response_code(403);
            echo 'Acceso denegado.';
            exit;
        }
    }

    // ——— Torneo: convocatoria (invitaciones) e inscripción por asociación ———

    public function torneosConvocatoriaTableExists(): bool
    {
        try {
            $db = $this->pdo->query('SELECT DATABASE()')->fetchColumn();
            if (!is_string($db) || $db === '') {
                return false;
            }
            $st = $this->pdo->prepare(
                'SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = :db AND TABLE_NAME = :t LIMIT 1'
            );
            $st->execute([':db' => $db, ':t' => 'torneo_convocatoria_asoc']);

            return (bool) $st->fetchColumn();
        } catch (Throwable $e) {
            return false;
        }
    }

    public function torneosInscripcionTorneoTableExists(): bool
    {
        try {
            $db = $this->pdo->query('SELECT DATABASE()')->fetchColumn();
            if (!is_string($db) || $db === '') {
                return false;
            }
            $st = $this->pdo->prepare(
                'SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = :db AND TABLE_NAME = :t LIMIT 1'
            );
            $st->execute([':db' => $db, ':t' => 'inscripcion_torneo']);

            return (bool) $st->fetchColumn();
        } catch (Throwable $e) {
            return false;
        }
    }

    /** Columnas legado en atletas para inscripción sin tabla inscripcion_torneo (delegados). */
    public function atletasTieneColumnasInscripcionTorneo(): bool
    {
        try {
            $this->pdo->query('SELECT inscripcion, torneo_id FROM atletas LIMIT 0');

            return true;
        } catch (Throwable $e) {
            return false;
        }
    }

    public function torneosEventoRequireFvdAdmin(): void
    {
        if (AuthService::role() !== AuthService::ROLE_FVD_ADMIN) {
            http_response_code(403);
            exit('Solo el administrador general FVD puede usar el panel de evento.');
        }
    }

    /**
     * Métricas operativas para el panel de administración del torneo (ámbito según rol / QueryHelper).
     * Pendiente = campo entero en 0 en tabla atletas (convención del sistema legado).
     *
     * @return array{
     *   inscripciones_torneo: int,
     *   atletas_en_ambito: int,
     *   afiliacion_pendiente: int,
     *   anualidad_pendiente: int,
     *   carnet_pendiente: int,
     *   traspaso_pendiente: int,
     *   inscripcion_maestro_pendiente: int
     * }
     */
    public function torneosPanelEstadisticas(int $torneoId): array
    {
        $out = [
            'inscripciones_torneo' => 0,
            'atletas_en_ambito' => 0,
            'afiliacion_pendiente' => 0,
            'anualidad_pendiente' => 0,
            'carnet_pendiente' => 0,
            'traspaso_pendiente' => 0,
            'inscripcion_maestro_pendiente' => 0,
        ];
        if ($this->torneosInscripcionTorneoTableExists()) {
            try {
                $st = $this->pdo->prepare('SELECT COUNT(*) FROM inscripcion_torneo WHERE torneo_id = :t');
                $st->execute([':t' => $torneoId]);
                $out['inscripciones_torneo'] = (int) $st->fetchColumn();
            } catch (Throwable $e) {
                error_log('[torneosPanelEstadisticas inscripcion_torneo] ' . $e->getMessage());
            }
        }

        $flagMap = [
            'afiliacion' => 'afiliacion_pendiente',
            'anualidad' => 'anualidad_pendiente',
            'carnet' => 'carnet_pendiente',
            'traspaso' => 'traspaso_pendiente',
            'inscripcion' => 'inscripcion_maestro_pendiente',
        ];

        try {
            $params = [];
            $scope = QueryHelper::asociacionScopeSql('atletas.asociacion', $params);
            $st = $this->pdo->prepare('SELECT COUNT(*) FROM atletas WHERE 1=1' . $scope);
            $st->execute($params);
            $out['atletas_en_ambito'] = (int) $st->fetchColumn();
        } catch (Throwable $e) {
            error_log('[torneosPanelEstadisticas atletas total] ' . $e->getMessage());

            return $out;
        }

        foreach ($flagMap as $col => $key) {
            try {
                $p = [];
                $sc = QueryHelper::asociacionScopeSql('atletas.asociacion', $p);
                $sql = 'SELECT COUNT(*) FROM atletas WHERE COALESCE(atletas.' . $col . ', 0) = 0' . $sc;
                $st = $this->pdo->prepare($sql);
                $st->execute($p);
                $out[$key] = (int) $st->fetchColumn();
            } catch (Throwable $e) {
                error_log('[torneosPanelEstadisticas ' . $col . '] ' . $e->getMessage());
            }
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function torneosConvocatoriaFilas(int $torneoId): array
    {
        $sql = 'SELECT a.id AS asociacion_id, a.nombre AS asoc_nombre, a.email AS asoc_email, a.telefono AS asoc_tel,
            c.id AS convocatoria_id, c.invitado_en, c.estado_respuesta, c.notas,
            COALESCE(x.cnt, 0) AS num_inscritos
            FROM asociaciones a
            LEFT JOIN torneo_convocatoria_asoc c ON c.asociacion_id = a.id AND c.torneo_id = :tconv
            LEFT JOIN (
                SELECT torneo_id, asociacion_id, COUNT(*) AS cnt FROM inscripcion_torneo GROUP BY torneo_id, asociacion_id
            ) x ON x.torneo_id = :txtor AND x.asociacion_id = a.id
            ORDER BY a.nombre ASC';
        $st = $this->pdo->prepare($sql);
        $st->execute([':tconv' => $torneoId, ':txtor' => $torneoId]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Misma fila que {@see torneosConvocatoriaFilas} sin depender de la tabla inscripcion_torneo.
     *
     * @return list<array<string, mixed>>
     */
    public function torneosConvocatoriaFilasSinInscripciones(int $torneoId): array
    {
        $sql = 'SELECT a.id AS asociacion_id, a.nombre AS asoc_nombre, a.email AS asoc_email, a.telefono AS asoc_tel,
            c.id AS convocatoria_id, c.invitado_en, c.estado_respuesta, c.notas,
            0 AS num_inscritos
            FROM asociaciones a
            LEFT JOIN torneo_convocatoria_asoc c ON c.asociacion_id = a.id AND c.torneo_id = :t
            ORDER BY a.nombre ASC';
        $st = $this->pdo->prepare($sql);
        $st->execute([':t' => $torneoId]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function torneosConvocatoriaInvitar(int $torneoId, int $asociacionId): void
    {
        $sql = 'INSERT INTO torneo_convocatoria_asoc (torneo_id, asociacion_id, invitado_en, estado_respuesta)
            VALUES (:t, :a, CURRENT_TIMESTAMP, \'pendiente\')
            ON DUPLICATE KEY UPDATE invitado_en = CURRENT_TIMESTAMP';
        $st = $this->pdo->prepare($sql);
        $st->execute([':t' => $torneoId, ':a' => $asociacionId]);
    }

    public function torneosConvocatoriaInvitarTodas(int $torneoId): void
    {
        $sql = 'INSERT INTO torneo_convocatoria_asoc (torneo_id, asociacion_id, invitado_en, estado_respuesta)
            SELECT :t, a.id, CURRENT_TIMESTAMP, \'pendiente\' FROM asociaciones a
            ON DUPLICATE KEY UPDATE invitado_en = CURRENT_TIMESTAMP';
        $st = $this->pdo->prepare($sql);
        $st->execute([':t' => $torneoId]);
    }

    /**
     * Registra invitación para varias asociaciones en una sola operación (INSERT…SELECT).
     *
     * @param list<int|string> $asociacionIds
     */
    public function torneosConvocatoriaInvitarSeleccion(int $torneoId, array $asociacionIds): void
    {
        $ids = [];
        foreach ($asociacionIds as $v) {
            $i = (int) $v;
            if ($i > 0) {
                $ids[$i] = true;
            }
        }
        $ids = array_keys($ids);
        if ($ids === []) {
            throw new InvalidArgumentException('Seleccione al menos una asociación.');
        }
        sort($ids, SORT_NUMERIC);
        $placeholders = [];
        $params = [':t' => $torneoId];
        foreach ($ids as $k => $ida) {
            $ph = ':a' . $k;
            $placeholders[] = $ph;
            $params[$ph] = $ida;
        }
        $inList = implode(', ', $placeholders);
        $sql = 'INSERT INTO torneo_convocatoria_asoc (torneo_id, asociacion_id, invitado_en, estado_respuesta)
            SELECT :t, a.id, CURRENT_TIMESTAMP, \'pendiente\' FROM asociaciones a
            WHERE a.id IN (' . $inList . ')
            ON DUPLICATE KEY UPDATE invitado_en = CURRENT_TIMESTAMP';
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
    }

    public function torneosConvocatoriaSetRespuesta(int $torneoId, int $asociacionId, string $estado): void
    {
        $ok = ['pendiente', 'aceptada', 'declinada'];
        if (!in_array($estado, $ok, true)) {
            throw new InvalidArgumentException('Estado de respuesta no válido.');
        }
        $sql = 'INSERT INTO torneo_convocatoria_asoc (torneo_id, asociacion_id, invitado_en, estado_respuesta)
            VALUES (:t, :a, CURRENT_TIMESTAMP, :e)
            ON DUPLICATE KEY UPDATE estado_respuesta = :e_upd';
        $st = $this->pdo->prepare($sql);
        $st->execute([':t' => $torneoId, ':a' => $asociacionId, ':e' => $estado, ':e_upd' => $estado]);
    }

    /**
     * Torneos futuros donde la asociación tiene invitación registrada (puede inscribir).
     *
     * @return list<array<string, mixed>>
     */
    public function torneosAbiertosInscripcionParaAsociacion(int $asociacionId): array
    {
        $sql = 'SELECT t.torneo, t.nombre, t.lugar, DATE(t.fechator) AS fechator, c.invitado_en, c.estado_respuesta
            FROM torneosact t
            INNER JOIN torneo_convocatoria_asoc c ON c.torneo_id = t.torneo AND c.asociacion_id = :a
                AND c.invitado_en IS NOT NULL
            WHERE DATE(t.fechator) >= CURDATE()
            ORDER BY t.fechator ASC';
        $st = $this->pdo->prepare($sql);
        $st->execute([':a' => $asociacionId]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    /**
     * Buscador compacto para inscripción AJAX (delegado / aso en su asociación).
     *
     * @return list<array<string, mixed>>
     */
    public function atletasBuscarEnAsociacion(int $asociacionId, string $q, int $limit = 12): array
    {
        if (!AuthService::canManageAsociacion($asociacionId)) {
            throw new RuntimeException('Sin permiso para esta asociación.');
        }
        $q = trim($q);
        if (strlen($q) < 2) {
            return [];
        }
        $limit = max(1, min(30, $limit));
        $like = '%' . $q . '%';
        $digits = preg_replace('/\D+/', '', $q);
        $likeCed = $digits !== '' ? $digits . '%' : $like;
        $st = $this->pdo->prepare(
            'SELECT id, cedula, nombre, numfvd FROM atletas WHERE asociacion = :a
             AND (nombre LIKE :qn OR cedula LIKE :qc) ORDER BY nombre ASC LIMIT ' . (int) $limit
        );
        $st->execute([':a' => $asociacionId, ':qn' => $like, ':qc' => $likeCed]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function torneosAtletasInscribibles(int $torneoId, int $asociacionId): array
    {
        $st = $this->pdo->prepare(
            'SELECT id, cedula, nombre, sexo, numfvd FROM atletas WHERE asociacion = :a ORDER BY nombre ASC'
        );
        $st->execute([':a' => $asociacionId]);
        $atletas = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $st2 = $this->pdo->prepare('SELECT cedula FROM inscripcion_torneo WHERE torneo_id = :t AND asociacion_id = :a');
        $st2->execute([':t' => $torneoId, ':a' => $asociacionId]);
        $usados = [];
        while ($row = $st2->fetch(PDO::FETCH_ASSOC)) {
            $usados[(int) $row['cedula']] = true;
        }

        $out = [];
        foreach ($atletas as $row) {
            $c = (int) preg_replace('/\D+/', '', (string) ($row['cedula'] ?? ''));
            if ($c === 0) {
                continue;
            }
            if (isset($usados[$c])) {
                continue;
            }
            $row['_cedula_num'] = $c;
            $out[] = $row;
        }

        return $out;
    }

    /**
     * @param list<int> $atletaIds
     */
    public function torneosInscribirAtletas(int $torneoId, int $asociacionId, array $atletaIds): int
    {
        return \FvdPortal\Services\InscripcionService::registrarMultiplesIndividuales($this->pdo, $torneoId, $asociacionId, $atletaIds);
    }

    /**
     * Metadatos de torneo + cupo para la UI de inscripción (modalidad 13").
     *
     * @return array{
     *   torneo: array<string, mixed>,
     *   clase: int,
     *   modo: string,
     *   integrantes_equipo: int|null,
     *   cupo: array{usado: int, max: int|null, restante: int|null}
     * }|null
     */
    public function torneoInscripcionMetaParaVista(int $torneoId, int $asociacionId, ?bool $cupoDesdeBandera = null): ?array
    {
        $row = \FvdPortal\Services\InscripcionService::torneoReglas($this->pdo, $torneoId);
        if ($row === null) {
            return null;
        }
        $cl = \FvdPortal\Services\InscripcionService::normalizarClaseTorneo($row);
        if ($cupoDesdeBandera === null) {
            $cupoDesdeBandera = AuthService::isDelegadoAsociacion();
        }
        $cupo = $cupoDesdeBandera
            ? \FvdPortal\Services\InscripcionService::estadoCupoAsociacionBandera($this->pdo, $torneoId, $asociacionId)
            : \FvdPortal\Services\InscripcionService::estadoCupoAsociacion($this->pdo, $torneoId, $asociacionId);
        $intEq = $cl === \FvdPortal\Services\InscripcionService::CLASE_EQUIPOS
            ? \FvdPortal\Services\InscripcionService::integrantesEquipoRequeridos($row)
            : null;

        return [
            'torneo'              => $row,
            'clase'               => $cl,
            'modo'                => \FvdPortal\Services\InscripcionService::claseEtiqueta($cl),
            'integrantes_equipo'  => $intEq,
            'cupo'                => $cupo,
        ];
    }

    /**
     * Buscador paginado para inscripción (8 por página en UI 13").
     *
     * @return array{rows: list<array<string, mixed>>, total: int, page: int, per_page: int, pages: int}
     */
    public function atletasBuscarInscripcionPaginado(
        int $asociacionId,
        string $q,
        int $page = 1,
        int $perPage = 8,
        ?int $omitirInscritosEnTorneoId = null
    ): array {
        if (!AuthService::canManageAsociacion($asociacionId)) {
            throw new RuntimeException('Sin permiso para esta asociación.');
        }
        $q = trim($q);
        $perPage = max(1, min(24, $perPage));
        $page = max(1, $page);
        if (strlen($q) < 2) {
            return ['rows' => [], 'total' => 0, 'page' => 1, 'per_page' => $perPage, 'pages' => 0];
        }
        $like = '%' . $q . '%';
        $digits = preg_replace('/\D+/', '', $q);
        $likeCed = $digits !== '' ? $digits . '%' : $like;
        $exSql = '';
        $bind = [':a' => $asociacionId, ':qn' => $like, ':qc' => $likeCed];
        if ($omitirInscritosEnTorneoId !== null && $omitirInscritosEnTorneoId > 0) {
            // Mismo criterio legado "disponibles": inscripcion=0 o sin torneo; excluye ya inscritos en cualquier torneo activo en ficha.
            $exSql = ' AND (COALESCE(a.inscripcion, 0) = 0 OR COALESCE(a.torneo_id, 0) = 0) ';
        }
        $stc = $this->pdo->prepare(
            'SELECT COUNT(*) FROM atletas a WHERE a.asociacion = :a
             AND (a.nombre LIKE :qn OR a.cedula LIKE :qc)' . $exSql
        );
        $stc->execute($bind);
        $total = (int) $stc->fetchColumn();
        $pages = $total > 0 ? (int) ceil($total / $perPage) : 0;
        if ($pages > 0 && $page > $pages) {
            $page = $pages;
        }
        $off = ($page - 1) * $perPage;
        $st = $this->pdo->prepare(
            'SELECT a.id, a.cedula, a.nombre, a.numfvd, a.foto, aso.nombre AS asociacion_nombre
             FROM atletas a
             LEFT JOIN asociaciones aso ON aso.id = a.asociacion
             WHERE a.asociacion = :a AND (a.nombre LIKE :qn OR a.cedula LIKE :qc)' . $exSql . '
             ORDER BY a.nombre ASC
             LIMIT ' . (int) $perPage . ' OFFSET ' . (int) $off
        );
        $st->execute($bind);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return [
            'rows'      => $rows,
            'total'     => $total,
            'page'      => $page,
            'per_page'  => $perPage,
            'pages'     => $pages,
        ];
    }
}
