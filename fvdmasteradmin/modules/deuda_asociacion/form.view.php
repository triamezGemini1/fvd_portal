<?php
/** @var ?array $row */
/** @var string $selfUrl */
/** @var ?array $fvdCostoTarifa */
/** @var bool $fvdPuedeActualizarDeuda */
/** @var list<array<string,mixed>> $fvdPagosRecibos */
/** @var float $fvdPagosSubtotalEur */
/** @var float $fvdPagosSubtotalBs */
/** @var string $fvdUrlRelacionPago */
/** @var array<string,string> $fvdTiposPagoOpciones */
$r = $row ?? [];
$c = $fvdCostoTarifa ?? [];
$fvdPuedeActualizarDeuda = isset($fvdPuedeActualizarDeuda) ? (bool) $fvdPuedeActualizarDeuda : false;
$fvdPagosRecibos = $fvdPagosRecibos ?? [];
$fvdPagosSubtotalEur = isset($fvdPagosSubtotalEur) ? (float) $fvdPagosSubtotalEur : 0.0;
$fvdPagosSubtotalBs = isset($fvdPagosSubtotalBs) ? (float) $fvdPagosSubtotalBs : 0.0;
$fvdUrlRelacionPago = $fvdUrlRelacionPago ?? '';
$fvdTiposPagoOpciones = $fvdTiposPagoOpciones ?? [];
$fvdFmtMonto = static function ($v, int $dec = 2): string {
    if ($v === '' || $v === null) {
        return '—';
    }

    return number_format((float) $v, $dec, ',', '.');
};
$reporteConceptosUrl = $selfUrl . '?action=reporte_conceptos&tid=' . (int) ($r['torneo_id'] ?? 0) . '&aid=' . (int) ($r['asociacion_id'] ?? 0);

/**
 * @param array<string, mixed> $costo
 */
$fvdTarifa = static function (array $costo, string $key): string {
    if (!array_key_exists($key, $costo)) {
        return '—';
    }
    return htmlspecialchars(number_format((float) $costo[$key], 2, ',', '.'), ENT_QUOTES, 'UTF-8');
};

$renglones = [
    ['total_inscritos', 'monto_inscritos', 'inscripciones', 'Inscripciones', 'Cantidad inscritos', 'Monto inscripciones'],
    ['total_afiliados', 'monto_afiliados', 'afiliacion', 'Afiliación', 'Cantidad afiliados', 'Monto afiliación'],
    ['total_carnets', 'monto_carnets', 'carnets', 'Carnets', 'Cantidad carnets', 'Monto carnets'],
    ['total_traspasos', 'monto_traspasos', 'traspasos', 'Traspasos', 'Cantidad traspasos', 'Monto traspasos'],
    ['total_anualidad', 'monto_anualidad', 'anualidad', 'Anualidad', 'Cantidad anualidad', 'Monto anualidad'],
];
?>

