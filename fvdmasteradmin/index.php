<?php

declare(strict_types=1);

require_once __DIR__ . '/services/AuthService.php';
AuthService::ensureSession();
AuthService::requireLogin();

$appBase = rtrim((string) (function_exists('env') ? env('APP_BASE_PATH', '') : ''), '/');
// Raíz web del proyecto (p. ej. /fvd_portal). No debe terminar en /fvdmasteradmin (evita /fvd_portal/fvdmasteradmin/fvdmasteradmin/...).
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

$projRoot = dirname(__DIR__);
if (!function_exists('url')) {
    require_once $projRoot . '/config/paths.php';
}

$fvdEsDelegadoPanel = AuthService::isDelegadoAsociacion();

if ($fvdEsDelegadoPanel) {
    require_once $projRoot . '/fvdmasteradmin/config/db.php';
    require_once $projRoot . '/src/Services/StatsService.php';
    require_once $projRoot . '/src/Services/DelegadoTorneoNotifService.php';
    require_once $projRoot . '/src/Services/InscripcionService.php';
    require_once $projRoot . '/src/Services/DelegadoTorneoVentanasService.php';
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
    $fvd_page_title = 'Panel de administración de torneos';
    require __DIR__ . '/includes/layout_header.php';
    require __DIR__ . '/partial_panel_delegado.php';
    require __DIR__ . '/includes/layout_footer.php';
    exit;
}

require_once __DIR__ . '/services/FvdDashboardStats.php';
require_once $projRoot . '/src/Services/StatsService.php';

$fvd_page_title = 'Panel general';
$stats = FvdDashboardStats::counts();
$fvdIndicadoresCostos = \FvdPortal\Services\StatsService::indicadoresServicioConCostosEstimados(fvd_db());
$urlNuevoTorneo = $appBase . '/modules/torneos/index.php?action=form';
$urlRegistrarAtleta = $appBase . '/modules/atletas/index.php';

$fvdPuedeGestionar = AuthService::checkAccess([
    AuthService::ROLE_FVD_ADMIN,
    AuthService::ROLE_ASO_ADMIN,
]);
$fvdEsAdminFvd = AuthService::isSuperAdmin();

if (!function_exists('fvd_master_module_url') || !function_exists('admin_module_url')) {
    require_once $projRoot . '/config/paths.php';
}
$fvdIndicadoresCostosVariant = 'full';
$fvdReporteIndicadoresUrl = function_exists('admin_module_url') ? admin_module_url('atletas/reporte_indicadores.php') : null;
$fvdAsocReporteFinancieroUrl = $fvdEsAdminFvd ? ($appBase . '/fvdmasteradmin/asociacion_reporte_financiero.php') : '';
$fvdEsAdminAsoc = AuthService::role() === AuthService::ROLE_ASO_ADMIN;

require __DIR__ . '/includes/layout_header.php';
?>

