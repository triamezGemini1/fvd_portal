<?php

declare(strict_types=1);

/**
 * Marca institucional FVD: misma URL de logo que la landing pública
 * ({@see public_layout_logo_url} en includes/public_header.php), luego logo en uploads (BD), luego SVG mínimo.
 */
function fvd_brand_logo_public_url(): string
{
    if (!function_exists('url')) {
        require_once dirname(__DIR__, 2) . '/config/paths.php';
    }

    $svgFallback = url('assets/img/fvd-logo.svg');

    // Igual que public_layout_logo_url(): fvd_full.png → logonvofvd.png
    if (defined('BASE_PATH') && function_exists('asset')) {
        $candidates = [
            'assets/img/fvd_full.png',
            'assets/img/logonvofvd.png',
        ];
        foreach ($candidates as $rel) {
            if (is_file(BASE_PATH . '/' . $rel)) {
                return asset('img/' . basename($rel));
            }
        }
    }

    $helper = dirname(__DIR__, 2) . '/config/logo_helper.php';
    if (is_file($helper)) {
        require_once $helper;
        if (function_exists('get_fvd_logo')) {
            $fn = get_fvd_logo();
            if ($fn !== null && $fn !== '') {
                $safe = basename(str_replace(['\\', "\0"], '', (string) $fn));
                if ($safe !== '' && !str_contains($safe, '..')) {
                    return url('uploads/' . $safe);
                }
            }
        }
    }

    return $svgFallback;
}
