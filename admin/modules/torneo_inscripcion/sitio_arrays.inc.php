<?php

declare(strict_types=1);

/**
 * Construye listas para el panel «sitio» de inscripción (disponibles / inscritos).
 *
 * @return array{fvdSitioDisponibles: list<array<string,mixed>>, fvdSitioInscritos: list<array<string,mixed>>}
 */
function fvd_torneo_inscripcion_build_sitio_arrays(
    FvdAdminService $svc,
    PDO $pdo,
    int $torneoSel,
    int $asocId,
    bool $tieneColsBandera
): array {
    $atletasDisp = $svc->torneosAtletasInscribiblesFiltradoTorneo($torneoSel, $asocId);

    $inscritosBandera = ($tieneColsBandera)
        ? \FvdPortal\Services\InscripcionService::listarInscritosBandera($pdo, $torneoSel, $asocId)
        : [];

    $fvdSitioDisponibles = [];
    $fvdSitioInscritos = [];

    foreach ($atletasDisp as $row) {
        $fvdSitioDisponibles[] = [
            'atleta_id' => (int) ($row['id'] ?? 0),
            'nombre' => (string) ($row['nombre'] ?? ''),
            'cedula' => (string) ($row['cedula'] ?? ''),
            'numfvd' => (int) ($row['numfvd'] ?? 0),
            'cedula_num' => (int) ($row['_cedula_num'] ?? 0),
        ];
    }

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
        foreach ($svc->torneosInscritosInscripcionTorneo($torneoSel, $asocId) as $r) {
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
    ];
}
