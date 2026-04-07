<?php
/**
 * Login FVD Master Admin (tabla fvd_usuarios).
 */

declare(strict_types=1);

require_once __DIR__ . '/config/ui_settings.php';
require_once __DIR__ . '/services/AuthService.php';

$projRoot = dirname(__DIR__);
if (!function_exists('url')) {
    require_once $projRoot . '/config/paths.php';
}
require_once __DIR__ . '/includes/fvd_brand.php';
$fvd_brand_logo_url = fvd_brand_logo_public_url();
$fvd_public_landing_url = url('index.php');

AuthService::ensureSession();

if (AuthService::isAuthenticated()) {
    $redir = $_SESSION['fvd_redirect_after_login'] ?? '';
    unset($_SESSION['fvd_redirect_after_login']);
    if (is_string($redir) && $redir !== '' && isset($redir[0]) && $redir[0] === '/') {
        header('Location: ' . $redir);
        exit;
    }
    if (AuthService::isDelegadoAsociacion()) {
        require_once __DIR__ . '/config/db.php';
        require_once dirname(__DIR__) . '/src/Services/DelegadoTorneoNotifService.php';
        $pdo = fvd_db();
        $uid = (int) AuthService::userId();
        $ult = \FvdPortal\Services\DelegadoTorneoNotifService::ultimaNoVista($pdo, $uid);
        $cnt = \FvdPortal\Services\DelegadoTorneoNotifService::contarNoVistas($pdo, $uid);
        if ($ult !== null && $cnt === 1) {
            $base = rtrim((string) (function_exists('env') ? env('APP_BASE_PATH', '') : ''), '/');
            header('Location: ' . $base . '/fvdmasteradmin/delegado_entrar_torneo.php?notif_id=' . (int) $ult['id']);
            exit;
        }
    }
    header('Location: ' . AuthService::homeUrl());
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = (string) ($_POST['email'] ?? '');
    $pass = (string) ($_POST['password'] ?? '');
        if (AuthService::attemptLogin($email, $pass)) {
        $redir = $_SESSION['fvd_redirect_after_login'] ?? '';
        unset($_SESSION['fvd_redirect_after_login']);
        if (is_string($redir) && $redir !== '' && isset($redir[0]) && $redir[0] === '/') {
            header('Location: ' . $redir);
            exit;
        }
        if (AuthService::isAthletePortalUser()) {
            $base = rtrim((string) (function_exists('env') ? env('APP_BASE_PATH', '') : ''), '/');
            header('Location: ' . $base . '/fvdmasteradmin/atleta/mi_ficha.php');
            exit;
        }
        if (AuthService::isDelegadoAsociacion()) {
            require_once __DIR__ . '/config/db.php';
            require_once dirname(__DIR__) . '/src/Services/DelegadoTorneoNotifService.php';
            $pdo = fvd_db();
            $ult = \FvdPortal\Services\DelegadoTorneoNotifService::ultimaNoVista($pdo, (int) AuthService::userId());
            $cnt = \FvdPortal\Services\DelegadoTorneoNotifService::contarNoVistas($pdo, (int) AuthService::userId());
            if ($ult !== null && $cnt === 1) {
                $base = rtrim((string) (function_exists('env') ? env('APP_BASE_PATH', '') : ''), '/');
                header('Location: ' . $base . '/fvdmasteradmin/delegado_entrar_torneo.php?notif_id=' . (int) $ult['id']);
                exit;
            }
        }
        header('Location: ' . AuthService::homeUrl());
        exit;
    }
    $code = AuthService::getLastLoginFailure();
    if ($code === 'db_error') {
        $error = 'No se pudo acceder a la base de datos de usuarios. Revise DB_* / FVD_DB_* en .env y que exista la tabla fvd_usuarios.';
    } elseif ($code === 'user_inactive') {
        $error = 'Su cuenta aún no está habilitada. Si es atleta, solicite el acceso con el correo y el teléfono de su ficha (enlace abajo).';
    } else {
        $error = 'Usuario o contraseña incorrectos.';
    }
    $dbg = strtolower((string) (function_exists('env') ? env('APP_DEBUG', '') : ''));
    if (in_array($dbg, ['1', 'true', 'yes'], true) && $code !== '' && $code !== 'db_error') {
        $error .= ' [diag: ' . htmlspecialchars($code, ENT_QUOTES, 'UTF-8') . ']';
    }
}

