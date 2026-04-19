<?php

declare(strict_types=1);

/**
 * Rutas CSS del manifest Vite para un entry, incluyendo las hojas declaradas en chunks importados.
 * En builds con code-splitting, Tailwind u otras hojas pueden vivir en un chunk compartido y no en el entry.
 *
 * @param array<string, mixed> $manifest
 * @return list<string>
 */
function fvd_vite_manifest_css_for_entry(array $manifest, string $entryKey): array
{
    $seenChunks = [];
    $cssPaths = [];

    $walk = function (string $chunkKey) use (&$manifest, &$seenChunks, &$cssPaths, &$walk): void {
        if (isset($seenChunks[$chunkKey])) {
            return;
        }
        $seenChunks[$chunkKey] = true;
        if (!isset($manifest[$chunkKey]) || !is_array($manifest[$chunkKey])) {
            return;
        }
        /** @var array<string, mixed> $chunk */
        $chunk = $manifest[$chunkKey];
        if (isset($chunk['imports']) && is_array($chunk['imports'])) {
            foreach ($chunk['imports'] as $imp) {
                if (is_string($imp) && $imp !== '') {
                    $walk($imp);
                }
            }
        }
        if (isset($chunk['css']) && is_array($chunk['css'])) {
            foreach ($chunk['css'] as $css) {
                if (is_string($css) && $css !== '') {
                    $cssPaths[] = $css;
                }
            }
        }
    };

    $walk($entryKey);

    $unique = [];
    $ordered = [];
    foreach ($cssPaths as $p) {
        if (!isset($unique[$p])) {
            $unique[$p] = true;
            $ordered[] = $p;
        }
    }

    return $ordered;
}

/**
 * Etiquetas script/link para el bundle Vite (servidor de desarrollo o manifest en producción).
 *
 * @param string $entry Ruta del entry respecto a la raíz del proyecto (p. ej. resources/js/app.js)
 */
function fvd_vite_tags(string $entry = 'resources/js/app.js'): string
{
    $projRoot = dirname(__DIR__, 2);
    if (!function_exists('url') || !function_exists('env')) {
        require_once $projRoot . '/config/paths.php';
    }

    if (fvd_vite_is_dev()) {
        $origin = fvd_vite_dev_origin();
        $e = htmlspecialchars($entry, ENT_QUOTES, 'UTF-8');

        return sprintf(
            '<script type="module" src="%s/@vite/client"></script>' . "\n"
            . '<script type="module" src="%s/%s"></script>',
            $origin,
            $origin,
            $e
        );
    }

    $manifestPath = $projRoot . '/public/build/.vite/manifest.json';
    if (!is_readable($manifestPath)) {
        return '<!-- Vite: ejecute npm run build en la raíz del proyecto -->';
    }
    /** @var mixed $decoded */
    $decoded = json_decode((string) file_get_contents($manifestPath), true);
    if (!is_array($decoded) || !isset($decoded[$entry]) || !is_array($decoded[$entry])) {
        return '<!-- Vite: entrada no encontrada en manifest: ' . htmlspecialchars($entry, ENT_QUOTES, 'UTF-8') . ' -->';
    }
    /** @var array<string, mixed> $chunk */
    $chunk = $decoded[$entry];
    /* Los archivos de Vite están en public/build (outDir de vite.config.js). */
    $base = rtrim(url('public/build'), '/');
    $v = (string) (@filemtime($manifestPath) ?: time());
    $html = '';
    foreach (fvd_vite_manifest_css_for_entry($decoded, $entry) as $css) {
        $href = $base . '/' . ltrim($css, '/') . '?v=' . rawurlencode($v);
        $html .= '<link rel="stylesheet" href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '">' . "\n";
    }
    if (isset($chunk['file'])) {
        $src = $base . '/' . ltrim((string) $chunk['file'], '/') . '?v=' . rawurlencode($v);
        $html .= '<script type="module" src="' . htmlspecialchars($src, ENT_QUOTES, 'UTF-8') . '"></script>';
    }

    return $html;
}

function fvd_vite_is_dev(): bool
{
    $raw = '';
    if (function_exists('env')) {
        $e = env('FVD_VITE_DEV', '');
        $raw = is_string($e) ? $e : (string) $e;
    }
    if ($raw === '') {
        $g = getenv('FVD_VITE_DEV');
        $raw = $g !== false ? (string) $g : '';
    }
    if ($raw === '') {
        return false;
    }

    return in_array(strtolower($raw), ['1', 'true', 'yes', 'on'], true);
}

function fvd_vite_dev_origin(): string
{
    $o = '';
    if (function_exists('env')) {
        $e = env('FVD_VITE_DEV_SERVER', '');
        $o = is_string($e) ? $e : '';
    }
    if ($o === '') {
        $g = getenv('FVD_VITE_DEV_SERVER');
        $o = $g !== false ? (string) $g : '';
    }
    if ($o !== '') {
        return rtrim($o, '/');
    }

    return 'http://localhost:5173';
}
