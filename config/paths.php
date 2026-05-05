<?php
/**
 * Configuración de Rutas Dinámicas
 * Este archivo maneja todas las rutas de la aplicación de forma dinámica
 */

// Cargar variables de entorno
require_once __DIR__ . '/env.php';
Env::load();
require_once __DIR__ . '/deployment_disabled.php';
if (fvd_deployment_is_disabled()) {
    fvd_deployment_disabled_exit();
}
require_once __DIR__ . '/php_polyfills.php';

// Definir rutas base
if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}

/**
 * Deduce el prefijo URL del proyecto (p. ej. /fvd_portal_beta) desde SCRIPT_NAME.
 * Así CSS y assets cargan aunque el nombre de carpeta en hosting no coincida con APP_BASE_PATH.
 *
 * @return string '' si la app está en la raíz del dominio; p. ej. /fvd_portal_beta en subcarpeta
 */
function fvd_infer_web_base_path(): string
{
    if (PHP_SAPI === 'cli' || empty($_SERVER['SCRIPT_NAME'])) {
        return '';
    }
    $sn = str_replace('\\', '/', (string) $_SERVER['SCRIPT_NAME']);
    $markers = ['/fvdmasteradmin/', '/admin/modules/', '/admin/', '/modules/', '/dashboard/'];
    foreach ($markers as $m) {
        $p = strpos($sn, $m);
        if ($p !== false) {
            $base = substr($sn, 0, $p);

            return $base === '' ? '' : rtrim($base, '/');
        }
    }
    $dir = dirname($sn);
    $dir = str_replace('\\', '/', $dir);
    if ($dir === '/' || $dir === '.' || $dir === '') {
        return '';
    }

    return rtrim($dir, '/');
}

