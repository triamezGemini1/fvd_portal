<?php
/** @var ?array $row */
/** @var string $selfUrl */
/** @var array $fvdReportePorConcepto */
/** @var bool $fvdReporteConceptosOk */
/** @var ?string $fvdReporteConceptoFiltro */
$r = $row ?? [];
$fvdReporteConceptosOk = $fvdReporteConceptosOk ?? false;
$fvdReporteConceptoFiltro = $fvdReporteConceptoFiltro ?? null;
$labels = DeudaAsociacionController::ETIQUETAS_CONCEPTO;
$formUrl = fvd_return_append_to_url($selfUrl . '?action=form&tid=' . (int) ($r['torneo_id'] ?? 0) . '&aid=' . (int) ($r['asociacion_id'] ?? 0));
$reporteTodosUrl = fvd_return_append_to_url($selfUrl . '?action=reporte_conceptos&tid=' . (int) ($r['torneo_id'] ?? 0) . '&aid=' . (int) ($r['asociacion_id'] ?? 0));
$labelsToShow = ($fvdReporteConceptoFiltro !== null && isset($labels[$fvdReporteConceptoFiltro]))
    ? [$fvdReporteConceptoFiltro => $labels[$fvdReporteConceptoFiltro]]
    : $labels;
?>

<style>
    .fvd-deuda-reporte-page {
        background: #fff;
        color: #111827;
        max-width: 960px;
        padding: 1.25rem 1.5rem;
        border-radius: 8px;
        border: 1px solid #e5e7eb;
        box-sizing: border-box;
    }
    .fvd-deuda-reporte-page > h1 {
        margin-top: 0;
        color: #111827;
    }
    .fvd-deuda-reporte-page .fvd-deuda-reporte-sub {
        margin-top: 0;
        color: #374151;
    }
    .fvd-deuda-reporte-page .fvd-deuda-reporte-lead {
        margin-top: 0;
        margin-bottom: 0.75rem;
        color: #374151;
        line-height: 1.5;
    }
    .fvd-deuda-reporte-page .fvd-deuda-reporte-enlaces {
        margin: 0 0 0.75rem;
    }
    .fvd-deuda-reporte-page .fvd-deuda-reporte-enlaces a {
        color: #1d4ed8;
        font-weight: 500;
    }
    .fvd-deuda-reporte-bloque {
        margin-top: 1.25rem;
    }
    .fvd-deuda-reporte-bloque:first-of-type {
        margin-top: 0.5rem;
    }
    .fvd-deuda-reporte-bloque h2 {
        font-size: 1.05rem;
        margin: 0 0 0.5rem;
        color: #111827;
        font-weight: 600;
    }
    .fvd-deuda-reporte-bloque h2 .fvd-deuda-reporte-conteo {
        font-weight: 400;
        color: #4b5563;
    }
    .fvd-deuda-reporte-tabla-caja {
        background: #fff;
        border: 1px solid #d1d5db;
        border-radius: 6px;
        overflow: hidden;
    }
    .fvd-deuda-reporte-bloque table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.95rem;
        color: #111827;
    }
    .fvd-deuda-reporte-bloque th,
    .fvd-deuda-reporte-bloque td {
        padding: 0.45rem 0.65rem;
        text-align: left;
        border-bottom: 1px solid #e5e7eb;
    }
    .fvd-deuda-reporte-bloque tbody tr:last-child td {
        border-bottom: none;
    }
    .fvd-deuda-reporte-bloque th {
        font-weight: 600;
        background: #f3f4f6;
        color: #111827;
        border-bottom: 2px solid #d1d5db;
    }
    .fvd-deuda-reporte-bloque .fvd-deuda-num {
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
    }
    .fvd-deuda-reporte-bloque .fvd-deuda-reporte-vacio {
        margin: 0;
        color: #4b5563;
    }
</style>

<div class="fvd-deuda-reporte-page">
<h1>Detalle por concepto</h1>
<?php if ($row !== null): ?>
    <p class="fvd-deuda-reporte-sub">Torneo #<?= (int) ($r['torneo_id'] ?? 0) ?> · Asociación #<?= (int) ($r['asociacion_id'] ?? 0) ?></p>
<?php endif; ?>

<?php if ($row === null): ?>
    <p>La deuda no existe o no tiene permisos para verla.</p>
    <p><a href="<?= htmlspecialchars(fvd_return_to_module_index($selfUrl), ENT_QUOTES, 'UTF-8') ?>">Volver al listado</a></p>
<?php elseif (!$fvdReporteConceptosOk): ?>
    <p>No se pudo generar el detalle por concepto.</p>
    <?php if (!empty($fvd_error)): ?>
        <p class="fvd-mod-msg"><?= htmlspecialchars($fvd_error, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>
    <p><a href="<?= htmlspecialchars($formUrl, ENT_QUOTES, 'UTF-8') ?>">Volver al reporte de costos</a> ·
        <a href="<?= htmlspecialchars(fvd_return_to_module_index($selfUrl), ENT_QUOTES, 'UTF-8') ?>">Listado de deudas</a></p>
<?php else: ?>
        <p class="fvd-deuda-reporte-lead">
            Listado de atletas que suman cada concepto (mismos criterios que el cálculo de deuda). Columnas: número FVD, nombre y cédula del registro.
        </p>
        <?php if ($fvdReporteConceptoFiltro !== null): ?>
            <p class="fvd-deuda-reporte-enlaces">
                <a href="<?= htmlspecialchars($reporteTodosUrl, ENT_QUOTES, 'UTF-8') ?>">Ver todos los conceptos</a>
            </p>
        <?php endif; ?>

        <?php foreach ($labelsToShow as $key => $titulo):
            $filas = $fvdReportePorConcepto[$key] ?? [];
            ?>
            <section class="fvd-deuda-reporte-bloque" aria-labelledby="fvd-rc-<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>">
                <h2 id="fvd-rc-<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8') ?>
                    <span class="fvd-deuda-reporte-conteo">(<?= count($filas) ?>)</span>
                </h2>
                <?php if ($filas === []): ?>
                    <p class="fvd-deuda-reporte-vacio">Ningún atleta con este concepto en este torneo.</p>
                <?php else: ?>
                    <div class="fvd-deuda-reporte-tabla-caja">
                        <table>
                            <thead>
                                <tr>
                                    <th class="fvd-deuda-num">Num. FVD</th>
                                    <th>Nombre</th>
                                    <th>Cédula</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($filas as $a): ?>
                                    <tr>
                                        <td class="fvd-deuda-num"><?= (int) $a['numfvd'] ?></td>
                                        <td><?= htmlspecialchars($a['nombre'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td class="fvd-deuda-num"><?= htmlspecialchars($a['cedula'], ENT_QUOTES, 'UTF-8') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>
        <?php endforeach; ?>

        <div class="fvd-mod-actions" style="margin-top:1.25rem">
            <a href="<?= htmlspecialchars($formUrl, ENT_QUOTES, 'UTF-8') ?>">Volver al reporte de costos</a>
            <a href="<?= htmlspecialchars(fvd_return_to_module_index($selfUrl), ENT_QUOTES, 'UTF-8') ?>">Listado de deudas</a>
        </div>
<?php endif; ?>
</div>
