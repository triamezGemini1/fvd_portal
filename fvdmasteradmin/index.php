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
    $pdo = fvd_db();
    $delegSnap = \FvdPortal\Services\StatsService::snapshotDelegadoPanel($pdo);
    $delegadoUid = (int) AuthService::userId();
    $delegNotifs = \FvdPortal\Services\DelegadoTorneoNotifService::listarParaDelegado($pdo, $delegadoUid, 25);
    $delegNotifNoVistas = \FvdPortal\Services\DelegadoTorneoNotifService::contarNoVistas($pdo, $delegadoUid);
    $delegInscripcionApi = $appBase . '/fvdmasteradmin/delegado_inscripcion_api.php';
    $urlRegistrarAtleta = $appBase . '/fvdmasteradmin/modules/atletas/index.php?action=form';
    $delegCupoInsc = null;
    $tidCtx = (int) ($delegSnap['torneo_id'] ?? 0);
    $myAs = AuthService::idAsociacion();
    if ($tidCtx > 0 && $myAs !== null && (int) $myAs > 0) {
        $delegCupoInsc = \FvdPortal\Services\InscripcionService::estadoCupoAsociacionBandera($pdo, $tidCtx, (int) $myAs);
    }
    $delegDeuda = null;
    if ($tidCtx > 0 && $myAs !== null && (int) $myAs > 0) {
        try {
            $stDeuda = $pdo->prepare(
                'SELECT monto_inscritos, monto_afiliados, monto_anualidad, monto_carnets, monto_traspasos, monto_total,
                    total_inscritos, total_afiliados, total_anualidad, total_carnets, total_traspasos
                 FROM deuda_asociaciones WHERE torneo_id = :t AND asociacion_id = :a LIMIT 1'
            );
            $stDeuda->execute([':t' => $tidCtx, ':a' => (int) $myAs]);
            $delegDeuda = $stDeuda->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (Throwable $e) {
            $delegDeuda = null;
        }
    }
    $fvd_page_title = 'Panel de administración de torneos';
    require __DIR__ . '/includes/layout_header.php';
    require __DIR__ . '/partial_panel_delegado.php';
    require __DIR__ . '/includes/layout_footer.php';
    exit;
}

require_once __DIR__ . '/services/FvdDashboardStats.php';

$fvd_page_title = 'Panel general';
$stats = FvdDashboardStats::counts();
$urlNuevoTorneo = $appBase . '/fvdmasteradmin/modules/torneos/index.php?action=form';
$urlRegistrarAtleta = $appBase . '/fvdmasteradmin/modules/atletas/index.php?action=form';

$fvdPuedeGestionar = AuthService::checkAccess([
    AuthService::ROLE_FVD_ADMIN,
    AuthService::ROLE_ASO_ADMIN,
]);
$fvdEsAdminFvd = AuthService::isSuperAdmin();

if (!function_exists('fvd_master_module_url')) {
    require_once $projRoot . '/config/paths.php';
}

require __DIR__ . '/includes/layout_header.php';
?>

<div class="fvd-dash">
    <h1><?= $fvdEsAdminFvd ? 'Panel FVD — administración general' : 'Panel general' ?></h1>
    <?php if ($fvdEsAdminFvd): ?>
    <p class="fvd-dash__intro" style="max-width:36rem">
        Todas las operaciones se gestionan desde el <strong>menú lateral</strong>: despliegue cada bloque (Asociaciones, Atletas, Torneos, Traspasos/carnets, Finanzas, Inscripciones e informes) y elija la opción correspondiente.
    </p>
    <?php else: ?>
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
    <?php endif; ?>

    <?php if (!$fvdEsAdminFvd && $fvdPuedeGestionar): ?>
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
