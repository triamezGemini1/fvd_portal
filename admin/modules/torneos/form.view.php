<?php

declare(strict_types=1);

/** @var ?array $row */
/** @var list<array<string,mixed>> $asociaciones */
/** @var string $selfUrl */
/** @var int $fvd_torneo_org_id */
/** @var string $fvd_torneo_org_nombre */

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

$tipoVal = (int) ($r['tipo'] ?? 1);
if ($tipoVal < 1 || $tipoVal > 2) {
    $tipoVal = $tipoVal === 3 ? 2 : 1;
}

$vTiempo = isset($r['tiempo']) && $r['tiempo'] !== '' && $r['tiempo'] !== null ? (string) (int) $r['tiempo'] : '35';
$vPuntos = isset($r['puntos']) && $r['puntos'] !== '' && $r['puntos'] !== null ? (string) (int) $r['puntos'] : '200';
$vRondas = isset($r['rondas']) && $r['rondas'] !== '' && $r['rondas'] !== null ? (string) (int) $r['rondas'] : '9';
$vRanking = isset($r['ranking']) && $r['ranking'] !== '' && $r['ranking'] !== null ? (string) (int) $r['ranking'] : '1';
$vCostotor = isset($r['costotor']) ? (string) $r['costotor'] : '0';
$vPareclub = isset($r['pareclub']) && $r['pareclub'] !== '' && $r['pareclub'] !== null ? (string) (int) $r['pareclub'] : '0';
$vEstatus = isset($r['estatus']) && $r['estatus'] !== '' && $r['estatus'] !== null ? (string) (int) $r['estatus'] : '0';

$federacionNombre = $fvd_torneo_org_nombre !== '' ? $fvd_torneo_org_nombre : 'Organización';
?>

<div class="fvd-tf-form-page">
    <p class="fvd-tf-federacion-name"><?= htmlspecialchars($federacionNombre, ENT_QUOTES, 'UTF-8') ?></p>
    <h1 class="fvd-tf-form-title"><?= $isEdit ? 'Editar torneo' : 'Nuevo torneo' ?></h1>

    <?php if (!empty($fvd_error ?? '')): ?>
    <p class="fvd-mod-msg fvd-tf-form-page__err"><?= htmlspecialchars((string) $fvd_error, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>

    <form class="fvd-tf-form fvd-tf-form--framed" method="post" enctype="multipart/form-data" action="<?= htmlspecialchars($selfUrl . '?action=form' . ($isEdit ? '&id=' . (int) $r['torneo'] : ''), ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="_action" value="save">
        <?php if ($isEdit): ?><input type="hidden" name="torneo" value="<?= (int) $r['torneo'] ?>"><?php endif; ?>
        <input type="hidden" name="organizacion_id" value="<?= (int) $fvd_torneo_org_id ?>">

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

            <?php if ($fvdEsAdminGeneral): ?>
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

            <div>
                <label for="tipo">Tipo</label>
                <select class="fvd-input" id="tipo" name="tipo" style="max-width:14rem">
                    <?php foreach ([1 => 'Torneo', 2 => 'Campeonato'] as $k => $lab): ?>
                        <option value="<?= $k ?>" <?= $tipoVal === $k ? 'selected' : '' ?>><?= htmlspecialchars($lab, ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="clase">Modalidad (clase)</label>
                <select class="fvd-input" id="clase" name="clase" style="max-width:14rem">
                    <?php foreach ([1 => 'Individual', 2 => 'Parejas', 3 => 'Equipos'] as $k => $lab): ?>
                        <option value="<?= $k ?>" <?= ((int) ($r['clase'] ?? 1) === $k) ? 'selected' : '' ?>><?= htmlspecialchars($lab, ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

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
            <button type="submit"<?= ($fvdEsAdminGeneral && $fvd_torneo_org_id <= 0) ? ' disabled' : '' ?>>Guardar</button>
            <a href="<?= htmlspecialchars($selfUrl, ENT_QUOTES, 'UTF-8') ?>">Volver</a>
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
