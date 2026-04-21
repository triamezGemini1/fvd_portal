<?php

declare(strict_types=1);

/**
 * Compatibilidad: el panel delegado vive en delegado_dashboard_new.php.
 * Redirige invitaciones y enlaces antiguos (p. ej. #fvd-deleg-guia-inscripcion).
 */

require_once __DIR__ . '/services/AuthService.php';
AuthService::ensureSession();
AuthService::requireLogin();

$projRoot = dirname(__DIR__);
require_once $projRoot . '/config/paths.php';
$appBase = rtrim((string) BASE_URL, '/');

if (AuthService::isAthletePortalUser()) {
    header('Location: ' . $appBase . '/fvdmasteradmin/atleta/mi_ficha.php', true, 302);
    exit;
}

if (!AuthService::isDelegadoAsociacion()) {
    header('Location: ' . AuthService::homeUrl(), true, 302);
    exit;
}

header('Location: ' . $appBase . '/fvdmasteradmin/delegado_dashboard_new.php', true, 302);
exit;
