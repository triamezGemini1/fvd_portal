<?php

declare(strict_types=1);

require_once __DIR__ . '/../fvdmasteradmin/services/AuthService.php';
require_once __DIR__ . '/../src/Services/PersonaReferencialService.php';
require_once __DIR__ . '/../src/Services/FvdAdminService.php';

use FvdPortal\Services\PersonaReferencialService;

/**
 * API JSON para gestión de atletas: listado con búsqueda inteligente, CRUD vía {@see FvdAdminService},
 * y consulta referencial RNE/persona (32M) acotada a campos mínimos.
 *
 * Patrón alineado con {@see AsociacionController}.
 */
final class AtletaController
{
    /** Paginación fija para rendimiento en WAMP (no negociable vía query). */
    private const LIST_PER_PAGE = 10;

    private FvdAdminService $svc;

    public function __construct(?FvdAdminService $svc = null)
    {
        $this->svc = $svc ?? new FvdAdminService();
    }

    /**
     * Clasifica `q` para ramas SQL: cédula (solo dígitos/puntos/guiones/espacios con al menos un dígito),
     * email (contiene "@"), o nombre/apellido (tokens AND sobre `atletas.nombre`).
     *
     * @return array{mode: string, value: string} mode ∈ '', 'cedula', 'email', 'nombre'
     */
    public static function classifyListSearch(string $q): array
    {
        $t = trim($q);
        if ($t === '') {
            return ['mode' => '', 'value' => ''];
        }
        if (str_contains($t, '@')) {
            return ['mode' => 'email', 'value' => $t];
        }
        if (preg_match('/^[0-9.\-\s]+$/', $t) === 1 && preg_replace('/\D+/', '', $t) !== '') {
            return ['mode' => 'cedula', 'value' => $t];
        }

        return ['mode' => 'nombre', 'value' => $t];
    }

    /**
     * @return array{ok: bool, error?: string, data?: mixed, code?: int}
     */
    public function listAction(int $page, string $q, int $idAsociacion, string $tipo): array
    {
        if ($page < 1) {
            $page = 1;
        }
        if (!in_array($tipo, ['normal', 'ultimos', 'no_activos', 'bajas'], true)) {
            $tipo = 'normal';
        }

        $isFvd = AuthService::role() === AuthService::ROLE_FVD_ADMIN;
        $alcance = 'todos';
        $asocId = 0;
        if (!$isFvd) {
            $alcance = 'asociacion';
            $mine = AuthService::idAsociacion();
            $asocId = $mine !== null ? (int) $mine : 0;
        } elseif ($idAsociacion > 0) {
            $alcance = 'asociacion';
            $asocId = $idAsociacion;
        }

        $classified = self::classifyListSearch($q);
        $smart = $classified['value'];
        $smartMode = $classified['mode'];

        try {
            $result = $this->svc->atletasPaginateList(
                $page,
                self::LIST_PER_PAGE,
                '',
                '',
                $alcance,
                $tipo,
                $asocId,
                $smart,
                $smartMode
            );
        } catch (Throwable $e) {
            error_log('[AtletaController::list] ' . $e->getMessage());

            return ['ok' => false, 'error' => 'Error al listar atletas.', 'code' => 500];
        }

        $rows = [];
        foreach ($result['rows'] as $r) {
            $rows[] = $this->normalizeRow(is_array($r) ? $r : [], false);
        }

        $asociaciones = [];
        try {
            $asociaciones = $this->svc->atletasListAsociacionesForSelect();
        } catch (Throwable $e) {
            $asociaciones = [];
        }

        $sigNum = 0;
        if ($isFvd) {
            try {
                $sigNum = $this->svc->atletasSiguienteNumfvdSugerido();
            } catch (Throwable $e) {
                $sigNum = 0;
            }
        }

        return [
            'ok' => true,
            'data' => [
                'total'    => (int) $result['total'],
                'page'     => (int) $result['page'],
                'per_page' => self::LIST_PER_PAGE,
                'pages'    => (int) $result['pages'],
                'rows'     => $rows,
                'meta'     => [
                    'isFvdAdmin'              => $isFvd,
                    'canCreate'               => true,
                    'canToggle'               => $isFvd,
                    'asociaciones'            => $asociaciones,
                    'siguienteNumFvdSugerido' => $sigNum,
                    'search_mode'             => $smartMode,
                ],
            ],
        ];
    }

