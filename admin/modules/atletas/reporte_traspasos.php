<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/_init.php';
require_once FVD_PROJECT_ROOT . '/src/Services/TraspasoService.php';
require_once __DIR__ . '/report_asoc_filter.inc.php';

use FvdPortal\Services\TraspasoService;

fvd_admin_require_roles();

TraspasoService::ensureLogTable(fvd_db());

$legacy = FVD_PROJECT_ROOT . '/fvdmasteradmin/services/QueryHelper.php';
if (!class_exists('QueryHelper', false)) {
    require_once $legacy;
}

$filtroAsocRep = fvd_report_filter_asociacion_id_from_get();

$params = [];
$scope = QueryHelper::asociacionScopeSql('a.asociacion', $params);
$sqlFiltroFvdAsoc = '';
if ($filtroAsocRep > 0) {
    $params[':_fvd_rep_tr_asoc'] = $filtroAsocRep;
    $sqlFiltroFvdAsoc = ' AND a.asociacion = :_fvd_rep_tr_asoc ';
}
$sqlSexoTr = '';
if (class_exists('AuthService', false) && AuthService::isDelegadoAsociacion()) {
    $ctxTr = \AuthService::delegadoTorneoContextId();
    if ($ctxTr !== null && (int) $ctxTr > 0) {
        require_once FVD_PROJECT_ROOT . '/src/Services/FvdAdminService.php';
        $fvdTr = new \FvdAdminService(fvd_db());
        $sqlSexoTr = $fvdTr->sqlAtletasFiltroSexoSegunTorneoTipo((int) $ctxTr, 'a');
    }
}
$sql = 'SELECT l.id, l.creado_en, l.atleta_id, l.asociacion_origen_id, l.asociacion_destino_id,
    a.id AS atleta_table_id,
    a.cedula, a.nombre, a.sexo, a.numfvd, a.foto, a.estatus,
    a.traspaso AS atleta_traspaso_marcador,
    s.nombre AS asociacion_nombre,
    o.nombre AS asoc_origen, d.nombre AS asoc_destino
    FROM log_traspasos l
    INNER JOIN atletas a ON a.id = l.atleta_id
    LEFT JOIN asociaciones s ON s.id = a.asociacion
    LEFT JOIN asociaciones o ON o.id = l.asociacion_origen_id
    LEFT JOIN asociaciones d ON d.id = l.asociacion_destino_id
    WHERE 1=1 ' . $scope . $sqlSexoTr . $sqlFiltroFvdAsoc . '
    ORDER BY l.creado_en DESC
    LIMIT 500';

$st = fvd_db()->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

$traspColKeys = [
    'creado_en',
    'foto',
    'numfvd',
    'cedula',
    'nombre',
    'sexo',
    'asociacion_nombre',
    'estatus',
    'asoc_origen',
    'asoc_destino',
    'atleta_traspaso_marcador',
];
$traspColLabels = [
    'creado_en'               => 'Fecha registro',
    'foto'                    => 'Foto',
    'numfvd'                  => 'Nº FVD',
    'cedula'                  => 'Cédula',
    'nombre'                  => 'Nombre',
    'sexo'                    => 'Sexo',
    'asociacion_nombre'       => 'Asociación',
    'estatus'                 => 'Estatus',
    'asoc_origen'            => 'Origen',
    'asoc_destino'           => 'Destino',
    'atleta_traspaso_marcador' => 'Marcador trasp.',
];

