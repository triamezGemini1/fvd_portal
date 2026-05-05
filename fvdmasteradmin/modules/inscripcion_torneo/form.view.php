<?php
declare(strict_types=1);
/**
 * @var array<string, mixed> $formRow
 * @var int $torneoClase 1|2|3
 * @var int $integrantesEquipo solo clase 3
 * @var bool $isNew
 * @var string $selfUrl
 * @var int $filterT
 * @var int $editId
 * @var list<array<string,mixed>> $asociacionesSelect
 * @var string $fvd_error
 * @var string $fvd_ok
 * @var string $fvd_form_torneo_nombre
 * @var string $fvd_url_inscripcion_sitio
 */
$fvd_error = isset($fvd_error) ? (string) $fvd_error : '';
$fvd_ok = isset($fvd_ok) ? (string) $fvd_ok : '';
$fvd_form_torneo_nombre = isset($fvd_form_torneo_nombre) ? (string) $fvd_form_torneo_nombre : '';
$fvd_url_inscripcion_sitio = isset($fvd_url_inscripcion_sitio) ? (string) $fvd_url_inscripcion_sitio : '';
$asociacionesSelect = isset($asociacionesSelect) && is_array($asociacionesSelect) ? $asociacionesSelect : [];
$isNew = !empty($isNew);
$editId = isset($editId) ? (int) $editId : 0;
$fvd_sustituir_desde_id = isset($fvd_sustituir_desde_id) ? (int) $fvd_sustituir_desde_id : 0;
$filterT = isset($filterT) ? (int) $filterT : 0;
$torneoClase = isset($torneoClase) ? (int) $torneoClase : 1;
if ($torneoClase !== 2 && $torneoClase !== 3) {
    $torneoClase = 1;
}
$integrantesEquipo = isset($integrantesEquipo) ? (int) $integrantesEquipo : 4;
$formRow = is_array($formRow ?? null) ? $formRow : [];

$esFvd = AuthService::role() === AuthService::ROLE_FVD_ADMIN;
$tid = (int) ($formRow['torneo_id'] ?? 0);
$aid = (int) ($formRow['asociacion_id'] ?? 0);
$clEt = $torneoClase === 2 ? 'Parejas' : ($torneoClase === 3 ? 'Equipos' : 'Individual');
$pillMod = $torneoClase === 2 ? 'parejas' : ($torneoClase === 3 ? 'equipos' : 'individual');
$backQs = $filterT > 0 ? '?torneo_id=' . $filterT : '';
$backUrl = $selfUrl . $backQs;

$vSexo = (int) ($formRow['sexo'] ?? 0);
$vInsc = (int) ($formRow['inscripcion'] ?? 1);
$neqVal = isset($formRow['nombre_equipo']) && $formRow['nombre_equipo'] !== null
    ? (string) $formRow['nombre_equipo'] : '';
?>
<?php
if (!function_exists('url')) {
    require_once FVD_PROJECT_ROOT . '/config/paths.php';
}
?>
<link rel="stylesheet" href="<?= htmlspecialchars(url('assets/css/fvd-insc-forms-panel.css'), ENT_QUOTES, 'UTF-8') ?>">

<?php if (function_exists('fvd_delegado_inner_heading_visible') && fvd_delegado_inner_heading_visible()): ?>
<h1><?= $fvd_sustituir_desde_id > 0 ? 'Sustituir inscripción' : ($isNew ? 'Nueva inscripción' : 'Editar inscripción') ?></h1>
<?php endif; ?>

<?php if ($fvd_ok !== ''): ?>
    <p class="fvd-mod-msg" style="color:#86efac"><?= htmlspecialchars($fvd_ok, ENT_QUOTES, 'UTF-8') ?></p>
<?php endif; ?>
<?php if ($fvd_error !== ''): ?>
    <p class="fvd-mod-msg"><?= htmlspecialchars($fvd_error, ENT_QUOTES, 'UTF-8') ?></p>
<?php endif; ?>
<?php if ($fvd_sustituir_desde_id > 0): ?>
    <div class="fvd-mtf-alert fvd-mtf-alert--info" style="margin-bottom:12px">
        <p class="fvd-mtf-alert__text"><strong>Sustitución:</strong> complete cédula, nombre y datos del <strong>nuevo</strong> atleta. Al guardar se eliminará la inscripción anterior (ID <?= (int) $fvd_sustituir_desde_id ?>) y se creará la nueva fila; se mantiene el mismo número de equipo y nombre de pareja/equipo salvo que los modifique.</p>
    </div>
<?php endif; ?>

<?php
$fvdInscStatsGen = null;
if ($tid > 0 && $aid > 0 && function_exists('fvd_db')) {
    try {
        $fvdInscStatsGen = \FvdPortal\Services\InscripcionService::estadisticasPorGeneroTorneoAsociacion(fvd_db(), $tid, $aid);
    } catch (Throwable $e) {
        error_log('[inscripcion_torneo form] estadisticasPorGenero: ' . $e->getMessage());
    }
}
$fvdInscTorneosChips = [];
$fvdInscCtxTorneos = [];
$fvdInscEsDelegado = false;
$fvdInscEsFvdAdmin = $esFvd;
$fvdInscAsocIdQuery = $aid;
$fvdInscCampeonatoId = 0;
$fvdInscSelfUrl = $selfUrl;
$fvdInscFilterTorneoId = $tid;
$fvdInscTorneoNombre = $fvd_form_torneo_nombre;
require __DIR__ . '/partial_insc_ctx_estadisticas.php';
?>

