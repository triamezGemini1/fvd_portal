<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/_init.php';

header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Método no permitido.'], JSON_UNESCAPED_UNICODE);

    exit;
}

if (!AuthService::checkAccess([AuthService::ROLE_ASO_ADMIN, AuthService::ROLE_DELEGADO_ASOC])) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Sin permisos.'], JSON_UNESCAPED_UNICODE);

    exit;
}

$asocSes = AuthService::idAsociacion();
if ($asocSes === null || (int) $asocSes <= 0) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Sin asociación en sesión.'], JSON_UNESCAPED_UNICODE);

    exit;
}

$raw = (string) file_get_contents('php://input');
$payload = json_decode($raw, true);
if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'JSON inválido.'], JSON_UNESCAPED_UNICODE);

    exit;
}

$tipo = isset($payload['tipo']) ? trim(strtolower((string) $payload['tipo'])) : '';
$atletaId = isset($payload['atleta_id']) ? (int) $payload['atleta_id'] : 0;
$dest = isset($payload['asociacion_destino_id']) && $payload['asociacion_destino_id'] !== '' ? (int) $payload['asociacion_destino_id'] : null;
$nota = isset($payload['nota']) ? (string) $payload['nota'] : null;

if ($atletaId <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'atleta_id inválido.'], JSON_UNESCAPED_UNICODE);

    exit;
}

require_once FVD_PROJECT_ROOT . '/src/Services/DelegadoSolicitudService.php';
require_once FVD_PROJECT_ROOT . '/src/Services/DelegadoTorneoVentanasService.php';

$pdo = fvd_db();

try {
    if (\FvdPortal\Services\DelegadoTorneoVentanasService::aplicaRestriccionDelegado()) {
        $ctxT = \AuthService::delegadoTorneoContextId();
        if ($ctxT === null || (int) $ctxT <= 0) {
            throw new \RuntimeException(
                'Debe seleccionar el torneo desde el panel (entrada por invitación) para enviar solicitudes según el calendario del evento.'
            );
        }
        \FvdPortal\Services\DelegadoTorneoVentanasService::assertPuedeFase1Administrativa($pdo, (int) $ctxT);
    }
    \FvdPortal\Services\DelegadoSolicitudService::ensureTable($pdo);
    \FvdPortal\Services\DelegadoSolicitudService::crear($pdo, $tipo, $atletaId, $dest, $nota);
} catch (Throwable $e) {
    $msg = $e->getMessage();
    $dup = str_contains($msg, 'Ya existe una solicitud pendiente');
    http_response_code($dup ? 409 : 422);
    echo json_encode(['ok' => false, 'error' => $msg, 'duplicate' => $dup], JSON_UNESCAPED_UNICODE);

    exit;
}

echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
