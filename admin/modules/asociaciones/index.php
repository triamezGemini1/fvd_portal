<?php

declare(strict_types=1);

/**
 * Listado/formulario clásico reemplazado por la UI en {@see ../../asociaciones.php} (Vite + API).
 */
require_once dirname(__DIR__, 2) . '/_init.php';
fvd_admin_require_roles();

$qs = isset($_SERVER['QUERY_STRING']) ? (string) $_SERVER['QUERY_STRING'] : '';
$dest = url('asociaciones.php');
if ($qs !== '') {
    $dest .= (str_contains($dest, '?') ? '&' : '?') . $qs;
}
header('Location: ' . $dest, true, 302);
exit;
