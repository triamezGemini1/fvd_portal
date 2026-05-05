<?php
/**
 * Listado compacto (panel maestro embebido): una columna de movimientos y montos solo en EUR.
 *
 * @var int $fvdRepDefaultTorneo
 * @var array<int, array<string, mixed>> $fvdRepStatsPorAsoc
 * @var list<array{id:int|string,nombre:string}> $fvdRepAsociaciones
 */
if (!function_exists('fvd_append_embed_to_url')) {
    require_once dirname(__DIR__, 3) . '/config/fvd_navigation_return.php';
}
$tSel = (int) ($fvdRepDefaultTorneo ?? 0);
$fvdUrlSelf = fvd_module_url('inscripciones/index.php');
$fvdRepEmbedActive = function_exists('fvd_master_embed_active') && fvd_master_embed_active();
$cg = isset($fvd_rep_campeonato_id) ? (int) $fvd_rep_campeonato_id : 0;
$fvdRepInscListUrl = static function (int $tid, int $aid) use ($fvdUrlSelf, $cg, $fvdRepEmbedActive): string {
    $q = ['torneo_id' => max(0, $tid), 'asociacion_id' => max(0, $aid)];
    if (AuthService::isDelegadoAsociacion() && $cg > 0) {
        $q['campeonato_id'] = $cg;
    }
    $u = $fvdUrlSelf . '?' . http_build_query($q);
    if ($fvdRepEmbedActive) {
        $u = fvd_append_embed_to_url($u);
    }

    return $u;
};
$fmtN = static fn (float $v): string => number_format($v, 2, ',', '.');
$fvdRepStatsPorAsoc = $fvdRepStatsPorAsoc ?? [];
$fvdRepAsociacionesList = $fvdRepAsociaciones ?? [];
$torneoNombre = '';
foreach (($fvdRepTorneos ?? []) as $tr) {
    if ((int) ($tr['torneo'] ?? 0) === $tSel) {
        $torneoNombre = trim((string) ($tr['nombre'] ?? ''));
        break;
    }
}
?>
<div class="fvd-insc-maestro-emb" style="max-width:72rem;margin:0 auto">
    <h1 class="fvd-atletas-title" style="margin:0 0 6px;font-size:1.15rem">Movimientos y finanzas por asociación</h1>
    <p class="fvd-mod-msg" style="margin:0 0 12px;max-width:48rem;font-size:0.875rem">
        Torneo: <strong><?= htmlspecialchars($torneoNombre !== '' ? $torneoNombre : ('#' . $tSel), ENT_QUOTES, 'UTF-8') ?></strong>
        (<?= (int) $tSel ?>). Pulse un club para el detalle en <strong>euros</strong> (deuda, pagos y movimientos).
    </p>
    <section class="fvd-card fvd-rep-asoc-resumen" style="padding:14px;overflow-x:auto" aria-label="Resumen por asociación">
        <table class="fvd-mod-table fvd-rep-asoc-resumen__table" style="font-size:0.8125rem;min-width:44rem">
            <thead>
            <tr>
                <th scope="col">Asociación</th>
                <th scope="col" class="fvd-rep-asoc-resumen__num">Inscr.</th>
                <th scope="col" class="fvd-rep-asoc-resumen__num">Carnet</th>
                <th scope="col" class="fvd-rep-asoc-resumen__num">Afiliac.</th>
                <th scope="col" class="fvd-rep-asoc-resumen__num">Anual.</th>
                <th scope="col" class="fvd-rep-asoc-resumen__num">Trasp.</th>
                <th scope="col" class="fvd-rep-asoc-resumen__num">Deuda €</th>
                <th scope="col" class="fvd-rep-asoc-resumen__num">Pagado €</th>
                <th scope="col" class="fvd-rep-asoc-resumen__num">Dif. €</th>
                <th scope="col" class="fvd-rep-asoc-resumen__detail">Detalle</th>
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
                $deudaE = $sr !== null && $eurOk ? (float) $sr['monto_total_eur'] : null;
                $pagE = $sr !== null ? (float) ($sr['pagado_eur'] ?? 0) : 0.0;
                $difE = $deudaE !== null ? max(0.0, round($deudaE - $pagE, 2)) : null;
                $rowUrl = htmlspecialchars($fvdRepInscListUrl($tSel, $aidR), ENT_QUOTES, 'UTF-8');
                ?>
                <tr
                    class="fvd-rep-asoc-resumen__row fvd-rep-asoc-resumen__row--click"
                    data-row-url="<?= $rowUrl ?>"
                    tabindex="0"
                    role="link"
                    aria-label="Abrir detalle: <?= htmlspecialchars($nomAsoc !== '' ? $nomAsoc : ('#' . $aidR), ENT_QUOTES, 'UTF-8') ?>"
                >
                    <td><?= htmlspecialchars($nomAsoc !== '' ? $nomAsoc : ('#' . $aidR), ENT_QUOTES, 'UTF-8') ?></td>
                    <td class="fvd-rep-asoc-resumen__num"><?= $sr !== null ? (int) ($sr['n_inscritos'] ?? 0) : '—' ?></td>
                    <td class="fvd-rep-asoc-resumen__num"><?= $sr !== null ? (int) ($sr['n_carnets'] ?? 0) : '—' ?></td>
                    <td class="fvd-rep-asoc-resumen__num"><?= $sr !== null ? (int) ($sr['n_afiliados'] ?? 0) : '—' ?></td>
                    <td class="fvd-rep-asoc-resumen__num"><?= $sr !== null ? (int) ($sr['n_anualidad'] ?? 0) : '—' ?></td>
                    <td class="fvd-rep-asoc-resumen__num"><?= $sr !== null ? (int) ($sr['n_traspasos'] ?? 0) : '—' ?></td>
                    <td class="fvd-rep-asoc-resumen__num"><?= $deudaE !== null ? $fmtN($deudaE) . ' €' : '—' ?></td>
                    <td class="fvd-rep-asoc-resumen__num"><?= $sr !== null ? $fmtN($pagE) . ' €' : '—' ?></td>
                    <td class="fvd-rep-asoc-resumen__num"><?= $difE !== null ? $fmtN($difE) . ' €' : '—' ?></td>
                    <td class="fvd-rep-asoc-resumen__detail">
                        <a
                            class="fvd-rep-asoc-resumen__detail-link"
                            href="<?= $rowUrl ?>"
                        >Ver detalle</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </section>
