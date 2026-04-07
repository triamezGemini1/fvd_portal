<?php

declare(strict_types=1);

$adminScript = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . 'modules' . DIRECTORY_SEPARATOR . 'atletas' . DIRECTORY_SEPARATOR . 'traspaso_api.php';
if (!is_file($adminScript)) {
    http_response_code(500);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['ok' => false, 'error' => 'API no disponible.'], JSON_UNESCAPED_UNICODE);
    exit;
}
require $adminScript;
