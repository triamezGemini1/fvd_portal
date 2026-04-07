<?php
/** @var ?array $row */
/** @var list<array<string,mixed>> $asociaciones */
/** @var string $selfUrl */

declare(strict_types=1);

$fvd_form_embed = $fvd_form_embed ?? false;

$r = $row ?? [];
$isEdit = $row !== null;
$isFvdAdmin = AuthService::role() === AuthService::ROLE_FVD_ADMIN;
$asocHiddenVal = 0;
if (!$isFvdAdmin) {
    $asocHiddenVal = AuthService::idAsociacion() ?? 0;
    if ($asocHiddenVal <= 0 && $asociaciones !== []) {
        $asocHiddenVal = (int) ($asociaciones[0]['id'] ?? 0);
    }
    if ($isEdit) {
        $asocHiddenVal = (int) ($r['asociacion'] ?? $asocHiddenVal);
    }
}

$fechnac = isset($r['fechnac']) && $r['fechnac'] ? substr((string) $r['fechnac'], 0, 10) : '';
$fechfvd = isset($r['fechfvd']) && $r['fechfvd'] ? substr((string) $r['fechfvd'], 0, 10) : '';
$fechact = isset($r['fechact']) && $r['fechact'] ? substr((string) $r['fechact'], 0, 10) : '';

$numfvdVal = (int) ($r['numfvd'] ?? 0);
$estatusVal = (int) ($r['estatus'] ?? FvdAdminService::ATLETA_ESTATUS_PENDIENTE);
if ($fechnac !== '') {
    $categEtiqueta = FvdAdminService::atletasCategoriaEtiquetaPorCodigo(
        FvdAdminService::atletasCategoriaCodigoDesdeFechanac($fechnac)
    );
} elseif ($isEdit) {
    $categEtiqueta = FvdAdminService::atletasCategoriaEtiquetaPorCodigo((int) ($r['categ'] ?? 0));
} else {
    $categEtiqueta = '— (indique fecha de nac.)';
}

$fotoUrl = !empty($r['foto'])
    ? url('crud_atletas/uploads/' . ltrim((string) $r['foto'], '/'))
    : '';
$cedulaImgUrl = !empty($r['cedula_img'])
    ? url('crud_atletas/uploads/' . ltrim((string) $r['cedula_img'], '/'))
    : '';

$mostrarAprobacionFvd = $isEdit && $isFvdAdmin && $numfvdVal === 0;

$fvdCedulaLookupBase = $selfUrl . '?action=lookup_cedula';
?>

