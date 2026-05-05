<?php
/** @var ?array $row */
/** @var list<array<string,mixed>> $asociaciones */
/** @var string $selfUrl */
$r = $row ?? [];
$isEdit = $row !== null;
?>

<?php if (function_exists('fvd_delegado_inner_heading_visible') && fvd_delegado_inner_heading_visible()): ?>
<h1><?= $isEdit ? 'Editar torneo' : 'Nuevo torneo' ?></h1>
<?php endif; ?>

<form method="post" enctype="multipart/form-data" action="<?= htmlspecialchars($selfUrl . '?action=form' . ($isEdit ? '&id=' . (int) $r['torneo'] : ''), ENT_QUOTES, 'UTF-8') ?>">
    <input type="hidden" name="_action" value="save">
    <?php if ($isEdit): ?><input type="hidden" name="torneo" value="<?= (int) $r['torneo'] ?>"><?php endif; ?>

    <div class="fvd-tf-grid">
        <div>
            <label for="organizacion_id">Asociación</label>
            <select class="fvd-input" id="organizacion_id" name="organizacion_id" required style="max-width:100%">
                <?php foreach ($asociaciones as $a): ?>
                    <option value="<?= (int) $a['id'] ?>" <?= ((int) ($r['organizacion_id'] ?? 0) === (int) $a['id']) ? 'selected' : '' ?>><?= htmlspecialchars((string) $a['nombre'], ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="nombre">Nombre</label>
            <input class="fvd-input" id="nombre" name="nombre" required value="<?= htmlspecialchars((string) ($r['nombre'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div>
            <label for="lugar">Lugar</label>
            <input class="fvd-input" id="lugar" name="lugar" required value="<?= htmlspecialchars((string) ($r['lugar'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div>
            <label for="fechator">Fecha</label>
            <input class="fvd-input" type="date" id="fechator" name="fechator" required value="<?= htmlspecialchars(substr((string) ($r['fechator'] ?? ''), 0, 10), ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div>
            <label for="tipo">Tipo</label>
            <select class="fvd-input" id="tipo" name="tipo" style="max-width:12rem">
                <?php foreach ([1 => 'Masculino', 2 => 'Femenino', 3 => 'Mixto'] as $k => $lab): ?>
                    <option value="<?= $k ?>" <?= ((int) ($r['tipo'] ?? 1) === $k) ? 'selected' : '' ?>><?= htmlspecialchars($lab, ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="clase">Modalidad</label>
            <select class="fvd-input" id="clase" name="clase" style="max-width:12rem">
                <?php foreach ([1 => 'Individual', 2 => 'Parejas', 3 => 'Equipos'] as $k => $lab): ?>
                    <option value="<?= $k ?>" <?= ((int) ($r['clase'] ?? 1) === $k) ? 'selected' : '' ?>><?= htmlspecialchars($lab, ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="tiempo">Tiempo</label>
            <input class="fvd-input" type="number" id="tiempo" name="tiempo" required value="<?= htmlspecialchars((string) ($r['tiempo'] ?? '0'), ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div>
            <label for="puntos">Puntos</label>
            <input class="fvd-input" type="number" id="puntos" name="puntos" required value="<?= htmlspecialchars((string) ($r['puntos'] ?? '0'), ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div>
            <label for="rondas">Rondas</label>
            <input class="fvd-input" type="number" id="rondas" name="rondas" required value="<?= htmlspecialchars((string) ($r['rondas'] ?? '0'), ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div>
            <label for="costotor">Costo</label>
            <input class="fvd-input" type="number" step="0.01" id="costotor" name="costotor" required value="<?= htmlspecialchars((string) ($r['costotor'] ?? '0'), ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div>
            <label for="ranking">Ranking</label>
            <input class="fvd-input" type="number" id="ranking" name="ranking" required value="<?= htmlspecialchars((string) ($r['ranking'] ?? '0'), ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div>
            <label for="pareclub">Pareclub</label>
            <input class="fvd-input" type="number" id="pareclub" name="pareclub" required value="<?= htmlspecialchars((string) ($r['pareclub'] ?? '0'), ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div>
            <label for="estatus">Estatus</label>
            <input class="fvd-input" type="number" id="estatus" name="estatus" required value="<?= htmlspecialchars((string) ($r['estatus'] ?? '0'), ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div>
            <label for="invitacion">Invitación (archivo)</label>
            <input class="fvd-input" type="file" id="invitacion" name="invitacion" style="padding:4px">
        </div>
        <div>
            <label for="afiche">Afiche (imagen)</label>
            <input class="fvd-input" type="file" id="afiche" name="afiche" accept="image/*" style="padding:4px">
        </div>
    </div>

    <div class="fvd-mod-actions">
        <button type="submit">Guardar</button>
        <a href="<?= htmlspecialchars($selfUrl, ENT_QUOTES, 'UTF-8') ?>">Volver</a>
    </div>
</form>
