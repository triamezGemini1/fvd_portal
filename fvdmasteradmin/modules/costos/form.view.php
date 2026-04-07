<?php
/** @var ?array $row */
/** @var string $selfUrl */
$r = $row ?? [];
$isEdit = $row !== null;
?>

<h1><?= $isEdit ? 'Editar tarifas' : 'Nueva tarifa' ?></h1>

<form class="fvd-mod-form" method="post" action="<?= htmlspecialchars($selfUrl . '?action=form' . ($isEdit ? '&id=' . (int) $r['id'] : ''), ENT_QUOTES, 'UTF-8') ?>">
    <input type="hidden" name="_action" value="save">
    <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><?php endif; ?>

    <label for="fecha">Fecha vigencia</label>
    <input class="fvd-input" type="date" id="fecha" name="fecha" required value="<?= htmlspecialchars(substr((string) ($r['fecha'] ?? ''), 0, 10), ENT_QUOTES, 'UTF-8') ?>">

    <?php foreach (['afiliacion' => 'Afiliación', 'anualidad' => 'Anualidad', 'carnets' => 'Carnets', 'traspasos' => 'Traspasos', 'inscripciones' => 'Inscripciones'] as $f => $lab): ?>
        <label for="<?= $f ?>"><?= htmlspecialchars($lab, ENT_QUOTES, 'UTF-8') ?></label>
        <input class="fvd-input" type="number" step="0.01" id="<?= $f ?>" name="<?= $f ?>" value="<?= htmlspecialchars((string) ($r[$f] ?? '0'), ENT_QUOTES, 'UTF-8') ?>">
    <?php endforeach; ?>

    <div class="fvd-mod-actions">
        <button type="submit">Guardar</button>
        <a href="<?= htmlspecialchars($selfUrl, ENT_QUOTES, 'UTF-8') ?>">Volver</a>
    </div>
</form>
