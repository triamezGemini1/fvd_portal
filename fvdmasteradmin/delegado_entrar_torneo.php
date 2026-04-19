<?php

declare(strict_types=1);

/**
 * Activa el contexto de administración del torneo para el delegado y abre el panel del evento.
 *
 * Parámetros: token (enlace de la tarjeta PDF), notif_id o ultima=1 (última notificación no vista).
 */

require_once __DIR__ . '/services/AuthService.php';
require_once dirname(__DIR__) . '/config/paths.php';
if (!function_exists('fvd_append_embed_to_url')) {
    require_once dirname(__DIR__) . '/config/fvd_navigation_return.php';
}
require_once __DIR__ . '/config/db.php';
require_once dirname(__DIR__) . '/src/Services/DelegadoTorneoNotifService.php';

use FvdPortal\Services\DelegadoTorneoNotifService;

AuthService::ensureSession();
AuthService::requireLogin();

if (!AuthService::isDelegadoAsociacion()) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Solo delegados de asociación.';
    exit;
}

$base = rtrim((string) env('APP_BASE_PATH', ''), '/');
$pdo = fvd_db();
$did = (int) AuthService::userId();
$aidCtx = AuthService::idAsociacion();
$aid = ($aidCtx !== null && (int) $aidCtx > 0) ? (int) $aidCtx : null;

$notifId = isset($_GET['notif_id']) ? (int) $_GET['notif_id'] : 0;
$tokenRaw = isset($_GET['token']) ? trim((string) $_GET['token']) : '';
$row = null;

if ($tokenRaw !== '') {
    $byTok = DelegadoTorneoNotifService::notificacionPorAccessToken($pdo, $tokenRaw);
    if ($byTok !== null) {
        $nid = (int) ($byTok['id'] ?? 0);
        if ($nid > 0) {
            $row = DelegadoTorneoNotifService::notificacionPorIdParaDelegado($pdo, $nid, $did, $aid);
        }
    }
} elseif ($notifId > 0) {
    $row = DelegadoTorneoNotifService::notificacionPorIdParaDelegado($pdo, $notifId, $did, $aid);
} elseif (isset($_GET['ultima']) && (string) $_GET['ultima'] === '1') {
    $row = DelegadoTorneoNotifService::ultimaNoVista($pdo, $did, $aid);
}

if ($row === null) {
    $h = AuthService::homeUrl();
    header('Location: ' . $h . (str_contains($h, '?') ? '&' : '?') . 'msg=notif_no');
    exit;
}

$tid = (int) ($row['torneo_id'] ?? 0);
if ($tid <= 0) {
    $h = AuthService::homeUrl();
    header('Location: ' . $h . (str_contains($h, '?') ? '&' : '?') . 'msg=notif_no');
    exit;
}

AuthService::setDelegadoTorneoContext($tid);
DelegadoTorneoNotifService::marcarVisto($pdo, (int) $row['id'], $did, $aid);

$dest = url('fvdmasteradmin/delegado_dashboard.php');
$dest = fvd_append_embed_to_url($dest);
header('Location: ' . $dest);
exit;
