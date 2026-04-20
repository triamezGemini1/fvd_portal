<?php

declare(strict_types=1);

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

/*
 * master_panel.php — INICIO ABSOLUTO
 * Vacía cualquier buffer de salida (p. ej. salida de auto_prepend_file) antes de renderizar solo la SPA.
 * Nota: no hay auto_prepend_file / auto_append_file en los .htaccess del repositorio (ver comentario al final).
 */

while (ob_get_level() > 0) {
    ob_end_clean();
}

require_once __DIR__ . '/services/AuthService.php';
AuthService::ensureSession();

// Tras session_start (ensureSession): sesión sin usuario válido → login con aviso (clave real: fvd_master_user, no user_id).
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
require_once $projRoot . '/src/Services/DelegadoTorneoNotifService.php';

use FvdPortal\Services\DelegadoTorneoNotifService;

$pdo = fvd_db();

/** Misma cadena que en seed SQL / master_panel_state (vista embebida delegado para pruebas). */
$fvdMasterTestDelegadoToken = 'TOKEN_PRUEBA_MIRANDA_2026';
$tokenMp = trim((string) ($_GET['token'] ?? ''));
if ($tokenMp === '') {
    $tokenMp = trim((string) ($_SESSION['fvd_master_delegado_notif_token'] ?? ''));
}
$isDelegadoPanel = ($tokenMp === $fvdMasterTestDelegadoToken);

if (AuthService::isAdminGral()) {
    if ($isDelegadoPanel) {
        $_SESSION['fvd_master_delegado_notif_token'] = $fvdMasterTestDelegadoToken;
        $tidAd = isset($_GET['ctx_torneo']) ? (int) $_GET['ctx_torneo'] : 0;
        if ($tidAd > 0) {
            AuthService::setDelegadoTorneoContext($tidAd);
        }
    } else {
        unset($_SESSION['fvd_master_delegado_notif_token'], $_GET['token']);
    }
} elseif ($tokenMp !== '') {
    if (!AuthService::isDelegadoAsociacion()) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=UTF-8');
        echo 'El enlace con token solo aplica a delegados de asociación.';
        exit;
    } else {
        $notifMp = DelegadoTorneoNotifService::notificacionPorAccessToken($pdo, $tokenMp);
        if (
            $notifMp === null
            || (int) ($notifMp['delegado_id'] ?? 0) !== (int) AuthService::userId()
        ) {
            http_response_code(403);
            header('Content-Type: text/plain; charset=UTF-8');
            echo 'Token inválido o no corresponde a su cuenta de delegado.';
            exit;
        }
        $_SESSION['fvd_master_delegado_notif_token'] = $tokenMp;
        $tidCtx = (int) ($notifMp['torneo_id'] ?? 0);
        if ($tidCtx > 0) {
            AuthService::setDelegadoTorneoContext($tidCtx);
        }
    }
} else {
    unset($_SESSION['fvd_master_delegado_notif_token']);
    AuthService::requireRoles([AuthService::ROLE_FVD_ADMIN]);
}

// Ancla de depuración: solo después de sesión, login y comprobación de roles (requireRoles/403 usan header()).
echo '<!-- fvd-master-panel:trace-php-after-auth -->' . "\n";

require_once __DIR__ . '/includes/master_panel_state.php';

$ctxTorneo = isset($_GET['ctx_torneo']) ? (int) $_GET['ctx_torneo'] : 0;
$initial_state = fvd_master_panel_build_initial_state($pdo, $ctxTorneo > 0 ? $ctxTorneo : null);

require_once __DIR__ . '/includes/vite_assets.php';

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
<html lang="es" class="h-full bg-slate-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FVD - Panel Maestro</title>
    <?= fvd_vite_tags() ?>
</head>
<body class="m-0 h-full overflow-hidden p-0">
    <div
        id="fvd-master-app"
        class="min-h-0"
        data-initial-state="<?= htmlspecialchars($initialJson, ENT_QUOTES, 'UTF-8') ?>"
    ></div>
</body>
</html>
<?php
exit;
