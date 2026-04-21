<?php

declare(strict_types=1);

/**
 * Login mínimo (compatibilidad / diagnóstico).
 * Solo AuthService + paths; sin StatsService ni src/Services salvo lo que arrastra AuthService internamente.
 * Restaurar UI completa cuando el acceso esté estable.
 */

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/services/AuthService.php';

$projRoot = dirname(__DIR__);
if (!function_exists('url')) {
    require_once $projRoot . '/config/paths.php';
}

AuthService::ensureSession();

/*
 * Acceso al formulario siempre: no redirigir si ya hay sesión (antes enviaba al panel).
 * En GET se cierra sesión para mostrar login limpio; en POST no, para no interferir con attemptLogin().
 */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    AuthService::logout();
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = (string) ($_POST['email'] ?? '');
    $pass = (string) ($_POST['password'] ?? '');
    if (AuthService::attemptLogin($email, $pass)) {
        if (AuthService::isAdministradorGeneral()) {
            AuthService::clearDelegadoPanelSessionKeys();
        }
        unset($_SESSION['fvd_redirect_after_login']);
        $user = AuthService::user() ?? [];
        $userRole = strtoupper((string) ($user['rol'] ?? ''));

        switch ($userRole) {
            case 'ADMIN_GRAL':
            case 'FVD_ADMIN':
                header('Location: /fvd_portal/fvdmasteradmin/master_panel.php', true, 302);
                break;
            case 'DELEGADO_ASO':
            case 'DELEGADO_ASOC':
                header('Location: ' . url('fvdmasteradmin/perfil_delegado.php'), true, 302);
                break;
            default:
                header('Location: /fvd_portal/public/index.php', true, 302);
                break;
        }
        exit;
    }
    $code = AuthService::getLastLoginFailure();
    if ($code === 'db_error') {
        $error = 'No se pudo acceder a la base de datos. Revise .env y la tabla fvd_usuarios.';
    } elseif ($code === 'user_inactive') {
        $error = 'Cuenta no habilitada.';
    } else {
        $error = 'Usuario o contraseña incorrectos.';
    }
}

header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FVD Master Admin — acceso</title>
    <link rel="stylesheet" href="css/login_minimal.css">
</head>
<body>
<div class="box">
    <h1>FVD Master Admin</h1>
    <p class="fvd-login-tagline">Federación Venezolana de Dominó</p>
    <?php if ($error !== '') : ?>
        <p class="err"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
    <?php endif; ?>
    <form method="post" action="">
        <label for="email">Usuario</label>
        <input type="text" id="email" name="email" required autocomplete="username">
        <label for="password">Contraseña</label>
        <input type="password" id="password" name="password" required autocomplete="current-password">
        <button type="submit">Entrar</button>
    </form>
    <p style="margin-top:1rem;font-size:12px;opacity:0.85;">
        <a href="/fvd_portal/index.php" style="color:#fff200;">← Sitio público</a>
    </p>
</div>
</body>
</html>
