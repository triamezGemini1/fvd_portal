<?php

declare(strict_types=1);

/**
 * Reporte financiero por asociación: resumen de indicadores/costos, pagos y listados por métrica.
 * Administración general FVD (super admin) o administrador de asociación (solo su asociación).
 */

require_once __DIR__ . '/services/AuthService.php';
AuthService::ensureSession();
AuthService::requireLogin();

$projRoot = dirname(__DIR__);
require_once $projRoot . '/config/paths.php';
require_once __DIR__ . '/config/db.php';

$isFvd = AuthService::isSuperAdmin();
$isAso = AuthService::role() === AuthService::ROLE_ASO_ADMIN;

if (!$isFvd && !$isAso) {
    http_response_code(403);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="es"><head><meta charset="utf-8"><title>Acceso denegado</title></head><body><p>No tiene permiso para ver este informe.</p></body></html>';
    exit;
}

$aid = 0;
if ($isFvd) {
    $aid = isset($_GET['id']) ? (int) $_GET['id'] : 0;
} else {
    $mine = AuthService::idAsociacion();
    $aid = $mine !== null ? (int) $mine : 0;
}

$detalleRaw = isset($_GET['detalle']) ? (string) $_GET['detalle'] : '';
$metricasPermitidas = ['afiliacion', 'anualidad', 'carnet', 'traspaso', 'inscripcion'];
$detalle = \in_array($detalleRaw, $metricasPermitidas, true) ? $detalleRaw : '';

$appBase = rtrim((string) (function_exists('env') ? env('APP_BASE_PATH', '') : ''), '/');
if ($appBase !== '' && preg_match('#/fvdmasteradmin$#i', $appBase)) {
    $appBase = rtrim((string) preg_replace('#/fvdmasteradmin$#i', '', $appBase), '/');
}
if ($appBase === '') {
    $sn = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
    $pos = strpos($sn, '/fvdmasteradmin/');
    if ($pos > 0) {
        $appBase = rtrim(substr($sn, 0, $pos), '/');
    }
}

$pdo = fvd_db();
$fvd_rep_fin_embed = false;

$fvd_page_title = 'Reporte financiero';
if ($aid > 0) {
    try {
        $st = $pdo->prepare('SELECT nombre FROM asociaciones WHERE id = :id LIMIT 1');
        $st->execute([':id' => $aid]);
        $nm = trim((string) ($st->fetchColumn() ?: ''));
        if ($nm !== '') {
            $fvd_page_title = 'Reporte financiero — ' . $nm;
        }
    } catch (Throwable $e) {
        // título genérico
    }
}
$fvd_required_roles = [AuthService::ROLE_FVD_ADMIN, AuthService::ROLE_ASO_ADMIN];
require __DIR__ . '/includes/layout_header.php';

if ($aid <= 0) {
    http_response_code(404);
    echo '<p class="fvd-mod-msg">Asociación no indicada o no disponible.</p>';
    require __DIR__ . '/includes/layout_footer.php';
    exit;
}

require __DIR__ . '/includes/partial_asociacion_reporte_financiero.php';

require __DIR__ . '/includes/layout_footer.php';
