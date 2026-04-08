<?php
/** @var InscripcionesController $ctrl */
$fvdUrlSelf = fvd_module_url('inscripciones/index.php');
$fvdUrlDeuda = fvd_module_url('deuda_asociacion/index.php');
$fvdUrlPagos = fvd_module_url('relacion_pago/index.php');
$fvdUrlInscTorneo = fvd_module_url('inscripcion_torneo/index.php');
$tSel = (int) $fvdRepDefaultTorneo;
$aSel = (int) $fvdRepDefaultAsoc;
$mkReportUrl = static function (string $tipo, bool $inline) use ($ctrl, $tSel, $aSel): string {
    $base = $ctrl->reportExportUrl($tipo, $tSel, $aSel);
    return $base . ($inline ? '&inline=1' : '');
};
?>
<h1 class="fvd-atletas-title">Reportes del torneo</h1>
<p style="font-size:0.875rem;color:var(--fvd-muted);max-width:48rem;margin:0 0 1.25rem">
    Elija <strong>torneo</strong><?php if (AuthService::role() === AuthService::ROLE_FVD_ADMIN): ?> y <strong>asociación</strong><?php endif; ?>, aplique el filtro y descargue los PDF (o HTML si no hay Dompdf).
    Los listados usan las marcas en <code>atletas</code> para el club en ese torneo. Los informes contables toman <code>deuda_asociaciones</code> y <code>relacion_pagos</code>.
    <?php if (isset($_GET['torneo_id']) && (int) $_GET['torneo_id'] > 0): ?>
        <br><span style="color:var(--fvd-amarillo,#ca8a04)">Torneo activo para los enlaces: <strong>#<?= (int) $_GET['torneo_id'] ?></strong> (parámetro <code>?torneo_id=</code> en la URL; no solo el torneo del contexto del delegado).</span>
    <?php endif; ?>
</p>

<?php if ($fvdRepTorneos === []): ?>
    <p class="fvd-mod-msg">No hay torneos en el selector. Si es delegado, confirme que existan atletas del club con <code>torneo_id</code> o un evento activo en contexto.</p>
