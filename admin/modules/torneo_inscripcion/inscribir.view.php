<?php
/** @var string $selfUrl */
/** @var string $fvd_error */
/** @var string $fvd_ok */
/** @var bool $tablasOk */
/** @var bool $esFvd */
/** @var int $asocId */
/** @var int $torneoSel */
/** @var list<array<string,mixed>> $torneosAbiertos */
/** @var list<array<string,mixed>> $asociacionesSelect */
/** @var list<array<string,mixed>> $atletasDisp */
/** @var string $inscripcionApiUrl */
/** @var string $uploadsPublicBase */
/** @var array<string,mixed>|null $torneoMeta */
/** @var bool $fvd_inscripcion_bandera_modo */
/** @var list<array<string,mixed>> $inscritosBandera */
/** @var list<array{atleta_id:int,nombre:string,cedula:string,numfvd:int}> $fvdSitioDisponibles */
/** @var list<array{atleta_id:int,nombre:string,cedula:string,numfvd:int}> $fvdSitioInscritos */
/** @var list<array<string,mixed>> $fvdSitioInscritosGrupos */
/** @var int $fvd_sitio_clase */
/** @var string $fvdSitioNuevoAtletaUrl */
/** @var list<array<string,mixed>> $fvdDelegadoGrupoTorneos */
/** @var string $fvd_error_campeonato */
/** @var int $fvd_campeonato_grupo */
/** @var string $fvd_campeonato_q query &campeonato_id=… para enlaces del delegado */
/** @var string $fvd_url_admin_inscripciones_tabla URL módulo tabla inscripcion_torneo (vacío si no aplica) */
$fvd_inscripcion_bandera_modo = !empty($fvd_inscripcion_bandera_modo);
$fvd_url_admin_inscripciones_tabla = isset($fvd_url_admin_inscripciones_tabla) ? (string) $fvd_url_admin_inscripciones_tabla : '';
$fvdSitioDisponibles = $fvdSitioDisponibles ?? [];
$fvdSitioInscritos = $fvdSitioInscritos ?? [];
$fvdSitioInscritosGrupos = $fvdSitioInscritosGrupos ?? [];
$fvd_sitio_clase = isset($fvd_sitio_clase) ? (int) $fvd_sitio_clase : 0;
$fvdSitioNuevoAtletaUrl = $fvdSitioNuevoAtletaUrl ?? (rtrim((string) (function_exists('env') ? env('APP_BASE_PATH', '') : ''), '/') . '/modules/atletas/index.php?action=form');
$fvdDelegadoGrupoTorneos = $fvdDelegadoGrupoTorneos ?? [];
$fvd_error_campeonato = $fvd_error_campeonato ?? '';
$fvd_campeonato_grupo = isset($fvd_campeonato_grupo) ? (int) $fvd_campeonato_grupo : 0;
$fvd_campeonato_q = $fvd_campeonato_q ?? '';
?>
<?php if (!empty($fvd_ok)): ?>
    <p class="fvd-mod-msg" style="color:#86efac"><?= htmlspecialchars($fvd_ok, ENT_QUOTES, 'UTF-8') ?></p>
<?php endif; ?>
<?php if (!empty($fvd_error)): ?>
    <p class="fvd-mod-msg"><?= htmlspecialchars($fvd_error, ENT_QUOTES, 'UTF-8') ?></p>
<?php endif; ?>

<?php if (!$tablasOk): ?>
    <div class="fvd-card" style="padding:1rem">
        <?php if ($fvd_inscripcion_bandera_modo): ?>
            <p>No se cumplen los requisitos para inscripción por bandera en <code>atletas</code>:</p>
            <ul>
                <li>Tabla <code>torneo_convocatoria_asoc</code> (script <code>install_torneo_convocatoria_y_publicacion.sql</code>)</li>
                <li>Columnas <code>inscripcion</code> y <code>torneo_id</code> en la tabla <code>atletas</code></li>
            </ul>
        <?php else: ?>
            <p>Faltan tablas en la base de datos. Ejecute:</p>
            <ul>
                <li><code>fvdmasteradmin/sql/install_inscripcion_torneo.sql</code></li>
                <li><code>fvdmasteradmin/sql/install_torneo_convocatoria_y_publicacion.sql</code></li>
            </ul>
        <?php endif; ?>
    </div>
