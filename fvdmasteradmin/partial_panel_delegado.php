<?php

declare(strict_types=1);

/** @var array $delegSnap */

/** @var string $appBase */

/** @var string $urlRegistrarAtleta */

/** @var array{usado:int,max:?int,restante:?int}|null $delegCupoInsc */

/** @var list<array<string,mixed>> $delegNotifs */

/** @var int $delegNotifNoVistas */



$ai = $delegSnap['activos_inactivos'];

$cr = $delegSnap['carnet'];

$tid = $delegSnap['torneo_id'];

$tnom = (string) ($delegSnap['torneo_nombre'] ?? '');

$insC = (int) ($delegSnap['inscripciones_torneo'] ?? 0);

$tidInt = (int) ($tid ?? 0);

$delegVentana = isset($delegVentana) && is_array($delegVentana) ? $delegVentana : null;
$delegPuedeFase1 = $delegVentana !== null && !empty($delegVentana['fase1_afiliados_carnets_traspasos']);
$delegPuedeFase2 = $delegVentana !== null && !empty($delegVentana['fase2_inscripciones']);

$carnetSolicitados = (int) ($cr['pendiente'] ?? 0);

$carnetConCarnet = (int) ($cr['solicitado'] ?? 0);



$urlTorneoInscripcion = fvd_module_url('torneo_inscripcion/index.php')
    . (isset($fvd_deleg_torneo_q) && $fvd_deleg_torneo_q !== ''
        ? $fvd_deleg_torneo_q
        : ($tidInt > 0 ? '?torneo_id=' . $tidInt : ''));

$urlInscripcionTorneoTabla = fvd_module_url('inscripcion_torneo/index.php')
    . (isset($fvd_deleg_torneo_q) && $fvd_deleg_torneo_q !== ''
        ? $fvd_deleg_torneo_q
        : ($tidInt > 0 ? '?torneo_id=' . $tidInt : ''));

$urlReportesInscripciones = fvd_module_url('inscripciones/index.php')
    . (isset($fvd_deleg_torneo_q) && $fvd_deleg_torneo_q !== ''
        ? $fvd_deleg_torneo_q
        : ($tidInt > 0 ? '?torneo_id=' . $tidInt : ''));

$urlSolCarnet = $appBase . '/fvdmasteradmin/delegado_carnet_afiliados.php';

$urlReportesMovimientos = $appBase . '/modules/atletas/reporte_carnets.php';

$urlDeudaLista = fvd_module_url('deuda_asociacion/index.php');

$myAid = AuthService::idAsociacion();

$urlDeudaForm = ($tidInt > 0 && $myAid !== null && (int) $myAid > 0)

    ? fvd_module_url('deuda_asociacion/index.php') . '?action=form&tid=' . $tidInt . '&aid=' . (int) $myAid

    : $urlDeudaLista;

$urlRelacionPagos = fvd_module_url('relacion_pago/index.php');

$urlTraspaso = $appBase . '/fvdmasteradmin/solicitud_traspaso.php';

$urlDelegUltimaInvit = $appBase . '/fvdmasteradmin/delegado_entrar_torneo.php?ultima=1';



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

    <span class="fvd-deleg-alert-invites__text"><strong>Nueva invitación a torneo.</strong> Tiene <?= (int) $delegNotifNoVistas ?> notificación(es) sin abrir. Revise la <a href="#fvd-deleg-guia-inscripcion">guía vía web</a> y el bloque <a href="#fvd-deleg-torneos-invites">Invitaciones a torneos</a>, o el enlace <strong>Invitaciones</strong> en la barra superior.</span>

</div>

<?php endif; ?>

