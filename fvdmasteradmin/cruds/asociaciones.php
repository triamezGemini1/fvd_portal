<?php

declare(strict_types=1);

/**
 * Entrada canónica del workspace → UI nueva (Vite) en la raíz del portal.
 */
require_once dirname(__DIR__, 2) . '/config/paths.php';
require_once dirname(__DIR__) . '/services/AuthService.php';

AuthService::ensureSession();
AuthService::requireLogin();
AuthService::requireRoles([
    AuthService::ROLE_FVD_ADMIN,
    AuthService::ROLE_ASO_ADMIN,
    AuthService::ROLE_DELEGADO_ASOC,
]);

$qs = isset($_SERVER['QUERY_STRING']) ? (string) $_SERVER['QUERY_STRING'] : '';
$dest = url('asociaciones.php');
if ($qs !== '') {
    $dest .= (str_contains($dest, '?') ? '&' : '?') . $qs;
}
header('Location: ' . $dest, true, 302);
exit;
