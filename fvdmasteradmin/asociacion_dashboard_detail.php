<?php

declare(strict_types=1);

/**
 * JSON para el panel admin: detalle de una asociación (ficha, deudas, pagos) y enlaces a módulos.
 */

require_once __DIR__ . '/services/AuthService.php';
AuthService::ensureSession();
AuthService::requireLogin();

if (!AuthService::isSuperAdmin()) {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'Solo administración general FVD.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$projRoot = dirname(__DIR__);
require_once $projRoot . '/config/paths.php';
require_once __DIR__ . '/config/db.php';
require_once $projRoot . '/src/Services/StatsService.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id <= 0) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'ID no válido.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$pdo = fvd_db();
$data = \FvdPortal\Services\StatsService::dashboardDetalleAsociacion($pdo, $id);
if ($data === null || ($data['asociacion'] ?? null) === null) {
    http_response_code(404);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'Asociación no encontrada.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$asoc = $data['asociacion'];
$aid = (int) ($asoc['id'] ?? 0);
$urls = [
    'editar_asociacion' => admin_module_url('asociaciones/index.php?action=form&id=' . $aid),
    'lista_deudas'      => fvd_master_module_url('deuda_asociacion/index.php'),
    'lista_pagos'       => fvd_master_module_url('relacion_pago/index.php'),
];

$deudasOut = [];
foreach ($data['deudas'] as $d) {
    $tid = (int) ($d['torneo_id'] ?? 0);
    $deudasOut[] = array_merge($d, [
        'url_detalle' => fvd_master_module_url('deuda_asociacion/index.php?action=form&tid=' . $tid . '&aid=' . $aid),
    ]);
}

$pagosOut = [];
foreach ($data['pagos'] as $p) {
    $pid = (int) ($p['id'] ?? 0);
    $pagosOut[] = array_merge($p, [
        'url_detalle' => fvd_master_module_url('relacion_pago/index.php?action=form&id=' . $pid),
    ]);
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'ok'           => true,
    'asociacion'   => $asoc,
    'deudas'       => $deudasOut,
    'pagos'        => $pagosOut,
    'urls'         => $urls,
], JSON_UNESCAPED_UNICODE);
