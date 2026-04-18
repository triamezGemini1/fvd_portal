<?php
/**
 * Cabecera HTML: sesión obligatoria, roles opcionales, UI compacta 13".
 *
 * Antes de incluir (opcional):
 *   $fvd_page_title = 'Mi página';
 *   $fvd_required_roles = ['fvd_admin', 'aso_admin'];
 */

declare(strict_types=1);

$fvdRoot = dirname(__DIR__);
require_once $fvdRoot . '/services/AuthService.php';
require_once $fvdRoot . '/config/ui_settings.php';

AuthService::ensureSession();
AuthService::requireLogin();

if (isset($fvd_required_roles) && is_array($fvd_required_roles) && $fvd_required_roles !== []) {
    AuthService::requireRoles($fvd_required_roles);
}

$fvd_page_title = isset($fvd_page_title) && is_string($fvd_page_title) && $fvd_page_title !== ''
    ? $fvd_page_title
    : 'FVD Master Admin';

$fvd_user = AuthService::user();

if (!function_exists('fvd_module_url')) {
    require_once $fvdRoot . '/modules/_init.php';
}
$fvdProjRoot = dirname($fvdRoot);
if (!function_exists('url')) {
    require_once $fvdProjRoot . '/config/paths.php';
}
if (!function_exists('fvd_return_from_request')) {
    require_once $fvdProjRoot . '/config/paths.php';
}
$fvd_return_nav_url = null;
if (isset($fvd_page_return_url) && is_string($fvd_page_return_url) && $fvd_page_return_url !== '') {
    $fvd_return_nav_url = fvd_return_sanitize($fvd_page_return_url);
} else {
    $fvd_return_nav_url = fvd_return_from_request();
}
require_once $fvdRoot . '/includes/fvd_brand.php';
$fvd_brand_logo_url = fvd_brand_logo_public_url();
$fvdUiCss = url('assets/css/fvd-ui-mistorneos.css');
$fvdNavBase = rtrim((string) env('APP_BASE_PATH', ''), '/') . '/fvdmasteradmin';
$fvdPanelUrl = $fvdNavBase . '/index.php';

$fvdScript = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));

$fvd_can_nav_modules = AuthService::checkAccess([
    AuthService::ROLE_FVD_ADMIN,
    AuthService::ROLE_ASO_ADMIN,
    AuthService::ROLE_DELEGADO_ASOC,
]);
if (!isset($fvd_sidebar_active)) {
    $fvd_sidebar_active = 'panel';
    if (preg_match('#/fvdmasteradmin/perfil\\.php#i', $fvdScript)) {
        $fvd_sidebar_active = 'perfil';
    } elseif (strpos($fvdScript, '/dashboard/') !== false) {
        $fvd_sidebar_active = 'dashboard_lite';
    } elseif (str_contains($fvdScript, '/admin/modules/asociaciones/')) {
        $fvd_sidebar_active = 'asociaciones';
    } elseif (str_contains($fvdScript, '/admin/modules/atletas/')) {
        $fvd_sidebar_active = 'atletas';
    } elseif (str_contains($fvdScript, '/admin/modules/torneos/')) {
        $fvd_sidebar_active = 'torneos';
    } elseif (str_contains($fvdScript, '/admin/modules/invitaciones/')) {
        $fvd_sidebar_active = 'invitaciones';
    } elseif (str_contains($fvdScript, '/modules/invitaciones/')) {
        $fvd_sidebar_active = 'invitaciones';
    } elseif (str_contains($fvdScript, '/modules/solicitudes_delegado/')) {
        $fvd_sidebar_active = 'solicitudes_delegado';
    } elseif (str_contains($fvdScript, '/admin/modules/solicitudes_delegado/')) {
        $fvd_sidebar_active = 'solicitudes_delegado';
    } elseif (str_contains($fvdScript, '/modules/asociaciones/')) {
        $fvd_sidebar_active = 'asociaciones';
    } elseif (str_contains($fvdScript, '/modules/atletas/')) {
        $fvd_sidebar_active = 'atletas';
    } elseif (str_contains($fvdScript, '/atletas/listado.php')) {
        $fvd_sidebar_active = 'atletas_clasico';
    } elseif (str_contains($fvdScript, '/modules/torneos/')) {
        $fvd_sidebar_active = 'torneos';
    } elseif (str_contains($fvdScript, '/modules/costos/')) {
        $fvd_sidebar_active = 'costos';
    } elseif (str_contains($fvdScript, '/modules/deuda_asociacion/')) {
        $fvd_sidebar_active = (isset($_GET['fvd_from']) && (string) $_GET['fvd_from'] === 'informes')
            ? 'informe_deudas_resumen'
            : 'deudas';
    } elseif (str_contains($fvdScript, '/modules/relacion_pago/')) {
        $fvd_sidebar_active = 'pagos';
    } elseif (str_contains($fvdScript, '/modules/inscripciones/')) {
        $fvd_sidebar_active = 'inscripciones';
    } elseif (str_contains($fvdScript, '/modules/inscripcion_torneo/')) {
        $fvd_sidebar_active = 'inscripcion_torneo';
    } elseif (str_contains($fvdScript, '/modules/torneo_inscripcion/')
        || str_contains($fvdScript, '/admin/modules/torneo_inscripcion/')) {
        $fvd_sidebar_active = 'torneo_inscripcion';
    } elseif (str_contains($fvdScript, '/atleta/mi_ficha.php')) {
        $fvd_sidebar_active = 'mi_ficha';
    }
}

