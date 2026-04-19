<?php

declare(strict_types=1);

/**
 * Alias del panel de delegado (Command Center).
 * La implementación vive en fvdmasteradmin/delegado_dashboard.php.
 */
require_once dirname(__DIR__, 2) . '/_init.php';

AuthService::ensureSession();
AuthService::requireLogin();

$base = rtrim((string) env('APP_BASE_PATH', ''), '/');
$dest = $base . '/fvdmasteradmin/delegado_dashboard.php';

if (!AuthService::isDelegadoAsociacion()) {
    header('Location: ' . AuthService::homeUrl(), true, 302);
    exit;
}

header('Location: ' . $dest, true, 302);
exit;
