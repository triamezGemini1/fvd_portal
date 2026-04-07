<?php
/** @var ?array $row */
/** @var list<array<string,mixed>> $asociaciones */
/** @var list<array<string,mixed>> $torneos */
/** @var string $selfUrl */
$r = $row ?? [];
$isEdit = $row !== null;
?>

<h1><?= $isEdit ? 'Editar pago' : 'Registrar pago' ?></h1>

<form method="post" action="<?= htmlspecialchars($selfUrl . '?action=form' . ($isEdit ? '&id=' . (int) $r['id'] : ''), ENT_QUOTES, 'UTF-8') ?>">
    <input type="hidden" name="_action" value="save">
    <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><?php endif; ?>

    <div class="fvd-pf-grid">
        <div>
            <label for="asociacion_id">Asociación</label>
            <select class="fvd-input" id="asociacion_id" name="asociacion_id" required style="max-width:100%">
                <?php foreach ($asociaciones as $a): ?>
                    <option value="<?= (int) $a['id'] ?>" <?= ((int) ($r['asociacion_id'] ?? 0) === (int) $a['id']) ? 'selected' : '' ?>><?= htmlspecialchars((string) $a['nombre'], ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="torneo_id">Torneo</label>
            <select class="fvd-input" id="torneo_id" name="torneo_id" style="max-width:100%">
                <option value="">—</option>
                <?php foreach ($torneos as $t): ?>
                    <option value="<?= (int) $t['torneo'] ?>" <?= ((int) ($r['torneo_id'] ?? 0) === (int) $t['torneo']) ? 'selected' : '' ?>><?= htmlspecialchars((string) $t['nombre'], ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="secuencia">Secuencia</label>
            <input class="fvd-input" type="number" id="secuencia" name="secuencia" value="<?= htmlspecialchars((string) ($r['secuencia'] ?? '1'), ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div>
            <label for="fecha">Fecha</label>
            <input class="fvd-input" type="date" id="fecha" name="fecha" value="<?= htmlspecialchars(substr((string) ($r['fecha'] ?? date('Y-m-d')), 0, 10), ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div>
            <label for="tasa_cambio">Tasa cambio</label>
            <input class="fvd-input" type="number" step="0.0001" id="tasa_cambio" name="tasa_cambio" value="<?= htmlspecialchars((string) ($r['tasa_cambio'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div>
            <label for="tipo_pago">Tipo pago</label>
            <input class="fvd-input" id="tipo_pago" name="tipo_pago" value="<?= htmlspecialchars((string) ($r['tipo_pago'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div>
            <label for="moneda">Moneda</label>
            <select class="fvd-input" id="moneda" name="moneda" style="max-width:12rem">
                <option value="Bs" <?= (($r['moneda'] ?? '') === 'Bs') ? 'selected' : '' ?>>Bs</option>
                <option value="divisas" <?= (($r['moneda'] ?? '') === 'divisas') ? 'selected' : '' ?>>Divisas</option>
            </select>
        </div>
        <div>
            <label for="monto_total">Monto total</label>
            <input class="fvd-input" type="number" step="0.01" id="monto_total" name="monto_total" value="<?= htmlspecialchars((string) ($r['monto_total'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div>
            <label for="monto_dolares">Monto USD</label>
            <input class="fvd-input" type="number" step="0.01" id="monto_dolares" name="monto_dolares" value="<?= htmlspecialchars((string) ($r['monto_dolares'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
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

    <div class="fvd-mod-actions">
        <button type="submit">Guardar</button>
        <a href="<?= htmlspecialchars($selfUrl, ENT_QUOTES, 'UTF-8') ?>">Volver</a>
    </div>
</form>
