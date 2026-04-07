<?php

declare(strict_types=1);

/**
 * Landing pública de invitación (solo token en la URL, sin id incremental).
 */

require_once __DIR__ . '/fvdmasteradmin/config/db.php';
require_once __DIR__ . '/config/paths.php';
require_once __DIR__ . '/src/Services/InvitacionService.php';

use FvdPortal\Services\InvitacionService;

header('Content-Type: text/html; charset=UTF-8');

$token = isset($_GET['token']) ? (string) $_GET['token'] : '';
$error = '';
$row = null;

try {
    $row = InvitacionService::validarToken(fvd_db(), $token);
} catch (Throwable $e) {
    $error = $e->getMessage();
}

$loginUrl = rtrim((string) env('APP_BASE_PATH', ''), '/') . '/fvdmasteradmin/login.php';
$tipo = is_array($row) && (($row['tipo'] ?? '') === 'club') ? 'club' : 'atleta';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invitación FVD</title>
    <style>
        body { font-family: system-ui, Segoe UI, sans-serif; margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center;
            background: #0f172a; color: #f8fafc; font-size: 15px; }
        .box { max-width: 420px; padding: 1.25rem 1.35rem; border-radius: 10px; border: 1px solid rgba(255,255,255,.12); background: #1e293b; }
        h1 { font-size: 1.1rem; margin: 0 0 0.5rem; color: #fff200; }
        p { margin: 0.5rem 0; line-height: 1.45; color: #cbd5e1; font-size: 0.9rem; }
        .err { color: #fecaca; border-left: 3px solid #ef4444; padding-left: 0.5rem; }
        a { color: #fff200; }
        ul { margin: 0.5rem 0; padding-left: 1.1rem; color: #cbd5e1; font-size: 0.88rem; }
    </style>
</head>
<body>
<div class="box">
    <h1>Invitación FVD</h1>
    <?php if ($error !== ''): ?>
        <p class="err"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
        <p><a href="<?= htmlspecialchars(url('index.php'), ENT_QUOTES, 'UTF-8') ?>">← Sitio público</a></p>
    <?php else: ?>
        <p>Su invitación es válida para registro como <strong><?= $tipo === 'club' ? 'asociación / club' : 'atleta' ?></strong>.</p>
        <p>Documento de referencia: <strong><?= htmlspecialchars((string) ($row['documento'] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong></p>
        <ul>
            <li>Complete su ficha en el panel autorizado (administración FVD o delegado).</li>
            <li>Conserve este enlace hasta finalizar; si es de un solo uso, el sistema lo marcará al cerrar el proceso en una futura integración.</li>
        </ul>
        <p><a href="<?= htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8') ?>">Ir al inicio de sesión FVD Master Admin</a></p>
        <p><a href="<?= htmlspecialchars(url('index.php'), ENT_QUOTES, 'UTF-8') ?>">← Sitio público</a></p>
    <?php endif; ?>
</div>
</body>
</html>
