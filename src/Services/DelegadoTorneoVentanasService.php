<?php

declare(strict_types=1);

namespace FvdPortal\Services;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use PDO;
use PDOException;
use RuntimeException;

require_once dirname(__DIR__, 2) . '/fvdmasteradmin/services/AuthService.php';

/**
 * Ventanas de acceso del delegado respecto a la fecha del torneo (fechator en torneosact).
 *
 * Fase 1 (día del torneo −15 … −7): afiliaciones, carnets, traspasos, altas de atletas.
 * Fase 2 (día del torneo −7 … −3): inscripciones, retiros y cambios en plantilla.
 * Fuera de esas ventanas: solo consulta y pagos (sin mutaciones operativas).
 */
final class DelegadoTorneoVentanasService
{
    /** Días antes del torneo donde abre/cierra cada fase (inclusive). */
    public const DIA_FASE1_INICIO = 15;

    public const DIA_FASE1_FIN = 7;

    public const DIA_FASE2_INICIO = 7;

    public const DIA_FASE2_FIN = 3;

    public static function aplicaRestriccionDelegado(): bool
    {
        if (\AuthService::role() === \AuthService::ROLE_FVD_ADMIN) {
            return false;
        }

        return \AuthService::isDelegadoAsociacion();
    }

    private static function timezoneApp(): DateTimeZone
    {
        $tzName = function_exists('env') ? (string) env('APP_TIMEZONE', 'America/Caracas') : 'America/Caracas';
        try {
            return new DateTimeZone($tzName);
        } catch (\Exception $e) {
            return new DateTimeZone('UTC');
        }
    }

    /**
     * @return array{
     *   torneo_id:int,
     *   fechator:?string,
     *   hoy:string,
     *   fase1_afiliados_carnets_traspasos:bool,
     *   fase2_inscripciones:bool,
     *   solo_consulta_y_pagos:bool,
     *   etiqueta_fase:string
     * }
     */
    public static function estadoParaTorneo(PDO $pdo, int $torneoId): array
    {
        if ($torneoId <= 0) {
            throw new InvalidArgumentException('Torneo no válido.');
        }
        $fechator = self::fechatorTorneo($pdo, $torneoId);
        $tz = self::timezoneApp();
        $today = (new DateTimeImmutable('today', $tz))->setTime(0, 0, 0);

        $base = [
            'torneo_id' => $torneoId,
            'fechator' => $fechator,
            'hoy' => $today->format('Y-m-d'),
            'fase1_afiliados_carnets_traspasos' => false,
            'fase2_inscripciones' => false,
            'solo_consulta_y_pagos' => true,
            'etiqueta_fase' => 'Sin fecha de torneo: gestión restringida.',
        ];

        if ($fechator === null || $fechator === '') {
            return $base;
        }

        try {
            $tTor = new DateTimeImmutable($fechator . ' 00:00:00', $tz);
        } catch (\Exception $e) {
            return $base;
        }

        $d15 = $tTor->modify('-' . self::DIA_FASE1_INICIO . ' days')->setTime(0, 0, 0);
        $d7 = $tTor->modify('-' . self::DIA_FASE1_FIN . ' days')->setTime(0, 0, 0);
        $d3 = $tTor->modify('-' . self::DIA_FASE2_FIN . ' days')->setTime(0, 0, 0);

        $inFase1 = $today >= $d15 && $today <= $d7;
        $inFase2 = $today >= $d7 && $today <= $d3;
        $soloConsulta = !$inFase1 && !$inFase2;

        $etiqueta = 'Periodo cerrado: solo consulta y pagos.';
        if ($inFase1 && $inFase2) {
            $etiqueta = 'Fase 1 y 2 (gestión ampliada): hasta el día ' . self::DIA_FASE2_FIN . ' antes del evento.';
        } elseif ($inFase1) {
            $etiqueta = 'Fase 1: afiliaciones, carnets y traspasos (hasta ' . self::DIA_FASE1_FIN . ' días antes del torneo).';
        } elseif ($inFase2) {
            $etiqueta = 'Fase 2: inscripciones, retiros y cambios (hasta ' . self::DIA_FASE2_FIN . ' días antes del torneo).';
        }

        return [
            'torneo_id' => $torneoId,
            'fechator' => $fechator,
            'hoy' => $today->format('Y-m-d'),
            'fase1_afiliados_carnets_traspasos' => $inFase1,
            'fase2_inscripciones' => $inFase2,
            'solo_consulta_y_pagos' => $soloConsulta,
            'etiqueta_fase' => $etiqueta,
        ];
    }

    private static function fechatorTorneo(PDO $pdo, int $torneoId): ?string
    {
        try {
            $st = $pdo->prepare('SELECT DATE(fechator) AS f FROM torneosact WHERE torneo = :t LIMIT 1');
            $st->execute([':t' => $torneoId]);
            $row = $st->fetch(PDO::FETCH_ASSOC);

            return $row !== false && isset($row['f']) && $row['f'] !== null && $row['f'] !== ''
                ? (string) $row['f']
                : null;
        } catch (PDOException $e) {
            error_log('[DelegadoTorneoVentanasService] fechatorTorneo: ' . $e->getMessage());

            return null;
        }
    }

    public static function assertPuedeFase1Administrativa(PDO $pdo, int $torneoId): void
    {
        if (!self::aplicaRestriccionDelegado()) {
            return;
        }
        $st = self::estadoParaTorneo($pdo, $torneoId);
        if (!$st['fase1_afiliados_carnets_traspasos']) {
            throw new RuntimeException(
                'La fase 1 (afiliaciones, carnets y traspasos) solo está habilitada entre '
                . self::DIA_FASE1_INICIO . ' y ' . self::DIA_FASE1_FIN . ' días antes de la fecha del torneo. '
                . 'Fuera de ese periodo solo puede consultar y registrar pagos.'
            );
        }
    }

    public static function assertPuedeInscripcionesRetiros(PDO $pdo, int $torneoId): void
    {
        if (!self::aplicaRestriccionDelegado()) {
            return;
        }
        $st = self::estadoParaTorneo($pdo, $torneoId);
        if (!$st['fase2_inscripciones']) {
            throw new RuntimeException(
                'Las inscripciones, retiros y cambios solo están habilitados entre '
                . self::DIA_FASE2_INICIO . ' y ' . self::DIA_FASE2_FIN . ' días antes de la fecha del torneo. '
                . 'Fuera de ese periodo solo puede consultar y registrar pagos.'
            );
        }
    }

    /**
     * Alta de nuevo atleta (delegado): solo fase 1.
     */
    public static function assertDelegadoPuedeAltaAtleta(PDO $pdo, int $torneoId): void
    {
        self::assertPuedeFase1Administrativa($pdo, $torneoId);
    }

    /**
     * Edición / baja / foto de atleta existente: fase 1 o fase 2.
     */
    public static function assertDelegadoPuedeEditarOBorrarAtleta(PDO $pdo, int $torneoId): void
    {
        if (!self::aplicaRestriccionDelegado()) {
            return;
        }
        $st = self::estadoParaTorneo($pdo, $torneoId);
        if (!$st['fase1_afiliados_carnets_traspasos'] && !$st['fase2_inscripciones']) {
            throw new RuntimeException(
                'El periodo de gestión de atletas para este torneo ha finalizado. Solo puede consultar información y registrar pagos.'
            );
        }
    }
}
