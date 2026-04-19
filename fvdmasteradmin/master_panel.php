<?php

declare(strict_types=1);

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
/*
 * Validación de administrador FVD (rol `fvd_admin` en sesión AuthService; no existe clave literal $_SESSION['fvd_admin']).
 */
AuthService::requireLogin();
AuthService::requireRoles([AuthService::ROLE_FVD_ADMIN]);

$projRoot = dirname(__DIR__);
if (!function_exists('url') || !function_exists('admin_module_url')) {
    require_once $projRoot . '/config/paths.php';
}

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/master_panel_state.php';

$pdo = fvd_db();
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
