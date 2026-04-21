<?php

declare(strict_types=1);

/** @var string $selfUrl */
/** @var list<array<string,mixed>> $relacionGrupoFilas */
/** @var bool $relacionGrupoColumnaOk */
/** @var string $fvd_torneo_relacion_flash */
/** @var string $fvd_torneo_relacion_err */
/** @var int $fvd_relacion_max_dias_fechas */
$tipoLabels = [1 => 'Torneo', 2 => 'Campeonato'];
$maxDiasFechas = isset($fvd_relacion_max_dias_fechas) ? (int) $fvd_relacion_max_dias_fechas : 7;

$candidatosCamp = [];
$refsTorneo = [];
foreach ($relacionGrupoFilas as $r) {
    if ((int) ($r['tipo'] ?? 1) === 2) {
        $candidatosCamp[] = $r;
    } else {
        $refsTorneo[] = $r;
    }
}
?>

<style>
    .fvd-rel-grupo-page { max-width: 52rem; }
    .fvd-rel-grupo-page h1 { margin: 0 0 0.35rem; font-size: 1.15rem; }
    .fvd-rel-grupo-lead { font-size: 0.78rem; color: var(--fvd-muted); margin: 0 0 0.75rem; line-height: 1.45; }
    .fvd-rel-grupo-actions-row { display: flex; flex-wrap: wrap; gap: 0.35rem; align-items: center; margin: 0 0 0.5rem; }
    .fvd-rel-grupo-actions-row button.fvd-input { padding: 0.25rem 0.5rem; font-size: 0.75rem; width: auto; }
    .fvd-rel-grupo-list { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 0.35rem; max-height: min(58vh, 28rem); overflow-y: auto; }
    .fvd-rel-grupo-item {
        display: grid; grid-template-columns: auto 1fr; gap: 0.5rem 0.65rem; align-items: start;
        padding: 0.45rem 0.55rem; border-radius: 6px;
        border: 1px solid var(--fvd-border, rgba(255,255,255,0.12));
        background: rgba(0,0,0,0.12);
    }
    .fvd-rel-grupo-item input[type="checkbox"] { margin-top: 0.2rem; width: 1rem; height: 1rem; cursor: pointer; accent-color: var(--fvd-amarillo, #fff200); }
    .fvd-rel-grupo-item__main { min-width: 0; }
    .fvd-rel-grupo-item__title { font-weight: 700; font-size: 0.84rem; line-height: 1.25; word-break: break-word; }
    .fvd-rel-grupo-item__meta { font-size: 0.72rem; color: var(--fvd-muted); margin-top: 0.15rem; line-height: 1.35; }
    .fvd-rel-grupo-item__meta code { font-size: 0.68rem; }
    .fvd-rel-grupo-refs { font-size: 0.72rem; color: var(--fvd-muted); margin: 0 0 0.65rem; padding: 0.35rem 0.5rem; border-left: 3px solid var(--fvd-amarillo, #fff200); background: rgba(0,0,0,0.08); }
    .fvd-rel-grupo-empty { color: var(--fvd-muted); font-size: 0.84rem; margin: 0; }
</style>

<div class="fvd-rel-grupo-page">
    <h1>Relacionar campeonatos (por realizar)</h1>
    <p class="fvd-rel-grupo-lead">
        Listado de <strong>campeonatos</strong> con fecha de inicio hoy o posterior. Marque dos o más eventos y confirme;
        el <strong>código de relación</strong> es el número de grupo (<code>grupo_evento_id</code>) que se asigna al vincular.
        En <strong>inscripción</strong> (sitio y panel admin) los delegados podrán alternar entre torneos del mismo grupo.
    </p>

    <?php if ($fvd_torneo_relacion_flash !== ''): ?>
        <p class="fvd-mod-msg" style="margin-bottom:0.5rem"><?= htmlspecialchars($fvd_torneo_relacion_flash, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>
    <?php if ($fvd_torneo_relacion_err !== ''): ?>
        <p class="fvd-mod-msg fvd-tf-form-page__err" style="margin-bottom:0.5rem"><?= htmlspecialchars($fvd_torneo_relacion_err, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>

    <p class="fvd-atl-muted" style="margin:0 0 0.75rem;font-size:0.75rem">
        <a href="<?= htmlspecialchars($selfUrl, ENT_QUOTES, 'UTF-8') ?>" style="color:inherit">← Listado torneos</a>
    </p>

    <?php if (!$relacionGrupoColumnaOk): ?>
        <p class="fvd-mod-msg">Falta la columna <code>grupo_evento_id</code> en <code>torneosact</code>. Ejecute los scripts SQL de actualización del módulo.</p>
    <?php elseif ($candidatosCamp === [] && $refsTorneo === []): ?>
        <p class="fvd-rel-grupo-empty">No hay campeonatos <strong>por realizar</strong> (fecha de inicio ≥ hoy).</p>
    <?php else: ?>
        <?php if ($refsTorneo !== []): ?>
            <p class="fvd-rel-grupo-refs" role="note">
                <strong>Referencia (no unificables):</strong>
                <?php
                $bits = [];
                foreach ($refsTorneo as $rt) {
                    $bits[] = '#' . (int) ($rt['torneo'] ?? 0) . ' ' . htmlspecialchars((string) ($rt['nombre'] ?? ''), ENT_QUOTES, 'UTF-8');
                }
                echo implode(' · ', $bits);
                ?>
            </p>
        <?php endif; ?>

        <?php if ($candidatosCamp === []): ?>
            <p class="fvd-rel-grupo-empty">No hay <strong>campeonatos</strong> en el rango (solo torneos de referencia o ningún evento).</p>
        <?php else: ?>
            <form method="post" action="<?= htmlspecialchars($selfUrl . '?action=relacion_grupo', ENT_QUOTES, 'UTF-8') ?>" id="fvd_form_relacion_grupo" data-max-dias-fechas="<?= $maxDiasFechas ?>">
                <input type="hidden" name="_action" value="relacion_grupo_aplicar">

                <div class="fvd-rel-grupo-actions-row">
                    <span style="font-size:0.75rem;color:var(--fvd-muted);margin-right:0.25rem"><?= count($candidatosCamp) ?> campeonato(s)</span>
                    <button type="button" class="fvd-input" id="fvd_rel_sel_todos_camp">Marcar todos</button>
                    <button type="button" class="fvd-input" id="fvd_rel_sel_ninguno">Desmarcar</button>
                </div>

                <ul class="fvd-rel-grupo-list" aria-label="Campeonatos por realizar">
                    <?php foreach ($candidatosCamp as $r): ?>
                        <?php
                        $tid = (int) ($r['torneo'] ?? 0);
                        $gAct = isset($r['grupo_evento_id']) ? (int) $r['grupo_evento_id'] : 0;
                        $fd = htmlspecialchars(substr((string) ($r['fechator'] ?? ''), 0, 10), ENT_QUOTES, 'UTF-8');
                        ?>
                        <li class="fvd-rel-grupo-item" data-fecha="<?= $fd ?>"<?= $gAct > 0 ? ' data-fvd-grupo="' . $gAct . '"' : '' ?>>
                            <input type="checkbox" name="torneo_id[]" value="<?= $tid ?>" class="fvd-rel-camp-cb" id="fvd_rel_cb_<?= $tid ?>" data-fvd-grupo="<?= $gAct > 0 ? $gAct : '' ?>">
                            <div class="fvd-rel-grupo-item__main">
                                <label class="fvd-rel-grupo-item__title" for="fvd_rel_cb_<?= $tid ?>">
                                    <?= htmlspecialchars((string) ($r['nombre'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                </label>
                                <div class="fvd-rel-grupo-item__meta">
                                    <code>ID <?= $tid ?></code>
                                    · <?= $fd ?>
                                    · <?= htmlspecialchars((string) ($r['lugar'] ?? '—'), ENT_QUOTES, 'UTF-8') ?>
                                    · <?= htmlspecialchars((string) ($r['org_nombre'] ?? '—'), ENT_QUOTES, 'UTF-8') ?>
                                    · <?= htmlspecialchars($tipoLabels[(int) ($r['tipo'] ?? 2)] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                    · Código grupo <?= $gAct > 0 ? (string) $gAct : '—' ?>
                                </div>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>

                <div class="fvd-mod-actions" style="margin-top:0.75rem">
                    <button type="submit" class="fvd-btn-primary" id="fvd_rel_btn_confirmar" disabled>Unificar selección (mismo código de grupo)</button>
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
                    var grupos = [];
                    cbs.forEach(function (el) {
                        if (!el.checked) return;
                        n++;
                        var g = el.getAttribute('data-fvd-grupo');
                        if (g && String(g).trim() !== '') {
                            grupos.push(parseInt(g, 10));
                        }
                    });
                    var yaTodosMismoGrupo = false;
                    if (n >= 2 && grupos.length === n) {
                        var u = grupos.filter(function (v, i, a) { return a.indexOf(v) === i; });
                        yaTodosMismoGrupo = u.length === 1;
                    }
                    if (btn) {
                        btn.disabled = n < 2 || yaTodosMismoGrupo;
                        btn.title = yaTodosMismoGrupo
                            ? 'Ya comparten el mismo grupo.'
                            : '';
                    }
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
                        var li = el.closest ? el.closest('li') : null;
                        if (li && li.getAttribute('data-fecha')) {
                            fechas.push(li.getAttribute('data-fecha'));
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
                                if (!window.confirm('Las fechas de inicio difieren en ' + dias + ' días (máx. recomendado ' + maxD + '). ¿Continuar?')) {
                                    e.preventDefault();
                                    return false;
                                }
                            }
                        }
                    }
                    return confirm('Se asignará el mismo código de grupo (grupo_evento_id) a los torneos seleccionados. ¿Continuar?');
                });
                sync();
            })();
            </script>
        <?php endif; ?>
    <?php endif; ?>
</div>
