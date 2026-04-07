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

$notifId = isset($_GET['notif_id']) ? (int) $_GET['notif_id'] : 0;
$tokenRaw = isset($_GET['token']) ? trim((string) $_GET['token']) : '';
$row = null;

if ($tokenRaw !== '') {
    $byTok = DelegadoTorneoNotifService::notificacionPorAccessToken($pdo, $tokenRaw);
    if ($byTok !== null && (int) ($byTok['delegado_id'] ?? 0) === $did) {
        $row = $byTok;
    }
} elseif ($notifId > 0) {
    $row = DelegadoTorneoNotifService::notificacionPorIdParaDelegado($pdo, $notifId, $did);
} elseif (isset($_GET['ultima']) && (string) $_GET['ultima'] === '1') {
    $row = DelegadoTorneoNotifService::ultimaNoVista($pdo, $did);
}

if ($row === null) {
    header('Location: ' . $base . '/fvdmasteradmin/index.php?msg=notif_no');
    exit;
}

$tid = (int) ($row['torneo_id'] ?? 0);
if ($tid <= 0) {
    header('Location: ' . $base . '/fvdmasteradmin/index.php?msg=notif_no');
    exit;
}

AuthService::setDelegadoTorneoContext($tid);
DelegadoTorneoNotifService::marcarVisto($pdo, (int) $row['id'], $did);

$dest = admin_module_url('torneos/index.php?action=evento&id=' . $tid);
header('Location: ' . $dest);
exit;
