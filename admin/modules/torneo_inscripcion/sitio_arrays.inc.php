<?php

declare(strict_types=1);

/**
 * Construye listas para el panel «sitio» de inscripción (disponibles / inscritos).
 *
 * @return array{
 *   fvdSitioDisponibles: list<array<string,mixed>>,
 *   fvdSitioInscritos: list<array<string,mixed>>,
 *   fvdSitioInscritosGrupos: list<array<string,mixed>>,
 *   fvd_sitio_clase: int
 * }
 */
function fvd_torneo_inscripcion_build_sitio_arrays(
    FvdAdminService $svc,
    PDO $pdo,
    int $torneoSel,
    int $asocId,
    bool $tieneColsBandera
): array {
    require_once dirname(__DIR__, 3) . '/src/Services/InscripcionService.php';
    try {
        return fvd_torneo_inscripcion_build_sitio_arrays_inner($svc, $pdo, $torneoSel, $asocId, $tieneColsBandera);
    } catch (Throwable $e) {
        error_log('[fvd_torneo_inscripcion_build_sitio_arrays] ' . $e->getMessage());

        return [
            'fvdSitioDisponibles'      => [],
            'fvdSitioInscritos'        => [],
            'fvdSitioInscritosGrupos'  => [],
            'fvd_sitio_clase'          => \FvdPortal\Services\InscripcionService::CLASE_INDIVIDUAL,
        ];
    }
}

/**
 * @return array{fvdSitioDisponibles: list<array<string,mixed>>, fvdSitioInscritos: list<array<string,mixed>>, fvdSitioInscritosGrupos: list<array<string,mixed>>, fvd_sitio_clase: int}
 */
