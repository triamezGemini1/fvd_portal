<?php

declare(strict_types=1);

use FvdPortal\Services\MasterPanelContextService;

/**
 * Estado inicial del panel maestro (Vue) y respuesta JSON del contexto.
 *
 * @return array<string, mixed>
 */
function fvd_master_panel_build_initial_state(PDO $pdo, ?int $contextTorneoId): array
{
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
    $urlTorneos = admin_module_url('torneos/index.php');
    $urlAsociaciones = admin_module_url('asociaciones/index.php');
    $urlInscripcionesReportes = fvd_master_module_url('inscripciones/index.php');

    // Rutas canónicas del workspace (stubs en fvdmasteradmin/cruds|operaciones|reportes → admin/modules/…)
    $workspaceRoutes = [
        'servicios/asociaciones' => url('fvdmasteradmin/cruds/asociaciones.php'),
        'servicios/atletas' => url('fvdmasteradmin/cruds/atletas.php'),
        'servicios/torneos' => url('fvdmasteradmin/cruds/torneos_config.php'),
        'servicios/atletas_reset' => url('fvdmasteradmin/cruds/atletas_reset.php'),

        'operaciones/torneos' => url('fvdmasteradmin/operaciones/gestion.php'),
        // Mismo día / grupo compartido (relacion_grupo), no inscripción asociación–torneo
        'operaciones/assoc_torneo' => url('fvdmasteradmin/operaciones/vinculacion.php'),
        'operaciones/invitaciones' => url('fvdmasteradmin/operaciones/invitaciones.php'),
        'operaciones/portal_assoc' => url('fvdmasteradmin/operaciones/portal_mirror.php'),

        'finanzas/general' => url('fvdmasteradmin/reportes/general.php'),
        'finanzas/por_torneo' => url('fvdmasteradmin/reportes/torneo_spec.php'),
        'finanzas/consolidado' => url('fvdmasteradmin/reportes/consolidado.php'),
        'finanzas/deudas_pagos' => url('fvdmasteradmin/reportes/cartera.php'),

        // Compatibilidad con claves anteriores
        'operaciones/torneos/editar' => $urlTorneos,
        'operaciones/inscripciones' => $urlInscripcionesReportes,
        'operaciones/asociar/torneo' => admin_module_url('torneo_inscripcion/index.php'),
        'operaciones/fichaje/social' => admin_module_url('atletas/index.php'),
        'finanzas/por-torneo' => url('fvdmasteradmin/reportes/torneo_spec.php'),
        'afiliaciones' => $urlListadoAltas,
        'traspasos' => admin_module_url('solicitudes_delegado/index.php?tipo=traspaso'),
        'carnets' => admin_module_url('solicitudes_delegado/index.php?tipo=carnet_afiliacion'),
        'torneos/crear' => admin_module_url('torneos/index.php?action=form'),
        'torneos/asociar' => admin_module_url('torneo_inscripcion/index.php'),
        'torneos/invitar' => admin_module_url('invitaciones/index.php'),
        'operaciones/enlace-simultaneo' => admin_module_url('torneos/index.php?action=relacion_grupo'),
        'operaciones/portal-asociacion' => $urlAsociaciones,
        'finanzas/deudas' => admin_module_url('deuda_asociacion/index.php'),
        'finanzas/pagos' => admin_module_url('relacion_pago/index.php'),
    ];

    require_once $projRoot . '/config/fvd_navigation_return.php';
    foreach ($workspaceRoutes as $wk => $wv) {
        $workspaceRoutes[$wk] = fvd_append_embed_to_url($wv);
    }

    $urlListadoAltasEmb = fvd_append_embed_to_url($urlListadoAltas);
    $urlSolicitudesEmb = fvd_append_embed_to_url($urlSolicitudes);

    $pendingPagosVerificar = 0;

    $recentEvents = [];
    $tsRows = [];

    try {
        $st = $pdo->query(
            "SELECT id, tipo, estado, creado_en FROM fvd_solicitudes_delegado ORDER BY creado_en DESC LIMIT 20"
        );
        if ($st !== false) {
            while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
                $ts = strtotime((string) ($row['creado_en'] ?? '')) ?: 0;
                $tsRows[] = [
                    'ts' => $ts,
                    'id' => 'sol-' . (int) $row['id'],
                    'title' => 'Solicitud ' . (string) $row['tipo'] . ' #' . (int) $row['id'],
                    'meta' => date('d/m/Y H:i', $ts ?: time()),
                    'badge' => (string) $row['estado'],
                    'href' => $urlSolicitudesEmb,
                ];
            }
        }
    } catch (Throwable $e) {
        error_log('[master_panel solicitudes recientes] ' . $e->getMessage());
    }

    try {
        $st2 = $pdo->query(
            'SELECT id, nombre, cedula FROM atletas
             WHERE COALESCE(alta_desde_delegado, 0) = 1 AND COALESCE(estatus, 0) = 0
             ORDER BY id DESC LIMIT 20'
        );
        if ($st2 !== false) {
            while ($row = $st2->fetch(PDO::FETCH_ASSOC)) {
                $aid = (int) ($row['id'] ?? 0);
                $nom = trim((string) ($row['nombre'] ?? ''));
                if ($nom === '') {
                    $nom = 'Atleta #' . $aid;
                }
                $tsRows[] = [
                    'ts' => $aid,
                    'id' => 'alta-' . $aid,
                    'title' => 'Alta desde delegado: ' . $nom,
                    'meta' => (string) ($row['cedula'] ?? ''),
                    'badge' => 'pendiente',
                    'href' => $urlListadoAltasEmb,
                ];
            }
        }
    } catch (Throwable $e) {
        error_log('[master_panel altas recientes] ' . $e->getMessage());
    }

    usort(
        $tsRows,
        static function (array $a, array $b): int {
            return ($b['ts'] <=> $a['ts']);
        }
    );
    foreach (array_slice($tsRows, 0, 16) as $ev) {
        unset($ev['ts']);
        $recentEvents[] = $ev;
    }

    $contextTorneos = MasterPanelContextService::listTorneosConGrupo($pdo);
    $showContextSelector = count($contextTorneos) >= 2;

    $selectedTorneoId = 0;
    if ($contextTorneoId !== null && $contextTorneoId > 0) {
        foreach ($contextTorneos as $ct) {
            if ((int) ($ct['torneo'] ?? 0) === $contextTorneoId) {
                $selectedTorneoId = $contextTorneoId;
                break;
            }
        }
    }
    if ($selectedTorneoId <= 0 && $contextTorneos !== []) {
        $selectedTorneoId = (int) ($contextTorneos[0]['torneo'] ?? 0);
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
    require_once dirname(__DIR__) . '/fvd_notifier_bot.php';
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

    return [
        'pendingApprovals' => $totalPend,
        'pendingAffiliations' => $nAltas,
        'pendingSolicitudes' => $nSol,
        'pendingPayments' => $pendingPagosVerificar,
        'pendingAthletesLabel' => $nAltas . ' atleta' . ($nAltas === 1 ? '' : 's'),
        'activeTournaments' => (int) ($stats['torneos'] ?? 0),
        'alerts' => [],
        'recentEvents' => $recentEvents,
        'workspaceRoutes' => $workspaceRoutes,
        'actionUrls' => [
            'validarAfiliaciones' => $urlListadoAltasEmb,
            'revisarSolicitudes' => $urlSolicitudesEmb,
            'torneos' => fvd_append_embed_to_url($urlTorneos),
            'asociaciones' => fvd_append_embed_to_url($urlAsociaciones),
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
        'invitacionesMonitor' => fvd_invitaciones_monitor_line($pdo),
    ];
}
