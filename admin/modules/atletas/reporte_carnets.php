<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/_init.php';
require_once FVD_PROJECT_ROOT . '/src/Services/QueryHelper.php';
require_once __DIR__ . '/inc_reporte_atletas_columnas.php';
require_once __DIR__ . '/report_asoc_filter.inc.php';

use FvdPortal\Services\QueryHelper;

fvd_admin_require_roles();

$filtroAsocRep = fvd_report_filter_asociacion_id_from_get();
$alcCarnets = $filtroAsocRep > 0 ? 'asociacion' : 'todos';
$asocCarnets = $filtroAsocRep > 0 ? $filtroAsocRep : 0;
$rows = QueryHelper::selectAtletasAdminAll('', '', fvd_db(), 1, $alcCarnets, 'normal', $asocCarnets);
fvd_rep_atletas_strip_telefonos($rows);
$rowsAll = $rows;

require_once FVD_PROJECT_ROOT . '/fvdmasteradmin/includes/fvd_asociacion_helpers.php';

$repOmitAsocCol = false;
$repAsocNombreCorto = '';
$repAsocLogoUrl = null;
$aidHdr = AuthService::idAsociacion();
if ($aidHdr !== null && $aidHdr > 0) {
    $repOmitAsocCol = true;
    $stH = fvd_db()->prepare('SELECT nombre, logo FROM asociaciones WHERE id = :id LIMIT 1');
    $stH->execute([':id' => $aidHdr]);
    $rH = $stH->fetch(PDO::FETCH_ASSOC);
    if (is_array($rH)) {
        $repAsocNombreCorto = fvd_asoc_nombre_sin_prefijo((string) ($rH['nombre'] ?? ''));
        $appB = rtrim((string) env('APP_BASE_PATH', ''), '/');
        $repAsocLogoUrl = fvd_asociacion_logo_public_url($appB, FVD_PROJECT_ROOT, isset($rH['logo']) ? (string) $rH['logo'] : null);
    }
} else {
    $asocIds = [];
    foreach ($rowsAll as $rw) {
        $ax = (int) ($rw['asociacion'] ?? 0);
        if ($ax > 0) {
            $asocIds[$ax] = true;
        }
    }
    if (count($asocIds) === 1) {
        $onlyId = 0;
        foreach (array_keys($asocIds) as $kAsoc) {
            $onlyId = (int) $kAsoc;
            break;
        }
        $repOmitAsocCol = true;
        $stH = fvd_db()->prepare('SELECT nombre, logo FROM asociaciones WHERE id = :id LIMIT 1');
        $stH->execute([':id' => $onlyId]);
        $rH = $stH->fetch(PDO::FETCH_ASSOC);
        if (is_array($rH)) {
            $repAsocNombreCorto = fvd_asoc_nombre_sin_prefijo((string) ($rH['nombre'] ?? ''));
            $appB = rtrim((string) env('APP_BASE_PATH', ''), '/');
            $repAsocLogoUrl = fvd_asociacion_logo_public_url($appB, FVD_PROJECT_ROOT, isset($rH['logo']) ? (string) $rH['logo'] : null);
        }
    }
}

$resolvedCols = fvd_rep_atletas_resolve_columnas($rowsAll[0] ?? null);
$fvdRepIndicadoresColsVisibles = $resolvedCols['columns'];
$fvdRepIndicadoresColEtiquetaPorKey = $resolvedCols['labels'];
$fvdRepIndicadoresLogicalLcPorKey = $resolvedCols['logical'];

if ($repOmitAsocCol) {
    $filtered = [];
    foreach ($fvdRepIndicadoresColsVisibles as $ck) {
        if (($fvdRepIndicadoresLogicalLcPorKey[$ck] ?? '') === 'asociacion_nombre') {
            unset($fvdRepIndicadoresColEtiquetaPorKey[$ck], $fvdRepIndicadoresLogicalLcPorKey[$ck]);
            continue;
        }
        $filtered[] = $ck;
    }
    $fvdRepIndicadoresColsVisibles = $filtered;
}

$titulo = 'Solicitud de carnets (atletas.carnet = 1)';
$fvd_page_title = 'Reporte carnets';
$selfReport = admin_module_url('atletas/reporte_carnets.php');

$retOrigen = fvd_return_from_request();
$atletasUrl = fvd_crud_self_url('atletas');
$atletasBackUrl = $retOrigen !== null ? $retOrigen : ($atletasUrl . '?action=list');
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

$format = isset($_GET['format']) ? strtolower(trim((string) $_GET['format'])) : '';
if ($format === 'csv') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="atletas_carnets_' . date('Y-m-d_His') . '.csv"');
    header('X-Content-Type-Options: nosniff');
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
                if ($v === null) {
                    $line[] = '';
                } elseif (is_scalar($v) || $v instanceof \Stringable) {
                    $line[] = (string) $v;
                } else {
                    $line[] = '';
                }
            }
            fputcsv($out, $line, ';');
        }
    } else {
        fputcsv($out, ['sin_registros'], ';');
    }
    fclose($out);
    exit;
}

