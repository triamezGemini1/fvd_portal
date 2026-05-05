<?php
declare(strict_types=1);

require_once __DIR__ . '/services/AuthService.php';
require_once __DIR__ . '/config/db.php';
require_once dirname(__DIR__) . '/config/paths.php';
require_once dirname(__DIR__) . '/config/fvd_navigation_return.php';
require_once __DIR__ . '/includes/vite_assets.php';
require_once __DIR__ . '/includes/fvd_brand.php';
require_once dirname(__DIR__) . '/src/Views/Delegado/Dashboard.php';
require_once dirname(__DIR__) . '/src/Services/DelegadoTorneoNotifService.php';
require_once dirname(__DIR__) . '/src/Services/DelegadoTorneoVentanasService.php';
require_once dirname(__DIR__) . '/src/Services/TorneoFinalizacionService.php';
require_once dirname(__DIR__) . '/src/Services/FvdAdminService.php';
require_once dirname(__DIR__) . '/src/Services/NotificacionesDelegadosService.php';

AuthService::ensureSession();
AuthService::requireLogin();

$adminPortalDelegado = false;
if (AuthService::isDelegadoAsociacion()) {
    // flujo delegado estándar
} elseif (AuthService::isSuperAdmin()) {
    $portalAid = AuthService::adminPortalDelegadoAsociacionId();
    if ($portalAid === null || $portalAid <= 0) {
        if (!function_exists('fvd_append_embed_to_url')) {
            require_once dirname(__DIR__) . '/config/fvd_navigation_return.php';
        }
        header('Location: ' . fvd_append_embed_to_url(url('fvdmasteradmin/operaciones/portal_mirror.php')), true, 302);
        exit;
    }
    $adminPortalDelegado = true;
} else {
    header('Location: ' . url('login.php'), true, 302);
    exit;
}

try {
    $pdo = fvd_db();
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Error de conexión a la base de datos.';
    exit;
}

$stats = [
    'atletas_afiliados' => 0,
    'afiliaciones' => 0,
    'carnets' => 0,
    'anualidades' => 0,
    'traspasos' => 0,
    'inscritos' => 0,
];
$torneoStats = [];
$asociacionId = $adminPortalDelegado
    ? (int) (AuthService::adminPortalDelegadoAsociacionId() ?? 0)
    : (int) (AuthService::idAsociacion() ?? 0);

$delegadoDashboardNewSelf = url('fvdmasteradmin/delegado_dashboard_new.php');

$delegadoUidAck = (int) (AuthService::userId() ?? 0);
if (
    !$adminPortalDelegado
    && $delegadoUidAck > 0
    && isset($_GET['ack_novedades'])
    && (string) $_GET['ack_novedades'] === '1'
) {
    \FvdPortal\Services\NotificacionesDelegadosService::marcarTodasLeidas($pdo, $delegadoUidAck);
    $redirAck = function_exists('fvd_append_embed_to_url')
        ? fvd_append_embed_to_url($delegadoDashboardNewSelf)
        : $delegadoDashboardNewSelf;
    header('Location: ' . $redirAck, true, 302);
    exit;
}

$fvdAdminPickSvc = new \FvdAdminService($pdo);
if ($asociacionId > 0 && isset($_GET['torneo_id'])) {
    $pickTid = (int) $_GET['torneo_id'];
    if ($pickTid > 0) {
        $puedeFijar = $fvdAdminPickSvc->delegadoPuedeFijarTorneoContext($asociacionId, $pickTid);
        if ($puedeFijar) {
            AuthService::setDelegadoTorneoContext($pickTid);
            $gSet = 0;
            try {
                $stg = $pdo->prepare('SELECT grupo_evento_id FROM torneosact WHERE torneo = :t LIMIT 1');
                $stg->execute([':t' => $pickTid]);
                $rawG = $stg->fetchColumn();
                if ($rawG !== false && $rawG !== null && (int) $rawG > 0) {
                    $gSet = (int) $rawG;
                }
            } catch (Throwable $e) {
                /* sin grupo_evento_id */
            }
            if ($gSet > 0) {
                AuthService::setDelegadoCampeonatoGrupo($gSet);
            }
            $qsPick = ['torneo_id' => $pickTid];
            if ($gSet > 0) {
                $qsPick['campeonato_id'] = $gSet;
            }
            $getCamp = isset($_GET['campeonato_id']) ? (int) $_GET['campeonato_id'] : 0;
            $urlYaCanonica = ((int) ($_GET['torneo_id'] ?? 0) === $pickTid)
                && (
                    ($gSet <= 0 && $getCamp <= 0)
                    || ($gSet > 0 && $getCamp === $gSet)
                );
            if (!$urlYaCanonica) {
                header('Location: ' . $delegadoDashboardNewSelf . '?' . http_build_query($qsPick), true, 302);
                exit;
            }
        }
    }
}