<?php else: ?>
<form method="get" action="<?= htmlspecialchars($fvdUrlSelf, ENT_QUOTES, 'UTF-8') ?>" class="fvd-card" style="padding:14px;margin-bottom:1.25rem;display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end">
    <div>
        <label style="font-size:0.75rem;color:var(--fvd-muted);display:block">Torneo</label>
        <select class="fvd-input" name="torneo_id" style="min-width:14rem">
            <?php foreach ($fvdRepTorneos as $tr): ?>
                <?php $tid = (int) ($tr['torneo'] ?? 0); ?>
                <option value="<?= $tid ?>"<?= $tid === $tSel ? ' selected' : '' ?>><?= htmlspecialchars((string) ($tr['nombre'] ?? ('#' . $tid)), ENT_QUOTES, 'UTF-8') ?> (#<?= $tid ?>)</option>
            <?php endforeach; ?>
        </select>
    </div>
    <?php if (AuthService::role() === AuthService::ROLE_FVD_ADMIN && $fvdRepAsociaciones !== []): ?>
    <div>
        <label style="font-size:0.75rem;color:var(--fvd-muted);display:block">Asociación</label>
        <select class="fvd-input" name="asociacion_id" style="min-width:14rem">
            <?php foreach ($fvdRepAsociaciones as $ar): ?>
                <?php $aid = (int) ($ar['id'] ?? 0); ?>
                <option value="<?= $aid ?>"<?= $aid === $aSel ? ' selected' : '' ?>><?= htmlspecialchars((string) ($ar['nombre'] ?? ''), ENT_QUOTES, 'UTF-8') ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <?php endif; ?>
    <button type="submit" class="fvd-btn-primary">Aplicar filtro</button>
</form>
<?php endif; ?>

<?php if ($fvdRepTorneos !== [] && $tSel > 0 && $aSel > 0): ?>

<style>
.fvd-rep-two-col {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 1rem;
    align-items: stretch;
    margin-bottom: 1rem;
}
@media (max-width: 900px) {
    .fvd-rep-two-col { grid-template-columns: 1fr; }
}
.fvd-rep-opt {
    border: 1px solid var(--fvd-border, rgba(255,255,255,.12));
    border-radius: 10px;
    padding: 12px 12px 10px;
    margin-bottom: 10px;
    background: rgba(0,0,0,.08);
}
.fvd-rep-opt:last-child { margin-bottom: 0; }
.fvd-rep-opt__title { font-weight: 600; font-size: 0.9rem; margin: 0 0 4px; line-height: 1.35; }
.fvd-rep-opt__hint { font-size: 0.75rem; color: var(--fvd-muted); margin: 0 0 10px; }
.fvd-rep-opt__actions { display: flex; flex-wrap: wrap; gap: 8px; }
.fvd-rep-opt__actions a {
    flex: 1 1 auto;
    min-width: 7.5rem;
    text-align: center;
    box-sizing: border-box;
    padding: 8px 12px;
    text-decoration: none;
    border-radius: 8px;
    font-size: 0.8125rem;
    font-weight: 600;
}
.fvd-rep-opt__actions a.fvd-rep-btn--linea {
    background: var(--fvd-amarillo, #fbbf24);
    color: #111;
    border: 1px solid rgba(0,0,0,.15);
}
.fvd-rep-opt__actions a.fvd-rep-btn--pdf {
    background: transparent;
    color: var(--fvd-fg, #e5e5e5);
    border: 1px solid var(--fvd-border, rgba(255,255,255,.2));
}
</style>

<div class="fvd-rep-two-col">
    <section class="fvd-card" style="padding:14px;margin:0;display:flex;flex-direction:column;height:100%">
        <h2 style="margin:0 0 6px;font-size:1.05rem">Movimiento Inscripción</h2>
        <p style="margin:0 0 12px;font-size:0.8125rem;color:var(--fvd-muted);flex-shrink:0">Atletas por marca en el torneo y asociación seleccionados.</p>
        <div style="flex:1;display:flex;flex-direction:column;gap:0">
            <div class="fvd-rep-opt">
                <p class="fvd-rep-opt__title">Todos los atletas inscritos al torneo</p>
                <p class="fvd-rep-opt__hint"><code>inscripcion = 1</code></p>
                <div class="fvd-rep-opt__actions">
                    <a class="fvd-rep-btn--linea" target="_blank" href="<?= htmlspecialchars($mkReportUrl('inscritos', true), ENT_QUOTES, 'UTF-8') ?>">Ver en línea</a>
                    <a class="fvd-rep-btn--pdf" href="<?= htmlspecialchars($mkReportUrl('inscritos', false), ENT_QUOTES, 'UTF-8') ?>">Descargar PDF</a>
                </div>
            </div>
            <div class="fvd-rep-opt">
                <p class="fvd-rep-opt__title">Atletas que solicitaron carnet</p>
                <p class="fvd-rep-opt__hint"><code>carnet = 1</code></p>
                <div class="fvd-rep-opt__actions">
                    <a class="fvd-rep-btn--linea" target="_blank" href="<?= htmlspecialchars($mkReportUrl('carnets', true), ENT_QUOTES, 'UTF-8') ?>">Ver en línea</a>
                    <a class="fvd-rep-btn--pdf" href="<?= htmlspecialchars($mkReportUrl('carnets', false), ENT_QUOTES, 'UTF-8') ?>">Descargar PDF</a>
                </div>
            </div>
            <div class="fvd-rep-opt">
                <p class="fvd-rep-opt__title">Nuevos afiliados</p>
                <p class="fvd-rep-opt__hint"><code>afiliacion = 1</code></p>
                <div class="fvd-rep-opt__actions">
                    <a class="fvd-rep-btn--linea" target="_blank" href="<?= htmlspecialchars($mkReportUrl('afiliados', true), ENT_QUOTES, 'UTF-8') ?>">Ver en línea</a>
                    <a class="fvd-rep-btn--pdf" href="<?= htmlspecialchars($mkReportUrl('afiliados', false), ENT_QUOTES, 'UTF-8') ?>">Descargar PDF</a>
                </div>
            </div>
        </div>
    </section>

    <section class="fvd-card" style="padding:14px;margin:0;display:flex;flex-direction:column;height:100%">
        <h2 style="margin:0 0 6px;font-size:1.05rem">Finanzas</h2>
        <p style="margin:0 0 12px;font-size:0.8125rem;color:var(--fvd-muted);flex-shrink:0">Deuda, pagos y estado de cuenta para esta asociación.</p>
        <div style="flex:1;display:flex;flex-direction:column;gap:0">
            <div class="fvd-rep-opt">
                <p class="fvd-rep-opt__title">Deuda generada detallada por concepto</p>
                <p class="fvd-rep-opt__hint">Totales por concepto desde <code>deuda_asociaciones</code></p>
                <div class="fvd-rep-opt__actions">
                    <a class="fvd-rep-btn--linea" target="_blank" href="<?= htmlspecialchars($mkReportUrl('finanzas_deuda', true), ENT_QUOTES, 'UTF-8') ?>">Ver en línea</a>
                    <a class="fvd-rep-btn--pdf" href="<?= htmlspecialchars($mkReportUrl('finanzas_deuda', false), ENT_QUOTES, 'UTF-8') ?>">Descargar PDF</a>
                </div>
            </div>
            <div class="fvd-rep-opt">
                <p class="fvd-rep-opt__title">Reporte de pagos detallado</p>
                <p class="fvd-rep-opt__hint">Recibos en <code>relacion_pagos</code></p>
                <div class="fvd-rep-opt__actions">
                    <a class="fvd-rep-btn--linea" target="_blank" href="<?= htmlspecialchars($mkReportUrl('finanzas_pagos', true), ENT_QUOTES, 'UTF-8') ?>">Ver en línea</a>
                    <a class="fvd-rep-btn--pdf" href="<?= htmlspecialchars($mkReportUrl('finanzas_pagos', false), ENT_QUOTES, 'UTF-8') ?>">Descargar PDF</a>
                </div>
            </div>
            <div class="fvd-rep-opt">
                <p class="fvd-rep-opt__title">Estado de cuenta final</p>
                <p class="fvd-rep-opt__hint">Deuda + detalle por concepto + pagos + saldo</p>
                <div class="fvd-rep-opt__actions">
                    <a class="fvd-rep-btn--linea" target="_blank" href="<?= htmlspecialchars($mkReportUrl('finanzas_estado', true), ENT_QUOTES, 'UTF-8') ?>">Ver en línea</a>
                    <a class="fvd-rep-btn--pdf" href="<?= htmlspecialchars($mkReportUrl('finanzas_estado', false), ENT_QUOTES, 'UTF-8') ?>">Descargar PDF</a>
                </div>
            </div>
        </div>
        <p style="font-size:0.8125rem;color:var(--fvd-muted);margin:14px 0 0;padding-top:10px;border-top:1px solid var(--fvd-border, rgba(255,255,255,.1))">
            Gestión:
            <a href="<?= htmlspecialchars($fvdUrlDeuda . '?action=form&tid=' . $tSel . '&aid=' . $aSel, ENT_QUOTES, 'UTF-8') ?>">Deuda / actualizar desde atletas</a>
            · <a href="<?= htmlspecialchars($fvdUrlPagos, ENT_QUOTES, 'UTF-8') ?>">Relación de pagos</a>
        </p>
    </section>
</div>

<?php elseif ($fvdRepTorneos !== [] && ($tSel <= 0 || $aSel <= 0)): ?>
    <p class="fvd-mod-msg">No hay torneo o asociación seleccionable. Compruebe permisos o datos en <a href="<?= htmlspecialchars($fvdUrlInscTorneo, ENT_QUOTES, 'UTF-8') ?>">inscripciones por torneo</a>.</p>
<?php endif; ?>

<p style="font-size:0.8125rem;color:var(--fvd-muted)">
    <a href="<?= htmlspecialchars($fvdUrlInscTorneo . ($tSel > 0 ? '?torneo_id=' . $tSel : ''), ENT_QUOTES, 'UTF-8') ?>">Tabla inscripcion_torneo</a>
    · <a href="<?= htmlspecialchars(fvd_module_url('atletas/index.php?action=list'), ENT_QUOTES, 'UTF-8') ?>">Módulo Atletas</a>
</p>

