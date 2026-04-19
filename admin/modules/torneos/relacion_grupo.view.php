<?php

declare(strict_types=1);

/** @var string $selfUrl */
/** @var string $fechaRel */
/** @var list<array<string,mixed>> $relacionGrupoFilas */
/** @var bool $relacionGrupoColumnaOk */
/** @var string $fvd_torneo_relacion_flash */
/** @var string $fvd_torneo_relacion_err */
/** @var int $fvd_relacion_max_dias_fechas */
$tipoLabels = [1 => 'Torneo', 2 => 'Campeonato'];
$maxDiasFechas = isset($fvd_relacion_max_dias_fechas) ? (int) $fvd_relacion_max_dias_fechas : 7;
?>

<h1>Relacionar campeonatos (mismo día)</h1>
<p style="font-size:0.8125rem;color:var(--fvd-muted);margin:0 0 0.75rem">
    Seleccione <strong>dos o más campeonatos</strong> celebrados el mismo día; el sistema asignará un <strong>nuevo número de grupo</strong> compartido. Los torneos (no campeonato) aparecen solo como referencia y no se pueden vincular.
</p>

<?php if ($fvd_torneo_relacion_flash !== ''): ?>
    <p class="fvd-mod-msg" style="margin-bottom:0.75rem"><?= htmlspecialchars($fvd_torneo_relacion_flash, ENT_QUOTES, 'UTF-8') ?></p>
<?php endif; ?>
<?php if ($fvd_torneo_relacion_err !== ''): ?>
    <p class="fvd-mod-msg fvd-tf-form-page__err" style="margin-bottom:0.75rem"><?= htmlspecialchars($fvd_torneo_relacion_err, ENT_QUOTES, 'UTF-8') ?></p>
<?php endif; ?>

<div class="fvd-mod-toolbar" style="margin-bottom:1rem">
    <form method="get" action="" style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end">
        <input type="hidden" name="action" value="relacion_grupo">
        <div>
            <label style="font-size:.8125rem;color:var(--fvd-muted);display:block">Fecha del evento</label>
            <input class="fvd-input" type="date" name="fecha" value="<?= htmlspecialchars($fechaRel, ENT_QUOTES, 'UTF-8') ?>" required style="max-width:12rem">
        </div>
        <button type="submit" class="fvd-input" style="width:auto;padding:6px 12px">Mostrar</button>
    </form>
    <a href="<?= htmlspecialchars($selfUrl, ENT_QUOTES, 'UTF-8') ?>" class="fvd-input" style="width:auto;padding:6px 12px;display:inline-flex;align-items:center;text-decoration:none;box-sizing:border-box">Volver al listado</a>
</div>

<?php if (!$relacionGrupoColumnaOk): ?>
    <p class="fvd-mod-msg">Falta la columna <code>grupo_evento_id</code> en <code>torneosact</code>. Ejecute los scripts SQL de actualización del módulo.</p>
<?php elseif ($relacionGrupoFilas === []): ?>
    <p style="color:var(--fvd-muted)">No hay eventos registrados para la fecha <?= htmlspecialchars($fechaRel, ENT_QUOTES, 'UTF-8') ?>.</p>
