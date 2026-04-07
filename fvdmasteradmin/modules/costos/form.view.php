<?php
/** @var ?array $row */
/** @var string $selfUrl */
$r = $row ?? [];
$isEdit = $row !== null;
?>

<div class="fvd-costos-form-page">
    <p class="fvd-costos-federacion-name">Federación Venezolana de Dominó</p>
    <h1 class="fvd-costos-form-title"><?= $isEdit ? 'Editar tarifas' : 'Nueva tarifa' ?></h1>

    <?php if (!empty($fvd_error ?? '')): ?>
    <p class="fvd-mod-msg fvd-costos-form-page__err"><?= htmlspecialchars((string) $fvd_error, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>

    <form class="fvd-mod-form fvd-costos-form fvd-costos-form--framed" method="post" action="<?= htmlspecialchars($selfUrl . '?action=form' . ($isEdit ? '&id=' . (int) $r['id'] : ''), ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="_action" value="save">
        <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><?php endif; ?>

        <div class="fvd-costos-row">
            <label for="fecha">Fecha vigencia</label>
            <input class="fvd-input fvd-costos-input" type="date" id="fecha" name="fecha" required value="<?= htmlspecialchars(substr((string) ($r['fecha'] ?? ''), 0, 10), ENT_QUOTES, 'UTF-8') ?>">
        </div>

        <?php foreach (['afiliacion' => 'Afiliación', 'anualidad' => 'Anualidad', 'carnets' => 'Carnets', 'traspasos' => 'Traspasos', 'inscripciones' => 'Inscripciones'] as $f => $lab): ?>
        <div class="fvd-costos-row">
            <label for="<?= htmlspecialchars($f, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($lab, ENT_QUOTES, 'UTF-8') ?></label>
            <input class="fvd-input fvd-costos-input" type="number" step="0.01" id="<?= htmlspecialchars($f, ENT_QUOTES, 'UTF-8') ?>" name="<?= htmlspecialchars($f, ENT_QUOTES, 'UTF-8') ?>" value="<?= htmlspecialchars((string) ($r[$f] ?? '0'), ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <?php endforeach; ?>

        <div class="fvd-mod-actions fvd-costos-form__actions">
            <button type="submit">Guardar</button>
            <a href="<?= htmlspecialchars($selfUrl, ENT_QUOTES, 'UTF-8') ?>">Volver</a>
        </div>

        <p class="fvd-costos-form-footnote" role="note">Estos montos serán calculados en base al cambio oficial del euro en el BCV.</p>
    </form>
</div>