// Base URL dinámica
if (!defined('BASE_URL')) {
    $useAuto = in_array(strtolower((string) env('APP_BASE_PATH_AUTO', '')), ['1', 'true', 'yes'], true);
    if ($useAuto) {
        $base_path = fvd_infer_web_base_path();
    } else {
        $base_path = env('APP_BASE_PATH', '/fvd_portal');
    }
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
 * URL del informe de indicadores coherente con la entrada actual (GET/POST):
 * misma ruta que SCRIPT_NAME si ya es reporte_indicadores.php; si no, admin/modules/atletas/reporte_indicadores.php.
 * Evita que el formulario POST apunte a otra base que el usuario tenga abierta en el navegador.
 */
function fvd_atletas_reporte_indicadores_self_url(): string
{
    $sn = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    if ($sn !== '' && str_contains($sn, '/atletas/reporte_indicadores.php')) {
        return $sn;
    }

    return admin_module_url('atletas/reporte_indicadores.php');
}

/**
 * URL pública hacia módulos FVD bajo /modules/ (sin "fvdmasteradmin"; rewrite interno).
 */
function fvd_master_module_url(string $path = ''): string {
    $path = ltrim($path, '/');
    return BASE_URL . '/modules/' . $path;
}

/**
 * Ruta canónica del módulo inscripción a torneo (para redirecciones):
 * si la petición entró por /modules/, /fvdmasteradmin/modules/ o /admin/modules/, se mantiene esa base.
 */
function fvd_torneo_inscripcion_self_url(): string
{
    $sn = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    if ($sn !== '' && (
        str_contains($sn, '/modules/torneo_inscripcion/')
        || str_contains($sn, '/fvdmasteradmin/modules/torneo_inscripcion/')
        || str_contains($sn, '/admin/modules/torneo_inscripcion/')
    )) {
        return $sn;
    }

    return fvd_master_module_url('torneo_inscripcion/index.php');
}

/**
 * Base del CRUD FvdAdminService: si la petición entró por /modules/{mod}/ (o legado fvdmasteradmin/modules/), redirecciones siguen esa ruta.
 *
 * @param 'asociaciones'|'atletas'|'torneos'|'invitaciones'|'solicitudes_delegado' $module
 */
function fvd_crud_self_url(string $module): string {
    $sn = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    // Preferir la ruta real del script: si APP_BASE_PATH en .env no coincide con la URL (p. ej. /fvd_portal
    // en local vs beta en .env), fvd_master_module_url() apuntaba a otro prefijo y el GET (estado, q)
    // no llegaba a este index.php — el filtro parecía no funcionar.
    if (str_contains($sn, '/modules/' . $module . '/') || str_contains($sn, '/fvdmasteradmin/modules/' . $module . '/')) {
        return $sn;
    }
    if (str_contains($sn, '/admin/modules/' . $module . '/')) {
        return $sn;
    }

    return admin_module_url($module . '/index.php');
}

/**
 * URL del panel de delegado con contexto de torneo (`torneo_id`) y, si aplica, campeonato (`campeonato_id` = grupo de evento).
 *
 * @param int|null $campeonatoGrupoId null = usar grupo en sesión del delegado si existe; int (p. ej. 0) = solo ese valor (0 no añade parámetro).
 */
function fvd_delegado_dashboard_torneo_url(int $torneoId, ?int $campeonatoGrupoId = null): string
{
    $base = url('fvdmasteradmin/delegado_dashboard_new.php');
    if ($torneoId <= 0) {
        return $base;
    }
    $q = ['torneo_id' => $torneoId];
    $cg = 0;
    if ($campeonatoGrupoId !== null) {
        $cg = max(0, $campeonatoGrupoId);
    } elseif (class_exists('AuthService', false)) {
        \AuthService::ensureSession();
        $sessG = \AuthService::delegadoCampeonatoGrupoId();
        $cg = ($sessG !== null && (int) $sessG > 0) ? (int) $sessG : 0;
    }
    if ($cg > 0) {
        $q['campeonato_id'] = $cg;
    }

    return $base . (str_contains($base, '?') ? '&' : '?') . http_build_query($q);
}

/**
 * Indica si la sesión corresponde a la vista de panel de delegado (delegado o admin en portal-asociación).
 */
function fvd_delegado_vista_activa(): bool
{
    if (!class_exists('AuthService', false)) {
        $p = dirname(__DIR__) . '/fvdmasteradmin/services/AuthService.php';
        if (is_readable($p)) {
            require_once $p;
        }
    }
    if (!class_exists('AuthService', false)) {
        return false;
    }
    \AuthService::ensureSession();
    if (\AuthService::isDelegadoAsociacion()) {
        return true;
    }
    if (!\AuthService::isSuperAdmin()) {
        return false;
    }
    $pAsoc = (int) (\AuthService::adminPortalDelegadoAsociacionId() ?? 0);

    return $pAsoc > 0;
}

/**
 * Enlace al panel de evento de un torneo (`action=evento&id=`).
 *
 * - En vista de panel delegado, siempre se devuelve {@see fvd_delegado_dashboard_torneo_url} (no el CRUD
 *   `modules/torneos/index.php?action=evento`).
 * - Si la petición actual es ya el módulo torneos (admin, /modules/ o proxy fvdmasteradmin), se reutiliza
 *   esa misma ruta de script para que los saltos entre torneos no cambien de «árbol» de URL.
 * - En cualquier otra página, se usa la base canónica `/modules/torneos/index.php`
 *   alineada con atletas e inscripción bajo `/modules/`, evitando mezclar `/admin/modules/` con `/modules/`.
 */
function fvd_torneo_evento_url(int $torneoId, ?int $campeonatoGrupoDelegado = null): string
{
    if ($torneoId <= 0) {
        return '#';
    }
    if (fvd_delegado_vista_activa()) {
        return fvd_delegado_dashboard_torneo_url($torneoId, $campeonatoGrupoDelegado);
    }
    $sn = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    if ($sn !== '' && (
        str_contains($sn, '/modules/torneos/')
        || str_contains($sn, '/fvdmasteradmin/modules/torneos/')
        || str_contains($sn, '/admin/modules/torneos/')
    )) {
        $base = $sn;
    } else {
        $base = fvd_master_module_url('torneos/index.php');
    }
    $sep = str_contains($base, '?') ? '&' : '?';

    return $base . $sep . http_build_query(['action' => 'evento', 'id' => $torneoId]);
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

require_once __DIR__ . '/fvd_navigation_return.php';