<?php else: ?>
    <form method="post" action="<?= htmlspecialchars($selfUrl . '?action=relacion_grupo&fecha=' . rawurlencode($fechaRel), ENT_QUOTES, 'UTF-8') ?>" id="fvd_form_relacion_grupo" data-max-dias-fechas="<?= $maxDiasFechas ?>">
        <input type="hidden" name="_action" value="relacion_grupo_aplicar">
        <input type="hidden" name="fecha_relacion" value="<?= htmlspecialchars($fechaRel, ENT_QUOTES, 'UTF-8') ?>">

        <div style="margin-bottom:1rem;max-width:32rem">
            <label for="fvd_nombre_nominal_campeonato" style="font-size:.8125rem;color:var(--fvd-muted);display:block;margin-bottom:0.35rem">Nombre nominal del campeonato</label>
            <input class="fvd-input" type="text" name="nombre_nominal_campeonato" id="fvd_nombre_nominal_campeonato" required minlength="2" maxlength="255" placeholder="Ej. Nacional Máster 2026" autocomplete="off" style="width:100%;box-sizing:border-box">
            <p style="font-size:0.75rem;color:var(--fvd-muted);margin:0.35rem 0 0">Se guarda en la tabla maestra y es el título que verán los delegados para el bloque vinculado (M / F / J).</p>
        </div>

        <p style="font-size:0.8125rem;margin:0 0 0.5rem">
            <button type="button" class="fvd-input" style="width:auto;padding:4px 10px;font-size:0.8125rem" id="fvd_rel_sel_todos_camp">Seleccionar todos los campeonatos</button>
            <button type="button" class="fvd-input" style="width:auto;padding:4px 10px;font-size:0.8125rem" id="fvd_rel_sel_ninguno">Quitar selección</button>
        </p>

        <div class="fvd-mod-table-wrap">
            <table class="fvd-mod-table fvd-mod-table--nowrap">
                <thead>
                <tr>
                    <th style="width:2.5rem"></th>
                    <th>ID</th>
                    <th>Nombre</th>
                    <th>Lugar</th>
                    <th>Tipo</th>
                    <th>Organización</th>
                    <th>Grupo actual</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($relacionGrupoFilas as $r): ?>
                    <?php
                    $tid = (int) ($r['torneo'] ?? 0);
                    $esCamp = (int) ($r['tipo'] ?? 0) === 2;
                    ?>
                    <tr<?= $esCamp ? ' data-fecha="' . htmlspecialchars(substr((string) ($r['fechator'] ?? ''), 0, 10), ENT_QUOTES, 'UTF-8') . '"' : '' ?><?= $esCamp ? '' : ' style="opacity:0.65"' ?>>
                        <td>
                            <?php if ($esCamp): ?>
                                <input type="checkbox" name="torneo_id[]" value="<?= $tid ?>" class="fvd-rel-camp-cb">
                            <?php else: ?>
                                <span title="Solo los campeonatos se vinculan por grupo">—</span>
                            <?php endif; ?>
                        </td>
                        <td><?= $tid ?></td>
                        <td><?= htmlspecialchars((string) ($r['nombre'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars((string) ($r['lugar'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($tipoLabels[(int) ($r['tipo'] ?? 1)] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars((string) ($r['org_nombre'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?php
                            $g = $r['grupo_evento_id'] ?? null;
                            echo $g !== null && (int) $g > 0 ? (string) (int) $g : '—';
                        ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="fvd-mod-actions" style="margin-top:1rem">
            <button type="submit" class="fvd-btn-primary" id="fvd_rel_btn_confirmar" disabled>Asegurar relación (mismo grupo)</button>
        </div>
    </form>

    <script>
    (function () {
        var form = document.getElementById('fvd_form_relacion_grupo');
        if (!form) return;
        var cbs = form.querySelectorAll('.fvd-rel-camp-cb');
        var btn = document.getElementById('fvd_rel_btn_confirmar');
        var selTodos = document.getElementById('fvd_rel_sel_todos_camp');
        var selNinguno = document.getElementById('fvd_rel_sel_ninguno');
        function sync() {
            var n = 0;
            cbs.forEach(function (el) { if (el.checked) n++; });
            if (btn) btn.disabled = n < 2;
        }
        cbs.forEach(function (el) { el.addEventListener('change', sync); });
        if (selTodos) selTodos.addEventListener('click', function () {
            cbs.forEach(function (el) { el.checked = true; });
            sync();
        });
        if (selNinguno) selNinguno.addEventListener('click', function () {
            cbs.forEach(function (el) { el.checked = false; });
            sync();
        });
        form.addEventListener('submit', function (e) {
            var n = 0;
            var fechas = [];
            cbs.forEach(function (el) {
                if (!el.checked) return;
                n++;
                var tr = el.closest ? el.closest('tr') : null;
                if (tr && tr.getAttribute('data-fecha')) {
                    fechas.push(tr.getAttribute('data-fecha'));
                }
            });
            if (n < 2) {
                e.preventDefault();
                return false;
            }
            var maxD = parseInt(form.getAttribute('data-max-dias-fechas') || '7', 10);
            if (!isNaN(maxD) && maxD > 0 && fechas.length >= 2) {
                var ts = [];
                fechas.forEach(function (s) {
                    var p = (s || '').split('-');
                    if (p.length === 3) {
                        var t = Date.UTC(parseInt(p[0], 10), parseInt(p[1], 10) - 1, parseInt(p[2], 10));
                        if (!isNaN(t)) ts.push(t);
                    }
                });
                if (ts.length >= 2) {
                    var mn = Math.min.apply(null, ts);
                    var mx = Math.max.apply(null, ts);
                    var dias = Math.round((mx - mn) / 86400000);
                    if (dias > maxD) {
                        if (!window.confirm('Las fechas de inicio de los campeonatos marcados difieren en ' + dias + ' días (superior a ' + maxD + '). Suele indicar un error. ¿Desea continuar de todos modos?')) {
                            e.preventDefault();
                            return false;
                        }
                    }
                }
            }
            var nom = document.getElementById('fvd_nombre_nominal_campeonato');
            if (nom && (!nom.value || String(nom.value).trim().length < 2)) {
                e.preventDefault();
                alert('Indique el nombre nominal del campeonato (mínimo 2 caracteres).');
                if (nom.focus) nom.focus();
                return false;
            }
            return confirm('Se asignará un nuevo grupo compartido, se guardará el nombre nominal y se actualizarán las invitaciones de delegados cuando corresponda. ¿Continuar?');
        });
        sync();
    })();
    </script>
<?php endif; ?>
