<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/services/AuthService.php';
AuthService::ensureSession();
AuthService::requireLogin();
AuthService::requireRoles([AuthService::ROLE_FVD_ADMIN]);

if (!function_exists('url')) {
    require_once dirname(__DIR__, 2) . '/config/paths.php';
}
header('Location: ' . url('fvdmasteradmin/reportes/consolidado_finanzas.php'), true, 302);
exit;
