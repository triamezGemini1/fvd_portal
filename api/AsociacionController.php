<?php

declare(strict_types=1);

/**
 * Lógica JSON para gestión de asociaciones (usa {@see FvdAdminService}).
 */
final class AsociacionController
{
    private FvdAdminService $svc;

    public function __construct(?FvdAdminService $svc = null)
    {
        $this->svc = $svc ?? new FvdAdminService();
    }

    /**
     * @return array{ok: bool, error?: string, data?: mixed, code?: int}
     */
    public function listAction(int $page, int $perPage, string $q, string $filtroEstatus): array
    {
        if ($page < 1) {
            $page = 1;
        }
        if ($perPage < 1 || $perPage > 100) {
            $perPage = 25;
        }
        if (!in_array($filtroEstatus, ['todas', 'activas', 'inactivas'], true)) {
            $filtroEstatus = 'todas';
        }

        try {
            $result = $this->svc->asociacionesPaginateList($page, $perPage, $q, $filtroEstatus);
        } catch (Throwable $e) {
            error_log('[AsociacionController::list] ' . $e->getMessage());

            return ['ok' => false, 'error' => 'Error al listar asociaciones.', 'code' => 500];
        }

        $rows = [];
        foreach ($result['rows'] as $r) {
            $rows[] = $this->normalizeRow($r);
        }

        $isFvd = AuthService::role() === AuthService::ROLE_FVD_ADMIN;

        return [
            'ok' => true,
            'data' => [
                'total' => (int) $result['total'],
                'page' => (int) $result['page'],
                'per_page' => (int) $result['per_page'],
                'pages' => (int) $result['pages'],
                'rows' => $rows,
                'meta' => [
                    'isFvdAdmin' => $isFvd,
                    'canCreate' => $isFvd,
                    'canDelete' => false,
                    'canToggleEstatus' => $isFvd,
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
        $row = $this->svc->asociacionesFind($id);
        if ($row === null) {
            return ['ok' => false, 'error' => 'No encontrado.', 'code' => 404];
        }

        $isFvd = AuthService::role() === AuthService::ROLE_FVD_ADMIN;
        $mine = AuthService::idAsociacion();
        $canEdit = $isFvd || ($mine !== null && (int) $mine === $id);

        return [
            'ok' => true,
            'data' => [
                'row' => $this->normalizeRow($row),
                'raw' => $row,
                'meta' => [
                    'isFvdAdmin' => $isFvd,
                    'canDelete' => false,
                    'canToggleEstatus' => $isFvd,
                    'canEdit' => $canEdit,
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
        $role = AuthService::role();
        if ($id === null) {
            if ($role !== AuthService::ROLE_FVD_ADMIN) {
                return ['ok' => false, 'error' => 'Solo el administrador FVD puede crear asociaciones.', 'code' => 403];
            }
        } elseif ($role !== AuthService::ROLE_FVD_ADMIN) {
            $mine = AuthService::idAsociacion();
            if ($mine === null || (int) $id !== (int) $mine) {
                return ['ok' => false, 'error' => 'Acceso denegado.', 'code' => 403];
            }
        }

        try {
            $this->svc->asociacionesSave($id, $post, $files);
        } catch (Throwable $e) {
            error_log('[AsociacionController::save] ' . $e->getMessage());

            return ['ok' => false, 'error' => $e->getMessage(), 'code' => 400];
        }

        return ['ok' => true, 'data' => ['saved' => true]];
    }

    /**
     * @return array{ok: bool, error?: string, code?: int}
     */
    public function deleteAction(int $id): array
    {
        if (AuthService::role() !== AuthService::ROLE_FVD_ADMIN) {
            return ['ok' => false, 'error' => 'No autorizado.', 'code' => 403];
        }
        if ($id <= 0) {
            return ['ok' => false, 'error' => 'ID inválido.', 'code' => 400];
        }
        try {
            $this->svc->asociacionesDelete($id);
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage(), 'code' => 400];
        }

        return ['ok' => true];
    }

    /**
     * @return array{ok: bool, error?: string, code?: int}
     */
    public function toggleEstatusAction(int $id): array
    {
        if (AuthService::role() !== AuthService::ROLE_FVD_ADMIN) {
            return ['ok' => false, 'error' => 'Solo el administrador FVD puede cambiar el estatus.', 'code' => 403];
        }
        if ($id <= 0) {
            return ['ok' => false, 'error' => 'ID inválido.', 'code' => 400];
        }
        try {
            $this->svc->asociacionesToggleEstatus($id);
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage(), 'code' => 400];
        }

        $row = $this->svc->asociacionesFind($id);

        return [
            'ok' => true,
            'data' => [
                'row' => $row !== null ? $this->normalizeRow($row) : null,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function normalizeRow(array $row): array
    {
        $logo = isset($row['logo']) ? trim((string) $row['logo']) : '';
        $logoUrl = $logo !== '' && function_exists('upload_url') ? upload_url($logo) : null;

        return [
            'id' => (int) ($row['id'] ?? 0),
            'nombre' => (string) ($row['nombre'] ?? ''),
            'delegado' => (string) ($row['delegado'] ?? ''),
            'telefono' => (string) ($row['telefono'] ?? ''),
            'email' => (string) ($row['email'] ?? ''),
            'direccion' => (string) ($row['direccion'] ?? ''),
            'numreg' => (string) ($row['numreg'] ?? ''),
            'providencia' => (string) ($row['providencia'] ?? ''),
            'logo' => $logo !== '' ? $logo : null,
            'logo_url' => $logoUrl,
            'activa' => FvdAdminService::asociacionEstatusEsActiva($row),
            'estatus_raw' => $row['estatus'] ?? null,
        ];
    }
}
