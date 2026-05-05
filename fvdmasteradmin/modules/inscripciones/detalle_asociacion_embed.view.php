<?php
/**
 * Detalle embebido (EUR): movimientos por concepto, deuda prorrateada, pagos y saldo.
 *
 * @var array<string, mixed>|null $fvdRepStats
 * @var array<string, float> $fvdRepDeudaEurConceptos
 * @var list<array> $fvdRepPagosEurRows
 */
if (!function_exists('fvd_append_embed_to_url')) {
    require_once dirname(__DIR__, 3) . '/config/fvd_navigation_return.php';
}
$tSel = (int) ($fvdRepDefaultTorneo ?? 0);
$aSel = (int) ($fvdRepDefaultAsoc ?? 0);
$fvdUrlSelf = fvd_module_url('inscripciones/index.php');
$fvdRepEmbedActive = function_exists('fvd_master_embed_active') && fvd_master_embed_active();
$volverQs = ['torneo_id' => $tSel];
$cg = isset($fvd_rep_campeonato_id) ? (int) $fvd_rep_campeonato_id : 0;
if (AuthService::isDelegadoAsociacion() && $cg > 0) {
    $volverQs['campeonato_id'] = $cg;
}
$volverUrl = $fvdUrlSelf . '?' . http_build_query($volverQs);
if ($fvdRepEmbedActive) {
    $volverUrl = fvd_append_embed_to_url($volverUrl);
}
$volverUrl = htmlspecialchars($volverUrl, ENT_QUOTES, 'UTF-8');
$st = is_array($fvdRepStats ?? null) ? $fvdRepStats : null;
if ($st === null) {
    echo '<div class="fvd-mod-msg" style="max-width:40rem">No se pudieron cargar las estadísticas para el torneo y la asociación.</div>';
    return;
}
$deur = is_array($fvdRepDeudaEurConceptos ?? null) ? $fvdRepDeudaEurConceptos : array_fill_keys(
    ['inscripciones', 'afiliacion', 'carnets', 'traspasos', 'anualidad'],
    0.0
);
$fmtN = static fn (float $v): string => number_format($v, 2, ',', '.');
$montoEur = ($st['monto_total_eur'] ?? null) !== null && $st['monto_total_eur'] !== '' ? (float) $st['monto_total_eur'] : null;
$pagE = (float) ($st['pagado_eur'] ?? 0);
$saldoE = (float) ($st['saldo_eur'] ?? 0);
$labels = [
    'inscripciones' => 'Inscripción',
    'afiliacion'    => 'Afiliación',
    'carnets'       => 'Carnets',
    'traspasos'     => 'Traspaso',
    'anualidad'     => 'Anualidad',
];
/** Texto del enlace al listado de atletas (marca 1) en el módulo de deudas — mismo criterio que conteos. */
$fvdListadoEnlace = [
    'inscripciones' => 'Listado de inscritos',
    'afiliacion'    => 'Listado de afiliados',
    'carnets'       => 'Listado de carnets',
    'traspasos'     => 'Listado de traspasos',
    'anualidad'     => 'Listado de anualidad',
];
$fvdTieneFilaDeuda = is_array($st['deuda'] ?? null);
$fvdUrlDeuda = fvd_module_url('deuda_asociacion/index.php');
/** @return string URL (ya escapada) al reporte de atletas por concepto; requiere fila en deuda_asociaciones. */
$fvdHrefReporteConcepto = static function (string $conceptoKey) use ($fvdUrlDeuda, $tSel, $aSel): string {
    $q = [
        'action' => 'reporte_conceptos',
        'tid' => $tSel,
        'aid' => $aSel,
        'concepto' => $conceptoKey,
    ];

    return htmlspecialchars(
        fvd_return_append_to_url($fvdUrlDeuda . '?' . http_build_query($q)),
        ENT_QUOTES,
        'UTF-8'
    );
};
$fvdHrefReporteTodos = htmlspecialchars(
    fvd_return_append_to_url(
        $fvdUrlDeuda . '?' . http_build_query(['action' => 'reporte_conceptos', 'tid' => $tSel, 'aid' => $aSel])
    ),
    ENT_QUOTES,
    'UTF-8'
);
?>
<div class="fvd-insc-det-emb" style="max-width:52rem;margin:0 auto">
    <a href="<?= $volverUrl ?>" class="fvd-insc-det-emb__back" style="display:inline-flex;align-items:center;gap:6px;margin-bottom:12px;font-size:0.85rem;font-weight:700;text-decoration:none;color:var(--fvd-amarillo,#fbbf24)">← Volver al listado de asociaciones</a>

    <h1 class="fvd-atletas-title" style="margin:0 0 6px;font-size:1.2rem">
        <?= htmlspecialchars($st['asoc_nombre'] !== '' ? (string) $st['asoc_nombre'] : ('Asociación #' . $aSel), ENT_QUOTES, 'UTF-8') ?>
    </h1>
    <p style="margin:0 0 1rem;font-size:0.875rem;color:var(--fvd-muted)">
        Torneo #<?= (int) $tSel ?> · Todos los importes de deuda, pagos y <strong>saldo</strong> se muestran en <strong>euros (€)</strong>. Los importes en bolívares (referencia) no se muestran.
    </p>

    <section class="fvd-card" style="padding:14px;margin-bottom:12px" aria-label="Movimientos">
        <h2 style="margin:0 0 10px;font-size:1rem">Movimientos (atletas con marca en este torneo)</h2>
        <div class="fvd-insc-det-emb__chips fvd-insc-det-emb__chips--row" role="group" aria-label="Conteos por concepto">
            <?php
            $moves = [
                ['k' => 'n_inscritos', 'l' => 'Inscripción', 'c' => (int) ($st['n_inscritos'] ?? 0)],
                ['k' => 'n_carnets', 'l' => 'Carnet', 'c' => (int) ($st['n_carnets'] ?? 0)],
                ['k' => 'n_afiliados', 'l' => 'Afiliación', 'c' => (int) ($st['n_afiliados'] ?? 0)],
                ['k' => 'n_anualidad', 'l' => 'Anualidad', 'c' => (int) ($st['n_anualidad'] ?? 0)],
                ['k' => 'n_traspasos', 'l' => 'Traspaso', 'c' => (int) ($st['n_traspasos'] ?? 0)],
            ];
            foreach ($moves as $m) {
                echo '<span class="fvd-insc-det-emb__chip"><strong class="fvd-insc-det-emb__chip-lbl">' . htmlspecialchars($m['l'], ENT_QUOTES, 'UTF-8') . '</strong><span class="fvd-insc-det-emb__chip-n">' . (int) $m['c'] . '</span></span>';
            }
            ?>
        </div>
    </section>

    <section class="fvd-card" style="padding:14px;margin-bottom:12px" aria-label="Deuda prorrateada en EUR">
        <h2 style="margin:0 0 6px;font-size:1rem">Deuda por concepto (EUR)</h2>
        <p style="margin:0 0 6px;font-size:0.75rem;color:var(--fvd-muted);max-width:40rem">Reparto proporcional de <code>monto_total_eur</code> según el peso de cada concepto en la referencia Bs (tasas oficiales de la deuda generada).</p>
        <?php if ($fvdTieneFilaDeuda): ?>
        <p style="margin:0 0 10px;font-size:0.8rem;max-width:48rem">
            <a
                class="fvd-insc-det-emb__conclink"
                href="<?= $fvdHrefReporteTodos ?>"
            >Listados: todos los bloques a la vez</a>
            <span style="color:var(--fvd-muted)">
                Inscritos, afiliados, quienes llevan <strong>carnet</strong>, <strong>traspasos</strong> y <strong>anualidad</strong> (FVD, nombre, cédula) — criterio del cálculo de deuda.
            </span>
        </p>
        <?php else: ?>
        <p style="margin:0 0 10px;font-size:0.75rem;color:var(--fvd-muted);max-width:40rem">No hay fila en <code>deuda_asociaciones</code> para este torneo y club: no se pueden abrir los listados de atletas por concepto hasta generar o sincronizar la deuda.</p>
        <?php endif; ?>
        <table class="fvd-mod-table fvd-insc-det-emb__tab-deuda" style="font-size:0.85rem;min-width:20rem">
            <thead>
            <tr>
                <th scope="col">Concepto</th>
                <th scope="col" class="fvd-insc-det-emb__th-monto">Deuda (€) y listado de atletas</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($labels as $key => $label): ?>
                <tr>
                    <td><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></td>
                    <td class="fvd-insc-det-emb__monto-cel">
                        <div class="fvd-insc-det-emb__monto-line" role="group" aria-label="<?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>: monto y listado">
                            <span class="fvd-insc-det-emb__monto-eur"><?= $fmtN((float) ($deur[$key] ?? 0)) ?> €</span>
                            <?php if ($fvdTieneFilaDeuda): ?>
                            <span class="fvd-insc-det-emb__monto-sep" aria-hidden="true">·</span>
                            <a
                                class="fvd-insc-det-emb__conclink"
                                href="<?= $fvdHrefReporteConcepto($key) ?>"
                                title="Listado de atletas con este concepto en el torneo"
                            ><?= htmlspecialchars($fvdListadoEnlace[$key] ?? 'Listado', ENT_QUOTES, 'UTF-8') ?></a>
                            <?php else: ?>
                            <span class="fvd-insc-det-emb__monto-sep" aria-hidden="true">·</span>
                            <span class="fvd-insc-det-emb__nodetail" title="Requiere fila en deuda_asociaciones">—</span>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            <tr style="font-weight:800;border-top:2px solid var(--fvd-amarillo,#fbbf24)">
                <td>Total deuda (€)</td>
                <td class="fvd-insc-det-emb__monto-cel fvd-insc-det-emb__num"><?= $montoEur !== null && $montoEur > 0 ? $fmtN($montoEur) . ' €' : '—' ?></td>
            </tr>
            </tbody>
        </table>
    </section>

    <section class="fvd-card" style="padding:14px;margin-bottom:12px" aria-label="Pagos">
        <h2 style="margin:0 0 10px;font-size:1rem">Pagos registrados (EUR)</h2>
        <?php if (($fvdRepPagosEurRows ?? []) === []): ?>
            <p style="margin:0;font-size:0.85rem;color:var(--fvd-muted)">Sin recibos en <code>relacion_pagos</code> para este club y torneo.</p>
        <?php else: ?>
        <div style="overflow-x:auto">
            <table class="fvd-mod-table" style="font-size:0.8rem;min-width:28rem">
                <thead>
                <tr>
                    <th scope="col">#</th>
                    <th scope="col">Fecha</th>
                    <th scope="col" class="fvd-insc-det-emb__num">EUR</th>
                    <th scope="col">Forma</th>
                    <th scope="col">Ref.</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($fvdRepPagosEurRows as $pr): ?>
                    <tr>
                        <td><?= (int) ($pr['secuencia'] ?? 0) ?></td>
                        <td><?= htmlspecialchars((string) ($pr['fecha'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                        <td class="fvd-insc-det-emb__num"><?= $fmtN((float) ($pr['monto_dolares'] ?? 0)) ?> €</td>
                        <td><?= htmlspecialchars((string) ($pr['tipo_pago'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars(trim((string) ($pr['referencia'] ?? '')), ENT_QUOTES, 'UTF-8') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </section>

    <section class="fvd-card" style="padding:14px;border:1px solid rgba(251,191,36,.35);background:rgba(251,191,36,.08)" aria-label="Resumen">
        <h2 style="margin:0 0 10px;font-size:1rem">Resumen (EUR)</h2>
        <dl style="display:grid;grid-template-columns:auto 1fr;gap:6px 16px;font-size:0.9rem;margin:0;max-width:24rem">
            <dt>Total deuda</dt>
            <dd style="margin:0;font-weight:800"><?= $montoEur !== null && $montoEur > 0 ? $fmtN($montoEur) . ' €' : '—' ?></dd>
            <dt>Total pagado</dt>
            <dd style="margin:0;font-weight:800"><?= $fmtN($pagE) ?> €</dd>
            <dt>Diferencia (saldo)</dt>
            <dd style="margin:0;font-weight:800"><?= $montoEur !== null && $montoEur > 0 ? $fmtN($saldoE) . ' €' : '—' ?></dd>
        </dl>
    </section>
</div>
<style>
.fvd-insc-det-emb__num { text-align: right; font-variant-numeric: tabular-nums; }
.fvd-insc-det-emb__chips--row {
    display: flex;
    flex-wrap: nowrap;
    align-items: stretch;
    gap: 8px;
    width: 100%;
    min-width: 0;
    overflow-x: auto;
    padding-bottom: 2px;
    -webkit-overflow-scrolling: touch;
    scrollbar-gutter: stable;
}
.fvd-insc-det-emb__chip {
    flex: 0 0 auto;
    display: inline-flex;
    flex-direction: column;
    min-width: 5.5rem;
    max-width: 7.5rem;
    padding: 8px 10px;
    border-radius: 10px;
    border: 1px solid var(--fvd-border, rgba(255, 255, 255, 0.12));
    background: rgba(0, 0, 0, 0.1);
    font-size: 0.8rem;
    justify-content: center;
}
.fvd-insc-det-emb__chip-lbl {
    opacity: 0.8;
    font-size: 0.65rem;
    text-transform: uppercase;
    line-height: 1.2;
    font-weight: 800;
}
.fvd-insc-det-emb__chip-n {
    font-size: 1.1rem;
    font-weight: 800;
    font-variant-numeric: tabular-nums;
    margin-top: 2px;
}
.fvd-insc-det-emb__th-monto { min-width: 12rem; text-align: right; }
.fvd-insc-det-emb__monto-cel { vertical-align: middle; }
.fvd-insc-det-emb__monto-line {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: flex-end;
    gap: 0.2rem 0.45rem;
    text-align: right;
}
.fvd-insc-det-emb__monto-eur { font-weight: 600; font-variant-numeric: tabular-nums; }
.fvd-insc-det-emb__monto-sep { color: var(--fvd-muted); user-select: none; }
.fvd-insc-det-emb__nodetail { color: var(--fvd-muted); font-size: 0.75rem; font-weight: 500; }
.fvd-insc-det-emb__conclink {
    font-size: 0.78rem;
    font-weight: 700;
    text-decoration: none;
    color: var(--fvd-amarillo, #fbbf24);
    white-space: nowrap;
}
.fvd-insc-det-emb__conclink:hover { text-decoration: underline; }
</style>