<style>
.fvd-atleta-form { max-width: 56rem; margin: 0 auto; }
.fvd-atleta-form-page {
    max-width: 56rem;
    margin-left: auto;
    margin-right: auto;
    width: 100%;
    box-sizing: border-box;
    padding: 0 0.75rem;
}
.fvd-atleta-form-page__title {
    text-align: center;
    margin: 0 0 0.65rem;
    width: 100%;
}
.fvd-atleta-form-page .fvd-atleta-form.fvd-atleta-form--framed {
    max-width: none;
    width: 100%;
    margin: 0;
    border: 2px solid var(--fvd-amarillo, var(--fvd-dorado, #fff200));
    border-radius: 0.75rem;
    padding: 1rem 1.15rem 0.9rem;
    box-sizing: border-box;
    background: rgba(0, 0, 0, 0.08);
    box-shadow: 0 0 0 1px rgba(255, 242, 0, 0.14);
}
.fvd-atleta-form-page--embed {
    max-width: none;
    margin: 0;
    padding: 0;
    width: auto;
}
.fvd-atleta-form-page--embed .fvd-atleta-form-page__title {
    text-align: left;
    margin: 0 0 0.5rem;
}
.fvd-atleta-form-page--embed .fvd-atleta-form--framed {
    border: none;
    border-radius: 0;
    padding: 0;
    background: transparent;
    box-shadow: none;
}
.fvd-atleta-form-page .fvd-atleta-form__actions.fvd-mod-actions {
    justify-content: center;
}
.fvd-atleta-form__top-grid {
    display: grid;
    grid-template-columns: 1fr min(273px, 42vw);
    gap: 1rem;
    align-items: start;
    margin-bottom: 0.65rem;
}
.fvd-atleta-form__asoc--grid-full {
    grid-column: 1 / -1;
}
.fvd-atleta-form__fields-col {
    grid-column: 1;
    grid-row: 2;
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 0;
}
.fvd-atleta-form__photo-col {
    grid-column: 2;
    grid-row: 2;
    align-self: start;
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
    align-items: stretch;
}
.fvd-atleta-form__top-grid--scoped-asoc .fvd-atleta-form__fields-col,
.fvd-atleta-form__top-grid--scoped-asoc .fvd-atleta-form__photo-col {
    grid-row: 1;
}
.fvd-atleta-form__foto-box {
    text-align: center;
    padding: 0.5rem;
    border: 1px solid var(--fvd-border, rgba(255,255,255,0.14));
    border-radius: 8px;
    background: rgba(0,0,0,0.12);
    min-height: 156px;
    display: flex;
    align-items: center;
    justify-content: center;
}
.fvd-atleta-form__foto-file { width: 100%; }
.fvd-atleta-form__foto-file .fvd-input[type="file"] { width: 100%; font-size: 0.75rem; padding: 4px; }
.fvd-atleta-form__numfvd-categ-row {
    display: flex;
    flex-direction: row;
    flex-wrap: nowrap;
    gap: 0.45rem;
    align-items: stretch;
}
.fvd-atleta-form__numfvd-categ-row > .fvd-atleta-form__numfvd,
.fvd-atleta-form__numfvd-categ-row > .fvd-atleta-form__categ-side {
    flex: 1 1 0;
    min-width: 0;
}
.fvd-atleta-form__row--fechas-fvd > .fvd-atleta-form__numfvd,
.fvd-atleta-form__row--fechas-fvd > .fvd-atleta-form__categ-side {
    flex: 1 1 7.5rem;
    min-width: 5.5rem;
    max-width: 11rem;
    align-self: flex-end;
}
.fvd-atleta-form__numfvd {
    font-size: 0.75rem;
    color: var(--fvd-muted);
    text-align: left;
    padding: 0.3rem 0.4rem;
    border-radius: 6px;
    background: rgba(0,0,0,0.08);
}
.fvd-atleta-form__numfvd strong { color: var(--fvd-amarillo, #fff200); font-size: 0.9375rem; }
.fvd-atleta-form__categ-side {
    font-size: 0.75rem;
    color: var(--fvd-muted);
    text-align: left;
    padding: 0.3rem 0.4rem;
    border-radius: 6px;
    background: rgba(0,0,0,0.06);
    line-height: 1.3;
}
.fvd-atleta-form__categ-side .fvd-atleta-form__categ-hint {
    margin: 0;
    font-size: 0.8125rem;
    color: var(--fvd-amarillo, #fff200);
    line-height: 1.25;
}
.fvd-atleta-form__preview {
    min-height: 2.5rem;
    width: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
}
.fvd-atleta-form__preview img {
    max-width: 100%;
    max-height: 182px;
    border-radius: 6px;
    object-fit: contain;
}
.fvd-atleta-form__preview--cedula-full {
    width: 100%;
    justify-content: center;
    min-height: 3rem;
}
.fvd-atleta-form__preview--cedula-full img {
    width: 100% !important;
    max-width: 100% !important;
    height: auto !important;
    max-height: none !important;
    object-fit: contain;
}
.fvd-atleta-form__side-field { margin-bottom: 0.55rem; }
.fvd-atleta-form__side-field .fvd-input { max-width: none; width: 100%; }
.fvd-atleta-form__asoc {
    text-align: left;
    margin-bottom: 0.65rem;
}
.fvd-atleta-form__asoc label {
    display: block;
    font-size: 0.8125rem;
    color: var(--fvd-muted);
    margin-bottom: 0.35rem;
    font-weight: 600;
}
.fvd-atleta-form__asoc select.fvd-input {
    max-width: none;
    width: 100%;
    display: block;
}
.fvd-atleta-form__row {
    display: flex;
    flex-wrap: wrap;
    gap: 0.75rem;
    align-items: flex-end;
    margin-bottom: 0.65rem;
}
.fvd-atleta-form__row--cednom {
    display: grid;
    grid-template-columns: minmax(0, 3fr) minmax(0, 7fr);
    gap: 0.75rem;
    align-items: start;
}
.fvd-atleta-form__row--cednom .fvd-atleta-form__ced,
.fvd-atleta-form__row--cednom .fvd-atleta-form__nom {
    min-width: 0;
}
/* Cédula: caja ~40% más estrecha (60% del ancho de su columna) */
.fvd-atleta-form__row--cednom .fvd-atleta-form__ced .fvd-input {
    width: 60%;
    max-width: 100%;
    box-sizing: border-box;
}
/* Nombre: todo el ancho de la columna amplia */
.fvd-atleta-form__row--cednom .fvd-atleta-form__nom .fvd-input {
    width: 100%;
    max-width: none;
    box-sizing: border-box;
}
.fvd-atleta-form__row--3 > div {
    flex: 1 1 7rem;
    min-width: 6rem;
}
.fvd-atleta-form__row--3 .fvd-input { max-width: none; width: 100%; }
.fvd-atleta-form__row--2 > div {
    flex: 1 1 10rem;
}
.fvd-atleta-form__row--2 .fvd-input { max-width: none; width: 100%; }
.fvd-atleta-form__full { margin-bottom: 0.65rem; }
.fvd-atleta-form__full .fvd-input { max-width: none; width: 100%; }
.fvd-atleta-form__cedula-in-side { margin-bottom: 0.25rem; }
.fvd-atleta-form__cedula-in-side .fvd-input[type="file"] { max-width: 100%; width: 100%; }
.fvd-atleta-form__cedula-block {
    margin-top: 0.65rem;
    padding-top: 0.65rem;
    border-top: 1px solid var(--fvd-border, rgba(255,255,255,0.1));
}
.fvd-atleta-form__cedula-block .fvd-atleta-form__preview--cedula-full {
    margin-top: 0.45rem;
}
.fvd-atleta-form__row--fechas-fvd {
    display: flex;
    flex-direction: row;
    flex-wrap: wrap;
    gap: 0.65rem;
    align-items: flex-end;
    margin-bottom: 0.45rem;
}
.fvd-atleta-form__date-fvd-wrap {
    flex: 0 1 auto;
    min-width: 0;
    max-width: none;
}
/* Fechas FVD/act: +20% respecto al tamaño anterior (72% × max 11.7rem) */
.fvd-atleta-form__date-fvd-wrap .fvd-input[type="date"] {
    width: 72%;
    max-width: 11.7rem;
    min-width: 8.1rem;
    box-sizing: border-box;
}
.fvd-atleta-form__date-fvd-wrap .fvd-input { max-width: 100%; }
.fvd-atleta-form__row--5 {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    align-items: flex-end;
    margin-bottom: 0.35rem;
}
.fvd-atleta-form__row--5 > div {
    flex: 1 1 5.5rem;
    min-width: 4.5rem;
}
.fvd-atleta-form__row--5 .fvd-input { max-width: none; width: 100%; }
.fvd-atleta-form__row--cel-email-est {
    align-items: flex-start;
    margin-bottom: 0.55rem;
}
.fvd-atleta-form__row--cel-email-est > div {
    flex: 1 1 6rem;
    min-width: 0;
}
.fvd-atleta-form__row--cel-email-est > div:nth-child(1) { flex: 1 1 8rem; min-width: 7rem; }
.fvd-atleta-form__row--cel-email-est > div:nth-child(2) { flex: 2 1 12rem; min-width: 8rem; }
.fvd-atleta-form__row--cel-email-est > div:nth-child(3) { flex: 0 0 6.5rem; max-width: 8rem; }
.fvd-atleta-form__row--cel-email-est .fvd-input { max-width: none; width: 100%; }
.fvd-atleta-form__edit-block {
    margin-top: 0.55rem;
    padding-top: 0.55rem;
    border-top: 1px solid var(--fvd-border, rgba(255,255,255,0.14));
}
.fvd-atleta-form__edit-block h2 {
    font-size: var(--fvd-font-h3, 1.05rem);
    margin: 0 0 0.35rem;
    color: var(--fvd-amarillo, #fff200);
}
.fvd-atleta-form__actions.fvd-mod-actions {
    margin-top: 0.35rem;
    gap: 0.35rem;
    flex-wrap: wrap;
}
.fvd-atleta-form__actions.fvd-mod-actions button,
.fvd-atleta-form__actions.fvd-mod-actions a {
    padding: 0.28rem 0.55rem;
    font-size: 0.8125rem;
    line-height: 1.2;
}
.fvd-atleta-form__aprob {
    margin: 0.5rem 0;
    padding: 0.5rem 0.65rem;
    border: 1px solid var(--fvd-border, rgba(255,255,255,0.2));
    border-radius: 8px;
    background: rgba(255,242,0,0.06);
}
.fvd-atleta-form__aprob p { margin: 0 0 0.5rem; font-size: 0.875rem; color: var(--fvd-muted); }
.fvd-atleta-form__aprob label { display: flex; align-items: flex-start; gap: 0.5rem; margin-bottom: 0.45rem; cursor: pointer; font-size: 0.875rem; color: inherit; }
.fvd-atleta-form__aprob input[type="checkbox"] { margin-top: 0.2rem; }
.fvd-atleta-form label { font-size: 0.8125rem; color: var(--fvd-muted); display: block; margin-bottom: 0.2rem; }
.fvd-atleta-form__categ-hint { font-size: 0.9375rem; color: var(--fvd-amarillo, #fff200); margin: 0; }
.fvd-cedula-msg { font-size: 0.8125rem; margin: 0.35rem 0 0; min-height: 1.25em; }
.fvd-cedula-msg--ok { color: #7dffb0; font-weight: 600; }
.fvd-cedula-msg--err { color: #ff8a8a; }
.fvd-cedula-hint { font-size: 0.75rem; color: var(--fvd-muted); margin: 0.2rem 0 0; line-height: 1.35; }
@media (max-width: 720px) {
    .fvd-atleta-form__top-grid {
        grid-template-columns: 1fr;
    }
    .fvd-atleta-form__asoc--grid-full {
        grid-column: 1;
    }
    .fvd-atleta-form__fields-col,
    .fvd-atleta-form__photo-col {
        grid-column: 1;
        grid-row: auto;
    }
    .fvd-atleta-form__photo-col {
        max-width: 320px;
        margin: 0 auto;
    }
    .fvd-atleta-form__row--fechas-fvd {
        flex-direction: column;
    }
    .fvd-atleta-form__date-fvd-wrap {
        flex: 1 1 100%;
        min-width: 0;
    }
    .fvd-atleta-form__date-fvd-wrap .fvd-input[type="date"] {
        width: 100%;
        max-width: none;
    }
    .fvd-atleta-form__row--fechas-fvd > .fvd-atleta-form__numfvd,
    .fvd-atleta-form__row--fechas-fvd > .fvd-atleta-form__categ-side {
        max-width: none;
        flex: 1 1 100%;
        align-self: stretch;
    }
    .fvd-atleta-form__numfvd-categ-row {
        flex-wrap: wrap;
    }
}
@media (max-width: 640px) {
    .fvd-atleta-form__row--cednom { grid-template-columns: 1fr; }
    .fvd-atleta-form__row--cednom .fvd-atleta-form__ced .fvd-input {
        width: 100%;
    }
    .fvd-atleta-form__row--cel-email-est > div,
    .fvd-atleta-form__row--cel-email-est > div:nth-child(1),
    .fvd-atleta-form__row--cel-email-est > div:nth-child(2),
    .fvd-atleta-form__row--cel-email-est > div:nth-child(3) {
        flex: 1 1 100%;
        max-width: none;
    }
}
</style>

<?php
$fvdFormAction = $selfUrl . '?action=form' . ($isEdit ? '&id=' . (int) $r['id'] : '');
if ($fvd_form_embed) {
    $fvdFormAction .= '&embed=1';
}
?>
<div class="fvd-atleta-form-page<?= !empty($fvd_form_embed) ? ' fvd-atleta-form-page--embed' : '' ?>">
<h1 class="fvd-atleta-form-page__title"><?= $isEdit ? 'Editar atleta' : 'Nuevo atleta' ?></h1>
<form class="fvd-atleta-form fvd-atleta-form--framed" method="post" enctype="multipart/form-data" action="<?= htmlspecialchars($fvdFormAction, ENT_QUOTES, 'UTF-8') ?>"<?= $fvd_form_embed ? ' target="_parent"' : '' ?>>
    <input type="hidden" name="_action" value="save">
    <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><?php endif; ?>

    <div class="fvd-atleta-form__top-grid<?= $isFvdAdmin ? '' : ' fvd-atleta-form__top-grid--scoped-asoc' ?>">
        <?php if ($isFvdAdmin): ?>
        <div class="fvd-atleta-form__asoc fvd-atleta-form__asoc--grid-full">
            <label for="asociacion">Asociación</label>
            <select class="fvd-input" id="asociacion" name="asociacion" required>
                <option value="">— Asociación —</option>
                <?php foreach ($asociaciones as $a): ?>
                    <option value="<?= (int) $a['id'] ?>" <?= ((int) ($r['asociacion'] ?? 0) === (int) $a['id']) ? 'selected' : '' ?>><?= htmlspecialchars((string) $a['nombre'], ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php else: ?>
        <input type="hidden" name="asociacion" value="<?= (int) $asocHiddenVal ?>">
        <?php endif; ?>

        <div class="fvd-atleta-form__fields-col">
            <div class="fvd-atleta-form__row fvd-atleta-form__row--cednom">
                <div class="fvd-atleta-form__ced">
                    <label for="cedula">Cédula</label>
                    <input class="fvd-input" id="cedula" name="cedula" required value="<?= htmlspecialchars((string) ($r['cedula'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" autocomplete="off">
                    <?php if (!$isEdit): ?>
                    <p id="fvd_cedula_lookup_msg" class="fvd-cedula-msg" role="status" aria-live="polite"></p>
                    <p class="fvd-cedula-hint">Si la cédula ya existe en el sistema, verá un aviso y se abrirá la ficha completa del atleta (como en consulta / edición).</p>
                    <?php endif; ?>
                </div>
                <div class="fvd-atleta-form__nom">
                    <label for="nombre">Nombre</label>
                    <input class="fvd-input" id="nombre" name="nombre" required value="<?= htmlspecialchars((string) ($r['nombre'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                </div>
            </div>

            <div class="fvd-atleta-form__row fvd-atleta-form__row--3">
                <div>
                    <label for="sexo">Sexo</label>
                    <select class="fvd-input" id="sexo" name="sexo" style="max-width:none">
                        <?php foreach ([0 => '— No indicado —', 1 => 'Masculino', 2 => 'Femenino'] as $k => $lab): ?>
                            <option value="<?= $k ?>" <?= ((int) ($r['sexo'] ?? 0) === $k) ? 'selected' : '' ?>><?= htmlspecialchars($lab, ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="fechnac">Fecha nac.</label>
                    <input class="fvd-input" type="date" id="fechnac" name="fechnac" value="<?= htmlspecialchars($fechnac, ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div>
                    <label for="profesion">Profesión</label>
                    <input class="fvd-input" id="profesion" name="profesion" value="<?= htmlspecialchars((string) ($r['profesion'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                </div>
            </div>

            <div class="fvd-atleta-form__side-field">
                <label for="direccion">Dirección</label>
                <input class="fvd-input" id="direccion" name="direccion" value="<?= htmlspecialchars((string) ($r['direccion'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="fvd-atleta-form__row fvd-atleta-form__row--cel-email-est">
                <div>
                    <label for="celular">Celular</label>
                    <input class="fvd-input" id="celular" name="celular" value="<?= htmlspecialchars((string) ($r['celular'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div>
                    <label for="email">Email</label>
                    <input class="fvd-input" type="email" id="email" name="email" value="<?= htmlspecialchars((string) ($r['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div>
                    <?php
                    $estatusEtiqueta = FvdAdminService::atletasEstatusEtiqueta($estatusVal);
                    $estatusOpciones = [
                        FvdAdminService::ATLETA_ESTATUS_PENDIENTE => 'Pendiente',
                        FvdAdminService::ATLETA_ESTATUS_ACTIVO  => 'Activo',
                    ];
                    if (!array_key_exists($estatusVal, $estatusOpciones)) {
                        $estatusOpciones[$estatusVal] = $estatusEtiqueta;
                    }
                    ?>
                    <?php if ($isFvdAdmin && $isEdit): ?>
                        <label for="estatus">Estatus</label>
                        <select class="fvd-input" id="estatus" name="estatus" style="max-width:none">
                            <?php foreach ($estatusOpciones as $ev => $elab): ?>
                                <option value="<?= (int) $ev ?>" <?= $estatusVal === (int) $ev ? 'selected' : '' ?>><?= htmlspecialchars($elab, ENT_QUOTES, 'UTF-8') ?></option>
                            <?php endforeach; ?>
                        </select>
                    <?php else: ?>
                        <label for="fvd_estatus_ver">Estatus</label>
                        <input type="hidden" name="estatus" value="<?= (int) $estatusVal ?>">
                        <input class="fvd-input" type="text" id="fvd_estatus_ver" readonly value="<?= htmlspecialchars($estatusEtiqueta, ENT_QUOTES, 'UTF-8') ?>" autocomplete="off">
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($isEdit): ?>
            <div class="fvd-atleta-form__edit-block">
                <h2>Registro FVD (edición)</h2>
                <div class="fvd-atleta-form__row--fechas-fvd">
                    <div class="fvd-atleta-form__date-fvd-wrap">
                        <label for="fechfvd">Fecha FVD</label>
                        <input class="fvd-input" type="date" id="fechfvd" name="fechfvd" value="<?= htmlspecialchars($fechfvd, ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="fvd-atleta-form__date-fvd-wrap">
                        <label for="fechact">Fecha act.</label>
                        <input class="fvd-input" type="date" id="fechact" name="fechact" value="<?= htmlspecialchars($fechact, ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="fvd-atleta-form__numfvd">
                        <span>Nº FVD</span><br>
                        <?php if ($numfvdVal > 0): ?>
                            <strong><?= (int) $numfvdVal ?></strong>
                        <?php else: ?>
                            <strong>—</strong>
                            <div style="font-size:0.6875rem;margin-top:2px;opacity:0.9;line-height:1.2">Pendiente asignación</div>
                        <?php endif; ?>
                    </div>
                    <div class="fvd-atleta-form__categ-side" id="fvd_categ_hint">
                        <span style="display:block;font-size:0.6875rem;color:var(--fvd-muted);margin-bottom:0.15rem">Categoría (edad)</span>
                        <p class="fvd-atleta-form__categ-hint"><span id="fvd_categ_hint_txt"><?= htmlspecialchars($categEtiqueta, ENT_QUOTES, 'UTF-8') ?></span></p>
                    </div>
                </div>
                <div class="fvd-atleta-form__row fvd-atleta-form__row--5">
                    <div>
                        <label for="afiliacion">Afiliación</label>
                        <input class="fvd-input" type="number" id="afiliacion" name="afiliacion" value="<?= htmlspecialchars((string) ($r['afiliacion'] ?? '0'), ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div>
                        <label for="anualidad">Anualidad</label>
                        <input class="fvd-input" type="number" id="anualidad" name="anualidad" value="<?= htmlspecialchars((string) ($r['anualidad'] ?? '0'), ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div>
                        <label for="carnet">Carnet</label>
                        <input class="fvd-input" type="number" id="carnet" name="carnet" value="<?= htmlspecialchars((string) ($r['carnet'] ?? '0'), ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div>
                        <label for="traspaso">Traspaso</label>
                        <input class="fvd-input" type="number" id="traspaso" name="traspaso" value="<?= htmlspecialchars((string) ($r['traspaso'] ?? '0'), ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div>
                        <label for="inscripcion">Inscripción</label>
                        <input class="fvd-input" type="number" id="inscripcion" name="inscripcion" value="<?= htmlspecialchars((string) ($r['inscripcion'] ?? '0'), ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <div class="fvd-atleta-form__photo-col" aria-label="Fotos carnet y documento">
            <div class="fvd-atleta-form__foto-file">
                <label for="foto">Archivo de foto del atleta (carnet)</label>
                <input class="fvd-input" type="file" id="foto" name="foto" accept="image/*">
            </div>
            <div class="fvd-atleta-form__foto-box">
                <div class="fvd-atleta-form__preview" id="fvd_preview_foto">
                    <?php if ($isEdit && $fotoUrl !== ''): ?>
                        <img src="<?= htmlspecialchars($fotoUrl, ENT_QUOTES, 'UTF-8') ?>" alt="Foto actual" width="156" height="156" style="object-fit:cover;border-radius:6px;max-width:100%">
                    <?php else: ?>
                        <span style="font-size:0.75rem;color:var(--fvd-muted)">Vista previa foto carnet</span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="fvd-atleta-form__cedula-in-side fvd-atleta-form__cedula-block">
                <label for="cedula_img">Archivo imagen de cédula</label>
                <input class="fvd-input" type="file" id="cedula_img" name="cedula_img" accept="image/*" style="padding:4px">
                <div class="fvd-atleta-form__preview fvd-atleta-form__preview--cedula-full" id="fvd_preview_cedula">
                    <?php if ($isEdit && $cedulaImgUrl !== ''): ?>
                        <img src="<?= htmlspecialchars($cedulaImgUrl, ENT_QUOTES, 'UTF-8') ?>" alt="Cédula actual" style="border-radius:6px;object-fit:contain">
                    <?php else: ?>
                        <span style="font-size:0.75rem;color:var(--fvd-muted)">Vista previa imagen de cédula</span>
                    <?php endif; ?>
                </div>
            </div>
            <?php if (!$isEdit): ?>
            <div class="fvd-atleta-form__numfvd-categ-row">
                <div class="fvd-atleta-form__numfvd">
                    <span>Nº FVD</span><br>
                    <?php if ($numfvdVal > 0): ?>
                        <strong><?= (int) $numfvdVal ?></strong>
                    <?php else: ?>
                        <strong>—</strong>
                        <div style="font-size:0.6875rem;margin-top:2px;opacity:0.9;line-height:1.2">Pendiente asignación</div>
                    <?php endif; ?>
                </div>
                <div class="fvd-atleta-form__categ-side" id="fvd_categ_hint">
                    <span style="display:block;font-size:0.6875rem;color:var(--fvd-muted);margin-bottom:0.15rem">Categoría (edad)</span>
                    <p class="fvd-atleta-form__categ-hint"><span id="fvd_categ_hint_txt"><?= htmlspecialchars($categEtiqueta, ENT_QUOTES, 'UTF-8') ?></span></p>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($mostrarAprobacionFvd): ?>
    <div class="fvd-atleta-form__aprob">
        <p><strong>Aprobación administrador general</strong> — Marque ambas opciones y guarde para asignar el consecutivo <code>MAX(numfvd)+1</code> como Nº FVD, fijar <strong>afiliación, carnet y anualidad</strong> en 1, y pasar a <strong>Activo</strong>.</p>
        <label>
            <input type="checkbox" name="fvd_visto_bueno" value="1">
            <span>Visto bueno del registro del atleta</span>
        </label>
        <label>
            <input type="checkbox" name="fvd_asignar_numfvd" value="1">
            <span>Asignar número FVD (consecutivo) y activar</span>
        </label>
    </div>
    <?php endif; ?>

    <div class="fvd-mod-actions fvd-atleta-form__actions">
        <button type="submit">Guardar</button>
        <a href="<?= htmlspecialchars($selfUrl . '?action=list', ENT_QUOTES, 'UTF-8') ?>">Volver al listado</a>
    </div>
</form>
</div>

<script src="<?= htmlspecialchars(url('assets/js/file-preview.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<script>
(function () {
    if (typeof window.filePreview === 'undefined') return;
    window.filePreview.init('foto', 'fvd_preview_foto', 'image', { previewSize: 180 });
    window.filePreview.init('cedula_img', 'fvd_preview_cedula', 'image', { previewSize: 280 });
})();

function fvdCategoriaEtiquetaDesdeFechnac(v) {
    if (!v) return '— (indique fecha de nac.)';
    var p = v.split('-');
    if (p.length !== 3) return '—';
    var y = parseInt(p[0], 10), m = parseInt(p[1], 10) - 1, d = parseInt(p[2], 10);
    var born = new Date(y, m, d);
    var today = new Date();
    today.setHours(0, 0, 0, 0);
    born.setHours(0, 0, 0, 0);
    if (born > today) return '—';
    var age = today.getFullYear() - born.getFullYear();
    var md = today.getMonth() - born.getMonth();
    if (md < 0 || (md === 0 && today.getDate() < born.getDate())) age--;
    if (age >= 18) return 'LIBRE';
    if (age >= 15) return 'SUB 18';
    if (age >= 12) return 'SUB 15';
    return 'SUB 12';
}
var fechnacEl = document.getElementById('fechnac');
var hintTxt = document.getElementById('fvd_categ_hint_txt');
if (fechnacEl && hintTxt) {
    fechnacEl.addEventListener('change', function () {
        hintTxt.textContent = fvdCategoriaEtiquetaDesdeFechnac(this.value);
    });
}
<?php if (!$isEdit): ?>
(function () {
    var ced = document.getElementById('cedula');
    var msg = document.getElementById('fvd_cedula_lookup_msg');
    var lookupBase = <?= json_encode($fvdCedulaLookupBase, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    if (!ced || !msg || !lookupBase) return;
    var debounce = null;
    var lastRedirect = false;
    function clearMsg() {
        msg.textContent = '';
        msg.className = 'fvd-cedula-msg';
    }
    function runLookup() {
        if (lastRedirect) return;
        var v = String(ced.value || '').trim();
        clearMsg();
        if (v.length < 3) return;
        msg.textContent = 'Buscando…';
        fetch(lookupBase + '&cedula=' + encodeURIComponent(v), {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' }
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data || !data.found) {
                    clearMsg();
                    return;
                }
                var nom = data.nombre ? String(data.nombre) : '';
                msg.textContent = 'Atleta encontrado' + (nom ? ': ' + nom : '') + '. Abriendo ficha…';
                msg.className = 'fvd-cedula-msg fvd-cedula-msg--ok';
                lastRedirect = true;
                if (data.redirect) {
                    window.location.href = data.redirect;
                }
            })
            .catch(function () {
                msg.textContent = 'No se pudo comprobar la cédula. Intente de nuevo.';
                msg.className = 'fvd-cedula-msg fvd-cedula-msg--err';
            });
    }
    ced.addEventListener('blur', runLookup);
    ced.addEventListener('input', function () {
        lastRedirect = false;
        clearTimeout(debounce);
        debounce = setTimeout(runLookup, 700);
    });
})();
<?php endif; ?>
</script>
