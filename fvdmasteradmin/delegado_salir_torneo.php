<?php

declare(strict_types=1);

require_once __DIR__ . '/services/AuthService.php';

AuthService::ensureSession();
AuthService::requireLogin();

$base = rtrim((string) (function_exists('env') ? env('APP_BASE_PATH', '') : ''), '/');

if (AuthService::isDelegadoAsociacion()) {
    AuthService::clearDelegadoTorneoContext();
}

header('Location: ' . $base . '/fvdmasteradmin/index.php');
exit;
