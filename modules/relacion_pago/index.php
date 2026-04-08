<?php

declare(strict_types=1);

/**
 * Entrada pública /modules/relacion_pago/ → implementación en fvdmasteradmin/modules/.
 */
$impl = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR
    . 'fvdmasteradmin' . DIRECTORY_SEPARATOR . 'modules' . DIRECTORY_SEPARATOR . 'relacion_pago'
    . DIRECTORY_SEPARATOR . 'index.php';
$impl = realpath($impl) ?: $impl;
if (!is_readable($impl)) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'No se encuentra el módulo de pagos. Ruta esperada: ' . $impl;
    exit;
}
require $impl;
