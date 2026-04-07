<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/_init.php';
require_once FVD_PROJECT_ROOT . '/src/Services/TraspasoService.php';

use FvdPortal\Services\TraspasoService;

fvd_admin_require_roles();

header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Método no permitido.'], JSON_UNESCAPED_UNICODE);

    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw !== false ? $raw : '', true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'JSON inválido.'], JSON_UNESCAPED_UNICODE);

    exit;
}

$atletaId = isset($data['atleta_id']) ? (int) $data['atleta_id'] : 0;
$asocId = isset($data['asociacion_id']) ? (int) $data['asociacion_id'] : 0;

try {
    TraspasoService::ejecutar(fvd_db(), $atletaId, $asocId, AuthService::userId());
    echo json_encode([
        'ok'                 => true,
        'marcador_atletas'   => 'traspaso=1',
        'stats_note'         => 'Los gráficos del dashboard usan datos en vivo; recargue la página del dashboard para actualizar la torta.',
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
