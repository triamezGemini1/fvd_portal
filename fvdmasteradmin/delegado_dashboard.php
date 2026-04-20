<?php

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/services/AuthService.php';
AuthService::ensureSession();
AuthService::requireLogin();

$projRoot = dirname(__DIR__);
require_once $projRoot . '/config/paths.php';

/* Misma base que AuthService::appWebBase() / url(): env('APP_BASE_PATH','') vacío rompía URLs (p. ej. API inscripción). */
$appBase = rtrim((string) BASE_URL, '/');

if (AuthService::isAthletePortalUser()) {
    header('Location: ' . $appBase . '/fvdmasteradmin/atleta/mi_ficha.php');
    exit;
}

if (!AuthService::isDelegadoAsociacion()) {
    header('Location: ' . AuthService::homeUrl());
    exit;
}

require_once $projRoot . '/fvdmasteradmin/config/db.php';
require_once $projRoot . '/src/Services/StatsService.php';
require_once $projRoot . '/src/Services/DelegadoTorneoNotifService.php';
require_once $projRoot . '/src/Services/InscripcionService.php';
require_once $projRoot . '/src/Services/DelegadoTorneoVentanasService.php';
require_once $projRoot . '/src/Services/DelegadoSolicitudService.php';
require_once $projRoot . '/src/Services/FvdAdminService.php';
require_once $projRoot . '/src/Services/FvdAccessManager.php';

$fvd_delegado_boot_error = '';