<?php else: ?>

<?php if ($esFvd): ?>
    <form method="get" action="<?= htmlspecialchars($selfUrl, ENT_QUOTES, 'UTF-8') ?>" class="fvd-mod-toolbar" style="flex-wrap:wrap;align-items:flex-end;gap:10px;margin-bottom:1rem">
        <?php
        if (function_exists('fvd_master_embed_active') && fvd_master_embed_active()) {
            echo '<input type="hidden" name="embedded" value="1">' . "\n";
            echo '<input type="hidden" name="fvd_master_embed" value="1">' . "\n";
            $tidEmb = isset($_GET['torneo_id']) ? (int) $_GET['torneo_id'] : 0;
            if ($tidEmb > 0) {
                echo '<input type="hidden" name="torneo_id" value="' . $tidEmb . '">' . "\n";
            }
            $ctxEmb = isset($_GET['ctx_torneo']) ? (int) $_GET['ctx_torneo'] : 0;
            if ($ctxEmb > 0) {
                echo '<input type="hidden" name="ctx_torneo" value="' . $ctxEmb . '">' . "\n";
            }
            $campEmb = isset($_GET['campeonato_id']) ? (int) $_GET['campeonato_id'] : 0;
            if ($campEmb > 0) {
                echo '<input type="hidden" name="campeonato_id" value="' . $campEmb . '">' . "\n";
            }
            if (function_exists('fvd_master_panel_render_context_hiddens')) {
                fvd_master_panel_render_context_hiddens();
            }
        }
        ?>
        <div>
            <label style="font-size:.8125rem;color:var(--fvd-muted);display:block">Actuar como asociación</label>
            <select class="fvd-input" name="asociacion_id" style="max-width:22rem" onchange="this.form.submit()">
                <option value="0">— Elija —</option>
                <?php foreach ($asociacionesSelect as $a): ?>
                    <option value="<?= (int) ($a['id'] ?? 0) ?>" <?= $asocId === (int) ($a['id'] ?? 0) ? 'selected' : '' ?>>
                        <?= htmlspecialchars((string) ($a['nombre'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <noscript><button type="submit" class="fvd-input" style="width:auto;padding:6px 12px">Aplicar</button></noscript>
    </form>
    <?php if ($asocId <= 0): ?>
        <p class="fvd-atl-muted" style="font-size:0.875rem">Seleccione una asociación.</p>
    <?php endif; ?>
<?php elseif (!$esFvd && ($asocId === null || $asocId <= 0)): ?>
    <p class="fvd-mod-msg">Su usuario no tiene asociación asignada.</p>
<?php endif; ?>

<?php if ($asocId > 0): ?>
    <?php if ($fvd_inscripcion_bandera_modo && $fvd_error_campeonato !== ''): ?>
        <p class="fvd-mod-msg"><?= htmlspecialchars($fvd_error_campeonato, ENT_QUOTES, 'UTF-8') ?></p>
    <?php elseif ($torneoSel <= 0 && $torneosAbiertos === [] && !($fvd_inscripcion_bandera_modo && $fvdDelegadoGrupoTorneos !== [])): ?>
        <p class="fvd-atl-muted" style="font-size:0.875rem">Sin eventos disponibles.</p>
    <?php elseif ($torneoSel > 0): ?>

        <?php if ($torneoMeta === null): ?>
            <p class="fvd-mod-msg">No se encontró el torneo o no está disponible.</p>
        <?php else: ?>
            <?php
            $fvdModoSlug = preg_replace('/[^a-z0-9_-]/', '', strtolower((string) ($torneoMeta['modo'] ?? 'individual')));
            if ($fvdModoSlug === '') {
                $fvdModoSlug = 'individual';
            }
            ?>
            <link rel="stylesheet" href="<?= htmlspecialchars(url('assets/css/fvd-torneo-inscripcion.css'), ENT_QUOTES, 'UTF-8') ?>">
            <link rel="stylesheet" href="<?= htmlspecialchars(url('assets/css/fvd-inscripciones-13.css'), ENT_QUOTES, 'UTF-8') ?>">
            <link rel="stylesheet" href="<?= htmlspecialchars(url('assets/css/fvd-insc-forms-panel.css'), ENT_QUOTES, 'UTF-8') ?>">
            <div class="fvd-torneo-insc fvd-torneo-insc--modalidad-<?= htmlspecialchars($fvdModoSlug, ENT_QUOTES, 'UTF-8') ?>" id="fvd-torneo-insc-root">
            <?php if ($fvd_url_admin_inscripciones_tabla !== ''): ?>
                <nav class="fvd-mod-toolbar" style="margin:0 0 12px;padding:10px 12px;font-size:.8125rem;align-items:center;gap:10px" aria-label="Cambiar vista de inscripciones">
                    <span style="font-weight:800;color:#0f172a">Inscripción en sitio</span>
                    <span style="color:#94a3b8">|</span>
                    <a href="<?= htmlspecialchars($fvd_url_admin_inscripciones_tabla, ENT_QUOTES, 'UTF-8') ?>"
                       style="font-weight:700;color:#2e3092;text-decoration:underline;text-underline-offset:2px">
                        Administrador de inscripciones (tabla <code style="font-size:.72rem">inscripcion_torneo</code>)
                    </a>
                </nav>
            <?php endif; ?>
            <?php
            require FVD_PROJECT_ROOT . '/templates/inscripciones/inscribir_sitio_panel.php';
            $clInsc = (int) ($torneoMeta['clase'] ?? 1);
            $fvd_insc_integrantes_equipo = (int) ($torneoMeta['integrantes_equipo'] ?? 4);
            $vdVenInscDet = $torneoMeta['ventana_delegado'] ?? null;
            $fvdDelegadoInscripcionCerradaDet = !empty($fvd_inscripcion_bandera_modo)
                && is_array($vdVenInscDet)
                && !($vdVenInscDet['fase2_inscripciones'] ?? false);
            $fvdInscJsonFlags = JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
            if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
                $fvdInscJsonFlags |= JSON_INVALID_UTF8_SUBSTITUTE;
            }
            $fvdInscJson = json_encode([
                'api' => $inscripcionApiUrl,
                'torneoId' => $torneoSel,
                'asociacionId' => $asocId,
                'esFvd' => $esFvd,
                'uploadsBase' => rtrim($uploadsPublicBase, '/') . '/',
                'modo' => (string) ($torneoMeta['modo'] ?? 'individual'),
                'clase' => (int) ($torneoMeta['clase'] ?? 1),
                'maxNomina' => $clInsc === 2 ? 2 : ($clInsc === 3 ? (int) ($torneoMeta['integrantes_equipo'] ?? 4) : 80),
                'banderaMode' => $fvd_inscripcion_bandera_modo,
                'delegadoInscripcionCerrada' => $fvdDelegadoInscripcionCerradaDet,
                'reemplazarEquipoId' => 0,
            ], $fvdInscJsonFlags);
            if ($fvdInscJson === false) {
                $fvdInscJson = '{"api":"","torneoId":0,"asociacionId":0,"esFvd":false,"uploadsBase":"/","modo":"individual","clase":1,"maxNomina":80,"banderaMode":false,"delegadoInscripcionCerrada":false,"reemplazarEquipoId":0}';
            }
            ?>
            <script>
            window.FVD_INSC = <?= $fvdInscJson ?>;
            </script>
            <script>
            (function () {
                var cfg = window.FVD_INSC || {};
                var api = cfg.api || '';
                var torneoId = cfg.torneoId | 0;
                var asocId = cfg.asociacionId | 0;
                var esFvd = !!cfg.esFvd;
                var uploadsBase = cfg.uploadsBase || '';
                var modo = cfg.modo || 'individual';
                var maxNomina = Math.max(1, cfg.maxNomina | 0);
                var banderaMode = !!cfg.banderaMode;
                var delegadoInscripcionCerrada = !!cfg.delegadoInscripcionCerrada;
                var qEl = document.getElementById('fvd-insc-q');
                var btnBuscar = document.getElementById('fvd-insc-buscar');
                var results = document.getElementById('fvd-insc-results');
                var pager = document.getElementById('fvd-insc-pager');
                var msg = document.getElementById('fvd-insc-msg');
                var grid = document.getElementById('fvd-insc-resumen-grid');
                var emptyHint = document.getElementById('fvd-insc-resumen-empty');
                var btnSub = document.getElementById('fvd-insc-submit');
                var btnClear = document.getElementById('fvd-insc-clear');
                var btnGuardarEq = document.getElementById('fvd-insc-eq-btn-guardar');
                var btnNuevaEq = document.getElementById('fvd-insc-eq-btn-nueva');
                var teamNameEl = document.getElementById('fvd-insc-nombre-equipo');
                var page = 1;
                var perPage = 8;
                var nomina = [];

                function liveTorneoId() {
                    var t = (window.FVD_INSC && window.FVD_INSC.torneoId) ? (window.FVD_INSC.torneoId | 0) : 0;
                    return t > 0 ? t : torneoId;
                }

                function getClInsc() {
                    return (window.FVD_INSC && window.FVD_INSC.clase != null) ? (window.FVD_INSC.clase | 0) : (cfg.clase | 0);
                }

                function getMaxNomina() {
                    var h = document.getElementById('fvd-insc-max-nomina');
                    if (h && h.value) {
                        var v = parseInt(h.value, 10);
                        if (v > 0) return v;
                    }
                    var m = (window.FVD_INSC && window.FVD_INSC.maxNomina != null) ? (window.FVD_INSC.maxNomina | 0) : 0;
                    if (m > 0) return m;
                    return Math.max(1, maxNomina);
                }

                function usesSlots() {
                    var c = getClInsc();
                    return c === 2 || c === 3;
                }

                function apiQs(extra) {
                    var s = api.indexOf('?') >= 0 ? '&' : '?';
                    var x = extra || '';
                    if (esFvd && asocId) {
                        x += (x ? '&' : '') + 'asociacion_id=' + encodeURIComponent(String(asocId));
                    }
                    return s + x;
                }

                function fotoUrl(foto) {
                    if (!foto || !String(foto).trim()) return '';
                    var fn = String(foto).replace(/^\//, '').replace(/\\/g, '/');
                    if (fn.indexOf('..') >= 0) return '';
                    return uploadsBase + fn.split('/').map(encodeURIComponent).join('/');
                }

                function syncEquipoJugadoresUI() {
                    var cap = getMaxNomina();
                    var p;
                    for (p = 1; p <= cap; p++) {
                        var idx = p - 1;
                        var r = nomina[idx];
                        var vis = document.getElementById('fvd-eq-jug-id-vis-' + p);
                        var hid = document.getElementById('fvd-eq-jug-aid-' + p);
                        var ced = document.getElementById('fvd-eq-jug-ced-' + p);
                        var nom = document.getElementById('fvd-eq-jug-nom-' + p);
                        var btn = document.getElementById('fvd-eq-jug-clear-' + p);
                        if (!vis || !hid || !ced || !nom) continue;
                        if (r) {
                            var nf = r.numfvd | 0;
                            vis.value = nf > 0 ? String(nf) : String(r.id);
                            hid.value = String(r.id);
                            ced.value = String(r.cedula != null ? r.cedula : '');
                            nom.value = String(r.nombre || '');
                            if (btn) {
                                btn.style.display = '';
                                btn.disabled = false;
                            }
                        } else {
                            vis.value = '';
                            hid.value = '';
                            ced.value = '';
                            nom.value = '';
                            if (btn) {
                                btn.style.display = 'none';
                                btn.disabled = true;
                            }
                        }
                    }
                    if (emptyHint) emptyHint.hidden = true;
                    if (grid) grid.hidden = true;
                }

                function bindEquipoRowClearButtons() {
                    var pi;
                    for (pi = 1; pi <= 16; pi++) {
                        (function (pos) {
                            var b = document.getElementById('fvd-eq-jug-clear-' + pos);
                            if (!b) return;
                            b.addEventListener('click', function () {
                                var i = pos - 1;
                                if (i >= 0 && i < nomina.length) {
                                    nomina.splice(i, 1);
                                    renderNomina();
                                }
                            });
                        })(pi);
                    }
                }
                bindEquipoRowClearButtons();

                function renderNominaGrid() {
                    if (!grid) return;
                    grid.innerHTML = '';
                    if (nomina.length === 0) {
                        grid.hidden = true;
                        if (emptyHint) emptyHint.hidden = false;
                        return;
                    }
                    if (emptyHint) emptyHint.hidden = true;
                    grid.hidden = false;
                    nomina.forEach(function (r) {
                        var card = document.createElement('article');
                        card.className = 'fvd-insc-card';
                        card.setAttribute('data-aid', String(r.id));
                        var imgWrap = document.createElement(r.foto_url ? 'img' : 'div');
                        if (r.foto_url) {
                            imgWrap.className = 'fvd-insc-card__photo';
                            imgWrap.src = r.foto_url;
                            imgWrap.alt = '';
                        } else {
                            imgWrap.className = 'fvd-insc-card__ph';
                            imgWrap.textContent = 'Sin foto';
                        }
                        card.appendChild(imgWrap);
                        var rm = document.createElement('button');
                        rm.type = 'button';
                        rm.className = 'fvd-insc-card__rm';
                        rm.setAttribute('aria-label', 'Quitar');
                        rm.textContent = '×';
                        var aid = r.id;
                        rm.onclick = function () {
                            nomina = nomina.filter(function (x) { return x.id !== aid; });
                            renderNomina();
                        };
                        card.appendChild(rm);
                        var nm = document.createElement('div');
                        nm.className = 'fvd-insc-card__name';
                        nm.textContent = r.nombre || '';
                        card.appendChild(nm);
                        var ced = document.createElement('div');
                        ced.textContent = 'CI ' + (r.cedula || '');
                        card.appendChild(ced);
                        var cl = document.createElement('div');
                        cl.style.color = 'var(--fvd-muted,#94a3b8)';
                        cl.style.marginTop = '2px';
                        cl.textContent = r.club || '';
                        card.appendChild(cl);
                        grid.appendChild(card);
                    });
                }

                function renderNomina() {
                    if (usesSlots()) {
                        syncEquipoJugadoresUI();
                    } else {
                        renderNominaGrid();
                    }
                }

                function addToNomina(row) {
                    var id = parseInt(row.id, 10);
                    if (!id) return false;
                    if (nomina.some(function (x) { return x.id === id; })) {
                        if (msg) msg.textContent = 'Ya está en la nómina.';
                        return false;
                    }
                    var cap = getMaxNomina();
                    if (nomina.length >= cap) {
                        if (msg) msg.textContent = 'Nómina completa (' + cap + ').';
                        return false;
                    }
                    var fu = row.foto ? fotoUrl(row.foto) : '';
                    var nf = row.numfvd;
                    var numfvd = nf != null && nf !== '' ? (parseInt(nf, 10) || 0) : 0;
                    nomina.push({
                        id: id,
                        nombre: row.nombre || '',
                        cedula: row.cedula != null ? String(row.cedula) : '',
                        numfvd: numfvd,
                        club: row.asociacion_nombre || '',
                        foto_url: fu
                    });
                    if (msg) msg.textContent = '';
                    renderNomina();
                    return true;
                }

                window.fvdCargarEdicionEquipo = function (equipoNum, nombreEq, integrantes) {
                    var n = (nombreEq != null) ? String(nombreEq).trim() : '';
                    vaciarNominaEquipo();
                    if (window.FVD_INSC) {
                        window.FVD_INSC.reemplazarEquipoId = equipoNum | 0;
                    }
                    if (teamNameEl) teamNameEl.value = n;
                    (integrantes || []).forEach(function (m) {
                        addToNomina({
                            id: m.id,
                            nombre: m.nombre || '',
                            cedula: m.cedula != null ? String(m.cedula) : '',
                            numfvd: m.numfvd,
                            foto: '',
                            asociacion_nombre: ''
                        });
                    });
                    if (msg) {
                        msg.textContent = 'Nómina cargada. Ajuste integrantes o el nombre y pulse Guardar equipo.';
                    }
                };

                function renderRows(rows) {
                    if (!results) return;
                    results.innerHTML = '';
                    if (!rows || !rows.length) {
                        results.hidden = true;
                        return;
                    }
                    results.hidden = false;
                    rows.forEach(function (r) {
                        var row = document.createElement('div');
                        row.className = 'fvd-insc-results__row';
                        var meta = document.createElement('div');
                        meta.className = 'fvd-insc-results__meta';
                        meta.textContent = (r.nombre || '') + ' · CI ' + (r.cedula || '') + ' · FVD ' + (r.numfvd | 0);
                        row.appendChild(meta);
                        var b = document.createElement('button');
                        b.type = 'button';
                        b.className = 'fvd-insc-btn fvd-insc-btn--inscribir';
                        b.style.fontSize = '0.72rem';
                        b.style.padding = '4px 8px';
                        b.textContent = 'Añadir';
                        b.onclick = function () { addToNomina(r); };
                        row.appendChild(b);
                        results.appendChild(row);
                    });
                }

                function renderPager(d) {
                    if (!pager) return;
                    if (!d || !d.pages || d.pages <= 1) {
                        pager.hidden = true;
                        pager.innerHTML = '';
                        return;
                    }
                    pager.hidden = false;
                    pager.innerHTML = 'Pág. ' + (d.page | 0) + ' / ' + (d.pages | 0) + ' · ' + (d.total | 0) + ' resultado(s) ';
                    var prev = document.createElement('button');
                    prev.type = 'button';
                    prev.className = 'fvd-insc-btn fvd-insc-btn--comprobante';
                    prev.style.fontSize = '0.65rem';
                    prev.style.padding = '2px 8px';
                    prev.textContent = '←';
                    prev.disabled = (d.page | 0) <= 1;
                    prev.onclick = function () { page = Math.max(1, (d.page | 0) - 1); buscar(); };
                    var next = document.createElement('button');
                    next.type = 'button';
                    next.className = 'fvd-insc-btn fvd-insc-btn--comprobante';
                    next.style.fontSize = '0.65rem';
                    next.style.padding = '2px 8px';
                    next.textContent = '→';
                    next.disabled = (d.page | 0) >= (d.pages | 0);
                    next.onclick = function () { page = Math.min(d.pages | 0, (d.page | 0) + 1); buscar(); };
                    pager.appendChild(prev);
                    pager.appendChild(next);
                }

                function buscar() {
                    var q = qEl ? qEl.value.trim() : '';
                    if (q.length < 2) {
                        if (msg) msg.textContent = 'Escriba al menos 2 caracteres.';
                        return;
                    }
                    if (msg) msg.textContent = 'Buscando…';
                    var buscarQs = 'action=buscar&q=' + encodeURIComponent(q) + '&page=' + page + '&per_page=' + perPage;
                    if (banderaMode) {
                        var tidB = liveTorneoId();
                        if (tidB) {
                            buscarQs += '&torneo_id=' + encodeURIComponent(String(tidB));
                        }
                    }
                    var u = api + apiQs(buscarQs);
                    fetch(u, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
                        .then(function (r) { return r.json(); })
                        .then(function (d) {
                            if (d && d.ok) {
                                if (msg) msg.textContent = '';
                                renderRows(d.rows || []);
                                renderPager(d);
                            } else {
                                if (msg) msg.textContent = (d && d.error) ? d.error : 'Error.';
                            }
                        })
                        .catch(function () { if (msg) msg.textContent = 'Error de red.'; });
                }

                if (btnBuscar) btnBuscar.addEventListener('click', function () { page = 1; buscar(); });
                if (qEl) qEl.addEventListener('keydown', function (e) {
                    if (e.key === 'Enter') { e.preventDefault(); page = 1; buscar(); }
                });

                function vaciarNominaEquipo() {
                    nomina = [];
                    if (teamNameEl) teamNameEl.value = '';
                    renderNomina();
                }

                if (btnClear) btnClear.addEventListener('click', vaciarNominaEquipo);
                if (btnNuevaEq) btnNuevaEq.addEventListener('click', vaciarNominaEquipo);

                function setSubmitInscripcionBusy(busy) {
                    if (btnSub) btnSub.disabled = busy;
                    if (btnGuardarEq) btnGuardarEq.disabled = busy;
                }

                function ejecutarInscripcion() {
                    if (banderaMode && delegadoInscripcionCerrada) {
                        if (msg) msg.textContent = 'Periodo de inscripción cerrado según calendario del torneo.';
                        return;
                    }
                    if (!nomina.length) {
                        if (msg) msg.textContent = 'Añada atletas a la nómina.';
                        return;
                    }
                    var cl = getClInsc();
                    var cap = getMaxNomina();
                    if ((cl === 2 || cl === 3) && nomina.length !== cap) {
                        if (msg) msg.textContent = 'Complete los ' + cap + ' integrantes antes de inscribir.';
                        return;
                    }
                    var neq = teamNameEl && teamNameEl.value ? teamNameEl.value.trim() : '';
                    if ((cl === 2 || cl === 3) && !neq) {
                        if (msg) msg.textContent = 'Indique el nombre del equipo.';
                        return;
                    }
                    var ids = nomina.map(function (x) { return x.id; });
                    var mLive = (window.FVD_INSC && window.FVD_INSC.modo) ? String(window.FVD_INSC.modo) : modo;
                    var tipo = mLive === 'parejas' ? 'pareja' : (mLive === 'equipos' ? 'equipo' : 'individual');
                    var repl = (window.FVD_INSC && (window.FVD_INSC.reemplazarEquipoId | 0) > 0) ? (window.FVD_INSC.reemplazarEquipoId | 0) : 0;
                    var body;
                    if (repl > 0 && (cl === 2 || cl === 3) && (tipo === 'pareja' || tipo === 'equipo')) {
                        body = {
                            action: 'actualizar_equipo',
                            torneo_id: liveTorneoId(),
                            equipo: repl,
                            atleta_ids: ids,
                            nombre_equipo: neq
                        };
                    } else {
                        body = {
                            action: (tipo === 'individual' && ids.length > 1) ? 'inscribir_lote' : 'inscribir',
                            torneo_id: liveTorneoId(),
                            tipo: tipo,
                            atleta_ids: ids
                        };
                        if ((cl === 2 || cl === 3) && neq) body.nombre_equipo = neq;
                    }
                    if (esFvd) body.asociacion_id = asocId;
                    if (msg) msg.textContent = 'Enviando…';
                    setSubmitInscripcionBusy(true);
                    fetch(api, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                        body: JSON.stringify(body)
                    }).then(function (r) { return r.json(); }).then(function (d) {
                        setSubmitInscripcionBusy(false);
                        if (d && d.ok) {
                            if (window.FVD_INSC) window.FVD_INSC.reemplazarEquipoId = 0;
                            if (banderaMode) {
                                window.location.reload();
                                return;
                            }
                            if (msg) msg.textContent = 'Listo.';
                            nomina = [];
                            if (teamNameEl) teamNameEl.value = '';
                            renderNomina();
                        } else {
                            if (msg) msg.textContent = (d && d.error) ? d.error : 'Error.';
                        }
                    }).catch(function () {
                        setSubmitInscripcionBusy(false);
                        if (msg) msg.textContent = 'Error de red.';
                    });
                }

                if (btnSub) btnSub.addEventListener('click', ejecutarInscripcion);
                if (btnGuardarEq) btnGuardarEq.addEventListener('click', ejecutarInscripcion);

                if (banderaMode && delegadoInscripcionCerrada) {
                    if (btnSub) {
                        btnSub.disabled = true;
                        btnSub.style.opacity = '0.45';
                    }
                    if (btnGuardarEq) {
                        btnGuardarEq.disabled = true;
                        btnGuardarEq.style.opacity = '0.45';
                    }
                }
                if (window.FVD_INSC && typeof window.FVD_INSC === 'object') {
                    window.FVD_INSC.addToNomina = addToNomina;
                }
                renderNomina();
            })();
            </script>
            </div>
        <?php endif; ?>
    <?php endif; ?>
<?php endif; ?>

<?php endif; ?>
