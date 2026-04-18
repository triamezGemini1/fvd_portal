<?php

declare(strict_types=1);

/**
 * Deshabilitar un despliegue duplicado (p. ej. carpeta beta o copia en disco) para evitar confusión.
 * En el .env de esa copia: APP_DEPLOYMENT_DISABLED=true
 *
 * No afecta a PHP en CLI (cron, scripts).
 */

/**
 * @return bool True si este despliegue debe rechazar peticiones HTTP.
 */
function fvd_deployment_is_disabled(): bool
{
    if (PHP_SAPI === 'cli') {
        return false;
    }
    if (!function_exists('env')) {
        return false;
    }

    return Env::getBool('APP_DEPLOYMENT_DISABLED', false);
}

function fvd_deployment_disabled_exit(): void
{
    if (!headers_sent()) {
        http_response_code(503);
        header('Content-Type: text/html; charset=UTF-8');
        header('Retry-After: 86400');
        header('X-Robots-Tag: noindex, nofollow');
    }

    $canonical = function_exists('env') ? trim((string) env('APP_DEPLOYMENT_CANONICAL_URL', '')) : '';
    $title = 'Entorno deshabilitado';
    ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></title>
</head>
<body style="font-family:system-ui,Segoe UI,sans-serif;max-width:28rem;margin:3rem auto;padding:0 1.25rem;line-height:1.5;color:#1e293b;background:#f8fafc">
    <h1 style="font-size:1.25rem;margin:0 0 1rem">Este entorno está deshabilitado</h1>
    <p style="margin:0 0 1rem;font-size:0.95rem">Esta copia del proyecto no está en uso. Acceder aquí puede generar sesiones o datos inconsistentes con el entorno oficial.</p>
    <?php if ($canonical !== ''): ?>
    <p style="margin:0"><a href="<?= htmlspecialchars($canonical, ENT_QUOTES, 'UTF-8') ?>" style="color:#1d4ed8;font-weight:600">Ir al sitio correcto</a></p>
    <?php endif; ?>
</body>
</html>
    <?php
    exit;
}