$delegadoUidInt = (int) (AuthService::userId() ?? 0);
$delegInvitacionesAgrupadas = [];
$delegInvitacionesPendientes = 0;
$delegadoTorneoCtx = (int) (AuthService::delegadoTorneoContextId() ?? 0);
$invitNotifDelegadoId = ($adminPortalDelegado && $delegadoUidInt <= 0) ? 0 : $delegadoUidInt;
if ($asociacionId > 0 && ($invitNotifDelegadoId > 0 || $adminPortalDelegado)) {
    try {
        $delegInvitacionesAgrupadas = \FvdPortal\Services\DelegadoTorneoNotifService::listarParaDelegadoVistaAgrupada(
            $pdo,
            $invitNotifDelegadoId,
            24,
            $asociacionId
        );
        $delegInvitacionesPendientes = \FvdPortal\Services\DelegadoTorneoNotifService::contarPendientesVistaAgrupada(
            $pdo,
            $invitNotifDelegadoId,
            $asociacionId
        );
    } catch (Throwable $e) {
        error_log('[delegado_dashboard_new invitaciones] ' . $e->getMessage());
    }
}

if ($asociacionId > 0) {
    $sqlAbiertoTorAt = '';
    if (\FvdPortal\Services\TorneoFinalizacionService::columnaFinalizadoExiste($pdo)) {
        $sqlAbiertoTorAt = ' AND (a.torneo_id IS NULL OR t.torneo IS NULL OR t.finalizado_en IS NULL) ';
    }
    if ($delegadoTorneoCtx <= 0) {
        /** Sin torneo en contexto: KPI reales de todo el club (misma base de datos, sin depender de la ventana de fases). */
        $sqlKpiClub = 'SELECT
                COUNT(*) AS atletas_afiliados,
                SUM(CASE WHEN COALESCE(a.afiliacion, 0) = 1 THEN 1 ELSE 0 END) AS afiliaciones,
                SUM(CASE WHEN COALESCE(a.carnet, 0) = 1 THEN 1 ELSE 0 END) AS carnets,
                SUM(CASE WHEN COALESCE(a.anualidad, 0) = 1 THEN 1 ELSE 0 END) AS anualidades,
                SUM(CASE WHEN COALESCE(a.traspaso, 0) = 1 THEN 1 ELSE 0 END) AS traspasos,
                SUM(CASE WHEN COALESCE(a.inscripcion, 0) = 1 THEN 1 ELSE 0 END) AS inscritos
             FROM atletas a
             LEFT JOIN torneosact t ON t.torneo = a.torneo_id
             WHERE a.asociacion = :asoc' . $sqlAbiertoTorAt;
        try {
            $stClub = $pdo->prepare($sqlKpiClub);
            $stClub->execute([':asoc' => $asociacionId]);
            $rowClub = $stClub->fetch(PDO::FETCH_ASSOC);
            if (is_array($rowClub)) {
                foreach (array_keys($stats) as $k) {
                    $stats[$k] = (int) ($rowClub[$k] ?? 0);
                }
            }
        } catch (Throwable $e) {
            foreach (array_keys($stats) as $k) {
                $stats[$k] = 0;
            }
            error_log('[delegado_dashboard_new kpi_club] ' . $e->getMessage());
        }
    } else {
        $tipoFiltro = 0;
        try {
            $stTip = $pdo->prepare('SELECT COALESCE(tipo, 0) AS tipo FROM torneosact WHERE torneo = :t LIMIT 1');
            $stTip->execute([':t' => $delegadoTorneoCtx]);
            $tipoFiltro = (int) $stTip->fetchColumn();
        } catch (Throwable $e) {
            $tipoFiltro = 0;
        }
        $sexoSql = '';
        if ($tipoFiltro === 1) {
            $sexoSql = " AND a.sexo = 'M' ";
        } elseif ($tipoFiltro === 2) {
            $sexoSql = " AND a.sexo = 'F' ";
        }
        $sqlKpi = 'SELECT
                COUNT(*) AS atletas_afiliados,
                SUM(CASE WHEN COALESCE(a.afiliacion, 0) = 1 THEN 1 ELSE 0 END) AS afiliaciones,
                SUM(CASE WHEN COALESCE(a.carnet, 0) = 1 THEN 1 ELSE 0 END) AS carnets,
                SUM(CASE WHEN COALESCE(a.anualidad, 0) = 1 THEN 1 ELSE 0 END) AS anualidades,
                SUM(CASE WHEN COALESCE(a.traspaso, 0) = 1 THEN 1 ELSE 0 END) AS traspasos,
                SUM(CASE WHEN COALESCE(a.inscripcion, 0) = 1 THEN 1 ELSE 0 END) AS inscritos
             FROM atletas a
             LEFT JOIN torneosact t ON t.torneo = a.torneo_id
             WHERE a.asociacion = :asoc AND a.torneo_id = :tid' . $sexoSql . $sqlAbiertoTorAt;
        try {
            $stK = $pdo->prepare($sqlKpi);
            $stK->execute([':asoc' => $asociacionId, ':tid' => $delegadoTorneoCtx]);
            $rowK = $stK->fetch(PDO::FETCH_ASSOC);
            if (is_array($rowK)) {
                foreach (array_keys($stats) as $k) {
                    $stats[$k] = (int) ($rowK[$k] ?? 0);
                }
            }
        } catch (Throwable $e) {
            foreach (array_keys($stats) as $k) {
                $stats[$k] = 0;
            }
        }
    }
}

