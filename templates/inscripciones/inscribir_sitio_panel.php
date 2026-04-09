<?php
declare(strict_types=1);
/** @var array<string,mixed> $torneoMeta */
/** @var string $inscripcionApiUrl */
/** @var int $torneoSel */
/** @var int $asocId */
/** @var bool $esFvd */
/** @var bool $fvd_inscripcion_bandera_modo */
/** @var list<array{atleta_id:int,nombre:string,cedula:string,numfvd:int,cedula_num:int}> $fvdSitioDisponibles */
/** @var list<array{atleta_id:int,nombre:string,cedula:string,numfvd:int,cedula_num:int,equipo:int,retirar_mode?:string}> $fvdSitioInscritos */
/** @var string $fvdSitioNuevoAtletaUrl */
$clSitio = (int) ($torneoMeta['clase'] ?? 1);
$tnom = htmlspecialchars((string) ($torneoMeta['torneo']['nombre'] ?? ''), ENT_QUOTES, 'UTF-8');
$esInd = $clSitio === 1;
$vdDeleg = $torneoMeta['ventana_delegado'] ?? null;
$fvdDelegadoInscripcionCerrada = !empty($fvd_inscripcion_bandera_modo)
    && is_array($vdDeleg)
    && !($vdDeleg['fase2_inscripciones'] ?? false);
$fvdDelegadoVentanaMsg = is_array($vdDeleg) ? (string) ($vdDeleg['etiqueta_fase'] ?? '') : '';
?>
<link rel="stylesheet" href="<?= htmlspecialchars(url('assets/css/fvd-inscripcion-sitio.css'), ENT_QUOTES, 'UTF-8') ?>">
<?php if ($fvdDelegadoInscripcionCerrada): ?>
<style>
.fvd-insc-sitio--solo-lectura .fvd-insc-sitio__btn,
.fvd-insc-sitio--solo-lectura .fvd-insc-sitio__btn--ok,
.fvd-insc-sitio--solo-lectura .fvd-insc-sitio__btn--sec { opacity: 0.45; pointer-events: none; cursor: not-allowed; }
</style>
<?php endif; ?>