if (str_contains($fvdScript, '/modules/atletas/') || str_contains($fvdScript, '/admin/modules/atletas/')) {
    $rawAtAct = isset($_GET['action']) ? trim((string) $_GET['action']) : '';
    $atImpliesList = isset($_GET['tab']) || isset($_GET['page']) || isset($_GET['cedula'])
        || (isset($_GET['q']) && trim((string) $_GET['q']) !== '');
    $atEff = $rawAtAct === '' ? ($atImpliesList ? 'list' : 'form') : $rawAtAct;
    if ($atEff === 'form' && (!isset($_GET['id']) || (string) $_GET['id'] === '')) {
        $fvd_sidebar_active = 'atletas_nuevo';
    } elseif ($atEff === 'list' || ($rawAtAct === '' && $atImpliesList)) {
        $fvd_sidebar_active = 'atletas';
    }
}
if (str_contains($fvdScript, 'solicitudes_delegado')) {
    $tfSide = isset($_GET['tipo']) ? trim((string) $_GET['tipo']) : '';
    if ($tfSide === 'traspaso') {
        $fvd_sidebar_active = 'sol_traspasos_fvd';
    } elseif ($tfSide === 'carnet' || $tfSide === 'carnet_afiliacion') {
        $fvd_sidebar_active = 'sol_carnets_fvd';
    }
}
if (str_contains($fvdScript, 'reporte_carnets.php')) {
    $fvd_sidebar_active = 'informe_carnets';
}
if (str_contains($fvdScript, 'reporte_traspasos.php')) {
    $fvd_sidebar_active = 'informe_traspasos';
}
if (str_contains($fvdScript, 'reporte_indicadores.php')) {
    $fvd_sidebar_active = 'informe_indicadores_atletas';
}
if (str_contains($fvdScript, '/atletas/export.php')) {
    $fvd_sidebar_active = 'informe_export_atletas';
}

$fvd_perfil_url = $fvdNavBase . '/perfil.php';
$fvd_mi_ficha_url = $fvdNavBase . '/atleta/mi_ficha.php';
$fvd_public_landing_url = url('index.php');

$fvd_sn_active = static function (string $key) use ($fvd_sidebar_active): string {
    return $key === $fvd_sidebar_active ? ' fvd-sn--active' : '';
};

$fvd_topbar_asoc_nombre = '';
$fvd_topbar_asoc_nombre_raw = '';
$fvd_topbar_asoc_logo_url = null;
$aidTopbar = AuthService::idAsociacion();
if ($aidTopbar !== null && $aidTopbar > 0) {
    require_once $fvdRoot . '/config/db.php';
    require_once $fvdRoot . '/includes/fvd_asociacion_helpers.php';
    try {
        $pdoTop = fvd_db();
        $stTop = $pdoTop->prepare('SELECT nombre, logo FROM asociaciones WHERE id = :id LIMIT 1');
        $stTop->execute([':id' => $aidTopbar]);
        $rowTop = $stTop->fetch(PDO::FETCH_ASSOC);
        if (is_array($rowTop)) {
            $fvd_topbar_asoc_nombre_raw = (string) ($rowTop['nombre'] ?? '');
            $fvd_topbar_asoc_nombre = fvd_asoc_nombre_sin_prefijo($fvd_topbar_asoc_nombre_raw);
            $appTop = rtrim((string) env('APP_BASE_PATH', ''), '/');
            $fvd_topbar_asoc_logo_url = fvd_asociacion_logo_public_url(
                $appTop,
                $fvdProjRoot,
                isset($rowTop['logo']) ? (string) $rowTop['logo'] : null
            );
        }
    } catch (Throwable $e) {
        $fvd_topbar_asoc_nombre = '';
        $fvd_topbar_asoc_nombre_raw = '';
        $fvd_topbar_asoc_logo_url = null;
    }
}

$fvd_topbar_deleg_notif_no_vistas = 0;
if (AuthService::isDelegadoAsociacion()) {
    try {
        require_once $fvdProjRoot . '/fvdmasteradmin/config/db.php';
        require_once $fvdProjRoot . '/src/Services/DelegadoTorneoNotifService.php';
        $pdoDelegNotif = fvd_db();
        $fvd_topbar_deleg_notif_no_vistas = \FvdPortal\Services\DelegadoTorneoNotifService::contarNoVistas(
            $pdoDelegNotif,
            (int) AuthService::userId()
        );
    } catch (Throwable $e) {
        $fvd_topbar_deleg_notif_no_vistas = 0;
    }
}

$fvd_deleg_ctx_tid = 0;
if (AuthService::isDelegadoAsociacion()) {
    $cCtx = AuthService::delegadoTorneoContextId();
    $fvd_deleg_ctx_tid = ($cCtx !== null && (int) $cCtx > 0) ? (int) $cCtx : 0;
}
$fvd_deleg_torneo_q = $fvd_deleg_ctx_tid > 0 ? ('?torneo_id=' . $fvd_deleg_ctx_tid) : '';

$fvd_acc_datos_open = in_array(
    $fvd_sidebar_active,
    ['asociaciones', 'atletas', 'invitaciones', 'solicitudes_delegado'],
    true
);
$fvd_acc_asoc_torneos_open = ($fvd_sidebar_active === 'torneos');
$fvd_acc_fin_open = in_array(
    $fvd_sidebar_active,
    ['costos', 'deudas', 'pagos', 'torneo_inscripcion', 'inscripciones', 'inscripcion_torneo'],
    true
);
$fvd_es_admin_fvd = AuthService::isSuperAdmin();
$fvd_acc_adm_asoc_open = ($fvd_sidebar_active === 'asociaciones');
$fvd_acc_adm_atletas_open = in_array($fvd_sidebar_active, ['atletas', 'atletas_nuevo'], true);
$fvd_acc_adm_torneos_open = ($fvd_sidebar_active === 'torneos');
$fvd_acc_adm_sol_open = in_array($fvd_sidebar_active, ['sol_traspasos_fvd', 'sol_carnets_fvd', 'solicitudes_delegado'], true);
$fvd_acc_adm_fin_open = in_array(
    $fvd_sidebar_active,
    ['costos', 'deudas', 'pagos', 'torneo_inscripcion', 'inscripciones', 'inscripcion_torneo'],
    true
);
$fvd_acc_adm_inf_open = in_array(
    $fvd_sidebar_active,
    ['inscripciones', 'inscripcion_torneo', 'torneo_inscripcion', 'informe_carnets', 'informe_traspasos', 'informe_indicadores_atletas', 'informe_export_atletas', 'informe_deudas_resumen'],
    true
);

$fvd_revision_altas = 0;
$fvd_revision_sol = 0;
$fvd_revision_total = 0;
if ($fvd_es_admin_fvd) {
    require_once $fvdProjRoot . '/src/Services/FvdAdminRevisionPendienteService.php';
    try {
        if (!function_exists('fvd_db')) {
            require_once $fvdRoot . '/config/db.php';
        }
        $rev = \FvdPortal\Services\FvdAdminRevisionPendienteService::conteos(fvd_db());
        $fvd_revision_altas = (int) ($rev['nuevas_altas_delegado'] ?? 0);
        $fvd_revision_sol = (int) ($rev['solicitudes_pendientes'] ?? 0);
        $fvd_revision_total = (int) ($rev['total'] ?? 0);
    } catch (Throwable $e) {
        error_log('[layout_header revision pendiente] ' . $e->getMessage());
    }
}

