<?php

declare(strict_types=1);

/** @var array $delegSnap */

/** @var string $appBase */

/** @var string $delegInscripcionApi */

/** @var string $urlRegistrarAtleta */

/** @var array{usado:int,max:?int,restante:?int}|null $delegCupoInsc */

/** @var list<array<string,mixed>> $delegNotifs */

/** @var int $delegNotifNoVistas */

/** @var array<string,mixed>|null $delegDeuda */



$fvdDelegMoney = static function (mixed $v): string {

    return number_format((float) ($v ?? 0), 2, ',', '.');

};



$ai = $delegSnap['activos_inactivos'];

$cr = $delegSnap['carnet'];

$tid = $delegSnap['torneo_id'];

$tnom = (string) ($delegSnap['torneo_nombre'] ?? '');

$insC = (int) ($delegSnap['inscripciones_torneo'] ?? 0);

$tidInt = (int) ($tid ?? 0);

$carnetSolicitados = (int) ($cr['pendiente'] ?? 0);

$carnetConCarnet = (int) ($cr['solicitado'] ?? 0);



$urlTorneoInscripcion = fvd_module_url('torneo_inscripcion/index.php') . ($tidInt > 0 ? '?torneo_id=' . $tidInt : '');

$urlInscripcionTorneoTabla = fvd_module_url('inscripcion_torneo/index.php') . ($tidInt > 0 ? '?torneo_id=' . $tidInt : '');

$urlReportesInscripciones = fvd_module_url('inscripciones/index.php');

$urlSolCarnet = $appBase . '/fvdmasteradmin/solicitud_carnet.php';

$urlReportesMovimientos = $appBase . '/modules/atletas/reporte_carnets.php';

$urlDeudaLista = fvd_module_url('deuda_asociacion/index.php');

$myAid = AuthService::idAsociacion();

$urlDeudaForm = ($tidInt > 0 && $myAid !== null && (int) $myAid > 0)

    ? fvd_module_url('deuda_asociacion/index.php') . '?action=form&tid=' . $tidInt . '&aid=' . (int) $myAid

    : $urlDeudaLista;

$urlRelacionPagos = fvd_module_url('relacion_pago/index.php');



$asocNombre = isset($fvd_topbar_asoc_nombre) ? (string) $fvd_topbar_asoc_nombre : '';

$asocLogo = isset($fvd_topbar_asoc_logo_url) ? $fvd_topbar_asoc_logo_url : null;

if (!function_exists('fvd_asoc_hero_h2_linea')) {

    require_once __DIR__ . '/includes/fvd_asociacion_helpers.php';

}

$delegHeroH2 = fvd_asoc_hero_h2_linea(

    isset($fvd_topbar_asoc_nombre_raw) ? (string) $fvd_topbar_asoc_nombre_raw : '',

    $asocNombre

);

$fvdDelegDeudaGenErr = '';

if (isset($_SESSION['fvd_delegado_deuda_err'])) {

    $fvdDelegDeudaGenErr = (string) $_SESSION['fvd_delegado_deuda_err'];

    unset($_SESSION['fvd_delegado_deuda_err']);

}

$urlGenerarDeudaTorneo = $appBase . '/fvdmasteradmin/delegado_generar_deuda_torneo.php';

?>

<?php if (isset($_GET['msg']) && $_GET['msg'] === 'notif_no'): ?>

    <p class="fvd-mod-msg">No se encontró la notificación o ya no aplica.</p>

<?php endif; ?>

<?php if (isset($_GET['msg']) && $_GET['msg'] === 'deuda_generada'): ?>

    <p class="fvd-mod-msg" style="color:#15803d">Deuda del torneo generada: conteos de atletas del club (inscripción, afiliación, anualidad, carnet, traspaso) multiplicados por la última fila de <code>costos</code>.</p>

<?php endif; ?>

<?php if (isset($_GET['msg']) && $_GET['msg'] === 'deuda_gen_sin_torneo'): ?>

    <p class="fvd-mod-msg">Indique un torneo en contexto para generar la deuda.</p>

<?php endif; ?>

<?php if (isset($_GET['msg']) && $_GET['msg'] === 'deuda_gen_sin_asoc'): ?>

    <p class="fvd-mod-msg">Su usuario no tiene asociación asignada.</p>

<?php endif; ?>

<?php if (isset($_GET['msg']) && $_GET['msg'] === 'deuda_gen_denegado'): ?>

    <p class="fvd-mod-msg">No tiene permiso para generar deuda de esa asociación.</p>

