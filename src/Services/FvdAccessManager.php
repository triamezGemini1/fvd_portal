<?php

declare(strict_types=1);

namespace FvdPortal\Services;

use PDO;

/**
 * Reglas de acceso del delegado al flujo por torneo (fases, límites de nómina).
 */
final class FvdAccessManager
{
    /**
     * @return array{
     *   fase_preparacion: bool,
     *   fase_ejecucion: bool,
     *   afiliaciones_traspasos: bool,
     *   inscripciones_carnets: bool,
     *   nomina_solo_lectura: bool,
     *   etiqueta: string,
     *   ventana: array<string, mixed>,
     *   fecha_limite_cambios: ?string,
     *   fecha_limite_cambios_formato: string
     * }
     */
    public static function estadoDelegadoTorneo(PDO $pdo, int $torneoId, ?int $asociacionId): array
    {
        $ventana = DelegadoTorneoVentanasService::estadoParaTorneo($pdo, $torneoId, $asociacionId);
        $limSup = DelegadoTorneoVentanasService::fechaLimiteCambiosNominaSuperada($pdo, $torneoId);

        $prep = (bool) ($ventana['fase1_afiliados_carnets_traspasos'] ?? false);
        $ejec = (bool) ($ventana['fase2_inscripciones'] ?? false);

        if ($limSup) {
            $ejec = false;
        }

        $etiqueta = (string) ($ventana['etiqueta_fase'] ?? '');
        if ($limSup) {
            $etiqueta = 'Límite de cambios de nómina alcanzado: inscripciones y cambios de plantilla en solo consulta. '
                . 'Afiliaciones y traspasos siguen las ventanas habituales si aplican.';
        }

        $fechaLimRaw = null;
        $fechaLimFmt = '';
        try {
            DelegadoTorneoVentanasService::ensureFechaLimiteCambiosColumn($pdo);
            $stf = $pdo->prepare('SELECT fecha_limite_cambios FROM torneosact WHERE torneo = :t LIMIT 1');
            $stf->execute([':t' => $torneoId]);
            $col = $stf->fetchColumn();
            if ($col !== false && $col !== null && trim((string) $col) !== '') {
                $fechaLimRaw = substr((string) $col, 0, 10);
                $ts = strtotime($fechaLimRaw . ' 12:00:00');
                $fechaLimFmt = $ts !== false ? date('d/m/Y', $ts) : $fechaLimRaw;
            }
        } catch (\Throwable $e) {
        }

        return [
            'fase_preparacion' => $prep,
            'fase_ejecucion' => $ejec,
            'afiliaciones_traspasos' => $prep,
            'inscripciones_carnets' => $ejec && !$limSup,
            'nomina_solo_lectura' => $limSup,
            'etiqueta' => $etiqueta,
            'ventana' => $ventana,
            'fecha_limite_cambios' => $fechaLimRaw,
            'fecha_limite_cambios_formato' => $fechaLimFmt,
        ];
    }
}
