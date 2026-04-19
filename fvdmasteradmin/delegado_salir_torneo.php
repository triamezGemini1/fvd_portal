<?php

declare(strict_types=1);

require_once __DIR__ . '/services/AuthService.php';

AuthService::ensureSession();
AuthService::requireLogin();

$base = rtrim((string) (function_exists('env') ? env('APP_BASE_PATH', '') : ''), '/');

if (AuthService::isDelegadoAsociacion()) {
    AuthService::clearDelegadoTorneoContext();
}

$dest = ($base . '/fvdmasteradmin/') . (AuthService::isDelegadoAsociacion() ? 'delegado_dashboard.php' : 'index.php');
header('Location: ' . $dest);
exit;
