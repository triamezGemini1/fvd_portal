<?php

declare(strict_types=1);

/** @var ?array $row */
/** @var string $selfUrl URL base del módulo torneos */
/** @var int $fvd_torneo_org_id 0 = sin fila vinculada en asociaciones (nombre exacto en BD) */

$r = $row ?? [];
$isEdit = $row !== null;
$fvdEsAdminGeneral = AuthService::role() === AuthService::ROLE_FVD_ADMIN;
$aficheUrl = '';
if (!empty($r['afiche']) && is_string($r['afiche']) && $r['afiche'] !== '') {
    $aficheUrl = upload_url($r['afiche']);
}
$invitacionUrl = '';
if (!empty($r['invitacion']) && is_string($r['invitacion']) && $r['invitacion'] !== '') {
    $invitacionUrl = upload_url($r['invitacion']);
}

$tipoGenVal = (int) ($r['tipo'] ?? 1);
if ($tipoGenVal < 1 || $tipoGenVal > 3) {
    $tipoGenVal = 1;
}
$fvdTorneoEsCampeonatoCol = !empty($fvdTorneoEsCampeonatoCol ?? false);
$esCampeonatoVal = ($fvdTorneoEsCampeonatoCol && (int) ($r['es_campeonato'] ?? 0) === 1) ? 1 : 0;

$vTiempo = isset($r['tiempo']) && $r['tiempo'] !== '' && $r['tiempo'] !== null ? (string) (int) $r['tiempo'] : '35';
$vPuntos = isset($r['puntos']) && $r['puntos'] !== '' && $r['puntos'] !== null ? (string) (int) $r['puntos'] : '200';
$vRondas = isset($r['rondas']) && $r['rondas'] !== '' && $r['rondas'] !== null ? (string) (int) $r['rondas'] : '9';
$vRanking = isset($r['ranking']) && $r['ranking'] !== '' && $r['ranking'] !== null ? (string) (int) $r['ranking'] : '1';
$vCostotor = isset($r['costotor']) ? (string) $r['costotor'] : '0';
$vPareclub = isset($r['pareclub']) && $r['pareclub'] !== '' && $r['pareclub'] !== null ? (string) (int) $r['pareclub'] : '0';
$vEstatus = isset($r['estatus']) && $r['estatus'] !== '' && $r['estatus'] !== null ? (string) (int) $r['estatus'] : '0';

if (!function_exists('fvd_return_preserve_query_params')) {
    require_once FVD_PROJECT_ROOT . '/config/fvd_navigation_return.php';
}
$fvdTorneoFormVolverListadoUrl = fvd_return_preserve_query_params($selfUrl . '?action=list');
$fvdTorneoFormMasterEmbed = fvd_master_embed_active();
$fvdTorneoFormShowTitleBlock = !$fvdTorneoFormMasterEmbed
    && (!function_exists('fvd_delegado_inner_heading_visible') || fvd_delegado_inner_heading_visible());

?>

