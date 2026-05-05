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
        /* Aceptar ret si coincide con APP_BASE_PATH o con BASE_URL (p. ej. inferido), no solo uno de ellos. */
        $prefixes = [];
        $fromEnv = rtrim((string) (function_exists('env') ? env('APP_BASE_PATH', '') : ''), '/');
        if ($fromEnv !== '') {
            $prefixes[] = $fromEnv;
        }
        if (defined('BASE_URL')) {
            $bu = rtrim((string) BASE_URL, '/');
            if ($bu !== '' && !in_array($bu, $prefixes, true)) {
                $prefixes[] = $bu;
            }
        }
        if ($prefixes !== []) {
            $ok = false;
            foreach ($prefixes as $pre) {
                if ($pre !== '' && strpos($raw, $pre) === 0) {
                    $ok = true;
                    break;
                }
            }
            if (!$ok) {
                return null;
            }
        }

        return $raw;
    }
}

if (!function_exists('fvd_master_panel_workspace_params')) {
    /**
     * Contexto del panel maestro (torneo + workspace) para propagarlo en iframes y en `ret`.
     * Prioridad: GET actual → sesión (fijada al cargar master_panel.php).
     *
     * @return array{ctx_torneo:int, fvd_ws:string}
     */
    function fvd_master_panel_workspace_params(): array
    {
        if (class_exists('AuthService', false)) {
            AuthService::ensureSession();
        }

        $ctx = isset($_GET['ctx_torneo']) ? max(0, (int) $_GET['ctx_torneo']) : 0;
        $sessCtx = 'fvd_master_panel_ctx_torneo';
        if ($ctx <= 0 && isset($_SESSION[$sessCtx])) {
            $ctx = max(0, (int) $_SESSION[$sessCtx]);
        }

        $ws = '';
        if (isset($_GET['fvd_ws']) && is_string($_GET['fvd_ws'])) {
            $cand = trim($_GET['fvd_ws']);
            if ($cand !== '' && preg_match('#^[a-zA-Z0-9_/\-]+$#', $cand)) {
                $ws = $cand;
            }
        }
        if ($ws === '' && isset($_SESSION['fvd_master_panel_fvd_ws']) && is_string($_SESSION['fvd_master_panel_fvd_ws'])) {
            $cand = trim($_SESSION['fvd_master_panel_fvd_ws']);
            if ($cand !== '' && preg_match('#^[a-zA-Z0-9_/\-]+$#', $cand)) {
                $ws = $cand;
            }
        }

        return ['ctx_torneo' => $ctx, 'fvd_ws' => $ws];
    }
}

if (!function_exists('fvd_master_panel_render_context_hiddens')) {
    /**
     * Campos ocultos para conservar ctx_torneo y fvd_ws en formularios embebidos del panel maestro.
     */
    function fvd_master_panel_render_context_hiddens(): void
    {
        if (!function_exists('fvd_master_embed_active') || !fvd_master_embed_active()) {
            return;
        }
        if (!function_exists('fvd_master_panel_workspace_params')) {
            return;
        }
        $wp = fvd_master_panel_workspace_params();
        if ($wp['ctx_torneo'] > 0) {
            echo '<input type="hidden" name="ctx_torneo" value="' . (int) $wp['ctx_torneo'] . '">' . "\n";
        }
        if ($wp['fvd_ws'] !== '') {
            echo '<input type="hidden" name="fvd_ws" value="' . htmlspecialchars($wp['fvd_ws'], ENT_QUOTES, 'UTF-8') . '">' . "\n";
        }
    }
}

