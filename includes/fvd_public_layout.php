<?php
declare(strict_types=1);

require_once __DIR__ . '/public_header.php';

/**
 * @param ''|'inicio'|'asociaciones'|'ranking'|'calendario'|'afiliacion' $active
 */
function fvd_public_header(string $title, string $active = ''): void
{
    $fvdUiCss = function_exists('url') ? url('assets/css/fvd-ui-mistorneos.css') : '/assets/css/fvd-ui-mistorneos.css';
    $home = url('index.php');
    $nav = [
        'inicio'        => ['label' => 'Inicio', 'href' => $home],
        'asociaciones'  => ['label' => 'Asociaciones', 'href' => url('asociaciones.php')],
        'ranking'       => ['label' => 'Rankings', 'href' => url('ranking_publico.php')],
        'calendario'    => ['label' => 'Calendario', 'href' => url('calendario.php')],
        'afiliacion'    => ['label' => 'Afiliación', 'href' => url('afiliacion.php')],
    ];
    $logo = public_layout_logo_url();
    header('Content-Type: text/html; charset=UTF-8');
    ?>
<!DOCTYPE html>
<html lang="es" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?> — FVD</title>
    <?php public_layout_print_brand_head(); ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= htmlspecialchars($fvdUiCss, ENT_QUOTES, 'UTF-8') ?>">
    <?php fvd_ui_print_css_variables(); ?>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        inst: {
                            blue: '#0A192F',
                            gold: '<?= htmlspecialchars(FVD_UI_COLOR_AMARILLO, ENT_QUOTES, 'UTF-8') ?>',
                        }
                    },
                    fontFamily: {
                        sans: ['Inter', 'system-ui', 'Segoe UI', 'sans-serif']
                    }
                }
            }
        }
    </script>
</head>
<body class="fvd-public-page flex min-h-screen flex-col">
<header class="fvd-p-header sticky top-0 z-50<?= $logo !== '' ? ' fvd-p-header--with-logo' : '' ?>">
    <div class="fvd-p-header__inner max-w-6xl px-4 py-3 sm:px-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <a href="<?= htmlspecialchars($home, ENT_QUOTES, 'UTF-8') ?>" class="fvd-p-header__brand flex min-w-0 items-center gap-3 sm:gap-4">
                <?php if ($logo !== ''): ?>
                    <img src="<?= htmlspecialchars($logo, ENT_QUOTES, 'UTF-8') ?>" alt="Federación Venezolana del Dominó" class="fvd-p-header__logo h-12 w-auto max-h-[3.35rem] object-contain object-left sm:h-14 sm:max-h-[3.75rem]" width="220" height="56" decoding="async">
                <?php else: ?>
                    <span class="fvd-p-header__mark flex h-10 w-10 shrink-0 items-center justify-center rounded-xl text-xs font-bold tracking-tight">FVD</span>
                <?php endif; ?>
                <span class="fvd-p-header__title hidden max-w-[10rem] truncate text-sm font-medium sm:block md:max-w-xs">Federación Venezolana del Dominó</span>
            </a>
            <div class="flex flex-wrap items-center justify-end gap-2">
                <?php
                require_once dirname(__DIR__) . '/fvdmasteradmin/services/AuthService.php';
                AuthService::ensureSession();
                if (AuthService::isAuthenticated()):
                ?>
                <a href="<?= htmlspecialchars(AuthService::perfilUrl(), ENT_QUOTES, 'UTF-8') ?>" class="rounded-lg border border-amber-300/50 bg-amber-500/15 px-3 py-1.5 text-xs font-semibold text-amber-100 hover:bg-amber-500/25 sm:text-sm">Mi perfil</a>
                <a href="<?= htmlspecialchars(AuthService::logoutUrl(), ENT_QUOTES, 'UTF-8') ?>" class="rounded-lg border border-white/35 px-3 py-1.5 text-xs font-semibold text-white hover:bg-white/10 sm:text-sm">Cerrar sesión</a>
                <?php endif; ?>
                <a href="<?= htmlspecialchars(url('fvdmasteradmin/login.php'), ENT_QUOTES, 'UTF-8') ?>" class="fvd-p-btn-dorado rounded-lg px-3 py-1.5 text-xs sm:text-sm">Master Admin</a>
                <a href="<?= htmlspecialchars(url('gestion_modulos.php'), ENT_QUOTES, 'UTF-8') ?>" class="fvd-p-btn-ghost rounded-lg px-3 py-1.5 text-xs font-medium sm:text-sm">Gestión interna</a>
            </div>
        </div>
        <nav class="fvd-p-header__nav-rule mt-3 flex flex-wrap gap-1 pt-3 text-sm sm:gap-2" aria-label="Principal">
            <?php foreach ($nav as $key => $item): ?>
                <?php
                $is = ($active === $key);
                $base = 'fvd-p-navlink rounded-lg px-3 py-2 font-medium transition-colors duration-200';
                $cls = $is ? $base . ' fvd-p-navlink--active' : $base;
                ?>
                <a class="<?= $cls ?>" href="<?= htmlspecialchars($item['href'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?></a>
            <?php endforeach; ?>
        </nav>
    </div>
</header>
<main class="fvd-p-main w-full max-w-6xl flex-1 px-4 py-10 sm:px-6">
    <?php
}

function fvd_public_footer(): void
{
    ?>
</main>
<footer class="fvd-p-footer mt-auto py-8">
    <div class="mx-auto max-w-6xl px-4 text-center text-sm sm:px-6">
        <p>© <?= (int) date('Y') ?> Federación Venezolana del Dominó. Sitio informativo.</p>
    </div>
</footer>
</body>
</html>
    <?php
}

function fvd_public_torneo_tipo_label($tipo): string
{
    $ti = (int) $tipo;
    $m = [1 => 'Torneo', 2 => 'Campeonato', 3 => 'Mixto (hist.)'];

    return $m[$ti] ?? '—';
}

function fvd_public_torneo_clase_label($clase): string
{
    $m = [1 => 'Individual', 2 => 'Parejas', 3 => 'Equipos'];

    return $m[(int) $clase] ?? '—';
}