<section class="fvd-insc-sitio<?= $fvdDelegadoInscripcionCerrada ? ' fvd-insc-sitio--solo-lectura' : '' ?>" aria-label="<?= $tnom ?>">
    <div class="fvd-insc-sitio__body">
        <?php if ($fvdDelegadoInscripcionCerrada): ?>
            <p class="fvd-mod-msg" style="margin:0 0 0.75rem;font-size:0.875rem;border-left:4px solid #f59e0c;padding-left:10px">
                <strong>Calendario del torneo:</strong> <?= htmlspecialchars($fvdDelegadoVentanaMsg !== '' ? $fvdDelegadoVentanaMsg : 'Fuera del periodo de inscripciones y retiros solo puede consultar listados y registrar pagos.', ENT_QUOTES, 'UTF-8') ?>
            </p>
        <?php endif; ?>
        <?php if ($esInd): ?>
        <div class="fvd-insc-sitio__fila" id="fvd-sitio-linea">
            <div class="fvd-insc-sitio__campo fvd-insc-sitio__nac">
                <label for="fvd-sitio-nac">Nac.</label>
                <input type="text" id="fvd-sitio-nac" class="fvd-input" maxlength="1" value="V" autocomplete="off" title="V, E, J o P">
            </div>
            <div class="fvd-insc-sitio__campo fvd-insc-sitio__ced">
                <label for="fvd-sitio-ced">Cédula <span style="color:#f87171">*</span></label>
                <input type="text" id="fvd-sitio-ced" class="fvd-input" inputmode="numeric" maxlength="15" placeholder="Solo números" autocomplete="off">
            </div>
            <div class="fvd-insc-sitio__campo fvd-insc-sitio__res is-hidden" id="fvd-sitio-wrap-nombre">
                <label for="fvd-sitio-nombre">Nombre</label>
                <input type="text" id="fvd-sitio-nombre" class="fvd-input" readonly>
            </div>
            <div class="fvd-insc-sitio__campo fvd-insc-sitio__sexo is-hidden" id="fvd-sitio-wrap-sexo">
                <label for="fvd-sitio-sexo">Sexo</label>
                <select id="fvd-sitio-sexo" class="fvd-input">
                    <option value="M">M</option>
                    <option value="F">F</option>
                    <option value="O">O</option>
                </select>
            </div>
            <div class="fvd-insc-sitio__acc is-hidden" id="fvd-sitio-wrap-btns">
                <button type="button" class="fvd-insc-sitio__btn fvd-insc-sitio__btn--ok" id="fvd-sitio-inscribir">Inscribir</button>
                <button type="button" class="fvd-insc-sitio__btn fvd-insc-sitio__btn--sec" id="fvd-sitio-limpiar" title="Otra búsqueda">↻</button>
            </div>
        </div>
        <p id="fvd-sitio-msg" class="fvd-insc-sitio__msg" role="status" aria-live="polite"></p>
        <p class="fvd-insc-sitio__hint" id="fvd-sitio-noenc" style="display:none">
            <a href="<?= htmlspecialchars($fvdSitioNuevoAtletaUrl, ENT_QUOTES, 'UTF-8') ?>">Registrar nuevo atleta</a>
        </p>
        <?php endif; ?>

        <div class="fvd-insc-sitio__grid">
            <div class="fvd-insc-sitio__card">
                <div class="fvd-insc-sitio__card-h fvd-insc-sitio__card-h--disp">
                    Disponibles
                    <span class="fvd-insc-sitio__badge" id="fvd-sitio-n-disp"><?= count($fvdSitioDisponibles) ?></span>
                </div>
                <div class="fvd-insc-sitio__table-wrap">
                    <table class="fvd-insc-sitio__table">
                        <thead><tr><th>Nombre</th><th>Nº FVD</th><th>CI</th></tr></thead>
                        <tbody id="fvd-sitio-tbody-disp">
                            <?php foreach ($fvdSitioDisponibles as $u): ?>
                            <tr data-aid="<?= (int) $u['atleta_id'] ?>" data-nombre="<?= htmlspecialchars($u['nombre'], ENT_QUOTES, 'UTF-8') ?>"
                                data-cedula-num="<?= (int) ($u['cedula_num'] ?? 0) ?>">
                                <td><strong><?= htmlspecialchars($u['nombre'], ENT_QUOTES, 'UTF-8') ?></strong></td>
                                <td><?= (int) $u['numfvd'] ?></td>
                                <td><?= htmlspecialchars($u['cedula'], ENT_QUOTES, 'UTF-8') ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="fvd-insc-sitio__card">
                <div class="fvd-insc-sitio__card-h fvd-insc-sitio__card-h--insc">
                    Inscritos
                    <span class="fvd-insc-sitio__badge" id="fvd-sitio-n-insc"><?= count($fvdSitioInscritos) ?></span>
                </div>
                <div class="fvd-insc-sitio__table-wrap">
                    <table class="fvd-insc-sitio__table">
                        <thead><tr><th>Nombre</th><th>Nº FVD</th><th>CI</th></tr></thead>
                        <tbody id="fvd-sitio-tbody-insc">
                            <?php foreach ($fvdSitioInscritos as $i):
                                $iAid = (int) $i['atleta_id'];
                                $iCedN = (int) ($i['cedula_num'] ?? 0);
                                $iEq = (int) ($i['equipo'] ?? 0);
                                if (isset($i['retirar_mode']) && (string) $i['retirar_mode'] !== '') {
                                    $retMode = (string) $i['retirar_mode'];
                                } else {
                                    $retMode = $fvd_inscripcion_bandera_modo && $iAid > 0
                                        ? 'bandera'
                                        : (!$fvd_inscripcion_bandera_modo && $iCedN > 0 && $iEq === 0 ? 'tabla' : '0');
                                }
                                ?>
                            <tr data-aid="<?= $iAid ?>" data-nombre="<?= htmlspecialchars($i['nombre'], ENT_QUOTES, 'UTF-8') ?>"
                                data-cedula-num="<?= $iCedN ?>"
                                data-retirar="<?= htmlspecialchars($retMode, ENT_QUOTES, 'UTF-8') ?>">
                                <td><strong><?= htmlspecialchars($i['nombre'], ENT_QUOTES, 'UTF-8') ?></strong></td>
                                <td><?= (int) $i['numfvd'] ?></td>
                                <td><?= htmlspecialchars($i['cedula'], ENT_QUOTES, 'UTF-8') ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="fvd-insc-sitio__finish" style="margin-top:1rem;display:flex;align-items:center;gap:0.75rem;flex-wrap:wrap">
            <button type="button" class="fvd-insc-sitio__btn fvd-insc-sitio__btn--sec" id="fvd-sitio-finalizar">Finalizar</button>
            <small class="fvd-insc-sitio__hint" id="fvd-sitio-finish-msg" style="margin:0"></small>
        </div>
    </div>
