<?php
declare(strict_types=1);

/**
 * Layout público minimalista (sin sidebar, sin controles de administración).
 * Requiere haber cargado antes config/paths.php (p. ej. vía fvdmasteradmin/bootstrap.php).
 */

$fvdUiCfg = dirname(__DIR__) . '/fvdmasteradmin/config/ui_settings.php';
if (is_file($fvdUiCfg)) {
    require_once $fvdUiCfg;
}
if (!defined('FVD_UI_COLOR_AZUL')) {
    define('FVD_UI_COLOR_AZUL', '#2E3092');
    define('FVD_UI_COLOR_AMARILLO', '#FFF200');
    define('FVD_UI_COLOR_ROJO', '#BE123C');
    define('FVD_UI_COLOR_AZUL_CARD', '#3A3EB5');
    define('FVD_UI_COLOR_DORADO', '#FFF200');
}

/**
 * Variables CSS de marca (80/15/5). Sitio público: fondo azul institucional (#0A192F).
 * Incluir después de fvd-ui-mistorneos.css.
 */
function fvd_ui_print_css_variables(): void
{
    $bg = '#0A192F';
    $card = '#172a45';
    ?>
    <style id="fvd-ui-variables">
        :root {
            --fvd-azul: <?= $bg ?>;
            --fvd-amarillo: <?= htmlspecialchars(FVD_UI_COLOR_AMARILLO, ENT_QUOTES, 'UTF-8') ?>;
            --fvd-rojo: <?= htmlspecialchars(FVD_UI_COLOR_ROJO, ENT_QUOTES, 'UTF-8') ?>;
            --fvd-azul-card: <?= $card ?>;
            --fvd-dorado: var(--fvd-amarillo);
            --fvd-primary: var(--fvd-azul);
        }
    </style>
    <?php
}

/**
 * URL de logo de asociación (ruta relativa a /uploads/ o URL absoluta).
 */
function public_assoc_logo_url(?string $logo): string
{
    if ($logo === null || trim($logo) === '') {
        return '';
    }
    $lg = trim($logo);
    if (preg_match('#^https?://#i', $lg)) {
        return $lg;
    }

    return upload_url($lg);
}

/**
 * Logo principal del sitio público (identidad FVD exportada desde PDF).
 * Prioridad: fvd_full.png → logonvofvd.png (legacy).
 */
function public_layout_logo_url(): string
{
    if (!defined('BASE_PATH')) {
        return '';
    }
    $candidates = [
        'assets/img/fvd_full.png',
        'assets/img/logonvofvd.png',
    ];
    foreach ($candidates as $rel) {
        if (is_file(BASE_PATH . '/' . $rel)) {
            return asset('img/' . basename($rel));
        }
    }

    return '';
}

/**
 * Isotipo para favicon / PWA (fvd_icon.png); si no existe, usa el logo completo.
 */
function public_layout_icon_url(): string
{
    if (!defined('BASE_PATH')) {
        return '';
    }
    if (is_file(BASE_PATH . '/assets/img/fvd_icon.png')) {
        return asset('img/fvd_icon.png');
    }

    return public_layout_logo_url();
}

/**
 * Meta e iconos de marca (favicon, apple-touch-icon, theme-color).
 */
function public_layout_print_brand_head(): void
{
    $icon = public_layout_icon_url();
    if ($icon !== '') {
        ?>
    <link rel="icon" type="image/png" href="<?= htmlspecialchars($icon, ENT_QUOTES, 'UTF-8') ?>">
    <link rel="apple-touch-icon" href="<?= htmlspecialchars($icon, ENT_QUOTES, 'UTF-8') ?>">
        <?php
    }
    ?>
    <meta name="theme-color" content="#0A192F">
    <meta name="msapplication-TileColor" content="#0A192F">
    <?php
}

/**
 * @param 'inicio'|'asociaciones'|'ranking'|'calendario'|'afiliacion'|'acceso' $active
 */
