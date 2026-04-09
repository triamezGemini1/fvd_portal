<?php

declare(strict_types=1);

$adminScript = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . 'modules' . DIRECTORY_SEPARATOR . 'atletas' . DIRECTORY_SEPARATOR . 'reporte_marcas_atletas.php';
if (!is_file($adminScript)) {
    http_response_code(500);
    echo 'Reporte no disponible.';
    exit;
}
require $adminScript;
