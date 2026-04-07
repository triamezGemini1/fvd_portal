<?php
/**
 * Solicitud explícita de primer acceso: correo + teléfono deben coincidir con tabla atletas.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/ui_settings.php';
require_once __DIR__ . '/../services/AtletaAccesoService.php';

$projRoot = dirname(__DIR__, 2);
if (!function_exists('url')) {
    require_once $projRoot . '/config/paths.php';
}
$publicLandingUrl = url('index.php');

$appBase = rtrim((string) (function_exists('env') ? env('APP_BASE_PATH', '') : ''), '/');
$loginUrl = $appBase . '/fvdmasteradmin/login.php';

$message = '';
$isError = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = (string) ($_POST['email'] ?? '');
    $tel = (string) ($_POST['telefono'] ?? '');
    try {
        $pdo = fvd_db();
        $out = AtletaAccesoService::procesarSolicitudAcceso($pdo, $email, $tel);
        $message = $out['message'];
        $isError = !$out['ok'];
    } catch (Throwable $e) {
        error_log('[atleta/solicitar_acceso] ' . $e->getMessage());
        $message = 'Ocurrió un error. Verifique que la base de datos tenga la columna atleta_id en fvd_usuarios (ver sql/alter_fvd_usuarios_atleta_id.sql) e intente de nuevo.';
        $isError = true;
    }
}

header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Solicitar acceso — Atletas FVD</title>
    <style>
        :root {
            --fvd-azul: <?= htmlspecialchars(FVD_UI_COLOR_AZUL, ENT_QUOTES, 'UTF-8') ?>;
            --fvd-amarillo: <?= htmlspecialchars(FVD_UI_COLOR_AMARILLO, ENT_QUOTES, 'UTF-8') ?>;
            --fvd-rojo: <?= htmlspecialchars(FVD_UI_COLOR_ROJO, ENT_QUOTES, 'UTF-8') ?>;
            --fvd-azul-card: <?= htmlspecialchars(FVD_UI_COLOR_AZUL_CARD, ENT_QUOTES, 'UTF-8') ?>;
        }
        body { font-family: 'Inter', system-ui, sans-serif; font-size: 14px; margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; background: var(--fvd-azul); color: #f8fafc; }
        .box { background: var(--fvd-azul-card); padding: 1.25rem 1.5rem; border-radius: 10px; border: 1px solid rgba(255,255,255,0.14); border-top: 3px solid var(--fvd-amarillo); width: 100%; max-width: 400px; box-shadow: 0 1px 3px rgba(0,0,0,0.15); }
        h1 { font-size: 1.15rem; margin: 0 0 0.5rem; color: #fff; }
        .sub { font-size: 13px; color: #cbd5e1; margin: 0 0 1rem; line-height: 1.45; }
        label { display: block; font-size: 13px; margin-bottom: 0.25rem; color: #cbd5e1; }
        input { width: 100%; padding: 6px 10px; font-size: 14px; border: 1px solid rgba(255,255,255,0.2); border-radius: 6px; margin-bottom: 0.75rem; background: rgba(0,0,0,0.2); color: #f8fafc; box-sizing: border-box; }
        button { width: 100%; padding: 8px; font-size: 14px; background: var(--fvd-amarillo); color: var(--fvd-azul); border: 1px solid rgba(46,48,146,0.35); border-radius: 6px; cursor: pointer; font-weight: 600; }
        button:hover { filter: brightness(0.97); }
        .msg { font-size: 13px; margin-bottom: 0.75rem; line-height: 1.45; padding: 0.5rem 0.65rem; border-radius: 6px; }
        .msg--ok { background: rgba(34, 197, 94, 0.15); border-left: 3px solid #22c55e; color: #ecfdf5; }
        .msg--err { color: #fecaca; border-left: 3px solid var(--fvd-rojo); padding-left: 0.5rem; }
        .foot { margin-top: 1rem; font-size: 13px; }
        .foot a { color: var(--fvd-amarillo); }
    </style>
</head>
<body>
<div class="box">
    <h1>Acceso al portal de atletas</h1>
    <p class="sub">Si es su primera vez, confirme su identidad con el <strong>correo</strong> y el <strong>teléfono celular</strong> registrados en su ficha. Luego podrá entrar con su correo como usuario.</p>
    <?php if ($message !== ''): ?>
        <div class="msg <?= $isError ? 'msg--err' : 'msg--ok' ?>"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>
    <form method="post" action="">
        <label for="email">Correo electrónico</label>
        <input type="email" id="email" name="email" required autocomplete="email" value="<?= htmlspecialchars((string) ($_POST['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
        <label for="telefono">Teléfono celular</label>
        <input type="tel" id="telefono" name="telefono" required autocomplete="tel" placeholder="Mismo número que en su ficha" value="<?= htmlspecialchars((string) ($_POST['telefono'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
        <button type="submit">Solicitar / habilitar acceso</button>
    </form>
    <p class="foot">¿Ya tiene acceso? <a href="<?= htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8') ?>">Ir al inicio de sesión</a></p>
    <p class="foot"><a href="<?= htmlspecialchars($publicLandingUrl, ENT_QUOTES, 'UTF-8') ?>">← Sitio público (inicio)</a></p>
</div>
</body>
</html>