<section class="fvd-deleg-guia-web" id="fvd-deleg-guia-inscripcion" aria-label="Guía de inscripción vía web">

    <h2 class="fvd-deleg-guia-web__h">Información vía web e inscripción al torneo</h2>

    <p class="fvd-deleg-guia-web__lead">Las invitaciones y avisos de la FVD se muestran en <strong>este portal</strong><?php if (($delegNotifs ?? []) !== []): ?>: en el bloque <a href="#fvd-deleg-torneos-invites">Invitaciones a torneos</a> y<?php else: ?> y<?php endif; ?> en el enlace <strong>Invitaciones</strong> de la barra superior. Puede usar el portal como fuente principal; el correo es complementario.</p>

    <ol class="fvd-deleg-guia-web__ol">

        <li><strong>Invitación:</strong> abra el <strong>PDF</strong> si está disponible y pulse <strong>Panel del torneo</strong> para fijar el torneo en contexto y continuar el flujo.<?php if (($delegNotifNoVistas ?? 0) > 0): ?> Si tiene avisos sin abrir, también puede <a href="<?= htmlspecialchars($urlDelegUltimaInvit, ENT_QUOTES, 'UTF-8') ?>">activar la última invitación sin abrir</a>.<?php endif; ?></li>

        <li><strong>Fase 1</strong> (afiliación, carnets, traspasos): cuando la ventana del calendario lo permita, use <a href="<?= htmlspecialchars($urlRegistrarAtleta, ENT_QUOTES, 'UTF-8') ?>">Registrar atleta</a>, <a href="<?= htmlspecialchars($urlSolCarnet, ENT_QUOTES, 'UTF-8') ?>">Carnets</a> y <a href="<?= htmlspecialchars($urlTraspaso, ENT_QUOTES, 'UTF-8') ?>">Traspaso</a><?php if ($tidInt > 0): ?>, y las pantallas del torneo en contexto (<a href="<?= htmlspecialchars($urlTorneoInscripcion, ENT_QUOTES, 'UTF-8') ?>">Preparación</a>)<?php endif; ?>.<?php if ($delegVentana !== null): ?> <span class="fvd-deleg-guia-web__hint"><?= $delegPuedeFase1 ? 'Ventana de fase 1 abierta.' : 'Fase 1 aún no disponible según calendario.' ?></span><?php endif; ?></li>

        <li><strong>Fase 2</strong> (inscripciones al torneo): <a href="<?= htmlspecialchars($urlInscripcionTorneoTabla, ENT_QUOTES, 'UTF-8') ?>">Inscripción al torneo</a> y <a href="<?= htmlspecialchars($urlReportesInscripciones, ENT_QUOTES, 'UTF-8') ?>">Reportes de inscripciones</a>.<?php if ($delegVentana !== null): ?> <span class="fvd-deleg-guia-web__hint"><?= $delegPuedeFase2 ? 'Ventana de fase 2 abierta.' : 'Fase 2 aún no disponible según calendario.' ?></span><?php endif; ?></li>

    </ol>

    <?php if ($delegVentana !== null): ?>

    <p class="fvd-deleg-guia-web__estado"><strong>Calendario del torneo en contexto:</strong> <?= htmlspecialchars((string) ($delegVentana['etiqueta_fase'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>

    <?php elseif ($tidInt > 0 && $tnom !== ''): ?>

    <p class="fvd-deleg-guia-web__estado"><strong>Torneo en contexto:</strong> <?= htmlspecialchars($tnom, ENT_QUOTES, 'UTF-8') ?> — use el menú de inscripciones o una invitación para alinear fechas si no ve el estado del calendario.</p>

    <?php endif; ?>

</section>

<?php if (($delegNotifs ?? []) !== []): ?>

<section id="fvd-deleg-torneos-invites" class="fvd-deleg-notif-wrap" aria-label="Invitaciones a torneos">

    <h2 class="fvd-deleg-notif-wrap__h">Invitaciones a torneos <?= ($delegNotifNoVistas ?? 0) > 0 ? ' (' . (int) $delegNotifNoVistas . ' sin abrir)' : '' ?></h2>

    <p class="fvd-deleg-notif-wrap__p">Notificaciones <strong>vía web</strong> (solo en este portal): PDF de invitación y botón para abrir el panel del torneo y seguir con la preparación e inscripción.</p>

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

    <?php
    $fvdIndicadoresCostos = isset($delegSnap['indicadores_costos']) && is_array($delegSnap['indicadores_costos'])
        ? $delegSnap['indicadores_costos']
        : [];
    $fvdIndicadoresCostosVariant = 'delegado';
    $fvdReporteIndicadoresUrl = function_exists('fvd_module_url') ? fvd_module_url('atletas/reporte_indicadores.php') : null;
    require __DIR__ . '/includes/partial_indicadores_costos_dashboard.php';
    ?>

    <?php if ($delegVentana !== null): ?>
    <p class="fvd-mod-msg" style="margin:0 0 1rem;font-size:0.8125rem;border-left:4px solid #f59e0c;padding:8px 12px;background:rgba(245,158,11,0.12)">
        <strong>Calendario del torneo:</strong> <?= htmlspecialchars((string) ($delegVentana['etiqueta_fase'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
        <?php if (!$delegPuedeFase2): ?> Puede consultar listados; las inscripciones y retiros quedan bloqueados fuera de la fase 2.<?php endif; ?>
    </p>
    <?php endif; ?>

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

                <?php if ($delegPuedeFase1): ?>
                <a href="<?= htmlspecialchars($urlRegistrarAtleta, ENT_QUOTES, 'UTF-8') ?>">Registrar atleta</a>
                <?php else: ?>
                <span style="opacity:0.55;cursor:not-allowed" title="Alta de atletas solo en fase 1 del calendario">Registrar atleta</span>
                <?php endif; ?>

                · <a href="<?= htmlspecialchars($appBase . '/modules/atletas/index.php?action=list', ENT_QUOTES, 'UTF-8') ?>">Fichas / carnets</a>

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

                <?php if ($delegPuedeFase1): ?>
                <a class="fvd-delegado-torneos__action fvd-delegado-torneos__action--m1" href="<?= htmlspecialchars($urlSolCarnet, ENT_QUOTES, 'UTF-8') ?>">Marcar carnet (afiliados)</a>
                <?php else: ?>
                <span class="fvd-delegado-torneos__action fvd-delegado-torneos__action--m1" style="opacity:0.55;cursor:not-allowed" title="Solo en fase 1 del calendario">Marcar carnet (afiliados)</span>
                <?php endif; ?>

                <a class="fvd-delegado-torneos__action fvd-delegado-torneos__action--m2" href="<?= htmlspecialchars($urlReportesMovimientos, ENT_QUOTES, 'UTF-8') ?>">Reportes</a>

                <?php if ($delegPuedeFase1): ?>
                <a class="fvd-delegado-torneos__action fvd-delegado-torneos__action--m3" href="<?= htmlspecialchars($urlRegistrarAtleta, ENT_QUOTES, 'UTF-8') ?>">Afiliaciones · nuevo atleta</a>
                <?php else: ?>
                <span class="fvd-delegado-torneos__action fvd-delegado-torneos__action--m3" style="opacity:0.55;cursor:not-allowed" title="Solo en fase 1 del calendario">Afiliaciones · nuevo atleta</span>
                <?php endif; ?>

            </div>

            <p class="fvd-delegado-torneos__card-foot">

                <?php if ($delegPuedeFase1): ?>
                <a href="<?= htmlspecialchars($appBase . '/fvdmasteradmin/solicitud_traspaso.php', ENT_QUOTES, 'UTF-8') ?>">Traspaso</a>
                · <a href="<?= htmlspecialchars($urlRegistrarAtleta, ENT_QUOTES, 'UTF-8') ?>">Nueva afiliación (alta atleta)</a>
                <?php else: ?>
                <span style="opacity:0.55" title="Solo en fase 1 del calendario">Traspaso · Nueva afiliación</span>
                <?php endif; ?>

            </p>

        </article>



        <article class="fvd-delegado-torneos__card">

            <h2 class="fvd-delegado-torneos__card-title">

                <span class="fvd-delegado-torneos__card-icon" aria-hidden="true">

                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>

                </span>

                Finanzas

            </h2>



            <div class="fvd-delegado-torneos__btn-stack">

                <?php if ($tidInt > 0 && $myAid !== null && (int) $myAid > 0): ?>

                <form method="post" action="<?= htmlspecialchars($urlGenerarDeudaTorneo, ENT_QUOTES, 'UTF-8') ?>" style="margin:0">

                    <input type="hidden" name="_action" value="generar_deuda_torneo">

                    <input type="hidden" name="torneo_id" value="<?= (int) $tidInt ?>">

                    <button type="submit" class="fvd-delegado-torneos__action fvd-delegado-torneos__action--f0" style="width:100%;border:none;cursor:pointer;font-family:inherit;text-align:center">Generar deuda torneo</button>

                </form>

                <?php else: ?>

                <button type="button" class="fvd-delegado-torneos__action fvd-delegado-torneos__action--f0 fvd-delegado-torneos__action--disabled" disabled style="width:100%;opacity:0.55;cursor:not-allowed">Generar deuda torneo</button>

                <p style="margin:0 0 4px;font-size:0.75rem;color:var(--dt-muted)">Requiere torneo en contexto.</p>

                <?php endif; ?>

                <a class="fvd-delegado-torneos__action fvd-delegado-torneos__action--f1" href="<?= htmlspecialchars($urlDeudaLista, ENT_QUOTES, 'UTF-8') ?>">Reporte general de movimientos</a>

                <a class="fvd-delegado-torneos__action fvd-delegado-torneos__action--f2" href="<?= htmlspecialchars($urlDeudaForm, ENT_QUOTES, 'UTF-8') ?>">Editar deuda / montos detallados</a>

                <a class="fvd-delegado-torneos__action fvd-delegado-torneos__action--f3" href="<?= htmlspecialchars($urlRelacionPagos, ENT_QUOTES, 'UTF-8') ?>">Generar pagos y reportarlos</a>

            </div>

        </article>

    </div>

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

#fvd-deleg-guia-inscripcion {

    scroll-margin-top: 4.5rem;

}

.fvd-deleg-guia-web {

    margin: 0 auto 1rem;

    max-width: 72rem;

    padding: 0.85rem 1rem;

    border-radius: 10px;

    border: 1px solid rgba(59, 130, 246, 0.35);

    background: linear-gradient(135deg, rgba(239, 246, 255, 0.95), rgba(219, 234, 254, 0.65));

    color: #1e3a5f;

    font-size: 0.8125rem;

    line-height: 1.5;

    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.06);

}

.fvd-deleg-guia-web__h {

    font-size: 0.95rem;

    margin: 0 0 0.5rem;

    color: #1e3a8a;

}

.fvd-deleg-guia-web__lead { margin: 0 0 0.65rem; }

.fvd-deleg-guia-web__lead a { color: #1d4ed8; font-weight: 600; }

.fvd-deleg-guia-web__ol {

    margin: 0 0 0.5rem;

    padding-left: 1.25rem;

}

.fvd-deleg-guia-web__ol li { margin-bottom: 0.4rem; }

.fvd-deleg-guia-web__ol a { color: #1d4ed8; font-weight: 600; }

.fvd-deleg-guia-web__hint {

    font-size: 0.75rem;

    color: #475569;

    font-weight: 500;

}

.fvd-deleg-guia-web__estado {

    margin: 0;

    padding: 0.5rem 0.65rem;

    border-radius: 8px;

    background: rgba(255, 255, 255, 0.65);

    border: 1px solid rgba(59, 130, 246, 0.2);

    font-size: 0.78rem;

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

</style>

