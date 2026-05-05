<?php

declare(strict_types=1);

/**
 * Bootstrap común para módulos (físicamente en fvdmasteradmin/modules/; URL pública /modules/ vía .htaccess).
 */
if (!defined('FVD_MASTER_ROOT')) {
    define('FVD_MASTER_ROOT', dirname(__DIR__));
}
if (!defined('FVD_PROJECT_ROOT')) {
    define('FVD_PROJECT_ROOT', dirname(FVD_MASTER_ROOT));
}

require_once FVD_MASTER_ROOT . '/services/AuthService.php';

if (!function_exists('env')) {
    require_once FVD_MASTER_ROOT . '/config/db.php';
}

if (!function_exists('fvd_return_append_to_url')) {
    require_once FVD_PROJECT_ROOT . '/config/paths.php';
}

if (!function_exists('fvd_module_url')) {
    /**
     * URL pública hacia un script bajo /modules/ (sin "fvdmasteradmin" en la ruta; rewrite → fvdmasteradmin/modules/).
     * Debe usar el mismo prefijo que {@see fvd_master_module_url()} (BASE_URL inferido); si solo se usa
     * env('APP_BASE_PATH') vacío, el navegador resuelve `/modules/…` contra la raíz del vhost y puede
     * encadenar redirecciones (ERR_TOO_MANY_REDIRECTS) respecto a la subcarpeta del proyecto.
     */
    function fvd_module_url(string $path): string
    {
        if (!defined('BASE_URL')) {
            require_once FVD_PROJECT_ROOT . '/config/paths.php';
        }
        $base = defined('BASE_URL') ? rtrim((string) BASE_URL, '/') : rtrim((string) env('APP_BASE_PATH', ''), '/');

        return $base . '/modules/' . ltrim($path, '/');
    }
}

if (!function_exists('fvd_module_require_roles')) {
    /**
     * @param list<string>|null $roles Por defecto fvd_admin + aso_admin
     */
    function fvd_module_require_roles(?array $roles = null): void
    {
        AuthService::ensureSession();
        AuthService::requireLogin();
        $roles = $roles ?? [AuthService::ROLE_FVD_ADMIN, AuthService::ROLE_ASO_ADMIN, AuthService::ROLE_DELEGADO_ASOC];
        AuthService::requireRoles($roles);
    }
}
