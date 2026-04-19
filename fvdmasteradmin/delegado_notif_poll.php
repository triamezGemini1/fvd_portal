<?php

declare(strict_types=1);

require_once __DIR__ . '/services/AuthService.php';
AuthService::ensureSession();
header('Content-Type: application/json; charset=utf-8');

if (!AuthService::isDelegadoAsociacion()) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'No autorizado'], JSON_UNESCAPED_UNICODE);

    exit;
}

require_once __DIR__ . '/config/db.php';
require_once dirname(__DIR__) . '/src/Services/DelegadoTorneoNotifService.php';

$pdo = fvd_db();
$delegadoUid = (int) AuthService::userId();
$delegAid = AuthService::idAsociacion();
$delegAidInt = ($delegAid !== null && (int) $delegAid > 0) ? (int) $delegAid : null;

$tidCtx = 0;
$cCtx = AuthService::delegadoTorneoContextId();
if ($cCtx !== null && (int) $cCtx > 0) {
    $tidCtx = (int) $cCtx;
}

$unread = \FvdPortal\Services\DelegadoTorneoNotifService::contarNoVistas($pdo, $delegadoUid, $delegAidInt);
$pendingAccept = false;
if ($tidCtx > 0) {
    $pendingAccept = \FvdPortal\Services\DelegadoTorneoNotifService::invitacionPendienteDeAceptacion(
        $pdo,
        $delegadoUid,
        $tidCtx,
        $delegAidInt
    );
}

echo json_encode(
    [
        'ok' => true,
        'unread' => $unread,
        'pendingAccept' => $pendingAccept,
        'torneoId' => $tidCtx,
    ],
    JSON_UNESCAPED_UNICODE
);
