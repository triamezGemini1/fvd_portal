<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/_init.php';
require_once FVD_PROJECT_ROOT . '/src/Services/QueryHelper.php';
require_once FVD_PROJECT_ROOT . '/src/Services/ReportService.php';
require_once FVD_PROJECT_ROOT . '/src/Services/FvdAdminRevisionPendienteService.php';

use FvdPortal\Services\QueryHelper;
use FvdPortal\Services\ReportService;

fvd_admin_require_roles();

$format = isset($_GET['format']) ? strtolower(trim((string) $_GET['format'])) : '';
if ($format === '') {
    $format = 'csv';
}
if ($format !== 'csv' && $format !== 'pdf') {
    http_response_code(400);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Parámetro format inválido (use csv o pdf).';
    exit;
}

$cedula = isset($_GET['cedula']) ? trim((string) $_GET['cedula']) : '';
$q = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
$fichaFiltro = isset($_GET['ficha']) ? trim((string) $_GET['ficha']) : '';
if (!in_array($fichaFiltro, ['sin_carnet', 'carnet_solicitado', 'carnet_emitido', 'ficha_vencida', ''], true)) {
    $fichaFiltro = '';
}
if ($fichaFiltro === 'carnet_emitido') {
    $fichaFiltro = 'carnet_solicitado';
}

$revisionDelegado = AuthService::isSuperAdmin()
    && isset($_GET['revision_delegado'])
    && (string) $_GET['revision_delegado'] === '1';
if ($revisionDelegado) {
    \FvdPortal\Services\FvdAdminRevisionPendienteService::ensureAltaDesdeDelegadoColumn(fvd_db());
}

$rows = QueryHelper::selectAtletasAdminAll($cedula, $q, fvd_db(), $fichaFiltro, $revisionDelegado);
$rowCount = count($rows);
$ts = date('Y-m-d_His');

require_once FVD_PROJECT_ROOT . '/fvdmasteradmin/includes/fvd_asociacion_helpers.php';

$omitAsocCol = false;
$repEncabezadoHtml = '';
$repTitle = 'Listado de atletas FVD';
$aidRep = AuthService::idAsociacion();
if ($aidRep !== null && $aidRep > 0) {
    $omitAsocCol = true;
    $stAs = fvd_db()->prepare('SELECT nombre, logo FROM asociaciones WHERE id = :id LIMIT 1');
    $stAs->execute([':id' => $aidRep]);
    $arAs = $stAs->fetch(PDO::FETCH_ASSOC);
    if (is_array($arAs)) {
        $nomAs = fvd_asoc_nombre_sin_prefijo((string) ($arAs['nombre'] ?? ''));
        if ($nomAs !== '') {
            $repTitle = 'Listado de atletas — ' . $nomAs;
        }
        $logoUri = fvd_asociacion_logo_data_uri(FVD_PROJECT_ROOT, isset($arAs['logo']) ? (string) $arAs['logo'] : null);
        $cellLogo = $logoUri !== null
            ? '<div class="fvd-rep-asoc-head__logo"><img src="' . htmlspecialchars($logoUri, ENT_QUOTES, 'UTF-8') . '" alt=""></div>'
            : '<div class="fvd-rep-asoc-head__logo"></div>';
        $cellTxt = $nomAs !== ''
            ? '<div class="fvd-rep-asoc-head__txt">' . htmlspecialchars($nomAs, ENT_QUOTES, 'UTF-8') . '</div>'
            : '';
        $repEncabezadoHtml = '<div class="fvd-rep-asoc-head">' . $cellLogo . $cellTxt . '</div>';
    }
}

if ($format === 'csv') {
    $body = ReportService::generateAtletasCsv($rows, $omitAsocCol);
    $filename = 'atletas_' . $ts . '.csv';
    $mime = 'text/csv; charset=UTF-8';
} else {
    $html = ReportService::buildAtletasPdfHtml($rows, $repTitle, $repEncabezadoHtml, $omitAsocCol);
    $pdf = ReportService::renderPdfWithDompdfIfAvailable($html);
    if ($pdf !== null && $pdf !== '') {
        $body = $pdf;
        $filename = 'atletas_' . $ts . '.pdf';
        $mime = 'application/pdf';
    } else {
        $body = $html;
        $filename = 'atletas_' . $ts . '.html';
        $mime = 'text/html; charset=UTF-8';
    }
}

$pack = ReportService::packageWithOptionalZip($body, $filename, $mime, $rowCount);

$disp = 'attachment; filename="' . str_replace(['"', "\r", "\n"], '', $pack['filename']) . '"';
header('Content-Type: ' . $pack['mime']);
header('Content-Disposition: ' . $disp);
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, no-cache, must-revalidate');

echo $pack['body'];