<?php endif; ?>

<?php if (isset($_GET['msg']) && $_GET['msg'] === 'deuda_gen_error'): ?>

    <p class="fvd-mod-msg"><?= $fvdDelegDeudaGenErr !== '' ? htmlspecialchars($fvdDelegDeudaGenErr, ENT_QUOTES, 'UTF-8') : 'No se pudo generar la deuda.' ?></p>

<?php endif; ?>

<?php if (($delegNotifNoVistas ?? 0) > 0): ?>

<div class="fvd-deleg-alert-invites" role="status" aria-live="polite">

    <span class="fvd-deleg-alert-invites__text"><strong>Nueva invitación a torneo.</strong> Tiene <?= (int) $delegNotifNoVistas ?> notificación(es) sin abrir. Revise el bloque <a href="#fvd-deleg-torneos-invites">Invitaciones a torneos</a> o el enlace <strong>Invitaciones</strong> en la barra superior.</span>

</div>

<?php endif; ?>

<?php if (($delegNotifs ?? []) !== []): ?>

<section id="fvd-deleg-torneos-invites" class="fvd-deleg-notif-wrap" aria-label="Invitaciones a torneos">

    <h2 class="fvd-deleg-notif-wrap__h">Invitaciones a torneos <?= ($delegNotifNoVistas ?? 0) > 0 ? ' (' . (int) $delegNotifNoVistas . ' sin abrir)' : '' ?></h2>

    <p class="fvd-deleg-notif-wrap__p">Avisos de la FVD con PDF de invitación y acceso al panel del torneo.</p>

    <ul class="fvd-deleg-notif-wrap__ul">

        <?php foreach ($delegNotifs as $nf): ?>

            <?php

            $nid = (int) ($nf['id'] ?? 0);

            $tn = htmlspecialchars((string) ($nf['torneo_nombre'] ?? ''), ENT_QUOTES, 'UTF-8');

            $fd = htmlspecialchars(substr((string) ($nf['fechator'] ?? ''), 0, 10), ENT_QUOTES, 'UTF-8');

            $sinAbrir = empty($nf['visto_en']);

            $entrar = $appBase . '/fvdmasteradmin/delegado_entrar_torneo.php?notif_id=' . $nid;

            $pdf = $appBase . '/fvdmasteradmin/delegado_invitacion_pdf.php?notif_id=' . $nid;

            ?>

            <li class="fvd-deleg-notif-wrap__li">

                <span class="fvd-deleg-notif-wrap__tn"><?= $tn ?></span>

                <span class="fvd-deleg-notif-wrap__fd"><?= $fd ?></span>

                <?php if ($sinAbrir): ?><span class="fvd-deleg-notif-wrap__new">Nuevo</span><?php endif; ?>

                <span class="fvd-deleg-notif-wrap__sp"></span>

                <?php if (!empty($nf['invitacion_archivo'])): ?>

                    <a class="fvd-btn fvd-btn--secondary fvd-deleg-notif-wrap__btn" href="<?= htmlspecialchars($pdf, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">PDF</a>

                <?php endif; ?>

                <a class="fvd-btn fvd-btn--primary fvd-deleg-notif-wrap__btn" href="<?= htmlspecialchars($entrar, ENT_QUOTES, 'UTF-8') ?>">Panel del torneo</a>

            </li>

        <?php endforeach; ?>

    </ul>

</section>

<?php endif; ?>



