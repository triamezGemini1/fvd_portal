<?php
declare(strict_types=1);

/**
 * Entrega del archivo de invitación de un torneo publicado (solo si la ficha es visible en landing).
 *
 * - Sin parámetro extra: Content-Disposition attachment (descarga).
 * - ?inline=1 : Content-Disposition inline (lectura en el navegador al solicitarlo, p. ej. pestaña nueva).
 */
require_once __DIR__ . '/fvdmasteradmin/bootstrap.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$inline = isset($_GET['inline']) && (string) $_GET['inline'] === '1';
if ($id <= 0) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Solicitud no válida.';
    exit;
}

try {
    $torneo = PublicSiteData::torneoPublicoPorId($id);
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'No se pudo verificar el evento.';
    exit;
}

if ($torneo === null || empty($torneo['invitacion'])) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Invitación no disponible.';
    exit;
}

$base = basename((string) $torneo['invitacion']);
if ($base === '' || str_contains($base, '..')) {
    http_response_code(404);
    exit;
}

$uploadsDir = FVD_PROJECT_ROOT . DIRECTORY_SEPARATOR . 'uploads';
$path = $uploadsDir . DIRECTORY_SEPARATOR . $base;
$realFile = realpath($path);
$realDir = realpath($uploadsDir);
if ($realFile === false || $realDir === false || !str_starts_with($realFile, $realDir) || !is_file($realFile)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Archivo no encontrado.';
    exit;
}

$ext = strtolower(pathinfo($realFile, PATHINFO_EXTENSION));
$mimeMap = [
    'pdf' => 'application/pdf',
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'gif' => 'image/gif',
    'webp' => 'image/webp',
    'svg' => 'image/svg+xml',
    'bmp' => 'image/bmp',
];
$mime = $mimeMap[$ext] ?? 'application/octet-stream';
if (class_exists('finfo')) {
    $fi = new finfo(FILEINFO_MIME_TYPE);
    $detected = $fi->file($realFile);
    if (is_string($detected) && $detected !== '' && $detected !== 'application/octet-stream') {
        $mime = $detected;
    }
}

$safeName = preg_replace('/[^a-zA-Z0-9._-]+/', '_', $base) ?: 'invitacion';

header('Content-Type: ' . $mime);
header('Content-Length: ' . (string) filesize($realFile));
if ($inline) {
    header('Content-Disposition: inline; filename="' . $safeName . '"');
} else {
    header('Content-Disposition: attachment; filename="' . $safeName . '"');
}
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=3600');

readfile($realFile);
