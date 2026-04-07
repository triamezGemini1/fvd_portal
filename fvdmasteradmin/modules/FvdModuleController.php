<?php

declare(strict_types=1);

require_once FVD_MASTER_ROOT . '/services/QueryHelper.php';

/**
 * Base de controladores de módulo: extiende QueryHelper (insert/update/paginate con alcance).
 */
abstract class FvdModuleController extends QueryHelper
{
    protected PDO $pdo;

    public function __construct()
    {
        $this->pdo = fvd_db();
    }

    protected function projectRoot(): string
    {
        return FVD_PROJECT_ROOT;
    }

    protected function redirectToModule(string $relativePath): void
    {
        header('Location: ' . fvd_module_url($relativePath));
        exit;
    }

    /** Solo fvd_admin puede crear nuevas asociaciones (organizadores regionales). */
    protected function requireFvdAdminToCreate(): void
    {
        if (AuthService::role() !== AuthService::ROLE_FVD_ADMIN) {
            http_response_code(403);
            echo 'Solo el administrador FVD puede crear este registro.';
            exit;
        }
    }

    /**
     * Comprueba que el registro pertenezca a la asociación de sesión (aso_admin / usuario).
     */
    protected function enforceAsociacionId(?int $asociacionId): void
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
}
