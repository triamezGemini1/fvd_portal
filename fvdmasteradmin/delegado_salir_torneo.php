<?php

declare(strict_types=1);

require_once __DIR__ . '/services/AuthService.php';

AuthService::ensureSession();
AuthService::requireLogin();

$base = rtrim((string) (function_exists('env') ? env('APP_BASE_PATH', '') : ''), '/');

if (AuthService::isDelegadoAsociacion()) {
    AuthService::clearDelegadoTorneoContext();
}

$dest = AuthService::isDelegadoAsociacion()
    ? ($base . '/fvdmasteradmin/delegado_dashboard.php')
    : AuthService::homeUrl();
header('Location: ' . $dest);
exit;