</div>
<style>
.fvd-insc-maestro-emb .fvd-rep-asoc-resumen__num { text-align: right; font-variant-numeric: tabular-nums; }
.fvd-insc-maestro-emb .fvd-rep-asoc-resumen__row--click { cursor: pointer; }
.fvd-insc-maestro-emb .fvd-rep-asoc-resumen__row--click:hover td { background: rgba(255, 255, 255, .06); }
.fvd-insc-maestro-emb .fvd-rep-asoc-resumen__row--click:focus-visible { outline: 2px solid var(--fvd-amarillo, #fbbf24); outline-offset: -2px; }
.fvd-insc-maestro-emb .fvd-rep-asoc-resumen__detail { text-align: center; white-space: nowrap; }
.fvd-insc-maestro-emb .fvd-rep-asoc-resumen__detail-link {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0.2rem 0.5rem;
    border-radius: 0.25rem;
    font-size: 0.75rem;
    font-weight: 700;
    text-decoration: none;
    color: var(--fvd-amarillo, #fbbf24);
    background: rgba(255, 255, 255, 0.08);
    border: 1px solid rgba(255, 255, 255, 0.15);
}
.fvd-insc-maestro-emb .fvd-rep-asoc-resumen__detail-link:hover { background: rgba(255, 255, 255, 0.14); }
</style>
<script>
(function () {
    var tb = document.querySelector('.fvd-insc-maestro-emb .fvd-rep-asoc-resumen__table tbody');
    if (!tb) return;
    function go(tr) {
        var u = tr.getAttribute('data-row-url');
        if (u) window.location.href = u;
    }
    tb.addEventListener('click', function (ev) {
        if (ev.target.closest('a.fvd-rep-asoc-resumen__detail-link')) {
            return;
        }
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