<style>
    .fvd-deuda-detalle-wrap {
        max-width: 960px;
        border: 3px solid #eab308;
        border-radius: 8px;
        padding: 1rem 1.25rem;
        background: #B3DBF5;
        box-sizing: border-box;
        color: #000;
        font-weight: 700;
    }
    .fvd-deuda-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 0.4rem 0.75rem;
        font-size: 0.875rem;
        font-weight: 700;
        line-height: 1.25;
        text-decoration: none;
        border-radius: 6px;
        border: 2px solid transparent;
        cursor: pointer;
        box-sizing: border-box;
        transition: background 0.15s ease, color 0.15s ease, border-color 0.15s ease;
    }
    .fvd-deuda-btn:focus-visible {
        outline: 2px solid #1d4ed8;
        outline-offset: 2px;
    }
    .fvd-deuda-btn--sm {
        padding: 0.28rem 0.55rem;
        font-size: 0.8125rem;
    }
    .fvd-deuda-btn--registros {
        background: #1d4ed8;
        color: #fff;
        border-color: #1e3a8a;
    }
    .fvd-deuda-btn--registros:hover {
        background: #1e40af;
        color: #fff;
    }
    .fvd-deuda-btn--conceptos {
        background: #0f766e;
        color: #fff;
        border-color: #115e59;
    }
    .fvd-deuda-btn--conceptos:hover {
        background: #115e59;
        color: #fff;
    }
    .fvd-deuda-btn--volver {
        background: #334155;
        color: #fff;
        border-color: #1e293b;
    }
    .fvd-deuda-btn--volver:hover {
        background: #1e293b;
        color: #fff;
    }
    .fvd-deuda-detalle-actions {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 0.5rem 0.65rem;
        margin-top: 1rem;
    }
    .fvd-deuda-detalle-actions button[type="submit"] {
        padding: 0.45rem 0.85rem;
        font-weight: 700;
        border-radius: 6px;
        cursor: pointer;
        background: #b45309;
        color: #fff;
        border: 2px solid #92400e;
    }
    .fvd-deuda-detalle-actions button[type="submit"]:hover {
        background: #92400e;
    }
    .fvd-deuda-detalle-wrap .fvd-deuda-row-enlace {
        text-align: right;
        white-space: nowrap;
        vertical-align: middle;
    }
    .fvd-deuda-detalle-wrap table {
        width: 100%;
        border-collapse: collapse;
    }
    .fvd-deuda-detalle-wrap th,
    .fvd-deuda-detalle-wrap td {
        padding: 0.35rem 0.5rem;
        vertical-align: middle;
        text-align: left;
    }
    .fvd-deuda-detalle-wrap th {
        font-weight: 700;
        color: #000;
        border-bottom: 1px solid #e5e7eb;
    }
    .fvd-deuda-detalle-wrap td {
        color: #000;
        font-weight: 700;
    }
    .fvd-deuda-detalle-wrap .fvd-input {
        color: #000;
        font-weight: 700;
    }
    .fvd-deuda-detalle-wrap .fvd-deuda-tarifa {
        text-align: right;
        white-space: nowrap;
        font-variant-numeric: tabular-nums;
    }
    .fvd-deuda-detalle-wrap .fvd-deuda-qty-input {
        max-width: 40%;
        min-width: 3.5rem;
        box-sizing: border-box;
    }
    .fvd-deuda-detalle-wrap .fvd-deuda-monto-input {
        max-width: 50%;
        min-width: 4.5rem;
        box-sizing: border-box;
    }
    .fvd-deuda-detalle-wrap .fvd-deuda-monto-total-row td {
        border-top: 1px solid #e5e7eb;
        font-weight: 700;
    }
    .fvd-deuda-detalle-titulo h1 {
        color: #000;
        font-weight: 700;
    }
    .fvd-deuda-detalle-titulo p {
        margin-top: 0;
        color: #000;
        font-weight: 700;
    }
    .fvd-deuda-pagos-bloque {
        margin-top: 1.25rem;
        padding-top: 1rem;
        border-top: 2px solid #94a3b8;
    }
    .fvd-deuda-pagos-bloque h2 {
        margin: 0 0 0.5rem;
        font-size: 1.05rem;
        color: #000;
    }
    .fvd-deuda-pagos-bloque .fvd-deuda-pagos-tabla {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.9rem;
    }
    .fvd-deuda-pagos-bloque .fvd-deuda-pagos-tabla th,
    .fvd-deuda-pagos-bloque .fvd-deuda-pagos-tabla td {
        padding: 0.35rem 0.45rem;
        border-bottom: 1px solid #cbd5e1;
        text-align: left;
    }
    .fvd-deuda-pagos-bloque .fvd-deuda-pagos-tabla th {
        background: #e2e8f0;
        font-weight: 700;
    }
    .fvd-deuda-pagos-bloque .fvd-deuda-pagos-tabla td.fvd-num {
        text-align: right;
        font-variant-numeric: tabular-nums;
    }
    .fvd-deuda-pagos-bloque .fvd-deuda-pagos-subtotal td {
        background: #fef3c7;
        border-top: 2px solid #b45309;
        font-weight: 800;
    }
</style>

<div class="fvd-deuda-detalle-titulo">
    <h1>Reporte detallado de costos</h1>
    <p>Torneo #<?= (int) ($r['torneo_id'] ?? 0) ?> · Asociación #<?= (int) ($r['asociacion_id'] ?? 0) ?></p>
    <p style="margin:0.25rem 0 0;font-size:0.86rem">Vista de solo lectura. <strong>Actualizar deuda</strong> vuelve a leer <code>atletas</code> (inscripción, afiliación, carnet, traspaso, anualidad) para este torneo y asociación, aplica tarifas de <code>costos</code> y actualiza el estado de cuenta.</p>
    <?php if (isset($_GET['msg']) && $_GET['msg'] === 'deuda_actualizada'): ?>
        <p class="fvd-mod-msg">Deuda actualizada correctamente desde atletas.</p>
    <?php endif; ?>
