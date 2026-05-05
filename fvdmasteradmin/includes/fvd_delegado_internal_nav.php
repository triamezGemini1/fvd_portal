<?php

declare(strict_types=1);

/**
 * Rutas internas seguras para redirigir al delegado tras cambiar el torneo en contexto.
 */
function fvd_delegado_safe_return_path(string $raw): string
{
    $decoded = rawurldecode(trim($raw));
    if ($decoded === '' || ($decoded[0] ?? '') !== '/') {
        return '';
    }
    if (preg_match('#^[a-z][a-z0-9+.-]*:#i', $decoded)) {
        return '';
    }
    $base = rtrim((defined('BASE_URL') ? (string) BASE_URL : ''), '/');
    $prefixes = [];
    if ($base !== '') {
        $prefixes[] = $base . '/fvdmasteradmin/';
        $prefixes[] = $base . '/admin/modules/';
        $prefixes[] = $base . '/modules/';
    } else {
        $prefixes[] = '/fvdmasteradmin/';
        $prefixes[] = '/admin/modules/';
        $prefixes[] = '/modules/';
    }
    foreach ($prefixes as $p) {
        if ($p !== '' && strpos($decoded, $p) === 0) {
            return $decoded;
        }
    }

    return '';
}

/**
 * Ruta + query actual (sin torneo_id) para volver a la misma pantalla tras fijar contexto.
 */
function fvd_delegado_build_return_from_current_request(): string
{
    $sn = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    if ($sn === '' || $sn[0] !== '/') {
        return '';
    }
    $get = $_GET;
    unset($get['torneo_id']);
    $qs = $get !== [] ? '?' . http_build_query($get) : '';

    return $sn . $qs;
}

function fvd_delegado_absolute_redirect_url(string $pathWithOptionalQuery): string
{
    $path = $pathWithOptionalQuery;
    if ($path === '' || ($path[0] ?? '') !== '/') {
        return url('fvdmasteradmin/delegado_dashboard_new.php');
    }
    $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
    if ($host === '') {
        return $path;
    }
    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

    return ($https ? 'https' : 'http') . '://' . $host . $path;
}

/**
 * Añade o sobrescribe el parámetro campeonato_id (grupo de evento) en la query si la ruta es de inscripciones o atletas.
 */
function fvd_delegado_merge_campeonato_into_path(string $pathWithOptionalQuery, int $campeonatoGrupoId): string
{
    if ($campeonatoGrupoId <= 0) {
        return $pathWithOptionalQuery;
    }
    $pathWithOptionalQuery = trim($pathWithOptionalQuery);
    if ($pathWithOptionalQuery === '' || ($pathWithOptionalQuery[0] ?? '') !== '/') {
        return $pathWithOptionalQuery;
    }
    $qPos = strpos($pathWithOptionalQuery, '?');
    $pathOnly = $qPos === false ? $pathWithOptionalQuery : substr($pathWithOptionalQuery, 0, $qPos);
    $qs = $qPos === false ? '' : substr($pathWithOptionalQuery, $qPos + 1);
    $params = [];
    if ($qs !== '') {
        parse_str($qs, $params);
    }
    $needCamp = str_contains($pathOnly, 'torneo_inscripcion/')
        || str_contains($pathOnly, 'inscripcion_torneo/')
        || str_contains($pathOnly, 'inscripciones/')
        || str_contains($pathOnly, 'atletas/');
    if (!$needCamp) {
        return $pathWithOptionalQuery;
    }
    $params['campeonato_id'] = $campeonatoGrupoId;
    $newQs = '?' . http_build_query($params);

    return $pathOnly . $newQs;
}
