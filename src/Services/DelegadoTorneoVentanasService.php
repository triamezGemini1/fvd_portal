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
require_once __DIR__ . '/DelegadoTorneoNotifService.php';

/**
 * Ventanas de acceso del delegado respecto a la fecha del torneo (fechator en torneosact).
 *
 * Fase 1 (calendario por defecto: día del torneo −15 … −7): afiliaciones, carnets, traspasos, altas de atletas.
 * Cuando el club tiene convocatoria / notificación de invitación al torneo, la fase 1 puede abrirse **desde esa fecha**
 * (tras crear el torneo e invitar), sin esperar la ventana −15 días.
 * Fase 2 (día del torneo −7 … −3): inscripciones, retiros y cambios en plantilla.
 * Invitación tardía (tras −7 días): se mantiene una ventana corta de gestión administrativa hasta −3 días.
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

    /**
     * Omite ventanas por calendario, límite de nómina y asertos de fase para el delegado.
     *
     * - Por defecto (sin `FVD_DELEGADO_CALENDARIO_STRICT`): **no hay límites de tiempo** (afiliaciones, carnets,
     *   traspasos e inscripciones al torneo, p. ej. individual).
     * - Producción con calendario: `FVD_DELEGADO_CALENDARIO_STRICT=true`
     * - Compatibilidad: `FVD_DELEGADO_MODO_PRUEBAS=true` fuerza omitir aunque STRICT esté activo.
     */
    public static function delegadoOmiteRestriccionVentanas(): bool
    {
        if (!self::aplicaRestriccionDelegado()) {
            return false;
        }
        if (!function_exists('env')) {
            return true;
        }
        $modo = strtolower(trim((string) env('FVD_DELEGADO_MODO_PRUEBAS', '')));
        if (in_array($modo, ['1', 'true', 'yes', 'on'], true)) {
            return true;
        }
        $strict = strtolower(trim((string) env('FVD_DELEGADO_CALENDARIO_STRICT', 'false')));
        if (in_array($strict, ['1', 'true', 'yes', 'on'], true)) {
            return false;
        }

        return true;
    }

    /**
     * @param array<string, mixed> $st
     * @return array<string, mixed>
     */
    private static function aplicarOverrideModoPruebas(array $st): array
    {
        if (!self::delegadoOmiteRestriccionVentanas()) {
            return $st;
        }
        $st['fase1_afiliados_carnets_traspasos'] = true;
        $st['fase2_inscripciones'] = true;
        $st['solo_consulta_y_pagos'] = false;
        $st['etiqueta_fase'] = 'Restricciones por calendario delegado desactivadas (predeterminado; en producción use FVD_DELEGADO_CALENDARIO_STRICT=true).';
        $st['gestion_admin_desde_invitacion'] = false;

        return $st;
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
     * Primera fecha (Y-m-d) en que el club quedó invitado / notificado al torneo (convocatoria o panel).
     */
    public static function fechaInicioGestionPorInvitacion(PDO $pdo, int $torneoId, int $asociacionId): ?string
    {
        if ($torneoId <= 0 || $asociacionId <= 0) {
            return null;
        }
        try {
            $st = $pdo->prepare(
                'SELECT DATE(MIN(c.invitado_en)) AS d
                 FROM torneo_convocatoria_asoc c
                 WHERE c.torneo_id = :t AND c.asociacion_id = :a AND c.invitado_en IS NOT NULL'
            );
            $st->execute([':t' => $torneoId, ':a' => $asociacionId]);
            $d = $st->fetchColumn();
            if ($d !== false && $d !== null && trim((string) $d) !== '') {
                return substr((string) $d, 0, 10);
            }
        } catch (\Throwable $e) {
            // tabla ausente u otro error: seguir con notificaciones
        }

        try {
            DelegadoTorneoNotifService::ensureTable($pdo);
            $st2 = $pdo->prepare(
                'SELECT DATE(MIN(n.creado_en)) AS d
                 FROM fvd_delegado_notif_torneo n
                 INNER JOIN delegados d ON d.id = n.delegado_id AND d.asociacion_id = :a
                 WHERE n.torneo_id = :t'
            );
            $st2->execute([':t' => $torneoId, ':a' => $asociacionId]);
            $d2 = $st2->fetchColumn();
            if ($d2 !== false && $d2 !== null && trim((string) $d2) !== '') {
                return substr((string) $d2, 0, 10);
            }
        } catch (\Throwable $e) {
            error_log('[DelegadoTorneoVentanasService] fechaInicioGestionPorInvitacion notif: ' . $e->getMessage());
        }

        return null;
    }

    /**
     * @return array{
     *   torneo_id:int,
     *   fechator:?string,
     *   hoy:string,
     *   fase1_afiliados_carnets_traspasos:bool,
     *   fase2_inscripciones:bool,
     *   solo_consulta_y_pagos:bool,
     *   etiqueta_fase:string,
     *   gestion_admin_desde_invitacion:bool
     * }
     */
    public static function estadoParaTorneo(PDO $pdo, int $torneoId, ?int $asociacionId = null): array
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
            'gestion_admin_desde_invitacion' => false,
        ];

        if ($fechator === null || $fechator === '') {
            return self::aplicarOverrideModoPruebas($base);
        }

        try {
            $tTor = new DateTimeImmutable($fechator . ' 00:00:00', $tz);
        } catch (\Exception $e) {
            return self::aplicarOverrideModoPruebas($base);
        }

        $d15 = $tTor->modify('-' . self::DIA_FASE1_INICIO . ' days')->setTime(0, 0, 0);
        $d7 = $tTor->modify('-' . self::DIA_FASE1_FIN . ' days')->setTime(0, 0, 0);
        $d3 = $tTor->modify('-' . self::DIA_FASE2_FIN . ' days')->setTime(0, 0, 0);

        $inFase1Cal = $today >= $d15 && $today <= $d7;
        $inFase2 = $today >= $d7 && $today <= $d3;

        $inFase1 = $inFase1Cal;
        $gestionInv = false;

        if ($asociacionId !== null && $asociacionId > 0 && self::aplicaRestriccionDelegado()) {
            $invStr = self::fechaInicioGestionPorInvitacion($pdo, $torneoId, $asociacionId);
            if ($invStr !== null) {
                try {
                    $invDt = new DateTimeImmutable($invStr . ' 00:00:00', $tz);
                    $gestionInv = true;
                    if ($invDt <= $d7) {
                        $inFase1Inv = $today >= $invDt && $today <= $d7;
                    } else {
                        // Invitación recibida dentro del tramo final: permitir alta/carnets/traspasos hasta el cierre de inscripciones
                        $inFase1Inv = $today >= $invDt && $today <= $d3;
                    }
                    $inFase1 = $inFase1 || $inFase1Inv;
                } catch (\Exception $e) {
                    // mantener solo calendario
                }
            }
        }

        $soloConsulta = !$inFase1 && !$inFase2;

        $etiqueta = 'Periodo cerrado: solo consulta y pagos.';
        if ($gestionInv && $inFase1 && !$inFase1Cal && $inFase2) {
            $etiqueta = 'Acceso por convocatoria/invitación: afiliaciones, carnets y traspasos; e inscripciones al torneo (fase 2).';
        } elseif ($gestionInv && $inFase1 && !$inFase1Cal) {
            $etiqueta = 'Acceso por convocatoria/invitación: puede registrar afiliados, solicitar carnets y traspasos según el calendario del torneo.';
        } elseif ($inFase1 && $inFase2) {
            $etiqueta = 'Fase 1 y 2 (gestión ampliada): hasta el día ' . self::DIA_FASE2_FIN . ' antes del evento.';
        } elseif ($inFase1) {
            $etiqueta = 'Fase 1: afiliaciones, carnets y traspasos (hasta ' . self::DIA_FASE1_FIN . ' días antes del torneo).';
        } elseif ($inFase2) {
            $etiqueta = 'Fase 2: inscripciones, retiros y cambios (hasta ' . self::DIA_FASE2_FIN . ' días antes del torneo).';
        }

        return self::aplicarOverrideModoPruebas([
            'torneo_id' => $torneoId,
            'fechator' => $fechator,
            'hoy' => $today->format('Y-m-d'),
            'fase1_afiliados_carnets_traspasos' => $inFase1,
            'fase2_inscripciones' => $inFase2,
            'solo_consulta_y_pagos' => $soloConsulta,
            'etiqueta_fase' => $etiqueta,
            'gestion_admin_desde_invitacion' => $gestionInv && $inFase1 && !$inFase1Cal,
        ]);
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

    public static function ensureFechaLimiteCambiosColumn(PDO $pdo): void
    {
        try {
            $pdo->exec(
                'ALTER TABLE torneosact ADD COLUMN fecha_limite_cambios date DEFAULT NULL COMMENT \'Tras esta fecha: nómina solo consulta\''
            );
        } catch (\Throwable $e) {
            if (stripos($e->getMessage(), 'Duplicate column') === false) {
                error_log('[DelegadoTorneoVentanasService] ensureFechaLimiteCambiosColumn: ' . $e->getMessage());
            }
        }
    }

    /**
     * Si el torneo define `fecha_limite_cambios` y hoy es posterior, la nómina (inscripciones/cambios) pasa a solo consulta.
     */
    public static function fechaLimiteCambiosNominaSuperada(PDO $pdo, int $torneoId): bool
    {
        if ($torneoId <= 0) {
            return false;
        }
        if (self::delegadoOmiteRestriccionVentanas()) {
            return false;
        }
        self::ensureFechaLimiteCambiosColumn($pdo);
        try {
            $st = $pdo->prepare('SELECT fecha_limite_cambios FROM torneosact WHERE torneo = :t LIMIT 1');
            $st->execute([':t' => $torneoId]);
            $raw = $st->fetchColumn();
            if ($raw === false || $raw === null || trim((string) $raw) === '') {
                return false;
            }
            $lim = substr((string) $raw, 0, 10);
            $tz = self::timezoneApp();
            $today = (new \DateTimeImmutable('today', $tz))->format('Y-m-d');

            return $today > $lim;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private static function delegadoAsociacionParaVentana(): ?int
    {
        if (!self::aplicaRestriccionDelegado()) {
            return null;
        }
        $a = \AuthService::idAsociacion();

        return $a !== null && (int) $a > 0 ? (int) $a : null;
    }

    public static function assertPuedeFase1Administrativa(PDO $pdo, int $torneoId): void
    {
        if (!self::aplicaRestriccionDelegado()) {
            return;
        }
        if (self::delegadoOmiteRestriccionVentanas()) {
            return;
        }
        $st = self::estadoParaTorneo($pdo, $torneoId, self::delegadoAsociacionParaVentana());
        if (!$st['fase1_afiliados_carnets_traspasos']) {
            throw new RuntimeException(
                'La fase 1 (afiliaciones, carnets y traspasos) está habilitada en la ventana habitual ('
                . self::DIA_FASE1_INICIO . '–' . self::DIA_FASE1_FIN . ' días antes del torneo) o desde la fecha en que su asociación figure en la convocatoria / reciba la invitación en el panel. '
                . 'Fuera de ese periodo solo puede consultar y registrar pagos.'
            );
        }
    }

    public static function assertPuedeInscripcionesRetiros(PDO $pdo, int $torneoId): void
    {
        if (!self::aplicaRestriccionDelegado()) {
            return;
        }
        if (self::delegadoOmiteRestriccionVentanas()) {
            return;
        }
        if (self::fechaLimiteCambiosNominaSuperada($pdo, $torneoId)) {
            throw new RuntimeException(
                'La fecha límite de cambios de nómina para este torneo ya ha pasado. Solo puede consultar la información; no se permiten inscripciones ni modificaciones de plantilla.'
            );
        }
        $st = self::estadoParaTorneo($pdo, $torneoId, self::delegadoAsociacionParaVentana());
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
        if (self::delegadoOmiteRestriccionVentanas()) {
            return;
        }
        $st = self::estadoParaTorneo($pdo, $torneoId, self::delegadoAsociacionParaVentana());
        if (self::fechaLimiteCambiosNominaSuperada($pdo, $torneoId) && $st['fase2_inscripciones']) {
            throw new RuntimeException(
                'La fecha límite de cambios de nómina para este torneo ya ha pasado. Solo puede consultar la plantilla; no modificar inscripciones ni bajas de nómina.'
            );
        }
        if (!$st['fase1_afiliados_carnets_traspasos'] && !$st['fase2_inscripciones']) {
            throw new RuntimeException(
                'El periodo de gestión de atletas para este torneo ha finalizado. Solo puede consultar información y registrar pagos.'
            );
        }
    }
}
