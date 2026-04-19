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

$torneoId = isset($_POST['torneo_id']) ? (int) $_POST['torneo_id'] : (isset($_GET['torneo_id']) ? (int) $_GET['torneo_id'] : 0);
if ($torneoId <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'torneo_id requerido'], JSON_UNESCAPED_UNICODE);

    exit;
}

require_once __DIR__ . '/config/db.php';
require_once dirname(__DIR__) . '/src/Services/DelegadoTorneoNotifService.php';

$pdo = fvd_db();
$delegadoUid = (int) AuthService::userId();
$delegAid = AuthService::idAsociacion();
$delegAidInt = ($delegAid !== null && (int) $delegAid > 0) ? (int) $delegAid : null;

$ok = \FvdPortal\Services\DelegadoTorneoNotifService::marcarInvitacionAceptada($pdo, $delegadoUid, $torneoId, $delegAidInt);

echo json_encode(['ok' => $ok], JSON_UNESCAPED_UNICODE);
