<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/_init.php';
require_once FVD_PROJECT_ROOT . '/config/paths.php';
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

$atletaId = isset($_POST['atleta_id']) ? (int) $_POST['atleta_id'] : 0;
if ($atletaId <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'atleta_id no válido.'], JSON_UNESCAPED_UNICODE);

    exit;
}

try {
    $svc = new FvdAdminService();
    $svc->atletasActualizarFoto($atletaId, $_FILES);
    $row = $svc->atletasFind($atletaId);
    if ($row === null) {
        throw new RuntimeException('No se pudo leer el atleta.');
    }
    $row = CarnetService::enriquecerAsociacion(fvd_db(), $row);
    $card = CarnetService::prepararTarjeta($row, FVD_PROJECT_ROOT);
    echo json_encode([
        'ok'       => true,
        'foto_url' => $card['foto_url'],
        'card'     => $card,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