<div class="fvd-delegado-torneos">

    <header class="fvd-delegado-torneos__hero">

        <?php if ($asocLogo !== null && $asocLogo !== ''): ?>

            <img class="fvd-delegado-torneos__logo" src="<?= htmlspecialchars($asocLogo, ENT_QUOTES, 'UTF-8') ?>" alt="" width="72" height="72" loading="lazy">

        <?php else: ?>

            <div class="fvd-delegado-torneos__logo fvd-delegado-torneos__logo--ph" aria-hidden="true"></div>

        <?php endif; ?>

        <div class="fvd-delegado-torneos__hero-text">

            <h2 class="fvd-delegado-torneos__title"><?= htmlspecialchars($delegHeroH2, ENT_QUOTES, 'UTF-8') ?></h2>

            <h3 class="fvd-delegado-torneos__subtitle">Panel de administración de torneos</h3>

            <?php if ($tidInt > 0 && $tnom !== ''): ?>

                <p class="fvd-delegado-torneos__ctx">Torneo en contexto: <strong><?= htmlspecialchars($tnom, ENT_QUOTES, 'UTF-8') ?></strong></p>

            <?php else: ?>

                <p class="fvd-delegado-torneos__ctx">Sin torneo fijado en contexto; podrá elegirlo en cada módulo.</p>

            <?php endif; ?>

        </div>

    </header>



    <div class="fvd-delegado-torneos__stats" role="group" aria-label="Estadísticas">

        <div class="fvd-delegado-torneos__pill fvd-delegado-torneos__pill--a">

            <span class="fvd-delegado-torneos__pill-label">Atletas activos</span>

            <span class="fvd-delegado-torneos__pill-val"><?= (int) ($ai['activos'] ?? 0) ?></span>

        </div>

        <div class="fvd-delegado-torneos__pill fvd-delegado-torneos__pill--b">

            <span class="fvd-delegado-torneos__pill-label">Inactivos</span>

            <span class="fvd-delegado-torneos__pill-val"><?= (int) ($ai['inactivos'] ?? 0) ?></span>

        </div>

        <div class="fvd-delegado-torneos__pill fvd-delegado-torneos__pill--c">

            <span class="fvd-delegado-torneos__pill-label">Carnets solicitados</span>

            <span class="fvd-delegado-torneos__pill-val"><?= $carnetSolicitados ?></span>

            <span class="fvd-delegado-torneos__pill-sub">con carnet: <?= $carnetConCarnet ?></span>

        </div>

        <div class="fvd-delegado-torneos__pill fvd-delegado-torneos__pill--d">

            <span class="fvd-delegado-torneos__pill-label">Inscripciones</span>

            <span class="fvd-delegado-torneos__pill-val"><?= $insC ?></span>

            <?php if ($tidInt > 0 && is_array($delegCupoInsc ?? null)): ?>

                <?php $dc = $delegCupoInsc; $dmax = $dc['max'] ?? null; ?>

                <span class="fvd-delegado-torneos__pill-sub">

                    <?php if ($dmax === null): ?>

                        cupo <?= (int) ($dc['usado'] ?? 0) ?> (sin tope)

                    <?php else: ?>

                        cupo <?= (int) ($dc['usado'] ?? 0) ?> / <?= (int) $dmax ?>

                    <?php endif; ?>

                </span>

            <?php endif; ?>

        </div>

    </div>



    <div class="fvd-delegado-torneos__grid">

        <article class="fvd-delegado-torneos__card">

            <h2 class="fvd-delegado-torneos__card-title">

                <span class="fvd-delegado-torneos__card-icon" aria-hidden="true">

                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>

                </span>

                Inscripciones

            </h2>

            <div class="fvd-delegado-torneos__btn-stack">

                <a class="fvd-delegado-torneos__action fvd-delegado-torneos__action--i1" href="<?= htmlspecialchars($urlTorneoInscripcion, ENT_QUOTES, 'UTF-8') ?>">Inscripción torneo</a>

                <a class="fvd-delegado-torneos__action fvd-delegado-torneos__action--i2" href="<?= htmlspecialchars($urlInscripcionTorneoTabla, ENT_QUOTES, 'UTF-8') ?>">Administrador de inscripciones</a>

                <a class="fvd-delegado-torneos__action fvd-delegado-torneos__action--i3" href="<?= htmlspecialchars($urlReportesInscripciones, ENT_QUOTES, 'UTF-8') ?>">Reportes</a>

            </div>

            <p class="fvd-delegado-torneos__card-foot">

                <a href="<?= htmlspecialchars($urlRegistrarAtleta, ENT_QUOTES, 'UTF-8') ?>">Registrar atleta</a>

                · <a href="<?= htmlspecialchars($appBase . '/modules/atletas/index.php?tab=ficha', ENT_QUOTES, 'UTF-8') ?>">Fichas / carnets</a>

            </p>

        </article>



        <article class="fvd-delegado-torneos__card">

            <h2 class="fvd-delegado-torneos__card-title">

                <span class="fvd-delegado-torneos__card-icon" aria-hidden="true">

                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>

                </span>

                Movimientos

            </h2>

            <div class="fvd-delegado-torneos__btn-stack">

                <a class="fvd-delegado-torneos__action fvd-delegado-torneos__action--m1" href="<?= htmlspecialchars($urlSolCarnet, ENT_QUOTES, 'UTF-8') ?>">Solicitud carnets</a>

                <a class="fvd-delegado-torneos__action fvd-delegado-torneos__action--m2" href="<?= htmlspecialchars($urlReportesMovimientos, ENT_QUOTES, 'UTF-8') ?>">Reportes</a>

                <a class="fvd-delegado-torneos__action fvd-delegado-torneos__action--m3" href="<?= htmlspecialchars($urlRegistrarAtleta, ENT_QUOTES, 'UTF-8') ?>">Afiliaciones · nuevo atleta</a>

            </div>

            <p class="fvd-delegado-torneos__card-foot">

                <a href="<?= htmlspecialchars($appBase . '/fvdmasteradmin/solicitud_traspaso.php', ENT_QUOTES, 'UTF-8') ?>">Traspaso</a>

                · <a href="<?= htmlspecialchars($appBase . '/fvdmasteradmin/solicitud_afiliacion.php', ENT_QUOTES, 'UTF-8') ?>">Afiliación</a>

            </p>

        </article>



        <article class="fvd-delegado-torneos__card">

            <h2 class="fvd-delegado-torneos__card-title">

                <span class="fvd-delegado-torneos__card-icon" aria-hidden="true">

                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>

                </span>

                Finanzas

            </h2>



            <div class="fvd-delegado-torneos__deuda-box">

                <h3 class="fvd-delegado-torneos__deuda-h">Deuda detallada<?= $tidInt > 0 ? ' (torneo actual)' : '' ?></h3>

                <?php if (is_array($delegDeuda ?? null) && $delegDeuda !== [] && isset($delegDeuda['monto_total_eur']) && $delegDeuda['monto_total_eur'] !== null && (float) $delegDeuda['monto_total_eur'] > 0): ?>

                    <p class="fvd-delegado-torneos__deuda-empty" style="margin:0 0 8px;font-size:0.875rem">Montos en <strong>EUR</strong> (deuda canónica; los pagos descuentan en EUR a tasa BCV por recibo).</p>

                <?php endif; ?>

                <?php if (is_array($delegDeuda ?? null) && $delegDeuda !== []): ?>

                    <dl class="fvd-delegado-torneos__deuda-dl">

                        <div><dt>Inscritos</dt><dd><?= $fvdDelegMoney($delegDeuda['monto_inscritos'] ?? 0) ?> <span class="fvd-delegado-torneos__deuda-n">(<?= (int) ($delegDeuda['total_inscritos'] ?? 0) ?>)</span></dd></div>

                        <div><dt>Afiliaciones</dt><dd><?= $fvdDelegMoney($delegDeuda['monto_afiliados'] ?? 0) ?> <span class="fvd-delegado-torneos__deuda-n">(<?= (int) ($delegDeuda['total_afiliados'] ?? 0) ?>)</span></dd></div>

                        <div><dt>Anualidad</dt><dd><?= $fvdDelegMoney($delegDeuda['monto_anualidad'] ?? 0) ?> <span class="fvd-delegado-torneos__deuda-n">(<?= (int) ($delegDeuda['total_anualidad'] ?? 0) ?>)</span></dd></div>

                        <div><dt>Carnets</dt><dd><?= $fvdDelegMoney($delegDeuda['monto_carnets'] ?? 0) ?> <span class="fvd-delegado-torneos__deuda-n">(<?= (int) ($delegDeuda['total_carnets'] ?? 0) ?>)</span></dd></div>

                        <div><dt>Traspasos</dt><dd><?= $fvdDelegMoney($delegDeuda['monto_traspasos'] ?? 0) ?> <span class="fvd-delegado-torneos__deuda-n">(<?= (int) ($delegDeuda['total_traspasos'] ?? 0) ?>)</span></dd></div>

                        <div class="fvd-delegado-torneos__deuda-total"><dt>Total</dt><dd><?= $fvdDelegMoney($delegDeuda['monto_total'] ?? 0) ?></dd></div>

                    </dl>

                <?php else: ?>

                    <p class="fvd-delegado-torneos__deuda-empty">No hay fila de deuda para su asociación y el torneo en contexto. Consulte el listado general o solicite el alta en la FVD.</p>

                <?php endif; ?>

            </div>



            <div class="fvd-delegado-torneos__btn-stack">

                <?php if ($tidInt > 0 && $myAid !== null && (int) $myAid > 0): ?>

                <form method="post" action="<?= htmlspecialchars($urlGenerarDeudaTorneo, ENT_QUOTES, 'UTF-8') ?>" style="margin:0">

                    <input type="hidden" name="_action" value="generar_deuda_torneo">

                    <input type="hidden" name="torneo_id" value="<?= (int) $tidInt ?>">

                    <button type="submit" class="fvd-delegado-torneos__action fvd-delegado-torneos__action--f0" style="width:100%;border:none;cursor:pointer;font-family:inherit;text-align:center">Generar deuda torneo</button>

                </form>

                <?php else: ?>

                <button type="button" class="fvd-delegado-torneos__action fvd-delegado-torneos__action--f0 fvd-delegado-torneos__action--disabled" disabled style="width:100%;opacity:0.55;cursor:not-allowed">Generar deuda torneo</button>

                <p class="fvd-delegado-torneos__deuda-empty" style="margin:0 0 4px">Requiere torneo en contexto.</p>

                <?php endif; ?>

                <a class="fvd-delegado-torneos__action fvd-delegado-torneos__action--f1" href="<?= htmlspecialchars($urlDeudaLista, ENT_QUOTES, 'UTF-8') ?>">Reporte general de movimientos</a>

                <a class="fvd-delegado-torneos__action fvd-delegado-torneos__action--f2" href="<?= htmlspecialchars($urlDeudaForm, ENT_QUOTES, 'UTF-8') ?>">Editar deuda / montos detallados</a>

                <a class="fvd-delegado-torneos__action fvd-delegado-torneos__action--f3" href="<?= htmlspecialchars($urlRelacionPagos, ENT_QUOTES, 'UTF-8') ?>">Generar pagos y reportarlos</a>

            </div>

        </article>

    </div>



    <?php if ($tid !== null && $tid > 0): ?>

    <section class="fvd-delegado-torneos__ajax">

        <h2 class="fvd-delegado-torneos__ajax-h">Inscripción rápida al torneo</h2>

        <p class="fvd-delegado-torneos__ajax-p">Torneo #<?= (int) $tid ?> — <?= htmlspecialchars($tnom, ENT_QUOTES, 'UTF-8') ?></p>

        <label class="fvd-delegado-torneos__ajax-l" for="fvd-del-insc-q">Buscar atleta del club</label>

        <div class="fvd-delegado-torneos__ajax-row">

            <input type="search" id="fvd-del-insc-q" class="fvd-delegado-torneos__ajax-input" placeholder="Nombre o cédula" autocomplete="off">

            <button type="button" class="fvd-delegado-torneos__ajax-btn" id="fvd-del-insc-buscar">Buscar</button>

        </div>

        <ul id="fvd-del-insc-res" class="fvd-delegado-torneos__ajax-ul"></ul>

        <p id="fvd-del-insc-msg" class="fvd-delegado-torneos__ajax-msg" aria-live="polite"></p>

    </section>

    <script>

    (function () {

        var api = <?= json_encode($delegInscripcionApi, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

        var torneoId = <?= (int) $tid ?>;

        var qEl = document.getElementById('fvd-del-insc-q');

        var btn = document.getElementById('fvd-del-insc-buscar');

        var list = document.getElementById('fvd-del-insc-res');

        var msg = document.getElementById('fvd-del-insc-msg');

        function render(rows) {

            list.innerHTML = '';

            if (!rows || !rows.length) {

                list.innerHTML = '<li class="fvd-delegado-torneos__ajax-li">Sin resultados.</li>';

                return;

            }

            rows.forEach(function (r) {

                var li = document.createElement('li');

                li.className = 'fvd-delegado-torneos__ajax-li';

                li.textContent = (r.nombre || '') + ' · CI ' + (r.cedula || '') + ' ';

                var b = document.createElement('button');

                b.type = 'button';

                b.textContent = 'Inscribir';

                b.className = 'fvd-delegado-torneos__ajax-inscribir';

                b.onclick = function () {

                    msg.textContent = 'Inscribiendo…';

                    fetch(api, {

                        method: 'POST',

                        credentials: 'same-origin',

                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },

                        body: JSON.stringify({ action: 'inscribir', torneo_id: torneoId, atleta_id: parseInt(r.id, 10) })

                    }).then(function (x) { return x.json(); }).then(function (d) {

                        if (d && d.ok) {

                            msg.textContent = d.inscritos ? 'Inscrito.' : 'Ya estaba inscrito o no se pudo.';

                        } else {

                            msg.textContent = (d && d.error) ? d.error : 'Error.';

                        }

                    }).catch(function () { msg.textContent = 'Error de red.'; });

                };

                li.appendChild(b);

                list.appendChild(li);

            });

        }

        function buscar() {

            var q = qEl ? qEl.value.trim() : '';

            if (q.length < 2) {

                if (msg) { msg.textContent = 'Escriba al menos 2 caracteres.'; }

                return;

            }

            if (msg) { msg.textContent = 'Buscando…'; }

            fetch(api + (api.indexOf('?') >= 0 ? '&' : '?') + 'action=buscar&q=' + encodeURIComponent(q) + (torneoId > 0 ? '&torneo_id=' + encodeURIComponent(String(torneoId)) : ''), { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })

                .then(function (r) { return r.json(); })

                .then(function (d) {

                    if (d && d.ok) {

                        if (msg) { msg.textContent = ''; }

                        render(d.rows || []);

                    } else {

                        if (msg) { msg.textContent = (d && d.error) ? d.error : 'Error.'; }

                    }

                })

                .catch(function () { if (msg) { msg.textContent = 'Error de red.'; } });

        }

        if (btn) { btn.addEventListener('click', buscar); }

        if (qEl) { qEl.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); buscar(); } }); }

    })();

    </script>

    <?php endif; ?>

