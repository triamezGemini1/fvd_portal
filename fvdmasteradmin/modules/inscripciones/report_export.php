<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/_init.php';
fvd_module_require_roles();

require_once FVD_PROJECT_ROOT . '/src/Services/ReportService.php';
require_once FVD_PROJECT_ROOT . '/src/Services/InscripcionesTorneoReportService.php';
require_once FVD_MASTER_ROOT . '/includes/fvd_asociacion_helpers.php';
require_once __DIR__ . '/../deuda_asociacion/Controller.php';

use FvdPortal\Services\InscripcionesTorneoReportService;
use FvdPortal\Services\ReportService;

$tipo = isset($_GET['tipo']) ? trim((string) $_GET['tipo']) : '';
$torneoId = max(0, (int) ($_GET['torneo_id'] ?? 0));
$asocIdGet = max(0, (int) ($_GET['asociacion_id'] ?? 0));
$inlineView = isset($_GET['inline']) && (string) $_GET['inline'] === '1';

if (AuthService::role() === AuthService::ROLE_FVD_ADMIN) {
    $asocId = $asocIdGet;
} else {
    $mine = AuthService::idAsociacion();
    $asocId = $mine !== null && (int) $mine > 0 ? (int) $mine : 0;
}

if ($torneoId <= 0 || $asocId <= 0) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Indique torneo_id válido y asociación (asociacion_id obligatorio para administrador FVD).';
    exit;
}

$pdo = fvd_db();
$ctrlDeuda = new DeudaAsociacionController();

$stT = $pdo->prepare('SELECT nombre FROM torneosact WHERE torneo = :t LIMIT 1');
$stT->execute([':t' => $torneoId]);
$tRow = $stT->fetch(PDO::FETCH_ASSOC);
$torneoNombre = $tRow !== false ? (string) ($tRow['nombre'] ?? ('Torneo #' . $torneoId)) : ('Torneo #' . $torneoId);

$stA = $pdo->prepare('SELECT nombre, logo FROM asociaciones WHERE id = :id LIMIT 1');
$stA->execute([':id' => $asocId]);
$aRow = $stA->fetch(PDO::FETCH_ASSOC);
$asocNombre = $aRow !== false ? fvd_asoc_nombre_sin_prefijo((string) ($aRow['nombre'] ?? '')) : ('Asociación #' . $asocId);

$repEncabezadoHtml = '';
$logoUri = is_array($aRow) ? fvd_asociacion_logo_data_uri(FVD_PROJECT_ROOT, isset($aRow['logo']) ? (string) $aRow['logo'] : null) : null;
$cellLogo = $logoUri !== null
    ? '<div class="fvd-rep-asoc-head__logo"><img src="' . htmlspecialchars($logoUri, ENT_QUOTES, 'UTF-8') . '" alt=""></div>'
    : '<div class="fvd-rep-asoc-head__logo"></div>';
$cellTxt = $asocNombre !== ''
    ? '<div class="fvd-rep-asoc-head__txt">' . htmlspecialchars($asocNombre, ENT_QUOTES, 'UTF-8') . '</div>'
    : '';
$repEncabezadoHtml = '<div class="fvd-rep-asoc-head">' . $cellLogo . $cellTxt . '</div>';

$ts = date('Y-m-d_His');
$html = '';
$filename = 'reporte_' . $ts;
$title = 'Reporte';
$rowCount = null;
$pagos = [];
$pdfLandscape = true;

