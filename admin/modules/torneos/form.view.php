<?php
/** @var ?array $row */
/** @var list<array<string,mixed>> $asociaciones */
/** @var string $selfUrl */
$r = $row ?? [];
$isEdit = $row !== null;
$fvdEsAdminGeneral = AuthService::role() === AuthService::ROLE_FVD_ADMIN;
?>

<h1><?= $isEdit ? 'Editar torneo' : 'Nuevo torneo' ?></h1>

<form method="post" enctype="multipart/form-data" action="<?= htmlspecialchars($selfUrl . '?action=form' . ($isEdit ? '&id=' . (int) $r['torneo'] : ''), ENT_QUOTES, 'UTF-8') ?>">
    <input type="hidden" name="_action" value="save">
    <?php if ($isEdit): ?><input type="hidden" name="torneo" value="<?= (int) $r['torneo'] ?>"><?php endif; ?>

    <div class="fvd-tf-grid">
        <div>
            <label for="organizacion_id">Asociación (organizacion_id)</label>
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
            <input class="fvd-input" id="lugar" name="lugar" value="<?= htmlspecialchars((string) ($r['lugar'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div>
            <label for="fechator">Fecha (fechator)</label>
            <input class="fvd-input" type="date" id="fechator" name="fechator" required value="<?= htmlspecialchars(substr((string) ($r['fechator'] ?? ''), 0, 10), ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <?php if ($fvdEsAdminGeneral): ?>
        <div style="grid-column:1/-1">
            <label style="display:flex;gap:8px;align-items:center;font-size:.875rem;color:var(--fvd-text)">
                <input type="checkbox" name="publicar_landing" value="1" <?= (!isset($r['publicar_landing']) || (int) ($r['publicar_landing'] ?? 1) === 1) ? 'checked' : '' ?>>
                Publicar en el sitio web (inicio, calendario y avisos de torneos en vivo)
            </label>
            <p class="fvd-atl-muted" style="margin:4px 0 0;font-size:12px">
                <?php if (!$isEdit): ?>
                    Los <strong>torneos nuevos</strong> se publican automáticamente en el landing y en el calendario público. Al <strong>editar</strong>, puede desmarcar para ocultarlos del sitio.
                <?php else: ?>
                    Si desactiva esta opción, el evento no aparecerá en el landing ni en el calendario público.
                <?php endif; ?>
            </p>
            <?php if ($isEdit): ?>
            <label style="display:flex;gap:8px;align-items:center;font-size:.875rem;color:var(--fvd-text);margin-top:12px">
                <input type="checkbox" name="invitar_todas_al_guardar" value="1">
                Al guardar (edición), registrar invitación a <strong>todas</strong> las asociaciones (habilita inscripciones desde el portal)
            </label>
            <p class="fvd-atl-muted" style="margin:4px 0 0;font-size:12px">Requiere la tabla de convocatorias. Tras guardar puede abrir el panel del evento.</p>
            <?php else: ?>
            <p class="fvd-atl-muted" style="margin:12px 0 0;font-size:12px">Al <strong>crear</strong> este torneo se invitará automáticamente a todas las asociaciones, se crearán los avisos para delegados y las tarjetas PDF con enlace de acceso (si Dompdf está disponible).</p>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <div>
            <label for="tipo">Tipo</label>
            <select class="fvd-input" id="tipo" name="tipo" style="max-width:12rem">
                <?php foreach ([1 => 'Masculino', 2 => 'Femenino', 3 => 'Mixto'] as $k => $lab): ?>
                    <option value="<?= $k ?>" <?= ((int) ($r['tipo'] ?? 1) === $k) ? 'selected' : '' ?>><?= htmlspecialchars($lab, ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="clase">Modalidad (clase)</label>
            <select class="fvd-input" id="clase" name="clase" style="max-width:12rem">
                <?php foreach ([1 => 'Individual', 2 => 'Parejas', 3 => 'Equipos'] as $k => $lab): ?>
                    <option value="<?= $k ?>" <?= ((int) ($r['clase'] ?? 1) === $k) ? 'selected' : '' ?>><?= htmlspecialchars($lab, ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="tiempo">Tiempo</label>
            <input class="fvd-input" type="number" id="tiempo" name="tiempo" value="<?= htmlspecialchars((string) ($r['tiempo'] ?? '0'), ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div>
            <label for="puntos">Puntos</label>
            <input class="fvd-input" type="number" id="puntos" name="puntos" value="<?= htmlspecialchars((string) ($r['puntos'] ?? '0'), ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div>
            <label for="rondas">Rondas</label>
            <input class="fvd-input" type="number" id="rondas" name="rondas" value="<?= htmlspecialchars((string) ($r['rondas'] ?? '0'), ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div>
            <label for="costotor">Costo (costotor)</label>
            <input class="fvd-input" type="number" step="0.01" id="costotor" name="costotor" value="<?= htmlspecialchars((string) ($r['costotor'] ?? '0'), ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div>
            <label for="ranking">Ranking</label>
            <input class="fvd-input" type="number" id="ranking" name="ranking" value="<?= htmlspecialchars((string) ($r['ranking'] ?? '0'), ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div>
            <label for="pareclub">Pareclub</label>
            <input class="fvd-input" type="number" id="pareclub" name="pareclub" value="<?= htmlspecialchars((string) ($r['pareclub'] ?? '0'), ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div>
            <label for="estatus">Estatus (entero)</label>
            <input class="fvd-input" type="number" id="estatus" name="estatus" value="<?= htmlspecialchars((string) ($r['estatus'] ?? '0'), ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <?php if ($isEdit && !empty($r['clavetor'])): ?>
        <div>
            <label>Clave (clavetor)</label>
            <input class="fvd-input" type="text" readonly value="<?= htmlspecialchars((string) $r['clavetor'], ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <?php endif; ?>
        <div>
            <label for="invitacion">Invitación (archivo)</label>
            <input class="fvd-input" type="file" id="invitacion" name="invitacion" style="padding:4px">
            <?php if (!empty($r['invitacion'])): ?>
                <span class="fvd-atl-muted" style="display:block;margin-top:4px;font-size:12px">Actual: <?= htmlspecialchars((string) $r['invitacion'], ENT_QUOTES, 'UTF-8') ?></span>
            <?php endif; ?>
        </div>
        <div>
            <label for="afiche">Afiche (imagen)</label>
            <input class="fvd-input" type="file" id="afiche" name="afiche" accept="image/*" style="padding:4px">
            <?php if (!empty($r['afiche'])): ?>
                <span class="fvd-atl-muted" style="display:block;margin-top:4px;font-size:12px">Actual: <?= htmlspecialchars((string) $r['afiche'], ENT_QUOTES, 'UTF-8') ?></span>
            <?php endif; ?>
        </div>
    </div>

    <div class="fvd-mod-actions">
        <button type="submit">Guardar</button>
        <a href="<?= htmlspecialchars($selfUrl, ENT_QUOTES, 'UTF-8') ?>">Volver</a>
    </div>
</form>