    /**
     * Consulta referencial dbo.persona / dpersona (32M): solo nombres, apellidos y fecha de nacimiento.
     *
     * @return array{ok: bool, error?: string, data?: mixed, code?: int}
     */
    public function searchReferencialAction(string $cedula): array
    {
        $trim = trim($cedula);
        if ($trim === '') {
            return ['ok' => false, 'error' => 'Indique la cédula.', 'code' => 400];
        }

        $raw = PersonaReferencialService::lookupByCedula($trim);
        if ($raw === null) {
            return [
                'ok'   => true,
                'data' => [
                    'found' => false,
                    'row'   => null,
                ],
            ];
        }

        $nombres = trim(trim($raw['nombre1'] ?? '') . ' ' . trim($raw['nombre2'] ?? ''));
        $apellidos = trim(trim($raw['apellido1'] ?? '') . ' ' . trim($raw['apellido2'] ?? ''));
        $nombres = preg_replace('/\s+/u', ' ', $nombres) ?? '';
        $apellidos = preg_replace('/\s+/u', ' ', $apellidos) ?? '';

        return [
            'ok'   => true,
            'data' => [
                'found' => true,
                'row'   => [
                    'nombres'            => $nombres,
                    'apellidos'          => $apellidos,
                    'fecha_nacimiento'   => $raw['fechnac'] ?? null,
                ],
            ],
        ];
    }

