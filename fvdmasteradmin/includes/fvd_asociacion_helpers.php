<?php

declare(strict_types=1);

/**
 * Nombre corto para cabeceras (quita prefijos habituales).
 */
function fvd_asoc_nombre_sin_prefijo(string $nombre): string
{
    $n = trim($nombre);
    if ($n === '') {
        return '';
    }
    $prefixes = [
        'Asociación de ', 'Asociación ', 'ASOCIACIÓN ', 'ASOCIACION ',
        'Aso. ', 'ASO. ', 'ACDP ', 'Club ',
    ];
    foreach ($prefixes as $pref) {
        $len = strlen($pref);
        if ($len > 0 && strncasecmp($n, $pref, $len) === 0) {
            return trim(substr($n, $len));
        }
    }

    return $n;
}

/**
 * URL pública del logo de asociación (columna `asociaciones.logo`).
 */
function fvd_asociacion_logo_public_url(string $appBase, string $projRoot, ?string $logo): ?string
{
    if ($logo === null) {
        return null;
    }
    $logo = trim(str_replace('\\', '/', $logo));
    if ($logo === '') {
        return null;
    }
    if (preg_match('#^https?://#i', $logo) === 1) {
        return $logo;
    }
    if (strpos($logo, '..') !== false) {
        return null;
    }
    $base = rtrim($appBase, '/');
    $uploadsDir = rtrim($projRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'uploads';
    $rel = ltrim($logo, '/');
    $full = $uploadsDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
    if (!is_file($full)) {
        $baseFile = $uploadsDir . DIRECTORY_SEPARATOR . basename($rel);
        if (!is_file($baseFile)) {
            return null;
        }
        $rel = basename($rel);
    }

    return $base . '/uploads/' . str_replace(DIRECTORY_SEPARATOR, '/', $rel);
}

/**
 * data:image/...;base64,... para PDF si el archivo existe bajo uploads/.
 */
function fvd_asociacion_logo_data_uri(string $projRoot, ?string $logo): ?string
{
    if ($logo === null) {
        return null;
    }
    $logo = trim(str_replace('\\', '/', $logo));
    if ($logo === '' || strpos($logo, '..') !== false) {
        return null;
    }
    $uploadsDir = rtrim($projRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'uploads';
    $full = $uploadsDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, ltrim($logo, '/'));
    if (!is_file($full)) {
        $full = $uploadsDir . DIRECTORY_SEPARATOR . basename($logo);
    }
    if (!is_file($full) || !is_readable($full)) {
        return null;
    }
    $ext = strtolower(pathinfo($full, PATHINFO_EXTENSION));
    if ($ext === 'png') {
        $mime = 'image/png';
    } elseif ($ext === 'jpg' || $ext === 'jpeg') {
        $mime = 'image/jpeg';
    } elseif ($ext === 'gif') {
        $mime = 'image/gif';
    } elseif ($ext === 'webp') {
        $mime = 'image/webp';
    } else {
        $mime = 'application/octet-stream';
    }
    $raw = @file_get_contents($full);
    if ($raw === false || $raw === '') {
        return null;
    }

    return 'data:' . $mime . ';base64,' . base64_encode($raw);
}
