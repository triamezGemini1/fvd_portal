<?php

declare(strict_types=1);

/**
 * Resuelve alcance + tipo de listado desde GET (y compatibilidad con list_mode antiguo).
 *
 * @param array<string, scalar|null> $get
 * @return array{alcance: string, tipo: string, asociacion_id: int}
 */
function fvd_atletas_resolve_list_filters(array $get): array
{
    $allowedAlcance = ['todos', 'asociacion'];
    $allowedTipo = ['normal', 'ultimos', 'no_activos', 'bajas'];

    $alcance = isset($get['alcance']) ? trim((string) $get['alcance']) : '';
    $tipo = isset($get['tipo']) ? trim((string) $get['tipo']) : '';
    $asociacionId = isset($get['asociacion_id']) ? (int) $get['asociacion_id'] : 0;

    if ($alcance === '' && $tipo === '' && isset($get['list_mode'])) {
        $lm = trim((string) $get['list_mode']);
        if ($lm === 'general') {
            $alcance = 'todos';
            $tipo = 'normal';
        } elseif ($lm === 'asociacion') {
            $alcance = 'asociacion';
            $tipo = 'normal';
        } elseif ($lm === 'ultimos') {
            $alcance = 'todos';
            $tipo = 'ultimos';
        } elseif ($lm === 'bajas') {
            $alcance = 'todos';
            $tipo = 'bajas';
        }
    }

    if ($tipo === '' && AuthService::role() === AuthService::ROLE_FVD_ADMIN) {
        $tipo = 'ultimos';
    }

    if (!in_array($alcance, $allowedAlcance, true)) {
        $alcance = 'todos';
    }
    if (!in_array($tipo, $allowedTipo, true)) {
        $tipo = AuthService::role() === AuthService::ROLE_FVD_ADMIN ? 'ultimos' : 'normal';
    }

    if (AuthService::role() !== AuthService::ROLE_FVD_ADMIN) {
        $mineAsoc = AuthService::idAsociacion();
        if ($alcance === 'asociacion' && $asociacionId <= 0 && $mineAsoc !== null && $mineAsoc > 0) {
            $asociacionId = (int) $mineAsoc;
        }
        if ($alcance === 'asociacion' && $mineAsoc !== null && $asociacionId !== (int) $mineAsoc) {
            $asociacionId = (int) $mineAsoc;
        }
        if ($alcance === 'asociacion' && ($mineAsoc === null || (int) $mineAsoc <= 0)) {
            $alcance = 'todos';
            $asociacionId = 0;
        }
    } else {
        /** Admin FVD: un solo selector `asociacion_id` — >0 = club; 0 = toda la federación. */
        if ($asociacionId > 0) {
            $alcance = 'asociacion';
        } else {
            $alcance = 'todos';
            $asociacionId = 0;
        }
    }

    return [
        'alcance'         => $alcance,
        'tipo'            => $tipo,
        'asociacion_id'   => $asociacionId,
    ];
}

/**
 * Marcador de servicio para el listado admin de atletas (columna en 1).
 *
 * @param array<string, scalar|null> $get
 * @return ''|'carnet'|'traspaso'|'afiliacion'|'anualidad'|'inscripcion'
 */
function fvd_atletas_resolve_marcador(array $get): string
{
    $allowed = ['carnet', 'traspaso', 'afiliacion', 'anualidad', 'inscripcion'];
    $m = isset($get['marcador']) ? trim((string) $get['marcador']) : '';

    return in_array($m, $allowed, true) ? $m : '';
}
