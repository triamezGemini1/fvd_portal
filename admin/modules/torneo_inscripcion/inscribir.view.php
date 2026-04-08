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
/** @var string $fvdSitioNuevoAtletaUrl */
/** @var list<array<string,mixed>> $fvdDelegadoGrupoTorneos */
$fvd_inscripcion_bandera_modo = !empty($fvd_inscripcion_bandera_modo);
$fvdSitioDisponibles = $fvdSitioDisponibles ?? [];
$fvdSitioInscritos = $fvdSitioInscritos ?? [];
$fvdSitioNuevoAtletaUrl = $fvdSitioNuevoAtletaUrl ?? (rtrim((string) (function_exists('env') ? env('APP_BASE_PATH', '') : ''), '/') . '/modules/atletas/index.php?action=form');
$inscritosBandera = $inscritosBandera ?? [];
$fvdDelegadoGrupoTorneos = $fvdDelegadoGrupoTorneos ?? [];
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
    <?php if ($torneosAbiertos === []): ?>
        <p class="fvd-atl-muted" style="font-size:0.875rem">Sin eventos disponibles.</p>
    <?php elseif ($torneoSel > 0): ?>

        <?php if ($torneoMeta === null): ?>
            <p class="fvd-mod-msg">No se encontró el torneo o no está disponible.</p>
        <?php else: ?>
            <?php if ($fvd_inscripcion_bandera_modo && count($fvdDelegadoGrupoTorneos) > 1): ?>
            <form method="get" action="<?= htmlspecialchars($selfUrl, ENT_QUOTES, 'UTF-8') ?>" class="fvd-insc-delegado-grupo" style="margin:0 0 1rem">
                <label for="fvd-insc-grupo-torneo" style="font-size:.8125rem;color:var(--fvd-muted);display:block">Evento / categoría / género</label>
                <select id="fvd-insc-grupo-torneo" class="fvd-input" name="torneo_id" style="max-width:36rem" onchange="this.form.submit()">
                    <?php foreach ($fvdDelegadoGrupoTorneos as $tg): ?>
                        <?php $tgId = (int) ($tg['torneo'] ?? 0); ?>
                        <?php if ($tgId <= 0) {
                            continue;
                        } ?>
                        <option value="<?= $tgId ?>" <?= $torneoSel === $tgId ? 'selected' : '' ?>>
                            <?= htmlspecialchars((string) ($tg['nombre'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
            <?php endif; ?>
            <?php
            require FVD_PROJECT_ROOT . '/templates/inscripciones/inscribir_sitio_panel.php';
            $fvdInscDetOpen = (int) ($torneoMeta['clase'] ?? 1) !== 1;
            ?>
            <details class="fvd-insc-modalidad-details"<?= $fvdInscDetOpen ? ' open' : '' ?>>
                <summary>Parejas, equipos o búsqueda por nombre</summary>
            <link rel="stylesheet" href="<?= htmlspecialchars(url('assets/css/fvd-inscripciones-13.css'), ENT_QUOTES, 'UTF-8') ?>">

            <div class="fvd-insc-wrap" id="fvd-insc-root">
                <?php
                $clInsc = (int) ($torneoMeta['clase'] ?? 1);
                $fvd_insc_integrantes_equipo = (int) ($torneoMeta['integrantes_equipo'] ?? 4);
                if ($clInsc === 2) {
                    require FVD_PROJECT_ROOT . '/templates/inscripciones/tipo_parejas.php';
                } elseif ($clInsc === 3) {
                    require FVD_PROJECT_ROOT . '/templates/inscripciones/tipo_equipos.php';
                } else {
                    require FVD_PROJECT_ROOT . '/templates/inscripciones/tipo_individual.php';
                }
                ?>

                <div class="fvd-insc-toolbar">
                    <div class="fvd-insc-search">
                        <label style="font-size:.65rem;color:var(--fvd-muted);display:block">Buscar atleta (nombre o cédula)</label>
                        <input type="search" class="fvd-input" id="fvd-insc-q" placeholder="Mín. 2 caracteres" style="width:100%;padding:6px 8px;font-size:0.8125rem" autocomplete="off">
                    </div>
                    <button type="button" class="fvd-insc-btn fvd-insc-btn--comprobante" id="fvd-insc-buscar" style="margin-top:14px">Buscar</button>
                </div>
                <div class="fvd-insc-pager" id="fvd-insc-pager" hidden></div>
                <div class="fvd-insc-results" id="fvd-insc-results" hidden></div>
                <p class="fvd-insc-hint" id="fvd-insc-msg" aria-live="polite"></p>

                <?php
                $fvd_resumen_inscripcion = [];
                $fvd_resumen_titulo = null;
                require FVD_PROJECT_ROOT . '/templates/components/resumen_inscripcion.php';
                ?>

                <div class="fvd-insc-actions">
                    <button type="button" class="fvd-insc-btn fvd-insc-btn--inscribir" id="fvd-insc-submit">Inscribir</button>
                    <button type="button" class="fvd-insc-btn fvd-insc-btn--pendiente" id="fvd-insc-clear" type="button">Vaciar nómina</button>
                    <a class="fvd-insc-btn fvd-insc-btn--comprobante" id="fvd-insc-ver-listado" href="<?= htmlspecialchars($selfUrl . '?torneo_id=' . $torneoSel . ($esFvd ? '&asociacion_id=' . $asocId : ''), ENT_QUOTES, 'UTF-8') ?>">Actualizar página</a>
                </div>

            </div>

            <script>
            window.FVD_INSC = <?= json_encode([
                'api' => $inscripcionApiUrl,
                'torneoId' => $torneoSel,
                'asociacionId' => $asocId,
                'esFvd' => $esFvd,
                'uploadsBase' => rtrim($uploadsPublicBase, '/') . '/',
                'modo' => (string) ($torneoMeta['modo'] ?? 'individual'),
                'clase' => (int) ($torneoMeta['clase'] ?? 1),
                'maxNomina' => $clInsc === 2 ? 2 : ($clInsc === 3 ? (int) ($torneoMeta['integrantes_equipo'] ?? 4) : 80),
                'banderaMode' => $fvd_inscripcion_bandera_modo,
            ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
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
                var qEl = document.getElementById('fvd-insc-q');
                var btnBuscar = document.getElementById('fvd-insc-buscar');
                var results = document.getElementById('fvd-insc-results');
                var pager = document.getElementById('fvd-insc-pager');
                var msg = document.getElementById('fvd-insc-msg');
                var grid = document.getElementById('fvd-insc-resumen-grid');
                var emptyHint = document.getElementById('fvd-insc-resumen-empty');
                var btnSub = document.getElementById('fvd-insc-submit');
                var btnClear = document.getElementById('fvd-insc-clear');
                var page = 1;
                var perPage = 8;
                var nomina = [];

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

                function renderNomina() {
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

                function addToNomina(row) {
                    var id = parseInt(row.id, 10);
                    if (!id) return;
                    if (nomina.some(function (x) { return x.id === id; })) {
                        if (msg) msg.textContent = 'Ya está en la nómina.';
                        return;
                    }
                    if (nomina.length >= maxNomina) {
                        if (msg) msg.textContent = 'Nómina completa (' + maxNomina + ').';
                        return;
                    }
                    var fu = row.foto ? fotoUrl(row.foto) : '';
                    nomina.push({
                        id: id,
                        nombre: row.nombre || '',
                        cedula: row.cedula || '',
                        club: row.asociacion_nombre || '',
                        foto_url: fu
                    });
                    if (msg) msg.textContent = '';
                    renderNomina();
                }

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
                    if (banderaMode && torneoId) {
                        buscarQs += '&torneo_id=' + encodeURIComponent(String(torneoId));
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

                if (btnClear) btnClear.addEventListener('click', function () {
                    nomina = [];
                    renderNomina();
                });

                if (btnSub) btnSub.addEventListener('click', function () {
                    if (!nomina.length) {
                        if (msg) msg.textContent = 'Añada atletas a la nómina.';
                        return;
                    }
                    var ids = nomina.map(function (x) { return x.id; });
                    var tipo = modo === 'parejas' ? 'pareja' : (modo === 'equipos' ? 'equipo' : 'individual');
                    var body = {
                        action: (tipo === 'individual' && ids.length > 1) ? 'inscribir_lote' : 'inscribir',
                        torneo_id: torneoId,
                        tipo: tipo,
                        atleta_ids: ids
                    };
                    if (esFvd) body.asociacion_id = asocId;
                    if (msg) msg.textContent = 'Enviando…';
                    btnSub.disabled = true;
                    fetch(api, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                        body: JSON.stringify(body)
                    }).then(function (r) { return r.json(); }).then(function (d) {
                        btnSub.disabled = false;
                        if (d && d.ok) {
                            if (banderaMode) {
                                window.location.reload();
                                return;
                            }
                            if (msg) msg.textContent = 'Listo.';
                            nomina = [];
                            renderNomina();
                        } else {
                            if (msg) msg.textContent = (d && d.error) ? d.error : 'Error.';
                        }
                    }).catch(function () {
                        btnSub.disabled = false;
                        if (msg) msg.textContent = 'Error de red.';
                    });
                });

                renderNomina();
            })();
            </script>
            </details>
        <?php endif; ?>
    <?php endif; ?>
<?php endif; ?>

<?php endif; ?>
