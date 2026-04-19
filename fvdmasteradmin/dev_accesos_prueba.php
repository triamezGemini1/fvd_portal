<?php

declare(strict_types=1);

/**
 * Página de referencia: enlaces y credenciales de prueba (solo desarrollo).
 * No exponer en producción: requiere APP_DEBUG=true en .env
 */

$projRoot = dirname(__DIR__);
require_once $projRoot . '/config/paths.php';

$debug = filter_var((string) env('APP_DEBUG', ''), FILTER_VALIDATE_BOOLEAN);
if (!$debug) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'No disponible.';
    exit;
}

require_once __DIR__ . '/config/test_accounts.php';

$rows = fvd_test_accounts_catalogue();
$pwd = FVD_TEST_PASSWORD;
header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accesos de prueba — FVD</title>
    <style>
        body { font-family: system-ui, sans-serif; margin: 1.5rem; max-width: 56rem; line-height: 1.45; }
        h1 { font-size: 1.25rem; }
        table { border-collapse: collapse; width: 100%; font-size: 0.9rem; }
        th, td { border: 1px solid #cbd5e1; padding: 0.5rem 0.6rem; text-align: left; vertical-align: top; }
        th { background: #f1f5f9; }
        code { background: #f1f5f9; padding: 0.1rem 0.35rem; border-radius: 4px; }
        .warn { background: #fff7ed; border: 1px solid #fdba74; padding: 0.75rem 1rem; border-radius: 6px; margin-bottom: 1rem; }
    </style>
</head>
<body>
    <h1>Accesos de prueba (solo APP_DEBUG)</h1>
    <p class="warn"><strong>Seguridad:</strong> desactive <code>APP_DEBUG</code> en producción. Ejecute
        <code>php fvdmasteradmin/cli/seed_all_pruebas.php</code> desde la raíz del proyecto para crear las cuentas.</p>
    <p>Contraseña común: <code><?= htmlspecialchars($pwd, ENT_QUOTES, 'UTF-8') ?></code></p>
    <table>
        <thead>
            <tr>
                <th>Perfil</th>
                <th>Usuario (campo login)</th>
                <th>Rol</th>
                <th>Tras iniciar sesión (típico)</th>
                <th>Notas</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <?php if (($r['key'] ?? '') === '_login') { continue; } ?>
            <tr>
                <td><?= htmlspecialchars($r['label'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><code><?= htmlspecialchars($r['login'], ENT_QUOTES, 'UTF-8') ?></code></td>
                <td><?= htmlspecialchars($r['rol'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><a href="<?= htmlspecialchars($r['destino_tras_login'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($r['destino_tras_login'], ENT_QUOTES, 'UTF-8') ?></a></td>
                <td><?= htmlspecialchars($r['notas'], ENT_QUOTES, 'UTF-8') ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <h2 style="margin-top:1.5rem;font-size:1rem;">Login</h2>
    <p><a href="<?= htmlspecialchars(url('fvdmasteradmin/login.php'), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(url('fvdmasteradmin/login.php'), ENT_QUOTES, 'UTF-8') ?></a></p>
    <h2 style="margin-top:1rem;font-size:1rem;">Panel maestro (admin FVD)</h2>
    <p><a href="<?= htmlspecialchars(url('fvdmasteradmin/master_panel.php'), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(url('fvdmasteradmin/master_panel.php'), ENT_QUOTES, 'UTF-8') ?></a></p>
</body>
</html>
