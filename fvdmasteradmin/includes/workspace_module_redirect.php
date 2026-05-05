<?php

declare(strict_types=1);

/**
 * Redirección HTTP a un módulo bajo /admin/modules/ (tras AuthService en el script llamante).
 */
function fvd_workspace_redirect_to_admin_module(string $adminPath): void
{
    if (!function_exists('admin_module_url')) {
        require_once dirname(__DIR__, 2) . '/config/paths.php';
    }
    $path = ltrim($adminPath, '/');
    $base = admin_module_url($path);
    $qs = isset($_SERVER['QUERY_STRING']) ? (string) $_SERVER['QUERY_STRING'] : '';
    if ($qs !== '') {
        $base .= (str_contains($base, '?') ? '&' : '?') . $qs;
    }
    header('Location: ' . $base, true, 302);
    exit;
}

/**
 * Redirección a módulos canónicos bajo /modules/… (rewrite → fvdmasteradmin/modules/).
 * Use cuando el destino no exista en /admin/modules/ (p. ej. deuda_asociacion, relacion_pago, inscripciones).
 */
function fvd_workspace_redirect_to_fvd_module(string $modulePath): void
{
    if (!function_exists('fvd_master_module_url')) {
        require_once dirname(__DIR__, 2) . '/config/paths.php';
    }
    $path = ltrim($modulePath, '/');
    $base = fvd_master_module_url($path);
    $qs = isset($_SERVER['QUERY_STRING']) ? (string) $_SERVER['QUERY_STRING'] : '';
    if ($qs !== '') {
        $base .= (str_contains($base, '?') ? '&' : '?') . $qs;
    }
    header('Location: ' . $base, true, 302);
    exit;
}
