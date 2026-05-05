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
require_once __DIR__ . '/FvdNotificacionesService.php';
require_once __DIR__ . '/NotificacionService.php';
require_once __DIR__ . '/FvdAccessManager.php';
require_once __DIR__ . '/NotificacionesDelegadosService.php';

use FvdPortal\Services\FvdNotificacionesService;

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

    /** Alta creada por delegado, pendiente de validación final del admin general. */
    public const ATLETA_ESTATUS_PENDIENTE_ADMIN = 990;

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
        'talla_camisa', 'observaciones',
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
        'organizacion_id', 'clavetor', 'nombre', 'lugar', 'fechator', 'tipo', 'es_campeonato', 'clase', 'tiempo',
        'puntos', 'rondas', 'estatus', 'costotor', 'ranking', 'pareclub', 'invitacion', 'afiche', 'publicar_landing',
        'grupo_evento_id', 'apertura_anual', 'fecha_limite_cambios',
    ];

    /** @return list<string> Columnas permitidas para INSERT en {@see torneosact} (p. ej. creación por campeonato). */
    public static function torneosInsertableColumns(): array
    {
        return self::TORNEOS_PERSIST;
    }

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
        if (is_string($estRaw)) {
            $t = trim($estRaw);
            if (strcasecmp($t, 'activo') === 0 || strcasecmp($t, 'activa') === 0) {
                return true;
            }
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
     * Fragmento `AND …` para filtrar por estatus de asociación (misma regla que el listado admin).
     *
     * @param non-empty-string $tableAlias Alias SQL válido (p. ej. `s`, `asociaciones`).
     * @param 'todas'|'activas'|'inactivas' $filtroEstatus
     */
    public static function asociacionesSqlFiltroEstatus(string $tableAlias, string $filtroEstatus): string
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $tableAlias)) {
            $tableAlias = 'asociaciones';
        }
        $a = $tableAlias;
        // Alineado con {@see asociacionEstatusEsActiva()}: INT 1 = activa; VARCHAR legado activo/a; NULL o cadena vacía = activa.
        // CAST(... AS UNSIGNED) evita depender de (estatus+0) con tipos raros y trata '1' en columna texto como activa.
        $estChar = 'TRIM(COALESCE(CAST(' . $a . '.estatus AS CHAR), \'\'))';
        $activa = '(
            (' . $a . '.estatus IS NOT NULL AND CAST(' . $a . '.estatus AS UNSIGNED) = 1)
            OR LOWER(' . $estChar . ') IN (\'activo\', \'activa\')
            OR ' . $a . '.estatus IS NULL
            OR ' . $estChar . ' = \'\'
        )';
        if ($filtroEstatus === 'activas') {
            return ' AND ' . $activa . ' ';
        }
        if ($filtroEstatus === 'inactivas') {
            return ' AND NOT (' . $activa . ') ';
        }

        return '';
    }

    /**
     * Condición SQL alineada con {@see asociacionEstatusEsActiva()} y PublicSiteData.
     */
    private static function sqlAsociacionesWhereEstatus(string $filtroEstatus): string
    {
        return self::asociacionesSqlFiltroEstatus('asociaciones', $filtroEstatus);
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

    /**
     * Establece el mismo estatus (0 o 1) en varias asociaciones. Solo administrador FVD.
     *
     * @param list<int|string> $ids
     */
    public function asociacionesBulkSetEstatus(array $ids, int $estatus): int
    {
        if (AuthService::role() !== AuthService::ROLE_FVD_ADMIN) {
            return 0;
        }
        $e = $estatus === 1 ? 1 : 0;
        $uniq = [];
        foreach ($ids as $v) {
            $i = (int) $v;
            if ($i > 0) {
                $uniq[$i] = true;
            }
        }
        $list = array_keys($uniq);
        if ($list === []) {
            return 0;
        }
        $updated = 0;
        foreach (array_chunk($list, 250) as $chunk) {
            $ph = [];
            $params = [':est' => $e];
            foreach ($chunk as $idx => $id) {
                $k = ':id' . $idx;
                $ph[] = $k;
                $params[$k] = $id;
            }
            $sql = 'UPDATE asociaciones SET estatus = :est WHERE id IN (' . implode(',', $ph) . ')';
            $st = $this->pdo->prepare($sql);
            $st->execute($params);
            $updated += $st->rowCount();
        }

        return $updated;
    }

    /**
     * @return array{
     *   atletas_total:int,
     *   atletas_activos:int,
     *   atletas_pendientes:int,
     *   atletas_baja:int,
     *   delegados_filas:int,
     *   torneos_organizados:int,
     *   inscripciones_registros:int,
     *   convocatorias_registros:int,
     *   deuda_filas:int
     * }
     */
    private static function asociacionesEstadisticasVacias(): array
    {
        return [
            'atletas_total'             => 0,
            'atletas_activos'           => 0,
            'atletas_pendientes'        => 0,
            'atletas_baja'              => 0,
            'delegados_filas'           => 0,
            'torneos_organizados'       => 0,
            'inscripciones_registros'   => 0,
            'convocatorias_registros'   => 0,
            'deuda_filas'               => 0,
        ];
    }

    /**
     * Contadores para el formulario de administración (requiere permiso sobre la asociación).
     *
     * @return array<string, int>
     */
    public function asociacionesEstadisticas(int $asociacionId): array
    {
        if ($asociacionId <= 0) {
            return self::asociacionesEstadisticasVacias();
        }
        $this->enforceAsociacionId($asociacionId);
        $out = self::asociacionesEstadisticasVacias();

        try {
            $st = $this->pdo->prepare(
                'SELECT
                    COUNT(*) AS tot,
                    SUM(CASE WHEN COALESCE(estatus, 0) = :ea THEN 1 ELSE 0 END) AS act,
                    SUM(CASE WHEN COALESCE(estatus, 0) = :ep THEN 1 ELSE 0 END) AS pen,
                    SUM(CASE WHEN COALESCE(estatus, 0) = :eb THEN 1 ELSE 0 END) AS baj
                 FROM atletas WHERE asociacion = :id'
            );
            $st->execute([
                ':id' => $asociacionId,
                ':ea' => self::ATLETA_ESTATUS_ACTIVO,
                ':ep' => self::ATLETA_ESTATUS_PENDIENTE,
                ':eb' => self::ATLETA_ESTATUS_BAJA,
            ]);
            $rw = $st->fetch(PDO::FETCH_ASSOC);
            if (is_array($rw)) {
                $out['atletas_total'] = (int) ($rw['tot'] ?? 0);
                $out['atletas_activos'] = (int) ($rw['act'] ?? 0);
                $out['atletas_pendientes'] = (int) ($rw['pen'] ?? 0);
                $out['atletas_baja'] = (int) ($rw['baj'] ?? 0);
            }
        } catch (Throwable $e) {
            error_log('[FvdAdminService::asociacionesEstadisticas atletas] ' . $e->getMessage());
        }

        try {
            $st = $this->pdo->prepare('SELECT COUNT(*) FROM delegados WHERE asociacion_id = :id');
            $st->execute([':id' => $asociacionId]);
            $out['delegados_filas'] = (int) $st->fetchColumn();
        } catch (Throwable $e) {
            error_log('[FvdAdminService::asociacionesEstadisticas delegados] ' . $e->getMessage());
        }

        try {
            $st = $this->pdo->prepare('SELECT COUNT(*) FROM torneosact WHERE organizacion_id = :id');
            $st->execute([':id' => $asociacionId]);
            $out['torneos_organizados'] = (int) $st->fetchColumn();
        } catch (Throwable $e) {
            error_log('[FvdAdminService::asociacionesEstadisticas torneosact] ' . $e->getMessage());
        }

        try {
            $st = $this->pdo->prepare('SELECT COUNT(*) FROM inscripcion_torneo WHERE asociacion_id = :id');
            $st->execute([':id' => $asociacionId]);
            $out['inscripciones_registros'] = (int) $st->fetchColumn();
        } catch (Throwable $e) {
            error_log('[FvdAdminService::asociacionesEstadisticas inscripcion_torneo] ' . $e->getMessage());
        }

        if ($this->torneosConvocatoriaTableExists()) {
            try {
                $st = $this->pdo->prepare('SELECT COUNT(*) FROM torneo_convocatoria_asoc WHERE asociacion_id = :id');
                $st->execute([':id' => $asociacionId]);
                $out['convocatorias_registros'] = (int) $st->fetchColumn();
            } catch (Throwable $e) {
                error_log('[FvdAdminService::asociacionesEstadisticas convocatoria] ' . $e->getMessage());
            }
        }

        try {
            $st = $this->pdo->prepare('SELECT COUNT(*) FROM deuda_asociaciones WHERE asociacion_id = :id');
            $st->execute([':id' => $asociacionId]);
            $out['deuda_filas'] = (int) $st->fetchColumn();
        } catch (Throwable $e) {
            error_log('[FvdAdminService::asociacionesEstadisticas deuda] ' . $e->getMessage());
        }

        return $out;
    }

    /**
     * Totales del catálogo (formulario «Nueva asociación», solo administrador FVD).
     *
     * @return array{asociaciones_total:int, asociaciones_activas:int, asociaciones_inactivas:int}
     */
    public function asociacionesEstadisticasGlobales(): array
    {
        $empty = ['asociaciones_total' => 0, 'asociaciones_activas' => 0, 'asociaciones_inactivas' => 0];
        if (AuthService::role() !== AuthService::ROLE_FVD_ADMIN) {
            return $empty;
        }
        try {
            $total = (int) $this->pdo->query('SELECT COUNT(*) FROM asociaciones')->fetchColumn();
            $sqlA = 'SELECT COUNT(*) FROM asociaciones WHERE 1=1' . self::asociacionesSqlFiltroEstatus('asociaciones', 'activas');
            $activas = (int) $this->pdo->query($sqlA)->fetchColumn();
            $sqlI = 'SELECT COUNT(*) FROM asociaciones WHERE 1=1' . self::asociacionesSqlFiltroEstatus('asociaciones', 'inactivas');
            $inactivas = (int) $this->pdo->query($sqlI)->fetchColumn();

            return [
                'asociaciones_total'    => $total,
                'asociaciones_activas'  => $activas,
                'asociaciones_inactivas'=> $inactivas,
            ];
        } catch (Throwable $e) {
            error_log('[FvdAdminService::asociacionesEstadisticasGlobales] ' . $e->getMessage());

            return $empty;
        }
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
        int $asociacionId = 0,
        string $smart = '',
        string $smartMode = ''
    ): array {
        require_once __DIR__ . '/QueryHelper.php';
        $smartTrim = trim($smart);
        $modeTrim = trim($smartMode);
        $p = \FvdPortal\Services\QueryHelper::selectPaginado(
            'atletas',
            [
                '__cedula'         => $smartTrim !== '' ? '' : $cedula,
                '__nombre'         => $smartTrim !== '' ? '' : $q,
                '__smart'          => $smartTrim,
                '__smart_mode'     => $modeTrim,
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
     * Busca por cédula en toda la tabla (sin filtro de alcance regional).
     * Sirve para comprobar duplicados globales (índice UNIQUE en cédula) y el aviso en alta.
     *
     * @return array<string, mixed>|null Fila con join de asociación o null
     */
    public function atletasFindByCedulaGlobal(string $cedula): ?array
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
        $sql = 'SELECT a.*, s.nombre AS asociacion_nombre
            FROM atletas a
            LEFT JOIN asociaciones s ON s.id = a.asociacion
            WHERE ' . $whereCed . '
            ORDER BY a.id ASC
            LIMIT 1';
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * Última fila en `fvd_solicitudes_delegado` (estado pendiente) vinculada al atleta (solicitud auxiliar delegado).
     *
     * @return array<string, mixed>|null
     */
    public function atletasSolicitudDelegadoPendientePorAtleta(int $atletaId): ?array
    {
        if ($atletaId <= 0) {
            return null;
        }
        require_once __DIR__ . '/DelegadoSolicitudService.php';
        \FvdPortal\Services\DelegadoSolicitudService::ensureTable($this->pdo);
        try {
            $st = $this->pdo->prepare(
                "SELECT id, tipo, estado, creado_en, nota
                 FROM fvd_solicitudes_delegado
                 WHERE atleta_id = :aid AND estado = 'pendiente'
                 ORDER BY creado_en DESC
                 LIMIT 1"
            );
            $st->execute([':aid' => $atletaId]);
            $r = $st->fetch(PDO::FETCH_ASSOC);

            return $r ?: null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Repuebla campos del formulario de alta tras un error de guardado (POST).
     *
     * @param array<string, mixed> $post
     * @return array<string, mixed>
     */
    public static function atletasRepoblarRowDesdePost(array $post): array
    {
        $out = [];
        $scalarKeys = [
            'cedula', 'nombre', 'profesion', 'direccion', 'celular', 'email', 'fechnac',
        ];
        foreach ($scalarKeys as $k) {
            if (!array_key_exists($k, $post)) {
                continue;
            }
            $out[$k] = is_scalar($post[$k]) ? trim((string) $post[$k]) : '';
        }
        foreach (['sexo', 'asociacion', 'estatus'] as $ik) {
            if (!array_key_exists($ik, $post)) {
                continue;
            }
            $pv = $post[$ik];
            $out[$ik] = $pv === '' || $pv === null ? 0 : (int) $pv;
        }

        return $out;
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

    private function atletasHasColumn(string $column): bool
    {
        static $cache = [];
        $key = strtolower($column);
        if (array_key_exists($key, $cache)) {
            return (bool) $cache[$key];
        }
        try {
            $st = $this->pdo->prepare(
                'SELECT 1
                 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = :t
                   AND COLUMN_NAME = :c
                 LIMIT 1'
            );
            $st->execute([':t' => 'atletas', ':c' => $column]);
            $cache[$key] = $st->fetchColumn() !== false;
        } catch (\Throwable $e) {
            $cache[$key] = false;
        }

        return (bool) $cache[$key];
    }

    /**
     * Columnas persistibles según esquema actual (omite talla/observaciones si no existen en BD).
     *
     * @return list<string>
     */
    private function atletasPersistColumnsForDb(): array
    {
        $out = [];
        foreach (self::ATLETAS_PERSIST as $col) {
            if (in_array($col, ['talla_camisa', 'observaciones'], true) && !$this->atletasHasColumn($col)) {
                continue;
            }
            $out[] = $col;
        }

        return $out;
    }

    /**
     * Siguiente número FVD correlativo (MAX(numfvd)+1), sin reservar fila.
     */
    public function atletasSiguienteNumfvdSugerido(): int
    {
        try {
            $st = $this->pdo->query('SELECT COALESCE(MAX(numfvd), 0) AS m FROM atletas');
            $row = $st ? $st->fetch(PDO::FETCH_ASSOC) : false;

            return (int) (($row['m'] ?? 0)) + 1;
        } catch (\Throwable $e) {
            error_log('[FvdAdminService::atletasSiguienteNumfvdSugerido] ' . $e->getMessage());

            return 1;
        }
    }

    /**
     * Torneos/eventos vinculados a una asociación (organizador = asociación).
     *
     * @return list<array{torneo:int,nombre:string,fechator:?string}>
     */
    public function atletasTorneosPorAsociacion(int $asociacionId): array
    {
        if ($asociacionId <= 0) {
            return [];
        }
        try {
            $st = $this->pdo->prepare(
                'SELECT torneo, nombre, fechator FROM torneosact
                 WHERE organizacion_id = :a
                 ORDER BY fechator DESC, nombre ASC
                 LIMIT 200'
            );
            $st->execute([':a' => $asociacionId]);
            $out = [];
            while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
                $out[] = [
                    'torneo'   => (int) ($r['torneo'] ?? 0),
                    'nombre'   => trim((string) ($r['nombre'] ?? '')),
                    'fechator' => $r['fechator'] ?? null,
                ];
            }

            return $out;
        } catch (\Throwable $e) {
            error_log('[FvdAdminService::atletasTorneosPorAsociacion] ' . $e->getMessage());

            return [];
        }
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
            if (in_array($col, ['talla_camisa', 'observaciones'], true) && !$this->atletasHasColumn($col)) {
                continue;
            }
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

        $fvdAltaInmediata = $id === null
            && AuthService::role() === AuthService::ROLE_FVD_ADMIN
            && !empty($post['fvd_alta_inmediata']);

        $deferNumfvd = false;
        if ($id === null) {
            if ($fvdAltaInmediata) {
                $data['numfvd'] = 0;
                $data['estatus'] = self::ATLETA_ESTATUS_ACTIVO;
            } else {
                $data['numfvd'] = 0;
                $data['estatus'] = AuthService::isDelegadoAsociacion() ? self::ATLETA_ESTATUS_PENDIENTE_ADMIN : self::ATLETA_ESTATUS_PENDIENTE;
            }
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
                $cedAlta = trim((string) ($data['cedula'] ?? ''));
                if ($cedAlta !== '') {
                    $dupGlobal = $this->atletasFindByCedulaGlobal($cedAlta);
                    if ($dupGlobal !== null) {
                        $nomD = trim((string) ($dupGlobal['nombre'] ?? ''));
                        $idD = (int) ($dupGlobal['id'] ?? 0);
                        $nfD = (int) ($dupGlobal['numfvd'] ?? 0);
                        $asocD = trim((string) ($dupGlobal['asociacion_nombre'] ?? ''));
                        $nfTxt = $nfD > 0 ? (string) $nfD : '— (pendiente)';
                        $asocTxt = $asocD !== '' ? $asocD : '—';
                        throw new RuntimeException(
                            'La cédula ' . $cedAlta . ' ya está registrada en el sistema.'
                            . ($nomD !== '' ? ' Atleta existente: ' . $nomD . '.' : '')
                            . ' ID interno: ' . $idD . ', Nº FVD: ' . $nfTxt . ', Asociación: ' . $asocTxt . '.'
                            . ' No puede crear otro registro con la misma cédula; revise el aviso bajo el campo cédula o abra la ficha existente.'
                        );
                    }
                }
                $persistCols = $this->atletasPersistColumnsForDb();
                if ($fvdAltaInmediata) {
                    $this->pdo->beginTransaction();
                    try {
                        $stMx = $this->pdo->query('SELECT COALESCE(MAX(numfvd), 0) AS m FROM atletas FOR UPDATE');
                        $rowMx = $stMx ? $stMx->fetch(PDO::FETCH_ASSOC) : false;
                        $data['numfvd'] = (int) (($rowMx['m'] ?? 0)) + 1;
                        $hoy = self::atletasFechaAceptacionFvd();
                        $data['fechfvd'] = $hoy;
                        $data['fechact'] = $hoy;
                        $data['afiliacion'] = 1;
                        $data['carnet'] = 1;
                        $data['anualidad'] = 1;
                        $data['estatus'] = self::ATLETA_ESTATUS_ACTIVO;
                        QueryHelper::insert($this->pdo, 'atletas', $data, $persistCols);
                        $this->pdo->commit();
                    } catch (\Throwable $e) {
                        if ($this->pdo->inTransaction()) {
                            $this->pdo->rollBack();
                        }
                        throw $e;
                    }
                } else {
                    QueryHelper::insert($this->pdo, 'atletas', $data, $persistCols);
                }
                $newAtletaId = (int) $this->pdo->lastInsertId();
                if (AuthService::isDelegadoAsociacion()) {
                    \FvdPortal\Services\FvdAdminRevisionPendienteService::marcarAltaDesdeDelegado($this->pdo, $newAtletaId);
                    if ($newAtletaId > 0 && $this->atletasHasColumn('estatus_verificacion')) {
                        $stPend = $this->pdo->prepare('UPDATE atletas SET estatus_verificacion = :st WHERE id = :id');
                        $stPend->execute([':st' => 'PENDIENTE', ':id' => $newAtletaId]);
                    }

                    $asocId = (int) ($data['asociacion'] ?? 0);
                    $asocNombre = '';
                    if ($asocId > 0) {
                        try {
                            $stAs = $this->pdo->prepare('SELECT nombre FROM asociaciones WHERE id = :id LIMIT 1');
                            $stAs->execute([':id' => $asocId]);
                            $asocNombre = trim((string) ($stAs->fetchColumn() ?: ''));
                        } catch (\Throwable $e) {
                            $asocNombre = '';
                        }
                    }
                    if ($asocNombre === '') {
                        $asocNombre = 'Una asociación';
                    }
                    $adminId = \FvdPortal\Services\NotificacionService::resolverAdminGeneralId($this->pdo);
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
                    QueryHelper::update($this->pdo, 'atletas', $data, $this->atletasPersistColumnsForDb(), 'id = :wid', [':wid' => $id]);
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

            QueryHelper::update($this->pdo, 'atletas', $data, $this->atletasPersistColumnsForDb(), 'id = :wid', [':wid' => $id]);
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
            throw new RuntimeException('Solo el administrador FVD puede activar o desactivar atletas.');
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

    /**
     * Texto de estatus para listados e informes.
     * Reglas de negocio: sin Nº FVD → Pendiente; estatus 9 → Inactivo; estatus 0 o 1 → Activo; 2 baja; 990 pendiente aprobación FVD.
     *
     * @param int      $e      Código `atletas.estatus`
     * @param int|null $numfvd Si se conoce `atletas.numfvd`, pasarlo para marcar pendiente cuando es 0
     */
    public static function atletasEstatusEtiqueta(int $e, ?int $numfvd = null): string
    {
        if ($numfvd !== null && (int) $numfvd === 0) {
            return 'Pendiente';
        }
        if ($e === 9) {
            return 'Inactivo';
        }
        if ($e === 0 || $e === self::ATLETA_ESTATUS_ACTIVO) {
            return 'Activo';
        }
        if ($e === self::ATLETA_ESTATUS_BAJA) {
            return 'Baja';
        }
        if ($e === self::ATLETA_ESTATUS_PENDIENTE_ADMIN) {
            return 'Pendiente aprobación FVD';
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
     * Listado paginado de `torneosact`. Filtros opcionales: texto, año calendario (derivado de fechator o alta),
     * o rango de fechas (inclusive) sobre la misma fecha de referencia.
     *
     * @return array{total:int,page:int,per_page:int,pages:int,rows:list<array<string,mixed>>}
     */
    public function torneosPaginateList(
        int $page,
        int $perPage,
        string $q,
        ?int $anioCalendario = null,
        ?string $fechaDesde = null,
        ?string $fechaHasta = null
    ): array {
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
        $fechaRefExpr = 'DATE(COALESCE(NULLIF(t.fechator, \'0000-00-00\'), t.created_at))';
        $periodo = '';
        $fd = $fechaDesde !== null ? trim($fechaDesde) : '';
        $fh = $fechaHasta !== null ? trim($fechaHasta) : '';
        if ($fd !== '' && $fh !== ''
            && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fd) === 1
            && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fh) === 1
            && strtotime($fd) !== false && strtotime($fh) !== false
            && strtotime($fd) <= strtotime($fh)
        ) {
            $periodo = ' AND ' . $fechaRefExpr . ' BETWEEN :fvd_td0 AND :fvd_td1 ';
            $params[':fvd_td0'] = $fd;
            $params[':fvd_td1'] = $fh;
        } elseif ($anioCalendario !== null && $anioCalendario >= 1990 && $anioCalendario <= 2100) {
            $periodo = ' AND YEAR(' . $fechaRefExpr . ') = :fvd_anio ';
            $params[':fvd_anio'] = $anioCalendario;
        }
        $countSql = 'SELECT COUNT(*) FROM torneosact t WHERE 1=1' . $search . $scopeAsoc . $periodo;
        if (!class_exists(\FvdPortal\Services\TorneoFinalizacionService::class, false)) {
            require_once __DIR__ . '/TorneoFinalizacionService.php';
        }
        $selFin = \FvdPortal\Services\TorneoFinalizacionService::columnaFinalizadoExiste($this->pdo) ? ', t.finalizado_en' : '';
        $dataSql = 'SELECT t.torneo, t.nombre, t.lugar, t.fechator, t.estatus, t.organizacion_id, o.nombre AS org_nombre' . $selFin . '
            FROM torneosact t
            LEFT JOIN asociaciones o ON t.organizacion_id = o.id
            WHERE 1=1' . $search . $scopeAsoc . $periodo . ' ORDER BY t.fechator DESC';

        return QueryHelper::paginate(
            $this->pdo,
            $countSql,
            $dataSql,
            $params,
            $page,
            $perPage
        );
    }

    /**
     * Añade a cada fila del listado: invitaciones_despachadas, despacho_enviados (filas en fvd_delegado_notif_torneo),
     * despacho_total_delegados (delegados activos con asociación).
     *
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    public function torneosListEnrichDespachoMonitor(array $rows): array
    {
        if ($rows === []) {
            return $rows;
        }
        $ids = [];
        foreach ($rows as $r) {
            $t = (int) ($r['torneo'] ?? 0);
            if ($t > 0) {
                $ids[] = $t;
            }
        }
        $ids = array_values(array_unique($ids));
        if ($ids === []) {
            return $rows;
        }
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $invMap = [];
        try {
            $st = $this->pdo->prepare(
                'SELECT torneo, COALESCE(invitaciones_despachadas, 0) AS invitaciones_despachadas FROM torneosact WHERE torneo IN (' . $ph . ')'
            );
            $st->execute($ids);
            while ($x = $st->fetch(PDO::FETCH_ASSOC)) {
                $invMap[(int) ($x['torneo'] ?? 0)] = (int) ($x['invitaciones_despachadas'] ?? 0);
            }
        } catch (Throwable $e) {
            error_log('[FvdAdminService] torneosListEnrichDespachoMonitor inv: ' . $e->getMessage());
        }
        $cntMap = [];
        try {
            $stn = $this->pdo->prepare(
                'SELECT torneo_id, COUNT(*) AS c FROM fvd_delegado_notif_torneo WHERE torneo_id IN (' . $ph . ') GROUP BY torneo_id'
            );
            $stn->execute($ids);
            while ($x = $stn->fetch(PDO::FETCH_ASSOC)) {
                $cntMap[(int) ($x['torneo_id'] ?? 0)] = (int) ($x['c'] ?? 0);
            }
        } catch (Throwable $e) {
            error_log('[FvdAdminService] torneosListEnrichDespachoMonitor count: ' . $e->getMessage());
        }
        $totalDel = 0;
        try {
            $stc = $this->pdo->query(
                'SELECT COUNT(*) FROM delegados WHERE activo = 1 AND asociacion_id IS NOT NULL AND asociacion_id > 0'
            );
            if ($stc !== false) {
                $totalDel = (int) $stc->fetchColumn();
            }
        } catch (Throwable $e) {
        }
        foreach ($rows as &$r) {
            $tid = (int) ($r['torneo'] ?? 0);
            $r['invitaciones_despachadas'] = $invMap[$tid] ?? 0;
            $r['despacho_enviados'] = $cntMap[$tid] ?? 0;
            $r['despacho_total_delegados'] = $totalDel;
        }
        unset($r);

        return $rows;
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
        \FvdPortal\Services\DelegadoTorneoVentanasService::ensureFechaLimiteCambiosColumn($this->pdo);

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
            if (in_array($col, ['organizacion_id', 'tipo', 'es_campeonato', 'clase', 'tiempo', 'puntos', 'rondas', 'estatus', 'ranking', 'pareclub', 'grupo_evento_id', 'apertura_anual'], true)) {
                $data[$col] = $v === '' || $v === null ? null : (int) $v;
            } elseif ($col === 'costotor') {
                $data[$col] = $v === '' ? null : (float) $v;
            } elseif ($col === 'fecha_limite_cambios') {
                $data[$col] = $v === '' || $v === null ? null : substr((string) $v, 0, 10);
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

        // Grupo de evento: solo para eventos marcados como campeonato (columna es_campeonato) o criterio legado tipo=2.
        if ($torneoId === null) {
            $data['grupo_evento_id'] = null;
        }
        if ($this->torneosactEsCampeonatoColumnExists()) {
            $data['es_campeonato'] = !empty($post['es_campeonato']) ? 1 : 0;
            if ((int) ($data['es_campeonato'] ?? 0) !== 1) {
                $data['grupo_evento_id'] = null;
            }
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
        if (!$this->torneosactEsCampeonatoColumnExists()) {
            unset($data['es_campeonato']);
        }

        $tg = (int) ($data['tipo'] ?? 1);
        if ($tg < 1 || $tg > 3) {
            $data['tipo'] = 1;
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
    public function torneosPostCreacionInvitacionesDelegados(int $newTorneoId): void
    {
        $role = AuthService::role();
        if ($newTorneoId <= 0 || ($role !== AuthService::ROLE_FVD_ADMIN && $role !== AuthService::ROLE_ASO_ADMIN)) {
            return;
        }
        try {
            $nomN = '';
            $stN = $this->pdo->prepare('SELECT nombre FROM torneosact WHERE torneo = :t LIMIT 1');
            $stN->execute([':t' => $newTorneoId]);
            $nomN = trim((string) ($stN->fetchColumn() ?: ''));
            NotificacionesDelegadosService::notificarTorneoNuevo($this->pdo, $newTorneoId, $nomN !== '' ? $nomN : null, null);
        } catch (Throwable $e) {
            error_log('[FvdAdminService] notificaciones_delegados torneo nuevo: ' . $e->getMessage());
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
            $this->despacharInvitacionesMasivas($newTorneoId);
        } catch (Throwable $e) {
            error_log('[FvdAdminService] despacharInvitacionesMasivas: ' . $e->getMessage());
        }
    }

    /**
     * Notificaciones en panel, tarjetas PDF, Telegram (si hay chat_id) y marca el torneo como invitaciones despachadas.
     */
    public function despacharInvitacionesMasivas(int $torneoId): void
    {
        if ($torneoId <= 0) {
            return;
        }
        $role = AuthService::role();
        if ($role !== AuthService::ROLE_FVD_ADMIN && $role !== AuthService::ROLE_ASO_ADMIN) {
            return;
        }
        $projRoot = dirname(__DIR__, 2);
        require_once $projRoot . '/fvdmasteradmin/fvd_notifier_bot.php';
        fvd_notifier_ensure_schema($this->pdo);
        \FvdPortal\Services\DelegadoTorneoNotifService::crearNotificacionesParaTorneo($this->pdo, $torneoId);
        \FvdPortal\Services\TorneoDelegadoTarjetaService::generarTarjetasParaTorneo($this->pdo, $torneoId, $this->projectRoot);
        fvd_notifier_despachar_telegram_para_torneo($this->pdo, $torneoId);
        try {
            $st = $this->pdo->prepare('UPDATE torneosact SET invitaciones_despachadas = 1 WHERE torneo = :t');
            $st->execute([':t' => $torneoId]);
        } catch (Throwable $e) {
            error_log('[FvdAdminService] invitaciones_despachadas: ' . $e->getMessage());
        }
    }

    /**
     * Tras vincular campeonatos al mismo grupo: Telegram (sin duplicar creación de filas) y marca torneos.
     *
     * @param list<int> $torneoIds
     */
    private function despacharTelegramYMarcaTrasVinculacionGrupo(array $torneoIds): void
    {
        $ids = [];
        foreach ($torneoIds as $x) {
            $n = (int) $x;
            if ($n > 0) {
                $ids[$n] = $n;
            }
        }
        $ids = array_values($ids);
        if ($ids === []) {
            return;
        }
        $projRoot = dirname(__DIR__, 2);
        require_once $projRoot . '/fvdmasteradmin/fvd_notifier_bot.php';
        fvd_notifier_ensure_schema($this->pdo);
        fvd_notifier_despachar_telegram_tras_grupo($this->pdo, $ids);
        $ph = implode(',', array_fill(0, count($ids), '?'));
        try {
            $st = $this->pdo->prepare('UPDATE torneosact SET invitaciones_despachadas = 1 WHERE torneo IN (' . $ph . ')');
            $st->execute($ids);
        } catch (Throwable $e) {
            error_log('[FvdAdminService] despacharTelegramYMarcaTrasVinculacionGrupo: ' . $e->getMessage());
        }
    }

    /**
     * Convocatoria nacional FVD: filas en `fvd_delegado_notif_torneo` (token), `fvd_notificaciones`, tarjetas PDF y Telegram con botón al panel embebido.
     *
     * @return array{delegados: int, notificaciones: int, telegram_enviados: int}
     */
    public function lanzarConvocatoriaNacional(int $torneoId): array
    {
        $this->torneosRequireFvdAdminForGestion();
        if ($torneoId <= 0) {
            throw new InvalidArgumentException('Torneo no válido.');
        }
        $row = $this->torneosFind($torneoId);
        if ($row === null) {
            throw new InvalidArgumentException('Torneo no encontrado.');
        }
        \FvdPortal\Services\DelegadoTorneoVentanasService::ensureFechaLimiteCambiosColumn($this->pdo);
        \FvdPortal\Services\DelegadoTorneoNotifService::crearNotificacionesParaTorneo($this->pdo, $torneoId);
        \FvdPortal\Services\TorneoDelegadoTarjetaService::generarTarjetasParaTorneo($this->pdo, $torneoId, $this->projectRoot);
        $nNotif = FvdNotificacionesService::sincronizarDesdeDelegadoNotifTorneo($this->pdo, $torneoId);
        $projRoot = dirname(__DIR__, 2);
        require_once $projRoot . '/fvdmasteradmin/fvd_notifier_bot.php';
        fvd_notifier_ensure_schema($this->pdo);
        $tg = fvd_notifier_convocatoria_nacional_telegram($this->pdo, $torneoId);
        try {
            $st = $this->pdo->prepare('UPDATE torneosact SET invitaciones_despachadas = 1 WHERE torneo = :t');
            $st->execute([':t' => $torneoId]);
        } catch (Throwable $e) {
            error_log('[FvdAdminService] lanzarConvocatoriaNacional marca: ' . $e->getMessage());
        }
        $stC = $this->pdo->prepare('SELECT COUNT(*) FROM fvd_delegado_notif_torneo WHERE torneo_id = :t');
        $stC->execute([':t' => $torneoId]);
        $nDel = (int) $stC->fetchColumn();

        return [
            'delegados' => $nDel,
            'notificaciones' => $nNotif,
            'telegram_enviados' => $tg,
        ];
    }

    /**
     * Tras el primer envío nacional: crea filas/token solo para delegados pendientes, sincroniza tarjetas y notifica por Telegram a esos delegados.
     *
     * @return array{insertados: int, notificaciones: int, telegram_enviados: int}
     */
    public function lanzarConvocatoriaPendientes(int $torneoId): array
    {
        $this->torneosRequireFvdAdminForGestion();
        if ($torneoId <= 0) {
            throw new InvalidArgumentException('Torneo no válido.');
        }
        $row = $this->torneosFind($torneoId);
        if ($row === null) {
            throw new InvalidArgumentException('Torneo no encontrado.');
        }
        $projRootEarly = dirname(__DIR__, 2);
        require_once $projRootEarly . '/fvdmasteradmin/fvd_notifier_bot.php';
        fvd_notifier_ensure_schema($this->pdo);
        try {
            $stM = $this->pdo->prepare('SELECT COALESCE(invitaciones_despachadas, 0) FROM torneosact WHERE torneo = :t');
            $stM->execute([':t' => $torneoId]);
            if ((int) $stM->fetchColumn() !== 1) {
                throw new RuntimeException('Primero debe enviarse el lote nacional; el monitor no marca el torneo como despachado.');
            }
        } catch (InvalidArgumentException $e) {
            throw $e;
        } catch (RuntimeException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new RuntimeException('No se pudo comprobar el estado del despacho.');
        }
        \FvdPortal\Services\DelegadoTorneoVentanasService::ensureFechaLimiteCambiosColumn($this->pdo);
        $falt = \FvdPortal\Services\DelegadoTorneoNotifService::crearNotificacionesFaltantesParaTorneo($this->pdo, $torneoId);
        $insertados = (int) ($falt['insertados'] ?? 0);
        $nuevos = $falt['nuevos_delegado_ids'] ?? [];
        $nNotif = 0;
        if ($insertados > 0) {
            \FvdPortal\Services\TorneoDelegadoTarjetaService::generarTarjetasParaTorneo($this->pdo, $torneoId, $this->projectRoot);
            $nNotif = FvdNotificacionesService::sincronizarDesdeDelegadoNotifTorneo($this->pdo, $torneoId);
        }
        $projRoot = $projRootEarly;
        $tg = 0;
        if ($nuevos !== []) {
            $tg = fvd_notifier_convocatoria_pendientes_telegram($this->pdo, $torneoId, $nuevos);
        }

        return [
            'insertados' => $insertados,
            'notificaciones' => $nNotif,
            'telegram_enviados' => $tg,
        ];
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

    public function torneosactEsCampeonatoColumnExists(): bool
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        $cache = false;
        try {
            $st = $this->pdo->query(
                "SELECT COUNT(*) FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'torneosact' AND COLUMN_NAME = 'es_campeonato'"
            );
            if ($st !== false) {
                $cache = ((int) $st->fetchColumn()) > 0;
            }
        } catch (Throwable $e) {
            $cache = false;
        }
        if (!$cache) {
            try {
                $this->pdo->query('SELECT es_campeonato FROM torneosact LIMIT 0');
                $cache = true;
            } catch (Throwable $e2) {
                $cache = false;
            }
        }

        return $cache;
    }

    /**
     * Condición SQL: torneo marcado como campeonato (agrupa categorías / grupo_evento_id).
     * Si no existe la columna es_campeonato, se mantiene el criterio legado tipo = 2 (puede coincidir con Femenino=2).
     */
    public function torneoSqlConditionEsCampeonato(string $tableAlias = 't'): string
    {
        if ($this->torneosactEsCampeonatoColumnExists()) {
            return 'COALESCE(' . $tableAlias . '.es_campeonato, 0) = 1';
        }

        return '(' . $tableAlias . '.tipo = 2)';
    }

    /**
     * Género del evento según torneosact.tipo: 1=M, 2=F, 3=Mixto (sin restricción).
     *
     * @return 'M'|'F'|null
     */
    public function torneoFiltroSexoEsperadoDesdeTipoTorneo(?int $tipoTorneo): ?string
    {
        $ti = (int) $tipoTorneo;
        if ($ti === 1) {
            return 'M';
        }
        if ($ti === 2) {
            return 'F';
        }
        if ($ti === 3) {
            return null;
        }

        return null;
    }

    /**
     * Restricción SQL sobre atletas según género del torneo (tipo 1=M, 2=F; 3=Mixto sin filtro).
     * Si {@see $torneoId} no tiene tipo definido, intenta inferirlo del nombre del torneo.
     *
     * @param string $alias Alias de la tabla atletas en la consulta (p. ej. "a")
     */
    public function sqlAtletasFiltroSexoSegunTorneoTipo(int $torneoId, string $alias = 'a'): string
    {
        if ($torneoId <= 0) {
            return '';
        }
        $alias = preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $alias) ? $alias : 'a';
        try {
            $stn = $this->pdo->prepare('SELECT nombre, tipo FROM torneosact WHERE torneo = :t LIMIT 1');
            $stn->execute([':t' => $torneoId]);
            $trow = $stn->fetch(PDO::FETCH_ASSOC) ?: [];
            $tipoTor = isset($trow['tipo']) ? (int) $trow['tipo'] : 0;
            if ($tipoTor === 3) {
                return '';
            }
            $esp = $this->torneoFiltroSexoEsperadoDesdeTipoTorneo($tipoTor > 0 ? $tipoTor : null);
            if ($esp === null) {
                $nomT = (string) ($trow['nombre'] ?? '');
                $esp = $this->torneoFiltroSexoEsperadoDesdeNombre($nomT);
            }
            if ($esp === 'M') {
                return ' AND (COALESCE(' . $alias . '.sexo, 0) = 1 OR UPPER(TRIM(CAST(' . $alias . '.sexo AS CHAR))) IN (\'M\', \'MASCULINO\')) ';
            }
            if ($esp === 'F') {
                return ' AND (COALESCE(' . $alias . '.sexo, 0) = 2 OR UPPER(TRIM(CAST(' . $alias . '.sexo AS CHAR))) IN (\'F\', \'FEMENINO\', \'FEMENINA\')) ';
            }
        } catch (Throwable $e) {
            error_log('[FvdAdminService::sqlAtletasFiltroSexoSegunTorneoTipo] ' . $e->getMessage());
        }

        return '';
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
        $orderDia = $this->torneosactEsCampeonatoColumnExists()
            ? 'COALESCE(t.es_campeonato,0) DESC, t.tipo ASC, t.nombre ASC'
            : 't.tipo DESC, t.nombre ASC';
        $st = $this->pdo->prepare(
            'SELECT t.torneo, t.nombre, t.lugar, DATE(t.fechator) AS fechator, t.tipo, t.grupo_evento_id,
                    t.organizacion_id, o.nombre AS org_nombre
                    ' . ($this->torneosactEsCampeonatoColumnExists() ? ', t.es_campeonato' : '') . '
             FROM torneosact t
             LEFT JOIN asociaciones o ON o.id = t.organizacion_id
             WHERE t.fechator IS NOT NULL AND DATE(t.fechator) = :d
             ORDER BY ' . $orderDia
        );
        $st->execute([':d' => $ymd]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Eventos (todos los tipos) para relación por grupo: próximos + en proceso (ventana reciente).
     * Use {@see torneosRelacionGrupoCandidatosPorFiltro()} para otros alcances.
     *
     * @return list<array<string, mixed>>
     */
    public function torneosRelacionGrupoCandidatosFuturos(): array
    {
        return $this->torneosRelacionGrupoCandidatosPorFiltro('activos');
    }

    /**
     * Listado para relación por grupo según vista temporal.
     *
     * @param 'activos'|'todos'|'proximos'|'en_proceso'|'realizados'|'por_realizar' $filtro
     *     activos = por realizar + en proceso (cualquier fila en torneosact con fecha en el rango).
     *     por_realizar = proximos.
     *     todos = catálogo completo en `torneosact`, sin filtrar por fecha.
     *
     * @return list<array<string, mixed>>
     */
    public function torneosRelacionGrupoCandidatosPorFiltro(string $filtro): array
    {
        if (!$this->torneosactGrupoEventoColumnExists()) {
            return [];
        }
        $f = strtolower(trim($filtro));
        if ($f === 'por_realizar') {
            $f = 'proximos';
        }
        $allowed = ['activos', 'todos', 'proximos', 'en_proceso', 'realizados'];
        if (!in_array($f, $allowed, true)) {
            $f = 'activos';
        }
        $diasAtras = max(1, (int) self::RELACION_GRUPO_EN_PROCESO_MAX_DIAS_ATRAS);

        if ($f === 'todos') {
            $whereClause = '1=1';
            $orderBy = 't.torneo DESC';
        } else {
            if ($f === 'proximos') {
                $dateSql = 't.fechator IS NOT NULL AND DATE(t.fechator) >= CURDATE()';
            } elseif ($f === 'en_proceso') {
                $dateSql = 't.fechator IS NOT NULL AND DATE(t.fechator) < CURDATE() AND DATE(t.fechator) >= DATE_SUB(CURDATE(), INTERVAL ' . $diasAtras . ' DAY)';
            } elseif ($f === 'realizados') {
                $dateSql = 't.fechator IS NOT NULL AND DATE(t.fechator) < DATE_SUB(CURDATE(), INTERVAL ' . $diasAtras . ' DAY)';
            } else {
                $dateSql = 't.fechator IS NOT NULL AND (DATE(t.fechator) >= CURDATE() OR (DATE(t.fechator) < CURDATE() AND DATE(t.fechator) >= DATE_SUB(CURDATE(), INTERVAL ' . $diasAtras . ' DAY)))';
            }
            $whereClause = $dateSql;
            if ($f === 'proximos') {
                $orderBy = 'DATE(t.fechator) ASC, t.nombre ASC';
            } elseif ($f === 'en_proceso' || $f === 'realizados') {
                $orderBy = 'DATE(t.fechator) DESC, t.nombre ASC';
            } else {
                $orderBy = '(DATE(t.fechator) >= CURDATE()) DESC, CASE WHEN DATE(t.fechator) >= CURDATE() THEN DATE(t.fechator) END ASC, CASE WHEN DATE(t.fechator) < CURDATE() THEN DATE(t.fechator) END DESC, t.nombre ASC';
            }
        }

        try {
            $extraSel = $this->torneosactEsCampeonatoColumnExists() ? ', t.es_campeonato' : '';
            $sql = 'SELECT t.torneo, t.nombre, t.lugar, DATE(t.fechator) AS fechator, t.tipo, t.grupo_evento_id,
                        t.organizacion_id, o.nombre AS org_nombre' . $extraSel . '
                 FROM torneosact t
                 LEFT JOIN asociaciones o ON o.id = t.organizacion_id
                 WHERE ' . $whereClause . '
                 ORDER BY ' . $orderBy;
            $st = $this->pdo->query($sql);
        } catch (Throwable $e) {
            error_log('[FvdAdminService::torneosRelacionGrupoCandidatosPorFiltro] ' . $e->getMessage());

            return [];
        }

        return $st ? ($st->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
    }

    /** Máxima diferencia en días entre fechas de inicio de campeonatos vinculados (alerta operativa). */
    public const RELACION_GRUPO_MAX_DIAS_ENTRE_FECHAS = 7;

    /**
     * Ventana hacia atrás desde hoy: eventos con fecha de inicio ya pasada pero reciente
     * se listan como «en proceso» en la pantalla de relación por grupo.
     */
    public const RELACION_GRUPO_EN_PROCESO_MAX_DIAS_ATRAS = 120;

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
     * @return int|null ID asociación si el usuario puede vincular en nombre del club (delegado o aso_admin); null si debe ser solo admin FVD.
     */
    private function torneosRelacionUsuarioClubConAsociacion(): ?int
    {
        if (AuthService::isDelegadoAsociacion()) {
            $a = AuthService::idAsociacion();

            return ($a !== null && $a > 0) ? $a : null;
        }
        if (AuthService::role() === AuthService::ROLE_ASO_ADMIN) {
            $a = AuthService::idAsociacion();

            return ($a !== null && $a > 0) ? $a : null;
        }

        return null;
    }

    /**
     * Delegado / admin de club: solo campeonatos que organiza o con convocatoria a su asociación.
     *
     * @param list<int> $ids
     */
    private function torneosRelacionDelegadoAssertTorneosEnAlcance(int $asociacionId, array $ids): void
    {
        if ($ids === []) {
            return;
        }
        $n = count($ids);
        $ph = implode(',', array_fill(0, $n, '?'));
        $condCamp = $this->torneoSqlConditionEsCampeonato('t');
        if ($this->torneosConvocatoriaTableExists()) {
            $sql = 'SELECT COUNT(DISTINCT t.torneo) AS c FROM torneosact t
                LEFT JOIN torneo_convocatoria_asoc c ON c.torneo_id = t.torneo AND c.asociacion_id = ?
                WHERE t.torneo IN (' . $ph . ') AND ' . $condCamp . '
                AND (t.organizacion_id = ? OR c.id IS NOT NULL)';
            $st = $this->pdo->prepare($sql);
            $st->execute(array_merge([$asociacionId], $ids, [$asociacionId]));
        } else {
            $sql = 'SELECT COUNT(*) AS c FROM torneosact t WHERE t.torneo IN (' . $ph . ') AND ' . $condCamp . ' AND t.organizacion_id = ?';
            $st = $this->pdo->prepare($sql);
            $st->execute(array_merge($ids, [$asociacionId]));
        }
        $ok = (int) $st->fetchColumn();
        if ($ok !== $n) {
            throw new InvalidArgumentException(
                'Solo puede vincular campeonatos de su asociación o con convocatoria para su club.'
            );
        }
    }

    /**
     * Campeonatos (es_campeonato / legado tipo=2) disponibles para el maestro de relaciones: organiza o tiene convocatoria.
     *
     * @return list<array<string, mixed>>
     */
    public function relacionTorneosMasterCandidatos(int $asociacionId): array
    {
        if ($asociacionId <= 0 || !$this->torneosactGrupoEventoColumnExists()) {
            return [];
        }
        $condCamp = $this->torneoSqlConditionEsCampeonato('t');
        $extraSel = $this->torneosactEsCampeonatoColumnExists() ? ', t.es_campeonato' : '';
        try {
            if ($this->torneosConvocatoriaTableExists()) {
                $st = $this->pdo->prepare(
                    'SELECT DISTINCT t.torneo, t.nombre, DATE(t.fechator) AS fechator, t.estatus, t.grupo_evento_id, t.tipo' . $extraSel . '
                     FROM torneosact t
                     LEFT JOIN torneo_convocatoria_asoc c ON c.torneo_id = t.torneo AND c.asociacion_id = :a1
                     WHERE ' . $condCamp . ' AND (t.organizacion_id = :a2 OR c.id IS NOT NULL)
                     ORDER BY fechator DESC, t.nombre ASC'
                );
                $st->execute([':a1' => $asociacionId, ':a2' => $asociacionId]);
            } else {
                $st = $this->pdo->prepare(
                    'SELECT t.torneo, t.nombre, DATE(t.fechator) AS fechator, t.estatus, t.grupo_evento_id, t.tipo' . $extraSel . '
                     FROM torneosact t
                     WHERE ' . $condCamp . ' AND t.organizacion_id = :a
                     ORDER BY fechator DESC, t.nombre ASC'
                );
                $st->execute([':a' => $asociacionId]);
            }

            return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            error_log('[FvdAdminService::relacionTorneosMasterCandidatos] ' . $e->getMessage());

            return [];
        }
    }

    /**
     * Campeonatos ya con grupo compartido donde la asociación participa (cabecera de contexto).
     *
     * @return list<array<string, mixed>>
     */
    public function relacionTorneosMasterRelacionados(int $asociacionId): array
    {
        if ($asociacionId <= 0 || !$this->torneosactGrupoEventoColumnExists()) {
            return [];
        }
        $condCamp = $this->torneoSqlConditionEsCampeonato('t');
        $condCamp2 = $this->torneoSqlConditionEsCampeonato('t2');
        try {
            if ($this->torneosConvocatoriaTableExists()) {
                $st = $this->pdo->prepare(
                    'SELECT DISTINCT t.torneo, t.nombre, t.grupo_evento_id
                     FROM torneosact t
                     WHERE ' . $condCamp . ' AND COALESCE(t.grupo_evento_id, 0) > 0
                     AND t.grupo_evento_id IN (
                         SELECT DISTINCT t2.grupo_evento_id
                         FROM torneosact t2
                         LEFT JOIN torneo_convocatoria_asoc c ON c.torneo_id = t2.torneo AND c.asociacion_id = :a1
                         WHERE (t2.organizacion_id = :a2 OR c.id IS NOT NULL)
                           AND COALESCE(t2.grupo_evento_id, 0) > 0
                           AND ' . $condCamp2 . '
                     )
                     ORDER BY t.nombre ASC'
                );
                $st->execute([':a1' => $asociacionId, ':a2' => $asociacionId]);
            } else {
                $st = $this->pdo->prepare(
                    'SELECT DISTINCT t.torneo, t.nombre, t.grupo_evento_id
                     FROM torneosact t
                     WHERE ' . $condCamp . ' AND COALESCE(t.grupo_evento_id, 0) > 0
                     AND t.grupo_evento_id IN (
                         SELECT DISTINCT t2.grupo_evento_id FROM torneosact t2
                         WHERE t2.organizacion_id = :a AND COALESCE(t2.grupo_evento_id, 0) > 0
                           AND ' . $condCamp2 . '
                     )
                     ORDER BY t.nombre ASC'
                );
                $st->execute([':a' => $asociacionId]);
            }

            return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            error_log('[FvdAdminService::relacionTorneosMasterRelacionados] ' . $e->getMessage());

            return [];
        }
    }

    /**
     * Asigna el mismo grupo_evento_id a varios eventos (torneos / campeonatos) y guarda etiqueta en la tabla maestra.
     * Si el nombre nominal va vacío, se usa el literal #N (N = grupo_evento_id).
     * Evita repetir el proceso si todos ya comparten el mismo grupo; permite incorporar eventos sin grupo
     * a un grupo ya existente (misma selección, distintos grupos → error).
     *
     * @param list<mixed> $torneoIds
     */
    public function torneosRelacionGrupoAplicar(array $torneoIds, string $nombreNominalCampeonato = ''): int
    {
        $clubUser = $this->torneosRelacionUsuarioClubConAsociacion();
        if ($clubUser === null) {
            $this->torneosRequireFvdAdminForGestion();
        }
        if (!$this->torneosactGrupoEventoColumnExists()) {
            throw new RuntimeException('No existe la columna grupo_evento_id en torneosact.');
        }
        $nombreNominalCampeonato = trim($nombreNominalCampeonato);
        if ($nombreNominalCampeonato !== '' && mb_strlen($nombreNominalCampeonato) > 255) {
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
            throw new InvalidArgumentException('Seleccione al menos dos eventos para vincular.');
        }
        $this->torneosRelacionGrupoValidarDispersionFechas($ids);
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $selGr = 'torneo, tipo, grupo_evento_id';
        if ($this->torneosactEsCampeonatoColumnExists()) {
            $selGr .= ', es_campeonato';
        }
        $st = $this->pdo->prepare('SELECT ' . $selGr . ' FROM torneosact WHERE torneo IN (' . $ph . ')');
        $st->execute($ids);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        if (count($rows) !== count($ids)) {
            throw new InvalidArgumentException('Uno o más eventos no existen.');
        }
        if ($clubUser !== null) {
            $this->torneosRelacionDelegadoAssertTorneosEnAlcance($clubUser, $ids);
        }
        $grupoPorId = [];
        foreach ($rows as $r) {
            $tid = (int) ($r['torneo'] ?? 0);
            $g = (int) ($r['grupo_evento_id'] ?? 0);
            $grupoPorId[$tid] = $g > 0 ? $g : null;
        }

        $gidsNoNulos = [];
        foreach ($ids as $tid) {
            $g = $grupoPorId[$tid] ?? null;
            if ($g !== null && $g > 0) {
                $gidsNoNulos[] = $g;
            }
        }
        $gidsUnicos = array_values(array_unique($gidsNoNulos));
        if (count($gidsUnicos) > 1) {
            throw new InvalidArgumentException(
                'Los eventos seleccionados pertenecen a grupos de evento distintos. '
                . 'No se puede unificar en un solo paso; revise la selección o corrija grupos en la base de datos si fue un error.'
            );
        }

        if (count($gidsUnicos) === 1) {
            $gidExistente = $gidsUnicos[0];
            $todosIgualesAlMismoGrupo = true;
            foreach ($ids as $tid) {
                $g = $grupoPorId[$tid] ?? null;
                if ($g === null || $g !== $gidExistente) {
                    $todosIgualesAlMismoGrupo = false;
                    break;
                }
            }
            if ($todosIgualesAlMismoGrupo) {
                throw new InvalidArgumentException(
                    'Estos eventos ya están vinculados al mismo grupo de evento (#' . $gidExistente . '). '
                    . 'No es necesario repetir el proceso; la relación se mantiene.'
                );
            }
        }

        $this->campeonatoGrupoEnsureTable();
        $insMaestro = $this->pdo->prepare(
            'INSERT INTO fvd_campeonato_grupo (grupo_evento_id, nombre_nominal) VALUES (:g, :n)
             ON DUPLICATE KEY UPDATE nombre_nominal = VALUES(nombre_nominal)'
        );

        if (count($gidsUnicos) === 1) {
            $gidFinal = $gidsUnicos[0];
            $nomMaestro = $nombreNominalCampeonato !== ''
                ? $nombreNominalCampeonato
                : ('#' . (string) $gidFinal);
            $this->pdo->beginTransaction();
            try {
                $upd = $this->pdo->prepare('UPDATE torneosact SET grupo_evento_id = :g WHERE torneo = :id');
                foreach ($ids as $tid) {
                    $upd->execute([':g' => $gidFinal, ':id' => $tid]);
                }
                $insMaestro->execute([':g' => $gidFinal, ':n' => $nomMaestro]);
                $this->pdo->commit();
            } catch (Throwable $e) {
                if ($this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }
                error_log('[FvdAdminService::torneosRelacionGrupoAplicar] merge: ' . $e->getMessage());
                throw new RuntimeException('No se pudo incorporar los eventos al grupo existente.');
            }
            try {
                \FvdPortal\Services\DelegadoTorneoNotifService::sincronizarInvitacionesTrasVincularGrupo($this->pdo, $ids);
            } catch (Throwable $e) {
                error_log('[FvdAdminService::torneosRelacionGrupoAplicar] notif sync: ' . $e->getMessage());
            }
            try {
                $this->despacharTelegramYMarcaTrasVinculacionGrupo($ids);
            } catch (Throwable $e) {
                error_log('[FvdAdminService::torneosRelacionGrupoAplicar] telegram/marca: ' . $e->getMessage());
            }

            return $gidFinal;
        }

        $stMax = $this->pdo->query('SELECT COALESCE(MAX(grupo_evento_id), 0) FROM torneosact');
        $next = (int) $stMax->fetchColumn() + 1;
        if ($next <= 0) {
            $next = 1;
        }
        $nomMaestro = $nombreNominalCampeonato !== ''
            ? $nombreNominalCampeonato
            : ('#' . (string) $next);
        $this->pdo->beginTransaction();
        try {
            $upd = $this->pdo->prepare('UPDATE torneosact SET grupo_evento_id = :g WHERE torneo = :id');
            foreach ($ids as $tid) {
                $upd->execute([':g' => $next, ':id' => $tid]);
            }
            $insMaestro->execute([':g' => $next, ':n' => $nomMaestro]);
            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log('[FvdAdminService::torneosRelacionGrupoAplicar] nuevo grupo: ' . $e->getMessage());
            throw new RuntimeException('No se pudo crear el grupo de evento. Compruebe que exista la tabla fvd_campeonato_grupo.');
        }

        try {
            \FvdPortal\Services\DelegadoTorneoNotifService::sincronizarInvitacionesTrasVincularGrupo($this->pdo, $ids);
        } catch (Throwable $e) {
            error_log('[FvdAdminService::torneosRelacionGrupoAplicar] notif sync: ' . $e->getMessage());
        }
        try {
            $this->despacharTelegramYMarcaTrasVinculacionGrupo($ids);
        } catch (Throwable $e) {
            error_log('[FvdAdminService::torneosRelacionGrupoAplicar] telegram/marca: ' . $e->getMessage());
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
            // 1) Preferir grupo_evento_id cuando el parámetro coincide con un campeonato (evita ambigüedad
            //    torneo_id == número y grupo_evento_id == mismo número → sin bucles en redirecciones canónicas).
            $stGrupo = $this->pdo->prepare('SELECT COUNT(*) FROM torneosact WHERE grupo_evento_id = :g');
            $stGrupo->execute([':g' => $param]);
            if ((int) $stGrupo->fetchColumn() > 0) {
                return $param;
            }
            // 2) Si no hay grupo con ese id, interpretar el parámetro como torneo_id.
            $st = $this->pdo->prepare('SELECT grupo_evento_id FROM torneosact WHERE torneo = :p LIMIT 1');
            $st->execute([':p' => $param]);
            $g = $st->fetchColumn();
            if ($g !== false && $g !== null && (int) $g > 0) {
                return (int) $g;
            }
        } catch (Throwable $e) {
            error_log('[FvdAdminService::resolverGrupoDesdeCampeonatoParam] ' . $e->getMessage());
        }

        return null;
    }

    /**
     * Restringe listados del mismo {@see grupo_evento_id} al género (tipo 1–3) y modalidad (clase 1–3)
     * del torneo de contexto, solo si ese torneo pertenece al mismo grupo.
     *
     * @return array{sql:string, params:array<string, int>}
     */
    private function filtroSqlTorneoContextoMismoGrupo(int $contextTorneoId, int $grupoEventoId): array
    {
        if ($contextTorneoId <= 0 || $grupoEventoId <= 0) {
            return ['sql' => '', 'params' => []];
        }
        $gCtx = $this->torneoGrupoEventoId($contextTorneoId);
        if ($gCtx === null || $gCtx !== $grupoEventoId) {
            return ['sql' => '', 'params' => []];
        }
        try {
            $st = $this->pdo->prepare(
                'SELECT COALESCE(tipo, 0) AS tipo, COALESCE(clase, 0) AS clase FROM torneosact WHERE torneo = :c LIMIT 1'
            );
            $st->execute([':c' => $contextTorneoId]);
            $row = $st->fetch(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            return ['sql' => '', 'params' => []];
        }
        $sql = '';
        $params = [];
        $tipo = (int) ($row['tipo'] ?? 0);
        $clase = (int) ($row['clase'] ?? 0);
        if ($tipo >= 1 && $tipo <= 3) {
            $sql .= ' AND t.tipo = :filt_ctx_tipo';
            $params[':filt_ctx_tipo'] = $tipo;
        }
        if ($clase >= 1 && $clase <= 3) {
            $sql .= ' AND t.clase = :filt_ctx_clase';
            $params[':filt_ctx_clase'] = $clase;
        }

        return ['sql' => $sql, 'params' => $params];
    }

    /**
     * Etiqueta corta de género para chips (torneosact.tipo: 1=M, 2=F, 3=Mixto).
     */
    public function delegadoEtiquetaGeneroTipo(int $tipo): string
    {
        if ($tipo === 1) {
            return 'M';
        }
        if ($tipo === 2) {
            return 'F';
        }
        if ($tipo === 3) {
            return 'Mixto';
        }

        return '';
    }

    /**
     * Indica si el delegado puede fijar ese torneo como contexto (convocatoria invitada, o club con atletas en el torneo; no finalizado).
     */
    public function delegadoPuedeFijarTorneoContext(int $asociacionId, int $torneoId): bool
    {
        if ($torneoId <= 0 || $asociacionId <= 0) {
            return false;
        }
        try {
            $c1 = $this->pdo->prepare(
                'SELECT 1 FROM torneo_convocatoria_asoc WHERE asociacion_id = :a AND torneo_id = :t AND invitado_en IS NOT NULL LIMIT 1'
            );
            $c1->execute([':a' => $asociacionId, ':t' => $torneoId]);
            $puede = (bool) $c1->fetchColumn();
            if (!$puede) {
                $c2 = $this->pdo->prepare('SELECT 1 FROM atletas WHERE asociacion = :a AND torneo_id = :t LIMIT 1');
                $c2->execute([':a' => $asociacionId, ':t' => $torneoId]);
                $puede = (bool) $c2->fetchColumn();
            }
            if ($puede && !class_exists(\FvdPortal\Services\TorneoFinalizacionService::class, false)) {
                require_once __DIR__ . '/TorneoFinalizacionService.php';
            }
            if ($puede && \FvdPortal\Services\TorneoFinalizacionService::columnaFinalizadoExiste($this->pdo)) {
                $chkFin = $this->pdo->prepare('SELECT finalizado_en FROM torneosact WHERE torneo = :t LIMIT 1');
                $chkFin->execute([':t' => $torneoId]);
                $finV = $chkFin->fetchColumn();
                if ($finV !== false && $finV !== null && trim((string) $finV) !== '') {
                    return false;
                }
            }

            return $puede;
        } catch (Throwable $e) {
            error_log('[FvdAdminService::delegadoPuedeFijarTorneoContext] ' . $e->getMessage());

            return false;
        }
    }

    /**
     * Lista del selector de contexto delegado: convocatoria + ramas de campeonato del mismo grupo,
     * filtradas por género del torneo en sesión (tipo 1–3). Incluye {@see tipo} para la UI.
     *
     * @return list<array<string, mixed>>
     */
    public function delegadoListaTorneosParaStrip(int $asociacionId, ?int $contextTorneoId = null): array
    {
        if ($asociacionId <= 0) {
            return [];
        }
        $ctx = $contextTorneoId;
        if ($ctx === null || $ctx <= 0) {
            $c = AuthService::delegadoTorneoContextId();
            $ctx = ($c !== null && $c > 0) ? (int) $c : 0;
        }
        $delegadoTipoFiltroAsociados = null;
        if ($ctx > 0) {
            try {
                $stTipoCtx = $this->pdo->prepare('SELECT COALESCE(tipo, 0) AS tipo FROM torneosact WHERE torneo = :t LIMIT 1');
                $stTipoCtx->execute([':t' => $ctx]);
                $tpx = (int) ($stTipoCtx->fetchColumn() ?: 0);
                if ($tpx >= 1 && $tpx <= 3) {
                    $delegadoTipoFiltroAsociados = $tpx;
                }
            } catch (Throwable $e) {
                /* ignorar */
            }
        }
        $andTipoTorneoAsociado = ($delegadoTipoFiltroAsociados !== null)
            ? (' AND (t.tipo = ' . (int) $delegadoTipoFiltroAsociados . ')')
            : '';
        if (!class_exists(\FvdPortal\Services\TorneoFinalizacionService::class, false)) {
            require_once __DIR__ . '/TorneoFinalizacionService.php';
        }
        try {
            $sqlAbiertoLista = \FvdPortal\Services\TorneoFinalizacionService::columnaFinalizadoExiste($this->pdo)
                ? ' AND (t.finalizado_en IS NULL) '
                : '';
            $sqlLista = 'SELECT DISTINCT t.torneo AS torneo_id,
                COALESCE(NULLIF(TRIM(t.nombre), \'\'), CONCAT(\'Torneo #\', t.torneo)) AS torneo_nombre,
                COALESCE(NULLIF(t.grupo_evento_id, 0), 0) AS grupo_evento_id,
                COALESCE(t.tipo, 0) AS tipo
            FROM torneosact t
            WHERE (t.torneo IN (
                    SELECT c.torneo_id FROM torneo_convocatoria_asoc c
                    WHERE c.asociacion_id = :a AND c.invitado_en IS NOT NULL
                )
                OR (
                    COALESCE(NULLIF(t.grupo_evento_id, 0), 0) > 0
                    AND ' . $this->torneoSqlConditionEsCampeonato('t') . '
                    AND EXISTS (
                        SELECT 1
                        FROM torneo_convocatoria_asoc c_inv
                        INNER JOIN torneosact t_inv ON t_inv.torneo = c_inv.torneo_id
                        WHERE c_inv.asociacion_id = :a2
                            AND c_inv.invitado_en IS NOT NULL
                            AND ' . $this->torneoSqlConditionEsCampeonato('t_inv') . '
                            AND COALESCE(NULLIF(t_inv.grupo_evento_id, 0), 0) = COALESCE(NULLIF(t.grupo_evento_id, 0), 0)
                            AND (t_inv.fechator <=> t.fechator)
                    )' . $andTipoTorneoAsociado . '
                ))' . $sqlAbiertoLista . '
            ORDER BY t.fechator DESC, t.torneo DESC';
            $stl = $this->pdo->prepare($sqlLista);
            $stl->execute([':a' => $asociacionId, ':a2' => $asociacionId]);

            return $stl->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            error_log('[FvdAdminService::delegadoListaTorneosParaStrip] ' . $e->getMessage());
        }
        try {
            $sqlAbiertoFb = \FvdPortal\Services\TorneoFinalizacionService::columnaFinalizadoExiste($this->pdo)
                ? ' AND (t.finalizado_en IS NULL) '
                : '';
            $stlFb = $this->pdo->prepare(
                'SELECT t.torneo AS torneo_id,
                    COALESCE(NULLIF(TRIM(t.nombre), \'\'), CONCAT(\'Torneo #\', t.torneo)) AS torneo_nombre,
                    COALESCE(NULLIF(t.grupo_evento_id, 0), 0) AS grupo_evento_id,
                    COALESCE(t.tipo, 0) AS tipo
                 FROM torneosact t
                 WHERE t.torneo IN (
                    SELECT c.torneo_id FROM torneo_convocatoria_asoc c
                    WHERE c.asociacion_id = :a AND c.invitado_en IS NOT NULL
                 )' . $sqlAbiertoFb . '
                 ORDER BY t.fechator DESC, t.torneo DESC'
            );
            $stlFb->execute([':a' => $asociacionId]);

            return $stlFb->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e2) {
            return [];
        }
    }

    /**
     * Torneos del mismo campeonato (grupo) con convocatoria para la asociación.
     * Con {@see $contextTorneoId}, se acotan al mismo género/modalidad que ese torneo si pertenece al grupo.
     *
     * @return list<array<string, mixed>>
     */
    public function torneosPorGrupoCampeonato(int $asociacionId, int $grupoEventoId, ?int $contextTorneoId = null): array
    {
        if ($asociacionId <= 0 || $grupoEventoId <= 0 || !$this->torneosactGrupoEventoColumnExists()) {
            return [];
        }
        try {
            $filt = $this->filtroSqlTorneoContextoMismoGrupo(
                $contextTorneoId !== null && $contextTorneoId > 0 ? $contextTorneoId : 0,
                $grupoEventoId
            );
            $sql = 'SELECT t.torneo, t.nombre, t.lugar, DATE(t.fechator) AS fechator, t.tipo, t.clase
                FROM torneosact t
                INNER JOIN torneo_convocatoria_asoc c ON c.torneo_id = t.torneo AND c.asociacion_id = :a
                WHERE t.grupo_evento_id = :g' . $filt['sql'] . '
                ORDER BY t.tipo ASC, t.nombre ASC';
            $st = $this->pdo->prepare($sql);
            $params = array_merge([':a' => $asociacionId, ':g' => $grupoEventoId], $filt['params']);
            $st->execute($params);

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
        $n = function_exists('mb_strtolower')
            ? mb_strtolower($nombre, 'UTF-8')
            : strtolower($nombre);
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
        $st = $this->pdo->prepare('SELECT nombre, tipo FROM torneosact WHERE torneo = :t LIMIT 1');
        $st->execute([':t' => $torneoId]);
        $tmeta = $st->fetch(PDO::FETCH_ASSOC) ?: [];
        $nom = (string) ($tmeta['nombre'] ?? '');
        $tipoT = isset($tmeta['tipo']) ? (int) $tmeta['tipo'] : 0;
        $espTipo = $this->torneoFiltroSexoEsperadoDesdeTipoTorneo($tipoT > 0 ? $tipoT : null);
        if ($espTipo === null && $tipoT === 3) {
            return $rows;
        }
        if ($espTipo !== null) {
            $out = [];
            foreach ($rows as $row) {
                if ($this->atletaCoincideSexoEsperado($row, $espTipo)) {
                    $out[] = $row;
                }
            }

            return $out;
        }
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
     * @param 'M'|'F' $esperado
     */
    private function atletaCoincideSexoEsperado(array $atletaRow, string $esperado): bool
    {
        $sx = $atletaRow['sexo'] ?? null;
        if (is_string($sx)) {
            $sx = strtoupper(trim($sx));
        }
        if ($esperado === 'F') {
            return $sx === 'F' || $sx === '2' || (int) $sx === 2;
        }
        if ($esperado === 'M') {
            return $sx === 'M' || $sx === '1' || (int) $sx === 1;
        }

        return true;
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
            $filt = $this->filtroSqlTorneoContextoMismoGrupo($contextTorneoId, $grupo);
            $sql = 'SELECT t.torneo, t.nombre, t.lugar, DATE(t.fechator) AS fechator, t.tipo, t.clase
                FROM torneosact t
                INNER JOIN torneo_convocatoria_asoc c ON c.torneo_id = t.torneo AND c.asociacion_id = :a
                WHERE t.grupo_evento_id = :g' . $filt['sql'] . '
                ORDER BY (t.torneo = :ctx) DESC, t.tipo ASC, t.nombre ASC';
            $st = $this->pdo->prepare($sql);
            $params = array_merge([':a' => $asociacionId, ':g' => $grupo, ':ctx' => $contextTorneoId], $filt['params']);
            $st->execute($params);
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
        $sexoBus = '';
        if (AuthService::isDelegadoAsociacion()) {
            $ctxB = AuthService::delegadoTorneoContextId();
            if ($ctxB !== null && (int) $ctxB > 0) {
                $sexoBus = $this->sqlAtletasFiltroSexoSegunTorneoTipo((int) $ctxB, 'atletas');
            }
        }
        $st = $this->pdo->prepare(
            'SELECT id, cedula, nombre, numfvd FROM atletas WHERE asociacion = :a
             AND (nombre LIKE :qn OR cedula LIKE :qc)' . $sexoBus . ' ORDER BY nombre ASC LIMIT ' . (int) $limit
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
     * @return list<array{cedula: int|string, nombre: string, numfvd: int, equipo: int, nombre_equipo: string|null, atleta_id: int|null}>
     */
    public function torneosInscritosInscripcionTorneo(int $torneoId, int $asociacionId): array
    {
        if (!$this->torneosInscripcionTorneoTableExists()) {
            return [];
        }
        $st = $this->pdo->prepare(
            'SELECT it.cedula, it.nombre, it.numfvd, it.equipo, it.nombre_equipo FROM inscripcion_torneo it
             WHERE it.torneo_id = :t AND it.asociacion_id = :a ORDER BY it.equipo ASC, it.nombre ASC'
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
     *   cupo: array{usado: int, max: int|null, restante: int|null},
     *   ventana_delegado: array<string, mixed>|null,
     *   acceso_delegado: array<string, mixed>|null
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
        $accesoDelegado = null;
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
            try {
                $accesoDelegado = \FvdPortal\Services\FvdAccessManager::estadoDelegadoTorneo(
                    $this->pdo,
                    $torneoId,
                    $asociacionId > 0 ? $asociacionId : null
                );
            } catch (\Throwable $e) {
                $accesoDelegado = null;
            }
        }

        return [
            'torneo'              => $row,
            'clase'               => $cl,
            'modo'                => \FvdPortal\Services\InscripcionService::claseEtiqueta($cl),
            'integrantes_equipo'  => $intEq,
            'cupo'                => $cupo,
            'ventana_delegado'    => $ventanaDelegado,
            'acceso_delegado'     => $accesoDelegado,
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
            $exSql .= $this->sqlAtletasFiltroSexoSegunTorneoTipo($filtroSexoDesdeTorneoId, 'a');
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
