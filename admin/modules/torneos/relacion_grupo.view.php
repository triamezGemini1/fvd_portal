<?php

declare(strict_types=1);

/** @var string $selfUrl */
/** @var list<array<string,mixed>> $relacionGrupoFilas */
/** @var bool $relacionGrupoColumnaOk */
/** @var string $fvd_torneo_relacion_flash */
/** @var string $fvd_torneo_relacion_err */
/** @var int $fvd_relacion_max_dias_fechas */
/** @var bool $relacionGrupoUsaEsCampeonato columna es_campeonato en torneosact */
/** @var string $relacionGrupoFiltro activos|todos|proximos|por_realizar|en_proceso|realizados */
/** @var int $relacionGrupoDiasEnProceso días hacia atrás para «en proceso» vs «realizado» */
$relacionGrupoUsaEsCampeonato = !empty($relacionGrupoUsaEsCampeonato ?? false);
$relacionGrupoFiltro = isset($relacionGrupoFiltro) ? strtolower(trim((string) $relacionGrupoFiltro)) : 'activos';
$relacionGrupoDiasEnProceso = isset($relacionGrupoDiasEnProceso) ? max(1, (int) $relacionGrupoDiasEnProceso) : 120;
$tipoGeneroLabels = [1 => 'Masc.', 2 => 'Fem.', 3 => 'Mixto'];
$maxDiasFechas = isset($fvd_relacion_max_dias_fechas) ? (int) $fvd_relacion_max_dias_fechas : 7;
$hoyYmd = date('Y-m-d');
$limiteEnProcesoYmd = (new \DateTimeImmutable($hoyYmd))->modify('-' . $relacionGrupoDiasEnProceso . ' days')->format('Y-m-d');

$mkRelTabUrl = static function (string $f) use ($selfUrl): string {
    $u = $selfUrl . '?action=relacion_grupo&filtro=' . rawurlencode($f);

    return function_exists('fvd_return_preserve_query_params') ? fvd_return_preserve_query_params($u) : $u;
};

/** Listado principal: todas las pestañas temporales listan cualquier fila de torneosact que cumpla el filtro de fechas. */
$candidatosCamp = $relacionGrupoFilas;
?>

