<?php
/** @var ?array $row */
/** @var list<array<string,mixed>> $asociaciones */
/** @var ?array $fvdTorneoRecibo array{torneo_id:int,nombre:string}|null */
/** @var ?array $fvdDeudaInicial */
/** @var ?array $fvd_form_repost */
/** @var string $selfUrl */

$rowDb = $row ?? null;
$fvdDeudaInicial = is_array($fvdDeudaInicial ?? null) ? $fvdDeudaInicial : [];
$fvd_form_repost = $fvd_form_repost ?? null;
$fvd_error = $fvd_error ?? '';

$r = $rowDb ?? [];
if ($fvd_form_repost !== null && $fvd_form_repost !== []) {
    $r = array_merge($r, $fvd_form_repost);
    if (isset($fvd_form_repost['monto_bs'])) {
        $r['monto_total'] = $fvd_form_repost['monto_bs'];
    }
    if (isset($fvd_form_repost['monto_eur'])) {
        $r['monto_dolares'] = $fvd_form_repost['monto_eur'];
    }
}
/* Alta nueva puede traer solo asociacion_id (sin id); solo consulta si ya existe recibo en BD */
$isEdit = $rowDb !== null && isset($rowDb['id']) && (int) $rowDb['id'] > 0;
$fvdSoloConsulta = $isEdit;

$legacyTipo = ['efectivo' => 'efectivo_bs', 'transferencia' => 'transferencia_bs', 'pago_movil' => 'pago_movil_bs'];
$tipoSel = (string) ($r['tipo_pago'] ?? 'efectivo_bs');
if (!isset(RelacionPagoController::TIPOS_PAGO_OPCIONES[$tipoSel]) && isset($legacyTipo[$tipoSel])) {
    $tipoSel = $legacyTipo[$tipoSel];
}
if (!isset(RelacionPagoController::TIPOS_PAGO_OPCIONES[$tipoSel])) {
    $tipoSel = 'efectivo_bs';
}

$tasaIni = isset($r['tasa_cambio']) && $r['tasa_cambio'] !== '' ? (float) $r['tasa_cambio'] : 0.0;
$modoDeuda = (string) ($fvdDeudaInicial['modo'] ?? 'eur');
$tieneTotalEurDeuda = (bool) ($fvdDeudaInicial['tiene_total_eur_deuda'] ?? false);
$pendBs = (float) ($fvdDeudaInicial['pendiente_bs'] ?? 0);
$deudaTotal = (float) ($fvdDeudaInicial['monto_total_deuda'] ?? 0);
$pagadoBs = (float) ($fvdDeudaInicial['pagado_bs'] ?? 0);
$deudaTotalEur = (float) ($fvdDeudaInicial['monto_total_deuda_eur'] ?? 0);
$pagadoEur = (float) ($fvdDeudaInicial['pagado_eur'] ?? 0);
$pendEurRaw = $fvdDeudaInicial['pendiente_eur'] ?? null;
$pendEur = is_numeric($pendEurRaw) ? (float) $pendEurRaw : null;

if (!$isEdit && $fvd_form_repost === null) {
    if ($modoDeuda === 'eur') {
        $iniEur = ($pendEur !== null && $pendEur > 0) ? $pendEur : '';
        $iniBs = ($tasaIni > 0 && $pendEur !== null && $pendEur > 0) ? round($pendEur * $tasaIni, 2) : '';
    } else {
        $iniBs = $pendBs > 0 ? $pendBs : '';
        $iniEur = ($tasaIni > 0 && $pendBs > 0) ? round($pendBs / $tasaIni, 6) : '';
    }
} else {
    $iniBs = isset($r['monto_total']) && $r['monto_total'] !== '' && $r['monto_total'] !== null ? (float) $r['monto_total'] : '';
    $iniEur = isset($r['monto_dolares']) && $r['monto_dolares'] !== '' && $r['monto_dolares'] !== null ? (float) $r['monto_dolares'] : '';
}

$fvdTorneoRecibo = $fvdTorneoRecibo ?? null;
$pfSinTorneoActivo = !$isEdit && $fvdTorneoRecibo === null;
$fvdTorneoIdForJs = (int) ($fvdTorneoRecibo['torneo_id'] ?? 0);
$fvdDeudaResumenUrl = $selfUrl . '?action=deuda_resumen&fmt=json&tid=' . $fvdTorneoIdForJs . '&aid=';