$retOrigen = fvd_return_from_request();
$atletasUrl = fvd_crud_self_url('atletas');
$atletasBackUrl = $retOrigen !== null ? $retOrigen : ($atletasUrl . '?action=list');
$selfReport = admin_module_url('atletas/reporte_traspasos.php');
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
    header('Content-Disposition: attachment; filename="log_traspasos_' . date('Y-m-d_His') . '.csv"');
    header('X-Content-Type-Options: nosniff');
    $out = fopen('php://output', 'wb');
    if ($out === false) {
        http_response_code(500);
        echo 'Error al generar CSV.';
        exit;
    }
    fwrite($out, "\xEF\xBB\xBF");
    if ($rows !== []) {
        $hdr = [];
        foreach ($traspColKeys as $k) {
            $hdr[] = $traspColLabels[$k] ?? $k;
        }
        fputcsv($out, $hdr, ';');
        foreach ($rows as $r) {
            $line = [];
            foreach ($traspColKeys as $k) {
                $v = $r[$k] ?? null;
                if ($k === 'estatus' && $v !== null && $v !== '') {
                    $nfCsv = isset($r['numfvd']) ? (int) $r['numfvd'] : null;
                    $line[] = FvdAdminService::atletasEstatusEtiqueta((int) $v, $nfCsv);
                } else {
                    $line[] = $v === null ? '' : (string) $v;
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

require_once FVD_PROJECT_ROOT . '/includes/fvd_report_pagination.php';
$rowsAll = $rows;
$pag = fvd_report_paginator_slice($rowsAll);
$rows = $pag['slice'];
$fvd_repPaginator = $pag;
$fvd_repPaginatorSelf = $selfReport;

$fvd_page_title = 'Reporte traspasos';

$resumenLog = [];
foreach ($rows as $rL) {
    if (!is_array($rL)) {
        continue;
    }
    $docL = (int) ($rL['numfvd'] ?? 0) > 0
        ? (string) ((int) $rL['numfvd'])
        : trim((string) ($rL['cedula'] ?? ''));
    $nomL = trim((string) ($rL['nombre'] ?? ''));
    $feL = trim((string) ($rL['creado_en'] ?? ''));
    $orig = trim((string) ($rL['asoc_origen'] ?? ''));
    $dest = trim((string) ($rL['asoc_destino'] ?? ''));
    $ruta = ($orig !== '' ? $orig : '—') . ' → ' . ($dest !== '' ? $dest : '—');
    $resumenLog[] = [
        'doc'   => $docL !== '' ? $docL : '—',
        'nom'   => $nomL !== '' ? $nomL : '—',
        'fecha' => $feL !== '' ? $feL : '—',
        'ruta'  => $ruta,
    ];
}

$qsCsv = ['format' => 'csv'];
if ($fvdRetPreserve !== '') {
    $qsCsv['ret'] = $fvdRetPreserve;
}
if ($filtroAsocRep > 0) {
    $qsCsv['asociacion_id'] = (string) $filtroAsocRep;
}
$urlCsv = $selfReport . '?' . http_build_query($qsCsv, '', '&', PHP_QUERY_RFC3986);

require FVD_MASTER_ROOT . '/includes/layout_header.php';
?>
<div class="report-container fvd-rep-indicadores" style="box-sizing:border-box;width:100%;max-width:100%;margin:0;padding:0 0 1rem">
    <?php require __DIR__ . '/partial_atletas_informes_nav.php'; ?>
    <?php if (function_exists('fvd_delegado_inner_heading_visible') && fvd_delegado_inner_heading_visible()): ?>
    <h1 class="fvd-atletas-title">Historial de traspasos</h1>
    <?php endif; ?>
    <p style="font-size:.8125rem;color:var(--fvd-muted);margin:0 0 .75rem;line-height:1.45">
        Movimientos en <code>log_traspasos</code> con datos del atleta. Registros: <strong><?= count($rowsAll) ?></strong> (máx. 500; tabla paginada).
    </p>
    <div class="no-print" style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin:0 0 1rem">
        <a class="fvd-input" style="width:auto;padding:6px 12px;text-decoration:none;display:inline-flex;align-items:center;box-sizing:border-box;font-weight:600" href="<?= htmlspecialchars($urlCsv, ENT_QUOTES, 'UTF-8') ?>">Descargar CSV</a>
        <a class="fvd-input" style="width:auto;padding:6px 12px;text-decoration:none;display:inline-flex;align-items:center;box-sizing:border-box;font-weight:700" href="<?= htmlspecialchars($atletasBackUrl, ENT_QUOTES, 'UTF-8') ?>"><?= $retOrigen !== null ? '← Volver a la consulta' : '← Listado atletas' ?></a>
    </div>
    <section class="fvd-rep-indicadores__stats" aria-label="Resumen" style="margin:0 0 1rem;padding:12px;border-radius:8px;border:1px solid var(--fvd-border);background:rgba(255,255,255,0.04)">
        <p style="margin:0;font-size:.8125rem;line-height:1.6">
            <strong>Eventos listados:</strong> <?= (int) count($rowsAll) ?>
        </p>
    </section>
    <div class="fvd-rep-indicadores__grid" style="display:grid;grid-template-columns:minmax(0,1fr);gap:12px;align-items:start;width:100%;max-width:100%;box-sizing:border-box">
        <div class="fvd-mod-table-wrap fvd-rep-table-wrap--paginated" style="width:100%;max-width:100%;box-sizing:border-box;overflow-x:auto;-webkit-overflow-scrolling:touch">
            <table class="fvd-mod-table tabla-atletas" style="font-size:.72rem;width:max-content;min-width:100%;table-layout:auto">
                <thead>
                <tr>
                    <?php foreach ($traspColKeys as $ck): ?>
                        <th scope="col" style="white-space:nowrap;position:sticky;top:0;background:var(--fvd-azul-card,#1e293b);z-index:1"><?= htmlspecialchars($traspColLabels[$ck] ?? $ck, ENT_QUOTES, 'UTF-8') ?></th>
                    <?php endforeach; ?>
                    <th scope="col" class="no-print" style="position:sticky;top:0;background:var(--fvd-azul-card,#1e293b);z-index:1">Acción</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <?php foreach ($traspColKeys as $ck): ?>
                            <?php
                            $cell = $r[$ck] ?? null;
                            ?>
                            <?php if ($ck === 'foto'):
                                $fv = trim((string) ($cell ?? ''));
                            ?>
                            <?php if ($fv !== ''):
                                $imgUrl = htmlspecialchars(url('crud_atletas/uploads/' . ltrim($fv, '/')), ENT_QUOTES, 'UTF-8');
                            ?>
                            <td style="vertical-align:middle;text-align:center;width:52px">
                                <img src="<?= $imgUrl ?>" alt="" width="40" height="40" loading="lazy" style="object-fit:cover;border-radius:4px;max-width:40px;max-height:40px;display:block;margin:0 auto" />
                            </td>
                            <?php else: ?>
                            <td style="vertical-align:middle;text-align:center">—</td>
                            <?php endif; ?>
                            <?php elseif ($ck === 'estatus'):
                                $nfHtml = isset($r['numfvd']) ? (int) $r['numfvd'] : null;
                                $disp = FvdAdminService::atletasEstatusEtiqueta((int) ($cell ?? 0), $nfHtml);
                            ?>
                            <td style="max-width:min(28rem,32vw);overflow:hidden;text-overflow:ellipsis;vertical-align:top" title="<?= htmlspecialchars($disp, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($disp, ENT_QUOTES, 'UTF-8') ?></td>
                            <?php elseif ($ck === 'numfvd'):
                                $nf = (int) ($cell ?? 0);
                                $disp = $nf > 0 ? (string) $nf : '—';
                            ?>
                            <td style="max-width:min(28rem,32vw);overflow:hidden;text-overflow:ellipsis;vertical-align:top" title="<?= htmlspecialchars($disp, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($disp, ENT_QUOTES, 'UTF-8') ?></td>
                            <?php else:
                                $disp = $cell === null || $cell === '' ? '—' : (is_scalar($cell) || $cell instanceof \Stringable ? (string) $cell : '');
                            ?>
                            <td style="max-width:min(28rem,32vw);overflow:hidden;text-overflow:ellipsis;vertical-align:top" title="<?= htmlspecialchars($disp, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($disp, ENT_QUOTES, 'UTF-8') ?></td>
                            <?php endif; ?>
                        <?php endforeach; ?>
                        <td class="no-print" style="white-space:nowrap">
                            <a href="<?= htmlspecialchars($atletasUrl . '?action=form&id=' . (int) ($r['atleta_table_id'] ?? 0), ENT_QUOTES, 'UTF-8') ?>">Editar</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($rows === []): ?>
                    <tr><td colspan="<?= count($traspColKeys) + 1 ?>" style="padding:12px">Sin traspasos registrados en su ámbito.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
        if (!empty($fvd_repPaginator) && is_array($fvd_repPaginator) && !empty($fvd_repPaginatorSelf)) {
            require FVD_PROJECT_ROOT . '/includes/partial_fvd_report_paginator.php';
        }
        ?>
        <aside style="width:100%;max-width:100%;box-sizing:border-box;border:1px solid var(--fvd-border);border-radius:8px;padding:10px;background:rgba(255,255,255,0.04);max-height:min(40vh,560px);overflow:auto">
            <h3 style="margin:0 0 .5rem;font-size:.88rem">Resumen del log</h3>
            <p style="margin:0 0 .6rem;font-size:.72rem;color:var(--fvd-muted)">
                Nº FVD o cédula, nombre, fecha y ruta origen → destino por evento.
            </p>
            <table class="fvd-mod-table" style="font-size:.72rem">
                <thead>
                <tr>
                    <th scope="col">Doc / Nº FVD</th>
                    <th scope="col">Nombre</th>
                    <th scope="col">Fecha</th>
                    <th scope="col">Ruta</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($resumenLog as $sl): ?>
                    <tr>
                        <td><?= htmlspecialchars($sl['doc'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($sl['nom'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($sl['fecha'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($sl['ruta'], ENT_QUOTES, 'UTF-8') ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($resumenLog === []): ?>
                    <tr><td colspan="4" style="padding:10px">Sin registros en el filtro actual.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </aside>
    </div>
</div>
<?php
require FVD_MASTER_ROOT . '/includes/layout_footer.php';