</section>

<style>.is-hidden{display:none!important}</style>
<script>
(function () {
    var api = <?= json_encode($inscripcionApiUrl, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    var torneoId = <?= (int) $torneoSel ?>;
    var asocId = <?= (int) $asocId ?>;
    var esFvd = <?= $esFvd ? 'true' : 'false' ?>;
    var banderaMode = <?= $fvd_inscripcion_bandera_modo ? 'true' : 'false' ?>;
    var esInd = <?= $esInd ? 'true' : 'false' ?>;
    var delegadoInscripcionCerrada = <?= $fvdDelegadoInscripcionCerrada ? 'true' : 'false' ?>;
    var usuarioEncontrado = null;

    function qs(id) { return document.getElementById(id); }
    function apiQs(extra) {
        var s = api.indexOf('?') >= 0 ? '&' : '?';
        var x = extra || '';
        if (esFvd && asocId) x += (x ? '&' : '') + 'asociacion_id=' + encodeURIComponent(String(asocId));
        return s + x;
    }
    function msg(txt, kind) {
        var el = qs('fvd-sitio-msg');
        if (!el) return;
        el.textContent = txt || '';
        el.className = 'fvd-insc-sitio__msg fvd-insc-sitio__msg--show' + (kind ? ' fvd-insc-sitio__msg--' + kind : '');
        if (!txt) el.classList.remove('fvd-insc-sitio__msg--show');
    }
    function setHidden(id, on) {
        var w = qs(id);
        if (w) w.classList.toggle('is-hidden', !!on);
    }
    function limpiarLinea() {
        var c = qs('fvd-sitio-ced');
        if (c) c.value = '';
        var n = qs('fvd-sitio-nac');
        if (n) n.value = 'V';
        if (qs('fvd-sitio-nombre')) qs('fvd-sitio-nombre').value = '';
        usuarioEncontrado = null;
        setHidden('fvd-sitio-wrap-nombre', true);
        setHidden('fvd-sitio-wrap-sexo', true);
        setHidden('fvd-sitio-wrap-btns', true);
        msg('', '');
        var ne = qs('fvd-sitio-noenc');
        if (ne) ne.style.display = 'none';
    }
    function normNac(v) {
        v = (v || '').trim().toUpperCase();
        return ['V','E','J','P'].indexOf(v) >= 0 ? v : 'V';
    }
    function buscarCedula() {
        if (!esInd) return;
        var nac = normNac(qs('fvd-sitio-nac') && qs('fvd-sitio-nac').value);
        if (qs('fvd-sitio-nac')) qs('fvd-sitio-nac').value = nac;
        var ced = (qs('fvd-sitio-ced') && qs('fvd-sitio-ced').value) || '';
        ced = ced.replace(/\D/g, '');
        if (ced.length < 4) return;
        msg('Buscando…', 'info');
        usuarioEncontrado = null;
        setHidden('fvd-sitio-wrap-nombre', true);
        setHidden('fvd-sitio-wrap-sexo', true);
        setHidden('fvd-sitio-wrap-btns', true);
        var ne = qs('fvd-sitio-noenc');
        if (ne) ne.style.display = 'none';
        var u = api + apiQs('action=buscar_cedula_sitio&torneo_id=' + encodeURIComponent(String(torneoId))
            + '&nacionalidad=' + encodeURIComponent(nac) + '&cedula=' + encodeURIComponent(ced));
        fetch(u, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (!d || !d.ok) {
                    msg((d && d.error) ? d.error : 'Error.', 'err');
                    return;
                }
                var res = d.resultado || '';
                if (res === 'ya_inscrito') {
                    msg(d.mensaje || 'Ya inscrito.', 'warn');
                    limpiarLinea();
                    return;
                }
                if (res === 'no_encontrado') {
                    msg(d.mensaje || 'No encontrado.', 'warn');
                    if (ne) ne.style.display = 'block';
                    return;
                }
                if (res === 'atleta' && d.atleta) {
                    usuarioEncontrado = d.atleta;
                    if (qs('fvd-sitio-nombre')) qs('fvd-sitio-nombre').value = d.atleta.nombre || '';
                    var sx = qs('fvd-sitio-sexo');
                    if (sx) sx.value = (d.atleta.sexo || 'M').toUpperCase();
                    setHidden('fvd-sitio-wrap-nombre', false);
                    setHidden('fvd-sitio-wrap-sexo', false);
                    setHidden('fvd-sitio-wrap-btns', false);
                    msg('Atleta encontrado. Pulse Inscribir.', 'ok');
                    return;
                }
                msg('Respuesta inesperada.', 'err');
            })
            .catch(function () { msg('Error de red.', 'err'); });
    }
    function postInscribir(aid) {
        if (banderaMode && delegadoInscripcionCerrada) {
            return Promise.resolve({ ok: false, error: 'Periodo de inscripción cerrado para delegados según calendario del torneo.' });
        }
        var body = { action: 'inscribir', torneo_id: torneoId, tipo: 'individual', atleta_ids: [aid] };
        if (esFvd) body.asociacion_id = asocId;
        return fetch(api, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
            body: JSON.stringify(body)
        }).then(function (r) { return r.json(); });
    }
    function postRetirar(aid) {
        if (banderaMode && delegadoInscripcionCerrada) {
            return Promise.resolve({ ok: false, error: 'Periodo de retiros cerrado según calendario del torneo.' });
        }
        return fetch(api, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
            body: JSON.stringify({ action: 'retirar', torneo_id: torneoId, atleta_id: aid })
        }).then(function (r) { return r.json(); });
    }
    function postRetirarTabla(cedulaNum) {
        var body = { action: 'retirar_tabla', torneo_id: torneoId, cedula: cedulaNum };
        if (esFvd) body.asociacion_id = asocId;
        return fetch(api, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
            body: JSON.stringify(body)
        }).then(function (r) { return r.json(); });
    }
    function updateBadges() {
        var nd = document.querySelectorAll('#fvd-sitio-tbody-disp tr').length;
        var ni = document.querySelectorAll('#fvd-sitio-tbody-insc tr').length;
        var bd = qs('fvd-sitio-n-disp');
        var bi = qs('fvd-sitio-n-insc');
        if (bd) bd.textContent = String(nd);
        if (bi) bi.textContent = String(ni);
    }
    function retirarModeAfterInscripcion() {
        return banderaMode ? 'bandera' : 'tabla';
    }
    function marcarFilaInscrita(tr) {
        var cedAttr = parseInt(tr.getAttribute('data-cedula-num') || '0', 10);
        if (!cedAttr) {
            var cells = tr.querySelectorAll('td');
            var cedText = cells.length > 2 ? (cells[2].textContent || '').replace(/\D/g, '') : '';
            cedAttr = parseInt(cedText, 10) || 0;
            if (cedAttr) tr.setAttribute('data-cedula-num', String(cedAttr));
        }
        tr.setAttribute('data-retirar', retirarModeAfterInscripcion());
    }
    function appendInscRowDesdeAtleta(atleta) {
        if (!atleta || !atleta.id) return;
        var tbi = qs('fvd-sitio-tbody-insc');
        if (!tbi) return;
        var cedStr = String(atleta.cedula || '');
        var cedNum = parseInt(String(cedStr).replace(/\D/g, ''), 10) || 0;
        var tr = document.createElement('tr');
        tr.setAttribute('data-aid', String(atleta.id));
        tr.setAttribute('data-nombre', String(atleta.nombre || ''));
        tr.setAttribute('data-cedula-num', String(cedNum));
        tr.setAttribute('data-retirar', retirarModeAfterInscripcion());
        var t0 = document.createElement('td');
        var strong = document.createElement('strong');
        strong.textContent = String(atleta.nombre || '');
        t0.appendChild(strong);
        var t1 = document.createElement('td');
        t1.textContent = String(atleta.numfvd != null ? atleta.numfvd : '');
        var t2 = document.createElement('td');
        t2.textContent = cedStr;
        tr.appendChild(t0);
        tr.appendChild(t1);
        tr.appendChild(t2);
        tbi.appendChild(tr);
        updateBadges();
    }

    document.addEventListener('DOMContentLoaded', function () {
        var cedEl = qs('fvd-sitio-ced');
        if (esInd && cedEl) {
            cedEl.addEventListener('blur', buscarCedula);
            cedEl.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') { e.preventDefault(); buscarCedula(); }
            });
        }
        var btnL = qs('fvd-sitio-limpiar');
        if (btnL) btnL.addEventListener('click', limpiarLinea);
        var btnI = qs('fvd-sitio-inscribir');
        if (btnI) btnI.addEventListener('click', function () {
            if (!usuarioEncontrado || !usuarioEncontrado.id) {
                msg('Busque primero por cédula.', 'warn');
                return;
            }
            msg('Inscribiendo…', 'info');
            btnI.disabled = true;
            postInscribir(usuarioEncontrado.id).then(function (d) {
                btnI.disabled = false;
                if (d && d.ok) {
                    var n = typeof d.inscritos === 'number' ? d.inscritos : 1;
                    if (n < 1) {
                        msg('No se registró cambio (p. ej. ya inscrito).', 'warn');
                        return;
                    }
                    msg('Inscripción registrada.', 'ok');
                    var tbd = qs('fvd-sitio-tbody-disp');
                    var trEx = tbd ? tbd.querySelector('tr[data-aid="' + String(usuarioEncontrado.id) + '"]') : null;
                    if (trEx && tbd) {
                        var tbi = qs('fvd-sitio-tbody-insc');
                        if (tbi) {
                            marcarFilaInscrita(trEx);
                            tbi.appendChild(trEx);
                            updateBadges();
                        }
                    } else {
                        appendInscRowDesdeAtleta(usuarioEncontrado);
                    }
                    limpiarLinea();
                } else {
                    msg((d && d.error) ? d.error : 'No se pudo inscribir.', 'err');
                }
            }).catch(function () { btnI.disabled = false; msg('Error de red.', 'err'); });
        });

        var tbd = qs('fvd-sitio-tbody-disp');
        if (tbd) {
            tbd.addEventListener('click', function (e) {
                var tr = e.target.closest('tr');
                if (!tr || !tr.getAttribute('data-aid')) return;
                var aid = parseInt(tr.getAttribute('data-aid'), 10);
                if (!aid) return;
                tr.style.opacity = '0.6';
                postInscribir(aid).then(function (d) {
                    tr.style.opacity = '1';
                    if (d && d.ok) {
                        var n = typeof d.inscritos === 'number' ? d.inscritos : 1;
                        if (n < 1) {
                            msg('No se registró cambio (p. ej. ya inscrito).', 'warn');
                            return;
                        }
                        var tbi = qs('fvd-sitio-tbody-insc');
                        if (tbi) {
                            marcarFilaInscrita(tr);
                            tbi.appendChild(tr);
                            updateBadges();
                            msg('Atleta pasó a inscritos.', 'ok');
                        }
                    } else {
                        msg((d && d.error) ? d.error : 'Error al inscribir.', 'err');
                    }
                }).catch(function () { tr.style.opacity = '1'; msg('Error de red.', 'err'); });
            });
        }
        var tbi = qs('fvd-sitio-tbody-insc');
        if (tbi) {
            tbi.addEventListener('click', function (e) {
                var tr = e.target.closest('tr');
                if (!tr || !tr.getAttribute('data-aid')) return;
                var mode = tr.getAttribute('data-retirar') || '0';
                if (mode === '0') {
                    msg('Esta fila no admite retiro desde aquí (p. ej. pareja o equipo).', 'info');
                    return;
                }
                var nom = tr.getAttribute('data-nombre') || '';
                if (!window.confirm('¿Retirar inscripción de ' + nom + '?')) return;
                tr.style.opacity = '0.6';
                var done = function (d, netOk) {
                    tr.style.opacity = '1';
                    if (d && d.ok && netOk) {
                        tr.removeAttribute('data-retirar');
                        if (tbd) tbd.insertBefore(tr, tbd.firstChild);
                        updateBadges();
                        msg('Atleta vuelve a disponibles.', 'ok');
                    } else {
                        msg((d && d.error) ? d.error : 'No se pudo retirar.', 'err');
                    }
                };
                if (mode === 'bandera') {
                    var aid = parseInt(tr.getAttribute('data-aid'), 10);
                    if (!aid) { tr.style.opacity = '1'; return; }
                    postRetirar(aid).then(function (d) {
                        done(d, d && d.retirado);
                    }).catch(function () { tr.style.opacity = '1'; msg('Error de red.', 'err'); });
                } else if (mode === 'tabla') {
                    var ced = parseInt(tr.getAttribute('data-cedula-num') || '0', 10);
                    if (!ced) { tr.style.opacity = '1'; msg('Falta cédula numérica en la fila.', 'err'); return; }
                    postRetirarTabla(ced).then(function (d) {
                        done(d, d && d.retirado);
                    }).catch(function () { tr.style.opacity = '1'; msg('Error de red.', 'err'); });
                } else {
                    tr.style.opacity = '1';
                }
            });
        }

        var btnFin = qs('fvd-sitio-finalizar');
        var finMsg = qs('fvd-sitio-finish-msg');
        if (btnFin) {
            btnFin.addEventListener('click', function () {
                if (finMsg) finMsg.textContent = 'Cambios guardados.';
                msg('', '');
            });
        }
    });
})();
</script>
