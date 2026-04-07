<?php

declare(strict_types=1);

/**
 * Raíz web del proyecto (p. ej. /fvd_portal), sin /fvdmasteradmin.
 *
 * @var string $fvdAppBasePath
 */
if (!function_exists('env')) {
    $envBootstrap = dirname(__DIR__, 2) . '/config/env.php';
    if (is_file($envBootstrap)) {
        require_once $envBootstrap;
    }
}

$fvdAppBasePath = rtrim((string) (function_exists('env') ? env('APP_BASE_PATH', '') : ''), '/');
if ($fvdAppBasePath !== '' && preg_match('#/fvdmasteradmin$#i', $fvdAppBasePath)) {
    $fvdAppBasePath = rtrim((string) preg_replace('#/fvdmasteradmin$#i', '', $fvdAppBasePath), '/');
}
if ($fvdAppBasePath === '') {
    $sn = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
    $pos = strpos($sn, '/fvdmasteradmin/');
    if ($pos > 0) {
        $fvdAppBasePath = rtrim(substr($sn, 0, $pos), '/');
    }
}
