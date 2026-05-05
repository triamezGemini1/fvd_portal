<?php

declare(strict_types=1);

use FvdPortal\Services\MasterPanelContextService;

/**
 * Alertas y contador de notificaciones (consultas más pesadas).
 * El listado de solicitudes / seguimiento vive en módulos embebidos y en la campanita del panel.
 * Se puede cargar tras el primer pintado vía `master_panel_api.php?feed=1`.
 *
 * @return array{recentEvents: list<array<string, mixed>>, alerts: list<array<string, mixed>>, adminNotifications: list<array<string, mixed>>, adminNotificationsUnread: int}
 */
function fvd_master_panel_collect_feed_extras(PDO $pdo): array
{
    if (!class_exists(AuthService::class, false)) {
        require_once dirname(__DIR__) . '/services/AuthService.php';
    }

    require_once dirname(__DIR__, 2) . '/src/Services/NotificacionService.php';

    $alerts = [];
    $recentEvents = [];
    $adminNotifications = [];

    $adminNotifUnread = [];
    try {
        $adminNotifUnread = \FvdPortal\Services\NotificacionService::listarNoLeidas(
            $pdo,
            AuthService::userId(),
            8
        );
        foreach ($adminNotifUnread as $n) {
            $nid = (int) ($n['id'] ?? 0);
            $msg = trim((string) ($n['mensaje'] ?? ''));
            if ($msg === '') {
                continue;
            }
            $adminNotifications[] = [
                'id' => $nid,
                'tipo' => trim((string) ($n['tipo'] ?? '')),
                'mensaje' => $msg,
                'creado_en' => (string) ($n['creado_en'] ?? ''),
            ];
        }
    } catch (Throwable $e) {
        error_log('[master_panel notificaciones] ' . $e->getMessage());
    }

    return [
        'recentEvents' => $recentEvents,
        'alerts' => $alerts,
        'adminNotifications' => $adminNotifications,
        'adminNotificationsUnread' => count($adminNotifUnread),
    ];
}

/**
 * Parche ligero para `master_panel_api.php?feed=1` (mismas URLs embebidas que el estado completo).
 *
 * @return array{recentEvents: list<array<string, mixed>>, alerts: list<array<string, mixed>>, adminNotifications: list<array<string, mixed>>, adminNotificationsUnread: int}
 */
function fvd_master_panel_build_feed_patch(PDO $pdo): array
{
    $projRoot = dirname(__DIR__, 2);
    if (!function_exists('admin_module_url') || !function_exists('url')) {
        require_once $projRoot . '/config/paths.php';
    }
    require_once $projRoot . '/config/fvd_navigation_return.php';

    return fvd_master_panel_collect_feed_extras($pdo);
}

/**
 * Estado inicial del panel maestro (Vue) y respuesta JSON del contexto.
 *
 * @return array<string, mixed>
 */