<style>
    .fvd-rel-grupo-page { max-width: 64rem; }
    .fvd-rel-grupo-page h1 { margin: 0 0 0.35rem; font-size: 1.15rem; }
    .fvd-rel-grupo-lead { font-size: 0.78rem; color: var(--fvd-muted); margin: 0 0 0.75rem; line-height: 1.45; }
    .fvd-rel-grupo-actions-row { display: flex; flex-wrap: wrap; gap: 0.35rem; align-items: center; margin: 0 0 0.5rem; }
    .fvd-rel-grupo-actions-row button.fvd-input { padding: 0.25rem 0.5rem; font-size: 0.75rem; width: auto; }
    .fvd-rel-grupo-table-wrap {
        max-height: min(58vh, 28rem);
        overflow: auto;
        border: 1px solid var(--fvd-border, rgba(255,255,255,0.12));
        border-radius: 8px;
        background: rgba(0,0,0,0.08);
    }
    .fvd-rel-grupo-table { width: 100%; border-collapse: collapse; font-size: 0.78rem; }
    .fvd-rel-grupo-table thead th {
        position: sticky; top: 0; z-index: 2;
        background: var(--fvd-azul-card, #1e293b);
        color: #f8fafc;
        font-weight: 700;
        text-align: left;
        padding: 0.45rem 0.5rem;
        border-bottom: 1px solid var(--fvd-border, rgba(255,255,255,0.15));
        white-space: nowrap;
    }
    .fvd-rel-grupo-table tbody td {
        padding: 0.4rem 0.5rem;
        border-bottom: 1px solid var(--fvd-border, rgba(255,255,255,0.08));
        vertical-align: middle;
    }
    .fvd-rel-grupo-table tbody tr:nth-child(even) { background: rgba(255,255,255,0.03); }
    .fvd-rel-grupo-table tbody tr:hover { background: rgba(255,255,255,0.06); }
    .fvd-rel-grupo-table .fvd-rel-cb-cell { width: 2.25rem; text-align: center; }
    .fvd-rel-grupo-table input[type="checkbox"] {
        width: 1rem; height: 1rem; cursor: pointer;
        accent-color: var(--fvd-amarillo, #fff200);
    }
    .fvd-rel-grupo-table .fvd-rel-nombre { font-weight: 700; word-break: break-word; max-width: 18rem; }
    .fvd-rel-grupo-table code { font-size: 0.68rem; }
    .fvd-rel-grupo-pill {
        display: inline-block; padding: 0.12rem 0.45rem; border-radius: 999px;
        font-size: 0.68rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.04em;
    }
    .fvd-rel-grupo-pill--prox { background: rgba(34,197,94,0.25); color: #bbf7d0; }
    .fvd-rel-grupo-pill--proc { background: rgba(251,191,36,0.22); color: #fef9c3; }
    .fvd-rel-grupo-pill--done { background: rgba(148,163,184,0.28); color: #e2e8f0; }
    .fvd-rel-grupo-tabs { display: flex; flex-wrap: wrap; gap: 0.4rem; margin: 0 0 0.85rem; align-items: center; }
    .fvd-rel-grupo-tab {
        display: inline-flex; align-items: center; padding: 0.38rem 0.65rem;
        font-size: 0.72rem; font-weight: 700; text-decoration: none; border-radius: 7px;
        border: 1px solid var(--fvd-border, rgba(255,255,255,0.2)); color: var(--fvd-muted);
        background: rgba(0,0,0,0.12);
    }
    .fvd-rel-grupo-tab:hover { border-color: var(--fvd-amarillo, #fff200); color: #f8fafc; }
    .fvd-rel-grupo-tab--current {
        border-color: var(--fvd-amarillo, #fff200);
        color: #0f172a;
        background: rgba(255, 242, 0, 0.92);
    }
    .fvd-rel-grupo-refs { font-size: 0.72rem; color: var(--fvd-muted); margin: 0 0 0.65rem; padding: 0.35rem 0.5rem; border-left: 3px solid var(--fvd-amarillo, #fff200); background: rgba(0,0,0,0.08); }
    .fvd-rel-grupo-empty { color: var(--fvd-muted); font-size: 0.84rem; margin: 0; }
    .sr-only {
        position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px;
        overflow: hidden; clip: rect(0, 0, 0, 0); white-space: nowrap; border: 0;
    }
</style>

<div class="fvd-rel-grupo-page">
    <h1>Relacionar torneos por grupo</h1>
    <p class="fvd-rel-grupo-lead">
        <strong>Todos</strong> muestra el catálogo completo de <code>torneosact</code> (cualquier tipo y fecha).
        Las demás pestañas filtran por <strong>fecha de inicio</strong> e incluyen <strong>campeonatos y torneos</strong> (individuales, parejas, equipos): por realizar, en proceso, realizados o activos (por realizar + en proceso).
        «En proceso» = inicio en los últimos <strong><?= (int) $relacionGrupoDiasEnProceso ?></strong> días; «realizado» = anterior a ese tramo.
        Marque dos o más filas y confirme para unificar; el código de relación es <code>grupo_evento_id</code>.
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

    <nav class="fvd-rel-grupo-tabs" aria-label="Vista del listado">
        <?php
        $tabAct = $relacionGrupoFiltro === 'por_realizar' ? 'proximos' : $relacionGrupoFiltro;
        $tabs = [
            'activos' => 'Activos (por realizar + en proceso)',
            'todos' => 'Todos',
            'proximos' => 'Por realizar',
            'en_proceso' => 'En proceso',
            'realizados' => 'Realizados',
        ];
        foreach ($tabs as $tf => $label):
            $isCur = ($tabAct === $tf);
            ?>
            <a class="fvd-rel-grupo-tab<?= $isCur ? ' fvd-rel-grupo-tab--current' : '' ?>"
               href="<?= htmlspecialchars($mkRelTabUrl($tf), ENT_QUOTES, 'UTF-8') ?>"
               <?= $isCur ? 'aria-current="page"' : '' ?>><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></a>
        <?php endforeach; ?>
    </nav>

    <?php if (!$relacionGrupoColumnaOk): ?>
        <p class="fvd-mod-msg">Falta la columna <code>grupo_evento_id</code> en <code>torneosact</code>. Ejecute los scripts SQL de actualización del módulo.</p>
    <?php elseif ($candidatosCamp === []): ?>
        <?php
        if ($relacionGrupoFiltro === 'todos') {
            $emptyTxt = 'No hay torneos registrados en torneosact.';
        } elseif ($relacionGrupoFiltro === 'proximos' || $relacionGrupoFiltro === 'por_realizar') {
            $emptyTxt = 'No hay eventos por realizar (fecha de inicio ≥ hoy).';
        } elseif ($relacionGrupoFiltro === 'en_proceso') {
            $emptyTxt = 'No hay eventos en proceso en la ventana de los últimos ' . (int) $relacionGrupoDiasEnProceso . ' días.';
        } elseif ($relacionGrupoFiltro === 'realizados') {
            $emptyTxt = 'No hay eventos realizados (anteriores a esa ventana) en el listado.';
        } else {
            $emptyTxt = 'No hay eventos activos (por realizar + en proceso) en el rango configurado.';
        }
        ?>
        <p class="fvd-rel-grupo-empty"><?= htmlspecialchars($emptyTxt, ENT_QUOTES, 'UTF-8') ?></p>
    <?php else: ?>
            <form method="post" action="<?= htmlspecialchars($selfUrl . '?action=relacion_grupo', ENT_QUOTES, 'UTF-8') ?>" id="fvd_form_relacion_grupo" data-max-dias-fechas="<?= $maxDiasFechas ?>">
                <input type="hidden" name="_action" value="relacion_grupo_aplicar">
                <input type="hidden" name="filtro" value="<?= htmlspecialchars($relacionGrupoFiltro, ENT_QUOTES, 'UTF-8') ?>">

                <div class="fvd-rel-grupo-actions-row">
                    <span style="font-size:0.75rem;color:var(--fvd-muted);margin-right:0.25rem"><?= count($candidatosCamp) ?> evento(s)</span>
                    <button type="button" class="fvd-input" id="fvd_rel_sel_todos_camp">Marcar todos</button>
                    <button type="button" class="fvd-input" id="fvd_rel_sel_ninguno">Desmarcar todos</button>
                </div>

                <div class="fvd-rel-grupo-table-wrap" role="region" aria-label="Eventos según filtro">
                    <table class="fvd-rel-grupo-table">
                        <thead>
                        <tr>
                            <th class="fvd-rel-cb-cell" scope="col"><span class="sr-only">Seleccionar</span></th>
                            <th scope="col">Situación</th>
                            <th scope="col">ID</th>
                            <th scope="col">Nombre</th>
                            <th scope="col">Inicio</th>
                            <th scope="col">Lugar</th>
                            <th scope="col">Organiza</th>
                            <th scope="col">Género</th>
                            <th scope="col">Tipo</th>
                            <th scope="col">Grupo</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($candidatosCamp as $r): ?>
                            <?php
                            $tid = (int) ($r['torneo'] ?? 0);
                            $gAct = isset($r['grupo_evento_id']) ? (int) $r['grupo_evento_id'] : 0;
                            $fdRaw = substr((string) ($r['fechator'] ?? ''), 0, 10);
                            $fd = htmlspecialchars($fdRaw, ENT_QUOTES, 'UTF-8');
                            if ($fdRaw === '') {
                                $situacion = '—';
                                $pillClass = '';
                            } elseif ($fdRaw >= $hoyYmd) {
                                $situacion = 'Por realizar';
                                $pillClass = 'fvd-rel-grupo-pill--prox';
                            } elseif ($fdRaw >= $limiteEnProcesoYmd) {
                                $situacion = 'En proceso';
                                $pillClass = 'fvd-rel-grupo-pill--proc';
                            } else {
                                $situacion = 'Realizado';
                                $pillClass = 'fvd-rel-grupo-pill--done';
                            }
                            ?>
                            <tr class="fvd-rel-grupo-row" data-fecha="<?= $fd ?>"<?= $gAct > 0 ? ' data-fvd-grupo="' . $gAct . '"' : '' ?>>
                                <td class="fvd-rel-cb-cell">
                                    <input type="checkbox" name="torneo_id[]" value="<?= $tid ?>" class="fvd-rel-camp-cb" id="fvd_rel_cb_<?= $tid ?>" data-fvd-grupo="<?= $gAct > 0 ? $gAct : '' ?>">
                                </td>
                                <td><?php if ($pillClass !== ''): ?><span class="fvd-rel-grupo-pill <?= $pillClass ?>"><?= htmlspecialchars($situacion, ENT_QUOTES, 'UTF-8') ?></span><?php else: ?><?= htmlspecialchars($situacion, ENT_QUOTES, 'UTF-8') ?><?php endif; ?></td>
                                <td><code><?= $tid ?></code></td>
                                <td class="fvd-rel-nombre">
                                    <label for="fvd_rel_cb_<?= $tid ?>"><?= htmlspecialchars((string) ($r['nombre'] ?? ''), ENT_QUOTES, 'UTF-8') ?></label>
                                </td>
                                <td><?= $fd !== '' ? $fd : '—' ?></td>
                                <td><?= htmlspecialchars((string) ($r['lugar'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars((string) ($r['org_nombre'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($tipoGeneroLabels[(int) ($r['tipo'] ?? 1)] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
                                <?php
                                $esCampRow = $relacionGrupoUsaEsCampeonato
                                    ? ((int) ($r['es_campeonato'] ?? 0) === 1)
                                    : ((int) ($r['tipo'] ?? 1) === 2);
                                ?>
                                <td><?= $esCampRow ? 'Campeonato' : 'Torneo' ?></td>
                                <td><?= $gAct > 0 ? (string) $gAct : '—' ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

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
                        var row = el.closest ? el.closest('tr.fvd-rel-grupo-row') : null;
                        if (row && row.getAttribute('data-fecha')) {
                            fechas.push(row.getAttribute('data-fecha'));
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
</div>
