<?php

declare(strict_types=1);

/**
 * Activa el contexto de administración del torneo para el delegado y abre el panel del evento.
 *
 * Parámetros: token (enlace de la tarjeta PDF), notif_id o ultima=1 (última notificación no vista).
 */

require_once __DIR__ . '/services/AuthService.php';
require_once dirname(__DIR__) . '/config/paths.php';
require_once dirname(__DIR__) . '/config/fvd_navigation_return.php';
require_once __DIR__ . '/config/db.php';
require_once dirname(__DIR__) . '/src/Services/DelegadoTorneoNotifService.php';
require_once dirname(__DIR__) . '/src/Services/TorneoFinalizacionService.php';

use FvdPortal\Services\DelegadoTorneoNotifService;
use FvdPortal\Services\TorneoFinalizacionService;

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
$fallbackHome = AuthService::homeUrl();
$originReturn = fvd_return_from_request();
if ($originReturn === null && isset($_SERVER['HTTP_REFERER']) && is_string($_SERVER['HTTP_REFERER'])) {
    $originReturn = fvd_return_sanitize($_SERVER['HTTP_REFERER']);
}
$originRedirect = $originReturn ?? $fallbackHome;
$withMsg = static function (string $url, string $msg): string {
    return $url . (str_contains($url, '?') ? '&' : '?') . 'msg=' . rawurlencode($msg);
};

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
    header('Location: ' . $withMsg($originRedirect, 'notif_no'));
    exit;
}

$tid = (int) ($row['torneo_id'] ?? 0);
if ($tid <= 0) {
    header('Location: ' . $withMsg($originRedirect, 'notif_no'));
    exit;
}

if (TorneoFinalizacionService::columnaFinalizadoExiste($pdo)) {
    try {
        $stC = $pdo->prepare('SELECT finalizado_en FROM torneosact WHERE torneo = :t LIMIT 1');
        $stC->execute([':t' => $tid]);
        $finCol = $stC->fetchColumn();
        if ($finCol !== false && $finCol !== null && trim((string) $finCol) !== '') {
            header('Location: ' . $withMsg($originRedirect, 'torneo_cerrado'));
            exit;
        }
    } catch (Throwable $e) {
        error_log('[delegado_entrar_torneo] finalizado_en: ' . $e->getMessage());
    }
}

$gidTorneo = (int) ($row['grupo_evento_id'] ?? 0);

$nid = (int) ($row['id'] ?? 0);
$accessTok = DelegadoTorneoNotifService::asegurarAccessTokenParaNotificacion($pdo, $nid);
if ($accessTok === null || $accessTok === '') {
    header('Location: ' . $withMsg($originRedirect, 'notif_no'));
    exit;
}

AuthService::setDelegadoTorneoContext($tid);
if ($gidTorneo > 0) {
    AuthService::setDelegadoCampeonatoGrupo($gidTorneo);
    $fdMarca = substr((string) ($row['fechator'] ?? ''), 0, 10);
    $ecMarca = isset($row['es_campeonato']) ? (int) $row['es_campeonato'] : null;
    if ($fdMarca !== '' && $ecMarca !== null) {
        DelegadoTorneoNotifService::marcarVistoTodasMismoGrupo($pdo, $did, $aid, $gidTorneo, $fdMarca, $ecMarca);
    } else {
        DelegadoTorneoNotifService::marcarVistoTodasMismoGrupo($pdo, $did, $aid, $gidTorneo);
    }
} else {
    DelegadoTorneoNotifService::marcarVisto($pdo, $nid, $did, $aid);
}

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
$dest = fvd_return_append_to_url($dest, $originRedirect);
header('Location: ' . $dest);
exit;
