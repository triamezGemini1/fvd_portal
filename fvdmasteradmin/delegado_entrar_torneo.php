<?php

declare(strict_types=1);

/**
 * Activa el contexto de administración del torneo para el delegado y abre el panel del evento.
 *
 * Parámetros: token (enlace de la tarjeta PDF), notif_id o ultima=1 (última notificación no vista).
 */

require_once __DIR__ . '/services/AuthService.php';
require_once dirname(__DIR__) . '/config/paths.php';
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
    if ($row !== null) {
        error_log('[delegado_entrar_torneo] Delegado detectado con token en URL: ' . $tokenRaw);
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

$nid = (int) ($row['id'] ?? 0);
$accessTok = DelegadoTorneoNotifService::asegurarAccessTokenParaNotificacion($pdo, $nid);
if ($accessTok === null || $accessTok === '') {
    $h = AuthService::homeUrl();
    header('Location: ' . $h . (str_contains($h, '?') ? '&' : '?') . 'msg=notif_no');
    exit;
}

AuthService::setDelegadoTorneoContext($tid);
DelegadoTorneoNotifService::marcarVisto($pdo, $nid, $did, $aid);

$_SESSION['fvd_master_delegado_notif_token'] = $accessTok;
error_log('[delegado_entrar_torneo] Sesión fvd_master_delegado_notif_token fijada; token=' . $accessTok);

$mp = url('fvdmasteradmin/master_panel.php');
$q = [
    'token'            => $accessTok,
    'embedded'         => '1',
    'fvd_master_embed' => '1',
    'ctx_torneo'       => $tid,
];
$dest = $mp . '?' . http_build_query($q);
header('Location: ' . $dest);
exit;