$stats = [
    'total'       => count($rowsAll),
    'afiliacion'  => 0,
    'anualidad'   => 0,
    'carnet'      => 0,
    'traspaso'    => 0,
    'inscripcion' => 0,
];
foreach ($rowsAll as $r) {
    foreach (['afiliacion', 'anualidad', 'carnet', 'traspaso', 'inscripcion'] as $k) {
        if ((int) ($r[$k] ?? 0) === 1) {
            ++$stats[$k];
        }
    }
}

$columnas = $fvdRepIndicadoresColsVisibles;

$qsCsv = ['format' => 'csv'];
if ($fvdRetPreserve !== '') {
    $qsCsv['ret'] = $fvdRetPreserve;
}
if ($filtroAsocRep > 0) {
    $qsCsv['asociacion_id'] = (string) $filtroAsocRep;
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
    <?php if ($repAsocNombreCorto !== '' || $repAsocLogoUrl !== null): ?>
    <div class="fvd-rep-carnets-head" style="display:flex;align-items:center;gap:14px;flex-wrap:wrap;margin:0 0 14px;padding-bottom:12px;border-bottom:2px solid var(--fvd-amarillo)">
        <?php if ($repAsocLogoUrl !== null): ?>
            <img src="<?= htmlspecialchars($repAsocLogoUrl, ENT_QUOTES, 'UTF-8') ?>" alt="" style="max-height:52px;max-width:100px;width:auto;object-fit:contain">
        <?php endif; ?>
        <?php if ($repAsocNombreCorto !== ''): ?>
            <span style="font-size:clamp(1rem,2.5vw,1.25rem);font-weight:700;color:var(--fvd-amarillo);flex:1;text-align:center;min-width:min(100%,12rem)"><?= htmlspecialchars($repAsocNombreCorto, ENT_QUOTES, 'UTF-8') ?></span>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    <h1 class="fvd-atletas-title"><?= htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8') ?></h1>
    <?php endif; ?>
    <p style="font-size:.8125rem;color:var(--fvd-muted);margin:0 0 .75rem;line-height:1.45">
        Igual que el resto de banderas en <code>atletas</code>, solo cuenta el valor <strong>1</strong>: indica solicitud / carnet registrado. Otros valores no se usan como criterio de informe.
        Total en conjunto filtrado: <strong><?= count($rowsAll) ?></strong> (tabla paginada).
    </p>
    <div class="no-print" style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin:0 0 1rem">
        <a class="fvd-input" style="width:auto;padding:6px 12px;text-decoration:none;display:inline-flex;align-items:center;box-sizing:border-box;font-weight:600" href="<?= htmlspecialchars($urlCsv, ENT_QUOTES, 'UTF-8') ?>">Descargar CSV</a>
        <a class="fvd-input" style="width:auto;padding:6px 12px;text-decoration:none;display:inline-flex;align-items:center;box-sizing:border-box;font-weight:700" href="<?= htmlspecialchars($atletasBackUrl, ENT_QUOTES, 'UTF-8') ?>"><?= $retOrigen !== null ? '← Volver a la consulta' : '← Listado atletas' ?></a>
    </div>
    <section class="fvd-rep-indicadores__stats" aria-label="Resumen por indicador" style="margin:0 0 1rem;padding:12px;border-radius:8px;border:1px solid var(--fvd-border);background:rgba(255,255,255,0.04)">
        <h2 style="margin:0 0 .5rem;font-size:.9rem">Estadísticas (sobre este listado)</h2>
        <p style="margin:0;font-size:.8125rem;line-height:1.6">
            <strong>Registros:</strong> <?= (int) $stats['total'] ?> &nbsp;|&nbsp;
            <span title="afiliacion=1"><strong>Afil.</strong> <?= (int) $stats['afiliacion'] ?></span> &nbsp;
            <span title="anualidad=1"><strong>Anual.</strong> <?= (int) $stats['anualidad'] ?></span> &nbsp;
            <span title="carnet=1"><strong>Carnet</strong> <?= (int) $stats['carnet'] ?></span> &nbsp;
            <span title="traspaso=1"><strong>Trasp.</strong> <?= (int) $stats['traspaso'] ?></span> &nbsp;
            <span title="inscripcion=1"><strong>Insc.</strong> <?= (int) $stats['inscripcion'] ?></span>
        </p>
    </section>
    <?php require __DIR__ . '/partial_reporte_atletas_main_grid.php'; ?>
</div>
<?php
require FVD_MASTER_ROOT . '/includes/layout_footer.php';
