<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/_init.php';
require_once FVD_PROJECT_ROOT . '/src/Services/QueryHelper.php';
require_once FVD_PROJECT_ROOT . '/src/Services/ReportService.php';
use FvdPortal\Services\QueryHelper;
use FvdPortal\Services\ReportService;

fvd_admin_require_roles();

require_once __DIR__ . '/list_filters.inc.php';

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

$lf = fvd_atletas_resolve_list_filters($_GET);
$alcance = $lf['alcance'];
$tipo = $lf['tipo'];
$asociacionFiltroId = $lf['asociacion_id'];

$rows = QueryHelper::selectAtletasAdminAll($cedula, $q, fvd_db(), null, $alcance, $tipo, $asociacionFiltroId);
$rowCount = count($rows);
$ts = date('Y-m-d_His');

require_once FVD_PROJECT_ROOT . '/fvdmasteradmin/includes/fvd_asociacion_helpers.php';

$omitAsocCol = ($alcance === 'asociacion' && $asociacionFiltroId > 0);
$repEncabezadoHtml = '';
$repTitle = 'Listado de atletas';
$headerAsocId = $asociacionFiltroId;
if (!$omitAsocCol && AuthService::role() !== AuthService::ROLE_FVD_ADMIN) {
    $mine = AuthService::idAsociacion();
    if ($mine !== null && (int) $mine > 0) {
        $omitAsocCol = true;
        $headerAsocId = (int) $mine;
    }
}
if ($headerAsocId > 0) {
    $stAs = fvd_db()->prepare('SELECT nombre, logo, delegado FROM asociaciones WHERE id = :id LIMIT 1');
    $stAs->execute([':id' => $headerAsocId]);
    $arAs = $stAs->fetch(PDO::FETCH_ASSOC);
    if (is_array($arAs) && $omitAsocCol) {
        $logoUri = fvd_asociacion_logo_data_uri(FVD_PROJECT_ROOT, isset($arAs['logo']) ? (string) $arAs['logo'] : null);
        $cellLogo = $logoUri !== null
            ? '<div class="fvd-rep-asoc-head__logo"><img src="' . htmlspecialchars($logoUri, ENT_QUOTES, 'UTF-8') . '" alt=""></div>'
            : '<div class="fvd-rep-asoc-head__logo"></div>';
        $delegado = trim((string) ($arAs['delegado'] ?? ''));
        $cellDel = $delegado !== ''
            ? '<div class="fvd-rep-asoc-head__deleg" style="font-size:10pt;margin-top:4px"><strong>Delegado:</strong> '
            . htmlspecialchars($delegado, ENT_QUOTES, 'UTF-8') . '</div>'
            : '';
        $repEncabezadoHtml = '<div class="fvd-rep-asoc-head">' . $cellLogo . '<div class="fvd-rep-asoc-head__txt"></div>' . $cellDel . '</div>';
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