<div class="fvd-dash">
    <?php if ($fvdEsAdminFvd): ?>
    <header class="fvd-dash__admin-head" aria-label="Cabecera del panel">
        <img
            class="fvd-dash__admin-head-logo"
            src="<?= htmlspecialchars($fvd_brand_logo_url, ENT_QUOTES, 'UTF-8') ?>"
            width="120"
            height="40"
            alt="Federación Venezolana de Dominó"
            decoding="async"
        >
        <h1 class="fvd-dash__admin-title">Panel FVD — administración general</h1>
    </header>
    <p class="fvd-dash__intro fvd-dash__intro--after-admin-head" style="max-width:36rem">
        Todas las operaciones se gestionan desde el <strong>menú lateral</strong>: despliegue cada bloque (Asociaciones, Atletas, Torneos, Traspasos/carnets, Finanzas, Inscripciones e informes) y elija la opción correspondiente.
    </p>
    <?php
    require __DIR__ . '/includes/partial_indicadores_costos_dashboard.php';
    ?>
    <?php else: ?>
    <h1>Panel general</h1>
    <p class="fvd-dash__intro">
        Federación Venezolana de Dominó — vista consolidada según su perfil y ámbito regional.
    </p>

    <div class="fvd-stat-grid" aria-label="Estadísticas rápidas">
        <article class="fvd-stat-card">
            <div class="fvd-stat-card__icon" aria-hidden="true">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                    <circle cx="9" cy="7" r="4"/>
                    <path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>
                </svg>
            </div>
            <div class="fvd-stat-card__body">
                <p class="fvd-stat-card__label">Atletas</p>
                <p class="fvd-stat-card__value"><?= number_format($stats['atletas'], 0, ',', '.') ?></p>
                <p class="fvd-stat-card__hint">Registros en su ámbito</p>
            </div>
        </article>

        <article class="fvd-stat-card">
            <div class="fvd-stat-card__icon" aria-hidden="true">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M3 21h18"/>
                    <path d="M5 21V7l8-4v18"/>
                    <path d="M19 21V11l-6-4"/>
                    <path d="M9 9v.01"/>
                    <path d="M9 12v.01"/>
                    <path d="M9 15v.01"/>
                    <path d="M9 18v.01"/>
                </svg>
            </div>
            <div class="fvd-stat-card__body">
                <p class="fvd-stat-card__label">Clubes</p>
                <p class="fvd-stat-card__value"><?= number_format($stats['clubes'], 0, ',', '.') ?></p>
                <p class="fvd-stat-card__hint">Asociaciones registradas</p>
            </div>
        </article>

        <article class="fvd-stat-card">
            <div class="fvd-stat-card__icon" aria-hidden="true">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M6 9H4.5a2.5 2.5 0 0 1 0-5H6"/>
                    <path d="M18 9h1.5a2.5 2.5 0 0 0 0-5H18"/>
                    <path d="M4 22h16"/>
                    <path d="M10 14.66V17c0 .55-.47.98-.97 1.21C7.85 18.75 7 20.24 7 22"/>
                    <path d="M14 14.66V17c0 .55.47.98.97 1.21C16.15 18.75 17 20.24 17 22"/>
                    <path d="M18 2H6v7a6 6 0 0 0 12 0V2Z"/>
                </svg>
            </div>
            <div class="fvd-stat-card__body">
                <p class="fvd-stat-card__label">Torneos</p>
                <p class="fvd-stat-card__value"><?= number_format($stats['torneos'], 0, ',', '.') ?></p>
                <p class="fvd-stat-card__hint">Torneos por organización</p>
            </div>
        </article>
    </div>
    <?php
    if ($fvdEsAdminAsoc) {
        require_once __DIR__ . '/config/db.php';
        if (!function_exists('admin_module_url')) {
            require_once $projRoot . '/config/paths.php';
        }
        $pdo = fvd_db();
        $aid = (int) (AuthService::idAsociacion() ?? 0);
        if ($aid <= 0) {
            echo '<p class="fvd-mod-msg">No se pudo determinar su asociación para el reporte financiero.</p>';
        } else {
            if (isset($_GET['detalle'])) {
                $detalleRawDash = trim((string) $_GET['detalle']);
                $metricasDash = ['afiliacion', 'anualidad', 'carnet', 'traspaso', 'inscripcion'];
                $detalle = \in_array($detalleRawDash, $metricasDash, true) ? $detalleRawDash : '';
            } else {
                $detalle = 'anualidad';
            }
            $fvd_rep_fin_embed = true;
            require __DIR__ . '/includes/partial_asociacion_reporte_financiero.php';
        }
    } else {
        require __DIR__ . '/includes/partial_indicadores_costos_dashboard.php';
    }
    ?>
    <?php endif; ?>

    <?php if (!$fvdEsAdminFvd && $fvdPuedeGestionar && AuthService::role() !== AuthService::ROLE_ASO_ADMIN): ?>
    <section class="fvd-actions-panel" aria-label="Accesos rápidos">
        <h2>Accesos rápidos</h2>
        <div class="fvd-actions-row">
            <a class="fvd-btn fvd-btn--primary" href="<?= htmlspecialchars($urlNuevoTorneo, ENT_QUOTES, 'UTF-8') ?>">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                </svg>
                Nuevo torneo
            </a>
            <a class="fvd-btn fvd-btn--secondary" href="<?= htmlspecialchars($urlRegistrarAtleta, ENT_QUOTES, 'UTF-8') ?>">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/>
                </svg>
                Registrar atleta
            </a>
        </div>
    </section>
    <?php endif; ?>
</div>
<?php
require __DIR__ . '/includes/layout_footer.php';