</div>



<style>

.fvd-deleg-alert-invites {

    margin: 0 auto 0.85rem;

    max-width: 72rem;

    padding: 0.65rem 0.85rem;

    border-radius: 10px;

    border: 1px solid rgba(245, 158, 11, 0.55);

    background: linear-gradient(135deg, rgba(254, 243, 199, 0.95), rgba(253, 230, 138, 0.88));

    color: #78350f;

    font-size: 0.8125rem;

    line-height: 1.45;

    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.08);

}

.fvd-deleg-alert-invites__text a {

    color: #b45309;

    font-weight: 700;

    text-decoration: underline;

}

#fvd-deleg-torneos-invites {

    scroll-margin-top: 4.5rem;

}

.fvd-deleg-notif-wrap {

    margin-bottom: 1rem;

    padding: 12px 14px;

    border: 1px solid rgba(250, 204, 21, 0.35);

    border-radius: 10px;

    max-width: 72rem;

    margin-left: auto;

    margin-right: auto;

    background: rgba(250, 204, 21, 0.12);

    color: var(--fvd-text);

}

.fvd-deleg-notif-wrap__h { font-size: 0.95rem; margin: 0 0 6px; }

.fvd-deleg-notif-wrap__p { font-size: 0.75rem; color: var(--fvd-muted); margin: 0 0 10px; }

