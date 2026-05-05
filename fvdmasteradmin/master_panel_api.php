<?php

declare(strict_types=1);

require_once __DIR__ . '/services/AuthService.php';
AuthService::ensureSession();
AuthService::requireLogin();

if (AuthService::isAdminGral() && isset($_GET['reset_portal']) && (string) $_GET['reset_portal'] === '1') {
    AuthService::clearAdminPortalDelegadoContext();
    /* No borrar fvd_master_panel_ctx_torneo: master_panel.php ya fijó el ancla desde ?ctx_torneo=;
     * borrarlo aquí vaciaba el contexto antes del primer iframe (atletas, etc.). */
}

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
    AuthService::requireRoles([
        AuthService::ROLE_FVD_ADMIN,
        AuthService::ROLE_DELEGADO_ASOC,
    ]);
}

require_once __DIR__ . '/includes/master_panel_state.php';

if (
    AuthService::isAdminGral()
    && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
) {
    $raw = (string) file_get_contents('php://input');
    /** @var mixed $json */
    $json = json_decode($raw !== '' ? $raw : '{}', true);
    if (is_array($json) && !empty($json['marcar_admin_notif'])) {
        require_once $projRoot . '/src/Services/NotificacionService.php';
        $nid = (int) ($json['id'] ?? 0);
        \FvdPortal\Services\NotificacionService::marcarLeida($pdoApi, $nid, AuthService::userId());
        $patch = fvd_master_panel_build_feed_patch($pdoApi);
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        echo json_encode($patch, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        exit;
    }
}

if (isset($_GET['feed']) && (string) $_GET['feed'] === '1') {
    $patch = fvd_master_panel_build_feed_patch($pdoApi);
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    echo json_encode($patch, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    exit;
}

$tid = isset($_GET['torneo_id']) ? (int) $_GET['torneo_id'] : 0;
$ctx = $tid > 0 ? $tid : null;

$state = fvd_master_panel_build_initial_state($pdoApi, $ctx, true);

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
echo json_encode($state, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
