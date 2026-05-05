<?php

declare(strict_types=1);

/**
 * Herramienta de reset masivo de marcadores / estatus (panel maestro embebido o vista completa).
 */

require_once dirname(__DIR__) . '/services/AuthService.php';
AuthService::ensureSession();
AuthService::requireLogin();
AuthService::requireRoles([AuthService::ROLE_FVD_ADMIN]);

$fvdRoot = dirname(__DIR__);
$projRoot = dirname($fvdRoot);
if (!function_exists('url')) {
    require_once $projRoot . '/config/paths.php';
}

$embedded = isset($_GET['embedded']) && (string) $_GET['embedded'] === '1';

if (!$embedded) {
    $fvd_page_title = 'Reiniciar atletas / marcadores';
    require_once $fvdRoot . '/includes/layout_header.php';
} else {
    header('Content-Type: text/html; charset=UTF-8');
    $fvdUiCss = url('assets/css/fvd-ui-portal.css');
    ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reiniciar atletas · FVD</title>
    <link rel="stylesheet" href="<?= htmlspecialchars($fvdUiCss, ENT_QUOTES, 'UTF-8') ?>">
    <style>
        .atletas-reset-body { margin: 0; padding: 0.75rem; background: #f8fafc; font-family: Inter, system-ui, sans-serif; }
        .atletas-reset-card {
            max-width: 56rem;
            margin: 0 auto;
            padding: 1rem;
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 0.5rem;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
        }
        .atletas-reset-card h2 { margin: 0 0 1rem; font-size: 1.125rem; font-weight: 700; color: #1e293b; }
        .atletas-reset-card p { margin: 0 0 1.5rem; font-size: 0.875rem; color: #475569; line-height: 1.5; }
        .atletas-reset-grid { display: grid; grid-template-columns: 1fr; gap: 1rem; }
        @media (min-width: 768px) {
            .atletas-reset-grid { grid-template-columns: 1fr 1fr; }
        }
        .fvd-btn-danger, .fvd-btn-warning {
            display: block;
            width: 100%;
            padding: 1rem;
            border-radius: 0.375rem;
            border: 1px solid #cbd5e1;
            background: #fff;
            cursor: pointer;
            text-align: left;
            transition: background 0.15s ease;
        }
        .fvd-btn-danger { border-color: #fecaca; color: #991b1b; }
        .fvd-btn-danger:hover { background: #fef2f2; }
        .fvd-btn-warning { border-color: #fcd34d; color: #92400e; }
        .fvd-btn-warning:hover { background: #fffbeb; }
        .fvd-btn-danger .lbl { display: block; font-weight: 700; margin-bottom: 0.25rem; }
        .fvd-btn-danger .sub, .fvd-btn-warning .sub { font-size: 0.75rem; opacity: 0.75; }
    </style>
</head>
<body class="atletas-reset-body is-embedded">
<script>
(function () {
    if (window.self !== window.top || window.location.search.includes('embedded=1')) {
        document.documentElement.classList.add('is-embedded-view');
        document.body.classList.add('is-embedded');
    }
})();
</script>
    <?php
}

?>
<div class="<?= $embedded ? 'atletas-reset-card' : 'fvd-card' ?>">
    <h2 style="margin:0 0 1rem;font-size:1.125rem;font-weight:700;color:<?= $embedded ? '#1e293b' : '#f8fafc' ?>;">Control de marcadores y estatus</h2>
    <p style="margin:0 0 1.5rem;font-size:0.875rem;line-height:1.5;color:<?= $embedded ? '#475569' : '#cbd5e1' ?>;">
        Esta acción permite resetear masivamente los puntos, rankings o estatus de afiliación de los atletas para el inicio de un nuevo ciclo.
        <strong>La lógica de base de datos aún no está conectada</strong> (solo confirmación en el navegador).
    </p>

    <div class="<?= $embedded ? 'atletas-reset-grid' : '' ?>" style="<?= $embedded ? '' : 'display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:1rem;' ?>">
        <button type="button" class="fvd-btn-danger" onclick="confirmReset('puntos')">
            <span class="lbl">Poner puntos a cero</span>
            <span class="sub">Reinicia el acumulado de todos los atletas.</span>
        </button>
        <button type="button" class="fvd-btn-warning" onclick="confirmReset('afiliacion')">
            <span class="lbl">Caducar afiliaciones</span>
            <span class="sub">Pasa todos los atletas a estatus «pendiente por renovar».</span>
        </button>
    </div>
</div>

<script>
function confirmReset(tipo) {
    if (!confirm('¿Está seguro? Esta acción es irreversible y afectará a toda la base de datos nacional.')) {
        return;
    }
    console.log('Procesando reset de: ' + tipo);
}
</script>
<?php
if (!$embedded) {
    require_once $fvdRoot . '/includes/layout_footer.php';
} else {
    echo '</body></html>';
}