</div>

<div class="fvd-deuda-detalle-wrap">
    <div>
        <table>
            <thead>
                <tr>
                    <th>Concepto</th>
                    <th>Cantidad</th>
                    <th class="fvd-deuda-tarifa">Tarifa (tabla costos)</th>
                    <th>Monto</th>
                    <th class="fvd-deuda-row-enlace" scope="col">Registros</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($renglones as [$fkQty, $fkMonto, $costKey, $labConcepto, $labQty, $labMonto]): ?>
                    <tr>
                        <td><?= htmlspecialchars($labConcepto, ENT_QUOTES, 'UTF-8') ?></td>
                        <td>
                            <label class="fvd-atl-muted" style="display:none" for="<?= htmlspecialchars($fkQty, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($labQty, ENT_QUOTES, 'UTF-8') ?></label>
                            <input class="fvd-input fvd-deuda-qty-input" type="number" step="1" min="0" id="<?= htmlspecialchars($fkQty, ENT_QUOTES, 'UTF-8') ?>" value="<?= htmlspecialchars((string) ($r[$fkQty] ?? '0'), ENT_QUOTES, 'UTF-8') ?>" readonly disabled>
                        </td>
                        <td class="fvd-deuda-tarifa"><?= $fvdTarifa($c, $costKey) ?></td>
                        <td>
                            <label class="fvd-atl-muted" style="display:none" for="<?= htmlspecialchars($fkMonto, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($labMonto, ENT_QUOTES, 'UTF-8') ?></label>
                            <input class="fvd-input fvd-deuda-monto-input" type="number" step="0.01" id="<?= htmlspecialchars($fkMonto, ENT_QUOTES, 'UTF-8') ?>" value="<?= htmlspecialchars((string) ($r[$fkMonto] ?? '0'), ENT_QUOTES, 'UTF-8') ?>" readonly disabled>
                        </td>
                        <td class="fvd-deuda-row-enlace">
                            <a class="fvd-deuda-btn fvd-deuda-btn--sm fvd-deuda-btn--registros" href="<?= htmlspecialchars($reporteConceptosUrl . '&concepto=' . rawurlencode($costKey), ENT_QUOTES, 'UTF-8') ?>">Ver registros</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <tr class="fvd-deuda-monto-total-row">
                    <td colspan="2">Monto total</td>
                    <td class="fvd-deuda-tarifa">—</td>
                    <td>
                        <label class="fvd-atl-muted" style="display:none" for="monto_total">Monto total</label>
                        <input class="fvd-input fvd-deuda-monto-input" type="number" step="0.01" id="monto_total" value="<?= htmlspecialchars((string) ($r['monto_total'] ?? '0'), ENT_QUOTES, 'UTF-8') ?>" readonly disabled>
                    </td>
                    <td class="fvd-deuda-row-enlace"></td>
                </tr>
                <tr class="fvd-deuda-monto-total-row">
                    <td colspan="2">Monto total contable</td>
                    <td class="fvd-deuda-tarifa">EUR</td>
                    <td>
                        <label class="fvd-atl-muted" style="display:none" for="monto_total_eur">Monto total EUR</label>
                        <input class="fvd-input fvd-deuda-monto-input" type="number" step="0.01" min="0" id="monto_total_eur" value="<?= htmlspecialchars((string) ($r['monto_total_eur'] ?? '0'), ENT_QUOTES, 'UTF-8') ?>" readonly disabled>
                    </td>
                    <td class="fvd-deuda-row-enlace"></td>
                </tr>
            </tbody>
        </table>
        <p style="margin:0.5rem 0 0;font-size:0.82rem;color:#334155;font-weight:700">
            El estado de cuenta usa <strong>EUR</strong> (monto total contable). Los Bs se conservan para conciliación y arqueo.
        </p>

        <?php if ($r !== []): ?>
            <div class="fvd-deuda-pagos-bloque">
                <h2>Pagos y recibos (detalle)</h2>
                <p style="margin:0 0 0.65rem;font-size:0.82rem;color:#334155;font-weight:600">
                    Recibos registrados para esta asociación en el torneo. Subtotal según suma de recibos.
                </p>
                <?php if ($fvdPagosRecibos === []): ?>
                    <p style="margin:0;font-size:0.88rem">Sin pagos registrados para este torneo y asociación.</p>
                <?php else: ?>
                    <table class="fvd-deuda-pagos-tabla">
                        <thead>
                            <tr>
                                <th scope="col">Recibo</th>
                                <th scope="col">Fecha</th>
                                <th scope="col">Nº</th>
                                <th scope="col">Tipo</th>
                                <th scope="col" class="fvd-num">Pago</th>
                                <th scope="col" class="fvd-num">Tasa BCV</th>
                                <th scope="col" class="fvd-num">Bs (ref.)</th>
                                <th scope="col"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($fvdPagosRecibos as $p): ?>
                                <?php
                                $tp = (string) ($p['tipo_pago'] ?? '');
                                $tipoLbl = $fvdTiposPagoOpciones[$tp] ?? $tp;
                                ?>
                                <tr>
                                    <td><?= (int) ($p['id'] ?? 0) ?></td>
                                    <td><?= htmlspecialchars((string) ($p['fecha'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= (int) ($p['secuencia'] ?? 0) ?></td>
                                    <td><?= htmlspecialchars($tipoLbl, ENT_QUOTES, 'UTF-8') ?></td>
                                    <td class="fvd-num"><?= htmlspecialchars($fvdFmtMonto($p['monto_dolares'] ?? null, 2), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td class="fvd-num"><?= htmlspecialchars($fvdFmtMonto($p['tasa_cambio'] ?? null, 2), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td class="fvd-num"><?= htmlspecialchars($fvdFmtMonto($p['monto_total'] ?? null, 2), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td>
                                        <?php if ($fvdUrlRelacionPago !== ''): ?>
                                            <a class="fvd-deuda-btn fvd-deuda-btn--sm fvd-deuda-btn--registros" href="<?= htmlspecialchars($fvdUrlRelacionPago . '?action=form&id=' . (int) ($p['id'] ?? 0), ENT_QUOTES, 'UTF-8') ?>">Ver</a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <tr class="fvd-deuda-pagos-subtotal">
                                <td colspan="4">Subtotal asociación</td>
                                <td class="fvd-num"><?= htmlspecialchars($fvdFmtMonto($fvdPagosSubtotalEur, 2), ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="fvd-num">—</td>
                                <td class="fvd-num"><?= htmlspecialchars($fvdFmtMonto($fvdPagosSubtotalBs, 2), ENT_QUOTES, 'UTF-8') ?></td>
                                <td></td>
                            </tr>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="fvd-deuda-detalle-actions fvd-mod-actions">
            <?php if ($fvdPuedeActualizarDeuda): ?>
                <form method="post" action="<?= htmlspecialchars($selfUrl . '?action=form&tid=' . (int) $r['torneo_id'] . '&aid=' . (int) $r['asociacion_id'], ENT_QUOTES, 'UTF-8') ?>" style="display:inline-flex">
                    <input type="hidden" name="_action" value="actualizar_deuda">
                    <input type="hidden" name="torneo_id" value="<?= (int) ($r['torneo_id'] ?? 0) ?>">
                    <input type="hidden" name="asociacion_id" value="<?= (int) ($r['asociacion_id'] ?? 0) ?>">
                    <button type="submit">Actualizar deuda</button>
                </form>
            <?php else: ?>
                <button type="button" disabled style="opacity:0.6;cursor:not-allowed">Actualizar deuda (torneo finalizado)</button>
            <?php endif; ?>
            <a class="fvd-deuda-btn fvd-deuda-btn--conceptos" href="<?= htmlspecialchars($reporteConceptosUrl, ENT_QUOTES, 'UTF-8') ?>">Todos los conceptos</a>
            <a class="fvd-deuda-btn fvd-deuda-btn--volver" href="<?= htmlspecialchars($selfUrl, ENT_QUOTES, 'UTF-8') ?>">Volver</a>
        </div>
    </div>
</div>