/** Página oficial del BCV: tipo de cambio oficial (incluye euro). */
$fvdBcvTasasUrl = 'https://www.bcv.org.ve/seccionportal/tipo-de-cambio-oficial-del-bcv';
$fvdBcvEuroJsonUrl = $selfUrl . '?action=bcv_euro&fmt=json';
?>

<style>
    .fvd-pf-form-center {
        width: 100%;
        max-width: 100%;
        display: flex;
        flex-direction: column;
        align-items: center;
        box-sizing: border-box;
        padding: 0 0.75rem 1.5rem;
    }
    .fvd-pf-form-center .fvd-deuda-detalle-titulo {
        width: 100%;
        max-width: 960px;
        text-align: center;
        margin-bottom: 0.5rem;
    }
    .fvd-deuda-detalle-wrap {
        max-width: 960px;
        width: 100%;
        border: 3px solid #eab308;
        border-radius: 8px;
        padding: 1rem 1.25rem;
        background: #B3DBF5;
        box-sizing: border-box;
        color: #000;
        font-weight: 700;
    }
    .fvd-pf-deuda-panel {
        margin: 0 0 14px;
        padding: 0.65rem 0.75rem;
        background: rgba(255, 255, 255, 0.65);
        border-radius: 6px;
        border: 1px solid #94a3b8;
        font-size: 0.95rem;
    }
    .fvd-pf-deuda-panel__row {
        display: flex;
        flex-wrap: nowrap;
        align-items: baseline;
        gap: 0.65rem 1rem;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }
    .fvd-pf-deuda-panel__title {
        flex-shrink: 0;
        white-space: nowrap;
    }
    .fvd-pf-deuda-panel__sep {
        flex-shrink: 0;
        color: #94a3b8;
        font-weight: 600;
        user-select: none;
    }
    .fvd-pf-deuda-panel__metric {
        flex-shrink: 0;
        white-space: nowrap;
        font-variant-numeric: tabular-nums;
    }
    .fvd-pf-deuda-panel__lbl {
        color: #334155;
        font-weight: 700;
    }
    .fvd-pf-deuda-panel__val {
        font-weight: 700;
    }
    .fvd-pf-deuda-panel__hint {
        margin: 0.5rem 0 0;
        font-size: 0.85rem;
        font-weight: 600;
        color: #475569;
    }
    .fvd-pf-moneda-derivada {
        margin: 0.25rem 0 0;
        font-size: 0.875rem;
        color: #1e293b;
    }
    .fvd-pf-secuencia-auto {
        margin: 0.35rem 0 0;
        font-size: 0.9rem;
        color: #334155;
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
    .fvd-deuda-btn--registros {
        background: #1d4ed8;
        color: #fff;
        border-color: #1e3a8a;
    }
    .fvd-deuda-btn--registros:hover {
        background: #1e40af;
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
        justify-content: center;
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
    .fvd-deuda-detalle-wrap label {
        color: #000;
        font-weight: 700;
    }
    .fvd-deuda-detalle-wrap .fvd-input,
    .fvd-deuda-detalle-wrap select.fvd-input,
    .fvd-deuda-detalle-wrap textarea.fvd-input {
        color: #000;
        font-weight: 700;
    }
    .fvd-deuda-detalle-wrap .fvd-pf-bcv-fallback {
        margin: 0 0 12px;
        text-align: center;
    }
    .fvd-deuda-detalle-wrap .fvd-pf-bcv-line {
        margin: 0 0 6px;
        font-size: 0.9rem;
    }
    .fvd-deuda-detalle-titulo h1 {
        color: #000;
        font-weight: 700;
    }
    .fvd-deuda-detalle-wrap button.fvd-deuda-btn {
        font-family: inherit;
    }
    #fvd-bcv-euro-status {
        color: #000;
        font-weight: 700;
    }
    .fvd-pf-torneo-info {
        margin: 0 0 12px;
        padding: 0.5rem 0.65rem;
        background: rgba(255, 255, 255, 0.55);
        border-radius: 6px;
        border: 1px solid #e5e7eb;
        color: #000;
        font-weight: 700;
    }
    .fvd-pf-torneo-info .fvd-pf-torneo-id {
        font-weight: 700;
        color: #374151;
    }
</style>

<div class="fvd-pf-form-center">
    <?php
    $fvdRpPanelUrl = isset($fvdRpPanelUrl) ? (string) $fvdRpPanelUrl : '';
    $fvdReporteOrigenUrl = isset($fvdReporteOrigenUrl) ? $fvdReporteOrigenUrl : null;
    $fvdRpRetornoRef = isset($fvdRpRetornoRef) ? (string) $fvdRpRetornoRef : '';
    $fvdRpRetornoRid = isset($fvdRpRetornoRid) ? (int) $fvdRpRetornoRid : 0;
    $aidVolver = (int) ($r['asociacion_id'] ?? 0);
    if ($aidVolver <= 0 && isset($_GET['asociacion_id'])) {
        $aidVolver = (int) $_GET['asociacion_id'];
    }
    $fvdVolverUrl = $selfUrl;
    if ($fvdReporteOrigenUrl !== null && $fvdReporteOrigenUrl !== '') {
        $fvdVolverUrl = $fvdReporteOrigenUrl;
    } else {
        $qv = [];
        if ($aidVolver > 0) {
            $qv['aid'] = $aidVolver;
        }
        if ($fvdRpRetornoRef === 'rep_asoc' && $fvdRpRetornoRid > 0) {
            $qv['ref'] = 'rep_asoc';
            $qv['rid'] = $fvdRpRetornoRid;
        }
        if ($qv !== []) {
            $fvdVolverUrl = $selfUrl . '?' . http_build_query($qv);
        }
    }
    $fvdVolverUrl = fvd_return_append_to_url($fvdVolverUrl);
    ?>
    <div class="no-print" style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin:0 0 1rem">
        <?php if ($fvdReporteOrigenUrl !== null && $fvdReporteOrigenUrl !== ''): ?>
            <a class="fvd-input" style="width:auto;padding:6px 12px;text-decoration:none;display:inline-flex;align-items:center;box-sizing:border-box;font-size:.8rem" href="<?= htmlspecialchars($fvdReporteOrigenUrl, ENT_QUOTES, 'UTF-8') ?>">← Reporte financiero (origen)</a>
        <?php endif; ?>
        <?php if ($fvdRpPanelUrl !== ''): ?>
            <a class="fvd-input" style="width:auto;padding:6px 12px;text-decoration:none;display:inline-flex;align-items:center;box-sizing:border-box;font-size:.8rem" href="<?= htmlspecialchars($fvdRpPanelUrl, ENT_QUOTES, 'UTF-8') ?>">← Panel general</a>
        <?php endif; ?>
    </div>
    <div class="fvd-deuda-detalle-titulo">
        <h1><?= $fvdSoloConsulta ? 'Consulta de pago' : 'Registrar pago' ?></h1>
        <?php if ($fvdSoloConsulta): ?>
            <p style="margin:0.35rem 0 0;font-size:0.88rem;font-weight:600;color:#334155">Solo consulta. Para un movimiento nuevo use <strong>Registrar pago</strong> en el listado.</p>
        <?php endif; ?>
    </div>

    <div class="fvd-deuda-detalle-wrap">
        <?php if (isset($_GET['msg']) && $_GET['msg'] === 'no_edicion'): ?>
            <p class="fvd-mod-msg" role="status">No está permitido editar recibos; solo altas nuevas.</p>
        <?php endif; ?>
        <?php if ($fvd_error !== ''): ?>
            <p class="fvd-mod-msg" role="alert"><?= htmlspecialchars($fvd_error, ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>

        <p id="fvd-bcv-tasas-fallback" class="fvd-mod-msg fvd-pf-bcv-fallback" style="display:none">
            El navegador puede haber bloqueado la ventana emergente con las tasas oficiales del BCV.
            <a href="<?= htmlspecialchars($fvdBcvTasasUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer">Abrir tipo de cambio oficial del BCV (euro y otras monedas)</a>
        </p>

        <form method="post" action="<?= htmlspecialchars(fvd_return_preserve_query_params($selfUrl . '?action=form' . ($isEdit ? '&id=' . (int) ($r['id'] ?? 0) : '')), ENT_QUOTES, 'UTF-8') ?>" id="fvd-form-relacion-pago"<?= $fvdSoloConsulta ? ' onsubmit="return false"' : '' ?>>
            <?php if (!$fvdSoloConsulta): ?>
                <input type="hidden" name="_action" value="save">
            <?php endif; ?>
            <?php if ($fvdRpRetornoRef === 'rep_asoc' && $fvdRpRetornoRid > 0): ?>
                <input type="hidden" name="_retorno_ref" value="rep_asoc">
                <input type="hidden" name="_retorno_rid" value="<?= (int) $fvdRpRetornoRid ?>">
            <?php endif; ?>
            <?php if (isset($_GET['ret']) && is_string($_GET['ret']) && fvd_return_sanitize($_GET['ret']) !== null): ?>
                <input type="hidden" name="ret" value="<?= htmlspecialchars($_GET['ret'], ENT_QUOTES, 'UTF-8') ?>">
            <?php elseif (isset($_GET['return']) && is_string($_GET['return']) && fvd_return_sanitize($_GET['return']) !== null): ?>
                <input type="hidden" name="return" value="<?= htmlspecialchars($_GET['return'], ENT_QUOTES, 'UTF-8') ?>">
            <?php endif; ?>

            <?php if ($pfSinTorneoActivo): ?>
                <p class="fvd-mod-msg fvd-pf-torneo-info">No hay torneo activo para este recibo. Como delegado, entre al panel del torneo desde la invitación; como administrador, marque al menos un torneo con estatus <strong>en proceso (1)</strong>.</p>
            <?php elseif ($fvdTorneoRecibo !== null): ?>
                <p class="fvd-pf-torneo-info" role="status">
                    Torneo del recibo: <strong><?= htmlspecialchars($fvdTorneoRecibo['nombre'], ENT_QUOTES, 'UTF-8') ?></strong>
                    <span class="fvd-pf-torneo-id">(id <?= (int) $fvdTorneoRecibo['torneo_id'] ?>)</span>
                    <?php if (!$isEdit): ?>
                        <span class="fvd-pf-torneo-id"> — definido por el torneo activo</span>
                    <?php endif; ?>
                </p>
            <?php endif; ?>

            <?php if ($fvdTorneoRecibo !== null && !$pfSinTorneoActivo): ?>
                <div class="fvd-pf-deuda-panel" id="fvd-deuda-panel" aria-live="polite">
                    <div class="fvd-pf-deuda-panel__row">
                        <strong class="fvd-pf-deuda-panel__title" id="fvd-deuda-panel-title"><?= $modoDeuda === 'eur' ? 'Estado de cuenta (EUR)' : 'Deuda y pagos (Bs)' ?></strong>
                        <span class="fvd-pf-deuda-panel__sep" aria-hidden="true">|</span>
                        <span class="fvd-pf-deuda-panel__metric"><span class="fvd-pf-deuda-panel__lbl">Deuda total</span> <span class="fvd-pf-deuda-panel__val" id="fvd-deuda-total"><?php
                            if ($modoDeuda === 'eur') {
                                echo $tieneTotalEurDeuda ? htmlspecialchars(number_format($deudaTotalEur, 2, ',', '.'), ENT_QUOTES, 'UTF-8') : '—';
                            } else {
                                echo htmlspecialchars(number_format($deudaTotal, 2, ',', '.'), ENT_QUOTES, 'UTF-8');
                            }
                        ?></span></span>
                        <span class="fvd-pf-deuda-panel__sep" aria-hidden="true">|</span>
                        <span class="fvd-pf-deuda-panel__metric"><span class="fvd-pf-deuda-panel__lbl">Pagado acumulado</span> <span class="fvd-pf-deuda-panel__val" id="fvd-deuda-pagado"><?= $modoDeuda === 'eur' ? htmlspecialchars(number_format($pagadoEur, 2, ',', '.'), ENT_QUOTES, 'UTF-8') : htmlspecialchars(number_format($pagadoBs, 2, ',', '.'), ENT_QUOTES, 'UTF-8') ?></span></span>
                        <span class="fvd-pf-deuda-panel__sep" aria-hidden="true">|</span>
                        <span class="fvd-pf-deuda-panel__metric"><span class="fvd-pf-deuda-panel__lbl">Pendiente</span> <span class="fvd-pf-deuda-panel__val" id="fvd-deuda-pendiente"><?php
                            if ($modoDeuda === 'eur') {
                                echo $pendEur !== null ? htmlspecialchars(number_format($pendEur, 2, ',', '.'), ENT_QUOTES, 'UTF-8') : '—';
                            } else {
                                echo htmlspecialchars(number_format($pendBs, 2, ',', '.'), ENT_QUOTES, 'UTF-8');
                            }
                        ?></span></span>
                    </div>
                    <?php if ($modoDeuda === 'eur'): ?>
                        <p id="fvd-deuda-refbs" class="fvd-pf-deuda-panel__hint" style="margin:0.35rem 0 0;font-size:0.82rem;font-weight:600;color:#64748b;<?= ($deudaTotal > 0 || $pagadoBs > 0) ? '' : 'display:none;' ?>">
                            Referencia arqueo (no suma al estado de cuenta en EUR): deuda Bs <?= htmlspecialchars(number_format($deudaTotal, 2, ',', '.'), ENT_QUOTES, 'UTF-8') ?> · pagado Bs <?= htmlspecialchars(number_format($pagadoBs, 2, ',', '.'), ENT_QUOTES, 'UTF-8') ?>
                        </p>
                    <?php endif; ?>
                    <p class="fvd-pf-deuda-panel__hint" id="fvd-deuda-panel-hint"><?= $modoDeuda === 'eur'
                        ? ($tieneTotalEurDeuda
                            ? 'El saldo es en euros. Los bolívares del recibo solo sirven para conciliación y arqueo según la tasa del día.'
                            : 'Indique la deuda total en EUR en el módulo de deudas (campo monto EUR) para calcular el pendiente. El pagado mostrado es la suma de EUR registrados en los recibos.')
                        : 'Deuda histórica en Bs. Puede abonar menos que el pendiente (parcial). El equivalente en EUR con tasa BCV es referencia si aplica.' ?></p>
                </div>
            <?php endif; ?>

            <div class="fvd-pf-grid"<?= $fvdSoloConsulta ? ' inert' : '' ?>>
                <div>
                    <label for="asociacion_id">Asociación</label>
                    <select class="fvd-input" id="asociacion_id" name="asociacion_id" required style="max-width:100%">
                        <?php foreach ($asociaciones as $a): ?>
                            <option value="<?= (int) $a['id'] ?>" <?= ((int) ($r['asociacion_id'] ?? 0) === (int) $a['id']) ? 'selected' : '' ?>><?= htmlspecialchars((string) $a['nombre'], ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="secuencia">Secuencia</label>
                    <?php if ($isEdit): ?>
                        <input class="fvd-input" type="number" id="secuencia" name="secuencia" value="<?= htmlspecialchars((string) ($r['secuencia'] ?? '1'), ENT_QUOTES, 'UTF-8') ?>">
                    <?php else: ?>
                        <p class="fvd-pf-secuencia-auto" id="fvd-secuencia-hint">Se asigna automáticamente al guardar.</p>
                    <?php endif; ?>
                </div>
                <div>
                    <label for="fecha">Fecha del movimiento</label>
                    <input class="fvd-input" type="date" id="fecha" name="fecha" value="<?= htmlspecialchars(substr((string) ($r['fecha'] ?? date('Y-m-d')), 0, 10), ENT_QUOTES, 'UTF-8') ?>">
                    <p class="fvd-pf-moneda-derivada" style="margin-top:0.25rem">Fecha a la que aplica el tipo de cambio y el registro para auditoría.</p>
                </div>
                <div>
                    <label for="tasa_cambio">Tasa BCV (Bs por 1 EUR)</label>
                    <p class="fvd-pf-bcv-line">
                        <button type="button" class="fvd-deuda-btn fvd-deuda-btn--registros" style="padding:0.28rem 0.55rem;font-size:0.8125rem" id="fvd-bcv-euro-btn" data-bcv-url="<?= htmlspecialchars($fvdBcvTasasUrl, ENT_QUOTES, 'UTF-8') ?>" data-json-url="<?= htmlspecialchars($fvdBcvEuroJsonUrl, ENT_QUOTES, 'UTF-8') ?>">BCV — cargar euro oficial</button>
                        <span id="fvd-bcv-euro-status" style="margin-left:8px;font-size:0.85rem" aria-live="polite"></span>
                    </p>
                    <input class="fvd-input" type="number" step="0.0001" id="tasa_cambio" name="tasa_cambio" value="<?= htmlspecialchars((string) ($r['tasa_cambio'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                    <p class="fvd-pf-moneda-derivada" style="margin-top:0.25rem">Se guarda con el recibo para trazabilidad del cambio del día y arqueo.</p>
                </div>
                <div>
                    <label for="tipo_pago">Tipo de pago</label>
                    <select class="fvd-input" id="tipo_pago" name="tipo_pago" required style="max-width:100%">
                        <?php foreach (RelacionPagoController::TIPOS_PAGO_OPCIONES as $val => $etiq): ?>
                            <option value="<?= htmlspecialchars($val, ENT_QUOTES, 'UTF-8') ?>" <?= $tipoSel === $val ? 'selected' : '' ?>><?= htmlspecialchars($etiq, ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="fvd-pf-moneda-derivada" id="fvd-moneda-derivada"></p>
                </div>
                <div>
                    <label for="monto_eur">Importe en EUR (contable)</label>
                    <input class="fvd-input" type="number" step="0.000001" id="monto_eur" name="monto_eur" value="<?= $iniEur !== '' ? htmlspecialchars((string) $iniEur, ENT_QUOTES, 'UTF-8') : '' ?>">
                    <p class="fvd-pf-moneda-derivada" style="margin-top:0.25rem">Es el monto que descuenta la deuda en euros.</p>
                </div>
                <div>
                    <label for="monto_bs">Equivalente en Bs (referencial)</label>
                    <input class="fvd-input" type="number" step="0.01" id="monto_bs" name="monto_bs" value="<?= $iniBs !== '' ? htmlspecialchars((string) $iniBs, ENT_QUOTES, 'UTF-8') : '' ?>">
                    <p class="fvd-pf-moneda-derivada" style="margin-top:0.25rem">Calculado con la tasa del recibo; sirve para verificación y arqueo de caja cuando el pago se relaciona con bolívares.</p>
                </div>
                <div>
                    <label for="referencia">Referencia</label>
                    <input class="fvd-input" id="referencia" name="referencia" value="<?= htmlspecialchars((string) ($r['referencia'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div>
                    <label for="banco">Banco</label>
                    <input class="fvd-input" id="banco" name="banco" value="<?= htmlspecialchars((string) ($r['banco'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div style="grid-column:1/-1">
                    <label for="observaciones">Observaciones</label>
                    <textarea class="fvd-input" id="observaciones" name="observaciones" rows="2" style="max-width:100%"><?= htmlspecialchars((string) ($r['observaciones'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                </div>
            </div>

            <div class="fvd-deuda-detalle-actions fvd-mod-actions">
                <?php if (!$fvdSoloConsulta): ?>
                    <button type="submit"<?= $pfSinTorneoActivo ? ' disabled' : '' ?>>Guardar</button>
                <?php endif; ?>
                <a class="fvd-deuda-btn fvd-deuda-btn--volver" href="<?= htmlspecialchars($fvdVolverUrl, ENT_QUOTES, 'UTF-8') ?>">Volver</a>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    var fvdSoloConsulta = <?= $fvdSoloConsulta ? 'true' : 'false' ?>;
    if (fvdSoloConsulta) {
        return;
    }
    var url = <?= json_encode($fvdBcvTasasUrl, JSON_UNESCAPED_SLASHES) ?>;
    function tryOpenBcvTasas() {
        var w = window.open(url, '_blank', 'noopener,noreferrer');
        var blocked = !w || (typeof w.closed !== 'undefined' && w.closed);
        setTimeout(function () {
            try {
                blocked = blocked || (w && w.closed);
            } catch (e) { /* cross-origin */ }
            var fb = document.getElementById('fvd-bcv-tasas-fallback');
            if (blocked && fb) {
                fb.style.display = 'block';
            }
        }, 500);
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', tryOpenBcvTasas);
    } else {
        tryOpenBcvTasas();
    }

    var fvdIsEdit = <?= $isEdit ? 'true' : 'false' ?>;
    var fvdTorneoId = <?= (int) $fvdTorneoIdForJs ?>;
    var fvdDeudaBase = <?= json_encode($fvdDeudaResumenUrl, JSON_UNESCAPED_SLASHES) ?>;
    var fvdModoDeuda = <?= json_encode($modoDeuda, JSON_UNESCAPED_UNICODE) ?>;

    function parseNum(el) {
        if (!el || el.value === '') {
            return NaN;
        }
        var s = String(el.value).replace(',', '.');
        var n = parseFloat(s);
        return n;
    }

    function getTasa() {
        var t = document.getElementById('tasa_cambio');
        var v = t ? parseNum(t) : NaN;
        return (typeof v === 'number' && !isNaN(v) && v > 0) ? v : 0;
    }

    var lastFx = fvdModoDeuda === 'eur' ? 'eur' : 'bs';

    function syncEurFromBs() {
        lastFx = 'bs';
        var t = getTasa();
        var bsEl = document.getElementById('monto_bs');
        var eurEl = document.getElementById('monto_eur');
        if (!bsEl || !eurEl || t <= 0) {
            return;
        }
        var bs = parseNum(bsEl);
        if (!isNaN(bs)) {
            eurEl.value = (bs / t).toFixed(2);
        }
    }

    function syncBsFromEur() {
        lastFx = 'eur';
        var t = getTasa();
        var bsEl = document.getElementById('monto_bs');
        var eurEl = document.getElementById('monto_eur');
        if (!bsEl || !eurEl || t <= 0) {
            return;
        }
        var eur = parseNum(eurEl);
        if (!isNaN(eur)) {
            bsEl.value = (eur * t).toFixed(2);
        }
    }

    function syncAfterTasa() {
        if (lastFx === 'eur') {
            syncBsFromEur();
        } else {
            syncEurFromBs();
        }
    }

    function formatEs(n, dec) {
        if (typeof n !== 'number' || isNaN(n)) {
            return '—';
        }
        return n.toLocaleString('es', { minimumFractionDigits: dec, maximumFractionDigits: dec });
    }

    function updateMonedaDerivada() {
        var sel = document.getElementById('tipo_pago');
        var out = document.getElementById('fvd-moneda-derivada');
        if (!sel || !out) {
            return;
        }
        var v = sel.value || '';
        var div = (v === 'efectivo_divisas' || v === 'transferencia_divisas');
        out.textContent = div
            ? 'Canal en divisas: el contable es el EUR; los Bs mostrados son referenciales (EUR × tasa BCV) para caja.'
            : 'Canal en Bs: el contable sigue siendo el EUR; los Bs son el equivalente referencial al día para verificación y arqueo.';
    }

    function applyDeudaResumen(data) {
        var tEl = document.getElementById('fvd-deuda-total');
        var pEl = document.getElementById('fvd-deuda-pagado');
        var penEl = document.getElementById('fvd-deuda-pendiente');
        var titleEl = document.getElementById('fvd-deuda-panel-title');
        var hintEl = document.getElementById('fvd-deuda-panel-hint');
        var refBsEl = document.getElementById('fvd-deuda-refbs');
        if (!data || !data.ok) {
            return;
        }
        fvdModoDeuda = data.modo || 'bs';
        if (data.modo === 'eur') {
            if (titleEl) {
                titleEl.textContent = 'Estado de cuenta (EUR)';
            }
            if (hintEl) {
                hintEl.textContent = data.tiene_total_eur_deuda
                    ? 'El saldo es en euros. Los bolívares del recibo solo sirven para conciliación y arqueo según la tasa del día.'
                    : 'Indique la deuda total en EUR en el módulo de deudas (campo monto EUR) para calcular el pendiente. El pagado mostrado es la suma de EUR registrados en los recibos.';
            }
            if (tEl) {
                tEl.textContent = data.tiene_total_eur_deuda ? formatEs(data.monto_total_deuda_eur, 2) : '—';
            }
            if (pEl) {
                pEl.textContent = formatEs(data.pagado_eur, 2);
            }
            var pendE = data.pendiente_eur;
            var pendNum = typeof pendE === 'number' && !isNaN(pendE) ? pendE : null;
            if (penEl) {
                penEl.textContent = pendNum !== null ? formatEs(pendNum, 2) : '—';
            }
            if (refBsEl) {
                var dBs = typeof data.monto_total_deuda === 'number' ? data.monto_total_deuda : 0;
                var pBs = typeof data.pagado_bs === 'number' ? data.pagado_bs : 0;
                if (dBs > 0 || pBs > 0) {
                    refBsEl.style.display = '';
                    refBsEl.textContent = 'Referencia arqueo (no suma al estado de cuenta en EUR): deuda Bs ' + formatEs(dBs, 2) + ' · pagado Bs ' + formatEs(pBs, 2);
                } else {
                    refBsEl.style.display = 'none';
                }
            }
            if (!fvdIsEdit) {
                var eurEl2 = document.getElementById('monto_eur');
                var bsEl2 = document.getElementById('monto_bs');
                var tasa2 = getTasa();
                if (eurEl2 && pendNum !== null && pendNum > 0) {
                    eurEl2.value = pendNum.toFixed(2);
                    lastFx = 'eur';
                    if (tasa2 > 0 && bsEl2) {
                        syncBsFromEur();
                    } else if (bsEl2) {
                        bsEl2.value = '';
                    }
                }
            }
            return;
        }
        if (titleEl) {
            titleEl.textContent = 'Deuda y pagos (Bs)';
        }
        if (hintEl) {
            hintEl.textContent = 'Deuda histórica en Bs. Puede abonar menos que el pendiente (pago parcial). Con tasa BCV, el equivalente en EUR se muestra como referencia.';
        }
        if (tEl) {
            tEl.textContent = formatEs(data.monto_total_deuda, 2);
        }
        if (pEl) {
            pEl.textContent = formatEs(data.pagado_bs, 2);
        }
        var pend = typeof data.pendiente_bs === 'number' ? data.pendiente_bs : 0;
        if (penEl) {
            penEl.textContent = formatEs(pend, 2);
        }
        if (!fvdIsEdit) {
            var bsEl = document.getElementById('monto_bs');
            var tasa = getTasa();
            if (bsEl && pend > 0) {
                bsEl.value = pend.toFixed(2);
                lastFx = 'bs';
                if (tasa > 0) {
                    syncEurFromBs();
                } else {
                    var eurEl = document.getElementById('monto_eur');
                    if (eurEl) {
                        eurEl.value = '';
                    }
                }
            }
        }
    }

    function fetchDeuda(aid) {
        if (fvdTorneoId <= 0 || !aid) {
            return;
        }
        fetch(fvdDeudaBase + encodeURIComponent(String(aid)), { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(applyDeudaResumen)
            .catch(function () { /* silencioso */ });
    }

    function wireFormFx() {
        var bsEl = document.getElementById('monto_bs');
        var eurEl = document.getElementById('monto_eur');
        var tasaEl = document.getElementById('tasa_cambio');
        var asoc = document.getElementById('asociacion_id');
        var tipo = document.getElementById('tipo_pago');
        if (bsEl) {
            bsEl.addEventListener('input', syncEurFromBs);
        }
        if (eurEl) {
            eurEl.addEventListener('input', syncBsFromEur);
        }
        if (tasaEl) {
            tasaEl.addEventListener('input', syncAfterTasa);
            tasaEl.addEventListener('change', syncAfterTasa);
        }
        if (asoc) {
            asoc.addEventListener('change', function () {
                fetchDeuda(parseInt(asoc.value, 10) || 0);
            });
        }
        if (tipo) {
            tipo.addEventListener('change', updateMonedaDerivada);
        }
        updateMonedaDerivada();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', wireFormFx);
    } else {
        wireFormFx();
    }

    function wireBcvEuroButton() {
        var btn = document.getElementById('fvd-bcv-euro-btn');
        var input = document.getElementById('tasa_cambio');
        var status = document.getElementById('fvd-bcv-euro-status');
        if (!btn || !input) {
            return;
        }
        var jsonUrl = btn.getAttribute('data-json-url');
        var bcvUrl = btn.getAttribute('data-bcv-url');
        btn.addEventListener('click', function () {
            if (bcvUrl) {
                window.open(bcvUrl, '_blank', 'noopener,noreferrer');
            }
            if (!jsonUrl) {
                return;
            }
            btn.disabled = true;
            if (status) {
                status.textContent = 'Consultando BCV…';
            }
            fetch(jsonUrl, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    var rate = data && data.rate;
                    if (typeof rate === 'string') {
                        rate = parseFloat(rate.replace(',', '.'));
                    }
                    if (data && data.ok && typeof rate === 'number' && !isNaN(rate)) {
                        input.value = String(rate);
                        if (status) {
                            status.textContent = data.raw ? ('EUR: ' + data.raw) : 'Listo.';
                        }
                        syncAfterTasa();
                    } else {
                        if (status) {
                            status.textContent = '';
                        }
                        alert((data && data.error) ? data.error : 'No se pudo obtener la tasa del euro.');
                    }
                })
                .catch(function () {
                    if (status) {
                        status.textContent = '';
                    }
                    alert('Error de red al consultar la tasa del BCV.');
                })
                .finally(function () {
                    btn.disabled = false;
                });
        });
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', wireBcvEuroButton);
    } else {
        wireBcvEuroButton();
    }
})();
</script>