function fvd_master_panel_build_initial_state(PDO $pdo, ?int $contextTorneoId, bool $includeFeedExtras = true): array
{
    if (!class_exists(AuthService::class, false)) {
        require_once dirname(__DIR__) . '/services/AuthService.php';
    }

    $projRoot = dirname(__DIR__, 2);
    if (!function_exists('admin_module_url') || !function_exists('url')) {
        require_once $projRoot . '/config/paths.php';
    }

    require_once $projRoot . '/fvdmasteradmin/services/FvdDashboardStats.php';
    require_once $projRoot . '/src/Services/FvdAdminRevisionPendienteService.php';
    require_once $projRoot . '/src/Services/DelegadoSolicitudService.php';
    require_once $projRoot . '/src/Services/MasterPanelContextService.php';

    $stats = FvdDashboardStats::counts();
    $rev = \FvdPortal\Services\FvdAdminRevisionPendienteService::conteos($pdo);

    $nAltas = (int) ($rev['nuevas_altas_delegado'] ?? 0);
    $nSol = (int) ($rev['solicitudes_pendientes'] ?? 0);
    $totalPend = (int) ($rev['total'] ?? 0);

    \FvdPortal\Services\DelegadoSolicitudService::ensureTable($pdo);
    \FvdPortal\Services\FvdAdminRevisionPendienteService::ensureAltaDesdeDelegadoColumn($pdo);

    $urlListadoAltas = admin_module_url('atletas/index.php?action=list');
    $urlSolicitudes = admin_module_url('solicitudes_delegado/index.php');
    $urlTorneos = fvd_master_module_url('torneos/index.php');
    $urlAsociaciones = url('asociaciones.php');
    /** Gestión atletas: SPA Vite {@see ../../../../atletas.php} (misma API JSON; embed en panel sin redirección PHP). */
    $urlAtletasGestion = url('atletas.php');
    $urlInscripcionesReportes = fvd_master_module_url('inscripciones/index.php');
    // Rutas canónicas del workspace (stubs en fvdmasteradmin/cruds|operaciones|reportes → admin/modules/…)
    $workspaceRoutes = [
        'servicios/asociaciones' => url('asociaciones.php'),
        'servicios/atletas' => $urlAtletasGestion,

        // Listado CRUD: /modules/torneos/ → fvdmasteradmin → admin (misma lógica; self-URL y redirecciones alineadas al panel).
        'operaciones/torneos_gestion' => fvd_master_module_url('torneos/index.php?action=list'),
        // Mismo día / grupo compartido (relacion_grupo), no inscripción asociación–torneo
        'operaciones/assoc_torneo' => url('fvdmasteradmin/operaciones/vinculacion.php'),
        // Selector de asociación → panel delegado (vista como delegado)
        'operaciones/portal_assoc' => url('fvdmasteradmin/operaciones/portal_mirror.php'),
        /** Bandeja unificada: traspasos, carnets, nuevas altas, etc. */
        'operaciones/solicitudes' => admin_module_url('solicitudes_delegado/index.php'),

        'finanzas/general' => url('fvdmasteradmin/reportes/consolidado_finanzas.php'),
        'finanzas/por_torneo' => fvd_master_module_url('inscripciones/index.php'),
        'finanzas/consolidado' => url('fvdmasteradmin/reportes/consolidado_finanzas.php'),
        'finanzas/estado_cuentas' => url('fvdmasteradmin/reportes/consolidado_finanzas.php'),
        'finanzas/deudas_pagos' => fvd_master_module_url('deuda_asociacion/index.php'),

        // Compatibilidad con claves anteriores
        'operaciones/torneos/editar' => $urlTorneos,
        'operaciones/inscripciones' => $urlInscripcionesReportes,
        'operaciones/asociar/torneo' => admin_module_url('torneo_inscripcion/index.php'),
        'operaciones/fichaje/social' => admin_module_url('atletas/index.php'),
        'finanzas/por-torneo' => fvd_master_module_url('inscripciones/index.php'),
        'afiliaciones' => $urlListadoAltas,
        'traspasos' => admin_module_url('solicitudes_delegado/index.php?tipo=traspaso'),
        'carnets' => admin_module_url('solicitudes_delegado/index.php?tipo=carnet_afiliacion'),
        'operaciones/portal-asociacion' => $urlAsociaciones,
        'finanzas/deudas' => fvd_master_module_url('deuda_asociacion/index.php'),
        'finanzas/pagos' => fvd_master_module_url('relacion_pago/index.php'),
    ];

    require_once $projRoot . '/config/fvd_navigation_return.php';
    foreach ($workspaceRoutes as $wk => $wv) {
        $workspaceRoutes[$wk] = fvd_append_embed_to_url($wv);
    }

    $nSolMiClub = 0;
    $clubAid = 0;
    if (AuthService::isDelegadoAsociacion() || AuthService::role() === AuthService::ROLE_ASO_ADMIN) {
        $clubAid = (int) (AuthService::idAsociacion() ?? 0);
    } elseif (AuthService::isSuperAdmin()) {
        $clubAid = (int) (AuthService::adminPortalDelegadoAsociacionId() ?? 0);
    }
    $portalAsoc = (int) (AuthService::adminPortalDelegadoAsociacionId() ?? 0);
    $misSolOk = $clubAid > 0
        && (!AuthService::isSuperAdmin() || ($portalAsoc > 0 && $portalAsoc === $clubAid));
    if ($misSolOk) {
        $workspaceRoutes['operaciones/mis_solicitudes'] = fvd_append_embed_to_url(
            admin_module_url('solicitudes_delegado/listado_asociacion.php')
        );
        $pendClub = \FvdPortal\Services\DelegadoSolicitudService::listarPendientesParaAsociacion($pdo, $clubAid);
        $nSolMiClub = count($pendClub);
    }

    $urlListadoAltasEmb = fvd_append_embed_to_url($urlListadoAltas);
    $urlSolicitudesEmb = fvd_append_embed_to_url($urlSolicitudes);

    $pendingPagosVerificar = 0;

    $feedExtras = $includeFeedExtras
        ? fvd_master_panel_collect_feed_extras($pdo)
        : [
            'recentEvents' => [],
            'alerts' => [],
            'adminNotifications' => [],
            'adminNotificationsUnread' => 0,
        ];

    $recentEvents = $feedExtras['recentEvents'];
    $alerts = $feedExtras['alerts'];
    $adminNotifications = $feedExtras['adminNotifications'] ?? [];
    $adminNotificationsUnreadCount = (int) ($feedExtras['adminNotificationsUnread'] ?? 0);

    $sessKey = MasterPanelContextService::SESSION_MASTER_PANEL_CTX_TORNEO;
    $sessionAnchor = isset($_SESSION[$sessKey]) ? (int) $_SESSION[$sessKey] : 0;
    $anchorCtx = $contextTorneoId !== null && $contextTorneoId > 0 ? (int) $contextTorneoId : 0;
    if ($anchorCtx <= 0 && $sessionAnchor > 0) {
        $anchorCtx = $sessionAnchor;
    }
    if ($anchorCtx <= 0 && AuthService::isAdminGral()) {
        $anchorCtx = MasterPanelContextService::resolveDefaultAnchorTorneoId($pdo);
    }

    $contextTorneos = $anchorCtx > 0
        ? MasterPanelContextService::listTorneosDelGrupoAncla($pdo, $anchorCtx)
        : [];
    if ($contextTorneos === [] && $anchorCtx > 0 && AuthService::isAdminGral()) {
        $fallback = MasterPanelContextService::resolveDefaultAnchorTorneoId($pdo);
        if ($fallback > 0 && $fallback !== $anchorCtx) {
            $anchorCtx = $fallback;
            $contextTorneos = MasterPanelContextService::listTorneosDelGrupoAncla($pdo, $anchorCtx);
        }
    }
    if ($contextTorneos !== []) {
        $contextTorneos = MasterPanelContextService::filterContextTorneosFechaNoPasada($contextTorneos);
    }
    $showContextSelector = count($contextTorneos) >= 2;

    $selectedTorneoId = 0;
    $urlAnchor = $contextTorneoId !== null && $contextTorneoId > 0 ? (int) $contextTorneoId : 0;
    if ($urlAnchor > 0) {
        foreach ($contextTorneos as $ct) {
            if ((int) ($ct['torneo'] ?? 0) === $urlAnchor) {
                $selectedTorneoId = $urlAnchor;
                break;
            }
        }
    }
    if ($selectedTorneoId <= 0 && $sessionAnchor > 0) {
        foreach ($contextTorneos as $ct) {
            if ((int) ($ct['torneo'] ?? 0) === $sessionAnchor) {
                $selectedTorneoId = $sessionAnchor;
                break;
            }
        }
    }
    if ($selectedTorneoId <= 0 && $contextTorneos !== []) {
        $selectedTorneoId = (int) ($contextTorneos[0]['torneo'] ?? 0);
    }

    if (AuthService::isAdminGral() && $selectedTorneoId > 0) {
        $_SESSION[$sessKey] = $selectedTorneoId;
    }

    if (AuthService::isAdminGral() && $contextTorneos !== []) {
        require_once $projRoot . '/src/Services/FvdAdminService.php';
        $svcCtxStats = new \FvdAdminService($pdo);
        foreach ($contextTorneos as $idx => $ct) {
            $tidSt = (int) ($ct['torneo'] ?? 0);
            if ($tidSt <= 0) {
                continue;
            }
            $pst = $svcCtxStats->torneosPanelEstadisticas($tidSt);
            $contextTorneos[$idx]['inscripciones_torneo'] = (int) ($pst['inscripciones_torneo'] ?? 0);
            $contextTorneos[$idx]['atletas_en_ambito'] = (int) ($pst['atletas_en_ambito'] ?? 0);
        }
    }

    $grupoId = null;
    if ($selectedTorneoId > 0) {
        $grupoId = MasterPanelContextService::resolveGrupoFromTorneo($pdo, $selectedTorneoId);
    }

    $finanzasGrupo = null;
    if ($grupoId !== null && $grupoId > 0) {
        $finanzasGrupo = MasterPanelContextService::aggregatePagosPorGrupo($pdo, $grupoId);
    }

    $apiUrl = url('fvdmasteradmin/master_panel_api.php');

    require_once __DIR__ . '/fvd_brand.php';
    $fvdLogoUrl = fvd_brand_logo_public_url();

    $userLabel = '';
    try {
        $u = AuthService::user();
        if (is_array($u)) {
            $userLabel = trim((string) ($u['nombre'] ?? ''));
            if ($userLabel === '') {
                $userLabel = (string) ($u['email'] ?? '');
            }
        }
    } catch (Throwable $e) {
        $userLabel = '';
    }

    $delegadoPanel = false;
    $delegadoDashboardEmbeddedUrl = '';
    $isAdminGralState = AuthService::isAdminGral();
    /** Misma cadena que en master_panel.php / seed SQL (vista embebida delegado en pruebas). */
    $fvdMasterTestDelegadoToken = 'TOKEN_PRUEBA_MIRANDA_2026';
    $sessTok = trim((string) ($_SESSION['fvd_master_delegado_notif_token'] ?? ''));

    if (
        $isAdminGralState
        && $sessTok === $fvdMasterTestDelegadoToken
    ) {
        $delegadoPanel = true;
        $delegadoDashboardEmbeddedUrl = function_exists('fvd_append_embed_to_url')
            ? fvd_append_embed_to_url(url('fvdmasteradmin/delegado_dashboard_new.php'))
            : url('fvdmasteradmin/delegado_dashboard_new.php');
    } elseif (!$isAdminGralState && AuthService::isDelegadoAsociacion()) {
        $delegadoPanel = true;
        $delegadoDashboardEmbeddedUrl = function_exists('fvd_append_embed_to_url')
            ? fvd_append_embed_to_url(url('fvdmasteradmin/delegado_dashboard_new.php'))
            : url('fvdmasteradmin/delegado_dashboard_new.php');
    }

    $userRole = (string) (AuthService::role() ?? '');
    $primaryDashboard = AuthService::isDelegadoAsociacion() ? 'delegado' : 'admin_gral';

    return [
        'pendingApprovals' => $totalPend,
        'pendingAffiliations' => $nAltas,
        'pendingSolicitudes' => $nSol,
        'pendingSolicitudesClub' => $nSolMiClub,
        'pendingPayments' => $pendingPagosVerificar,
        'pendingAthletesLabel' => $nAltas . ' atleta' . ($nAltas === 1 ? '' : 's'),
        'activeTournaments' => (int) ($stats['torneos'] ?? 0),
        'alerts' => $alerts,
        'adminNotifications' => $adminNotifications,
        'adminNotificationsUnread' => $adminNotificationsUnreadCount,
        'recentEvents' => $recentEvents,
        'workspaceRoutes' => $workspaceRoutes,
        'actionUrls' => [
            'validarAfiliaciones' => $urlListadoAltasEmb,
            'revisarSolicitudes' => $urlSolicitudesEmb,
            'bandejaSolicitudes' => $urlSolicitudesEmb,
            'torneos' => fvd_append_embed_to_url($urlTorneos),
            'asociaciones' => fvd_append_embed_to_url($urlAsociaciones),
            'atletas' => fvd_append_embed_to_url($urlAtletasGestion),
        ],
        'contextTorneos' => $contextTorneos,
        'showContextSelector' => $showContextSelector,
        'selectedContextTorneoId' => $selectedTorneoId,
        'contextGrupoEventoId' => $grupoId,
        'finanzasGrupo' => $finanzasGrupo,
        'masterPanelApiUrl' => $apiUrl,
        'perfilUrl' => url('fvdmasteradmin/perfil.php'),
        'logoutUrl' => url('fvdmasteradmin/logout.php'),
        'userLabel' => $userLabel,
        'fvdLogoUrl' => $fvdLogoUrl,
        'partnerLogos' => [
            ['src' => url('assets/img/partners/cov.svg'), 'alt' => 'Comité Olímpico Venezolano'],
            ['src' => url('assets/img/partners/fid.svg'), 'alt' => 'FID'],
            ['src' => url('assets/img/partners/mindeporte.svg'), 'alt' => 'Ministerio del Poder Popular para el Deporte'],
        ],
        'torneosAdminDirectUrl' => $urlTorneos,
        'delegadoPanel' => $delegadoPanel,
        'delegadoDashboardEmbeddedUrl' => $delegadoDashboardEmbeddedUrl,
        'isAdminGral' => $isAdminGralState,
        'userRole' => $userRole,
        'primaryDashboard' => $primaryDashboard,
    ];
}