$delegadoListaTorneos = $asociacionId > 0
    ? $fvdAdminPickSvc->delegadoListaTorneosParaStrip($asociacionId)
    : [];

/** Afiliados (afiliación activa) de la asociación, desglose por género — cabecera del panel delegado. */
$afiliadosAfiliacionPorGenero = ['M' => 0, 'F' => 0, 'O' => 0];
if ($asociacionId > 0) {
    try {
        $sqlGen = 'SELECT
            SUM(CASE WHEN UPPER(TRIM(COALESCE(sexo, \'\'))) IN (\'M\', \'MASCULINO\', \'H\', \'HOMBRE\') THEN 1 ELSE 0 END) AS n_m,
            SUM(CASE WHEN UPPER(TRIM(COALESCE(sexo, \'\'))) IN (\'F\', \'FEMENINO\', \'MUJER\') THEN 1 ELSE 0 END) AS n_f,
            SUM(CASE WHEN UPPER(TRIM(COALESCE(sexo, \'\'))) NOT IN (\'M\', \'MASCULINO\', \'H\', \'HOMBRE\', \'F\', \'FEMENINO\', \'MUJER\')
                      OR TRIM(COALESCE(sexo, \'\')) = \'\' THEN 1 ELSE 0 END) AS n_o
            FROM atletas WHERE asociacion = :asoc AND COALESCE(afiliacion, 0) = 1';
        $stGen = $pdo->prepare($sqlGen);
        $stGen->execute([':asoc' => $asociacionId]);
        $rowGen = $stGen->fetch(PDO::FETCH_ASSOC);
        if (is_array($rowGen)) {
            $afiliadosAfiliacionPorGenero['M'] = (int) ($rowGen['n_m'] ?? 0);
            $afiliadosAfiliacionPorGenero['F'] = (int) ($rowGen['n_f'] ?? 0);
            $afiliadosAfiliacionPorGenero['O'] = (int) ($rowGen['n_o'] ?? 0);
        }
    } catch (Throwable $e) {
        error_log('[delegado_dashboard_new afiliados_genero] ' . $e->getMessage());
    }
}

$asociacionLabel = 'Miranda 4';
if ($asociacionId > 0) {
    try {
        $stAsoc = $pdo->prepare('SELECT nombre FROM asociaciones WHERE id = :id LIMIT 1');
        $stAsoc->execute([':id' => $asociacionId]);
        $asocNombre = trim((string) ($stAsoc->fetchColumn() ?: ''));
        if ($asocNombre !== '') {
            $asociacionLabel = $asocNombre;
        }
    } catch (Throwable $e) {
        /* Mantener fallback estable si falla esta consulta auxiliar. */
    }
}
$viteTags = '';
$fvdDelegadoEmbedMaster = function_exists('fvd_master_embed_active') && fvd_master_embed_active();

/* delegados-app no siempre declara CSS en manifest; inyectamos app.css para asegurar Tailwind.
 * En iframe del panel maestro ya hay shell + Tailwind en el padre: omitir app.js CSS evita choque visual (doble «framework»). */
$manifestPath = fvd_vite_manifest_path();
if (!is_readable($manifestPath)) {
    $legacyManifest = dirname(__DIR__) . '/public/build/.vite/manifest.json';
    if (is_readable($legacyManifest)) {
        $manifestPath = $legacyManifest;
    }
}
if (!$fvdDelegadoEmbedMaster && is_readable($manifestPath)) {
    $decoded = json_decode((string) file_get_contents($manifestPath), true);
    if (is_array($decoded)) {
        $mp = str_replace('\\', '/', $manifestPath);
        $baseBuild = (str_contains($mp, '/fvd_panel/') || str_contains($mp, 'fvd_panel'))
            ? fvd_vite_public_build_url()
            : rtrim(url('public/build'), '/');
        $v = (string) (@filemtime($manifestPath) ?: time());
        foreach (fvd_vite_manifest_css_for_entry($decoded, 'resources/js/app.js') as $css) {
            $href = $baseBuild . '/' . ltrim((string) $css, '/') . '?v=' . rawurlencode($v);
            $viteTags .= '<link rel="stylesheet" href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '">' . "\n";
        }
    }
}
$viteTags .= fvd_vite_tags('resources/js/delegado-app.js');

$user = AuthService::user() ?? [];
$userDisplayName = trim((string) ($user['nombre'] ?? 'Usuario activo'));
if ($userDisplayName === '') {
    $userDisplayName = trim((string) ($user['email'] ?? 'Usuario activo'));
}
if ($userDisplayName === '') {
    $userDisplayName = 'Usuario activo';
}

$brandLogoUrl = fvd_brand_logo_public_url();
$perfilUrl = AuthService::perfilUrl();
$logoutUrl = AuthService::logoutUrl();
$panelUrl = AuthService::homeUrl();
$campeonatoCtx = (int) (AuthService::delegadoCampeonatoGrupoId() ?? 0);

$torneoActualId = $delegadoTorneoCtx;

$grupoDesdeTorneo = 0;
$nomTorneoDb = '';
$tipoTorneoCtx = 0;
if ($torneoActualId > 0) {
    try {
        $stCur = $pdo->prepare('SELECT nombre, grupo_evento_id, COALESCE(tipo, 0) AS tipo FROM torneosact WHERE torneo = :t LIMIT 1');
        $stCur->execute([':t' => $torneoActualId]);
        $tcur = $stCur->fetch(PDO::FETCH_ASSOC);
        if (is_array($tcur)) {
            $nomTorneoDb = trim((string) ($tcur['nombre'] ?? ''));
            $tipoTorneoCtx = (int) ($tcur['tipo'] ?? 0);
            $rawGid = $tcur['grupo_evento_id'] ?? null;
            if ($rawGid !== null && $rawGid !== '' && (int) $rawGid > 0) {
                $grupoDesdeTorneo = (int) $rawGid;
            }
        }
    } catch (Throwable $e) {
        /* Esquema sin grupo_evento_id o error puntual. */
    }
}

$campeonatoParaUrl = 0;
if ($torneoActualId > 0) {
    $campeonatoParaUrl = $grupoDesdeTorneo > 0 ? $grupoDesdeTorneo : 0;
} elseif ($campeonatoCtx > 0) {
    $campeonatoParaUrl = $campeonatoCtx;
}
if ($torneoActualId > 0 && $grupoDesdeTorneo > 0 && $campeonatoCtx !== $grupoDesdeTorneo) {
    AuthService::setDelegadoCampeonatoGrupo($grupoDesdeTorneo);
} elseif ($torneoActualId > 0 && $grupoDesdeTorneo <= 0 && $campeonatoCtx > 0) {
    AuthService::setDelegadoCampeonatoGrupo(null);
}

$delegadoPanelHome = $delegadoDashboardNewSelf;
if ($torneoActualId > 0) {
    $delegadoPanelHome = fvd_torneo_evento_url(
        $torneoActualId,
        $grupoDesdeTorneo > 0 ? $grupoDesdeTorneo : 0
    );
}
AuthService::setDelegadoPanelHomeUrl($delegadoPanelHome);

$qTorneo = [];
if ($torneoActualId > 0) {
    $qTorneo['torneo_id'] = $torneoActualId;
}
if ($campeonatoParaUrl > 0) {
    $qTorneo['campeonato_id'] = $campeonatoParaUrl;
}
if ($adminPortalDelegado && $asociacionId > 0) {
    $qTorneo['asociacion_id'] = $asociacionId;
}
$qTorneoStr = $qTorneo !== [] ? ('?' . http_build_query($qTorneo)) : '';

$msgCampeonatoObl = 'Debe indicar el campeonato (parámetro obligatorio campeonato_id en la URL). Use el ID de grupo de evento o el ID de uno de los torneos del campeonato.';
$inscripcionesCtx = [
    'torneo_id'              => $torneoActualId,
    'torneo_nombre'          => $nomTorneoDb,
    'torneos_en_grupo'       => 0,
    'grupo_evento_id'        => $grupoDesdeTorneo > 0 ? $grupoDesdeTorneo : null,
    'url_destino'            => '',
    'campeonato_en_url'      => $campeonatoParaUrl,
    'msg_campeonato_obligatorio' => $msgCampeonatoObl,
    'fase1_habilitada'       => true,
    'fase2_habilitada'       => true,
    'motivo_fase1_bloqueo'   => '',
    'motivo_fase2_bloqueo'   => '',
];
if ($grupoDesdeTorneo > 0) {
    try {
        $cntGrSql = 'SELECT COUNT(*) FROM torneosact WHERE grupo_evento_id = :g';
        $cntGrBind = [':g' => $grupoDesdeTorneo];
        if ($tipoTorneoCtx >= 1 && $tipoTorneoCtx <= 3) {
            $cntGrSql .= ' AND tipo = :tip';
            $cntGrBind[':tip'] = $tipoTorneoCtx;
        }
        if (\FvdPortal\Services\TorneoFinalizacionService::columnaFinalizadoExiste($pdo)) {
            $cntGrSql .= ' AND (finalizado_en IS NULL)';
        }
        $stCnt = $pdo->prepare($cntGrSql);
        $stCnt->execute($cntGrBind);
        $inscripcionesCtx['torneos_en_grupo'] = (int) $stCnt->fetchColumn();
    } catch (Throwable $e) {
        $inscripcionesCtx['torneos_en_grupo'] = 0;
    }
}
if (\FvdPortal\Services\DelegadoTorneoVentanasService::aplicaRestriccionDelegado() && $torneoActualId > 0) {
    try {
        $ventana = \FvdPortal\Services\DelegadoTorneoVentanasService::estadoParaTorneo(
            $pdo,
            $torneoActualId,
            $asociacionId > 0 ? $asociacionId : null
        );
        $fase1Ok = !empty($ventana['fase1_afiliados_carnets_traspasos']);
        $fase2Ok = !empty($ventana['fase2_inscripciones']);
        $inscripcionesCtx['fase1_habilitada'] = $fase1Ok;
        $inscripcionesCtx['fase2_habilitada'] = $fase2Ok;
        if (!$fase1Ok) {
            $inscripcionesCtx['motivo_fase1_bloqueo'] = 'Fuera de ventana para afiliaciones, carnets y traspasos. Esta opción se habilita en fase 1 o cuando aplica acceso por convocatoria/invitación.';
        }
        if (!$fase2Ok) {
            $inscripcionesCtx['motivo_fase2_bloqueo'] = 'Inscripciones y administración de inscritos no disponibles en este momento. Esta opción se habilita solo durante la fase 2 del torneo.';
        }
    } catch (Throwable $e) {
        error_log('[delegado_dashboard_new ventanas] ' . $e->getMessage());
    }
}

$qAfiliaciones = [
    'action' => 'list',
    'alcance' => 'asociacion',
    'asociacion_id' => $asociacionId > 0 ? $asociacionId : 0,
];
if ($campeonatoParaUrl > 0) {
    $qAfiliaciones['campeonato_id'] = $campeonatoParaUrl;
}
$finDeudaUrl = fvd_master_module_url('deuda_asociacion/index.php');
if ($asociacionId > 0 && $torneoActualId > 0) {
    $finDeudaUrl .= '?action=form&tid=' . $torneoActualId . '&aid=' . $asociacionId;
} elseif ($asociacionId > 0) {
    $finDeudaUrl .= '?action=list';
}

$finPagosUrl = fvd_master_module_url('relacion_pago/index.php');
if ($asociacionId > 0) {
    $finPagosUrl .= '?' . http_build_query(['aid' => $asociacionId]);
}

$urlListadoAtletasAsoc = fvd_master_module_url('atletas/index.php?' . http_build_query($qAfiliaciones));
$fvdBaseTorneoInscripcion = fvd_master_module_url('torneo_inscripcion/index.php' . $qTorneoStr);
$inscribirTorneoUrl = $fvdBaseTorneoInscripcion . '#fvd-insc-sitio-inscribir';
$adminInscritosUrl = fvd_master_module_url('inscripcion_torneo/index.php' . $qTorneoStr);

$urlAfiliarAtleta = fvd_master_module_url('atletas/index.php?' . http_build_query(['action' => 'form'] + ($asociacionId > 0 ? ['asociacion_id' => $asociacionId] : [])));

$actionUrls = [
    'afiliaciones' => $urlListadoAtletasAsoc,
    'afiliar_atleta' => $urlAfiliarAtleta,
    'carnets' => url('fvdmasteradmin/solicitud_carnet.php'),
    'traspasos' => url('fvdmasteradmin/solicitud_traspaso.php'),
    'transferencias' => url('fvdmasteradmin/solicitud_traspaso.php'),
    'panel_torneo_evento' => $torneoActualId > 0
        ? fvd_torneo_evento_url($torneoActualId, $grupoDesdeTorneo > 0 ? $grupoDesdeTorneo : 0)
        : '',
    'inscribir_torneo' => $inscribirTorneoUrl,
    'administrar_inscripciones' => $adminInscritosUrl,
    'finanzas_situacion' => $finDeudaUrl,
    'finanzas_pagos' => $finPagosUrl,
    'detalle_atletas_afiliados' => $urlListadoAtletasAsoc,
    'detalle_afiliaciones' => $urlListadoAtletasAsoc,
    'detalle_carnets' => fvd_master_module_url('atletas/reporte_carnets.php'),
    'detalle_anualidades' => '',
    'detalle_traspasos' => fvd_master_module_url('atletas/reporte_traspasos.php'),
    'detalle_inscritos' => $adminInscritosUrl,
];
$inscripcionesCtx['url_destino'] = (string) ($actionUrls['inscribir_torneo'] ?? '');

$inscripcionesCtx['aviso_torneo_panel'] = '';

$adminPortalCambiarAsocUrl = '';
if ($adminPortalDelegado) {
    if (!function_exists('fvd_append_embed_to_url')) {
        require_once dirname(__DIR__) . '/config/fvd_navigation_return.php';
    }
    $adminPortalCambiarAsocUrl = fvd_append_embed_to_url(url('fvdmasteradmin/operaciones/portal_mirror.php'));
}

$vistaOperativaDelegado = isset($_GET['vista']) && trim((string) $_GET['vista']) === 'operativo';
$novedadesDelegadosUnread = 0;
if (!$adminPortalDelegado && $delegadoUidInt > 0) {
    try {
        $novedadesDelegadosUnread = \FvdPortal\Services\NotificacionesDelegadosService::contarNoLeidas($pdo, $delegadoUidInt);
    } catch (Throwable $e) {
        $novedadesDelegadosUnread = 0;
    }
}
$delegadoNotifPollUrl = url('fvdmasteradmin/delegado_notif_poll.php');

try {
    \FvdPortal\Views\Delegado\Dashboard::render(
        $stats,
        $torneoStats,
        $asociacionLabel,
        $userDisplayName,
        $perfilUrl,
        $logoutUrl,
        $panelUrl,
        $brandLogoUrl,
        $actionUrls,
        $viteTags,
        $inscripcionesCtx,
        $adminPortalDelegado,
        $adminPortalCambiarAsocUrl,
        $delegInvitacionesAgrupadas,
        $delegInvitacionesPendientes,
        rtrim((string) (defined('BASE_URL') ? BASE_URL : ''), '/'),
        $delegadoListaTorneos,
        $torneoActualId,
        $delegadoDashboardNewSelf,
        $vistaOperativaDelegado,
        $novedadesDelegadosUnread,
        $delegadoNotifPollUrl,
        $fvdDelegadoEmbedMaster,
        $afiliadosAfiliacionPorGenero
    );
} catch (Throwable $e) {
    error_log('[delegado_dashboard_new] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=UTF-8');
    }
    echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>Error — panel delegado</title></head><body style="font-family:system-ui;padding:1.5rem;">'
        . '<h1>No se pudo cargar el panel</h1><p>Revise el registro de errores de PHP (p. ej. <code>logs/error.log</code> o el log de Apache) para el detalle técnico.</p>'
        . '</body></html>';
    exit;
}
