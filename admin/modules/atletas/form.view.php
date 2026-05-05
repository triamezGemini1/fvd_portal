<?php
/** @var ?array $row */
/** @var list<array<string,mixed>> $asociaciones */
/** @var string $selfUrl */

declare(strict_types=1);

$fvd_form_embed = $fvd_form_embed ?? false;

$r = $row ?? [];
/** Edición solo si hay fila persistida con id > 0 (el repoblar tras error de alta no trae id). */
$idForm = isset($r['id']) ? (int) $r['id'] : 0;
$isEdit = $idForm > 0;
$isFvdAdmin = AuthService::role() === AuthService::ROLE_FVD_ADMIN;
$fvdEsDelegadoFormToolbar = AuthService::role() === AuthService::ROLE_DELEGADO_ASOC;
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
.fvd-cedula-hint { font-size: 0.75rem; color: var(--fvd-muted); margin: 0.35rem 0 0; line-height: 1.35; }
/* Registro existente para la misma cédula: mismo lenguaje visual que el formulario (sin paso extra ni enlaces). */
.fvd-atleta-form__ced-existe-wrap {
    margin: 0 0 0.65rem;
    padding: 0.65rem 0.75rem;
    border-radius: 8px;
    border: 1px solid rgba(250, 204, 21, 0.65);
    background: rgba(255, 255, 255, 0.08);
    box-sizing: border-box;
}
.fvd-atleta-form__ced-existe-wrap[hidden] { display: none !important; }
.fvd-atleta-form__ced-existe__lead {
    margin: 0 0 0.5rem;
    font-size: 0.8125rem;
    font-weight: 800;
    color: var(--fvd-amarillo, #facc15);
    line-height: 1.35;
}
.fvd-atleta-form__ced-existe__grid {
    display: grid;
    grid-template-columns: minmax(5.5rem, auto) 1fr;
    gap: 0.35rem 0.75rem;
    font-size: 0.8125rem;
    align-items: baseline;
}
.fvd-atleta-form__ced-existe__grid dt {
    margin: 0;
    font-weight: 700;
    color: rgba(255, 255, 255, 0.88);
}
.fvd-atleta-form__ced-existe__grid dd {
    margin: 0;
    font-weight: 700;
    color: #0f172a;
    background: #fff;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    padding: 0.28rem 0.45rem;
    line-height: 1.35;
}
.fvd-atleta-form__ced-existe__grid dd.fvd-atleta-form__ced-existe__est {
    background: #fef9c3;
    border-color: #eab308;
    font-weight: 800;
}
.fvd-atleta-form__ced-existe__scope {
    margin: 0.45rem 0 0;
    font-size: 0.75rem;
    line-height: 1.4;
    color: rgba(255, 255, 255, 0.92);
}
/* Contraste alto para el formulario de afiliación */
.fvd-atleta-form-page .fvd-atleta-form.fvd-atleta-form--framed {
    background: #003366;
    border-color: #cbd5e1;
    box-shadow: 0 10px 22px rgba(15, 23, 42, 0.12);
}
.fvd-atleta-form label {
    font-weight: 700;
    color: #ffffff !important;
}
.fvd-atleta-form .fvd-input,
.fvd-atleta-form select.fvd-input,
.fvd-atleta-form input.fvd-input,
.fvd-atleta-form textarea.fvd-input {
    background: #ffffff;
    border: 1px solid #cbd5e1;
    color: #0f172a;
}
.fvd-atleta-form .fvd-input:focus,
.fvd-atleta-form select.fvd-input:focus,
.fvd-atleta-form input.fvd-input:focus,
.fvd-atleta-form textarea.fvd-input:focus {
    border-color: #2563eb;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.2);
    outline: 0;
}
.fvd-atleta-form__actions.fvd-mod-actions button[type="submit"] {
    background: #003366;
    color: #ffffff;
    border: 1px solid #003366;
    font-weight: 800;
}
.fvd-atleta-form__actions.fvd-mod-actions button[type="submit"]:hover {
    background: #00264d;
}
.fvd-form-back-link {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    margin: 0 0 .65rem;
    text-decoration: none;
    color: #475569;
    font-weight: 700;
}
.fvd-form-back-link:hover { color: #1e293b; }
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
if (!function_exists('fvd_return_preserve_query_params')) {
    require_once FVD_PROJECT_ROOT . '/config/paths.php';
}
$fvdFormAction = $selfUrl . '?action=form' . ($isEdit ? '&id=' . $idForm : '');
if ($fvd_form_embed) {
    $fvdFormAction .= '&embed=1';
}
$fvdFormAction = fvd_return_preserve_query_params($fvdFormAction);
$fvdListQuery = ['action' => 'list'];
if (AuthService::isDelegadoAsociacion()) {
    $fvdListMineAsoc = (int) (AuthService::idAsociacion() ?? 0);
    if ($fvdListMineAsoc > 0) {
        $fvdListQuery['alcance'] = 'asociacion';
        $fvdListQuery['asociacion_id'] = $fvdListMineAsoc;
    }
}
$fvdBackUrl = fvd_return_preserve_query_params($selfUrl . '?' . http_build_query($fvdListQuery));
if ($isEdit) {
    if (isset($_GET['ret']) && is_string($_GET['ret']) && fvd_return_sanitize($_GET['ret']) !== null) {
        $fvdBackUrl = (string) $_GET['ret'];
    } elseif (isset($_GET['return']) && is_string($_GET['return']) && fvd_return_sanitize($_GET['return']) !== null) {
        $fvdBackUrl = (string) $_GET['return'];
    }
}
$fvdFormTopBackLabel = $isEdit
    ? ($isFvdAdmin ? '← Volver al listado' : ($fvdEsDelegadoFormToolbar ? '← Volver al panel' : '← Volver al listado'))
    : '← Cancelar';
$fvdFormBottomBackLabel = $isEdit ? 'Volver al listado' : 'Cancelar';
$fvdFormRetornoUrl = '';
if (isset($_GET['ret']) && is_string($_GET['ret']) && fvd_return_sanitize($_GET['ret']) !== null) {
    $fvdFormRetornoUrl = (string) $_GET['ret'];
} elseif (isset($_GET['return']) && is_string($_GET['return']) && fvd_return_sanitize($_GET['return']) !== null) {
    $fvdFormRetornoUrl = (string) $_GET['return'];
} elseif ($fvdEsDelegadoFormToolbar) {
    $fvdFormRetornoUrl = AuthService::homeUrl();
}
$fvdToolbarCedula = isset($_GET['cedula']) && is_string($_GET['cedula']) ? trim($_GET['cedula']) : '';
$fvdToolbarNombre = isset($_GET['q']) && is_string($_GET['q']) ? trim($_GET['q']) : '';
$fvdToolbarAsocId = 0;
$lfToolbar = null;
if ($isFvdAdmin) {
    if (!function_exists('fvd_atletas_resolve_list_filters')) {
        require_once __DIR__ . '/list_filters.inc.php';
    }
    $lfToolbar = fvd_atletas_resolve_list_filters($_GET);
    $fvdToolbarAsocId = (int) ($lfToolbar['asociacion_id'] ?? 0);
}
$fvdToolbarTipo = isset($_GET['tipo']) && is_string($_GET['tipo']) ? trim((string) $_GET['tipo']) : '';
if (!in_array($fvdToolbarTipo, ['normal', 'ultimos', 'no_activos', 'bajas'], true)) {
    $fvdToolbarTipo = ($isFvdAdmin && is_array($lfToolbar))
        ? (string) ($lfToolbar['tipo'] ?? 'ultimos')
        : 'normal';
}
?>
<div class="fvd-atleta-form-page<?= !empty($fvd_form_embed) ? ' fvd-atleta-form-page--embed' : '' ?>">
<?php if (!$fvd_form_embed): ?>
<a class="fvd-form-back-link btn-link text-slate-600" href="<?= htmlspecialchars($fvdBackUrl, ENT_QUOTES, 'UTF-8') ?>"<?= !$isEdit ? ' title="Regresar al listado de afiliaciones sin guardar"' : '' ?>><?= htmlspecialchars($fvdFormTopBackLabel, ENT_QUOTES, 'UTF-8') ?></a>
<?php endif; ?>
<?php if (!empty($fvd_form_embed) && !$isEdit): ?>
<div class="fvd-atleta-form-embed-cancel no-print" style="margin:0 0 10px">
    <a class="fvd-form-back-link btn-link text-slate-600" href="<?= htmlspecialchars($fvdBackUrl, ENT_QUOTES, 'UTF-8') ?>" title="Regresar al listado de afiliaciones sin guardar"><?= htmlspecialchars($fvdFormTopBackLabel, ENT_QUOTES, 'UTF-8') ?></a>
</div>
<?php endif; ?>
<?php
$fvdMasterEmbedUi = function_exists('fvd_master_embed_active') && fvd_master_embed_active();
$fvdHideFormPageTitle = $fvdMasterEmbedUi
    || (function_exists('fvd_delegado_inner_heading_visible') && !fvd_delegado_inner_heading_visible());
?>
<?php if (!empty($fvd_error ?? '')): ?>
<div class="fvd-mod-msg fvd-atleta-form__flash-err" role="alert" style="margin:0 0 12px;padding:12px 14px;border-radius:10px;border:1px solid #b45309;background:rgba(254,215,170,.25);font-size:.875rem;line-height:1.45;color:#0f172a">
    <?= htmlspecialchars((string) $fvd_error, ENT_QUOTES, 'UTF-8') ?>
</div>
<?php endif; ?>
<?php if (!$fvdHideFormPageTitle): ?>
<h1 class="fvd-atleta-form-page__title"><?= $isEdit ? 'Editar atleta' : 'Nuevo atleta' ?></h1>
<?php endif; ?>
<?php if (!$fvd_form_embed && $isEdit): ?>
<div class="fvd-mod-toolbar fvd-atletas-toolbar-unified no-print" style="flex-wrap:wrap;align-items:flex-end;gap:10px;padding:12px 14px;border-radius:12px;border:1px solid rgba(46,48,146,.28);border-top:3px solid #fff200;background:#f8fafc;box-shadow:0 8px 18px rgba(15,23,42,.12);margin:0 0 12px">
    <form method="get" action="" class="no-print" id="fvd-atletas-filter-form-formpage" style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;padding:10px;border-radius:10px;border:1px solid rgba(46,48,146,.22);background:#ffffff">
        <input type="hidden" name="action" value="list">
        <?php if (function_exists('fvd_master_panel_render_context_hiddens')) {
            fvd_master_panel_render_context_hiddens();
        } ?>
        <?php
        $fvdFormToolbarIncluirEmbed = (\AuthService::role() === \AuthService::ROLE_FVD_ADMIN)
            || (function_exists('fvd_master_embed_active') && fvd_master_embed_active());
        if ($fvdFormToolbarIncluirEmbed) : ?>
        <input type="hidden" name="embedded" value="1">
        <input type="hidden" name="fvd_master_embed" value="1">
        <?php endif; ?>
        <?php if (isset($_GET['torneo_id']) && (int) $_GET['torneo_id'] > 0) : ?>
        <input type="hidden" name="torneo_id" value="<?= (int) $_GET['torneo_id'] ?>">
        <?php endif; ?>
        <?php if (isset($_GET['campeonato_id']) && (int) $_GET['campeonato_id'] > 0) : ?>
        <input type="hidden" name="campeonato_id" value="<?= (int) $_GET['campeonato_id'] ?>">
        <?php endif; ?>
        <?php if ($isFvdAdmin): ?>
        <input type="hidden" name="tipo" value="<?= htmlspecialchars($fvdToolbarTipo, ENT_QUOTES, 'UTF-8') ?>">
        <div>
            <label style="font-size:.8125rem;color:#000;display:block;font-weight:700">Asociación</label>
            <select id="fvd-atletas-asoc-id-formtoolbar" class="fvd-input" name="asociacion_id" style="min-width:min(11rem,32vw);max-width:15rem" aria-label="Asociación o FVD — todas">
                <option value="0"<?= $fvdToolbarAsocId <= 0 ? ' selected' : '' ?>>FVD — todas</option>
                <?php foreach ($asociaciones as $aso): ?>
                    <?php
                    $aidOpt = (int) ($aso['id'] ?? 0);
                    if ($aidOpt <= 0) {
                        continue;
                    }
                    ?>
                    <option value="<?= $aidOpt ?>"<?= $fvdToolbarAsocId === $aidOpt ? ' selected' : '' ?> title="<?= htmlspecialchars((string) ($aso['nombre'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) ($aso['nombre'] ?? ''), ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php else: ?>
        <input type="hidden" name="alcance" value="todos">
        <div>
            <label style="font-size:.8125rem;color:#000;display:block;font-weight:700">Tipo de listado</label>
            <select id="fvd-atletas-tipo-form" class="fvd-input" name="tipo" style="max-width:16rem">
                <option value="normal"<?= $fvdToolbarTipo === 'normal' ? ' selected' : '' ?>>Listado general</option>
                <option value="ultimos"<?= $fvdToolbarTipo === 'ultimos' ? ' selected' : '' ?>>Últimos afiliados</option>
                <option value="no_activos"<?= $fvdToolbarTipo === 'no_activos' ? ' selected' : '' ?>>No activos (pendientes)</option>
                <option value="bajas"<?= $fvdToolbarTipo === 'bajas' ? ' selected' : '' ?>>Dados de baja</option>
            </select>
        </div>
        <?php endif; ?>
        <div>
            <label style="font-size:.8125rem;color:#000;display:block;font-weight:700">Cédula</label>
            <input id="fvd-atleta-cedula-form" class="fvd-input" type="search" name="cedula" value="<?= htmlspecialchars($fvdToolbarCedula, ENT_QUOTES, 'UTF-8') ?>" placeholder="Ej. 30399011" style="max-width:11rem" autocomplete="off">
        </div>
        <div>
            <label style="font-size:.8125rem;color:#000;display:block;font-weight:700">Nombre</label>
            <input id="fvd-atleta-q-form" class="fvd-input" type="search" name="q" value="<?= htmlspecialchars($fvdToolbarNombre, ENT_QUOTES, 'UTF-8') ?>" placeholder="Contiene…" style="max-width:12rem">
        </div>
        <button type="submit" class="fvd-input" style="width:auto;padding:6px 12px">Buscar</button>
        <a href="<?= htmlspecialchars(function_exists('fvd_return_preserve_query_params') ? fvd_return_preserve_query_params($selfUrl . '?action=list') : ($selfUrl . '?action=list'), ENT_QUOTES, 'UTF-8') ?>" class="fvd-input" style="width:auto;padding:6px 12px;display:inline-flex;align-items:center;text-decoration:none;box-sizing:border-box">Limpiar</a>
    </form>
    <?php if ($fvdFormRetornoUrl !== ''): ?>
    <a href="<?= htmlspecialchars($fvdFormRetornoUrl, ENT_QUOTES, 'UTF-8') ?>" class="fvd-input no-print" style="width:auto;padding:8px 12px;display:inline-flex;align-items:center;text-decoration:none;box-sizing:border-box;border-color:#000;background:#fff;color:#000;font-weight:800">
        ← Retornar
    </a>
    <?php endif; ?>
    <div class="fvd-atletas-export no-print" role="group" aria-label="Exportar listado">
        <span class="fvd-atletas-export__label" style="font-size:.75rem;color:#000;display:block;margin-bottom:4px;font-weight:700">Exportar</span>
        <div style="display:flex;flex-wrap:wrap;gap:8px;align-items:center">
            <a href="<?= htmlspecialchars(function_exists('fvd_return_preserve_query_params') ? fvd_return_preserve_query_params($selfUrl . '?action=list&format=csv') : ($selfUrl . '?action=list&format=csv'), ENT_QUOTES, 'UTF-8') ?>" class="fvd-input" style="width:auto;padding:6px 12px;display:inline-flex;align-items:center;text-decoration:none;box-sizing:border-box">Excel (CSV)</a>
            <a href="<?= htmlspecialchars(function_exists('fvd_return_preserve_query_params') ? fvd_return_preserve_query_params($selfUrl . '?action=list&format=pdf') : ($selfUrl . '?action=list&format=pdf'), ENT_QUOTES, 'UTF-8') ?>" class="fvd-input" style="width:auto;padding:6px 12px;display:inline-flex;align-items:center;text-decoration:none;box-sizing:border-box">PDF</a>
        </div>
    </div>
    <a href="<?= htmlspecialchars(function_exists('fvd_return_append_to_url') ? fvd_return_append_to_url($selfUrl . '?action=form') : ($selfUrl . '?action=form'), ENT_QUOTES, 'UTF-8') ?>" class="fvd-btn-primary no-print" style="text-decoration:none;box-sizing:border-box;display:inline-flex;align-items:center;justify-content:center">Nuevo atleta</a>
</div>
<?php endif; ?>
<form class="fvd-atleta-form fvd-atleta-form--framed" method="post" enctype="multipart/form-data" action="<?= htmlspecialchars($fvdFormAction, ENT_QUOTES, 'UTF-8') ?>"<?= $fvd_form_embed ? ' target="_parent"' : '' ?>>
    <input type="hidden" name="_action" value="save">
    <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= $idForm ?>"><?php endif; ?>
    <?php if (isset($_GET['ret']) && is_string($_GET['ret']) && fvd_return_sanitize($_GET['ret']) !== null): ?>
    <input type="hidden" name="ret" value="<?= htmlspecialchars($_GET['ret'], ENT_QUOTES, 'UTF-8') ?>">
    <?php elseif (isset($_GET['return']) && is_string($_GET['return']) && fvd_return_sanitize($_GET['return']) !== null): ?>
    <input type="hidden" name="return" value="<?= htmlspecialchars($_GET['return'], ENT_QUOTES, 'UTF-8') ?>">
    <?php endif; ?>
    <?php if (function_exists('fvd_master_panel_render_context_hiddens')) {
        fvd_master_panel_render_context_hiddens();
    } ?>

    <div class="fvd-atleta-form__top-grid<?= $isFvdAdmin ? '' : ' fvd-atleta-form__top-grid--scoped-asoc' ?>">
        <div class="fvd-atleta-form__asoc fvd-atleta-form__asoc--grid-full">
            <label for="asociacion">Asociación</label>
            <select class="fvd-input" id="asociacion" name="<?= $isFvdAdmin ? 'asociacion' : 'asociacion_view' ?>" required<?= $isFvdAdmin ? '' : ' disabled' ?>>
                <option value="">— Asociación —</option>
                <?php foreach ($asociaciones as $a): ?>
                    <?php
                    $aId = (int) $a['id'];
                    $selAsoc = $isFvdAdmin
                        ? ((int) ($r['asociacion'] ?? 0) === $aId)
                        : ((int) $asocHiddenVal === $aId);
                    ?>
                    <option value="<?= $aId ?>" <?= $selAsoc ? 'selected' : '' ?>><?= htmlspecialchars((string) $a['nombre'], ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
            </select>
            <?php if (!$isFvdAdmin): ?>
            <input type="hidden" name="asociacion" value="<?= (int) $asocHiddenVal ?>">
            <?php endif; ?>
        </div>

        <div class="fvd-atleta-form__fields-col">
            <div class="fvd-atleta-form__row fvd-atleta-form__row--cednom">
                <div class="fvd-atleta-form__ced">
                    <label for="cedula">Cédula</label>
                    <input class="fvd-input" id="cedula" name="cedula" required value="<?= htmlspecialchars((string) ($r['cedula'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" autocomplete="off">
                    <?php if (!$isEdit): ?>
                    <p id="fvd_cedula_lookup_msg" class="fvd-cedula-msg" role="status" aria-live="polite"></p>
                    <?php endif; ?>
                </div>
                <div class="fvd-atleta-form__nom">
                    <label for="nombre">Nombre</label>
                    <input class="fvd-input" id="nombre" name="nombre" required value="<?= htmlspecialchars((string) ($r['nombre'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                </div>
            </div>
            <?php if (!$isEdit): ?>
            <div id="fvd_cedula_existe_wrap" class="fvd-atleta-form__ced-existe-wrap" hidden></div>
            <?php endif; ?>

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
                    $estatusEtiqueta = FvdAdminService::atletasEstatusEtiqueta($estatusVal, isset($r['numfvd']) ? (int) $r['numfvd'] : null);
                    $estatusOpciones = [
                        FvdAdminService::ATLETA_ESTATUS_PENDIENTE => 'Pendiente',
                        FvdAdminService::ATLETA_ESTATUS_ACTIVO  => 'Activo',
                    ];
                    if (!array_key_exists($estatusVal, $estatusOpciones)) {
                        $estatusOpciones[$estatusVal] = $estatusEtiqueta;
                    }
                    ?>
                    <label for="estatus">Estatus</label>
                    <select class="fvd-input" id="estatus" name="<?= ($isFvdAdmin && $isEdit) ? 'estatus' : 'estatus_view' ?>" style="max-width:none"<?= ($isFvdAdmin && $isEdit) ? '' : ' disabled' ?>>
                        <?php foreach ($estatusOpciones as $ev => $elab): ?>
                            <option value="<?= (int) $ev ?>" <?= $estatusVal === (int) $ev ? 'selected' : '' ?>><?= htmlspecialchars($elab, ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (!($isFvdAdmin && $isEdit)): ?>
                    <input type="hidden" name="estatus" value="<?= (int) $estatusVal ?>">
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
        <a href="<?= htmlspecialchars($fvdBackUrl, ENT_QUOTES, 'UTF-8') ?>"<?= !$isEdit ? ' title="Regresar al listado de afiliaciones sin guardar"' : '' ?>><?= htmlspecialchars($fvdFormBottomBackLabel, ENT_QUOTES, 'UTF-8') ?></a>
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
    var wrap = document.getElementById('fvd_cedula_existe_wrap');
    var lookupBase = <?= json_encode($fvdCedulaLookupBase, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    if (!ced || !lookupBase) return;
    var debounce = null;
    function clearUi() {
        if (msg) {
            msg.textContent = '';
            msg.className = 'fvd-cedula-msg';
        }
        if (wrap) {
            wrap.innerHTML = '';
            wrap.setAttribute('hidden', 'hidden');
        }
    }
    function esc(s) {
        var d = document.createElement('div');
        d.textContent = s == null ? '' : String(s);
        return d.innerHTML;
    }
    function runLookup() {
        var v = String(ced.value || '').trim();
        clearUi();
        if (v.length < 3) return;
        if (msg) {
            msg.textContent = 'Comprobando cédula…';
        }
        fetch(lookupBase + '&cedula=' + encodeURIComponent(v), {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' }
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (msg) {
                    msg.textContent = '';
                    msg.className = 'fvd-cedula-msg';
                }
                if (!data || !data.found) {
                    return;
                }
                var nom = data.nombre ? String(data.nombre) : '—';
                var cedR = data.cedula ? String(data.cedula) : v;
                var nf = data.numfvd != null && parseInt(data.numfvd, 10) > 0 ? String(parseInt(data.numfvd, 10)) : '— (pendiente)';
                var est = data.estatus_etiqueta ? String(data.estatus_etiqueta) : '—';
                var asoc = data.asociacion_nombre ? String(data.asociacion_nombre) : '—';
                var scopeNote = data.scope_notice ? String(data.scope_notice) : '';
                var sol = data.solicitud_pendiente;
                var solTipo = '';
                if (sol && sol.tipo) {
                    var tm = { afiliacion: 'Afiliación', carnet: 'Carnet / afiliación', traspaso: 'Traspaso' };
                    solTipo = tm[sol.tipo] || String(sol.tipo);
                }
                var solExtra = '';
                if (sol && sol.id) {
                    solExtra = '<dt>Solicitud delegado (pend.)</dt><dd>#' + esc(String(sol.id)) + (solTipo ? ' — ' + esc(solTipo) : '');
                    if (sol.creado_en) { solExtra += ' <span class="fvd-atleta-form__ced-existe__meta">(' + esc(String(sol.creado_en)) + ')</span>'; }
                    solExtra += '</dd>';
                    if (sol.nota) { solExtra += '<dt>Nota solicitud</dt><dd>' + esc(String(sol.nota)) + '</dd>'; }
                }
                if (data.alta_desde_delegado && parseInt(data.alta_desde_delegado, 10) === 1 && !sol) {
                    solExtra += '<dt>Alta delegado</dt><dd>Marcada como alta desde delegado (pendiente de validación).</dd>';
                }
                if (wrap) {
                    wrap.innerHTML = '<p class="fvd-atleta-form__ced-existe__lead">Registro existente para esta cédula</p>'
                        + '<dl class="fvd-atleta-form__ced-existe__grid" role="group" aria-label="Datos del atleta ya registrado">'
                        + '<dt>Cédula en sistema</dt><dd>' + esc(cedR) + '</dd>'
                        + '<dt>Nombre</dt><dd>' + esc(nom) + '</dd>'
                        + '<dt>Estatus</dt><dd class="fvd-atleta-form__ced-existe__est">' + esc(est) + '</dd>'
                        + '<dt>Nº FVD</dt><dd>' + esc(nf) + '</dd>'
                        + '<dt>Asociación</dt><dd>' + esc(asoc) + '</dd>'
                        + solExtra
                        + '</dl>'
                        + (scopeNote ? '<p class="fvd-atleta-form__ced-existe__scope">' + esc(scopeNote) + '</p>' : '');
                    wrap.removeAttribute('hidden');
                }
            })
            .catch(function () {
                if (msg) {
                    msg.textContent = 'No se pudo comprobar la cédula. Intente de nuevo.';
                    msg.className = 'fvd-cedula-msg fvd-cedula-msg--err';
                }
            });
    }
    ced.addEventListener('blur', runLookup);
    ced.addEventListener('input', function () {
        clearTimeout(debounce);
        debounce = setTimeout(runLookup, 450);
    });
    if (String(ced.value || '').trim().length >= 3) {
        runLookup();
    }
})();
<?php endif; ?>
</script>