if (!function_exists('fvd_return_current_for_link')) {
    /**
     * URL de esta petición (sin ret/return), para adjuntar como próximo origen.
     * En vista embebida del panel maestro incluye ctx_torneo y fvd_ws para que cada retorno
     * reconstruya el mismo workspace (listado, informes, formulario).
     */
    function fvd_return_current_for_link(): string
    {
        $path = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
        $qs = $_GET;
        unset($qs['ret'], $qs['return'], $qs['spa_json'], $qs['_fvd_embed_ts']);
        if (function_exists('fvd_master_embed_active') && fvd_master_embed_active() && function_exists('fvd_master_panel_workspace_params')) {
            $wp = fvd_master_panel_workspace_params();
            if ($wp['ctx_torneo'] > 0) {
                $qs['ctx_torneo'] = (string) $wp['ctx_torneo'];
            }
            if ($wp['fvd_ws'] !== '') {
                $qs['fvd_ws'] = $wp['fvd_ws'];
            }
        }
        $q = http_build_query($qs);

        return $path . ($q !== '' ? '?' . $q : '');
    }
}

if (!function_exists('fvd_return_append_to_url')) {
    /**
     * Añade ret=… a una URL conservando el origen indicado, o el ret ya en la URL,
     * o en su defecto la página actual (sin ret/return).
     * Si la vista es embebida del panel maestro, añade también fvd_master_embed=1.
     */
    function fvd_return_append_to_url(string $url, ?string $returnTo = null): string
    {
        $out = $url;
        if ($returnTo === null) {
            $returnTo = fvd_return_from_request() ?? fvd_return_current_for_link();
        }
        if ($returnTo !== '') {
            $sep = str_contains($out, '?') ? '&' : '?';
            $out .= $sep . 'ret=' . rawurlencode($returnTo);
        }
        if (function_exists('fvd_master_embed_active') && fvd_master_embed_active()) {
            if (preg_match('/[?&]embedded=1(?:&|$)/', $out) !== 1) {
                $sep = str_contains($out, '?') ? '&' : '?';
                $out .= $sep . 'embedded=1';
            }
            if (preg_match('/[?&]fvd_master_embed=1(?:&|$)/', $out) !== 1) {
                $sep = str_contains($out, '?') ? '&' : '?';
                $out .= $sep . 'fvd_master_embed=1';
            }
        }

        return $out;
    }
}

if (!function_exists('fvd_return_to_module_index')) {
    /**
     * URL al índice/listado de un módulo sin usar la página actual como ret:
     * solo conserva ret/return si ya venían en la petición (evita anidar el detalle
     * sobre el listado y romper la cadena hacia el panel u otras pantallas).
     */
    function fvd_return_to_module_index(string $moduleIndexUrl): string
    {
        $prev = fvd_return_from_request();

        return fvd_return_append_to_url($moduleIndexUrl, $prev !== null ? $prev : '');
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
        if (isset($_GET['embedded']) && (string) $_GET['embedded'] === '1') {
            $queryParams['embedded'] = '1';
        }
        if (isset($_GET['fvd_master_embed']) && (string) $_GET['fvd_master_embed'] === '1') {
            $queryParams['fvd_master_embed'] = '1';
        }
        if (isset($_GET['ctx_torneo']) && (int) $_GET['ctx_torneo'] > 0) {
            $queryParams['ctx_torneo'] = (string) (int) $_GET['ctx_torneo'];
        }
        if (isset($_GET['fvd_ws']) && is_string($_GET['fvd_ws'])) {
            $w = trim($_GET['fvd_ws']);
            if ($w !== '' && preg_match('#^[a-zA-Z0-9_/\-]+$#', $w)) {
                $queryParams['fvd_ws'] = $w;
            }
        }
        if (isset($_GET['torneo_id']) && (int) $_GET['torneo_id'] > 0) {
            $queryParams['torneo_id'] = (string) (int) $_GET['torneo_id'];
        }
        if (isset($_GET['campeonato_id']) && (int) $_GET['campeonato_id'] > 0) {
            $queryParams['campeonato_id'] = (string) (int) $_GET['campeonato_id'];
        }
        if (function_exists('fvd_master_embed_active') && fvd_master_embed_active() && function_exists('fvd_master_panel_workspace_params')) {
            $wp = fvd_master_panel_workspace_params();
            if ($wp['ctx_torneo'] > 0 && !isset($queryParams['ctx_torneo'])) {
                $queryParams['ctx_torneo'] = (string) $wp['ctx_torneo'];
            }
            if ($wp['fvd_ws'] !== '' && !isset($queryParams['fvd_ws'])) {
                $queryParams['fvd_ws'] = $wp['fvd_ws'];
            }
        }

        return $queryParams;
    }
}

