<?php

declare(strict_types=1);

require_once __DIR__ . '/admin/_init.php';
fvd_admin_require_roles();

require_once __DIR__ . '/fvdmasteradmin/includes/vite_assets.php';

if (!headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
}

$apiUrl = url('api/gestion_asociaciones.php');
?>
<!DOCTYPE html>
<html lang="es" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de asociaciones · FVD</title>
    <?= fvd_vite_tags('resources/js/gestion-asociaciones.js') ?>
</head>
<body class="m-0 min-h-screen bg-slate-100 text-slate-900 antialiased">
<script>
window.FVD_GESTION_ASOC = <?= json_encode(['apiUrl' => $apiUrl], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) ?>;
</script>
<div id="fvd-gestion-asoc-app" class="min-h-screen"></div>
</body>
</html>
