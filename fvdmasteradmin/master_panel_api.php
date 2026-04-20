<?php

declare(strict_types=1);

require_once __DIR__ . '/services/AuthService.php';
AuthService::ensureSession();
AuthService::requireLogin();

if (AuthService::isAdminGral()) {
    unset($_SESSION['fvd_master_delegado_notif_token']);
}

$projRoot = dirname(__DIR__);
if (!function_exists('url')) {
    require_once $projRoot . '/config/paths.php';
}

require_once __DIR__ . '/config/db.php';
require_once $projRoot . '/src/Services/DelegadoTorneoNotifService.php';

use FvdPortal\Services\DelegadoTorneoNotifService;

$pdoApi = fvd_db();
$tokSess = isset($_SESSION['fvd_master_delegado_notif_token'])
    ? trim((string) $_SESSION['fvd_master_delegado_notif_token'])
    : '';

if ($tokSess !== '' && AuthService::isDelegadoAsociacion()) {
    $notifApi = DelegadoTorneoNotifService::notificacionPorAccessToken($pdoApi, $tokSess);
    if (
        $notifApi === null
        || (int) ($notifApi['delegado_id'] ?? 0) !== (int) AuthService::userId()
    ) {
        http_response_code(403);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['error' => 'token_invalido'], JSON_UNESCAPED_UNICODE);
        exit;
    }
} else {
    AuthService::requireRoles([AuthService::ROLE_FVD_ADMIN]);
}

require_once __DIR__ . '/includes/master_panel_state.php';

$tid = isset($_GET['torneo_id']) ? (int) $_GET['torneo_id'] : 0;
$ctx = $tid > 0 ? $tid : null;

$state = fvd_master_panel_build_initial_state($pdoApi, $ctx);

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
echo json_encode($state, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
