<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/_init.php';
require_once FVD_PROJECT_ROOT . '/src/Services/CarnetService.php';

use FvdPortal\Services\CarnetService;

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
if (!is_array($data) || !isset($data['ids']) || !is_array($data['ids'])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Se requiere ids[].'], JSON_UNESCAPED_UNICODE);

    exit;
}

try {
    $n = CarnetService::emitirCarnet(fvd_db(), $data['ids']);
    echo json_encode([
        'ok'                  => true,
        'updated'             => $n,
        'marcador_atletas'    => 'carnet=1 (solicitado)',
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
