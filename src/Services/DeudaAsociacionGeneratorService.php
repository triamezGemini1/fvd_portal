<?php

declare(strict_types=1);

namespace FvdPortal\Services;

use PDO;
use PDOException;
use RuntimeException;

/**
 * Genera o actualiza una fila en deuda_asociaciones a partir de atletas del club en un torneo
 * y la última fila de tarifas en costos (productos: cantidad × precio unitario).
 *
 * **Actualizar deuda (estado de cuenta):** los conteos y montos se calculan siempre desde
 * la tabla **`atletas`** del club en el torneo (`torneo_id` + `asociacion`), con marca 1 en cada
 * concepto: `inscripcion`, `afiliacion`, `carnet`, `traspaso`, `anualidad` (alineado con reportes
 * de inscripciones, afiliaciones, carnets y traspasos). La tabla `inscripcion_torneo` puede existir
 * como volcado auxiliar; no sustituye a `atletas` para finanzas.
 */
final class DeudaAsociacionGeneratorService
{
    /**
     * Asegura columnas EUR en `deuda_asociaciones` (migración idempotente).
     * Sin esto, INSERT/SELECT que usan monto_total_eur fallan en bases sin el ALTER aplicado.
     */
    public static function ensureDeudaEurColumns(PDO $pdo): void
    {
        try {
            $pdo->exec(
                'ALTER TABLE `deuda_asociaciones` ADD COLUMN `monto_total_eur` DECIMAL(14, 6) NULL DEFAULT NULL COMMENT \'Deuda total en EUR\' AFTER `monto_total`'
            );
        } catch (PDOException $e) {
            $msg = $e->getMessage();
            if (stripos($msg, 'Duplicate column') === false && stripos($msg, '1060') === false) {
                error_log('[DeudaAsociacionGeneratorService] ensureDeudaEurColumns monto_total_eur: ' . $msg);
            }
        }
        try {
            $pdo->exec(
                'ALTER TABLE `deuda_asociaciones` ADD COLUMN `abono_eur` DECIMAL(14, 6) NULL DEFAULT NULL COMMENT \'Suma de pagos en EUR (monto_dolares)\' AFTER `abono`'
            );
        } catch (PDOException $e) {
            $msg = $e->getMessage();
            if (stripos($msg, 'Duplicate column') === false && stripos($msg, '1060') === false) {
                error_log('[DeudaAsociacionGeneratorService] ensureDeudaEurColumns abono_eur: ' . $msg);
            }
        }
    }

