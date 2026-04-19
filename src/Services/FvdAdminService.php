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
require_once __DIR__ . '/DeudaAsociacionGeneratorService.php';
require_once __DIR__ . '/DelegadoTorneoVentanasService.php';

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

    /** Baja lógica: no se elimina la fila; no debe figurar en listados normales. */
    public const ATLETA_ESTATUS_BAJA = 2;

    /** Nombre exacto en `asociaciones.nombre` para vincular `torneosact.organizacion_id` al guardar (mismo texto en el encabezado del formulario). */
    public const ASOCIACION_NOMBRE_FEDERACION_TORNEOS = 'Federación Venezolana de Dominó';

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
        'grupo_evento_id', 'apertura_anual',
    ];

    public function __construct(?PDO $pdo = null, ?string $projectRoot = null)
    {
        $this->pdo = $pdo ?? fvd_db();
        $this->projectRoot = $projectRoot ?? dirname(__DIR__, 2);
    }

    // ——— Asociaciones ———
    //
    // Campo único: `asociaciones.estatus` (misma columna para listado, filtro y toggle).
    // Valores canónicos al guardar desde el panel: 1 = activa, 0 = inactiva.
    // Legacy / sitio público (PublicSiteData): también se consideran activas 'activo', NULL o ''.

    /**
     * ¿La fila de asociación está "activa" para UI, filtro y toggle? Misma regla que el SQL del listado.
     *
     * @param array<string, mixed> $row
     */
    public static function asociacionEstatusEsActiva(array $row): bool
    {
        $estRaw = $row['estatus'] ?? null;
        if ($estRaw === 1 || $estRaw === '1') {
            return true;
        }
        if (is_string($estRaw) && strcasecmp(trim($estRaw), 'activo') === 0) {
            return true;
        }
        if ($estRaw === null) {
            return true;
        }
        if (trim((string) $estRaw) === '') {
            return true;
        }

        return false;
    }

    /**
     * Condición SQL alineada con {@see asociacionEstatusEsActiva()} y PublicSiteData.
     */
    private static function sqlAsociacionesWhereEstatus(string $filtroEstatus): string
    {
        $activa = '(asociaciones.estatus = 1 OR asociaciones.estatus = \'activo\' '
            . 'OR asociaciones.estatus IS NULL '
            . 'OR TRIM(COALESCE(CAST(asociaciones.estatus AS CHAR), \'\')) = \'\')';
        if ($filtroEstatus === 'activas') {
            return ' AND ' . $activa . ' ';
        }
        if ($filtroEstatus === 'inactivas') {
            return ' AND NOT (' . $activa . ') ';
        }

        return '';
    }

    /**
     * @param 'todas'|'activas'|'inactivas' $filtroEstatus
     * @return array{total:int,page:int,per_page:int,pages:int,rows:list<array<string,mixed>>}
     */
    public function asociacionesPaginateList(int $page, int $perPage, string $q, string $filtroEstatus = 'todas'): array
    {
        $params = [];
        $search = '';
        if ($q !== '') {
            $params[':fq'] = '%' . $q . '%';
            $search = ' AND asociaciones.nombre LIKE :fq ';
        }
        $search .= self::sqlAsociacionesWhereEstatus($filtroEstatus);
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

    /** Activa/desactiva asociación: persiste siempre 0/1 en `estatus`. Solo administrador FVD. */
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
        $activa = self::asociacionEstatusEsActiva($row);
        $new = $activa ? 0 : 1;
        $st = $this->pdo->prepare('UPDATE asociaciones SET estatus = :e WHERE id = :id');
        $st->execute([':e' => $new, ':id' => $id]);
    }

    // ——— Atletas ———

    /**
     * @return array{total:int,page:int,per_page:int,pages:int,rows:list<array<string,mixed>>}
     */
    public function atletasPaginateList(
        int $page,
        int $perPage,
        string $cedula,
        string $q,
        string $alcance = 'todos',
        string $tipo = 'normal',
        int $asociacionId = 0
    ): array {
        require_once __DIR__ . '/QueryHelper.php';
        $p = \FvdPortal\Services\QueryHelper::selectPaginado(
            'atletas',
            [
                '__cedula'         => $cedula,
                '__nombre'         => $q,
                '__alcance'        => $alcance,
                '__tipo'           => $tipo,
                '__asociacion_id'  => $asociacionId,
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
     * Pone en 0 un marcador de servicio en `atletas` dentro del alcance de sesión
     * ({@see QueryHelper::asociacionScopeSql}: FVD = todos; regional = una asociación).
     * Para `inscripcion` también pone `torneo_id = 0`.
     *
     * @param 'carnet'|'traspaso'|'anualidad'|'afiliacion'|'inscripcion' $campo
     *
     * @return int Filas afectadas (puede ser 0)
     */
    public function atletasResetMarcadorMasivo(string $campo): int
    {
        static $allowed = [
            'carnet' => true,
            'traspaso' => true,
            'anualidad' => true,
            'afiliacion' => true,
            'inscripcion' => true,
        ];
        if (!isset($allowed[$campo])) {
            throw new \InvalidArgumentException('Marcador no permitido.');
        }
        $params = [];
        $scope = QueryHelper::asociacionScopeSql('a.asociacion', $params);
        if ($campo === 'inscripcion') {
            $sql = 'UPDATE atletas a SET a.inscripcion = 0, a.torneo_id = 0 WHERE 1=1' . $scope;
        } else {
            $sql = 'UPDATE atletas a SET a.' . $campo . ' = 0 WHERE 1=1' . $scope;
        }
        try {
            $st = $this->pdo->prepare($sql);
            $st->execute($params);

            return $st->rowCount();
        } catch (\PDOException $e) {
            error_log('[FvdAdminService] atletasResetMarcadorMasivo: ' . $e->getMessage());
            throw new \RuntimeException('No se pudo reiniciar el marcador.');
        }
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
    /**
     * Recalcula `deuda_asociaciones` desde `atletas` para el torneo/asociación (no interrumpe el guardado si falla).
     */
    private function sincronizarDeudaTrasCambioAtletas(int $torneoId, int $asociacionId): void
    {
        if ($torneoId <= 0 || $asociacionId <= 0) {
            return;
        }
        try {
            \FvdPortal\Services\DeudaAsociacionGeneratorService::generarParaTorneoYAsociacion($this->pdo, $torneoId, $asociacionId);
        } catch (\Throwable $e) {
            error_log('[FvdAdminService] sincronizarDeudaTrasCambioAtletas: ' . $e->getMessage());
        }
    }

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
                $this->sincronizarDeudaTrasCambioAtletas((int) ($data['torneo_id'] ?? 0), (int) ($data['asociacion'] ?? 0));

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
                $this->sincronizarDeudaTrasCambioAtletas((int) ($data['torneo_id'] ?? 0), (int) ($data['asociacion'] ?? 0));

                return;
            }

            QueryHelper::update($this->pdo, 'atletas', $data, self::ATLETAS_PERSIST, 'id = :wid', [':wid' => $id]);
            $tNew = (int) ($data['torneo_id'] ?? 0);
            $aNew = (int) ($data['asociacion'] ?? 0);
            $this->sincronizarDeudaTrasCambioAtletas($tNew, $aNew);
            if ($prevRow !== null) {
                $tOld = (int) ($prevRow['torneo_id'] ?? 0);
                $aOld = (int) ($prevRow['asociacion'] ?? 0);
                if ($tOld !== $tNew || $aOld !== $aNew) {
                    $this->sincronizarDeudaTrasCambioAtletas($tOld, $aOld);
                }
            }
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
        if ($cur === self::ATLETA_ESTATUS_BAJA) {
            return;
        }
        $new = $cur === self::ATLETA_ESTATUS_ACTIVO ? self::ATLETA_ESTATUS_PENDIENTE : self::ATLETA_ESTATUS_ACTIVO;
        $st = $this->pdo->prepare('UPDATE atletas SET estatus = :e WHERE id = :id');
        $st->execute([':e' => $new, ':id' => $id]);
        if ($st->rowCount() > 0) {
            $this->sincronizarDeudaTrasCambioAtletas((int) ($prev['torneo_id'] ?? 0), (int) ($prev['asociacion'] ?? 0));
        }
    }

    /**
     * Marca al atleta como dado de baja (no borra la fila).
     */
    public function atletasDarBaja(int $id): void
    {
        $prev = $this->atletasFind($id);
        if ($prev === null) {
            return;
        }
        $this->enforceAsociacionId(isset($prev['asociacion']) ? (int) $prev['asociacion'] : null);
        $params = [':id' => $id, ':e' => self::ATLETA_ESTATUS_BAJA];
        $scope = QueryHelper::asociacionScopeSql('a.asociacion', $params);
        $sql = 'UPDATE atletas a SET a.estatus = :e WHERE a.id = :id ' . $scope;
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        if ($st->rowCount() > 0) {
            $this->sincronizarDeudaTrasCambioAtletas((int) ($prev['torneo_id'] ?? 0), (int) ($prev['asociacion'] ?? 0));
        }
    }

    /** Restaura un atleta dado de baja a estatus pendiente (reactivación manual). */
    public function atletasRestaurarDesdeBaja(int $id): void
    {
        $prev = $this->atletasFind($id);
        if ($prev === null) {
            return;
        }
        if ((int) ($prev['estatus'] ?? 0) !== self::ATLETA_ESTATUS_BAJA) {
            return;
        }
        $this->enforceAsociacionId(isset($prev['asociacion']) ? (int) $prev['asociacion'] : null);
        $params = [':id' => $id, ':e' => self::ATLETA_ESTATUS_PENDIENTE];
        $scope = QueryHelper::asociacionScopeSql('a.asociacion', $params);
        $sql = 'UPDATE atletas a SET a.estatus = :e WHERE a.id = :id ' . $scope;
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        if ($st->rowCount() > 0) {
            $this->sincronizarDeudaTrasCambioAtletas((int) ($prev['torneo_id'] ?? 0), (int) ($prev['asociacion'] ?? 0));
        }
    }

    /**
     * @deprecated Usar {@see self::atletasDarBaja()}; se mantiene por enlaces antiguos.
     */
    public function atletasDelete(int $id): void
    {
        $this->atletasDarBaja($id);
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
        if ($e === self::ATLETA_ESTATUS_BAJA) {
            return 'Baja';
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
        $scopeAsoc = '';
        if (AuthService::role() === AuthService::ROLE_ASO_ADMIN) {
            $mine = AuthService::idAsociacion();
            if ($mine === null || (int) $mine <= 0) {
                return [
                    'total' => 0,
                    'page' => 1,
                    'per_page' => $perPage,
                    'pages' => 0,
                    'rows' => [],
                ];
            }
            $params[':org_scope'] = (int) $mine;
            $scopeAsoc = ' AND t.organizacion_id = :org_scope ';
        }
        $countSql = 'SELECT COUNT(*) FROM torneosact t WHERE 1=1' . $search . $scopeAsoc;
        $dataSql = 'SELECT t.torneo, t.nombre, t.lugar, t.fechator, t.estatus, t.organizacion_id, o.nombre AS org_nombre
            FROM torneosact t
            LEFT JOIN asociaciones o ON t.organizacion_id = o.id
            WHERE 1=1' . $search . $scopeAsoc . ' ORDER BY t.fechator DESC';

        return QueryHelper::paginate(
            $this->pdo,
            $countSql,
            $dataSql,
            $params,
            $page,
            $perPage
        );
    }

    public function torneosFind(?int $torneo): ?array
    {
        if ($torneo === null) {
            return null;
        }
        $params = [':t' => $torneo];
        $sql = 'SELECT t.* FROM torneosact t WHERE t.torneo = :t';
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * Alta, edición y borrado de torneos (tabla torneosact): administrador general FVD o administrador de asociación (solo torneos de su organización).
     */
    public function torneosRequireGestionTorneo(): void
    {
        $r = AuthService::role();
        if ($r === AuthService::ROLE_FVD_ADMIN) {
            return;
        }
        if ($r === AuthService::ROLE_ASO_ADMIN) {
            $mine = AuthService::idAsociacion();
            if ($mine !== null && (int) $mine > 0) {
                return;
            }
        }
        http_response_code(403);
        exit('No tiene permiso para gestionar torneos.');
    }

    /**
     * Operaciones solo FVD (p. ej. relacionar campeonatos por grupo).
     */
    public function torneosRequireFvdAdminForGestion(): void
    {
        if (AuthService::role() !== AuthService::ROLE_FVD_ADMIN) {
            http_response_code(403);
            exit('Solo el administrador general FVD puede gestionar torneos.');
        }
    }

    /**
     * Si el usuario es administrador de asociación, el torneo debe tener `organizacion_id` = su asociación.
     *
     * @param array<string, mixed>|null $row Fila de {@see torneosFind} o null en alta.
     */
    public function torneosAssertCanGestionarTorneoRow(?array $row): void
    {
        if ($row === null) {
            return;
        }
        if (AuthService::role() !== AuthService::ROLE_ASO_ADMIN) {
            return;
        }
        $mine = AuthService::idAsociacion();
        if ($mine === null || (int) ($row['organizacion_id'] ?? 0) !== (int) $mine) {
            http_response_code(403);
            exit('No puede gestionar este torneo.');
        }
    }

    /**
     * Organización al guardar: federación para FVD; asociación del usuario para administrador regional.
     */
    public function torneosOrganizacionIdParaGuardado(): ?int
    {
        if (AuthService::role() === AuthService::ROLE_FVD_ADMIN) {
            return $this->torneosOrganizacionFederacionId();
        }
        if (AuthService::role() === AuthService::ROLE_ASO_ADMIN) {
            $mine = AuthService::idAsociacion();

            return $mine !== null && (int) $mine > 0 ? (int) $mine : null;
        }

        return null;
    }

    /**
     * ID en `asociaciones` cuyo `nombre` coincide exactamente (tras TRIM) con el nombre de federación configurado en el servicio.
     * Si no existe esa fila, devuelve null (el torneo puede guardarse con `organizacion_id` NULL).
     */
    public function torneosOrganizacionFederacionId(): ?int
    {
        $st = $this->pdo->prepare('SELECT id FROM asociaciones WHERE TRIM(nombre) = :n LIMIT 1');
        $st->execute([':n' => self::ASOCIACION_NOMBRE_FEDERACION_TORNEOS]);
        $id = $st->fetchColumn();
        if ($id !== false && (int) $id > 0) {
            return (int) $id;
        }

        return null;
    }

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
        $this->torneosRequireGestionTorneo();

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
            if (in_array($col, ['organizacion_id', 'tipo', 'clase', 'tiempo', 'puntos', 'rondas', 'estatus', 'ranking', 'pareclub', 'grupo_evento_id', 'apertura_anual'], true)) {
                $data[$col] = $v === '' || $v === null ? null : (int) $v;
            } elseif ($col === 'costotor') {
                $data[$col] = $v === '' ? null : (float) $v;
            } else {
                $data[$col] = $v === '' ? null : (string) $v;
            }
        }

        if ($torneoId === null) {
            $data['apertura_anual'] = !empty($post['apertura_anual']) ? 1 : 0;
        } elseif (array_key_exists('apertura_anual', $post)) {
            $data['apertura_anual'] = !empty($post['apertura_anual']) ? 1 : 0;
        } else {
            unset($data['apertura_anual']);
        }

        $data['publicar_landing'] = 1;
        $data['organizacion_id'] = $this->torneosOrganizacionIdParaGuardado();

        // Grupo de evento: solo tras crear el torneo y solo para campeonatos (varios el mismo día).
        if ($torneoId === null) {
            $data['grupo_evento_id'] = null;
        }
        if ((int) ($data['tipo'] ?? 0) !== 2) {
            $data['grupo_evento_id'] = null;
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
            $this->torneosAssertCanGestionarTorneoRow($prev);
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
        if (!$this->torneosactGrupoEventoColumnExists()) {
            unset($data['grupo_evento_id'], $data['apertura_anual']);
        }

        if ($torneoId === null) {
            $newId = (int) QueryHelper::insert($this->pdo, 'torneosact', $data, self::TORNEOS_PERSIST);
            $this->torneosPostCreacionInvitacionesDelegados($newId);
            if (!empty($data['apertura_anual'])) {
                try {
                    $this->torneosEjecutarMarcaAnualidadTodosAtletas();
                } catch (Throwable $e) {
                    error_log('[FvdAdminService] apertura_anual: ' . $e->getMessage());
                }
            }

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
        $role = AuthService::role();
        if ($newTorneoId <= 0 || ($role !== AuthService::ROLE_FVD_ADMIN && $role !== AuthService::ROLE_ASO_ADMIN)) {
            return;
        }
        if ($this->torneosConvocatoriaTableExists()) {
            try {
                $this->torneosConvocatoriaInvitarTodas($newTorneoId);
            } catch (Throwable $e) {
                error_log('[FvdAdminService] torneosPostCreacionInvitacionesDelegados convocatoria: ' . $e->getMessage());
            }

            return;
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
        $role = AuthService::role();
        if ($role !== AuthService::ROLE_FVD_ADMIN && $role !== AuthService::ROLE_ASO_ADMIN) {
            return;
        }
        if (!$this->torneosConvocatoriaTableExists()) {
            return;
        }
        $this->torneosConvocatoriaInvitarTodas($torneoId);
    }

    public function torneosDelete(int $torneo): void
    {
        $this->torneosRequireGestionTorneo();
        $prev = $this->torneosFind($torneo);
        if ($prev === null) {
            return;
        }
        $this->torneosAssertCanGestionarTorneoRow($prev);
        $params = [':t' => $torneo];
        $sql = 'DELETE FROM torneosact t WHERE t.torneo = :t';
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
     * Panel de evento, convocatorias, histórico y acciones masivas: FVD o administrador de la asociación organizadora del torneo.
     */
    public function torneosEventoRequireGestionPanel(int $torneoId): void
    {
        if ($torneoId <= 0) {
            http_response_code(403);
            exit('Torneo no válido.');
        }
        if (AuthService::role() === AuthService::ROLE_FVD_ADMIN) {
            return;
        }
        if (AuthService::role() === AuthService::ROLE_ASO_ADMIN) {
            $t = $this->torneosFind($torneoId);
            if ($t === null) {
                http_response_code(404);
                exit('Torneo no encontrado.');
            }
            $this->torneosAssertCanGestionarTorneoRow($t);

            return;
        }
        http_response_code(403);
        exit('Solo el administrador general FVD o el administrador de la asociación organizadora puede usar esta acción.');
    }

    /**
     * Métricas del panel de evento del torneo: solo atletas con `torneo_id` = este evento.
     * Conteos de banderas = filas con valor 1 (no pendientes con 0).
     *
     * @return array{
     *   inscripciones_torneo: int,
     *   atletas_en_ambito: int,
     *   afiliacion_pendiente: int,
     *   anualidad_pendiente: int,
     *   carnet_pendiente: int,
     *   traspaso_pendiente: int
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
        ];
        if ($torneoId <= 0) {
            return $out;
        }

        $flagMap = [
            'afiliacion' => 'afiliacion_pendiente',
            'anualidad' => 'anualidad_pendiente',
            'carnet' => 'carnet_pendiente',
            'traspaso' => 'traspaso_pendiente',
        ];

        try {
            $params = [':torneo_panel' => $torneoId];
            $scope = QueryHelper::asociacionScopeSql('atletas.asociacion', $params);
            $base = 'atletas.torneo_id = :torneo_panel';

            $st = $this->pdo->prepare(
                'SELECT COUNT(*) FROM atletas WHERE ' . $base . ' AND COALESCE(atletas.inscripcion, 0) = 1' . $scope
            );
            $st->execute($params);
            $out['inscripciones_torneo'] = (int) $st->fetchColumn();

            $params = [':torneo_panel' => $torneoId];
            $scope = QueryHelper::asociacionScopeSql('atletas.asociacion', $params);
            $st = $this->pdo->prepare('SELECT COUNT(*) FROM atletas WHERE ' . $base . $scope);
            $st->execute($params);
            $out['atletas_en_ambito'] = (int) $st->fetchColumn();
        } catch (Throwable $e) {
            error_log('[torneosPanelEstadisticas atletas base] ' . $e->getMessage());

            return $out;
        }

        foreach ($flagMap as $col => $key) {
            try {
                $p = [':torneo_panel' => $torneoId];
                $sc = QueryHelper::asociacionScopeSql('atletas.asociacion', $p);
                $sql = 'SELECT COUNT(*) FROM atletas WHERE atletas.torneo_id = :torneo_panel AND COALESCE(atletas.'
                    . $col . ', 0) = 1' . $sc;
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
        $this->torneosConvocatoriaSincronizarNotificacionesDelegados($torneoId, [$asociacionId]);
    }

    public function torneosConvocatoriaInvitarTodas(int $torneoId): void
    {
        $sql = 'INSERT INTO torneo_convocatoria_asoc (torneo_id, asociacion_id, invitado_en, estado_respuesta)
            SELECT :t, a.id, CURRENT_TIMESTAMP, \'pendiente\' FROM asociaciones a
            ON DUPLICATE KEY UPDATE invitado_en = CURRENT_TIMESTAMP';
        $st = $this->pdo->prepare($sql);
        $st->execute([':t' => $torneoId]);
        $this->torneosConvocatoriaSincronizarNotificacionesDelegados($torneoId, null);
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
        $this->torneosConvocatoriaSincronizarNotificacionesDelegados($torneoId, $ids);
    }

    /**
     * Tras registrar invitación en convocatoria: aviso en panel del delegado + tarjeta PDF (si Dompdf está disponible).
     *
     * @param list<int>|null $soloAsociacionIds null = todos los delegados activos del torneo
     */
    private function torneosConvocatoriaSincronizarNotificacionesDelegados(int $torneoId, ?array $soloAsociacionIds): void
    {
        if ($torneoId <= 0) {
            return;
        }
        try {
            if ($soloAsociacionIds === null) {
                \FvdPortal\Services\DelegadoTorneoNotifService::crearNotificacionesParaTorneo($this->pdo, $torneoId);
                \FvdPortal\Services\TorneoDelegadoTarjetaService::generarTarjetasParaTorneo($this->pdo, $torneoId, $this->projectRoot);
            } else {
                $ids = array_values(array_unique(array_filter(array_map(static fn ($v): int => (int) $v, $soloAsociacionIds), static fn (int $x): bool => $x > 0)));
                if ($ids === []) {
                    return;
                }
                \FvdPortal\Services\DelegadoTorneoNotifService::crearNotificacionesParaTorneoFiltrado($this->pdo, $torneoId, $ids);
                \FvdPortal\Services\TorneoDelegadoTarjetaService::generarTarjetasParaTorneo($this->pdo, $torneoId, $this->projectRoot, $ids);
            }
        } catch (Throwable $e) {
            error_log('[FvdAdminService] torneosConvocatoriaSincronizarNotificacionesDelegados: ' . $e->getMessage());
        }
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
     * Torneos futuros con convocatoria para la asociación (incluye pendientes de envío de invitación).
     *
     * @return list<array<string, mixed>>
     */
    public function torneosAbiertosInscripcionParaAsociacion(int $asociacionId): array
    {
        $sql = 'SELECT t.torneo, t.nombre, t.lugar, DATE(t.fechator) AS fechator, c.invitado_en, c.estado_respuesta
            FROM torneosact t
            INNER JOIN torneo_convocatoria_asoc c ON c.torneo_id = t.torneo AND c.asociacion_id = :a
            WHERE DATE(t.fechator) >= CURDATE()
            ORDER BY t.fechator ASC';
        $st = $this->pdo->prepare($sql);
        $st->execute([':a' => $asociacionId]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function torneosactGrupoEventoColumnExists(): bool
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        try {
            $this->pdo->query('SELECT grupo_evento_id FROM torneosact LIMIT 0');
            $cache = true;
        } catch (Throwable $e) {
            $cache = false;
        }

        return $cache;
    }

    /**
     * Eventos de un día concreto para la pantalla de relación por grupo (torneos y campeonatos con asociación si existe).
     *
     * @return list<array<string, mixed>>
     */
    public function torneosRelacionGrupoCandidatosPorFecha(string $fechaYmd): array
    {
        if (!$this->torneosactGrupoEventoColumnExists()) {
            return [];
        }
        try {
            $d = new \DateTimeImmutable($fechaYmd . ' 00:00:00');
        } catch (\Exception $e) {
            return [];
        }
        $ymd = $d->format('Y-m-d');
        $st = $this->pdo->prepare(
            'SELECT t.torneo, t.nombre, t.lugar, DATE(t.fechator) AS fechator, t.tipo, t.grupo_evento_id,
                    t.organizacion_id, o.nombre AS org_nombre
             FROM torneosact t
             LEFT JOIN asociaciones o ON o.id = t.organizacion_id
             WHERE t.fechator IS NOT NULL AND DATE(t.fechator) = :d
             ORDER BY t.tipo DESC, t.nombre ASC'
        );
        $st->execute([':d' => $ymd]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** Máxima diferencia en días entre fechas de inicio de campeonatos vinculados (alerta operativa). */
    public const RELACION_GRUPO_MAX_DIAS_ENTRE_FECHAS = 7;

    /**
     * Crea la tabla maestra de nombres nominales por grupo si no existe.
     */
    public function campeonatoGrupoEnsureTable(): void
    {
        $sqlPath = dirname(__DIR__, 2) . '/fvdmasteradmin/sql/install_fvd_campeonato_grupo.sql';
        if (!is_readable($sqlPath)) {
            return;
        }
        $sql = file_get_contents($sqlPath);
        if ($sql === false || strpos($sql, 'CREATE TABLE') === false) {
            return;
        }
        try {
            $this->pdo->exec($sql);
        } catch (Throwable $e) {
            error_log('[FvdAdminService::campeonatoGrupoEnsureTable] ' . $e->getMessage());
        }
    }

    /**
     * Comprueba que las fechas de inicio de los torneos no estén demasiado alejadas entre sí.
     *
     * @param list<int> $ids
     */
    public function torneosRelacionGrupoValidarDispersionFechas(array $ids): void
    {
        if ($ids === []) {
            return;
        }
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $st = $this->pdo->prepare("SELECT DATE(t.fechator) AS fd FROM torneosact t WHERE t.torneo IN ($ph)");
        $st->execute($ids);
        $dates = [];
        while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
            $fd = $row['fd'] ?? null;
            if ($fd === null || $fd === false) {
                continue;
            }
            $s = (string) $fd;
            if ($s === '' || strncmp($s, '0000', 4) === 0) {
                continue;
            }
            try {
                $dates[] = new \DateTimeImmutable($s . ' 00:00:00');
            } catch (\Exception $e) {
                continue;
            }
        }
        if (count($dates) < 2) {
            return;
        }
        $min = $dates[0];
        $max = $dates[0];
        foreach ($dates as $d) {
            if ($d < $min) {
                $min = $d;
            }
            if ($d > $max) {
                $max = $d;
            }
        }
        $diff = (int) $min->diff($max)->days;
        if ($diff > self::RELACION_GRUPO_MAX_DIAS_ENTRE_FECHAS) {
            throw new InvalidArgumentException(
                'Las fechas de inicio de los campeonatos seleccionados difieren en '
                . $diff . ' días (máximo permitido: ' . self::RELACION_GRUPO_MAX_DIAS_ENTRE_FECHAS
                . '). Revise la selección o corrija las fechas en cada evento antes de vincular.'
            );
        }
    }

    /**
     * Asigna el mismo grupo_evento_id (nuevo) a varios campeonatos y guarda el nombre nominal en la tabla maestra.
     *
     * @param list<mixed> $torneoIds
     */
    public function torneosRelacionGrupoAplicar(array $torneoIds, string $nombreNominalCampeonato): int
    {
        $this->torneosRequireFvdAdminForGestion();
        if (!$this->torneosactGrupoEventoColumnExists()) {
            throw new RuntimeException('No existe la columna grupo_evento_id en torneosact.');
        }
        $nombreNominalCampeonato = trim($nombreNominalCampeonato);
        if ($nombreNominalCampeonato === '' || mb_strlen($nombreNominalCampeonato) < 2) {
            throw new InvalidArgumentException('Indique el nombre nominal del campeonato (mínimo 2 caracteres).');
        }
        if (mb_strlen($nombreNominalCampeonato) > 255) {
            throw new InvalidArgumentException('El nombre nominal del campeonato no puede superar 255 caracteres.');
        }
        $ids = [];
        foreach ($torneoIds as $x) {
            $n = (int) $x;
            if ($n > 0) {
                $ids[$n] = $n;
            }
        }
        $ids = array_values($ids);
        if (count($ids) < 2) {
            throw new InvalidArgumentException('Seleccione al menos dos campeonatos para vincular.');
        }
        $this->torneosRelacionGrupoValidarDispersionFechas($ids);
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $st = $this->pdo->prepare("SELECT torneo, tipo FROM torneosact WHERE torneo IN ($ph)");
        $st->execute($ids);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        if (count($rows) !== count($ids)) {
            throw new InvalidArgumentException('Uno o más eventos no existen.');
        }
        foreach ($rows as $r) {
            if ((int) ($r['tipo'] ?? 0) !== 2) {
                throw new InvalidArgumentException('Solo se pueden vincular campeonatos (tipo Campeonato).');
            }
        }
        $stMax = $this->pdo->query('SELECT COALESCE(MAX(grupo_evento_id), 0) FROM torneosact');
        $next = (int) $stMax->fetchColumn() + 1;
        if ($next <= 0) {
            $next = 1;
        }
        $upd = $this->pdo->prepare('UPDATE torneosact SET grupo_evento_id = :g WHERE torneo = :id');
        foreach ($ids as $tid) {
            $upd->execute([':g' => $next, ':id' => $tid]);
        }

        $this->campeonatoGrupoEnsureTable();
        try {
            $ins = $this->pdo->prepare(
                'INSERT INTO fvd_campeonato_grupo (grupo_evento_id, nombre_nominal) VALUES (:g, :n)
                 ON DUPLICATE KEY UPDATE nombre_nominal = VALUES(nombre_nominal)'
            );
            $ins->execute([':g' => $next, ':n' => $nombreNominalCampeonato]);
        } catch (Throwable $e) {
            error_log('[FvdAdminService::torneosRelacionGrupoAplicar] maestro: ' . $e->getMessage());
            throw new RuntimeException('No se pudo guardar el nombre nominal del campeonato. Compruebe que exista la tabla fvd_campeonato_grupo.');
        }

        try {
            \FvdPortal\Services\DelegadoTorneoNotifService::sincronizarInvitacionesTrasVincularGrupo($this->pdo, $ids);
        } catch (Throwable $e) {
            error_log('[FvdAdminService::torneosRelacionGrupoAplicar] notif sync: ' . $e->getMessage());
        }

        return $next;
    }

    public function torneoGrupoEventoId(int $torneoId): ?int
    {
        if ($torneoId <= 0 || !$this->torneosactGrupoEventoColumnExists()) {
            return null;
        }
        $st = $this->pdo->prepare('SELECT grupo_evento_id FROM torneosact WHERE torneo = :t LIMIT 1');
        $st->execute([':t' => $torneoId]);
        $g = $st->fetchColumn();
        if ($g === false || $g === null) {
            return null;
        }
        $n = (int) $g;

        return $n > 0 ? $n : null;
    }

    /**
     * Resuelve el grupo de evento (campeonato vinculado) desde un ID de torneo o desde un grupo_evento_id directo.
     */
    public function resolverGrupoDesdeCampeonatoParam(int $param): ?int
    {
        if ($param <= 0 || !$this->torneosactGrupoEventoColumnExists()) {
            return null;
        }
        try {
            $st = $this->pdo->prepare('SELECT grupo_evento_id FROM torneosact WHERE torneo = :p LIMIT 1');
            $st->execute([':p' => $param]);
            $g = $st->fetchColumn();
            if ($g !== false && $g !== null && (int) $g > 0) {
                return (int) $g;
            }
            $st2 = $this->pdo->prepare('SELECT COUNT(*) FROM torneosact WHERE grupo_evento_id = :g');
            $st2->execute([':g' => $param]);
            if ((int) $st2->fetchColumn() > 0) {
                return $param;
            }
        } catch (Throwable $e) {
            error_log('[FvdAdminService::resolverGrupoDesdeCampeonatoParam] ' . $e->getMessage());
        }

        return null;
    }

    /**
     * Torneos del mismo campeonato (grupo) con convocatoria para la asociación.
     *
     * @return list<array<string, mixed>>
     */
    public function torneosPorGrupoCampeonato(int $asociacionId, int $grupoEventoId): array
    {
        if ($asociacionId <= 0 || $grupoEventoId <= 0 || !$this->torneosactGrupoEventoColumnExists()) {
            return [];
        }
        try {
            $sql = 'SELECT t.torneo, t.nombre, t.lugar, DATE(t.fechator) AS fechator, t.tipo, t.clase
                FROM torneosact t
                INNER JOIN torneo_convocatoria_asoc c ON c.torneo_id = t.torneo AND c.asociacion_id = :a
                WHERE t.grupo_evento_id = :g
                ORDER BY t.tipo ASC, t.nombre ASC';
            $st = $this->pdo->prepare($sql);
            $st->execute([':a' => $asociacionId, ':g' => $grupoEventoId]);

            return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            error_log('[FvdAdminService::torneosPorGrupoCampeonato] ' . $e->getMessage());

            return [];
        }
    }

    /**
     * Etiqueta corta para UI (Masculino / Femenino / Juvenil / primera palabra).
     */
    public function nombreCortaTorneoCampeonato(string $nombre): string
    {
        $n = mb_strtolower($nombre, 'UTF-8');
        if (str_contains($n, 'juvenil')) {
            return 'Juvenil';
        }
        if (str_contains($n, 'femen')) {
            return 'Femenino';
        }
        if (str_contains($n, 'masc')) {
            return 'Masculino';
        }
        $parts = preg_split('/\s+/u', trim($nombre)) ?: [];

        return $parts[0] !== '' && $parts[0] !== null ? (string) $parts[0] : $nombre;
    }

    /**
     * @return 'M'|'F'|null
     */
    public function torneoFiltroSexoEsperadoDesdeNombre(string $nombreTorneo): ?string
    {
        $n = mb_strtolower($nombreTorneo, 'UTF-8');
        if (str_contains($n, 'juvenil')) {
            return null;
        }
        if (str_contains($n, 'femen')) {
            return 'F';
        }
        if (str_contains($n, 'masc')) {
            return 'M';
        }

        return null;
    }

    /**
     * @param array<string, mixed> $atletaRow
     */
    public function atletaCoincideSexoTorneo(array $atletaRow, string $nombreTorneo): bool
    {
        $esp = $this->torneoFiltroSexoEsperadoDesdeNombre($nombreTorneo);
        if ($esp === null) {
            return true;
        }
        $sx = $atletaRow['sexo'] ?? null;
        if (is_string($sx)) {
            $sx = strtoupper(trim($sx));
        }
        if ($esp === 'F') {
            return $sx === 'F' || $sx === '2' || (int) $sx === 2;
        }
        if ($esp === 'M') {
            return $sx === 'M' || $sx === '1' || (int) $sx === 1;
        }

        return true;
    }

    /**
     * Listado de elegibles con filtro por género inferido del nombre del torneo (Masculino/Femenino).
     *
     * @return list<array<string, mixed>>
     */
    public function torneosAtletasInscribiblesFiltradoTorneo(int $torneoId, int $asociacionId): array
    {
        $rows = $this->torneosAtletasInscribibles($torneoId, $asociacionId);
        $st = $this->pdo->prepare('SELECT nombre FROM torneosact WHERE torneo = :t LIMIT 1');
        $st->execute([':t' => $torneoId]);
        $nom = (string) ($st->fetchColumn() ?: '');
        if ($nom === '' || $this->torneoFiltroSexoEsperadoDesdeNombre($nom) === null) {
            return $rows;
        }
        $out = [];
        foreach ($rows as $row) {
            if ($this->atletaCoincideSexoTorneo($row, $nom)) {
                $out[] = $row;
            }
        }

        return $out;
    }

    /**
     * Torneos del mismo grupo de inscripción que el contexto (o solo el contexto si no hay grupo).
     * Sin filtro de fecha: el delegado trabaja el evento activo invitado.
     *
     * @return list<array<string, mixed>>
     */
    public function torneosDelegadoGrupoInscripcion(int $asociacionId, int $contextTorneoId): array
    {
        $grupo = $this->torneoGrupoEventoId($contextTorneoId);
        if ($grupo !== null && $this->torneosactGrupoEventoColumnExists()) {
            $sql = 'SELECT t.torneo, t.nombre, t.lugar, DATE(t.fechator) AS fechator, t.tipo, t.clase
                FROM torneosact t
                INNER JOIN torneo_convocatoria_asoc c ON c.torneo_id = t.torneo AND c.asociacion_id = :a
                WHERE t.grupo_evento_id = :g
                ORDER BY (t.torneo = :ctx) DESC, t.tipo ASC, t.nombre ASC';
            $st = $this->pdo->prepare($sql);
            $st->execute([':a' => $asociacionId, ':g' => $grupo, ':ctx' => $contextTorneoId]);
        } else {
            $sql = 'SELECT t.torneo, t.nombre, t.lugar, DATE(t.fechator) AS fechator, t.tipo, t.clase
                FROM torneosact t
                INNER JOIN torneo_convocatoria_asoc c ON c.torneo_id = t.torneo AND c.asociacion_id = :a
                WHERE t.torneo = :ctx
                ORDER BY t.nombre ASC';
            $st = $this->pdo->prepare($sql);
            $st->execute([':a' => $asociacionId, ':ctx' => $contextTorneoId]);
        }

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function delegadoTorneoInscripcionPermitido(int $asociacionId, int $torneoIdSolicitado): bool
    {
        if (!AuthService::isDelegadoAsociacion()) {
            return true;
        }
        if ($torneoIdSolicitado <= 0) {
            return false;
        }
        foreach ($this->torneosAbiertosInscripcionParaAsociacion($asociacionId) as $r) {
            if ((int) ($r['torneo'] ?? 0) === $torneoIdSolicitado) {
                return true;
            }
        }
        $ctx = AuthService::delegadoTorneoContextId();
        if ($ctx === null || $ctx <= 0) {
            return true;
        }
        foreach ($this->torneosDelegadoGrupoInscripcion($asociacionId, $ctx) as $r) {
            if ((int) ($r['torneo'] ?? 0) === $torneoIdSolicitado) {
                return true;
            }
        }

        return false;
    }

    public function delegadoPuedeAbrirPantallaEvento(int $asociacionId, int $torneoId): bool
    {
        if (!AuthService::isDelegadoAsociacion()) {
            return true;
        }
        $did = (int) AuthService::userId();
        if ($did <= 0) {
            return false;
        }
        $grupo = $this->torneoGrupoEventoId($torneoId);

        return \FvdPortal\Services\DelegadoTorneoNotifService::delegadoTieneAccesoEventoGrupo(
            $this->pdo,
            $did,
            $asociacionId,
            $torneoId,
            $grupo
        );
    }

    public function torneosEjecutarMarcaAnualidadTodosAtletas(): void
    {
        $this->pdo->exec('UPDATE atletas SET anualidad = 1');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function torneosHistoricoMovimientos(int $torneoId): array
    {
        return \FvdPortal\Services\TorneoFinalizacionService::listarHistorico($this->pdo, $torneoId);
    }

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
            'SELECT id, cedula, nombre, sexo, numfvd, fechact, fechfvd, created_at, updated_at
             FROM atletas WHERE asociacion = :a
             ORDER BY COALESCE(fechact, fechfvd, DATE(updated_at), DATE(created_at)) DESC,
                      updated_at DESC, nombre ASC'
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
     * Inscritos confirmados en tabla inscripcion_torneo (modo administrador FVD / sin bandera en atletas).
     *
     * @return list<array{cedula: int|string, nombre: string, numfvd: int, equipo: int, atleta_id: int|null}>
     */
    public function torneosInscritosInscripcionTorneo(int $torneoId, int $asociacionId): array
    {
        if (!$this->torneosInscripcionTorneoTableExists()) {
            return [];
        }
        $st = $this->pdo->prepare(
            'SELECT it.cedula, it.nombre, it.numfvd, it.equipo FROM inscripcion_torneo it
             WHERE it.torneo_id = :t AND it.asociacion_id = :a ORDER BY it.nombre ASC'
        );
        $st->execute([':t' => $torneoId, ':a' => $asociacionId]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $st2 = $this->pdo->prepare(
            'SELECT id FROM atletas WHERE asociacion = :a
             AND REPLACE(REPLACE(REPLACE(REPLACE(UPPER(TRIM(cedula)), \'V\', \'\'), \'E\', \'\'), \'J\', \'\'), \'P\', \'\') = :d
             LIMIT 1'
        );
        foreach ($rows as &$r) {
            $d = preg_replace('/\D+/', '', (string) ($r['cedula'] ?? ''));
            $r['atleta_id'] = null;
            if ($d !== '') {
                $st2->execute([':a' => $asociacionId, ':d' => $d]);
                $aid = $st2->fetchColumn();
                $r['atleta_id'] = $aid !== false && (int) $aid > 0 ? (int) $aid : null;
            }
        }
        unset($r);

        return $rows;
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

        $ventanaDelegado = null;
        if (AuthService::isDelegadoAsociacion()) {
            try {
                $aidV = AuthService::idAsociacion();
                $ventanaDelegado = \FvdPortal\Services\DelegadoTorneoVentanasService::estadoParaTorneo(
                    $this->pdo,
                    $torneoId,
                    $aidV !== null && (int) $aidV > 0 ? (int) $aidV : null
                );
            } catch (\Throwable $e) {
                $ventanaDelegado = null;
            }
        }

        return [
            'torneo'              => $row,
            'clase'               => $cl,
            'modo'                => \FvdPortal\Services\InscripcionService::claseEtiqueta($cl),
            'integrantes_equipo'  => $intEq,
            'cupo'                => $cupo,
            'ventana_delegado'    => $ventanaDelegado,
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
        ?int $omitirInscritosEnTorneoId = null,
        ?int $filtroSexoDesdeTorneoId = null
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
        if ($filtroSexoDesdeTorneoId !== null && $filtroSexoDesdeTorneoId > 0) {
            try {
                $stn = $this->pdo->prepare('SELECT nombre FROM torneosact WHERE torneo = :t LIMIT 1');
                $stn->execute([':t' => $filtroSexoDesdeTorneoId]);
                $nomT = (string) ($stn->fetchColumn() ?: '');
                $esp = $this->torneoFiltroSexoEsperadoDesdeNombre($nomT);
                if ($esp === 'M') {
                    $exSql .= ' AND (COALESCE(a.sexo, 0) = 1 OR UPPER(TRIM(CAST(a.sexo AS CHAR))) IN (\'M\', \'MASCULINO\')) ';
                } elseif ($esp === 'F') {
                    $exSql .= ' AND (COALESCE(a.sexo, 0) = 2 OR UPPER(TRIM(CAST(a.sexo AS CHAR))) IN (\'F\', \'FEMENINO\', \'FEMENINA\')) ';
                }
            } catch (Throwable $e) {
                error_log('[FvdAdminService::atletasBuscarInscripcionPaginado sexo] ' . $e->getMessage());
            }
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
