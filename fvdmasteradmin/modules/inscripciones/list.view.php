<?php
/** @var InscripcionesController $ctrl */
$fvd_rep_campeonato_error = $fvd_rep_campeonato_error ?? '';
if (!function_exists('fvd_append_embed_to_url')) {
    require_once dirname(__DIR__, 3) . '/config/fvd_navigation_return.php';
}
$fvdUrlSelf = fvd_module_url('inscripciones/index.php');
$fvdUrlDeuda = fvd_module_url('deuda_asociacion/index.php');
$fvdUrlPagos = fvd_module_url('relacion_pago/index.php');
$fvdUrlInscTorneo = fvd_module_url('inscripcion_torneo/index.php');
$tSel = (int) $fvdRepDefaultTorneo;
$aSel = (int) $fvdRepDefaultAsoc;
$fvdRepCampeonatoId = isset($fvd_rep_campeonato_id) ? (int) $fvd_rep_campeonato_id : 0;
$fvdRepEmbedActive = function_exists('fvd_master_embed_active') && fvd_master_embed_active();
$fvdRepInscListUrl = static function (int $tid, int $aid) use ($fvdUrlSelf, $fvdRepCampeonatoId, $fvdRepEmbedActive): string {
    $q = ['torneo_id' => max(0, $tid)];
    if ($aid > 0) {
        $q['asociacion_id'] = $aid;
    }
    if (AuthService::isDelegadoAsociacion() && $fvdRepCampeonatoId > 0) {
        $q['campeonato_id'] = $fvdRepCampeonatoId;
    }
    $u = $fvdUrlSelf . '?' . http_build_query($q);
    if ($fvdRepEmbedActive) {
        $u = fvd_append_embed_to_url($u);
    }

    return $u;
};
$mkReportUrl = static function (string $tipo, bool $inline) use ($ctrl, $tSel, $aSel): string {
    $base = $ctrl->reportExportUrl($tipo, $tSel, $aSel);
    return $base . ($inline ? '&inline=1' : '');
};
?>
<div class="fvd-rep-insc-head" style="margin-bottom:1rem">
    <?php if (function_exists('fvd_delegado_inner_heading_visible') && fvd_delegado_inner_heading_visible()): ?>
    <h1 class="fvd-atletas-title" style="margin:0 0 10px">Reportes del torneo</h1>
    <?php endif; ?>
    <?php if (!empty($fvd_rep_campeonato_error)): ?>
        <p class="fvd-mod-msg" style="max-width:42rem;margin:0 0 10px"><?= htmlspecialchars((string) $fvd_rep_campeonato_error, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>
    <?php if ($fvdRepTorneos !== []): ?>
    <div class="fvd-rep-torneo-pills" role="tablist" aria-label="Torneos del informe" style="display:flex;flex-wrap:wrap;gap:8px;align-items:center">
        <?php
        $aidForTorneoUrls = $aSel;
        if ($aidForTorneoUrls <= 0 && AuthService::role() === AuthService::ROLE_FVD_ADMIN && ($fvdRepAsociaciones ?? []) !== []) {
            $aidForTorneoUrls = (int) ($fvdRepAsociaciones[0]['id'] ?? 0);
        }
        ?>
        <?php foreach ($fvdRepTorneos as $tr): ?>
            <?php
            $tidP = (int) ($tr['torneo'] ?? 0);
            if ($tidP <= 0) {
                continue;
            }
            $isAct = $tidP === $tSel;
            $hrefP = htmlspecialchars($fvdRepInscListUrl($tidP, max(0, $aidForTorneoUrls)), ENT_QUOTES, 'UTF-8');
            $labelP = htmlspecialchars(trim((string) ($tr['nombre'] ?? '')) !== '' ? (string) $tr['nombre'] : ('Torneo #' . $tidP), ENT_QUOTES, 'UTF-8');
            ?>
            <a
                role="tab"
                aria-selected="<?= $isAct ? 'true' : 'false' ?>"
                class="fvd-rep-torneo-pill<?= $isAct ? ' fvd-rep-torneo-pill--active' : '' ?>"
                href="<?= $hrefP ?>"
                style="display:inline-flex;align-items:center;gap:6px;padding:8px 14px;border-radius:10px;font-size:0.875rem;font-weight:700;text-decoration:none;border:2px solid <?= $isAct ? 'var(--fvd-amarillo,#fbbf24)' : 'rgba(255,255,255,.22)' ?>;background:<?= $isAct ? 'rgba(251,191,36,.18)' : 'rgba(0,0,0,.12)' ?>;color:var(--fvd-fg,#f5f5f5)"
            ><?= $labelP ?> <span style="opacity:.85;font-weight:600">#<?= $tidP ?></span></a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<?php if ($fvdRepTorneos === [] && empty($fvd_rep_campeonato_error)): ?>
    <p class="fvd-mod-msg">No hay torneos disponibles. Si es delegado, confirme <code>campeonato_id</code> en la URL, convocatoria y <code>torneo_id</code> o evento en contexto.</p>
<?php endif; ?>

<?php
$fvdRepStatsPorAsoc = $fvdRepStatsPorAsoc ?? [];
$fvdRepAsociacionesList = $fvdRepAsociaciones ?? [];
$fmtN = static fn (float $v): string => number_format($v, 2, ',', '.');
?>
<?php if (AuthService::isSuperAdmin() && $fvdRepTorneos !== [] && $tSel > 0 && $fvdRepAsociacionesList !== []): ?>
<section class="fvd-card fvd-rep-asoc-resumen" style="padding:14px;margin-bottom:1rem;overflow-x:auto" aria-label="Elegir asociación para el informe">
    <h2 style="margin:0 0 8px;font-size:1.05rem">Resumen por asociación (torneo #<?= (int) $tSel ?>)</h2>
    <p style="margin:0 0 10px;font-size:0.8125rem;color:var(--fvd-muted);max-width:48rem">
        Datos desde <code>atletas</code> (marcas en este <code>torneo_id</code>) y finanzas en <code>deuda_asociaciones</code> / <code>relacion_pagos</code>.
        <strong>Pulse una fila</strong> para fijar el club y cargar los informes PDF/HTML de abajo.
    </p>
    <table class="fvd-mod-table fvd-rep-asoc-resumen__table" style="font-size:0.8125rem;min-width:48rem">
        <thead>
        <tr>
            <th scope="col">Asociación</th>
            <th scope="col" class="fvd-rep-asoc-resumen__num">Inscritos</th>
            <th scope="col" class="fvd-rep-asoc-resumen__num">Carnet</th>
            <th scope="col" class="fvd-rep-asoc-resumen__num">Afiliación</th>
            <th scope="col" class="fvd-rep-asoc-resumen__num">Anual.</th>
            <th scope="col" class="fvd-rep-asoc-resumen__num">Trasp.</th>
            <th scope="col" class="fvd-rep-asoc-resumen__num">Deuda EUR</th>
            <th scope="col" class="fvd-rep-asoc-resumen__num">Pagado EUR</th>
            <th scope="col" class="fvd-rep-asoc-resumen__num">Saldo EUR</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($fvdRepAsociacionesList as $arPick): ?>
            <?php
            $aidR = (int) ($arPick['id'] ?? 0);
            if ($aidR <= 0) {
                continue;
            }
            $sr = $fvdRepStatsPorAsoc[$aidR] ?? null;
            $nomAsoc = trim((string) ($arPick['nombre'] ?? ''));
            if ($sr !== null && ($sr['asoc_nombre'] ?? '') !== '') {
                $nomAsoc = (string) $sr['asoc_nombre'];
            }
            $eurOk = $sr !== null && ($sr['monto_total_eur'] ?? null) !== null && (float) $sr['monto_total_eur'] > 0;
            $saldo = ($sr !== null && $eurOk) ? ($sr['saldo_eur'] ?? null) : null;
            $rowUrl = htmlspecialchars($fvdRepInscListUrl($tSel, $aidR), ENT_QUOTES, 'UTF-8');
            $selClass = $aidR === $aSel ? ' fvd-rep-asoc-resumen__row--sel' : '';
            ?>
            <tr
                class="fvd-rep-asoc-resumen__row fvd-rep-asoc-resumen__row--click<?= $selClass ?>"
                data-row-url="<?= $rowUrl ?>"
                tabindex="0"
                role="link"
                aria-label="Seleccionar asociación <?= htmlspecialchars($nomAsoc !== '' ? $nomAsoc : ('#' . $aidR), ENT_QUOTES, 'UTF-8') ?>"
            >
                <td><?= htmlspecialchars($nomAsoc !== '' ? $nomAsoc : ('#' . $aidR), ENT_QUOTES, 'UTF-8') ?></td>
                <td class="fvd-rep-asoc-resumen__num"><?= $sr !== null ? (int) ($sr['n_inscritos'] ?? 0) : '—' ?></td>
                <td class="fvd-rep-asoc-resumen__num"><?= $sr !== null ? (int) ($sr['n_carnets'] ?? 0) : '—' ?></td>
                <td class="fvd-rep-asoc-resumen__num"><?= $sr !== null ? (int) ($sr['n_afiliados'] ?? 0) : '—' ?></td>
                <td class="fvd-rep-asoc-resumen__num"><?= $sr !== null ? (int) ($sr['n_anualidad'] ?? 0) : '—' ?></td>
                <td class="fvd-rep-asoc-resumen__num"><?= $sr !== null ? (int) ($sr['n_traspasos'] ?? 0) : '—' ?></td>
                <td class="fvd-rep-asoc-resumen__num"><?= $sr !== null && $eurOk ? $fmtN((float) $sr['monto_total_eur']) . ' €' : '—' ?></td>
                <td class="fvd-rep-asoc-resumen__num"><?= $sr !== null ? $fmtN((float) ($sr['pagado_eur'] ?? 0)) . ' €' : '—' ?></td>
                <td class="fvd-rep-asoc-resumen__num"><?= $saldo !== null ? $fmtN((float) $saldo) . ' €' : '—' ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>
<script>
(function () {
    var tb = document.querySelector('.fvd-rep-asoc-resumen__table tbody');
    if (!tb) return;
    function go(tr) {
        var u = tr.getAttribute('data-row-url');
        if (u) window.location.href = u;
    }
    tb.addEventListener('click', function (ev) {
        var tr = ev.target.closest('tr[data-row-url]');
        if (!tr || !tb.contains(tr)) return;
        go(tr);
    });
    tb.addEventListener('keydown', function (ev) {
        if (ev.key !== 'Enter' && ev.key !== ' ') return;
        var tr = ev.target.closest('tr[data-row-url]');
        if (!tr || !tb.contains(tr)) return;
        ev.preventDefault();
        go(tr);
    });
})();
</script>
<?php endif; ?>
<style>
.fvd-rep-asoc-resumen__num { text-align: right; font-variant-numeric: tabular-nums; }
.fvd-rep-asoc-resumen__row--click { cursor: pointer; }
.fvd-rep-asoc-resumen__row--click:hover td { background: rgba(255, 255, 255, .05); }
.fvd-rep-asoc-resumen__row--click:focus-visible { outline: 2px solid var(--fvd-amarillo, #fbbf24); outline-offset: -2px; }
.fvd-rep-asoc-resumen__row--sel td { background: rgba(251, 191, 36, .12); }
</style>

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
.fvd-rep-stats {
    display: flex;
    flex-wrap: wrap;
    align-items: stretch;
    gap: 8px;
    margin: 0 0 12px;
}
.fvd-rep-stat {
    display: inline-flex;
    flex-direction: column;
    justify-content: center;
    min-height: 2.75rem;
    padding: 8px 12px;
    border-radius: 10px;
    border: 1px solid var(--fvd-border, rgba(255,255,255,.15));
    background: rgba(0,0,0,.12);
    font-size: 0.75rem;
    line-height: 1.25;
}
.fvd-rep-stat__k {
    color: var(--fvd-muted);
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.02em;
    margin-bottom: 2px;
}
.fvd-rep-stat__v {
    font-weight: 700;
    font-size: 0.95rem;
    color: var(--fvd-fg, #f5f5f5);
}
.fvd-rep-stat--club {
    flex: 1 1 12rem;
    border-color: var(--fvd-amarillo, rgba(251,191,36,.45));
    background: rgba(251,191,36,.08);
}
.fvd-rep-gestion {
    font-size: 0.8125rem;
    color: var(--fvd-muted);
    margin: 0 0 14px;
}
.fvd-rep-gestion a { color: inherit; text-decoration: underline; }
.fvd-rep-stats--deuda { margin-top: 10px; }
.fvd-rep-deuda-note {
    font-size: 0.75rem;
    color: var(--fvd-muted);
    margin: 0 0 8px;
    max-width: 48rem;
}
.fvd-rep-deuda-empty {
    font-size: 0.8125rem;
    color: var(--fvd-muted);
    margin: 10px 0 0;
    max-width: 48rem;
}
.fvd-rep-stat__n { font-weight: 600; color: var(--fvd-muted); font-size: 0.8rem; }
.fvd-rep-stat--total {
    border-color: var(--fvd-amarillo, rgba(251,191,36,.45));
    background: rgba(251,191,36,.1);
}
</style>

<?php
$fvdRepStats = $fvdRepStats ?? null;
?>
<?php if (is_array($fvdRepStats)): ?>
<div class="fvd-rep-stats" aria-label="Estadísticas de la asociación en este torneo">
    <div class="fvd-rep-stat fvd-rep-stat--club">
        <span class="fvd-rep-stat__k">Asociación</span>
        <span class="fvd-rep-stat__v"><?= htmlspecialchars($fvdRepStats['asoc_nombre'] !== '' ? $fvdRepStats['asoc_nombre'] : ('#' . (int) ($fvdRepStats['asociacion_id'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></span>
    </div>
    <div class="fvd-rep-stat">
        <span class="fvd-rep-stat__k">Inscritos</span>
        <span class="fvd-rep-stat__v"><?= (int) ($fvdRepStats['n_inscritos'] ?? 0) ?></span>
    </div>
    <div class="fvd-rep-stat">
        <span class="fvd-rep-stat__k">Carnet</span>
        <span class="fvd-rep-stat__v"><?= (int) ($fvdRepStats['n_carnets'] ?? 0) ?></span>
    </div>
    <div class="fvd-rep-stat">
        <span class="fvd-rep-stat__k">Afiliación</span>
        <span class="fvd-rep-stat__v"><?= (int) ($fvdRepStats['n_afiliados'] ?? 0) ?></span>
    </div>
    <?php if (($fvdRepStats['monto_total_eur'] ?? null) !== null && (float) $fvdRepStats['monto_total_eur'] > 0): ?>
    <div class="fvd-rep-stat">
        <span class="fvd-rep-stat__k">Deuda (EUR)</span>
        <span class="fvd-rep-stat__v"><?= $fmtN((float) $fvdRepStats['monto_total_eur']) ?> €</span>
    </div>
    <div class="fvd-rep-stat">
        <span class="fvd-rep-stat__k">Pagado (EUR)</span>
        <span class="fvd-rep-stat__v"><?= $fmtN((float) ($fvdRepStats['pagado_eur'] ?? 0)) ?> €</span>
    </div>
    <div class="fvd-rep-stat">
        <span class="fvd-rep-stat__k">Saldo (EUR)</span>
        <span class="fvd-rep-stat__v"><?= $fmtN((float) ($fvdRepStats['saldo_eur'] ?? 0)) ?> €</span>
    </div>
    <?php else: ?>
    <div class="fvd-rep-stat">
        <span class="fvd-rep-stat__k">Pagado (EUR cont.)</span>
        <span class="fvd-rep-stat__v"><?= $fmtN((float) ($fvdRepStats['pagado_eur'] ?? 0)) ?> €</span>
    </div>
    <?php endif; ?>
</div>
<?php
$dRow = $fvdRepStats['deuda'] ?? null;
$deudaEurPorConcepto = is_array($dRow) ? $ctrl->allocDeudaEurPorConcepto($dRow) : null;
if (is_array($dRow)):
?>
    <?php if (($fvdRepStats['monto_total_eur'] ?? null) !== null && (float) $fvdRepStats['monto_total_eur'] > 0): ?>
    <p class="fvd-rep-deuda-note">Deuda detallada por concepto en <strong>EUR</strong> (prorrateo desde <code>deuda_asociaciones</code> según referencia Bs; total canónico <code>monto_total_eur</code>).</p>
    <?php endif; ?>
    <div class="fvd-rep-stats fvd-rep-stats--deuda" aria-label="Deuda detallada por concepto (EUR)">
        <div class="fvd-rep-stat">
            <span class="fvd-rep-stat__k">Inscritos (€)</span>
            <span class="fvd-rep-stat__v"><?= is_array($deudaEurPorConcepto) ? $fmtN((float) $deudaEurPorConcepto['inscripciones']) : '0,00' ?> € <span class="fvd-rep-stat__n">(<?= (int) ($dRow['total_inscritos'] ?? 0) ?>)</span></span>
        </div>
        <div class="fvd-rep-stat">
            <span class="fvd-rep-stat__k">Afiliaciones (€)</span>
            <span class="fvd-rep-stat__v"><?= is_array($deudaEurPorConcepto) ? $fmtN((float) $deudaEurPorConcepto['afiliacion']) : '0,00' ?> € <span class="fvd-rep-stat__n">(<?= (int) ($dRow['total_afiliados'] ?? 0) ?>)</span></span>
        </div>
        <div class="fvd-rep-stat">
            <span class="fvd-rep-stat__k">Anualidad (€)</span>
            <span class="fvd-rep-stat__v"><?= is_array($deudaEurPorConcepto) ? $fmtN((float) $deudaEurPorConcepto['anualidad']) : '0,00' ?> € <span class="fvd-rep-stat__n">(<?= (int) ($dRow['total_anualidad'] ?? 0) ?>)</span></span>
        </div>
        <div class="fvd-rep-stat">
            <span class="fvd-rep-stat__k">Carnets (€)</span>
            <span class="fvd-rep-stat__v"><?= is_array($deudaEurPorConcepto) ? $fmtN((float) $deudaEurPorConcepto['carnets']) : '0,00' ?> € <span class="fvd-rep-stat__n">(<?= (int) ($dRow['total_carnets'] ?? 0) ?>)</span></span>
        </div>
        <div class="fvd-rep-stat">
            <span class="fvd-rep-stat__k">Traspasos (€)</span>
            <span class="fvd-rep-stat__v"><?= is_array($deudaEurPorConcepto) ? $fmtN((float) $deudaEurPorConcepto['traspasos']) : '0,00' ?> € <span class="fvd-rep-stat__n">(<?= (int) ($dRow['total_traspasos'] ?? 0) ?>)</span></span>
        </div>
        <div class="fvd-rep-stat fvd-rep-stat--total">
            <span class="fvd-rep-stat__k">Total deuda (€)</span>
            <span class="fvd-rep-stat__v"><?= $fmtN((float) ($fvdRepStats['monto_total_eur'] ?? 0)) ?> €</span>
        </div>
    </div>
<?php else: ?>
    <p class="fvd-rep-deuda-empty">No hay fila de deuda para este torneo y asociación en <code>deuda_asociaciones</code>. Puede generarla desde el panel del delegado o desde el módulo de deudas.</p>
<?php endif; ?>
<?php endif; ?>

<p class="fvd-rep-gestion">
    Gestión:
    <a href="<?= htmlspecialchars(fvd_return_append_to_url($fvdUrlDeuda . '?action=form&tid=' . $tSel . '&aid=' . $aSel), ENT_QUOTES, 'UTF-8') ?>">Deuda / actualizar desde atletas</a>
    · <a href="<?= htmlspecialchars(fvd_return_append_to_url($fvdUrlPagos), ENT_QUOTES, 'UTF-8') ?>">Relación de pagos</a>
</p>

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
        <p style="margin:0 0 12px;font-size:0.8125rem;color:var(--fvd-muted);flex-shrink:0">Deuda, pagos y estado de cuenta (los totales arriba son de esta asociación y torneo).</p>
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
    </section>
</div>

<?php elseif ($fvdRepTorneos !== [] && ($tSel <= 0 || $aSel <= 0)): ?>
    <p class="fvd-mod-msg">No hay torneo o asociación seleccionable. Compruebe permisos o datos en <a href="<?= htmlspecialchars(fvd_return_append_to_url($fvdUrlInscTorneo), ENT_QUOTES, 'UTF-8') ?>">inscripciones por torneo</a>.</p>
<?php endif; ?>

<p style="font-size:0.8125rem;color:var(--fvd-muted)">
    <a href="<?= htmlspecialchars(fvd_return_append_to_url($fvdUrlInscTorneo . ($tSel > 0 ? '?torneo_id=' . $tSel : '')), ENT_QUOTES, 'UTF-8') ?>">Tabla inscripcion_torneo</a>
    · <a href="<?= htmlspecialchars(fvd_return_append_to_url(fvd_module_url('atletas/index.php?action=list')), ENT_QUOTES, 'UTF-8') ?>">Módulo Atletas</a>
</p>