<nav class="fvd-mod-toolbar" style="margin-bottom:12px;font-size:.8125rem;gap:10px;flex-wrap:wrap">
    <a href="<?= htmlspecialchars($backUrl, ENT_QUOTES, 'UTF-8') ?>" class="fvd-mtf-btn fvd-mtf-btn--secondary">← Volver al listado</a>
    <?php if ($fvd_url_inscripcion_sitio !== ''): ?>
        <a href="<?= htmlspecialchars($fvd_url_inscripcion_sitio, ENT_QUOTES, 'UTF-8') ?>" class="fvd-mtf-btn fvd-mtf-btn--secondary">Inscripción en sitio</a>
    <?php endif; ?>
</nav>

<div class="fvd-mtf-shell">
    <div class="fvd-mtf-card">
        <header class="fvd-mtf-card__head">
            <div class="fvd-mtf-card__head-main">
                <h2 class="fvd-mtf-card__title"><?= htmlspecialchars($fvd_form_torneo_nombre !== '' ? $fvd_form_torneo_nombre : ('Torneo #' . $tid), ENT_QUOTES, 'UTF-8') ?></h2>
                <span class="fvd-mtf-pill<?= $pillMod === 'parejas' ? ' fvd-mtf-pill--parejas' : ($pillMod === 'equipos' ? ' fvd-mtf-pill--equipos' : '') ?>"><?= htmlspecialchars($clEt, ENT_QUOTES, 'UTF-8') ?></span>
            </div>
            <div class="fvd-mtf-card__meta">Tabla <code style="font-size:.72rem">inscripcion_torneo</code></div>
        </header>
        <div class="fvd-mtf-card__body">
            <?php if ($torneoClase === 2): ?>
                <div class="fvd-mtf-alert fvd-mtf-alert--info">
                    <p class="fvd-mtf-alert__text"><strong>Parejas:</strong> el mismo <strong>número de equipo</strong> y <strong>nombre de pareja/equipo</strong> deben repetirse en las dos filas (una por cédula), según el modelo de base de datos.</p>
                </div>
            <?php elseif ($torneoClase === 3): ?>
                <div class="fvd-mtf-alert fvd-mtf-alert--info">
                    <p class="fvd-mtf-alert__text"><strong>Equipos:</strong> reglamento <?= (int) $integrantesEquipo ?> integrantes — una fila por atleta; mismo <strong>equipo</strong> y <strong>nombre_equipo</strong> para todos los integrantes.</p>
                </div>
            <?php else: ?>
                <div class="fvd-mtf-alert fvd-mtf-alert--info">
                    <p class="fvd-mtf-alert__text"><strong>Individual:</strong> una fila por atleta inscrito; <strong>equipo</strong> suele ser 0 salvo migraciones.</p>
                </div>
            <?php endif; ?>

            <form method="post" action="<?= htmlspecialchars($selfUrl . $backQs, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="_action" value="guardar_fila_inscripcion_torneo">
                <?php if (!$isNew): ?>
                    <input type="hidden" name="id" value="<?= $editId ?>">
                <?php endif; ?>
                <?php if ($fvd_sustituir_desde_id > 0): ?>
                    <input type="hidden" name="sustituir_de_id" value="<?= (int) $fvd_sustituir_desde_id ?>">
                <?php endif; ?>
                <input type="hidden" name="torneo_id" value="<?= $tid ?>">

                <div class="fvd-mtf-grid">
                    <?php if ($esFvd): ?>
                        <div>
                            <label class="fvd-mtf-form-label" for="fvd-it-asoc">Asociación *</label>
                            <select class="fvd-input" id="fvd-it-asoc" name="asociacion_id" required style="width:100%">
                                <option value="0">— Elija asociación —</option>
                                <?php foreach ($asociacionesSelect as $a): ?>
                                    <?php $oid = (int) ($a['id'] ?? 0); ?>
                                    <option value="<?= $oid ?>" <?= $aid === $oid ? 'selected' : '' ?>>
                                        <?= htmlspecialchars((string) ($a['nombre'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php else: ?>
                        <input type="hidden" name="asociacion_id" value="<?= $aid ?>">
                        <div>
                            <span class="fvd-mtf-form-label">Asociación</span>
                            <div class="fvd-input" style="background:#f8fafc"><?= (int) $aid ?></div>
                        </div>
                    <?php endif; ?>

                    <div>
                        <label class="fvd-mtf-form-label" for="fvd-it-eq">Nº equipo (grupo)</label>
                        <input class="fvd-input" id="fvd-it-eq" name="equipo" type="number" min="0" step="1"
                               value="<?= (int) ($formRow['equipo'] ?? 0) ?>" style="width:100%">
                    </div>
                    <div>
                        <label class="fvd-mtf-form-label" for="fvd-it-ced">Cédula (numérica) *</label>
                        <input class="fvd-input" id="fvd-it-ced" name="cedula" type="text" inputmode="numeric" required
                               value="<?= htmlspecialchars((string) ($formRow['cedula'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" style="width:100%">
                    </div>
                    <div style="grid-column:1/-1">
                        <label class="fvd-mtf-form-label" for="fvd-it-nom">Nombre *</label>
                        <input class="fvd-input" id="fvd-it-nom" name="nombre" type="text" required maxlength="255"
                               value="<?= htmlspecialchars((string) ($formRow['nombre'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" style="width:100%">
                    </div>
                    <div style="grid-column:1/-1">
                        <label class="fvd-mtf-form-label" for="fvd-it-neq">
                            Nombre pareja / equipo<?= ($torneoClase === 2 || $torneoClase === 3) ? ' *' : '' ?>
                        </label>
                        <input class="fvd-input" id="fvd-it-neq" name="nombre_equipo" type="text" maxlength="255"
                            <?= ($torneoClase === 2 || $torneoClase === 3) ? 'required' : '' ?>
                               value="<?= htmlspecialchars($neqVal, ENT_QUOTES, 'UTF-8') ?>" style="width:100%"
                               placeholder="<?= $torneoClase === 1 ? 'Opcional (individual)' : 'Obligatorio en parejas/equipos' ?>">
                    </div>
                    <div>
                        <label class="fvd-mtf-form-label" for="fvd-it-nf">Nº FVD</label>
                        <input class="fvd-input" id="fvd-it-nf" name="numfvd" type="number" min="0" step="1"
                               value="<?= (int) ($formRow['numfvd'] ?? 0) ?>" style="width:100%">
                    </div>
                    <div>
                        <label class="fvd-mtf-form-label" for="fvd-it-sx">Sexo (BD)</label>
                        <select class="fvd-input" id="fvd-it-sx" name="sexo" style="width:100%">
                            <option value="0" <?= $vSexo === 0 ? 'selected' : '' ?>>0 — no indicado</option>
                            <option value="1" <?= $vSexo === 1 ? 'selected' : '' ?>>1 — M</option>
                            <option value="2" <?= $vSexo === 2 ? 'selected' : '' ?>>2 — F</option>
                        </select>
                    </div>
                    <div>
                        <label class="fvd-mtf-form-label" for="fvd-it-tel">Teléfono</label>
                        <input class="fvd-input" id="fvd-it-tel" name="telefono" type="text" maxlength="20"
                               value="<?= htmlspecialchars((string) ($formRow['telefono'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" style="width:100%">
                    </div>
                    <div>
                        <label class="fvd-mtf-form-label" for="fvd-it-em">Email</label>
                        <input class="fvd-input" id="fvd-it-em" name="email" type="email" maxlength="255"
                               value="<?= htmlspecialchars((string) ($formRow['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" style="width:100%">
                    </div>
                    <div>
                        <label class="fvd-mtf-form-label" for="fvd-it-insc">Canal inscripción</label>
                        <select class="fvd-input" id="fvd-it-insc" name="inscripcion" style="width:100%">
                            <option value="0" <?= $vInsc === 0 ? 'selected' : '' ?>>0</option>
                            <option value="1" <?= $vInsc === 1 ? 'selected' : '' ?>>1 — Sitio / panel</option>
                            <option value="2" <?= $vInsc === 2 ? 'selected' : '' ?>>2 — Movimiento / sincro</option>
                        </select>
                    </div>
                </div>

                <fieldset style="border:1px solid #e2e8f0;border-radius:8px;padding:0.65rem 0.75rem;margin:1rem 0 0;font-size:0.8125rem">
                    <legend style="font-weight:800;font-size:0.72rem;color:#64748b">Flags (0 / 1)</legend>
                    <div style="display:flex;flex-wrap:wrap;gap:0.75rem 1.25rem;margin-top:0.35rem">
                        <label><input type="checkbox" name="afiliacion" value="1" <?= !empty($formRow['afiliacion']) ? 'checked' : '' ?>> Afiliación</label>
                        <label><input type="checkbox" name="anualidad" value="1" <?= !empty($formRow['anualidad']) ? 'checked' : '' ?>> Anualidad</label>
                        <label><input type="checkbox" name="carnet" value="1" <?= !empty($formRow['carnet']) ? 'checked' : '' ?>> Carnet</label>
                        <label><input type="checkbox" name="traspaso" value="1" <?= !empty($formRow['traspaso']) ? 'checked' : '' ?>> Traspaso</label>
                    </div>
                </fieldset>

                <div class="fvd-mtf-actions">
                    <button type="submit" class="fvd-mtf-btn fvd-mtf-btn--primary"><?= $fvd_sustituir_desde_id > 0 ? 'Confirmar sustitución' : ($isNew ? 'Crear registro' : 'Guardar cambios') ?></button>
                    <a class="fvd-mtf-btn fvd-mtf-btn--secondary" href="<?= htmlspecialchars($backUrl, ENT_QUOTES, 'UTF-8') ?>">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</div>
