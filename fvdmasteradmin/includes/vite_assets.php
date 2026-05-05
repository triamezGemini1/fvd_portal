<?php

declare(strict_types=1);

/**
 * Proyecto Vite/Node en disco: C:\wamp64\www\fvd_panel (hermano de fvd_portal bajo www).
 */
function fvd_vite_disk_root(): string
{
    if (function_exists('env')) {
        $custom = env('FVD_VITE_DISK_ROOT', '');
        if (is_string($custom) && $custom !== '' && is_dir($custom)) {
            return rtrim($custom, '/\\');
        }
    }
    $g = getenv('FVD_VITE_DISK_ROOT');
    if ($g !== false && is_string($g) && $g !== '' && is_dir($g)) {
        return rtrim($g, '/\\');
    }

    /** includes → fvdmasteradmin → fvd_portal (raíz) → www → fvd_panel */
    return dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'fvd_panel';
}

/**
 * Manifest de producción: hermano `fvd_panel` o copia en `fvd_portal/public/build`
 * (tras `npm run build` en la raíz del portal, que ejecuta el copy).
 */
function fvd_vite_manifest_path(): string
{
    $portalRoot = dirname(__DIR__, 2);
    $portalManifest = $portalRoot . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'build'
        . DIRECTORY_SEPARATOR . '.vite' . DIRECTORY_SEPARATOR . 'manifest.json';

    if (function_exists('env')) {
        $ov = env('FVD_VITE_MANIFEST', '');
        if (is_string($ov) && $ov !== '' && is_readable($ov)) {
            return $ov;
        }
    }
    $g = getenv('FVD_VITE_MANIFEST');
    if ($g !== false && is_string($g) && $g !== '' && is_readable($g)) {
        return $g;
    }

    $siblingManifest = fvd_vite_disk_root() . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'build'
        . DIRECTORY_SEPARATOR . '.vite' . DIRECTORY_SEPARATOR . 'manifest.json';

    $portalOk = is_readable($portalManifest);
    $siblingOk = is_readable($siblingManifest);
    if ($portalOk && $siblingOk) {
        $mtP = (int) (@filemtime($portalManifest) ?: 0);
        $mtS = (int) (@filemtime($siblingManifest) ?: 0);

        /* Más reciente gana; empate → portal (mismo host que atletas.php / panel). */
        return $mtS > $mtP ? $siblingManifest : $portalManifest;
    }
    if ($portalOk) {
        return $portalManifest;
    }
    if ($siblingOk) {
        return $siblingManifest;
    }

    return $siblingManifest;
}

/**
 * URL base para enlazar CSS/JS del build (DocumentRoot típico: /fvd_panel/public/build).
 * Sobrescribir con FVD_VITE_PUBLIC_BASE en .env si la ruta pública difiere.
 */
function fvd_vite_public_build_url(): string
{
    if (function_exists('env')) {
        $b = env('FVD_VITE_PUBLIC_BASE', '');
        if (is_string($b) && $b !== '') {
            return rtrim($b, '/');
        }
    }
    $g = getenv('FVD_VITE_PUBLIC_BASE');
    if ($g !== false && (string) $g !== '') {
        return rtrim((string) $g, '/');
    }

    return '/fvd_panel/public/build';
}

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

    $manifestPath = fvd_vite_manifest_path();
    if (!is_readable($manifestPath)) {
        $legacy = $projRoot . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'build'
            . DIRECTORY_SEPARATOR . '.vite' . DIRECTORY_SEPARATOR . 'manifest.json';
        if (is_readable($legacy)) {
            $manifestPath = $legacy;
        }
    }
    if (!is_readable($manifestPath)) {
        return '<p class="fvd-mod-msg" role="alert" style="margin:0.75rem 0;padding:0.75rem;background:#fff7ed;border:1px solid #fdba74;color:#9a3412;font-size:0.875rem;">'
            . 'No se encontró el build de Vite (<code>fvd_panel/public/build/.vite/manifest.json</code>). En <code>C:\\wamp64\\www\\fvd_panel</code> ejecute <code>npm install</code> y <code>npm run build</code>, o active el modo desarrollo (<code>FVD_VITE_DEV=true</code> y servidor Vite en el puerto configurado).</p>';
    }
    /** @var mixed $decoded */
    $decoded = json_decode((string) file_get_contents($manifestPath), true);
    if (!is_array($decoded) || !isset($decoded[$entry]) || !is_array($decoded[$entry])) {
        return '<p class="fvd-mod-msg" role="alert" style="margin:0.75rem 0;padding:0.75rem;background:#fff7ed;border:1px solid #fdba74;color:#9a3412;font-size:0.875rem;">'
            . 'Entrada Vite no encontrada en el manifest: <code>' . htmlspecialchars($entry, ENT_QUOTES, 'UTF-8') . '</code>. Ejecute <code>npm run build</code> tras añadir el entry en Vite.</p>';
    }
    /** @var array<string, mixed> $chunk */
    $chunk = $decoded[$entry];
    $mp = str_replace('\\', '/', $manifestPath);
    /* Manifest bajo fvd_portal/public/build → assets relativos a esta app (evita 404 si /fvd_panel no está mapeado). */
    $fromSiblingPanel = (str_contains($mp, '/fvd_panel/') || preg_match('#(^|/)fvd_panel/public/build/#', $mp) === 1)
        && !str_contains($mp, '/fvd_portal/');
    $base = $fromSiblingPanel
        ? fvd_vite_public_build_url()
        : rtrim(url('public/build'), '/');
    /** Versión de caché: manifest + archivo del entry (evita JS viejo tras build con mismo manifest si el navegador cachea agresivamente). */
    $v = (int) (@filemtime($manifestPath) ?: time());
    if (isset($chunk['file']) && is_string($chunk['file']) && $chunk['file'] !== '') {
        $buildRoot = dirname($manifestPath, 2);
        $chunkOnDisk = $buildRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, ltrim((string) $chunk['file'], '/'));
        if (is_readable($chunkOnDisk)) {
            $t = @filemtime($chunkOnDisk);
            if ($t !== false) {
                $v = max($v, (int) $t);
            }
        }
    }
    foreach (fvd_vite_manifest_css_for_entry($decoded, $entry) as $cssRel) {
        if (!is_string($cssRel) || $cssRel === '') {
            continue;
        }
        $buildRoot = dirname($manifestPath, 2);
        $cssOnDisk = $buildRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, ltrim($cssRel, '/'));
        if (is_readable($cssOnDisk)) {
            $t = @filemtime($cssOnDisk);
            if ($t !== false) {
                $v = max($v, (int) $t);
            }
        }
    }
    $v = (string) $v;
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