    /**
     * Columnas en `atletas` que marcan conceptos facturables (1 = aplica).
     * Deben coincidir con los precios unitarios en `costos` (misma lógica que el generador).
     *
     * @var array<string, string> columna atletas => clave de conteo interna
     */
    private const ATLETA_MARCAS_CONCEPTO = [
        'inscripcion' => 'total_inscritos',
        'afiliacion' => 'total_afiliados',
        'carnet' => 'total_carnets',
        'traspaso' => 'total_traspasos',
        'anualidad' => 'total_anualidad',
    ];

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
     * Los conteos de deuda por torneo/asociación se toman siempre de {@see conteosPorTorneoYAsociacion}
     * sobre **`atletas`**. Se mantiene el método por compatibilidad; ya no delega en `inscripcion_torneo`.
     */
    public static function conteosUsanTablaInscripcionTorneo(PDO $pdo): bool
    {
        return false;
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
        $emptyCounts = [
            'total_inscritos' => 0,
            'total_afiliados' => 0,
            'total_carnets' => 0,
            'total_traspasos' => 0,
            'total_anualidad' => 0,
        ];
        if ($torneoId <= 0 || $asociacionId <= 0) {
            return $emptyCounts;
        }

        if (self::conteosUsanTablaInscripcionTorneo($pdo)) {
            return self::conteosPorTorneoYAsociacionDesdeInscripcionTorneo($pdo, $torneoId, $asociacionId);
        }

        $selects = [];
        foreach (self::ATLETA_MARCAS_CONCEPTO as $colAtleta => $alias) {
            if ($colAtleta === 'anualidad') {
                $selects[] = 'COALESCE(SUM(CASE WHEN COALESCE(a.anualidad, 0) = 1 AND COALESCE(a.afiliacion, 0) = 1 THEN 1 ELSE 0 END), 0) AS `' . $alias . '`';
            } elseif ($colAtleta === 'inscripcion') {
                $selects[] = 'COALESCE(SUM(CASE WHEN COALESCE(a.inscripcion, 0) = 1 AND COALESCE(a.afiliacion, 0) = 0 THEN 1 ELSE 0 END), 0) AS `' . $alias . '`';
            } else {
                $selects[] = 'COALESCE(SUM(CASE WHEN COALESCE(a.`' . $colAtleta . '`, 0) = 1 THEN 1 ELSE 0 END), 0) AS `' . $alias . '`';
            }
        }
        $sql = 'SELECT ' . implode(",\n            ", $selects) . '
            FROM atletas a
            WHERE a.asociacion = :asoc AND a.torneo_id = :tor';
        $st = $pdo->prepare($sql);
        $st->execute([':asoc' => $asociacionId, ':tor' => $torneoId]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        if ($r === false) {
            return $emptyCounts;
        }

        $out = [];
        foreach (self::ATLETA_MARCAS_CONCEPTO as $aliasResultado) {
            $out[$aliasResultado] = (int) ($r[$aliasResultado] ?? 0);
        }

        return $out;
    }

    /**
     * Misma lógica de conceptos que en `atletas`, aplicada a filas de `inscripcion_torneo`.
     * Columna `inscripcion` en la tabla: 1 = confirmado en sitio, 2 = movimiento (ambos cuentan como inscrito al torneo).
     *
     * @return array{
     *   total_inscritos:int,total_afiliados:int,total_carnets:int,total_traspasos:int,total_anualidad:int
     * }
     */
    public static function conteosPorTorneoYAsociacionDesdeInscripcionTorneo(PDO $pdo, int $torneoId, int $asociacionId): array
    {
        $emptyCounts = [
            'total_inscritos' => 0,
            'total_afiliados' => 0,
            'total_carnets' => 0,
            'total_traspasos' => 0,
            'total_anualidad' => 0,
        ];
        if ($torneoId <= 0 || $asociacionId <= 0) {
            return $emptyCounts;
        }

        $sql = 'SELECT
            COALESCE(SUM(CASE WHEN COALESCE(it.inscripcion, 0) IN (1, 2) AND COALESCE(it.afiliacion, 0) = 0 THEN 1 ELSE 0 END), 0) AS total_inscritos,
            COALESCE(SUM(CASE WHEN COALESCE(it.afiliacion, 0) = 1 THEN 1 ELSE 0 END), 0) AS total_afiliados,
            COALESCE(SUM(CASE WHEN COALESCE(it.carnet, 0) = 1 THEN 1 ELSE 0 END), 0) AS total_carnets,
            COALESCE(SUM(CASE WHEN COALESCE(it.traspaso, 0) = 1 THEN 1 ELSE 0 END), 0) AS total_traspasos,
            COALESCE(SUM(CASE WHEN COALESCE(it.anualidad, 0) = 1 AND COALESCE(it.afiliacion, 0) = 1 THEN 1 ELSE 0 END), 0) AS total_anualidad
            FROM inscripcion_torneo it
            WHERE it.torneo_id = :tor AND it.asociacion_id = :asoc';
        try {
            $st = $pdo->prepare($sql);
            $st->execute([':tor' => $torneoId, ':asoc' => $asociacionId]);
            $r = $st->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('[DeudaAsociacionGeneratorService] conteosPorTorneoYAsociacionDesdeInscripcionTorneo: ' . $e->getMessage());

            return $emptyCounts;
        }
        if ($r === false) {
            return $emptyCounts;
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

    /**
     * Recalcula totales y montos en `deuda_asociaciones` leyendo de nuevo `atletas` (marcas de concepto)
     * y precios en `costos`. Sustituye/actualiza la fila del torneo+asociación.
     */
    public static function generarParaTorneoYAsociacion(PDO $pdo, int $torneoId, int $asociacionId): void
    {
        if ($torneoId <= 0 || $asociacionId <= 0) {
            throw new RuntimeException('Torneo y asociación son obligatorios para generar la deuda.');
        }
        self::ensureDeudaEurColumns($pdo);
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
            monto_total, monto_total_eur,
            fecha_creacion, fecha_actualizacion
        ) VALUES (
            :tid, :aid,
            :ti, :mi, :ta, :ma, :tc, :mc, :man, :tan, :tt, :mtt, :mtot, :mtot_eur,
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
            monto_total_eur = VALUES(monto_total_eur),
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
            ':mtot_eur' => $m['monto_total'],
        ]);
    }
}