.fvd-deleg-notif-wrap__ul { list-style: none; margin: 0; padding: 0; font-size: 0.8125rem; }

.fvd-deleg-notif-wrap__li {

    padding: 8px 0;

    border-bottom: 1px solid rgba(255,255,255,.1);

    display: flex;

    flex-wrap: wrap;

    gap: 8px;

    align-items: center;

}

.fvd-deleg-notif-wrap__tn { font-weight: 600; }

.fvd-deleg-notif-wrap__fd { color: var(--fvd-muted); }

.fvd-deleg-notif-wrap__new { font-size: 0.7rem; background: rgba(239,68,68,.25); padding: 2px 6px; border-radius: 4px; }

.fvd-deleg-notif-wrap__sp { flex: 1; }

.fvd-deleg-notif-wrap__btn { font-size: 0.75rem !important; padding: 4px 10px !important; }



.fvd-delegado-torneos {

    --dt-bg: #e8edf3;

    --dt-card: #ffffff;

    --dt-text: #0f172a;

    --dt-muted: #64748b;

    --dt-border: #e2e8f0;

    background: var(--dt-bg);

    color: var(--dt-text);

    border-radius: 14px;

    padding: 1.25rem 1.1rem 1.5rem;

    max-width: 72rem;

    margin: 0 auto 1rem;

    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.08);

}