header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso — FVD Master Admin · Federación Venezolana de Dominó</title>
    <style>
        :root {
            --fvd-azul: <?= htmlspecialchars(FVD_UI_COLOR_AZUL, ENT_QUOTES, 'UTF-8') ?>;
            --fvd-amarillo: <?= htmlspecialchars(FVD_UI_COLOR_AMARILLO, ENT_QUOTES, 'UTF-8') ?>;
            --fvd-rojo: <?= htmlspecialchars(FVD_UI_COLOR_ROJO, ENT_QUOTES, 'UTF-8') ?>;
            --fvd-azul-card: <?= htmlspecialchars(FVD_UI_COLOR_AZUL_CARD, ENT_QUOTES, 'UTF-8') ?>;
        }
        body { font-family: 'Inter', system-ui, sans-serif; font-size: 14px; margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; background: var(--fvd-azul); color: #f8fafc; }
        .box { background: var(--fvd-azul-card); padding: 1.25rem 1.5rem; border-radius: 10px; border: 1px solid rgba(255,255,255,0.14); border-top: 3px solid var(--fvd-amarillo); width: 100%; max-width: 360px; box-shadow: 0 1px 3px rgba(0,0,0,0.15); }
        h1 { font-size: 1.15rem; margin: 0 0 1rem; color: #fff; }
        label { display: block; font-size: 13px; margin-bottom: 0.25rem; color: #cbd5e1; }
        input { width: 100%; padding: 6px 10px; font-size: 14px; border: 1px solid rgba(255,255,255,0.2); border-radius: 6px; margin-bottom: 0.75rem; background: rgba(0,0,0,0.2); color: #f8fafc; }
        button { width: 100%; padding: 8px; font-size: 14px; background: var(--fvd-amarillo); color: var(--fvd-azul); border: 1px solid rgba(46,48,146,0.35); border-radius: 6px; cursor: pointer; font-weight: 600; }
        button:hover { filter: brightness(0.97); }
        .err { color: #fecaca; font-size: 13px; margin-bottom: 0.75rem; border-left: 3px solid var(--fvd-rojo); padding-left: 0.5rem; }
        .fvd-login-brand { text-align: center; margin-bottom: 1rem; }
        .fvd-login-brand img { width: 50%; max-width: 200px; height: auto; object-fit: contain; display: block; margin: 0 auto 0.5rem; }
        .fvd-login-brand .tagline { margin: 0; font-size: clamp(0.75rem, 2.5vw, 0.9rem); font-weight: 600; color: var(--fvd-amarillo); letter-spacing: 0.03em; line-height: 1.3; }
    </style>
</head>
<body>
<div class="box">
    <div class="fvd-login-brand">
        <img src="<?= htmlspecialchars($fvd_brand_logo_url, ENT_QUOTES, 'UTF-8') ?>" width="200" height="64" alt="Federación Venezolana de Dominó" decoding="async">
        <p class="tagline">Federación Venezolana de Dominó</p>
    </div>
    <h1>FVD Master Admin</h1>
    <?php if ($error !== ''): ?><div class="err"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
    <form method="post" action="">
        <label for="email">Usuario</label>
        <input type="text" id="email" name="email" required autocomplete="username" placeholder="ej. Trinoamez">
        <label for="password">Contraseña</label>
        <input type="password" id="password" name="password" required autocomplete="current-password">
        <button type="submit">Entrar</button>
    </form>
    <?php
    $solUrl = rtrim((string) (function_exists('env') ? env('APP_BASE_PATH', '') : ''), '/') . '/fvdmasteradmin/atleta/solicitar_acceso.php';
    ?>
    <p style="margin-top:1rem;font-size:13px;color:#cbd5e1;line-height:1.4;">¿Es atleta y es su primer acceso? <a href="<?= htmlspecialchars($solUrl, ENT_QUOTES, 'UTF-8') ?>" style="color:var(--fvd-amarillo);">Solicitar acceso con correo y teléfono</a></p>
    <p style="margin-top:0.65rem;font-size:13px;color:#cbd5e1;line-height:1.4;"><a href="<?= htmlspecialchars($fvd_public_landing_url, ENT_QUOTES, 'UTF-8') ?>" style="color:var(--fvd-amarillo);">← Volver al sitio público</a></p>
</div>
</body>
</html>
