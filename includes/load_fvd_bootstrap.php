<?php

declare(strict_types=1);

/**
 * Punto único para cargar fvdmasteradmin/bootstrap.php.
 *
 * La carpeta del núcleo DEBE llamarse "fvdmasteradmin" (nombre fijo en el repositorio).
 * No la sustituya por el segmento de URL (p. ej. fvd_portal_beta): eso provoca error fatal.
 */
$__fvdProjectRoot = dirname(__DIR__);
$__fvdBootstrap = $__fvdProjectRoot . '/fvdmasteradmin/bootstrap.php';
if (!is_file($__fvdBootstrap)) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Instalación incompleta: no se encontró fvdmasteradmin/bootstrap.php. ';
    echo 'Suba el proyecto completo; la carpeta fvdmasteradmin debe existir junto a index.php. Buscado en: ' . $__fvdBootstrap;
    exit;
}
require_once $__fvdBootstrap;