.fvd-delegado-torneos__hero {

    display: flex;

    align-items: center;

    gap: 1rem;

    background: var(--dt-card);

    border: 1px solid var(--dt-border);

    border-radius: 12px;

    padding: 1rem 1.15rem;

    margin-bottom: 1rem;

    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.06);

}

.fvd-delegado-torneos__logo {

    width: 72px;

    height: 72px;

    object-fit: contain;

    border-radius: 10px;

    border: 1px solid var(--dt-border);

    background: #f8fafc;

    flex-shrink: 0;

}

.fvd-delegado-torneos__logo--ph {

    background: linear-gradient(135deg, #cbd5e1, #94a3b8);

}

.fvd-delegado-torneos__title {

    font-size: clamp(1.2rem, 2.6vw, 1.55rem);

    font-weight: 700;

    margin: 0 0 0.35rem;

    color: var(--dt-text);

    line-height: 1.25;

}

.fvd-delegado-torneos__subtitle {

    font-size: clamp(0.95rem, 2vw, 1.12rem);

    font-weight: 600;

    margin: 0 0 0.15rem;

    color: #334155;

    line-height: 1.3;

}

.fvd-delegado-torneos__ctx {

    margin: 0.5rem 0 0;

    font-size: 0.8125rem;

    color: var(--dt-muted);

}

.fvd-delegado-torneos__stats {

    display: flex;

    flex-wrap: wrap;

    gap: 0.65rem;

    margin-bottom: 1.1rem;

}

.fvd-delegado-torneos__pill {

    flex: 1 1 140px;

    min-width: 120px;

    background: var(--dt-card);

    border: 1px solid var(--dt-border);

    border-radius: 10px;

    padding: 0.65rem 0.85rem;

    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.05);

    border-left: 4px solid #94a3b8;

}