    /**
     * @return array{ok: bool, error?: string, data?: mixed, code?: int}
     */
    public function getAction(int $id): array
    {
        if ($id <= 0) {
            return ['ok' => false, 'error' => 'ID inválido.', 'code' => 400];
        }
        $row = $this->svc->atletasFind($id);
        if ($row === null) {
            return ['ok' => false, 'error' => 'No encontrado.', 'code' => 404];
        }

        $isFvd = AuthService::role() === AuthService::ROLE_FVD_ADMIN;
        $mine = AuthService::idAsociacion();
        $asocRow = isset($row['asociacion']) ? (int) $row['asociacion'] : 0;
        $canEdit = $isFvd || ($mine !== null && (int) $mine === $asocRow);

        return [
            'ok' => true,
            'data' => [
                'row' => $this->normalizeRow($row, true),
                'raw' => $row,
                'meta' => [
                    'isFvdAdmin' => $isFvd,
                    'canEdit'    => $canEdit,
                    'canToggle'  => $isFvd,
                ],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $post
     * @param array<string, mixed> $files
     * @return array{ok: bool, error?: string, data?: mixed, code?: int}
     */
    public function saveAction(?int $id, array $post, array $files): array
    {
        $isFvd = AuthService::role() === AuthService::ROLE_FVD_ADMIN;

        if ($id !== null) {
            $existing = $this->svc->atletasFind($id);
            if ($existing === null) {
                return ['ok' => false, 'error' => 'No encontrado o sin permiso.', 'code' => 404];
            }
            if (!$isFvd) {
                $mine = AuthService::idAsociacion();
                $asoc = isset($existing['asociacion']) ? (int) $existing['asociacion'] : 0;
                if ($mine === null || (int) $mine !== $asoc) {
                    return ['ok' => false, 'error' => 'Solo puede editar atletas de su asociación.', 'code' => 403];
                }
            }
        }

        $nombres = isset($post['nombres']) ? trim((string) $post['nombres']) : '';
        $apellidos = isset($post['apellidos']) ? trim((string) $post['apellidos']) : '';
        if ($nombres !== '' || $apellidos !== '') {
            $post['nombre'] = trim($nombres . ' ' . $apellidos);
            $post['nombre'] = preg_replace('/\s+/u', ' ', (string) $post['nombre']) ?? '';
        }

        try {
            $this->svc->atletasSave($id, $post, $files);
        } catch (Throwable $e) {
            error_log('[AtletaController::save] ' . $e->getMessage());

            return ['ok' => false, 'error' => $e->getMessage(), 'code' => 400];
        }

        return ['ok' => true, 'data' => ['saved' => true]];
    }

    /**
     * @return array{ok: bool, error?: string, code?: int}
     */
    public function toggleActivoAction(int $id): array
    {
        if (AuthService::role() !== AuthService::ROLE_FVD_ADMIN) {
            return ['ok' => false, 'error' => 'Solo el administrador FVD puede alternar activo.', 'code' => 403];
        }
        if ($id <= 0) {
            return ['ok' => false, 'error' => 'ID inválido.', 'code' => 400];
        }
        try {
            $this->svc->atletasToggleActivo($id);
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage(), 'code' => 400];
        }
        $row = $this->svc->atletasFind($id);
        $payload = $row !== null && is_array($row)
            ? $row
            : ['id' => $id];

        return [
            'ok'   => true,
            'data' => [
                'row' => $this->normalizeRow($payload, false),
            ],
        ];
    }

    /**
     * Compatibilidad: misma consulta que {@see searchReferencialAction()} con el formato extendido del servicio.
     *
     * @return array{ok: bool, error?: string, data?: mixed, code?: int}
     */
    public function personaLookupAction(string $cedula): array
    {
        $row = PersonaReferencialService::lookupByCedula($cedula);
        if ($row === null) {
            return [
                'ok'   => true,
                'data' => [
                    'found' => false,
                    'row'   => null,
                ],
            ];
        }

        return [
            'ok'   => true,
            'data' => [
                'found' => true,
                'row'   => $row,
            ],
        ];
    }

    /**
     * @return array{ok: bool, error?: string, data?: mixed, code?: int}
     */
    public function siguienteNumFvdAction(): array
    {
        if (AuthService::role() !== AuthService::ROLE_FVD_ADMIN) {
            return ['ok' => false, 'error' => 'No autorizado.', 'code' => 403];
        }

        return [
            'ok'   => true,
            'data' => [
                'siguiente' => $this->svc->atletasSiguienteNumfvdSugerido(),
            ],
        ];
    }

    /**
     * @return array{ok: bool, error?: string, data?: mixed, code?: int}
     */
    public function cedulaDisponibleAction(string $cedula): array
    {
        $trim = trim($cedula);
        if ($trim === '') {
            return ['ok' => true, 'data' => ['disponible' => true, 'existe' => false, 'mensaje' => '']];
        }
        $dup = $this->svc->atletasFindByCedulaGlobal($trim);

        return [
            'ok'   => true,
            'data' => [
                'disponible' => $dup === null,
                'existe'     => $dup !== null,
                'mensaje'    => $dup !== null
                    ? 'Esta cédula ya está registrada como atleta en el sistema.'
                    : '',
                'existente'  => $dup !== null ? [
                    'id'         => (int) ($dup['id'] ?? 0),
                    'nombre'     => trim((string) ($dup['nombre'] ?? '')),
                    'numfvd'     => (int) ($dup['numfvd'] ?? 0),
                    'asociacion' => trim((string) ($dup['asociacion_nombre'] ?? '')),
                    'estatus'    => (int) ($dup['estatus'] ?? 0),
                    'email'      => trim((string) ($dup['email'] ?? '')),
                    'celular'    => trim((string) ($dup['celular'] ?? '')),
                    'fechnac'    => isset($dup['fechnac']) && (string) $dup['fechnac'] !== ''
                        ? substr((string) $dup['fechnac'], 0, 10)
                        : '',
                    'profesion'  => trim((string) ($dup['profesion'] ?? '')),
                    'direccion'  => trim((string) ($dup['direccion'] ?? '')),
                ] : null,
            ],
        ];
    }

    /**
     * @return array{ok: bool, error?: string, data?: mixed, code?: int}
     */
    public function torneosPorAsociacionAction(int $asociacionId): array
    {
        if ($asociacionId <= 0) {
            return ['ok' => true, 'data' => ['torneos' => []]];
        }

        $mine = AuthService::idAsociacion();
        if (AuthService::role() !== AuthService::ROLE_FVD_ADMIN && ($mine === null || (int) $mine !== $asociacionId)) {
            return ['ok' => false, 'error' => 'No autorizado.', 'code' => 403];
        }

        return [
            'ok'   => true,
            'data' => [
                'torneos' => $this->svc->atletasTorneosPorAsociacion($asociacionId),
            ],
        ];
    }

    /**
     * Foto pública: archivos bajo `crud_atletas/uploads` (mismo criterio que el admin PHP).
     * Si en el futuro las rutas viven bajo `/uploads/`, use {@see upload_url()} con el subpath relativo a uploads.
     */
    private function buildFotoUrl(?string $foto): ?string
    {
        $f = $foto !== null ? trim($foto) : '';
        if ($f === '') {
            return null;
        }
        $rel = ltrim($f, '/');
        if (str_starts_with($rel, 'uploads/') && function_exists('upload_url')) {
            return upload_url(substr($rel, strlen('uploads/')));
        }
        if (function_exists('url')) {
            return url('crud_atletas/uploads/' . $rel);
        }

        return function_exists('upload_url') ? upload_url($rel) : null;
    }

    /**
     * Etiqueta UI “doble fila” en un solo string (salto de línea entre Nº FVD y cédula).
     */
    private function buildIdentidadLabel(int $numfvd, string $cedula): string
    {
        $nf = $numfvd > 0 ? 'Nº FVD ' . $numfvd : 'Nº FVD pendiente';
        $ced = trim($cedula);

        return $nf . "\n" . ($ced !== '' ? 'C.I. ' . $ced : 'Sin cédula');
    }

    /**
     * Edad en años cumplidos a la fecha de hoy; null si no hay fecha válida.
     */
    private function edadDesdeFechanac($fechnac): ?int
    {
        if ($fechnac === null || $fechnac === '') {
            return null;
        }
        $raw = trim((string) $fechnac);
        $s = substr($raw, 0, 10);
        $born = null;
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $s) === 1) {
            try {
                $born = new DateTimeImmutable($s);
            } catch (Throwable $e) {
                $born = null;
            }
        }
        if ($born === null) {
            $ts = strtotime($raw);
            if ($ts === false) {
                return null;
            }
            try {
                $born = (new DateTimeImmutable('@' . $ts))->setTime(0, 0);
            } catch (Throwable $e) {
                return null;
            }
        }
        try {
            $today = new DateTimeImmutable('today');
        } catch (Throwable $e) {
            return null;
        }
        $d = $born->diff($today);

        return max(0, (int) $d->y);
    }