function fvd_torneo_inscripcion_build_sitio_arrays_inner(
    FvdAdminService $svc,
    PDO $pdo,
    int $torneoSel,
    int $asocId,
    bool $tieneColsBandera
): array {
    $torRow = \FvdPortal\Services\InscripcionService::torneoReglas($pdo, $torneoSel);
    $clase = $torRow !== null
        ? \FvdPortal\Services\InscripcionService::normalizarClaseTorneo($torRow)
        : \FvdPortal\Services\InscripcionService::CLASE_INDIVIDUAL;

    $atletasDisp = [];
    try {
        $atletasDisp = $svc->torneosAtletasInscribiblesFiltradoTorneo($torneoSel, $asocId);
    } catch (Throwable $e) {
        error_log('[sitio_arrays] torneosAtletasInscribiblesFiltradoTorneo: ' . $e->getMessage());
    }

    $inscritosBandera = [];
    if ($tieneColsBandera) {
        try {
            $inscritosBandera = \FvdPortal\Services\InscripcionService::listarInscritosBandera($pdo, $torneoSel, $asocId);
        } catch (Throwable $e) {
            error_log('[sitio_arrays] listarInscritosBandera: ' . $e->getMessage());
        }
    }

    $fvdSitioDisponibles = [];
    $fvdSitioInscritos = [];
    $fvdSitioInscritosGrupos = [];

    foreach ($atletasDisp as $row) {
        $fvdSitioDisponibles[] = [
            'atleta_id' => (int) ($row['id'] ?? 0),
            'nombre' => (string) ($row['nombre'] ?? ''),
            'cedula' => (string) ($row['cedula'] ?? ''),
            'numfvd' => (int) ($row['numfvd'] ?? 0),
            'cedula_num' => (int) ($row['_cedula_num'] ?? 0),
        ];
    }

    if ($clase === \FvdPortal\Services\InscripcionService::CLASE_INDIVIDUAL) {
        $banderaPorAtletaId = [];
        $banderaCedulaIndiv = [];
        foreach ($inscritosBandera as $ib) {
            $aidB = (int) ($ib['id'] ?? 0);
            $cedN = (int) preg_replace('/\D+/', '', (string) ($ib['cedula'] ?? ''));
            if ($aidB > 0) {
                $banderaPorAtletaId[$aidB] = true;
            }
            if ($cedN > 0) {
                $banderaCedulaIndiv[$cedN] = true;
            }
            $fvdSitioInscritos[] = [
                'atleta_id' => $aidB,
                'nombre' => (string) ($ib['nombre'] ?? ''),
                'cedula' => (string) ($ib['cedula'] ?? ''),
                'numfvd' => (int) ($ib['numfvd'] ?? 0),
                'cedula_num' => $cedN,
                'equipo' => 0,
                'retirar_mode' => $aidB > 0 ? 'bandera' : '0',
            ];
        }

        if ($svc->torneosInscripcionTorneoTableExists()) {
            $rowsTabla = [];
            try {
                $rowsTabla = $svc->torneosInscritosInscripcionTorneo($torneoSel, $asocId);
            } catch (Throwable $e) {
                error_log('[sitio_arrays] torneosInscritosInscripcionTorneo (indiv): ' . $e->getMessage());
            }
            foreach ($rowsTabla as $r) {
                $aidT = isset($r['atleta_id']) && $r['atleta_id'] !== null ? (int) $r['atleta_id'] : 0;
                $cedN = (int) preg_replace('/\D+/', '', (string) ($r['cedula'] ?? ''));
                $eq = (int) ($r['equipo'] ?? 0);
                if ($aidT > 0 && isset($banderaPorAtletaId[$aidT])) {
                    continue;
                }
                if ($eq === 0 && $cedN > 0 && isset($banderaCedulaIndiv[$cedN])) {
                    continue;
                }
                $rm = $eq === 0 && $cedN > 0 ? 'tabla' : '0';
                $fvdSitioInscritos[] = [
                    'atleta_id' => $aidT,
                    'nombre' => (string) ($r['nombre'] ?? ''),
                    'cedula' => (string) ($r['cedula'] ?? ''),
                    'numfvd' => (int) ($r['numfvd'] ?? 0),
                    'cedula_num' => $cedN,
                    'equipo' => $eq,
                    'retirar_mode' => $rm,
                ];
            }
        }

        usort(
            $fvdSitioInscritos,
            static function (array $a, array $b): int {
                return strcasecmp((string) ($a['nombre'] ?? ''), (string) ($b['nombre'] ?? ''));
            }
        );

        return [
            'fvdSitioDisponibles' => $fvdSitioDisponibles,
            'fvdSitioInscritos'   => $fvdSitioInscritos,
            'fvdSitioInscritosGrupos' => [],
            'fvd_sitio_clase'     => $clase,
        ];
    }

    /* Parejas / equipos: listado por grupo (inscripcion_torneo.equipo) */
    $cedulasIt = [];
    if ($svc->torneosInscripcionTorneoTableExists()) {
        $rowsIt = [];
        try {
            $rowsIt = $svc->torneosInscritosInscripcionTorneo($torneoSel, $asocId);
        } catch (Throwable $e) {
            error_log('[sitio_arrays] torneosInscritosInscripcionTorneo (parejas/eq): ' . $e->getMessage());
        }
        $byEquipo = [];
        foreach ($rowsIt as $r) {
            $eqN = (int) ($r['equipo'] ?? 0);
            if ($eqN <= 0) {
                continue;
            }
            if (!isset($byEquipo[$eqN])) {
                $ne = trim((string) ($r['nombre_equipo'] ?? ''));
                $byEquipo[$eqN] = [
                    'equipo' => $eqN,
                    'nombre_equipo' => $ne !== '' ? $ne : ('Grupo ' . $eqN),
                    'miembros' => [],
                ];
            }
            $cedN = (int) preg_replace('/\D+/', '', (string) ($r['cedula'] ?? ''));
            if ($cedN > 0) {
                $cedulasIt[$cedN] = true;
            }
            $aidT = isset($r['atleta_id']) && $r['atleta_id'] !== null ? (int) $r['atleta_id'] : 0;
            $rmEq = $tieneColsBandera ? 'retirar_equipo_bandera' : 'retirar_equipo_tabla';
            $byEquipo[$eqN]['miembros'][] = [
                'atleta_id' => $aidT,
                'nombre' => (string) ($r['nombre'] ?? ''),
                'cedula' => (string) ($r['cedula'] ?? ''),
                'numfvd' => (int) ($r['numfvd'] ?? 0),
                'cedula_num' => $cedN,
                'retirar_mode' => $rmEq,
            ];
        }
        foreach ($byEquipo as $g) {
            usort(
                $g['miembros'],
                static function (array $a, array $b): int {
                    return strcasecmp((string) ($a['nombre'] ?? ''), (string) ($b['nombre'] ?? ''));
                }
            );
            $fvdSitioInscritosGrupos[] = [
                'equipo' => (int) $g['equipo'],
                'nombre_equipo' => (string) $g['nombre_equipo'],
                'es_legacy_solo_bandera' => false,
                'integrantes' => $g['miembros'],
            ];
        }
    }

    if ($tieneColsBandera) {
        foreach ($inscritosBandera as $ib) {
            $cedN = (int) preg_replace('/\D+/', '', (string) ($ib['cedula'] ?? ''));
            if ($cedN > 0 && isset($cedulasIt[$cedN])) {
                continue;
            }
            $aidB = (int) ($ib['id'] ?? 0);
            if ($aidB <= 0) {
                continue;
            }
            $fvdSitioInscritosGrupos[] = [
                'equipo' => 0,
                'nombre_equipo' => 'Inscripción (solo bandera — sin ficha de equipo en tabla)',
                'es_legacy_solo_bandera' => true,
                'integrantes' => [[
                    'atleta_id' => $aidB,
                    'nombre' => (string) ($ib['nombre'] ?? ''),
                    'cedula' => (string) ($ib['cedula'] ?? ''),
                    'numfvd' => (int) ($ib['numfvd'] ?? 0),
                    'cedula_num' => $cedN,
                    'retirar_mode' => 'bandera',
                ]],
            ];
        }
    }

    usort(
        $fvdSitioInscritosGrupos,
        static function (array $a, array $b): int {
            $ae = (int) ($a['es_legacy_solo_bandera'] ?? 0);
            $be = (int) ($b['es_legacy_solo_bandera'] ?? 0);
            if ($ae !== $be) {
                return $ae <=> $be;
            }

            return strcasecmp((string) ($a['nombre_equipo'] ?? ''), (string) ($b['nombre_equipo'] ?? ''));
        }
    );

    return [
        'fvdSitioDisponibles' => $fvdSitioDisponibles,
        'fvdSitioInscritos'   => [],
        'fvdSitioInscritosGrupos' => $fvdSitioInscritosGrupos,
        'fvd_sitio_clase'     => $clase,
    ];
}