if (in_array($tipo, InscripcionesTorneoReportService::TIPOS_ATLETAS, true)) {
    $rows = InscripcionesTorneoReportService::atletasParaReporte($pdo, $torneoId, $asocId, $tipo);
    $rowCount = count($rows);
    $titleMap = [
        'inscritos' => 'Atletas inscritos al torneo',
        'carnets' => 'Atletas con carnet solicitado',
        'afiliados' => 'Nuevos afiliados (marca afiliación)',
    ];
    $title = $titleMap[$tipo] . ' — ' . $torneoNombre;
    $html = ReportService::buildAtletasPdfHtml($rows, $title, $repEncabezadoHtml, true);
    $filename = 'reporte_' . $tipo . '_' . $torneoId . '_' . $ts;
} elseif ($tipo === 'finanzas_deuda') {
    $pdfLandscape = false;
    $deudaRow = $ctrlDeuda->find($torneoId, $asocId);
    $tieneEur = false;
    try {
        $stc = $pdo->query("SHOW COLUMNS FROM deuda_asociaciones LIKE 'monto_total_eur'");
        $tieneEur = $stc && $stc->fetch(PDO::FETCH_ASSOC) !== false;
    } catch (Throwable $e) {
        $tieneEur = false;
    }
    $html = InscripcionesTorneoReportService::buildDeudaConceptosHtml($torneoNombre, $asocNombre, $deudaRow, $tieneEur);
    $filename = 'deuda_conceptos_' . $torneoId . '_' . $ts;
    $title = 'Deuda por concepto';
} elseif ($tipo === 'finanzas_pagos') {
    $pdfLandscape = false;
    $pagos = $ctrlDeuda->listPagosRecibos($torneoId, $asocId);
    $html = InscripcionesTorneoReportService::buildPagosListadoHtml($torneoNombre, $asocNombre, $pagos);
    $filename = 'pagos_' . $torneoId . '_' . $ts;
    $title = 'Pagos';
} elseif ($tipo === 'finanzas_estado') {
    $pdfLandscape = false;
    $deudaRow = $ctrlDeuda->find($torneoId, $asocId);
    $pagos = $ctrlDeuda->listPagosRecibos($torneoId, $asocId);
    $pagadoEur = 0.0;
    $pagadoBs = 0.0;
    foreach ($pagos as $p) {
        $pagadoEur += (float) ($p['monto_dolares'] ?? 0);
        $pagadoBs += (float) ($p['monto_total'] ?? 0);
    }
    $tieneEur = false;
    try {
        $stc = $pdo->query("SHOW COLUMNS FROM deuda_asociaciones LIKE 'monto_total_eur'");
        $tieneEur = $stc && $stc->fetch(PDO::FETCH_ASSOC) !== false;
    } catch (Throwable $e) {
        $tieneEur = false;
    }
    $saldoEur = 0.0;
    $saldoBs = 0.0;
    if ($deudaRow !== null) {
        if ($tieneEur) {
            $deudaEur = (float) ($deudaRow['monto_total_eur'] ?? 0);
            $saldoEur = max(0.0, round($deudaEur - $pagadoEur, 2));
        } else {
            $deudaBs = (float) ($deudaRow['monto_total'] ?? 0);
            $saldoBs = max(0.0, round($deudaBs - $pagadoBs, 2));
        }
    }

    $detalleConceptos = [];
    if ($deudaRow !== null) {
        try {
            $detalleConceptos = $ctrlDeuda->detallePorConceptos($torneoId, $asocId, null);
        } catch (Throwable $e) {
            $detalleConceptos = [];
        }
    }
    $html = InscripcionesTorneoReportService::buildEstadoCuentaHtml(
        $torneoNombre,
        $asocNombre,
        $deudaRow,
        $pagos,
        $detalleConceptos,
        round($pagadoEur, 2),
        $saldoEur,
        $tieneEur,
        round($pagadoBs, 2),
        $saldoBs
    );
    $filename = 'estado_cuenta_' . $torneoId . '_' . $ts;
    $title = 'Estado de cuenta';
} else {
    http_response_code(400);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Parámetro tipo no válido.';
    exit;
}

$pdf = ReportService::renderPdfWithDompdfIfAvailable($html, $pdfLandscape);
if ($pdf !== null && $pdf !== '') {
    $body = $pdf;
    $filename .= '.pdf';
    $mime = 'application/pdf';
} else {
    $body = $html;
    $filename .= '.html';
    $mime = 'text/html; charset=UTF-8';
}

$zipRows = $rowCount !== null ? (int) $rowCount : count($pagos);
$pack = $inlineView
    ? ['body' => $body, 'filename' => $filename, 'mime' => $mime]
    : ReportService::packageWithOptionalZip($body, $filename, $mime, $zipRows);

$dispType = $inlineView ? 'inline' : 'attachment';
$disp = $dispType . '; filename="' . str_replace(['"', "\r", "\n"], '', $pack['filename']) . '"';
header('Content-Type: ' . $pack['mime']);
header('Content-Disposition: ' . $disp);
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, no-cache, must-revalidate');

echo $pack['body'];
