<?php

declare(strict_types=1);

/**
 * Portal asociación (administrador FVD): elige asociación y abre el mismo panel que ven los delegados.
 */

require_once dirname(__DIR__) . '/services/AuthService.php';
AuthService::ensureSession();
AuthService::requireLogin();
AuthService::requireRoles([AuthService::ROLE_FVD_ADMIN]);

require_once dirname(__DIR__) . '/config/db.php';
$projRoot = dirname(__DIR__, 2);
if (!function_exists('url')) {
    require_once $projRoot . '/config/paths.php';
}
if (!function_exists('fvd_append_embed_to_url')) {
    require_once $projRoot . '/config/fvd_navigation_return.php';
}

try {
    $pdo = fvd_db();
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Error de conexión a la base de datos.';
    exit;
}

$asociaciones = [];
try {
    $st = $pdo->query('SELECT id, nombre FROM asociaciones ORDER BY nombre ASC');
    if ($st !== false) {
        while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
            $asociaciones[] = [
                'id' => (int) ($row['id'] ?? 0),
                'nombre' => trim((string) ($row['nombre'] ?? '')),
            ];
        }
    }
} catch (Throwable $e) {
    error_log('[portal_mirror] ' . $e->getMessage());
}

$err = '';
if (isset($_GET['clear']) && (string) $_GET['clear'] === '1') {
    AuthService::clearAdminPortalDelegadoAsociacionId();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aid = (int) ($_POST['asociacion_id'] ?? 0);
    if ($aid <= 0) {
        $err = 'Seleccione una asociación.';
    } else {
        $ok = false;
        try {
            $chk = $pdo->prepare('SELECT 1 FROM asociaciones WHERE id = :id LIMIT 1');
            $chk->execute([':id' => $aid]);
            $ok = (bool) $chk->fetchColumn();
        } catch (Throwable $e) {
            error_log('[portal_mirror validate] ' . $e->getMessage());
        }
        if (!$ok) {
            $err = 'Asociación no válida.';
        } else {
            AuthService::setAdminPortalDelegadoAsociacionId($aid);
            $dest = fvd_append_embed_to_url(url('fvdmasteradmin/delegado_dashboard_new.php'));
            header('Location: ' . $dest, true, 302);
            exit;
        }
    }
}

$currentAid = AuthService::adminPortalDelegadoAsociacionId();
header('Content-Type: text/html; charset=UTF-8');
$selfUrl = htmlspecialchars(url('fvdmasteradmin/operaciones/portal_mirror.php'), ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portal asociación — FVD</title>
    <style>
        body { margin: 0; font-family: system-ui, Segoe UI, Roboto, sans-serif; background: #f1f5f9; color: #0f172a; }
        .wrap { max-width: 32rem; margin: 2rem auto; padding: 0 1rem; }
        .card {
            background: #fff; border-radius: 12px; padding: 1.35rem 1.5rem;
            box-shadow: 0 8px 30px rgba(15, 23, 42, .08); border: 1px solid #e2e8f0;
        }
        h1 { font-size: 1.15rem; margin: 0 0 .35rem; color: #1e3a8a; }
        p.lead { margin: 0 0 1rem; font-size: .9rem; color: #475569; line-height: 1.45; }
        label { display: block; font-size: .78rem; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: #64748b; margin-bottom: .35rem; }
        select {
            width: 100%; padding: .55rem .65rem; font-size: 1rem; border-radius: 8px; border: 1px solid #cbd5e1;
            background: #fff;
        }
        .err { background: #fef2f2; color: #991b1b; padding: .65rem .75rem; border-radius: 8px; font-size: .875rem; margin-bottom: 1rem; border: 1px solid #fecaca; }
        .actions { margin-top: 1.1rem; display: flex; flex-wrap: wrap; gap: .5rem; align-items: center; }
        button[type="submit"] {
            background: linear-gradient(135deg, #1e3a8a, #2563eb); color: #fff; border: none; padding: .65rem 1.1rem;
            font-size: .95rem; font-weight: 700; border-radius: 8px; cursor: pointer;
        }
        button[type="submit"]:hover { filter: brightness(1.05); }
        a.muted { font-size: .85rem; color: #475569; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <h1>Portal asociación</h1>
        <p class="lead">Elija la asociación para abrir el <strong>panel de delegado</strong> con sus datos y accesos (misma vista que usan los delegados de asociación).</p>
        <?php if ($err !== ''): ?>
            <div class="err"><?= htmlspecialchars($err, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>
        <?php if ($asociaciones === []): ?>
            <p>No hay asociaciones registradas.</p>
        <?php else: ?>
            <form method="post" action="<?= $selfUrl ?>">
                <label for="asociacion_id">Asociación</label>
                <select id="asociacion_id" name="asociacion_id" required>
                    <option value="" disabled<?= $currentAid === null ? ' selected' : '' ?>>— Seleccione —</option>
                    <?php foreach ($asociaciones as $a): ?>
                        <?php if ($a['id'] <= 0) {
                            continue;
                        } ?>
                        <option value="<?= (int) $a['id'] ?>"<?= $currentAid === $a['id'] ? ' selected' : '' ?>>
                            <?= htmlspecialchars($a['nombre'] !== '' ? $a['nombre'] : ('#' . $a['id']), ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="actions">
                    <button type="submit">Abrir panel de delegado</button>
                    <?php if ($currentAid !== null): ?>
                        <a class="muted" href="<?= htmlspecialchars(fvd_append_embed_to_url(url('fvdmasteradmin/delegado_dashboard_new.php')), ENT_QUOTES, 'UTF-8') ?>">Volver al panel sin cambiar</a>
                    <?php endif; ?>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
