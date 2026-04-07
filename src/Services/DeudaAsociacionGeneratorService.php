<?php

declare(strict_types=1);

namespace FvdPortal\Services;

use PDO;
use PDOException;
use RuntimeException;

/**
 * Genera o actualiza una fila en deuda_asociaciones a partir de atletas del club en un torneo
 * y la última fila de tarifas en costos (productos: cantidad × precio unitario).
 */
final class DeudaAsociacionGeneratorService
{
    /**
     * @return array<string, mixed>|null
     */
    public static function ultimoCosto(PDO $pdo): ?array
    {
        try {
            $st = $pdo->query('SELECT * FROM costos ORDER BY fecha DESC, id DESC LIMIT 1');
            $row = $st ? $st->fetch(PDO::FETCH_ASSOC) : false;

            return $row !== false ? $row : null;
        } catch (PDOException $e) {
            error_log('[DeudaAsociacionGeneratorService] ultimoCosto: ' . $e->getMessage());

            return null;
        }
    }

    /**
     * Conteos entre atletas de la asociación inscritos en el torneo (misma cohorte que torneo_id en ficha).
     *
     * @return array{
     *   total_inscritos:int,total_afiliados:int,total_carnets:int,total_traspasos:int,total_anualidad:int
     * }
     */
    public static function conteosPorTorneoYAsociacion(PDO $pdo, int $torneoId, int $asociacionId): array
    {
        if ($torneoId <= 0 || $asociacionId <= 0) {
            return [
                'total_inscritos' => 0,
                'total_afiliados' => 0,
                'total_carnets' => 0,
                'total_traspasos' => 0,
                'total_anualidad' => 0,
            ];
        }
        $sql = 'SELECT
            COALESCE(SUM(CASE WHEN COALESCE(a.inscripcion, 0) = 1 THEN 1 ELSE 0 END), 0) AS total_inscritos,
            COALESCE(SUM(CASE WHEN COALESCE(a.afiliacion, 0) = 1 THEN 1 ELSE 0 END), 0) AS total_afiliados,
            COALESCE(SUM(CASE WHEN COALESCE(a.carnet, 0) = 1 THEN 1 ELSE 0 END), 0) AS total_carnets,
            COALESCE(SUM(CASE WHEN COALESCE(a.traspaso, 0) = 1 THEN 1 ELSE 0 END), 0) AS total_traspasos,
            COALESCE(SUM(CASE WHEN COALESCE(a.anualidad, 0) = 1 THEN 1 ELSE 0 END), 0) AS total_anualidad
            FROM atletas a
            WHERE a.asociacion = :asoc AND a.torneo_id = :tor';
        $st = $pdo->prepare($sql);
        $st->execute([':asoc' => $asociacionId, ':tor' => $torneoId]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        if ($r === false) {
            return [
                'total_inscritos' => 0,
                'total_afiliados' => 0,
                'total_carnets' => 0,
                'total_traspasos' => 0,
                'total_anualidad' => 0,
            ];
        }

        return [
            'total_inscritos' => (int) ($r['total_inscritos'] ?? 0),
            'total_afiliados' => (int) ($r['total_afiliados'] ?? 0),
            'total_carnets'   => (int) ($r['total_carnets'] ?? 0),
            'total_traspasos' => (int) ($r['total_traspasos'] ?? 0),
            'total_anualidad' => (int) ($r['total_anualidad'] ?? 0),
        ];
    }

    /**
     * @param array<string, mixed> $costo Fila de costos (afiliacion, anualidad, carnets, traspasos, inscripciones)
     * @param array<string, int> $c Conteos desde conteosPorTorneoYAsociacion
     *
     * @return array{
     *   monto_inscritos:float,monto_afiliados:float,monto_carnets:float,monto_traspasos:float,monto_anualidad:float,monto_total:float
     * }
     */
    public static function calcularMontos(array $costo, array $c): array
    {
        $puIns = (float) ($costo['inscripciones'] ?? 0);
        $puAfi = (float) ($costo['afiliacion'] ?? 0);
        $puCar = (float) ($costo['carnets'] ?? 0);
        $puTra = (float) ($costo['traspasos'] ?? 0);
        $puAnu = (float) ($costo['anualidad'] ?? 0);

        $mi = round($c['total_inscritos'] * $puIns, 2);
        $ma = round($c['total_afiliados'] * $puAfi, 2);
        $mc = round($c['total_carnets'] * $puCar, 2);
        $mt = round($c['total_traspasos'] * $puTra, 2);
        $man = round($c['total_anualidad'] * $puAnu, 2);
        $tot = round($mi + $ma + $mc + $mt + $man, 2);

        return [
            'monto_inscritos' => $mi,
            'monto_afiliados' => $ma,
            'monto_carnets'   => $mc,
            'monto_traspasos' => $mt,
            'monto_anualidad' => $man,
            'monto_total'     => $tot,
        ];
    }

    public static function generarParaTorneoYAsociacion(PDO $pdo, int $torneoId, int $asociacionId): void
    {
        if ($torneoId <= 0 || $asociacionId <= 0) {
            throw new RuntimeException('Torneo y asociación son obligatorios para generar la deuda.');
        }
        $costo = self::ultimoCosto($pdo);
        if ($costo === null) {
            throw new RuntimeException('No hay tarifas en la tabla costos. Registre al menos una fila de costos.');
        }

        $c = self::conteosPorTorneoYAsociacion($pdo, $torneoId, $asociacionId);
        $m = self::calcularMontos($costo, $c);

        $sql = 'INSERT INTO deuda_asociaciones (
            torneo_id, asociacion_id,
            total_inscritos, monto_inscritos,
            total_afiliados, monto_afiliados,
            total_carnets, monto_carnets,
            monto_anualidad, total_anualidad,
            total_traspasos, monto_traspasos,
            monto_total,
            fecha_creacion, fecha_actualizacion
        ) VALUES (
            :tid, :aid,
            :ti, :mi, :ta, :ma, :tc, :mc, :man, :tan, :tt, :mtt, :mtot,
            NOW(), NOW()
        )
        ON DUPLICATE KEY UPDATE
            total_inscritos = VALUES(total_inscritos),
            monto_inscritos = VALUES(monto_inscritos),
            total_afiliados = VALUES(total_afiliados),
            monto_afiliados = VALUES(monto_afiliados),
            total_carnets = VALUES(total_carnets),
            monto_carnets = VALUES(monto_carnets),
            monto_anualidad = VALUES(monto_anualidad),
            total_anualidad = VALUES(total_anualidad),
            total_traspasos = VALUES(total_traspasos),
            monto_traspasos = VALUES(monto_traspasos),
            monto_total = VALUES(monto_total),
            fecha_actualizacion = NOW()';

        $st = $pdo->prepare($sql);
        $st->execute([
            ':tid' => $torneoId,
            ':aid' => $asociacionId,
            ':ti' => $c['total_inscritos'],
            ':mi' => $m['monto_inscritos'],
            ':ta' => $c['total_afiliados'],
            ':ma' => $m['monto_afiliados'],
            ':tc' => $c['total_carnets'],
            ':mc' => $m['monto_carnets'],
            ':man' => $m['monto_anualidad'],
            ':tan' => $c['total_anualidad'],
            ':tt' => $c['total_traspasos'],
            ':mtt' => $m['monto_traspasos'],
            ':mtot' => $m['monto_total'],
        ]);
    }
}
