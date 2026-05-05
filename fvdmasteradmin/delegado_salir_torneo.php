<?php

declare(strict_types=1);

require_once __DIR__ . '/services/AuthService.php';
require_once dirname(__DIR__) . '/config/fvd_navigation_return.php';

AuthService::ensureSession();
AuthService::requireLogin();

$base = rtrim((string) (function_exists('env') ? env('APP_BASE_PATH', '') : ''), '/');

if (AuthService::isDelegadoAsociacion()) {
    AuthService::clearDelegadoTorneoContext();
}

$dest = AuthService::isDelegadoAsociacion()
    ? ($base . '/fvdmasteradmin/delegado_dashboard_new.php')
    : AuthService::homeUrl();
$originReturn = fvd_return_from_request();
if ($originReturn === null && isset($_SERVER['HTTP_REFERER']) && is_string($_SERVER['HTTP_REFERER'])) {
    $originReturn = fvd_return_sanitize($_SERVER['HTTP_REFERER']);
}
if ($originReturn !== null) {
    $dest = $originReturn;
}
header('Location: ' . $dest);
exit;
