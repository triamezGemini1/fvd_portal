<?php

declare(strict_types=1);

require_once __DIR__ . '/admin/_init.php';
fvd_admin_require_roles();

require_once __DIR__ . '/fvdmasteradmin/includes/vite_assets.php';
require_once __DIR__ . '/config/fvd_navigation_return.php';

if (!headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
}

$apiUrl = url('api/atleta_controller.php');
$referencialUrl = url('api/atleta_referencial.php');

$embeddedMaster = function_exists('fvd_master_embed_active') && fvd_master_embed_active();

/** En iframe del panel maestro: volver al mismo `master_panel.php` con ctx y workspace. */
$returnUrl = admin_module_url('atletas/');
if ($embeddedMaster && function_exists('fvd_master_panel_workspace_params')) {
    $wp = fvd_master_panel_workspace_params();
    $q = [];
    if ($wp['ctx_torneo'] > 0) {
        $q['ctx_torneo'] = (string) $wp['ctx_torneo'];
    }
    if (($wp['fvd_ws'] ?? '') !== '') {
        $q['fvd_ws'] = $wp['fvd_ws'];
    }
    $returnUrl = url('fvdmasteradmin/master_panel.php');
    if ($q !== []) {
        $returnUrl .= '?' . http_build_query($q);
    }
}

/** Misma carpeta que el listado PHP: enlaces a informes HTML (reporte_*.php) desde la SPA. */
$reportsEnabled = !AuthService::isDelegadoAsociacion();
$reportsBase = $reportsEnabled ? admin_module_url('atletas/') : '';
?>
<!DOCTYPE html>
<html lang="es" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate">
    <title>Gestión de atletas · FVD</title>
    <?= fvd_vite_tags('resources/js/gestion-atletas.js') ?>
</head>
<body class="m-0 min-h-screen bg-slate-100 text-slate-900 antialiased">
<script>
window.FVD_GESTION_ATLETAS = <?= json_encode([
    'apiUrl'           => $apiUrl,
    'referencialUrl'   => $referencialUrl,
    'embeddedMaster'   => $embeddedMaster,
    'reportsEnabled'   => $reportsEnabled,
    'reportsBase'      => $reportsBase,
    'returnUrl'        => $returnUrl,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) ?>;
</script>
<div id="fvd-gestion-atletas-app" class="relative isolate min-h-screen"></div>
</body>
</html>
