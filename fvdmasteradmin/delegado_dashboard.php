<?php

declare(strict_types=1);

require_once __DIR__ . '/services/AuthService.php';
AuthService::ensureSession();
AuthService::requireLogin();

$projRoot = dirname(__DIR__);
if (!function_exists('url')) {
    require_once $projRoot . '/config/paths.php';
}

$appBase = rtrim((string) (function_exists('env') ? env('APP_BASE_PATH', '') : ''), '/');
if ($appBase !== '' && preg_match('#/fvdmasteradmin$#i', $appBase)) {
    $appBase = rtrim((string) preg_replace('#/fvdmasteradmin$#i', '', $appBase), '/');
}
if ($appBase === '') {
    $sn = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
    $pos = strpos($sn, '/fvdmasteradmin/');
    if ($pos > 0) {
        $appBase = rtrim(substr($sn, 0, $pos), '/');
    }
}

if (AuthService::isAthletePortalUser()) {
    header('Location: ' . $appBase . '/fvdmasteradmin/atleta/mi_ficha.php');
    exit;
}

if (!AuthService::isDelegadoAsociacion()) {
    header('Location: ' . $appBase . '/fvdmasteradmin/index.php');
    exit;
}

require_once $projRoot . '/fvdmasteradmin/config/db.php';
require_once $projRoot . '/src/Services/StatsService.php';
require_once $projRoot . '/src/Services/DelegadoTorneoNotifService.php';
require_once $projRoot . '/src/Services/InscripcionService.php';
require_once $projRoot . '/src/Services/DelegadoTorneoVentanasService.php';
require_once $projRoot . '/src/Services/DelegadoSolicitudService.php';
require_once $projRoot . '/src/Services/FvdAdminService.php';

$pdo = fvd_db();
$delegSnap = \FvdPortal\Services\StatsService::snapshotDelegadoPanel($pdo);
$delegadoUid = (int) AuthService::userId();
$delegAid = AuthService::idAsociacion();
$delegAidInt = ($delegAid !== null && (int) $delegAid > 0) ? (int) $delegAid : null;

$delegNotifs = \FvdPortal\Services\DelegadoTorneoNotifService::listarParaDelegado($pdo, $delegadoUid, 25, $delegAidInt);
$delegNotifNoVistas = \FvdPortal\Services\DelegadoTorneoNotifService::contarNoVistas($pdo, $delegadoUid, $delegAidInt);

$urlRegistrarAtleta = $appBase . '/modules/atletas/index.php';
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

$fvd_hide_sidebar = true;
$fvdDdCssV = (string) (@filemtime($projRoot . '/assets/css/fvd-delegado-dashboard.css') ?: time());
$fvdBsGridV = (string) (@filemtime($projRoot . '/assets/css/bootstrap-grid.css') ?: time());
$fvd_head_extra_html = '<link rel="stylesheet" href="' . htmlspecialchars(url('assets/css/bootstrap-grid.css'), ENT_QUOTES, 'UTF-8') . '?v=' . rawurlencode($fvdBsGridV) . '">'
    . '<link rel="stylesheet" href="' . htmlspecialchars(url('assets/css/fvd-delegado-dashboard.css'), ENT_QUOTES, 'UTF-8') . '?v=' . rawurlencode($fvdDdCssV) . '">'
    . '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">';

$fvd_page_title = 'Panel de delegación';
require __DIR__ . '/includes/layout_header.php';
require __DIR__ . '/includes/partial_delegado_dashboard_mosaic.php';
require __DIR__ . '/includes/layout_footer.php';
