<?php

declare(strict_types=1);

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

/*
 * Panel maestro (SPA): vacía buffers de auto_prepend antes del HTML.
 */

while (ob_get_level() > 0) {
    ob_end_clean();
}

require_once __DIR__ . '/services/AuthService.php';
AuthService::ensureSession();

if (!AuthService::isAuthenticated()) {
    $login = AuthService::loginUrl();
    $sep = strpos($login, '?') !== false ? '&' : '?';
    header('Location: ' . $login . $sep . 'error=sesion_expirada');
    exit;
}

AuthService::requireLogin();

$projRoot = dirname(__DIR__);
if (!function_exists('url') || !function_exists('admin_module_url')) {
    require_once $projRoot . '/config/paths.php';
}

require_once __DIR__ . '/config/db.php';
require_once $projRoot . '/src/Services/MasterPanelEntryService.php';
require_once $projRoot . '/src/Services/MasterPanelContextService.php';

use FvdPortal\Services\MasterPanelEntryService;
use FvdPortal\Services\MasterPanelContextService;

$pdo = fvd_db();
MasterPanelEntryService::bootstrapSession($pdo);

$ctxTorneo = isset($_GET['ctx_torneo']) ? (int) $_GET['ctx_torneo'] : 0;
if ($ctxTorneo > 0) {
    $_SESSION[MasterPanelContextService::SESSION_MASTER_PANEL_CTX_TORNEO] = $ctxTorneo;
}
if (isset($_GET['fvd_ws']) && is_string($_GET['fvd_ws'])) {
    $wsTrim = trim($_GET['fvd_ws']);
    if ($wsTrim !== '' && preg_match('#^[a-zA-Z0-9_/\-]+$#', $wsTrim)) {
        $_SESSION['fvd_master_panel_fvd_ws'] = $wsTrim;
    }
}

require_once __DIR__ . '/includes/master_panel_state.php';
/** JSON inicial ligero: KPIs + rutas + contexto; feed (timeline, alertas) vía API tras montar Vue. */
$initial_state = fvd_master_panel_build_initial_state($pdo, $ctxTorneo > 0 ? $ctxTorneo : null, false);

require_once __DIR__ . '/includes/vite_assets.php';

$perfilQuick = url('fvdmasteradmin/perfil.php');
$logoutQuick = url('fvdmasteradmin/logout.php');

try {
    $initialJson = json_encode(
        is_array($initial_state) ? $initial_state : [],
        JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE
    );
} catch (JsonException $e) {
    $initialJson = '{}';
}
?>
<!DOCTYPE html>
<html lang="es" class="h-full bg-slate-50 fvd-master-html-reset">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FVD - Panel Maestro</title>
    <?= fvd_vite_tags() ?>
</head>
<body class="fvd-master-body-shell fvd-layout-13 m-0 overflow-hidden p-0">
    <?php
    echo '<script>window.__FVD_MASTER_INITIAL_STATE = ' . $initialJson . ';</script>' . "\n";
    ?>
    <div class="fvd-l13-app">
        <header class="fvd-l13-topbar" role="banner">
            <h1 class="fvd-l13-topbar__title">Federación Venezolana de Dominó · Panel</h1>
            <div class="fvd-l13-topbar__actions">
                <a href="<?= htmlspecialchars($perfilQuick, ENT_QUOTES, 'UTF-8') ?>">Perfil</a>
                <a href="<?= htmlspecialchars($logoutQuick, ENT_QUOTES, 'UTF-8') ?>">Salir</a>
            </div>
        </header>
        <div class="fvd-l13-body">
            <aside class="fvd-l13-sidebar" id="fvdL13Sidebar" aria-label="Navegación lateral">
                <button type="button" class="fvd-l13-sidebar__toggle" id="fvdL13SidebarToggle" title="Contraer o expandir menú" aria-expanded="true" aria-controls="fvdL13Sidebar">≡</button>
                <nav class="fvd-l13-sidebar__nav">
                    <p class="fvd-l13-sidebar__hint">Navegue desde la cuadrícula principal o use la búsqueda rápida (Ctrl+K) en el panel.</p>
                </nav>
            </aside>
            <div
                id="fvd-master-app"
                class="fvd-l13-canvas min-h-0"
                data-initial-state="<?= htmlspecialchars($initialJson, ENT_QUOTES, 'UTF-8') ?>"
            ></div>
        </div>
    </div>
    <script>
    (function () {
        var side = document.getElementById('fvdL13Sidebar');
        var btn = document.getElementById('fvdL13SidebarToggle');
        if (!side || !btn) return;
        btn.addEventListener('click', function () {
            var c = side.classList.toggle('fvd-l13-sidebar--collapsed');
            btn.setAttribute('aria-expanded', c ? 'false' : 'true');
        });
    })();
    </script>
</body>
</html>
<?php
exit;
