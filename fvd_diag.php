<?php

declare(strict_types=1);

/**
 * Diagnóstico: confirma que Apache/PHP sirven ESTA carpeta del proyecto y la antigüedad de archivos clave.
 *
 * Acceso:
 * - Con APP_DEBUG=true en .env: abrir /fvd_diag.php (o …/fvd_portal/fvd_diag.php según su base).
 * - Sin APP_DEBUG: añada en .env  FVD_DIAG_SECRET=una_clave_larga  y abra  /fvd_diag.php?key=una_clave_larga
 *
 * Elimine o renombre este archivo en producción si no lo necesita (menor superficie).
 */

require_once __DIR__ . '/config/env.php';
Env::load();

$debug = Env::getBool('APP_DEBUG', false);
$secret = trim((string) Env::get('FVD_DIAG_SECRET', ''));
$key = isset($_GET['key']) ? (string) $_GET['key'] : '';
$allowed = $debug || ($secret !== '' && hash_equals($secret, $key));

if (!$allowed) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Not found.';
    exit;
}

header('Content-Type: text/plain; charset=UTF-8');
header('X-Content-Type-Options: nosniff');

$root = realpath(__DIR__) ?: __DIR__;
echo "FVD_DIAG OK\n";
echo 'DOCUMENT_ROOT (si existe): ' . (isset($_SERVER['DOCUMENT_ROOT']) ? (string) $_SERVER['DOCUMENT_ROOT'] : '(no)') . "\n";
echo 'SCRIPT_FILENAME: ' . (isset($_SERVER['SCRIPT_FILENAME']) ? (string) $_SERVER['SCRIPT_FILENAME'] : '(no)') . "\n";
echo "Proyecto (raíz): {$root}\n";
echo 'APP_BASE_PATH: ' . (string) Env::get('APP_BASE_PATH', '') . "\n";
echo 'APP_DEPLOYMENT_DISABLED: ' . (Env::getBool('APP_DEPLOYMENT_DISABLED', false) ? 'true (esta copia responde 503 en rutas normales)' : 'false') . "\n";

$checks = [
    'reporte_indicadores (reset marcadores)' => $root . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . 'modules' . DIRECTORY_SEPARATOR . 'atletas' . DIRECTORY_SEPARATOR . 'reporte_indicadores.php',
    'paths.php (gate deshabilitar copia)'      => $root . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'paths.php',
    'deployment_disabled.php'                 => $root . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'deployment_disabled.php',
];

foreach ($checks as $label => $path) {
    echo "{$label}: ";
    if (!is_file($path)) {
        echo "NO ENCONTRADO → {$path}\n";
        continue;
    }
    echo 'OK, modificado ' . date('Y-m-d H:i:s', filemtime($path)) . " (servidor)\n";
}

echo "\nSi las fechas son viejas o falta reporte_indicadores, esta URL no es la carpeta donde actualizó el código.\n";
echo "Busque en el código fuente del informe el comentario: <!-- fvd indicadores: bloque reinicio masivo v2026-04 -->\n";