header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($fvd_page_title, ENT_QUOTES, 'UTF-8') ?> · FVD — Federación Venezolana de Dominó</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= htmlspecialchars($fvdUiCss, ENT_QUOTES, 'UTF-8') ?>">
    <style>
        :root {
            --fvd-font-body: <?= FVD_UI_FONT_BODY_13IN ?>;
            --fvd-font-h1: <?= FVD_UI_FONT_H1_COMPACT ?>;
            --fvd-font-h2: <?= FVD_UI_FONT_H2_COMPACT ?>;
            --fvd-font-h3: <?= FVD_UI_FONT_H3_COMPACT ?>;
            --fvd-line: <?= FVD_UI_LINE_NORMAL ?>;
            --fvd-page-pad: <?= FVD_UI_PAD_PAGE_COMPACT ?>;
            --fvd-card-pad: <?= FVD_UI_PAD_CARD_COMPACT ?>;
            --fvd-nav-y: <?= FVD_UI_PAD_NAVBAR_Y_COMPACT ?>;
            --fvd-nav-x: <?= FVD_UI_PAD_NAVBAR_X_COMPACT ?>;
            --fvd-input-y: <?= FVD_UI_PAD_INPUT_Y_COMPACT ?>;
            --fvd-input-x: <?= FVD_UI_PAD_INPUT_X_COMPACT ?>;
            --fvd-table-y: <?= FVD_UI_PAD_TABLE_CELL_Y_COMPACT ?>;
            --fvd-table-x: <?= FVD_UI_PAD_TABLE_CELL_X_COMPACT ?>;
            --fvd-max: <?= FVD_UI_CONTAINER_MAX ?>;
            --fvd-border: rgba(255, 255, 255, 0.14);
            --fvd-bg: <?= htmlspecialchars(FVD_UI_COLOR_AZUL, ENT_QUOTES, 'UTF-8') ?>;
            --fvd-text: #f8fafc;
            --fvd-muted: #cbd5e1;
            --fvd-azul: <?= htmlspecialchars(FVD_UI_COLOR_AZUL, ENT_QUOTES, 'UTF-8') ?>;
            --fvd-amarillo: <?= htmlspecialchars(FVD_UI_COLOR_AMARILLO, ENT_QUOTES, 'UTF-8') ?>;
            --fvd-rojo: <?= htmlspecialchars(FVD_UI_COLOR_ROJO, ENT_QUOTES, 'UTF-8') ?>;
            --fvd-azul-card: <?= htmlspecialchars(FVD_UI_COLOR_AZUL_CARD, ENT_QUOTES, 'UTF-8') ?>;
            --fvd-dorado: var(--fvd-amarillo);
            --fvd-primary: var(--fvd-azul);
            --fvd-mt-primary-50: rgba(255, 255, 255, 0.08);
        }
        *, *::before, *::after { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: 'Inter', system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            font-size: var(--fvd-font-body);
            line-height: var(--fvd-line);
            color: var(--fvd-text);
            background: var(--fvd-bg);
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }
        .fvd-shell { min-height: 100vh; display: flex; flex-direction: row; align-items: stretch; position: relative; }
        .fvd-mnav-toggle {
            display: none;
            position: fixed;
            top: 10px;
            left: 10px;
            z-index: 300;
            align-items: center;
            justify-content: center;
            width: 42px;
            height: 42px;
            padding: 0;
            border: 1px solid var(--fvd-border);
            border-radius: 8px;
            background: var(--fvd-azul-card);
            color: var(--fvd-text);
            font-size: 1.35rem;
            line-height: 1;
            cursor: pointer;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.25);
        }
        .fvd-sidebar-backdrop {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.45);
            z-index: 140;
        }
        .fvd-shell--nav-open .fvd-sidebar-backdrop { display: block; }
        .fvd-sidebar {
            width: 16.75rem;
            flex-shrink: 0;
            min-height: 100vh;
            background: var(--fvd-azul-card);
            border-right: 2px solid var(--fvd-amarillo);
            display: flex;
            flex-direction: column;
            z-index: 150;
            box-shadow: 2px 0 12px rgba(0, 0, 0, 0.12);
            transition: width 0.2s ease;
        }
        .fvd-shell--sidebar-rail .fvd-sidebar {
            width: 4.5rem;
        }
        .fvd-shell--sidebar-rail .fvd-sidebar-head {
            padding: 8px 6px;
        }
        .fvd-shell--sidebar-rail .fvd-sidebar-brand {
            font-size: 0.58rem;
            line-height: 1.15;
            text-align: center;
            display: block;
            padding: 4px 2px;
        }
        .fvd-shell--sidebar-rail .fvd-sn {
            font-size: 0.55rem;
            line-height: 1.1;
            padding: 6px 4px;
            margin: 2px 4px;
            text-align: center;
            max-height: 2.8em;
            overflow: hidden;
        }
        .fvd-shell--sidebar-rail .fvd-sn-acc {
            margin: 2px 4px;
        }
        .fvd-shell--sidebar-rail .fvd-sn-acc__summary {
            font-size: 0.45rem;
            padding: 6px 4px;
            line-height: 1.15;
            justify-content: center;
        }
        .fvd-shell--sidebar-rail .fvd-sn-acc__chev { display: none; }
        .fvd-shell--sidebar-rail .fvd-sidebar-user {
            padding: 8px 6px;
            font-size: 0.55rem;
        }
        .fvd-shell--sidebar-rail .fvd-logout {
            font-size: 0.55rem;
            padding: 6px 4px;
        }
        .fvd-sidebar-rail-toggle {
            width: 100%;
            margin-bottom: 6px;
            padding: 6px 8px;
            border-radius: 6px;
            border: 1px solid var(--fvd-border);
            background: rgba(0, 0, 0, 0.2);
            color: var(--fvd-amarillo);
            font-size: 0.75rem;
            font-weight: 700;
            cursor: pointer;
        }
        .fvd-sidebar-rail-toggle:hover {
            background: rgba(255, 242, 0, 0.12);
        }
        .fvd-sidebar-head {
            padding: var(--fvd-nav-y) 14px;
            border-bottom: 1px solid var(--fvd-border);
        }
        .fvd-sidebar-fvd-identity {
            text-align: center;
            margin-bottom: 8px;
            padding-bottom: 10px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        }
        .fvd-sidebar-fvd-logo {
            display: block;
            margin: 0 auto 6px;
            max-width: 100%;
            height: auto;
            max-height: 52px;
            object-fit: contain;
        }
        .fvd-sidebar-fvd-tagline {
            margin: 4px 0 0;
            font-size: 0.62rem;
            line-height: 1.3;
            color: var(--fvd-muted);
            font-weight: 500;
            letter-spacing: 0.04em;
        }
        .fvd-shell--sidebar-rail .fvd-sidebar-fvd-logo { max-height: 30px; }
        .fvd-shell--sidebar-rail .fvd-sidebar-fvd-tagline { display: none; }
        .fvd-sidebar-brand {
            font-weight: 700;
            font-size: var(--fvd-font-h3);
            color: var(--fvd-text);
            text-decoration: none;
        }
        .fvd-sidebar-brand:hover { color: var(--fvd-amarillo); }
        .fvd-sidebar-nav { flex: 1; overflow-y: auto; padding: 10px 0 16px; }
        .fvd-sn {
            display: block;
            margin: 2px 8px;
            padding: 8px 12px;
            border-radius: 6px;
            color: var(--fvd-text);
            text-decoration: none;
            font-size: var(--fvd-font-body);
            font-weight: 500;
            border-left: 3px solid transparent;
        }
        .fvd-sn:hover { background: rgba(255, 255, 255, 0.08); color: var(--fvd-amarillo); }
        .fvd-sn--pend {
            border-left-color: var(--fvd-amarillo) !important;
            background: rgba(255, 242, 0, 0.1);
            font-weight: 600;
        }
        .fvd-pend-badge {
            display: inline-block;
            min-width: 1.25em;
            margin-left: 6px;
            padding: 1px 6px;
            border-radius: 999px;
            font-size: 0.72em;
            font-weight: 800;
            line-height: 1.35;
            background: var(--fvd-rojo);
            color: #fff;
            vertical-align: middle;
        }
        .fvd-sn--active {
            background: rgba(255, 242, 0, 0.12);
            color: var(--fvd-amarillo);
            font-weight: 600;
            border-left-color: var(--fvd-amarillo);
        }
        .fvd-sn-acc {
            margin: 4px 8px 6px;
            border-radius: 8px;
            border: 1px solid rgba(255, 255, 255, 0.08);
            background: rgba(0, 0, 0, 0.12);
        }
        .fvd-sn-acc[open] {
            border-color: rgba(255, 242, 0, 0.22);
            background: rgba(0, 0, 0, 0.18);
        }
        .fvd-sn-acc__summary {
            list-style: none;
            cursor: pointer;
            padding: 10px 12px;
            font-size: 0.65rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--fvd-muted);
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            user-select: none;
        }
        .fvd-sn-acc__summary::-webkit-details-marker { display: none; }
        .fvd-sn-acc__summary::marker { content: ''; }
        .fvd-sn-acc__chev {
            flex-shrink: 0;
            width: 0;
            height: 0;
            border-left: 5px solid transparent;
            border-right: 5px solid transparent;
            border-top: 6px solid var(--fvd-muted);
            transition: transform 0.2s ease;
            margin-top: 2px;
        }
        .fvd-sn-acc[open] .fvd-sn-acc__chev {
            transform: rotate(180deg);
        }
        .fvd-sn-acc[open] .fvd-sn-acc__summary {
            color: #e2e8f0;
        }
        .fvd-sn-acc__body {
            padding: 0 4px 8px;
        }
        .fvd-sn-acc__body .fvd-sn {
            margin: 2px 4px;
        }
        .fvd-sidebar-hint { padding: 8px 14px; font-size: 0.8rem; color: var(--fvd-muted); line-height: 1.35; }
        .fvd-sidebar-user {
            padding: 12px 14px;
            border-top: 1px solid var(--fvd-border);
            font-size: var(--fvd-font-body);
            color: var(--fvd-muted);
        }
        .fvd-sidebar-user strong { color: var(--fvd-text); display: block; margin-bottom: 4px; font-weight: 600; }
        .fvd-sidebar .fvd-logout {
            display: block;
            margin-top: 10px;
            text-align: center;
            color: var(--fvd-text);
            padding: 8px 10px;
            border-radius: 6px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-bottom: 2px solid var(--fvd-rojo);
            text-decoration: none;
            font-weight: 600;
        }
        .fvd-sidebar .fvd-logout:hover {
            background: rgba(190, 18, 60, 0.15);
            color: #fff;
        }
        .fvd-main-column {
            flex: 1;
            min-width: 0;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }
        .fvd-topbar {
            flex-shrink: 0;
            border-bottom: 1px solid var(--fvd-border);
            background: rgba(0, 0, 0, 0.18);
        }
        .fvd-topbar__inner {
            display: grid;
            grid-template-columns: minmax(0, auto) 1fr minmax(0, auto);
            align-items: center;
            gap: 0.5rem 0.75rem;
            max-width: var(--fvd-max);
            width: 100%;
            margin: 0 auto;
            padding: 0.35rem var(--fvd-page-pad);
            min-height: 48px;
            box-sizing: border-box;
        }
        .fvd-topbar__brand { justify-self: start; display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap; min-width: 0; }
        .fvd-topbar__fvd-lockup {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            min-width: 0;
            max-width: 100%;
            text-decoration: none;
            color: inherit;
        }
        .fvd-topbar__fvd-lockup:hover .fvd-topbar__page-title { color: var(--fvd-amarillo); }
        .fvd-topbar__fvd-logo {
            height: 40px;
            width: auto;
            max-width: 104px;
            object-fit: contain;
            flex-shrink: 0;
            display: block;
        }
        .fvd-topbar__page-title {
            font-size: clamp(0.72rem, 2.1vw, 0.92rem);
            font-weight: 600;
            color: var(--fvd-text);
            line-height: 1.25;
            max-width: min(52vw, 19rem);
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .fvd-topbar__asoc-badge {
            height: 36px;
            width: auto;
            max-width: 88px;
            object-fit: contain;
            border-radius: 4px;
            flex-shrink: 0;
            display: block;
        }
        .fvd-topbar__logo {
            display: block;
            max-height: 44px;
            max-width: 120px;
            width: auto;
            height: auto;
            object-fit: contain;
        }
        .fvd-topbar__center {
            justify-self: center;
            text-align: center;
            min-width: 0;
            max-width: 100%;
            padding: 0 0.25rem;
        }
        .fvd-topbar__asoc-name {
            font-size: clamp(0.8rem, 2.4vw, 1.08rem);
            font-weight: 700;
            color: var(--fvd-amarillo);
            line-height: 1.2;
            display: block;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .fvd-topbar__actions { justify-self: end; display: flex; align-items: center; gap: 0.5rem; flex-shrink: 0; }
        .fvd-topbar__notif-inv {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.28rem 0.55rem;
            border-radius: 6px;
            font-size: 0.78rem;
            font-weight: 700;
            text-decoration: none;
            color: var(--fvd-azul, #2e3092);
            background: var(--fvd-amarillo, #fff200);
            border: 1px solid rgba(46, 48, 146, 0.25);
            line-height: 1.2;
            max-width: min(42vw, 11rem);
            white-space: nowrap;
        }
        .fvd-topbar__notif-inv:hover {
            filter: brightness(1.06);
            color: var(--fvd-azul, #2e3092);
        }
        .fvd-topbar__notif-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 1.15rem;
            height: 1.15rem;
            padding: 0 0.28rem;
            border-radius: 999px;
            font-size: 0.68rem;
            font-weight: 800;
            background: var(--fvd-azul, #2e3092);
            color: #fff;
        }
        .fvd-topbar__perfil {
            display: inline-flex;
            align-items: center;
            padding: 0.28rem 0.65rem;
            border-radius: 6px;
            font-size: 0.8125rem;
            font-weight: 600;
            color: var(--fvd-text);
            text-decoration: none;
            border: 1px solid rgba(255, 255, 255, 0.18);
            border-bottom: 2px solid var(--fvd-amarillo);
            background: rgba(255, 255, 255, 0.06);
        }
        .fvd-topbar__perfil:hover {
            color: var(--fvd-amarillo);
            background: rgba(255, 242, 0, 0.1);
        }
        .fvd-topbar__perfil--active {
            color: var(--fvd-amarillo);
            background: rgba(255, 242, 0, 0.12);
            border-left: 3px solid var(--fvd-amarillo);
            padding-left: calc(0.75rem - 3px);
        }
        .fvd-return-bar {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 10px;
            margin: 0 0 12px;
            padding: 8px 12px;
            border-radius: 8px;
            border: 1px solid var(--fvd-border);
            background: rgba(15, 23, 42, 0.45);
            font-size: 0.8125rem;
        }
        .fvd-return-bar a {
            color: var(--fvd-amarillo);
            font-weight: 600;
            text-decoration: none;
        }
        .fvd-return-bar a:hover { text-decoration: underline; }
        .fvd-main-wrap {
            flex: 1;
            max-width: var(--fvd-max);
            width: 100%;
            margin: 0 auto;
            padding: var(--fvd-page-pad);
        }
        @media (max-width: 900px) {
            .fvd-mnav-toggle { display: flex; }
            .fvd-sidebar {
                position: fixed;
                left: 0;
                top: 0;
                bottom: 0;
                transform: translateX(-100%);
                transition: transform 0.2s ease;
            }
            .fvd-shell--nav-open .fvd-sidebar { transform: translateX(0); }
            .fvd-topbar__inner { padding-left: 3.35rem; }
            .fvd-main-wrap { padding-top: 0.65rem; }
        }
        .fvd-card {
            background: var(--fvd-azul-card);
            border: 1px solid var(--fvd-border);
            border-radius: 10px;
            border-top: 3px solid var(--fvd-amarillo);
            padding: var(--fvd-card-pad);
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.12);
            color: var(--fvd-text);
        }
        h1 { font-size: var(--fvd-font-h1); margin: 0 0 0.5em; color: var(--fvd-text); }
        h2 { font-size: var(--fvd-font-h2); margin: 1em 0 0.4em; color: var(--fvd-text); }
        h3 { font-size: var(--fvd-font-h3); margin: 0.9em 0 0.35em; color: var(--fvd-text); }
        .fvd-actions a {
            color: var(--fvd-amarillo);
            text-decoration: none;
            font-size: var(--fvd-font-body);
        }
        .fvd-actions a:hover { text-decoration: underline; color: #fff; }
        .fvd-actions a.fvd-logout {
            color: var(--fvd-text);
            padding: 4px 10px;
            border-radius: 6px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-bottom: 2px solid var(--fvd-rojo);
            text-decoration: none;
        }
        .fvd-actions a.fvd-logout:hover {
            color: #fff;
            background: rgba(190, 18, 60, 0.15);
            text-decoration: none;
        }
        table.fvd-table { width: 100%; border-collapse: collapse; font-size: var(--fvd-font-body); }
        table.fvd-table th, table.fvd-table td {
            padding: var(--fvd-table-y) var(--fvd-table-x);
            border-bottom: 1px solid var(--fvd-border);
            text-align: left;
            color: var(--fvd-text);
        }
        table.fvd-table th { background: rgba(255, 255, 255, 0.08); font-weight: 600; color: var(--fvd-text); }
        input.fvd-input, textarea.fvd-input {
            font-size: var(--fvd-font-body);
            padding: var(--fvd-input-y) var(--fvd-input-x);
            border: 1px solid var(--fvd-border);
            border-radius: 6px;
            width: 100%;
            max-width: 28rem;
            background: rgba(0, 0, 0, 0.2);
            color: var(--fvd-text);
        }
        select.fvd-input {
            font-size: var(--fvd-font-body);
            padding: var(--fvd-input-y) var(--fvd-input-x);
            border: 1px solid rgba(15, 23, 42, 0.28);
            border-radius: 6px;
            width: 100%;
            max-width: 28rem;
            background: #ffffff;
            color: #111827;
            color-scheme: light;
        }
        select.fvd-input option,
        select.fvd-input optgroup {
            background: #ffffff;
            color: #111827;
        }
        select.fvd-input:disabled {
            background: #f1f5f9;
            color: #475569;
            opacity: 1;
        }
        input.fvd-input::placeholder, textarea.fvd-input::placeholder { color: var(--fvd-muted); opacity: 0.85; }
    </style>
</head>
<body>
<div class="fvd-shell fvd-shell--sidebar-rail" id="fvd-shell">
    <button type="button" class="fvd-mnav-toggle" id="fvd-mnav-toggle" aria-controls="fvd-sidebar" aria-expanded="false" aria-label="Abrir menú">☰</button>
    <div class="fvd-sidebar-backdrop" id="fvd-sidebar-backdrop" aria-hidden="true"></div>
    <aside class="fvd-sidebar" id="fvd-sidebar" aria-label="Menú principal">
        <div class="fvd-sidebar-head">
            <button type="button" class="fvd-sidebar-rail-toggle" id="fvd-sidebar-wide-toggle" title="Ampliar o contraer menú lateral">Menú ↔</button>
            <div class="fvd-sidebar-fvd-identity">
                <a href="<?= htmlspecialchars($fvdPanelUrl, ENT_QUOTES, 'UTF-8') ?>" title="Panel — Federación Venezolana de Dominó">
                    <img class="fvd-sidebar-fvd-logo" src="<?= htmlspecialchars($fvd_brand_logo_url, ENT_QUOTES, 'UTF-8') ?>" width="160" height="52" alt="Federación Venezolana de Dominó" decoding="async">
                </a>
                <a class="fvd-sidebar-brand" href="<?= htmlspecialchars($fvdPanelUrl, ENT_QUOTES, 'UTF-8') ?>" title="FVD Master Admin">FVD Master Admin</a>
                <p class="fvd-sidebar-fvd-tagline">Federación Venezolana de Dominó</p>
            </div>
            <a class="fvd-sn" href="<?= htmlspecialchars($fvd_public_landing_url, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer" title="Sitio público (inicio)">Sitio público (inicio)</a>
        </div>
        <nav class="fvd-sidebar-nav">
            <a class="fvd-sn<?= $fvd_sn_active('panel') ?>" href="<?= htmlspecialchars($fvdPanelUrl, ENT_QUOTES, 'UTF-8') ?>" title="Panel / Inicio">Panel / Inicio</a>
            <?php if ($fvd_es_admin_fvd && $fvd_revision_total > 0 && function_exists('admin_module_url') && function_exists('fvd_module_url')): ?>
                <?php if ($fvd_revision_altas > 0): ?>
                    <a class="fvd-sn fvd-sn--pend" href="<?= htmlspecialchars(fvd_module_url('atletas/index.php?action=list'), ENT_QUOTES, 'UTF-8') ?>" title="Listado de atletas (incluye altas desde delegados pendientes de validar)">Revisar altas delegado<span class="fvd-pend-badge" aria-label="Cantidad"><?= (int) $fvd_revision_altas ?></span></a>
                <?php endif; ?>
                <?php if ($fvd_revision_sol > 0): ?>
                    <a class="fvd-sn fvd-sn--pend" href="<?= htmlspecialchars(admin_module_url('solicitudes_delegado/index.php'), ENT_QUOTES, 'UTF-8') ?>" title="Solicitudes de carnet, traspaso o afiliación">Revisar solicitudes club<span class="fvd-pend-badge" aria-label="Cantidad"><?= (int) $fvd_revision_sol ?></span></a>
                <?php endif; ?>
            <?php endif; ?>
            <?php if ($fvd_can_nav_modules): ?>
                <a class="fvd-sn<?= $fvd_sn_active('dashboard_lite') ?>" href="<?= htmlspecialchars(url('dashboard/index.php'), ENT_QUOTES, 'UTF-8') ?>" title="Dashboard analítico">Dashboard analítico</a>
            <?php endif; ?>
            <?php if (AuthService::isAthletePortalUser()): ?>
                <a class="fvd-sn<?= $fvd_sn_active('mi_ficha') ?>" href="<?= htmlspecialchars($fvd_mi_ficha_url, ENT_QUOTES, 'UTF-8') ?>" title="Mi ficha (atleta)">Mi ficha (atleta)</a>
            <?php endif; ?>
            <?php if ($fvd_can_nav_modules && $fvd_es_admin_fvd): ?>
                <?php
                if (!function_exists('admin_module_url')) {
                    require_once $fvdProjRoot . '/config/paths.php';
                }
                ?>
                <details class="fvd-sn-acc"<?= $fvd_acc_adm_asoc_open ? ' open' : '' ?>>
                    <summary class="fvd-sn-acc__summary" title="Asociaciones">Asociaciones <span class="fvd-sn-acc__chev" aria-hidden="true"></span></summary>
                    <div class="fvd-sn-acc__body">
                        <a class="fvd-sn<?= $fvd_sn_active('asociaciones') ?>" href="<?= htmlspecialchars(fvd_module_url('asociaciones/index.php'), ENT_QUOTES, 'UTF-8') ?>" title="Listado, crear, editar y estatus">Listado y CRUD</a>
                    </div>
                </details>
                <details class="fvd-sn-acc"<?= $fvd_acc_adm_atletas_open ? ' open' : '' ?>>
                    <summary class="fvd-sn-acc__summary" title="Atletas">Atletas <span class="fvd-sn-acc__chev" aria-hidden="true"></span></summary>
                    <div class="fvd-sn-acc__body">
                        <a class="fvd-sn<?= $fvd_sn_active('atletas') ?>" href="<?= htmlspecialchars(fvd_module_url('atletas/index.php?action=list'), ENT_QUOTES, 'UTF-8') ?>" title="Listado general">Listado general</a>
                        <a class="fvd-sn<?= $fvd_sn_active('atletas_nuevo') ?>" href="<?= htmlspecialchars(fvd_module_url('atletas/index.php'), ENT_QUOTES, 'UTF-8') ?>" title="Formulario de alta (entrada principal del módulo)">Registro / nuevo atleta</a>
                    </div>
                </details>
                <details class="fvd-sn-acc"<?= $fvd_acc_adm_torneos_open ? ' open' : '' ?>>
                    <summary class="fvd-sn-acc__summary" title="Torneos">Torneos <span class="fvd-sn-acc__chev" aria-hidden="true"></span></summary>
                    <div class="fvd-sn-acc__body">
                        <a class="fvd-sn<?= $fvd_sn_active('torneos') ?>" href="<?= htmlspecialchars(fvd_module_url('torneos/index.php'), ENT_QUOTES, 'UTF-8') ?>" title="Listado, ver, editar, crear">Listado y gestión</a>
                    </div>
                </details>
                <details class="fvd-sn-acc"<?= $fvd_acc_adm_sol_open ? ' open' : '' ?>>
                    <summary class="fvd-sn-acc__summary" title="Traspasos y carnets">Traspasos / carnets <span class="fvd-sn-acc__chev" aria-hidden="true"></span></summary>
                    <div class="fvd-sn-acc__body">
                        <a class="fvd-sn<?= $fvd_sn_active('sol_traspasos_fvd') ?>" href="<?= htmlspecialchars(admin_module_url('solicitudes_delegado/index.php?tipo=traspaso'), ENT_QUOTES, 'UTF-8') ?>" title="Solicitudes de traspaso">Solicitudes de traspaso</a>
                        <a class="fvd-sn<?= $fvd_sn_active('sol_carnets_fvd') ?>" href="<?= htmlspecialchars(admin_module_url('solicitudes_delegado/index.php?tipo=carnet_afiliacion'), ENT_QUOTES, 'UTF-8') ?>" title="Carnets y afiliaciones">Solicitudes de carnet y afiliación</a>
                        <a class="fvd-sn<?= $fvd_sn_active('solicitudes_delegado') ?>" href="<?= htmlspecialchars(admin_module_url('solicitudes_delegado/index.php'), ENT_QUOTES, 'UTF-8') ?>" title="Todas las solicitudes">Todas las solicitudes</a>
                    </div>
                </details>
                <details class="fvd-sn-acc"<?= $fvd_acc_adm_fin_open ? ' open' : '' ?>>
                    <summary class="fvd-sn-acc__summary" title="Finanzas">Finanzas <span class="fvd-sn-acc__chev" aria-hidden="true"></span></summary>
                    <div class="fvd-sn-acc__body">
                        <a class="fvd-sn<?= $fvd_sn_active('costos') ?>" href="<?= htmlspecialchars(fvd_module_url('costos/index.php'), ENT_QUOTES, 'UTF-8') ?>" title="CRUD de tarifas">Tarifas (costos)</a>
                        <a class="fvd-sn<?= $fvd_sn_active('deudas') ?>" href="<?= htmlspecialchars(fvd_module_url('deuda_asociacion/index.php'), ENT_QUOTES, 'UTF-8') ?>" title="Estados de cuenta por torneo y asociación">Estados de cuenta (deudas)</a>
                        <a class="fvd-sn" href="<?= htmlspecialchars(fvd_module_url('deuda_asociacion/index.php?action=estadisticas_inscripcion'), ENT_QUOTES, 'UTF-8') ?>" title="Conteos por asociación desde atletas (torneo_id y banderas)">Estadísticas inscripciones (atletas)</a>
                        <a class="fvd-sn<?= $fvd_sn_active('pagos') ?>" href="<?= htmlspecialchars(fvd_module_url('relacion_pago/index.php'), ENT_QUOTES, 'UTF-8') ?>" title="Pagos registrados">Pagos realizados</a>
                    </div>
                </details>
                <details class="fvd-sn-acc"<?= $fvd_acc_adm_inf_open ? ' open' : '' ?>>
                    <summary class="fvd-sn-acc__summary" title="Informes y exportaciones">Informes <span class="fvd-sn-acc__chev" aria-hidden="true"></span></summary>
                    <div class="fvd-sn-acc__body">
                        <a class="fvd-sn<?= $fvd_sn_active('informe_carnets') ?>" href="<?= htmlspecialchars(fvd_module_url('atletas/reporte_carnets.php'), ENT_QUOTES, 'UTF-8') ?>" title="Solo atletas.carnet = 1">Solicitud de carnets (marcador = 1)</a>
                        <a class="fvd-sn<?= $fvd_sn_active('informe_traspasos') ?>" href="<?= htmlspecialchars(fvd_module_url('atletas/reporte_traspasos.php'), ENT_QUOTES, 'UTF-8') ?>" title="Informe de traspasos">Informe traspasos</a>
                        <a class="fvd-sn<?= $fvd_sn_active('informe_indicadores_atletas') ?>" href="<?= htmlspecialchars(fvd_module_url('atletas/reporte_indicadores.php'), ENT_QUOTES, 'UTF-8') ?>" title="Todos los campos de atletas con indicadores de servicio">Indicadores servicio (ficha completa)</a>
                        <a class="fvd-sn<?= $fvd_sn_active('informe_deudas_resumen') ?>" href="<?= htmlspecialchars(fvd_module_url('deuda_asociacion/index.php?fvd_from=informes'), ENT_QUOTES, 'UTF-8') ?>" title="Montos por concepto (inscripciones, afiliaciones, carnets, traspasos…)">Resumen finanzas / deudas por torneo</a>
                        <a class="fvd-sn<?= $fvd_sn_active('informe_export_atletas') ?>" href="<?= htmlspecialchars(fvd_module_url('atletas/export.php'), ENT_QUOTES, 'UTF-8') ?>" title="Exportar datos de atletas">Exportar atletas (afiliaciones / datos)</a>
                    </div>
                </details>
            <?php elseif ($fvd_can_nav_modules): ?>
                <details class="fvd-sn-acc"<?= $fvd_acc_datos_open ? ' open' : '' ?>>
                    <summary class="fvd-sn-acc__summary" title="Datos maestros">Datos <span class="fvd-sn-acc__chev" aria-hidden="true"></span></summary>
                    <div class="fvd-sn-acc__body">
                        <a class="fvd-sn<?= $fvd_sn_active('atletas') ?>" href="<?= htmlspecialchars(fvd_module_url('atletas/index.php?action=list'), ENT_QUOTES, 'UTF-8') ?>" title="Atletas">Atletas</a>
                    </div>
                </details>
                <?php if (AuthService::role() === AuthService::ROLE_ASO_ADMIN): ?>
                <details class="fvd-sn-acc"<?= $fvd_acc_asoc_torneos_open ? ' open' : '' ?>>
                    <summary class="fvd-sn-acc__summary" title="Torneos">Torneos <span class="fvd-sn-acc__chev" aria-hidden="true"></span></summary>
                    <div class="fvd-sn-acc__body">
                        <a class="fvd-sn<?= $fvd_sn_active('torneos') ?>" href="<?= htmlspecialchars(fvd_module_url('torneos/index.php'), ENT_QUOTES, 'UTF-8') ?>" title="Torneos organizados por su asociación">Listado y gestión</a>
                    </div>
                </details>
                <?php endif; ?>
                <details class="fvd-sn-acc"<?= $fvd_acc_fin_open ? ' open' : '' ?>>
                    <summary class="fvd-sn-acc__summary" title="Inscripciones al torneo y pagos">Inscripc. / pagos <span class="fvd-sn-acc__chev" aria-hidden="true"></span></summary>
                    <div class="fvd-sn-acc__body">
                        <a class="fvd-sn<?= $fvd_sn_active('torneo_inscripcion') ?>" href="<?= htmlspecialchars(fvd_module_url('torneo_inscripcion/index.php' . $fvd_deleg_torneo_q), ENT_QUOTES, 'UTF-8') ?>" title="Inscribir afiliados al torneo en curso">Inscripciones al torneo</a>
                        <a class="fvd-sn<?= $fvd_sn_active('inscripcion_torneo') ?>" href="<?= htmlspecialchars(fvd_module_url('inscripcion_torneo/index.php' . $fvd_deleg_torneo_q), ENT_QUOTES, 'UTF-8') ?>" title="Tabla inscripción por torneo y banderas">Administrador de inscripciones</a>
                        <a class="fvd-sn<?= $fvd_sn_active('inscripciones') ?>" href="<?= htmlspecialchars(fvd_module_url('inscripciones/index.php' . $fvd_deleg_torneo_q), ENT_QUOTES, 'UTF-8') ?>" title="Reportes PDF/HTML y finanzas por torneo">Reportes de inscripciones</a>
                        <a class="fvd-sn<?= $fvd_sn_active('pagos') ?>" href="<?= htmlspecialchars(fvd_module_url('relacion_pago/index.php'), ENT_QUOTES, 'UTF-8') ?>" title="Pagos registrados">Pagos</a>
                    </div>
                </details>
            <?php elseif (AuthService::isAthletePortalUser()): ?>
                <p class="fvd-sidebar-hint">Aquí ve su ficha deportiva registrada. Puede cambiar su contraseña con <strong>Mi perfil</strong> (arriba a la derecha).</p>
            <?php else: ?>
                <p class="fvd-sidebar-hint">Su rol no incluye los módulos de gestión. Use el panel y <strong>Mi perfil</strong> (arriba a la derecha).</p>
            <?php endif; ?>
        </nav>
        <div class="fvd-sidebar-user">
            <?php if (is_array($fvd_user)): ?>
                <strong><?= htmlspecialchars((string) ($fvd_user['nombre'] ?? $fvd_user['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong>
                <?= htmlspecialchars((string) ($fvd_user['rol'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
            <?php endif; ?>
            <a class="fvd-logout" href="<?= htmlspecialchars(AuthService::logoutUrl(), ENT_QUOTES, 'UTF-8') ?>">Cerrar sesión</a>
        </div>
    </aside>
    <div class="fvd-main-column">
    <header class="fvd-topbar">
        <div class="fvd-topbar__inner">
            <div class="fvd-topbar__brand">
                <a class="fvd-topbar__fvd-lockup" href="<?= htmlspecialchars($fvdPanelUrl, ENT_QUOTES, 'UTF-8') ?>" title="Ir al panel — FVD">
                    <img class="fvd-topbar__fvd-logo" src="<?= htmlspecialchars($fvd_brand_logo_url, ENT_QUOTES, 'UTF-8') ?>" width="104" height="40" alt="" decoding="async">
                    <span class="fvd-topbar__page-title"><?= htmlspecialchars($fvd_page_title, ENT_QUOTES, 'UTF-8') ?></span>
                </a>
                <?php if ($fvd_topbar_asoc_logo_url !== null && $fvd_topbar_asoc_logo_url !== ''): ?>
                    <img class="fvd-topbar__asoc-badge" src="<?= htmlspecialchars($fvd_topbar_asoc_logo_url, ENT_QUOTES, 'UTF-8') ?>" alt="" width="88" height="36" decoding="async">
                <?php endif; ?>
            </div>
            <div class="fvd-topbar__center">
                <?php if ($fvd_topbar_asoc_nombre !== ''): ?>
                    <span class="fvd-topbar__asoc-name"><?= htmlspecialchars($fvd_topbar_asoc_nombre, ENT_QUOTES, 'UTF-8') ?></span>
                <?php endif; ?>
            </div>
            <div class="fvd-topbar__actions">
                <?php if ($fvd_topbar_deleg_notif_no_vistas > 0): ?>
                <a class="fvd-topbar__notif-inv" href="<?= htmlspecialchars($fvdPanelUrl . '#fvd-deleg-torneos-invites', ENT_QUOTES, 'UTF-8') ?>" title="Invitaciones a torneos sin abrir">
                    Invitaciones
                    <span class="fvd-topbar__notif-badge"><?= (int) $fvd_topbar_deleg_notif_no_vistas ?></span>
                </a>
                <?php endif; ?>
                <a class="fvd-topbar__perfil<?= $fvd_sidebar_active === 'perfil' ? ' fvd-topbar__perfil--active' : '' ?>" href="<?= htmlspecialchars($fvd_perfil_url, ENT_QUOTES, 'UTF-8') ?>">Mi perfil</a>
            </div>
        </div>
    </header>
    <div class="fvd-main-wrap">
        <main class="fvd-main">
        <?php if ($fvd_return_nav_url !== null && $fvd_return_nav_url !== ''): ?>
            <nav class="fvd-return-bar no-print" aria-label="Volver al origen">
                <a href="<?= htmlspecialchars($fvd_return_nav_url, ENT_QUOTES, 'UTF-8') ?>">← Volver al origen</a>
            </nav>
        <?php endif; ?>