function public_layout_head(string $title): void
{
    header('Content-Type: text/html; charset=UTF-8');
    $fvdUiCss = function_exists('url') ? url('assets/css/fvd-ui-mistorneos.css') : '/assets/css/fvd-ui-mistorneos.css';
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
                        sans: ['Inter', 'system-ui', 'Segoe UI', 'Roboto', 'sans-serif'],
                    }
                }
            }
        }
    </script>
</head>
<body class="fvd-public-page min-h-screen">
    <?php
}

/**
 * Menú horizontal limpio. Sin enlaces a paneles administrativos.
 *
 * @param 'inicio'|'asociaciones'|'ranking'|'calendario'|'afiliacion'|'acceso' $active
 */
function public_layout_header(string $active = 'inicio'): void
{
    $home = url('index.php');
    $items = [
        ['key' => 'inicio', 'label' => 'Inicio', 'href' => $home],
        ['key' => 'eventos', 'label' => 'Eventos', 'href' => $home . '#actividades'],
        ['key' => 'ranking', 'label' => 'Rankings', 'href' => url('ranking_publico.php')],
        ['key' => 'asociaciones', 'label' => 'Asociaciones', 'href' => $home . '#asociaciones'],
        ['key' => 'acceso', 'label' => 'Accesos', 'href' => $home . '#acceso'],
        ['key' => 'calendario', 'label' => 'Calendario', 'href' => url('calendario.php')],
        ['key' => 'afiliacion', 'label' => 'Afiliación', 'href' => url('afiliacion.php')],
    ];
    $logo = public_layout_logo_url();
    ?>
<header class="fvd-p-header sticky top-0 z-50<?= $logo !== '' ? ' fvd-p-header--with-logo' : '' ?>">
    <div class="fvd-p-header__inner flex max-w-6xl flex-wrap items-center justify-between gap-4 px-4 py-3 sm:px-6">
        <a href="<?= htmlspecialchars($home, ENT_QUOTES, 'UTF-8') ?>" class="fvd-p-header__brand flex min-w-0 items-center gap-3 sm:gap-4">
            <?php if ($logo !== ''): ?>
                <img src="<?= htmlspecialchars($logo, ENT_QUOTES, 'UTF-8') ?>" alt="Federación Venezolana del Dominó" class="fvd-p-header__logo h-12 w-auto max-h-[3.35rem] object-contain object-left sm:h-14 sm:max-h-[3.75rem]" width="220" height="56" decoding="async">
            <?php else: ?>
                <span class="fvd-p-header__mark flex h-10 w-10 shrink-0 items-center justify-center rounded-xl text-xs font-bold tracking-tight">FVD</span>
            <?php endif; ?>
            <span class="fvd-p-header__title hidden max-w-[12rem] truncate text-sm font-medium leading-tight sm:block lg:max-w-xs">Federación Venezolana del Dominó</span>
        </a>
        <nav class="flex flex-wrap items-center justify-end gap-1 sm:gap-2" aria-label="Principal">
            <?php foreach ($items as $item): ?>
                <?php
                $isActive = ($active === $item['key']);
                $base = 'fvd-p-navlink rounded-lg px-3 py-2 text-sm font-medium transition-colors duration-200';
                $cls = $isActive ? $base . ' fvd-p-navlink--active' : $base;
                ?>
                <a class="<?= $cls ?>" href="<?= htmlspecialchars($item['href'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?></a>
            <?php endforeach; ?>
        </nav>
    </div>
</header>
    <?php
}

function public_layout_main_open(): void
{
    ?>
<main id="contenido" class="fvd-p-main fvd-p-main--public max-w-6xl px-4 py-10 sm:px-6">
    <?php
}

function public_layout_footer(): void
{
    ?>
</main>
<footer class="fvd-p-footer py-8 text-center text-sm">
    <p>© <?= (int) date('Y') ?> Federación Venezolana del Dominó</p>
</footer>
</body>
</html>
    <?php
}
