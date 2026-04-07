<?php

declare(strict_types=1);

if (!defined('FVD_ADMIN_ROOT')) {
    define('FVD_ADMIN_ROOT', __DIR__);
}
if (!defined('FVD_PROJECT_ROOT')) {
    define('FVD_PROJECT_ROOT', dirname(FVD_ADMIN_ROOT));
}
if (!defined('FVD_MASTER_ROOT')) {
    define('FVD_MASTER_ROOT', FVD_PROJECT_ROOT . '/fvdmasteradmin');
}

require_once FVD_PROJECT_ROOT . '/config/paths.php';
require_once FVD_MASTER_ROOT . '/config/db.php';
require_once FVD_MASTER_ROOT . '/services/AuthService.php';
require_once FVD_PROJECT_ROOT . '/src/Services/FvdAdminService.php';

/**
 * @param list<string>|null $roles
 */
function fvd_admin_require_roles(?array $roles = null): void
{
    AuthService::ensureSession();
    AuthService::requireLogin();
    $roles = $roles ?? [AuthService::ROLE_FVD_ADMIN, AuthService::ROLE_ASO_ADMIN, AuthService::ROLE_DELEGADO_ASOC];
    AuthService::requireRoles($roles);
}