try {
$pdo = fvd_db();
$delegSnap = \FvdPortal\Services\StatsService::snapshotDelegadoPanel($pdo);
$delegadoUid = (int) AuthService::userId();
$delegAid = AuthService::idAsociacion();
$delegAidInt = ($delegAid !== null && (int) $delegAid > 0) ? (int) $delegAid : null;

$delegNotifs = \FvdPortal\Services\DelegadoTorneoNotifService::listarParaDelegado($pdo, $delegadoUid, 25, $delegAidInt);
$delegNotifNoVistas = \FvdPortal\Services\DelegadoTorneoNotifService::contarNoVistas($pdo, $delegadoUid, $delegAidInt);

$delegCupoInsc = null;
$tidCtx = (int) ($delegSnap['torneo_id'] ?? 0);
$myAs = AuthService::idAsociacion();
if ($tidCtx > 0 && $myAs !== null && (int) $myAs > 0) {
    $delegCupoInsc = \FvdPortal\Services\InscripcionService::estadoCupoAsociacionBandera($pdo, $tidCtx, (int) $myAs);
}
$delegVentana = null;
if ($tidCtx > 0) {
    try {
        $aidVent = AuthService::idAsociacion();
        $delegVentana = \FvdPortal\Services\DelegadoTorneoVentanasService::estadoParaTorneo(
            $pdo,
            $tidCtx,
            $aidVent !== null && (int) $aidVent > 0 ? (int) $aidVent : null
        );
    } catch (Throwable $e) {
        $delegVentana = null;
    }
}

$fvdDelegadoGrupoTorneos = [];
$campeonatoSess = AuthService::delegadoCampeonatoGrupoId();
$campeonatoIdInt = ($campeonatoSess !== null && (int) $campeonatoSess > 0) ? (int) $campeonatoSess : 0;
$nombreCampeonatoNominal = '';
if ($campeonatoIdInt > 0) {
    \FvdPortal\Services\DelegadoTorneoNotifService::ensureCampeonatoGrupoTable($pdo);
    try {
        $stNom = $pdo->prepare('SELECT nombre_nominal FROM fvd_campeonato_grupo WHERE grupo_evento_id = :g LIMIT 1');
        $stNom->execute([':g' => $campeonatoIdInt]);
        $nombreCampeonatoNominal = trim((string) ($stNom->fetchColumn() ?: ''));
    } catch (Throwable $e) {
        $nombreCampeonatoNominal = '';
    }
    if ($nombreCampeonatoNominal === '') {
        try {
            $stFb = $pdo->prepare('SELECT nombre FROM torneosact WHERE grupo_evento_id = :g ORDER BY torneo ASC LIMIT 1');
            $stFb->execute([':g' => $campeonatoIdInt]);
            $nombreCampeonatoNominal = trim((string) ($stFb->fetchColumn() ?: ''));
        } catch (Throwable $e) {
            $nombreCampeonatoNominal = '';
        }
    }
    if ($delegAidInt !== null && $delegAidInt > 0) {
        $svcDash = new \FvdAdminService($pdo);
        $fvdDelegadoGrupoTorneos = $svcDash->torneosPorGrupoCampeonato($delegAidInt, $campeonatoIdInt);
        foreach ($fvdDelegadoGrupoTorneos as &$fvd_gt_row) {
            $fvd_gt_row['nombre_corta'] = $svcDash->nombreCortaTorneoCampeonato((string) ($fvd_gt_row['nombre'] ?? ''));
        }
        unset($fvd_gt_row);
    }
}

$balanceCampeonato = null;
if ($campeonatoIdInt > 0 && $delegAidInt !== null && $delegAidInt > 0) {
    $balanceCampeonato = \FvdPortal\Services\StatsService::obtenerBalanceCampeonato($pdo, $campeonatoIdInt, $delegAidInt);
}

$ai = $delegSnap['activos_inactivos'] ?? [];
$totalAtletasAsoc = (int) ($ai['activos'] ?? 0) + (int) ($ai['inactivos'] ?? 0);
$carnetFaltan = (int) (($delegSnap['carnet']['pendiente'] ?? 0));

$nPendTrasp = 0;
if ($delegAidInt !== null && $delegAidInt > 0) {
    try {
        \FvdPortal\Services\DelegadoSolicitudService::ensureTable($pdo);
        $pendTraspRows = \FvdPortal\Services\DelegadoSolicitudService::listarPendientesParaAsociacion($pdo, $delegAidInt);
        $nPendTrasp = is_array($pendTraspRows) ? count($pendTraspRows) : 0;
    } catch (Throwable $e) {
        $nPendTrasp = 0;
    }
}

$inscripcionApiUrl = $appBase . '/fvdmasteradmin/delegado_inscripcion_api.php';
$fvd_campeonato_q = $campeonatoIdInt > 0 ? ('&campeonato_id=' . $campeonatoIdInt) : '';

$tnom = (string) ($delegSnap['torneo_nombre'] ?? '');

if (!function_exists('fvd_module_url')) {
    require_once $projRoot . '/fvdmasteradmin/modules/_init.php';
}
if (!function_exists('fvd_append_embed_to_url')) {
    require_once $projRoot . '/config/fvd_navigation_return.php';
}

$fvd_deleg_ctx_tid = 0;
$cCtxNav = AuthService::delegadoTorneoContextId();
if ($cCtxNav !== null && (int) $cCtxNav > 0) {
    $fvd_deleg_ctx_tid = (int) $cCtxNav;
}
$fvd_deleg_campeonato_id = $campeonatoIdInt;
$fvd_deleg_torneo_q = '';
if ($fvd_deleg_ctx_tid > 0 || $fvd_deleg_campeonato_id > 0) {
    $qDel = [];
    if ($fvd_deleg_ctx_tid > 0) {
        $qDel['torneo_id'] = $fvd_deleg_ctx_tid;
    }
    if ($fvd_deleg_campeonato_id > 0) {
        $qDel['campeonato_id'] = $fvd_deleg_campeonato_id;
    }
    $fvd_deleg_torneo_q = '?' . http_build_query($qDel);
}

$fvd_deleg_asoc_q = '';
if ($delegAidInt !== null && $delegAidInt > 0) {
    $fvd_deleg_asoc_q = '?asociacion_id=' . $delegAidInt;
}

$fvd_q_nuevo = ['action' => 'form'];
$fvd_q_lista = ['action' => 'list'];
if ($delegAidInt !== null && $delegAidInt > 0) {
    $fvd_q_nuevo['asociacion_id'] = $delegAidInt;
    $fvd_q_lista['asociacion_id'] = $delegAidInt;
}

$qInformes = ['fvd_from' => 'informes'];
if ($fvd_deleg_ctx_tid > 0) {
    $qInformes['torneo_id'] = $fvd_deleg_ctx_tid;
}
if ($fvd_deleg_campeonato_id > 0) {
    $qInformes['campeonato_id'] = $fvd_deleg_campeonato_id;
}

$inscrMod = 'torneo_inscripcion/index.php' . ($fvd_deleg_torneo_q !== '' ? $fvd_deleg_torneo_q : '');
$torneoInvitacionId = $fvd_deleg_ctx_tid > 0 ? $fvd_deleg_ctx_tid : $tidCtx;
$inscripcionBloqueadaPorInvitacion = false;
if ($torneoInvitacionId > 0) {
    $inscripcionBloqueadaPorInvitacion = \FvdPortal\Services\DelegadoTorneoNotifService::invitacionPendienteDeAceptacion(
        $pdo,
        $delegadoUid,
        $torneoInvitacionId,
        $delegAidInt
    );
}

$delegadoAccess = null;
if ($torneoInvitacionId > 0) {
    $delegadoAccess = \FvdPortal\Services\FvdAccessManager::estadoDelegadoTorneo($pdo, $torneoInvitacionId, $delegAidInt);
}

$fvd_delegado_initial_state = [
    'delegadoRoutes' => [
        'atleta/afiliacion' => fvd_module_url('atletas/index.php?' . http_build_query($fvd_q_nuevo)),
        'atleta/carnet' => url('fvdmasteradmin/delegado_carnet_afiliados.php'),
        'atleta/traspaso' => url('fvdmasteradmin/delegado_bandeja_traspasos.php') . $fvd_deleg_asoc_q,
        'torneo/inscripcion' => fvd_append_embed_to_url(fvd_module_url($inscrMod)),
        'torneo/cambios' => fvd_append_embed_to_url(fvd_module_url('inscripcion_torneo/index.php' . ($fvd_deleg_torneo_q !== '' ? $fvd_deleg_torneo_q : ''))),
        'finanzas/deuda' => fvd_module_url('deuda_asociacion/index.php' . ($fvd_deleg_torneo_q !== '' ? $fvd_deleg_torneo_q : '')),
        'finanzas/pagos' => fvd_module_url('relacion_pago/index.php' . ($fvd_deleg_torneo_q !== '' ? $fvd_deleg_torneo_q : '')),
        'finanzas/consolidado' => fvd_module_url('deuda_asociacion/index.php?' . http_build_query($qInformes)),
    ],
    'notificationsUnread' => (int) $delegNotifNoVistas,
    'contextTorneoOptions' => [],
    'campeonatoId' => $campeonatoIdInt,
    'campeonatoNombre' => $nombreCampeonatoNominal,
    'currentTorneoId' => $tidCtx,
    'currentTorneoNombre' => $tnom,
    'inscripcionApiUrl' => $inscripcionApiUrl,
    'notifPollUrl' => url('fvdmasteradmin/delegado_notif_poll.php'),
    'notifAceptarUrl' => url('fvdmasteradmin/delegado_notif_aceptar.php'),
    'torneoInvitacionId' => (int) $torneoInvitacionId,
    'inscripcionBloqueadaPorInvitacion' => $inscripcionBloqueadaPorInvitacion,
    'delegadoAccess' => $delegadoAccess,
];

foreach ($fvdDelegadoGrupoTorneos as $tg) {
    $tidg = (int) ($tg['torneo'] ?? 0);
    if ($tidg <= 0) {
        continue;
    }
    $fvd_delegado_initial_state['contextTorneoOptions'][] = [
        'torneo_id' => $tidg,
        'label' => (string) ($tg['nombre_corta'] ?? $tg['nombre'] ?? ('Torneo #' . $tidg)),
    ];
}
if ($fvd_delegado_initial_state['contextTorneoOptions'] === [] && $tidCtx > 0 && $tnom !== '') {
    $fvd_delegado_initial_state['contextTorneoOptions'][] = [
        'torneo_id' => $tidCtx,
        'label' => $tnom,
    ];
}

try {
    $fvd_delegado_initial_json = json_encode(
        $fvd_delegado_initial_state,
        JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE
    );
} catch (JsonException $e) {
    $fvd_delegado_initial_json = '{}';
}

} catch (Throwable $e) {
    error_log('[delegado_dashboard] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    $fvd_delegado_boot_error = 'Error al cargar datos del panel. Si APP_DEBUG está activo, el detalle queda en el log de PHP.';
    if (function_exists('env') && in_array(strtolower((string) env('APP_DEBUG', '')), ['1', 'true', 'yes'], true)) {
        $fvd_delegado_boot_error .= ' — ' . $e->getMessage();
    }
    $fvd_delegado_initial_json = '{}';
}

/* Sin menú lateral: layout_header aplica fvd-shell--no-sidebar para delegados (contenido a ancho completo). */
$fvd_hide_sidebar = true;
$fvdDdCssV = (string) (@filemtime($projRoot . '/assets/css/fvd-delegado-dashboard.css') ?: time());
require_once __DIR__ . '/includes/vite_assets.php';
$fvd_head_extra_html = '<link rel="preconnect" href="https://fonts.googleapis.com">'
    . '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>'
    . '<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">'
    . '<link rel="stylesheet" href="' . htmlspecialchars(url('assets/css/fvd-delegado-dashboard.css'), ENT_QUOTES, 'UTF-8') . '?v=' . rawurlencode($fvdDdCssV) . '">'
    . '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">'
    . fvd_vite_tags('resources/js/delegado-app.js');

$fvd_page_title = 'Administración de la asociación';
/* Menú lateral ancho al cargar (el modo “rail” deja el menú en una franja casi inútil). */
$fvd_sidebar_start_expanded = true;
require __DIR__ . '/includes/layout_header.php';
require __DIR__ . '/includes/partial_delegado_dashboard_vue.php';
require __DIR__ . '/includes/layout_footer.php';