.fvd-delegado-torneos__pill--a { border-left-color: #0ea5e9; }

.fvd-delegado-torneos__pill--b { border-left-color: #8b5cf6; }

.fvd-delegado-torneos__pill--c { border-left-color: #10b981; }

.fvd-delegado-torneos__pill--d { border-left-color: #f59e0b; }

.fvd-delegado-torneos__pill-label {

    display: block;

    font-size: 0.7rem;

    font-weight: 600;

    text-transform: uppercase;

    letter-spacing: 0.03em;

    color: var(--dt-muted);

}

.fvd-delegado-torneos__pill-val {

    display: block;

    font-size: 1.5rem;

    font-weight: 700;

    line-height: 1.15;

    margin-top: 0.15rem;

}

.fvd-delegado-torneos__pill-sub {

    display: block;

    font-size: 0.68rem;

    color: var(--dt-muted);

    margin-top: 0.2rem;

}

.fvd-delegado-torneos__grid {

    display: grid;

    grid-template-columns: repeat(3, minmax(0, 1fr));

    gap: 1rem;

    align-items: stretch;

}

@media (max-width: 960px) {

    .fvd-delegado-torneos__grid { grid-template-columns: 1fr; }

}

.fvd-delegado-torneos__card {

    background: var(--dt-card);

    border: 1px solid var(--dt-border);

    border-radius: 12px;

    padding: 1rem 0.9rem 0.85rem;

    box-shadow: 0 2px 8px rgba(15, 23, 42, 0.06);

    display: flex;

    flex-direction: column;

    min-height: 100%;

}

.fvd-delegado-torneos__card-title {

    display: flex;

    align-items: center;

    gap: 0.45rem;

    font-size: 1rem;

    font-weight: 700;

    margin: 0 0 0.75rem;

    color: #1e293b;

}

.fvd-delegado-torneos__card-icon {

    display: flex;

    color: #475569;

}

.fvd-delegado-torneos__card-icon svg { width: 1.35rem; height: 1.35rem; }

.fvd-delegado-torneos__btn-stack {

    display: flex;

    flex-direction: column;

    gap: 0.45rem;

    flex: 1;

}

.fvd-delegado-torneos__action {

    display: block;

    width: 100%;

    text-align: center;

    text-decoration: none;

    font-weight: 600;

    font-size: 0.8125rem;

    padding: 0.55rem 0.65rem;

    border-radius: 8px;

    border: none;

    color: #0f172a;

    transition: filter 0.15s ease, transform 0.1s ease;

    box-sizing: border-box;

}

.fvd-delegado-torneos__action:hover { filter: brightness(1.05); transform: translateY(-1px); }

.fvd-delegado-torneos__action--i1 { background: #cbd5e1; color: #1e293b; }

.fvd-delegado-torneos__action--i2 { background: #bae6fd; color: #0c4a6e; }

.fvd-delegado-torneos__action--i3 { background: #a7f3d0; color: #064e3b; }

.fvd-delegado-torneos__action--m1 { background: #c7d2fe; color: #312e81; }

.fvd-delegado-torneos__action--m2 { background: #fde68a; color: #78350f; }

.fvd-delegado-torneos__action--m3 { background: #a7f3d0; color: #064e3b; }

.fvd-delegado-torneos__action--f0 { background: #38bdf8; color: #0c4a6e; }

.fvd-delegado-torneos__action--f1 { background: #ddd6fe; color: #4c1d95; }

.fvd-delegado-torneos__action--f2 { background: #fbcfe8; color: #831843; }

.fvd-delegado-torneos__action--f3 { background: #6ee7b7; color: #064e3b; }

.fvd-delegado-torneos__card-foot {

    margin: 0.75rem 0 0;

    font-size: 0.72rem;

    color: var(--dt-muted);

}

.fvd-delegado-torneos__card-foot a { color: #2563eb; font-weight: 600; }

.fvd-delegado-torneos__deuda-box {

    background: #f8fafc;

    border: 1px solid var(--dt-border);

    border-radius: 10px;

    padding: 0.65rem 0.75rem;

    margin-bottom: 0.65rem;

}

.fvd-delegado-torneos__deuda-h {

    font-size: 0.78rem;

    font-weight: 700;

    text-transform: uppercase;

    letter-spacing: 0.04em;

    color: var(--dt-muted);

    margin: 0 0 0.5rem;

}

.fvd-delegado-torneos__deuda-dl {

    margin: 0;

    display: flex;

    flex-direction: column;

    gap: 0.35rem;

}

.fvd-delegado-torneos__deuda-dl > div {

    display: flex;

    justify-content: space-between;

    align-items: baseline;

    gap: 0.5rem;

    font-size: 0.78rem;

    border-bottom: 1px dashed var(--dt-border);

    padding-bottom: 0.3rem;

}

.fvd-delegado-torneos__deuda-dl > div:last-child { border-bottom: none; }

.fvd-delegado-torneos__deuda-dl dt { margin: 0; color: #475569; font-weight: 600; }

.fvd-delegado-torneos__deuda-dl dd { margin: 0; font-weight: 700; color: #0f172a; text-align: right; }

.fvd-delegado-torneos__deuda-n { font-weight: 500; color: var(--dt-muted); font-size: 0.7rem; }

.fvd-delegado-torneos__deuda-total dt, .fvd-delegado-torneos__deuda-total dd { font-size: 0.88rem; color: #0f172a; }

.fvd-delegado-torneos__deuda-empty { margin: 0; font-size: 0.75rem; color: var(--dt-muted); line-height: 1.4; }

.fvd-delegado-torneos__ajax {

    margin-top: 1.1rem;

    padding: 0.85rem 1rem;

    background: var(--dt-card);

    border: 1px solid var(--dt-border);

    border-radius: 12px;

}

.fvd-delegado-torneos__ajax-h { font-size: 0.95rem; margin: 0 0 0.35rem; }

.fvd-delegado-torneos__ajax-p { font-size: 0.75rem; color: var(--dt-muted); margin: 0 0 0.5rem; }

.fvd-delegado-torneos__ajax-l { font-size: 0.7rem; color: var(--dt-muted); display: block; margin-bottom: 0.25rem; }

.fvd-delegado-torneos__ajax-row { display: flex; flex-wrap: wrap; gap: 0.4rem; margin-bottom: 0.35rem; }

.fvd-delegado-torneos__ajax-input {

    padding: 0.4rem 0.55rem;

    font-size: 0.8125rem;

    border: 1px solid var(--dt-border);

    border-radius: 6px;

    max-width: 16rem;

    background: #fff;

    color: #0f172a;

}

.fvd-delegado-torneos__ajax-btn {

    padding: 0.4rem 0.75rem;

    font-size: 0.8125rem;

    border-radius: 6px;

    border: 1px solid #cbd5e1;

    background: #e2e8f0;

    cursor: pointer;

    font-weight: 600;

    color: #1e293b;

}

.fvd-delegado-torneos__ajax-ul { list-style: none; margin: 0; padding: 0; font-size: 0.8125rem; max-height: 10rem; overflow: auto; }

.fvd-delegado-torneos__ajax-li { padding: 0.35rem 0; border-bottom: 1px solid var(--dt-border); display: flex; align-items: center; gap: 0.35rem; flex-wrap: wrap; }

.fvd-delegado-torneos__ajax-inscribir {

    padding: 0.2rem 0.5rem;

    font-size: 0.72rem;

    border-radius: 4px;

    border: 1px solid #94a3b8;

    background: #f1f5f9;

    cursor: pointer;

    font-weight: 600;

    color: #1e293b;

}

.fvd-delegado-torneos__ajax-msg { font-size: 0.75rem; color: var(--dt-muted); min-height: 1rem; margin: 0.35rem 0 0; }

</style>

