<?php
declare(strict_types=1);

require_once __DIR__ . '/services/AuthService.php';
require_once __DIR__ . '/config/db.php';
require_once dirname(__DIR__) . '/config/paths.php';
require_once __DIR__ . '/includes/vite_assets.php';
require_once __DIR__ . '/includes/fvd_brand.php';
require_once dirname(__DIR__) . '/src/Views/Delegado/Dashboard.php';
require_once dirname(__DIR__) . '/src/Services/DelegadoTorneoNotifService.php';

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

$delegadoUidInt = (int) (AuthService::userId() ?? 0);
$delegInvitacionesAgrupadas = [];
$delegInvitacionesPendientes = 0;
if (!$adminPortalDelegado && $delegadoUidInt > 0 && $asociacionId > 0) {
    try {
        $ctxPrev = AuthService::delegadoTorneoContextId();
        if (($ctxPrev === null || (int) $ctxPrev <= 0)) {
            $sug = \FvdPortal\Services\DelegadoTorneoNotifService::sugerirContextoDesdeInvitacionesPendientes(
                $pdo,
                $delegadoUidInt,
                $asociacionId
            );
            if ((int) ($sug['torneo_id'] ?? 0) > 0) {
                AuthService::setDelegadoTorneoContext((int) $sug['torneo_id']);
            }
            if ((int) ($sug['grupo_evento_id'] ?? 0) > 0) {
                AuthService::setDelegadoCampeonatoGrupo((int) $sug['grupo_evento_id']);
            }
        }
        $delegInvitacionesAgrupadas = \FvdPortal\Services\DelegadoTorneoNotifService::listarParaDelegadoVistaAgrupada(
            $pdo,
            $delegadoUidInt,
            24,
            $asociacionId
        );
        $delegInvitacionesPendientes = \FvdPortal\Services\DelegadoTorneoNotifService::contarPendientesVistaAgrupada(
            $pdo,
            $delegadoUidInt,
            $asociacionId
        );
    } catch (Throwable $e) {
        error_log('[delegado_dashboard_new invitaciones] ' . $e->getMessage());
    }
}

