<?php

declare(strict_types=1);

/**
 * Retorno de navegación al “origen” real: se pasa por GET `ret` o `return` (ruta interna validada).
 * Use {@see fvd_return_append_to_url()} al enlazar hacia otra pantalla para conservar el origen.
 */

if (!function_exists('fvd_return_base_path_prefix')) {
    function fvd_return_base_path_prefix(): string
    {
        $b = rtrim((string) (function_exists('env') ? env('APP_BASE_PATH', '') : ''), '/');

        return $b === '' ? '/' : $b;
    }
}

if (!function_exists('fvd_return_sanitize')) {
    /**
     * @return string|null Ruta + query internas (p. ej. /fvd_portal/modules/atletas/index.php?action=list)
     */
    function fvd_return_sanitize(?string $raw): ?string
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        $raw = trim(rawurldecode($raw));
        if ($raw === '') {
            return null;
        }
        if (str_starts_with($raw, '//')) {
            return null;
        }
        if (preg_match('#^https?://#i', $raw) === 1) {
            $p = parse_url($raw);
            if (!is_array($p)) {
                return null;
            }
            $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
            if ($host !== '' && isset($p['host']) && strcasecmp((string) $p['host'], $host) !== 0) {
                return null;
            }
            $path = (string) ($p['path'] ?? '/');
            $q = isset($p['query']) && (string) $p['query'] !== '' ? '?' . $p['query'] : '';

            return $path . $q;
        }
        if (!isset($raw[0]) || $raw[0] !== '/') {
            return null;
        }
        $base = fvd_return_base_path_prefix();
        if ($base !== '/' && strpos($raw, $base) !== 0) {
            return null;
        }

        return $raw;
    }
}

if (!function_exists('fvd_return_current_for_link')) {
    /**
     * URL de esta petición (sin ret/return), para adjuntar como próximo origen.
     */
    function fvd_return_current_for_link(): string
    {
        $path = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
        $qs = $_GET;
        unset($qs['ret'], $qs['return']);
        $q = http_build_query($qs);

        return $path . ($q !== '' ? '?' . $q : '');
    }
}

if (!function_exists('fvd_return_append_to_url')) {
    /**
     * Añade ret=… a una URL conservando el origen indicado, o el ret ya en la URL,
     * o en su defecto la página actual (sin ret/return).
     */
    function fvd_return_append_to_url(string $url, ?string $returnTo = null): string
    {
        if ($returnTo === null) {
            $returnTo = fvd_return_from_request() ?? fvd_return_current_for_link();
        }
        if ($returnTo === '') {
            return $url;
        }
        $sep = str_contains($url, '?') ? '&' : '?';

        return $url . $sep . 'ret=' . rawurlencode($returnTo);
    }
}

if (!function_exists('fvd_return_merge_get_params')) {
    /**
     * Copia ret o return válidos del GET actual para conservarlos en paginación / filtros.
     *
     * @param array<string, scalar|null> $queryParams
     * @return array<string, scalar|null>
     */
    function fvd_return_merge_get_params(array $queryParams): array
    {
        if (isset($_GET['ret']) && is_string($_GET['ret']) && $_GET['ret'] !== '' && fvd_return_sanitize($_GET['ret']) !== null) {
            $queryParams['ret'] = $_GET['ret'];
        } elseif (isset($_GET['return']) && is_string($_GET['return']) && $_GET['return'] !== '' && fvd_return_sanitize($_GET['return']) !== null) {
            $queryParams['return'] = $_GET['return'];
        }

        return $queryParams;
    }
}

if (!function_exists('fvd_return_preserve_query_params')) {
    /**
     * Añade ret=… a una URL de redirección si GET o POST traen un origen válido (p. ej. tras guardar formulario).
     */
    function fvd_return_preserve_query_params(string $url): string
    {
        $p = fvd_return_from_request();
        if ($p === null && isset($_POST['ret']) && is_string($_POST['ret'])) {
            $p = fvd_return_sanitize($_POST['ret']);
        }
        if ($p === null && isset($_POST['return']) && is_string($_POST['return'])) {
            $p = fvd_return_sanitize($_POST['return']);
        }
        if ($p === null) {
            return $url;
        }
        $sep = str_contains($url, '?') ? '&' : '?';

        return $url . $sep . 'ret=' . rawurlencode($p);
    }
}

if (!function_exists('fvd_return_from_request')) {
    function fvd_return_from_request(): ?string
    {
        $r = $_GET['ret'] ?? $_GET['return'] ?? '';
        if (!is_string($r) || $r === '') {
            return null;
        }

        return fvd_return_sanitize($r);
    }
}
