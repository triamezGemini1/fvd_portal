<?php

declare(strict_types=1);

$adminScript = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . 'modules' . DIRECTORY_SEPARATOR . 'torneo_inscripcion' . DIRECTORY_SEPARATOR . 'index.php';
if (!is_file($adminScript)) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Falta admin/modules/torneo_inscripcion. Ruta esperada: ' . $adminScript;
    exit;
}
require $adminScript;