    /**
     * @param array<string, mixed>|null $r Fila BD o vacío; nunca se asume no-null desde listados externos.
     * @return array<string, mixed> Siempre incluye `cedula` (string), `numfvd` (int), `identidad_label`, etc.
     */
    private function normalizeRow(?array $r, bool $includeFormFields): array
    {
        $r = is_array($r) ? $r : [];
        $foto = isset($r['foto']) ? trim((string) $r['foto']) : '';
        $cedula = (string) ($r['cedula'] ?? '');
        $numfvd = (int) ($r['numfvd'] ?? 0);
        $fechnac = $r['fechnac'] ?? null;

        $base = [
            'id'               => (int) ($r['id'] ?? 0),
            'foto'             => $foto !== '' ? $foto : null,
            'foto_url'         => $this->buildFotoUrl($foto),
            'cedula'           => $cedula,
            'nombre'           => (string) ($r['nombre'] ?? ''),
            'sexo'             => (int) ($r['sexo'] ?? 0),
            'numfvd'           => $numfvd,
            'estatus'          => (int) ($r['estatus'] ?? 0),
            'celular'          => (string) ($r['celular'] ?? ''),
            'email'            => (string) ($r['email'] ?? ''),
            'asociacion'       => isset($r['asociacion']) ? (int) $r['asociacion'] : null,
            'asociacion_nombre' => (string) ($r['asociacion_nombre'] ?? ''),
            'fechnac'          => $fechnac,
            'categ'            => (int) ($r['categ'] ?? 0),
            'identidad_label'  => $this->buildIdentidadLabel($numfvd, $cedula),
            'edad'             => $this->edadDesdeFechanac($fechnac),
        ];

        if (!$includeFormFields) {
            return $base;
        }

        $nombre = trim((string) ($r['nombre'] ?? ''));
        $parts = preg_split('/\s+/u', $nombre, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $nombres = $nombre;
        $apellidos = '';
        $n = count($parts);
        if ($n >= 3) {
            $apellidos = implode(' ', array_slice($parts, -2));
            $nombres = implode(' ', array_slice($parts, 0, -2));
        } elseif ($n === 2) {
            $nombres = $parts[0];
            $apellidos = $parts[1];
        }

        $fechfvd = $r['fechfvd'] ?? null;
        $fechfvdStr = $fechfvd !== null && $fechfvd !== '' ? substr((string) $fechfvd, 0, 10) : '';

        return array_merge($base, [
            'profesion'      => (string) ($r['profesion'] ?? ''),
            'direccion'      => (string) ($r['direccion'] ?? ''),
            'torneo_id'      => (int) ($r['torneo_id'] ?? 0),
            'nombres'        => $nombres,
            'apellidos'      => $apellidos,
            'talla_camisa'   => (string) ($r['talla_camisa'] ?? ''),
            'observaciones'  => (string) ($r['observaciones'] ?? ''),
            'fechfvd'        => $fechfvdStr,
            'categ_etiqueta' => FvdAdminService::atletasCategoriaEtiquetaPorCodigo((int) ($r['categ'] ?? 0)),
        ]);
    }
}
