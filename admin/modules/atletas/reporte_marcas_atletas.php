<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/_init.php';
require_once __DIR__ . '/inc_reporte_atletas_columnas.php';

fvd_admin_require_roles();

$legacy = FVD_PROJECT_ROOT . '/fvdmasteradmin/services/QueryHelper.php';
if (!class_exists('QueryHelper', false)) {
    require_once $legacy;
}

$format = isset($_GET['format']) ? strtolower(trim((string) $_GET['format'])) : 'html';
if ($format !== 'html' && $format !== 'csv') {
    $format = 'html';
}

$params = [];
$scope = QueryHelper::asociacionScopeSql('a.asociacion', $params);

$marcasWhere = '(
    COALESCE(a.afiliacion, 0) = 1
    OR COALESCE(a.anualidad, 0) = 1
    OR COALESCE(a.carnet, 0) = 1
    OR COALESCE(a.traspaso, 0) = 1
    OR COALESCE(a.inscripcion, 0) = 1
)';

$sql = 'SELECT a.*, s.nombre AS asociacion_nombre
    FROM atletas a
    LEFT JOIN asociaciones s ON s.id = a.asociacion
    WHERE ' . $marcasWhere . $scope . '
    ORDER BY a.id ASC
    LIMIT 15000';

$st = fvd_db()->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

fvd_rep_atletas_strip_telefonos($rows);
$rowsAll = $rows;
$resolvedCols = fvd_rep_atletas_resolve_columnas($rowsAll[0] ?? null);
$fvdRepIndicadoresColsVisibles = $resolvedCols['columns'];
$fvdRepIndicadoresColEtiquetaPorKey = $resolvedCols['labels'];
$fvdRepIndicadoresLogicalLcPorKey = $resolvedCols['logical'];

$n = count($rowsAll);
$sumAfi = 0;
$sumAnu = 0;
$sumCar = 0;
$sumTra = 0;
$sumIns = 0;
foreach ($rowsAll as $rw) {
    if ((int) ($rw['afiliacion'] ?? 0) === 1) {
        ++$sumAfi;
    }
    if ((int) ($rw['anualidad'] ?? 0) === 1) {
        ++$sumAnu;
    }
    if ((int) ($rw['carnet'] ?? 0) === 1) {
        ++$sumCar;
    }
    if ((int) ($rw['traspaso'] ?? 0) === 1) {
        ++$sumTra;
    }
    if ((int) ($rw['inscripcion'] ?? 0) === 1) {
        ++$sumIns;
    }
}

$selfReport = fvd_crud_self_url('atletas/reporte_marcas_atletas.php');
$atletasUrl = fvd_crud_self_url('atletas');
$listUrl = $atletasUrl . '?action=list';
$retOrigen = fvd_return_from_request();
$atletasBackUrl = $retOrigen !== null ? $retOrigen : $listUrl;
$fvdRetPreserve = '';
if ($retOrigen !== null) {
    if (isset($_GET['ret']) && is_string($_GET['ret']) && fvd_return_sanitize($_GET['ret']) !== null) {
        $fvdRetPreserve = $_GET['ret'];
    } elseif (isset($_GET['return']) && is_string($_GET['return']) && fvd_return_sanitize($_GET['return']) !== null) {
        $fvdRetPreserve = $_GET['return'];
    } else {
        $fvdRetPreserve = rawurlencode($retOrigen);
    }
}

if ($format === 'csv') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('X-Content-Type-Options: nosniff');
    $fn = 'atletas_marcas_' . date('Y-m-d_His') . '.csv';
    header('Content-Disposition: attachment; filename="' . str_replace(['"', "\r", "\n"], '', $fn) . '"');
    $out = fopen('php://output', 'wb');
    if ($out === false) {
        http_response_code(500);
        echo 'Error al generar CSV.';
        exit;
    }
    fwrite($out, "\xEF\xBB\xBF");
    if ($rowsAll !== []) {
        $cols = $fvdRepIndicadoresColsVisibles;
        $hdrCsv = [];
        foreach ($cols as $c) {
            $hdrCsv[] = $fvdRepIndicadoresColEtiquetaPorKey[$c] ?? $c;
        }
        fputcsv($out, $hdrCsv, ';');
        foreach ($rowsAll as $r) {
            $line = [];
            foreach ($cols as $c) {
                $v = $r[$c] ?? null;
                $line[] = $v === null ? '' : (is_scalar($v) || $v instanceof \Stringable ? (string) $v : '');
            }
            fputcsv($out, $line, ';');
        }
    } else {
        fputcsv($out, ['sin_registros'], ';');
    }
    fclose($out);
    exit;
}

$fvd_page_title = 'Atletas con marcas (informe)';
$columnas = $fvdRepIndicadoresColsVisibles;

$qsCsv = ['format' => 'csv'];
if ($fvdRetPreserve !== '') {
    $qsCsv['ret'] = $fvdRetPreserve;
}
$urlCsv = $selfReport . '?' . http_build_query($qsCsv, '', '&', PHP_QUERY_RFC3986);

