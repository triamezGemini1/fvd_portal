<?php

declare(strict_types=1);

/**
 * Descarga del PDF de invitación asociado a una notificación de torneo (solo el delegado dueño).
 */

require_once __DIR__ . '/services/AuthService.php';
require_once __DIR__ . '/config/db.php';
require_once dirname(__DIR__) . '/src/Services/DelegadoTorneoNotifService.php';

use FvdPortal\Services\DelegadoTorneoNotifService;

AuthService::ensureSession();
AuthService::requireLogin();

if (!AuthService::isDelegadoAsociacion()) {
    http_response_code(403);
    exit('Acceso denegado.');
}

$notifId = isset($_GET['notif_id']) ? (int) $_GET['notif_id'] : 0;
if ($notifId <= 0) {
    http_response_code(400);
    exit('Solicitud no válida.');
}

$pdo = fvd_db();
$did = (int) AuthService::userId();
$aidCtx = AuthService::idAsociacion();
$aid = ($aidCtx !== null && (int) $aidCtx > 0) ? (int) $aidCtx : null;
$row = DelegadoTorneoNotifService::notificacionPorIdParaDelegado($pdo, $notifId, $did, $aid);
if ($row === null) {
    http_response_code(404);
    exit('Notificación no encontrada.');
}

$file = '';
if (!empty($row['tarjeta_pdf']) && trim((string) $row['tarjeta_pdf']) !== '') {
    $file = trim((string) $row['tarjeta_pdf']);
}
if ($file === '') {
    $file = isset($row['invitacion_archivo']) ? trim((string) $row['invitacion_archivo']) : '';
}
if ($file === '') {
    http_response_code(404);
    exit('No hay PDF de invitación registrado para este aviso.');
}

$root = dirname(__DIR__);
$path = $root . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . basename($file);
if (!is_file($path) || !is_readable($path)) {
    http_response_code(404);
    exit('El archivo ya no está disponible.');
}

$ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
$mime = $ext === 'pdf' ? 'application/pdf' : 'application/octet-stream';

header('Content-Type: ' . $mime);
header('Content-Disposition: inline; filename="' . basename($file) . '"');
header('Cache-Control: private, max-age=0, must-revalidate');
header('Content-Length: ' . (string) filesize($path));
readfile($path);
exit;