if ($asociacionId > 0) {
    try {
        $stmt = $pdo->prepare(
            'SELECT
                COUNT(*) AS atletas_afiliados,
                SUM(CASE WHEN COALESCE(afiliacion, 0) = 1 THEN 1 ELSE 0 END) AS afiliaciones,
                SUM(CASE WHEN COALESCE(carnet, 0) = 1 THEN 1 ELSE 0 END) AS carnets,
                SUM(CASE WHEN COALESCE(anualidad, 0) = 1 THEN 1 ELSE 0 END) AS anualidades,
                SUM(CASE WHEN COALESCE(traspaso, 0) = 1 THEN 1 ELSE 0 END) AS traspasos,
                SUM(CASE WHEN COALESCE(inscripcion, 0) = 1 THEN 1 ELSE 0 END) AS inscritos
             FROM atletas
             WHERE asociacion = :asoc'
        );
        $stmt->execute([':asoc' => $asociacionId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (is_array($row)) {
            foreach (array_keys($stats) as $k) {
                $stats[$k] = (int) ($row[$k] ?? 0);
            }
        }

        $stTor = $pdo->prepare(
            'SELECT
                a.torneo_id,
                COALESCE(NULLIF(TRIM(t.nombre), \'\'), CONCAT(\'Torneo #\', a.torneo_id)) AS torneo_nombre,
                COUNT(*) AS atletas_afiliados,
                SUM(CASE WHEN COALESCE(a.afiliacion, 0) = 1 THEN 1 ELSE 0 END) AS afiliaciones,
                SUM(CASE WHEN COALESCE(a.carnet, 0) = 1 THEN 1 ELSE 0 END) AS carnets,
                SUM(CASE WHEN COALESCE(a.anualidad, 0) = 1 THEN 1 ELSE 0 END) AS anualidades,
                SUM(CASE WHEN COALESCE(a.traspaso, 0) = 1 THEN 1 ELSE 0 END) AS traspasos,
                SUM(CASE WHEN COALESCE(a.inscripcion, 0) = 1 THEN 1 ELSE 0 END) AS inscritos
             FROM atletas a
             LEFT JOIN torneosact t ON t.torneo = a.torneo_id
             WHERE a.asociacion = :asoc AND COALESCE(a.torneo_id, 0) > 0
             GROUP BY a.torneo_id, t.nombre
             ORDER BY a.torneo_id DESC'
        );
        $stTor->execute([':asoc' => $asociacionId]);
        $torneoRows = $stTor->fetchAll(PDO::FETCH_ASSOC);
        foreach ($torneoRows as $tr) {
            $torneoStats[] = [
                'torneo_id' => (int) ($tr['torneo_id'] ?? 0),
                'torneo_nombre' => (string) ($tr['torneo_nombre'] ?? ''),
                'atletas_afiliados' => (int) ($tr['atletas_afiliados'] ?? 0),
                'afiliaciones' => (int) ($tr['afiliaciones'] ?? 0),
                'carnets' => (int) ($tr['carnets'] ?? 0),
                'anualidades' => (int) ($tr['anualidades'] ?? 0),
                'traspasos' => (int) ($tr['traspasos'] ?? 0),
                'inscritos' => (int) ($tr['inscritos'] ?? 0),
            ];
        }
    } catch (Throwable $e) {
        /* Fallback de compatibilidad si el esquema usa "persona". */
        try {
            $stmtAlt = $pdo->prepare('SELECT COUNT(*) AS atletas_afiliados FROM persona WHERE asociacion_id = :asoc');
            $stmtAlt->execute([':asoc' => $asociacionId]);
            $stats['atletas_afiliados'] = (int) $stmtAlt->fetchColumn();
        } catch (Throwable $e2) {
            $stats['atletas_afiliados'] = 0;
        }
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

/* delegados-app no siempre declara CSS en manifest; inyectamos app.css para asegurar Tailwind. */
$manifestPath = dirname(__DIR__) . '/public/build/.vite/manifest.json';
if (is_readable($manifestPath)) {
    $decoded = json_decode((string) file_get_contents($manifestPath), true);
    if (is_array($decoded)) {
        $baseBuild = rtrim(url('public/build'), '/');
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
$torneoCtx = (int) (AuthService::delegadoTorneoContextId() ?? 0);
$campeonatoCtx = (int) (AuthService::delegadoCampeonatoGrupoId() ?? 0);

$torneoActualId = $torneoCtx > 0
    ? $torneoCtx
    : (isset($torneoStats[0]['torneo_id']) && (int) $torneoStats[0]['torneo_id'] > 0 ? (int) $torneoStats[0]['torneo_id'] : 0);

$grupoDesdeTorneo = 0;
$nomTorneoDb = '';
if ($torneoActualId > 0) {
    try {
        $stCur = $pdo->prepare('SELECT nombre, grupo_evento_id FROM torneosact WHERE torneo = :t LIMIT 1');
        $stCur->execute([':t' => $torneoActualId]);
        $tcur = $stCur->fetch(PDO::FETCH_ASSOC);
        if (is_array($tcur)) {
            $nomTorneoDb = trim((string) ($tcur['nombre'] ?? ''));
            $rawGid = $tcur['grupo_evento_id'] ?? null;
            if ($rawGid !== null && $rawGid !== '' && (int) $rawGid > 0) {
                $grupoDesdeTorneo = (int) $rawGid;
            }
        }
    } catch (Throwable $e) {
        /* Esquema sin grupo_evento_id o error puntual. */
    }
}

$campeonatoParaUrl = $campeonatoCtx > 0 ? $campeonatoCtx : ($grupoDesdeTorneo > 0 ? $grupoDesdeTorneo : 0);
if ($grupoDesdeTorneo > 0 && $campeonatoCtx <= 0) {
    AuthService::setDelegadoCampeonatoGrupo($grupoDesdeTorneo);
}

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
];
if ($grupoDesdeTorneo > 0) {
    try {
        $stCnt = $pdo->prepare('SELECT COUNT(*) FROM torneosact WHERE grupo_evento_id = :g');
        $stCnt->execute([':g' => $grupoDesdeTorneo]);
        $inscripcionesCtx['torneos_en_grupo'] = (int) $stCnt->fetchColumn();
    } catch (Throwable $e) {
        $inscripcionesCtx['torneos_en_grupo'] = 0;
    }
}
if ($inscripcionesCtx['torneo_nombre'] === '' && isset($torneoStats[0]['torneo_nombre'])) {
    $inscripcionesCtx['torneo_nombre'] = (string) $torneoStats[0]['torneo_nombre'];
}

$qAfiliaciones = [
    'action' => 'list',
    'alcance' => 'asociacion',
    'asociacion_id' => $asociacionId > 0 ? $asociacionId : 0,
];
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

$actionUrls = [
    'afiliaciones' => fvd_master_module_url('atletas/index.php?' . http_build_query($qAfiliaciones)),
    'carnets' => url('fvdmasteradmin/solicitud_carnet.php'),
    'transferencias' => url('fvdmasteradmin/solicitud_traspaso.php'),
    'inscribir_torneo' => admin_module_url('torneo_inscripcion/index.php' . $qTorneoStr),
    'administrar_inscripciones' => fvd_master_module_url('inscripcion_torneo/index.php' . $qTorneoStr),
    'finanzas_situacion' => $finDeudaUrl,
    'finanzas_pagos' => $finPagosUrl,
    'detalle_atletas_afiliados' => url('atleta/index.php'),
    'detalle_afiliaciones' => url('atleta/index.php'),
    'detalle_carnets' => url('atleta/index.php?filter=carnets'),
    'detalle_anualidades' => '',
    'detalle_traspasos' => url('atleta/index.php?filter=traspasos'),
    'detalle_inscritos' => url('torneos/inscripciones.php'),
];
$inscripcionesCtx['url_destino'] = (string) ($actionUrls['inscribir_torneo'] ?? '');

$avisoTorneoPanel = '';
if ($torneoActualId <= 0) {
    $avisoTorneoPanel = 'No hay torneo disponible ni activo: no tiene un torneo fijado en la sesión y no hay datos que permitan determinar un evento (por ejemplo, atletas vinculados a un torneo o convocatoria abierta). Revise invitaciones en la barra superior o espere a que la federación asigne el contexto del evento.';
} elseif ($campeonatoParaUrl <= 0) {
    $avisoTorneoPanel = 'No se puede determinar el campeonato (grupo de evento) del torneo en contexto. Las inscripciones y enlaces por evento quedan deshabilitados hasta que el torneo esté vinculado correctamente en la base de datos.';
}
$inscripcionesCtx['aviso_torneo_panel'] = $avisoTorneoPanel;

$adminPortalCambiarAsocUrl = '';
if ($adminPortalDelegado) {
    if (!function_exists('fvd_append_embed_to_url')) {
        require_once dirname(__DIR__) . '/config/fvd_navigation_return.php';
    }
    $adminPortalCambiarAsocUrl = fvd_append_embed_to_url(url('fvdmasteradmin/operaciones/portal_mirror.php'));
}

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
    rtrim((string) (defined('BASE_URL') ? BASE_URL : ''), '/')
);