if (!function_exists('fvd_master_embed_active')) {
    /**
     * Vista embebida desde el panel maestro (iframe): ocultar cabecera/nav/footer del layout
     * y conservar flags en enlaces. Acepta `embedded=1` (canónico) o `fvd_master_embed=1` (compat).
     */
    function fvd_master_embed_active(): bool
    {
        if (isset($_GET['embedded']) && (string) $_GET['embedded'] === '1') {
            return true;
        }
        if (isset($_POST['embedded']) && (string) $_POST['embedded'] === '1') {
            return true;
        }
        if (isset($_GET['fvd_master_embed']) && (string) $_GET['fvd_master_embed'] === '1') {
            return true;
        }
        if (isset($_POST['fvd_master_embed']) && (string) $_POST['fvd_master_embed'] === '1') {
            return true;
        }

        return false;
    }
}

if (!function_exists('fvd_append_embed_to_url')) {
    /**
     * Añade embedded=1 y fvd_master_embed=1 a una URL si aún no están (panel maestro → iframes).
     *
     * @param non-empty-string $url
     */
    function fvd_append_embed_to_url(string $url): string
    {
        $out = $url;
        if (preg_match('/[?&]embedded=1(?:&|$)/', $out) !== 1) {
            $out .= (str_contains($out, '?') ? '&' : '?') . 'embedded=1';
        }
        if (preg_match('/[?&]fvd_master_embed=1(?:&|$)/', $out) !== 1) {
            $out .= (str_contains($out, '?') ? '&' : '?') . 'fvd_master_embed=1';
        }

        return $out;
    }
}

if (!function_exists('fvd_return_preserve_query_params')) {
    /**
     * Añade ret=… a una URL de redirección si GET o POST traen un origen válido (p. ej. tras guardar formulario).
     * Conserva también fvd_master_embed=1 cuando la petición viene del iframe del panel maestro.
     */
    function fvd_return_preserve_query_params(string $url): string
    {
        $out = $url;
        $p = fvd_return_from_request();
        if ($p === null && isset($_POST['ret']) && is_string($_POST['ret'])) {
            $p = fvd_return_sanitize($_POST['ret']);
        }
        if ($p === null && isset($_POST['return']) && is_string($_POST['return'])) {
            $p = fvd_return_sanitize($_POST['return']);
        }
        if ($p !== null) {
            $sep = str_contains($out, '?') ? '&' : '?';
            $out .= $sep . 'ret=' . rawurlencode($p);
        }
        if (fvd_master_embed_active()) {
            if (preg_match('/[?&]embedded=1(?:&|$)/', $out) !== 1) {
                $sep = str_contains($out, '?') ? '&' : '?';
                $out .= $sep . 'embedded=1';
            }
            if (preg_match('/[?&]fvd_master_embed=1(?:&|$)/', $out) !== 1) {
                $sep = str_contains($out, '?') ? '&' : '?';
                $out .= $sep . 'fvd_master_embed=1';
            }
            if (function_exists('fvd_master_panel_workspace_params')) {
                $wp = fvd_master_panel_workspace_params();
                if ($wp['ctx_torneo'] > 0 && preg_match('/[?&]ctx_torneo=\d+/', $out) !== 1) {
                    $sep = str_contains($out, '?') ? '&' : '?';
                    $out .= $sep . 'ctx_torneo=' . (int) $wp['ctx_torneo'];
                }
                if ($wp['fvd_ws'] !== '' && preg_match('/[?&]fvd_ws=/', $out) !== 1) {
                    $sep = str_contains($out, '?') ? '&' : '?';
                    $out .= $sep . 'fvd_ws=' . rawurlencode($wp['fvd_ws']);
                }
            }
        }

        return $out;
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
