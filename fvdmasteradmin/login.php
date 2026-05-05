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

require_once dirname(__DIR__) . '/config/paths.php';
require_once __DIR__ . '/services/AuthService.php';
require_once __DIR__ . '/includes/fvd_brand.php';

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
                header('Location: ' . url('fvdmasteradmin/master_panel.php'), true, 302);
                break;
            case 'DELEGADO_ASO':
            case 'DELEGADO_ASOC':
                header('Location: ' . url('fvdmasteradmin/perfil_delegado.php'), true, 302);
                break;
            default:
                header('Location: ' . url('public/index.php'), true, 302);
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

/** Logo oficial FVD (PNG aportado); si no existe el archivo, se usa la marca del sitio. */
$fvdLoginLogoFile = dirname(__DIR__) . '/assets/img/fvd-login-oficial.png';
$fvdLoginLogoUrl = is_file($fvdLoginLogoFile)
    ? url('assets/img/fvd-login-oficial.png')
    : fvd_brand_logo_public_url();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#2e3092">
    <title>FVD Master Admin — acceso</title>
    <link rel="stylesheet" href="css/login_minimal.css">
</head>
<body>
<div class="fvd-login-page">
    <header class="fvd-login-brand" aria-label="Identidad FVD">
        <div class="fvd-login-logo-wrap">
            <div class="fvd-login-logo-frame">
                <img
                    class="fvd-login-logo"
                    src="<?php echo htmlspecialchars($fvdLoginLogoUrl, ENT_QUOTES, 'UTF-8'); ?>"
                    width="400"
                    height="140"
                    alt="Federación Venezolana de Dominó — FVD"
                    decoding="async"
                    fetchpriority="high"
                />
            </div>
        </div>
        <p class="fvd-login-affil fvd-login-affil--hero">
            Afiliada A: Comite Olimpico Venezolano, Ministerio del Poder Popular para el Deporte y
            Federación Internacional de Dominó.
        </p>
    </header>

    <div class="box" role="region" aria-label="Inicio de sesión">
        <div class="fvd-login-card__head">
            <h1 class="fvd-login-card__title">FVD Master Admin</h1>
            <p class="fvd-login-tagline">Federación Venezolana de Dominó</p>
        </div>
        <?php if ($error !== '') : ?>
            <div class="fvd-login-alert" role="alert"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>
        <form class="fvd-login-form" method="post" action="">
            <div class="fvd-field">
                <label class="fvd-label" for="email">Usuario</label>
                <input
                    class="fvd-input"
                    type="text"
                    id="email"
                    name="email"
                    required
                    autocomplete="username"
                    inputmode="text"
                >
            </div>
            <div class="fvd-field">
                <label class="fvd-label" for="password">Contraseña</label>
                <input
                    class="fvd-input"
                    type="password"
                    id="password"
                    name="password"
                    required
                    autocomplete="current-password"
                >
            </div>
            <button class="fvd-btn" type="submit">Entrar</button>
        </form>
        <p class="fvd-login-sitio">
            <a href="<?php echo htmlspecialchars(url('index.php'), ENT_QUOTES, 'UTF-8'); ?>">← Volver al sitio público</a>
        </p>
    </div>
</div>
</body>
</html>
