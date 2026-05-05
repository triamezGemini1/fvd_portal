<?php

declare(strict_types=1);

$__fvdPoly = dirname(__DIR__) . '/config/php_polyfills.php';
if (is_file($__fvdPoly)) {
    require_once $__fvdPoly;
}
unset($__fvdPoly);

/**
 * Verificación global de rango para scripts bajo la raíz del proyecto (`fvd_portal`).
 * Las rutas públicas se listan explícitamente; el resto exige sesión de personal
 * (ADMIN_GRAL, ADMIN_ASOC o DELEGADO) vía {@see FvdAuth}.
 */
if (defined('FVD_PORTAL_RBAC_APPLIED')) {
    return;
}

if (PHP_SAPI === 'cli') {
    return;
}

if (!empty($GLOBALS['FVD_PORTAL_SKIP_RBAC'])) {
    return;
}

if (defined('FVD_PORTAL_SKIP_RBAC') && FVD_PORTAL_SKIP_RBAC) {
    return;
}

$appRoot = dirname(__DIR__);
$authPath = $appRoot . '/src/Auth.php';
if (!is_file($authPath)) {
    return;
}

$scriptFs = isset($_SERVER['SCRIPT_FILENAME']) ? (string) $_SERVER['SCRIPT_FILENAME'] : '';
if ($scriptFs === '') {
    return;
}

$appReal = realpath($appRoot);
$scriptReal = realpath($scriptFs);
if ($appReal === false || $scriptReal === false) {
    return;
}

if (strpos($scriptReal, $appReal) !== 0) {
    return;
}

$rel = str_replace('\\', '/', substr($scriptReal, strlen($appReal)));
$rel = ltrim($rel, '/');

/** @var list<string> Coincidencia por prefijo o nombre de archivo relativo */
$publicExact = [
    'index.php',
    'login.php',
    'login_fix.php',
    'ranking_publico.php',
    'torneo_publico.php',
    'calendario.php',
    'afiliacion.php',
    'asociaciones.php',
    'atletas.php',
    'api/atleta_referencial.php',
    'invitacion.php',
    'fvd_diag.php',
    'test.php',
    'app_liquid_demo.php',
    'fvdmasteradmin/perfil.php',
    'fvdmasteradmin/login.php',
];

$publicPrefixes = [
    'crud_atletas/',
    'atleta/',
    'dashboard/',
    'fvdmasteradmin/atleta/',
];

$publicSubstrings = [
    '/login.php',
    '/logout.php',
];

foreach ($publicExact as $name) {
    if ($rel === $name) {
        define('FVD_PORTAL_RBAC_APPLIED', true);

        return;
    }
}

foreach ($publicPrefixes as $prefix) {
    if (str_starts_with($rel, $prefix)) {
        define('FVD_PORTAL_RBAC_APPLIED', true);

        return;
    }
}

foreach ($publicSubstrings as $needle) {
    if (str_contains($rel, $needle)) {
        define('FVD_PORTAL_RBAC_APPLIED', true);

        return;
    }
}

require_once $authPath;

$min = FvdAuth::DELEGADO;
if (isset($_ENV['FVD_PORTAL_RBAC_MIN'])) {
    $m = strtoupper(trim((string) $_ENV['FVD_PORTAL_RBAC_MIN']));
    if (in_array($m, [FvdAuth::ADMIN_GRAL, FvdAuth::ADMIN_ASOC, FvdAuth::DELEGADO], true)) {
        $min = $m;
    }
}

FvdAuth::requireStaffSession($min);
define('FVD_PORTAL_RBAC_APPLIED', true);
