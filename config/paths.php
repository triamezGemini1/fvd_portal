<?php
/**
 * Configuración de Rutas Dinámicas
 * Este archivo maneja todas las rutas de la aplicación de forma dinámica
 */

// Cargar variables de entorno
require_once __DIR__ . '/env.php';
Env::load();
require_once __DIR__ . '/php_polyfills.php';

// Definir rutas base
if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}

// Base URL dinámica
if (!defined('BASE_URL')) {
    $base_path = env('APP_BASE_PATH', '/fvd_portal');
    define('BASE_URL', $base_path);
}

// URL completa de la aplicación
if (!defined('APP_URL')) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    define('APP_URL', $protocol . $host . BASE_URL);
}

/**
 * Generar URL absoluta
 */
function url($path = '') {
    $path = ltrim($path, '/');
    return BASE_URL . ($path ? '/' . $path : '');
}

/**
 * URL hacia módulos bajo /admin/modules/ (CRUD centralizado vía FvdAdminService).
 */
function admin_module_url(string $path = ''): string {
    $path = ltrim($path, '/');
    return BASE_URL . '/admin/modules/' . $path;
}

/**
 * URL pública hacia módulos FVD bajo /modules/ (sin "fvdmasteradmin"; rewrite interno).
 */
function fvd_master_module_url(string $path = ''): string {
    $path = ltrim($path, '/');
    return BASE_URL . '/modules/' . $path;
}

/**
 * Base del CRUD FvdAdminService: si la petición entró por /modules/{mod}/ (o legado fvdmasteradmin/modules/), redirecciones siguen esa ruta.
 *
 * @param 'asociaciones'|'atletas'|'torneos'|'invitaciones'|'solicitudes_delegado' $module
 */
function fvd_crud_self_url(string $module): string {
    $sn = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
    if (str_contains($sn, '/modules/' . $module . '/') || str_contains($sn, '/fvdmasteradmin/modules/' . $module . '/')) {
        return fvd_master_module_url($module . '/index.php');
    }

    return admin_module_url($module . '/index.php');
}

/**
 * Generar URL completa con dominio
 */
function full_url($path = '') {
    $path = ltrim($path, '/');
    return APP_URL . ($path ? '/' . $path : '');
}

/**
 * Generar ruta de archivo absoluta
 */
function base_path($path = '') {
    $path = ltrim($path, '/');
    return BASE_PATH . ($path ? '/' . $path : '');
}

/**
 * Ruta a archivos públicos (assets)
 */
function asset($path) {
    $path = ltrim($path, '/');
    return BASE_URL . '/assets/' . $path;
}

/**
 * Ruta a uploads
 */
function upload_url($path) {
    $path = ltrim($path, '/');
    return BASE_URL . '/uploads/' . $path;
}

/**
 * Verificar si estamos en producción
 */
function is_production() {
    return env('APP_ENV') === 'production';
}

/**
 * Verificar si el debug está activado
 */
function is_debug() {
    return env('APP_DEBUG', false) === 'true' || env('APP_DEBUG', false) === true;
}

/**
 * Formato uniforme para importes y cantidades contables en pantalla (2 decimales, es-VE).
 *
 * @param float|int|string|null $value
 */
function fvd_format_contable($value, string $empty = '—'): string
{
    if ($value === null || $value === '') {
        return $empty;
    }
    if (!is_numeric($value)) {
        return $empty;
    }

    return number_format((float) $value, 2, ',', '.');
}