require_once FVD_PROJECT_ROOT . '/includes/fvd_report_pagination.php';
$pag = fvd_report_paginator_slice($rowsAll);
$rows = $pag['slice'];
$fvd_repPaginator = $pag;
$fvd_repPaginatorSelf = $selfReport;
$movimientosSolicitados = fvd_rep_atletas_movimientos_sidebar_rows($rowsAll);

require FVD_MASTER_ROOT . '/includes/layout_header.php';
?>
<div class="report-container fvd-rep-indicadores" style="box-sizing:border-box;width:100%;max-width:100%;margin:0;padding:0 0 1rem">
    <?php require __DIR__ . '/partial_atletas_informes_nav.php'; ?>
    <?php if (function_exists('fvd_delegado_inner_heading_visible') && fvd_delegado_inner_heading_visible()): ?>
    <h1 class="fvd-atletas-title">Atletas con al menos una marca activa</h1>
    <?php endif; ?>
    <p style="font-size:.8125rem;color:var(--fvd-muted);margin:0 0 .75rem;line-height:1.45">
        Criterio: <code>afiliacion</code>, <code>anualidad</code>, <code>carnet</code>, <code>traspaso</code> o <code>inscripcion</code> = 1.
        Misma estructura de columnas que el informe de afiliación. Máximo <strong>15.000</strong> filas. Ámbito: su asociación (o todas si es administrador FVD).
    </p>
    <div class="fvd-marcas-resumen no-print" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(9rem,1fr));gap:.5rem;margin:0 0 1rem;font-size:.75rem">
        <div style="padding:.5rem .65rem;border-radius:.35rem;border:1px solid var(--fvd-border);background:var(--fvd-azul-card)">
            <strong>Filas</strong><br><span style="font-size:1.1rem"><?= (int) $n ?></span>
        </div>
        <div style="padding:.5rem .65rem;border-radius:.35rem;border:1px solid var(--fvd-border);background:var(--fvd-azul-card)">
            <strong>afiliacion=1</strong><br><?= (int) $sumAfi ?>
        </div>
        <div style="padding:.5rem .65rem;border-radius:.35rem;border:1px solid var(--fvd-border);background:var(--fvd-azul-card)">
            <strong>anualidad=1</strong><br><?= (int) $sumAnu ?>
        </div>
        <div style="padding:.5rem .65rem;border-radius:.35rem;border:1px solid var(--fvd-border);background:var(--fvd-azul-card)">
            <strong>carnet=1</strong><br><?= (int) $sumCar ?>
        </div>
        <div style="padding:.5rem .65rem;border-radius:.35rem;border:1px solid var(--fvd-border);background:var(--fvd-azul-card)">
            <strong>traspaso=1</strong><br><?= (int) $sumTra ?>
        </div>
        <div style="padding:.5rem .65rem;border-radius:.35rem;border:1px solid var(--fvd-border);background:var(--fvd-azul-card)">
            <strong>inscripcion=1</strong><br><?= (int) $sumIns ?>
        </div>
    </div>
    <div class="no-print" style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin:0 0 1rem">
        <a class="fvd-input" style="width:auto;padding:6px 12px;text-decoration:none;display:inline-flex;align-items:center;box-sizing:border-box;font-weight:600" href="<?= htmlspecialchars($urlCsv, ENT_QUOTES, 'UTF-8') ?>">Descargar CSV</a>
        <a class="fvd-input" style="width:auto;padding:6px 12px;text-decoration:none;display:inline-flex;align-items:center;box-sizing:border-box;font-weight:700" href="<?= htmlspecialchars($atletasBackUrl, ENT_QUOTES, 'UTF-8') ?>"><?= $retOrigen !== null ? '← Volver a la consulta' : '← Listado atletas' ?></a>
    </div>
    <section class="fvd-rep-indicadores__stats no-print" aria-label="Resumen" style="margin:0 0 1rem;padding:12px;border-radius:8px;border:1px solid var(--fvd-border);background:rgba(255,255,255,0.04)">
        <p style="margin:0;font-size:.8125rem;line-height:1.6">
            <strong>Registros:</strong> <?= (int) $n ?> &nbsp;|&nbsp;
            <strong>Afil.</strong> <?= (int) $sumAfi ?> &nbsp;
            <strong>Anual.</strong> <?= (int) $sumAnu ?> &nbsp;
            <strong>Carnet</strong> <?= (int) $sumCar ?> &nbsp;
            <strong>Trasp.</strong> <?= (int) $sumTra ?> &nbsp;
            <strong>Insc.</strong> <?= (int) $sumIns ?>
        </p>
    </section>
    <?php require __DIR__ . '/partial_reporte_atletas_main_grid.php'; ?>
</div>
<?php
require FVD_MASTER_ROOT . '/includes/layout_footer.php';