<div class="fvd-tf-form-page">
    <?php if ($fvdTorneoFormShowTitleBlock): ?>
    <p class="fvd-tf-federacion-name"><?= htmlspecialchars(FvdAdminService::ASOCIACION_NOMBRE_FEDERACION_TORNEOS, ENT_QUOTES, 'UTF-8') ?></p>
    <h1 class="fvd-tf-form-title"><?= $isEdit ? 'Editar torneo' : 'Nuevo torneo' ?></h1>
    <?php endif; ?>

    <?php if (!empty($fvd_error ?? '')): ?>
    <p class="fvd-mod-msg fvd-tf-form-page__err"><?= htmlspecialchars((string) $fvd_error, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>

    <form class="fvd-tf-form fvd-tf-form--framed" method="post" enctype="multipart/form-data" action="<?= htmlspecialchars($selfUrl . '?action=form' . ($isEdit ? '&id=' . (int) $r['torneo'] : ''), ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="_action" value="save">
        <?php if ($isEdit): ?><input type="hidden" name="torneo" value="<?= (int) $r['torneo'] ?>"><?php endif; ?>
        <input type="hidden" name="organizacion_id" value="<?= $fvd_torneo_org_id > 0 ? (string) (int) $fvd_torneo_org_id : '' ?>">

        <?php if ($fvdEsAdminGeneral): ?>
        <input type="hidden" name="publicar_landing" value="1">
        <input type="hidden" name="invitar_todas_al_guardar" value="1">
        <?php endif; ?>

        <div class="fvd-tf-grid fvd-tf-grid--form">
            <div class="fvd-tf-nombre-fecha-row">
                <div>
                    <label for="nombre">Nombre del torneo</label>
                    <input class="fvd-input" id="nombre" name="nombre" required value="<?= htmlspecialchars((string) ($r['nombre'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="fvd-tf-nombre-fecha-row__fecha">
                    <label for="fechator">Fecha del torneo</label>
                    <input class="fvd-input" type="date" id="fechator" name="fechator" required value="<?= htmlspecialchars(substr((string) ($r['fechator'] ?? ''), 0, 10), ENT_QUOTES, 'UTF-8') ?>">
                </div>
            </div>

            <div>
                <label for="lugar">Lugar</label>
                <input class="fvd-input" id="lugar" name="lugar" value="<?= htmlspecialchars((string) ($r['lugar'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
            </div>

            <?php if ($fvdEsAdminGeneral && $isEdit): ?>
            <div>
                <label for="fecha_limite_cambios">Límite de cambios de nómina</label>
                <input class="fvd-input" type="date" id="fecha_limite_cambios" name="fecha_limite_cambios"
                    value="<?= htmlspecialchars(substr((string) ($r['fecha_limite_cambios'] ?? ''), 0, 10), ENT_QUOTES, 'UTF-8') ?>">
                <small style="display:block;font-size:0.7rem;color:var(--fvd-muted,#94a3b8);margin-top:4px">
                    A partir del día siguiente, el delegado solo podrá consultar inscripciones y cambios de plantilla (sin edición).
                </small>
            </div>
            <?php endif; ?>

            <div>
                <label for="tipo">Tipo de torneo (género)</label>
                <select class="fvd-input" id="tipo" name="tipo" style="max-width:14rem" required>
                    <?php foreach ([1 => 'Masculino', 2 => 'Femenino', 3 => 'Mixto'] as $k => $lab): ?>
                        <option value="<?= $k ?>" <?= $tipoGenVal === $k ? 'selected' : '' ?>><?= htmlspecialchars($lab, ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
                <small style="display:block;font-size:0.7rem;color:var(--fvd-muted,#94a3b8);margin-top:4px">
                    Define el criterio de elegibles en inscripción (listado Disponibles y búsqueda de atletas).
                </small>
            </div>
            <?php if ($fvdTorneoEsCampeonatoCol): ?>
            <div>
                <input type="hidden" name="es_campeonato" value="0">
                <label class="fvd-tf-check-readonly" style="cursor:pointer;display:flex;align-items:flex-start;gap:0.5rem;margin-top:0.25rem">
                    <input type="checkbox" name="es_campeonato" id="es_campeonato" value="1" <?= $esCampeonatoVal === 1 ? 'checked' : '' ?> style="margin-top:0.15rem">
                    <span><strong>Campeonato</strong> (varias categorías / mismo evento). Si está marcado, puede indicar el <strong>ID grupo de evento</strong> para vincular con otros torneos del mismo campeonato.</span>
                </label>
            </div>
            <?php endif; ?>
            <div>
                <label for="clase">Modalidad (clase)</label>
                <select class="fvd-input" id="clase" name="clase" style="max-width:14rem">
                    <?php foreach ([1 => 'Individual', 2 => 'Parejas', 3 => 'Equipos'] as $k => $lab): ?>
                        <option value="<?= $k ?>" <?= ((int) ($r['clase'] ?? 1) === $k) ? 'selected' : '' ?>><?= htmlspecialchars($lab, ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <?php if ($fvdEsAdminGeneral && $isEdit): ?>
            <div id="fvd_grupo_evento_wrap" class="fvd-tf-grupo-wrap"<?= ($fvdTorneoEsCampeonatoCol ? $esCampeonatoVal === 1 : $tipoGenVal === 2) ? '' : ' style="display:none"' ?>>
                <label for="grupo_evento_id">ID grupo de evento (campeonatos)</label>
                <input class="fvd-input" type="number" min="1" id="grupo_evento_id" name="grupo_evento_id" style="max-width:14rem"
                    placeholder="Ej. 1"
                    value="<?= isset($r['grupo_evento_id']) && (int) ($r['grupo_evento_id'] ?? 0) > 0 ? (int) $r['grupo_evento_id'] : '' ?>">
                <small style="display:block;font-size:0.7rem;color:var(--fvd-muted,#94a3b8);margin-top:4px">
                    También puede usar <a href="<?= htmlspecialchars($selfUrl . '?action=relacion_grupo', ENT_QUOTES, 'UTF-8') ?>">Relacionar campeonatos</a> (próximos, mismo código de grupo). Aquí puede corregir el número a mano si hace falta.
                </small>
            </div>
            <?php endif; ?>

            <?php if ($fvdEsAdminGeneral): ?>
            <div>
                <label class="fvd-tf-check-readonly" style="cursor:pointer">
                    <input type="checkbox" name="apertura_anual" value="1" <?= !empty($r['apertura_anual']) ? 'checked' : '' ?>>
                    <span>Primer torneo del año: al <strong>crear</strong> este evento, marcar <code>anualidad = 1</code> en todos los atletas.</span>
                </label>
            </div>
            <div class="fvd-tf-checks-row">
                <label class="fvd-tf-check-readonly">
                    <input type="checkbox" checked disabled>
                    <span>Publicar en el sitio web (inicio, calendario y avisos). Los eventos pueden figurar como pendientes hasta activación según directrices de la federación.</span>
                </label>
                <label class="fvd-tf-check-readonly">
                    <input type="checkbox" checked disabled>
                    <span>Registrar invitación a todas las asociaciones del circuito (convocatoria e inscripciones en portal).</span>
                </label>
            </div>
            <?php endif; ?>

            <div class="fvd-tf-metrics-row">
                <div class="fvd-tf-metric">
                    <label for="tiempo">Tiempo</label>
                    <input class="fvd-input" type="number" id="tiempo" name="tiempo" value="<?= htmlspecialchars($vTiempo, ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="fvd-tf-metric">
                    <label for="puntos">Puntos</label>
                    <input class="fvd-input" type="number" id="puntos" name="puntos" value="<?= htmlspecialchars($vPuntos, ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="fvd-tf-metric">
                    <label for="rondas">Rondas</label>
                    <input class="fvd-input" type="number" id="rondas" name="rondas" value="<?= htmlspecialchars($vRondas, ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="fvd-tf-metric">
                    <label for="ranking">Ranking</label>
                    <input class="fvd-input" type="number" id="ranking" name="ranking" value="<?= htmlspecialchars($vRanking, ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="fvd-tf-metric">
                    <label for="costotor">Costo</label>
                    <input class="fvd-input" type="number" step="0.01" id="costotor" name="costotor" value="<?= htmlspecialchars($vCostotor, ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="fvd-tf-metric">
                    <label for="pareclub">Pareclub</label>
                    <input class="fvd-input" type="number" id="pareclub" name="pareclub" value="<?= htmlspecialchars($vPareclub, ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="fvd-tf-metric">
                    <label for="estatus">Estatus</label>
                    <input class="fvd-input" type="number" id="estatus" name="estatus" value="<?= htmlspecialchars($vEstatus, ENT_QUOTES, 'UTF-8') ?>">
                </div>
            </div>

            <div class="fvd-tf-files-2col">
                <div class="fvd-tf-file-col">
                    <label for="invitacion">Invitación (archivo)</label>
                    <input class="fvd-input fvd-tf-file-input" type="file" id="invitacion" name="invitacion">
                    <div class="fvd-tf-file-preview" id="fvd_preview_invitacion">
                        <?php if ($invitacionUrl !== ''): ?>
                            <p class="fvd-tf-file-preview__linkwrap">
                                <a href="<?= htmlspecialchars($invitacionUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">Abrir archivo actual</a>
                            </p>
                            <span class="fvd-tf-file-preview__meta fvd-atl-muted"><?= htmlspecialchars((string) $r['invitacion'], ENT_QUOTES, 'UTF-8') ?></span>
                        <?php else: ?>
                            <span class="fvd-tf-file-preview__placeholder fvd-atl-muted">Vista previa / archivo anexo</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="fvd-tf-file-col">
                    <label for="afiche">Afiche (imagen)</label>
                    <input class="fvd-input fvd-tf-file-input" type="file" id="afiche" name="afiche" accept="image/*">
                    <div class="fvd-tf-file-preview fvd-tf-file-preview--afiche" id="fvd_preview_afiche">
                        <?php if ($aficheUrl !== ''): ?>
                            <img src="<?= htmlspecialchars($aficheUrl, ENT_QUOTES, 'UTF-8') ?>" alt="Afiche actual" class="fvd-tf-file-preview__img">
                        <?php else: ?>
                            <span class="fvd-tf-file-preview__placeholder fvd-atl-muted">Vista previa del afiche</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="fvd-mod-actions fvd-tf-form__actions">
            <button type="submit">Guardar</button>
            <a href="<?= htmlspecialchars($fvdTorneoFormVolverListadoUrl, ENT_QUOTES, 'UTF-8') ?>">Volver al listado</a>
        </div>
    </form>
</div>

<script src="<?= htmlspecialchars(url('assets/js/file-preview.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<script>
(function () {
    if (typeof window.filePreview === 'undefined') return;
    window.filePreview.init('afiche', 'fvd_preview_afiche', 'image', { previewSize: 200 });
})();
</script>
<?php if ($fvdEsAdminGeneral && $isEdit): ?>
<script>
(function () {
    var tipo = document.getElementById('tipo');
    var wrap = document.getElementById('fvd_grupo_evento_wrap');
    var inp = document.getElementById('grupo_evento_id');
    var cb = document.getElementById('es_campeonato');
    if (!wrap) return;
    function sync() {
        var esCamp = cb ? cb.checked : (tipo && tipo.value === '2');
        wrap.style.display = esCamp ? '' : 'none';
        if (!esCamp && inp) inp.value = '';
    }
    if (tipo) tipo.addEventListener('change', sync);
    if (cb) cb.addEventListener('change', sync);
})();
</script>
<?php endif; ?>
