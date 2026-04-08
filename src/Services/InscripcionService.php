<?php

declare(strict_types=1);

namespace FvdPortal\Services;

use InvalidArgumentException;
use PDO;
use PDOException;
use RuntimeException;
use Throwable;

require_once dirname(__DIR__, 2) . '/fvdmasteradmin/services/AuthService.php';
require_once __DIR__ . '/DeudaAsociacionGeneratorService.php';
require_once __DIR__ . '/DelegadoTorneoVentanasService.php';

/**
 * Reglas de inscripción por modalidad (torneosact.clase: 1=Ind, 2=Parejas, 3=Equipos).
 * Inserta en `inscripcion_torneo` (equipo > 0 agrupa pareja/equipo).
 */
final class InscripcionService
{
    public const CLASE_INDIVIDUAL = 1;

    public const CLASE_PAREJAS = 2;

    public const CLASE_EQUIPOS = 3;

    /**
     * Modo bandera: cambios en `atletas.inscripcion` / `torneo_id` sin pasar por FvdAdminService.
     * Recalcula `deuda_asociaciones` desde conteos; no debe interrumpir el flujo de inscripción.
     */
    private static function sincronizarDeudaBandera(PDO $pdo, int $torneoId, int $asociacionId): void
    {
        if ($torneoId <= 0 || $asociacionId <= 0) {
            return;
        }
        try {
            DeudaAsociacionGeneratorService::generarParaTorneoYAsociacion($pdo, $torneoId, $asociacionId);
        } catch (Throwable $e) {
            error_log('[InscripcionService] sincronizarDeudaBandera: ' . $e->getMessage());
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function torneoReglas(PDO $pdo, int $torneoId): ?array
    {
        if ($torneoId <= 0) {
            return null;
        }
        $st = $pdo->prepare(
            'SELECT torneo, nombre, clase, tipo, pareclub, estatus FROM torneosact WHERE torneo = :id LIMIT 1'
        );
        $st->execute([':id' => $torneoId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? $row : null;
    }

    public static function integrantesEquipoRequeridos(array $torneoRow): int
    {
        $cl = self::normalizarClaseTorneo($torneoRow);
        if ($cl !== self::CLASE_EQUIPOS) {
            return 0;
        }
        $pc = (int) ($torneoRow['pareclub'] ?? 0);
        if ($pc >= 2) {
            return $pc;
        }

        return 4;
    }

    public static function claseEtiqueta(int $clase): string
    {
        if ($clase === self::CLASE_PAREJAS) {
            return 'parejas';
        }
        if ($clase === self::CLASE_EQUIPOS) {
            return 'equipos';
        }

        return 'individual';
    }

    public static function normalizarClaseTorneo(?array $torneoRow): int
    {
        $cl = (int) ($torneoRow['clase'] ?? self::CLASE_INDIVIDUAL);
        if ($cl <= 0) {
            return self::CLASE_INDIVIDUAL;
        }
        if ($cl === self::CLASE_PAREJAS || $cl === self::CLASE_EQUIPOS) {
            return $cl;
        }

        return self::CLASE_INDIVIDUAL;
    }

    public static function assertConvocatoria(PDO $pdo, int $torneoId, int $asociacionId): void
    {
        $chk = $pdo->prepare(
            'SELECT 1 FROM torneo_convocatoria_asoc WHERE torneo_id = :t AND asociacion_id = :a AND invitado_en IS NOT NULL LIMIT 1'
        );
        $chk->execute([':t' => $torneoId, ':a' => $asociacionId]);
        if (!$chk->fetchColumn()) {
            throw new RuntimeException('La asociación no tiene invitación registrada para este torneo.');
        }
    }

    /**
     * Plazas usadas (filas) por la asociación en el torneo.
     */
    public static function contarPlazasUsadas(PDO $pdo, int $torneoId, int $asociacionId): int
    {
        try {
            $st = $pdo->prepare(
                'SELECT COUNT(*) FROM inscripcion_torneo WHERE torneo_id = :t AND asociacion_id = :a'
            );
            $st->execute([':t' => $torneoId, ':a' => $asociacionId]);
            $n = $st->fetchColumn();

            return $n !== false ? (int) $n : 0;
        } catch (PDOException $e) {
            if (self::pdoEsTablaOColumnaAusente($e)) {
                return 0;
            }
            throw $e;
        }
    }

    private static function pdoEsTablaOColumnaAusente(PDOException $e): bool
    {
        $em = $e->getMessage();

        return strpos($em, '42S02') !== false
            || strpos($em, '1146') !== false
            || strpos($em, '1054') !== false
            || stripos($em, "doesn't exist") !== false
            || strpos($em, 'Unknown column') !== false;
    }

    /**
     * @return array{usado: int, max: int|null, restante: int|null}
     */
    public static function estadoCupoAsociacion(PDO $pdo, int $torneoId, int $asociacionId): array
    {
        try {
            $usado = self::contarPlazasUsadas($pdo, $torneoId, $asociacionId);
            $max = self::cupoMaxDesdeConvocatoria($pdo, $torneoId, $asociacionId);
            $restante = $max === null ? null : max(0, $max - $usado);

            return ['usado' => $usado, 'max' => $max, 'restante' => $restante];
        } catch (PDOException $e) {
            if (self::pdoEsTablaOColumnaAusente($e)) {
                return ['usado' => 0, 'max' => null, 'restante' => null];
            }
            throw $e;
        }
    }

    public static function cupoMaxDesdeConvocatoria(PDO $pdo, int $torneoId, int $asociacionId): ?int
    {
        try {
            $st = $pdo->prepare(
                'SELECT cupo_max_inscripciones FROM torneo_convocatoria_asoc WHERE torneo_id = :t AND asociacion_id = :a LIMIT 1'
            );
            $st->execute([':t' => $torneoId, ':a' => $asociacionId]);
            $v = $st->fetchColumn();
            if ($v === false || $v === null) {
                return null;
            }
            $n = (int) $v;

            return $n > 0 ? $n : null;
        } catch (PDOException $e) {
            if (self::pdoEsTablaOColumnaAusente($e)) {
                return null;
            }
            throw $e;
        }
    }

    public static function assertCupoParaNuevasPlazas(PDO $pdo, int $torneoId, int $asociacionId, int $nuevasFilas): void
    {
        if ($nuevasFilas <= 0) {
            return;
        }
        $max = self::cupoMaxDesdeConvocatoria($pdo, $torneoId, $asociacionId);
        if ($max === null) {
            return;
        }
        $usado = self::contarPlazasUsadas($pdo, $torneoId, $asociacionId);
        if ($usado + $nuevasFilas > $max) {
            throw new InvalidArgumentException(
                'Cupo insuficiente: quedan ' . max(0, $max - $usado) . ' plazas y se solicitan ' . $nuevasFilas . '.'
            );
        }
    }

    /**
     * @param array<string, mixed> $atletaRow
     */
    public static function assertSolvenciaYActivo(array $atletaRow): void
    {
        $est = trim((string) ($atletaRow['estatus'] ?? ''));
        if ($est !== '' && strcasecmp($est, 'Suspendido') === 0) {
            throw new InvalidArgumentException('El atleta está suspendido y no puede inscribirse.');
        }
        if ($est !== '' && strcasecmp($est, 'Inactivo') === 0) {
            throw new InvalidArgumentException('El atleta está inactivo.');
        }
        $af = (int) ($atletaRow['afiliacion'] ?? 0);
        $an = (int) ($atletaRow['anualidad'] ?? 0);
        if ($af !== 1) {
            throw new InvalidArgumentException('Solvencia: afiliación no al día para inscribir.');
        }
        if ($an !== 1) {
            throw new InvalidArgumentException('Solvencia: anualidad no al día para inscribir.');
        }
    }

    public static function cedulaNumerica(array $atletaRow): int
    {
        return (int) preg_replace('/\D+/', '', (string) ($atletaRow['cedula'] ?? ''));
    }

    /**
     * @return array<string, mixed>
     */
    public static function atletaEnAsociacion(PDO $pdo, int $atletaId, int $asociacionId): array
    {
        $st = $pdo->prepare(
            'SELECT id, cedula, nombre, sexo, numfvd, asociacion, estatus, afiliacion, anualidad, inscripcion, torneo_id FROM atletas WHERE id = :id AND asociacion = :asoc LIMIT 1'
        );
        $st->execute([':id' => $atletaId, ':asoc' => $asociacionId]);
        $a = $st->fetch(PDO::FETCH_ASSOC);
        if ($a === false) {
            throw new InvalidArgumentException('El atleta no pertenece a su asociación o no existe.');
        }

        return $a;
    }

    public static function assertNoDuplicadoCedulaEnTorneo(PDO $pdo, int $torneoId, int $asociacionId, int $cedulaNum): void
    {
        if ($cedulaNum <= 0) {
            throw new InvalidArgumentException('Cédula numérica inválida para inscripción.');
        }
        $st = $pdo->prepare(
            'SELECT 1 FROM inscripcion_torneo WHERE torneo_id = :t AND asociacion_id = :a AND cedula = :c LIMIT 1'
        );
        $st->execute([':t' => $torneoId, ':a' => $asociacionId, ':c' => $cedulaNum]);
        if ($st->fetchColumn()) {
            throw new InvalidArgumentException('Esta cédula ya está inscrita en el torneo para su asociación.');
        }
    }

    /**
     * Fila existente por cédula o null.
     *
     * @return array{cedula:int, equipo:int}|null
     */
    public static function filaInscripcionPorCedula(PDO $pdo, int $torneoId, int $asociacionId, int $cedulaNum): ?array
    {
        $st = $pdo->prepare(
            'SELECT cedula, equipo FROM inscripcion_torneo WHERE torneo_id = :t AND asociacion_id = :a AND cedula = :c LIMIT 1'
        );
        $st->execute([':t' => $torneoId, ':a' => $asociacionId, ':c' => $cedulaNum]);
        $r = $st->fetch(PDO::FETCH_ASSOC);

        return $r !== false ? ['cedula' => (int) $r['cedula'], 'equipo' => (int) ($r['equipo'] ?? 0)] : null;
    }

    public static function validarIndividual(PDO $pdo, int $atletaId, int $torneoId, int $asociacionId): void
    {
        if (!\AuthService::canManageAsociacion($asociacionId)) {
            throw new RuntimeException('No puede inscribir para otra asociación.');
        }
        $torneo = self::torneoReglas($pdo, $torneoId);
        if ($torneo === null) {
            throw new InvalidArgumentException('Torneo no encontrado.');
        }
        $cl = self::normalizarClaseTorneo($torneo);
        if ($cl !== self::CLASE_INDIVIDUAL) {
            throw new InvalidArgumentException('Este torneo no es de modalidad individual.');
        }
        self::assertConvocatoria($pdo, $torneoId, $asociacionId);
        $row = self::atletaEnAsociacion($pdo, $atletaId, $asociacionId);
        self::assertSolvenciaYActivo($row);
        self::assertNoDuplicadoCedulaEnTorneo($pdo, $torneoId, $asociacionId, self::cedulaNumerica($row));
    }

    public static function validarPareja(PDO $pdo, int $atleta1Id, int $atleta2Id, int $torneoId, int $asociacionId): void
    {
        if (!\AuthService::canManageAsociacion($asociacionId)) {
            throw new RuntimeException('No puede inscribir para otra asociación.');
        }
        if ($atleta1Id === $atleta2Id) {
            throw new InvalidArgumentException('Debe elegir dos atletas distintos.');
        }
        $torneo = self::torneoReglas($pdo, $torneoId);
        if ($torneo === null) {
            throw new InvalidArgumentException('Torneo no encontrado.');
        }
        $cl = self::normalizarClaseTorneo($torneo);
        if ($cl !== self::CLASE_PAREJAS) {
            throw new InvalidArgumentException('Este torneo no es de modalidad parejas.');
        }
        self::assertConvocatoria($pdo, $torneoId, $asociacionId);
        $r1 = self::atletaEnAsociacion($pdo, $atleta1Id, $asociacionId);
        $r2 = self::atletaEnAsociacion($pdo, $atleta2Id, $asociacionId);
        self::assertSolvenciaYActivo($r1);
        self::assertSolvenciaYActivo($r2);
        $c1 = self::cedulaNumerica($r1);
        $c2 = self::cedulaNumerica($r2);
        if ($c1 <= 0 || $c2 <= 0) {
            throw new InvalidArgumentException('Ambos atletas deben tener cédula numérica válida.');
        }
        $f1 = self::filaInscripcionPorCedula($pdo, $torneoId, $asociacionId, $c1);
        $f2 = self::filaInscripcionPorCedula($pdo, $torneoId, $asociacionId, $c2);
        if ($f1 === null && $f2 === null) {
            return;
        }
        if ($f1 === null xor $f2 === null) {
            throw new InvalidArgumentException('Un integrante ya figura inscrito y el otro no: regularice la pareja en el torneo.');
        }
        $e1 = $f1['equipo'];
        $e2 = $f2['equipo'];
        if ($e1 === 0 || $e2 === 0) {
            throw new InvalidArgumentException('Un integrante ya está inscrito en modalidad individual; no puede formar pareja con otro.');
        }
        if ($e1 !== $e2) {
            throw new InvalidArgumentException('Los atletas ya están inscritos con otros compañeros en este torneo.');
        }
    }

    /**
     * @param list<int> $atletasIds
     */
    public static function validarEquipo(PDO $pdo, array $atletasIds, int $torneoId, int $asociacionId): void
    {
        if (!\AuthService::canManageAsociacion($asociacionId)) {
            throw new RuntimeException('No puede inscribir para otra asociación.');
        }
        $torneo = self::torneoReglas($pdo, $torneoId);
        if ($torneo === null) {
            throw new InvalidArgumentException('Torneo no encontrado.');
        }
        $cl = self::normalizarClaseTorneo($torneo);
        if ($cl !== self::CLASE_EQUIPOS) {
            throw new InvalidArgumentException('Este torneo no es de modalidad equipos.');
        }
        $need = self::integrantesEquipoRequeridos($torneo);
        if (count($atletasIds) !== $need) {
            throw new InvalidArgumentException("El reglamento exige {$need} integrantes en este torneo.");
        }
        $uniq = [];
        foreach ($atletasIds as $id) {
            $id = (int) $id;
            if ($id <= 0) {
                throw new InvalidArgumentException('Identificador de atleta no válido.');
            }
            if (isset($uniq[$id])) {
                throw new InvalidArgumentException('No repita atletas en el mismo equipo.');
            }
            $uniq[$id] = true;
        }
        self::assertConvocatoria($pdo, $torneoId, $asociacionId);
        $cedulas = [];
        foreach (array_keys($uniq) as $aid) {
            $row = self::atletaEnAsociacion($pdo, (int) $aid, $asociacionId);
            self::assertSolvenciaYActivo($row);
            $c = self::cedulaNumerica($row);
            if ($c <= 0) {
                throw new InvalidArgumentException('Todos los integrantes deben tener cédula numérica válida.');
            }
            $cedulas[] = $c;
        }
        foreach ($cedulas as $c) {
            $ex = self::filaInscripcionPorCedula($pdo, $torneoId, $asociacionId, $c);
            if ($ex !== null) {
                throw new InvalidArgumentException('Uno o más integrantes ya están inscritos en este torneo.');
            }
        }
    }

    public static function siguienteNumeroEquipo(PDO $pdo, int $torneoId, int $asociacionId): int
    {
        $st = $pdo->prepare(
            'SELECT COALESCE(MAX(equipo), 0) FROM inscripcion_torneo WHERE torneo_id = :t AND asociacion_id = :a'
        );
        $st->execute([':t' => $torneoId, ':a' => $asociacionId]);
        $m = (int) $st->fetchColumn();

        return max(1, $m + 1);
    }

    /**
     * @param list<int> $atletaIds
     */
    public static function insertarFilas(PDO $pdo, int $torneoId, int $asociacionId, array $atletaIds, int $equipo): int
    {
        $ins = $pdo->prepare(
            'INSERT INTO inscripcion_torneo (asociacion_id, torneo_id, equipo, cedula, nombre, numfvd, sexo, inscripcion)
            VALUES (:asoc, :tor, :eq, :ced, :nom, :nf, :sx, 1)'
        );
        $n = 0;
        foreach ($atletaIds as $rawId) {
            $id = (int) $rawId;
            if ($id <= 0) {
                continue;
            }
            $st = $pdo->prepare('SELECT id, cedula, nombre, sexo, numfvd FROM atletas WHERE id = :id AND asociacion = :asoc');
            $st->execute([':id' => $id, ':asoc' => $asociacionId]);
            $a = $st->fetch(PDO::FETCH_ASSOC);
            if ($a === false) {
                continue;
            }
            $ced = self::cedulaNumerica($a);
            if ($ced <= 0) {
                continue;
            }
            $sxRaw = strtoupper(trim((string) ($a['sexo'] ?? '')));
            $sx = $sxRaw === 'F' || $sxRaw === '2' ? 2 : 1;
            try {
                $ins->execute([
                    ':asoc' => $asociacionId,
                    ':tor' => $torneoId,
                    ':eq' => $equipo,
                    ':ced' => $ced,
                    ':nom' => (string) ($a['nombre'] ?? ''),
                    ':nf' => (int) ($a['numfvd'] ?? 0),
                    ':sx' => $sx,
                ]);
                ++$n;
            } catch (PDOException $e) {
                $em = $e->getMessage();
                if (strpos($em, 'Duplicate') !== false || strpos($em, '1062') !== false) {
                    continue;
                }
                throw $e;
            }
        }

        return $n;
    }

    /**
     * @param list<int> $atletaIds
     *
     * @return int Número de filas insertadas
     */
    public static function registrarInscripcion(PDO $pdo, int $torneoId, int $asociacionId, string $tipo, array $atletaIds): int
    {
        if (DelegadoTorneoVentanasService::aplicaRestriccionDelegado()) {
            DelegadoTorneoVentanasService::assertPuedeInscripcionesRetiros($pdo, $torneoId);
        }
        $tipo = strtolower(trim($tipo));
        if (!in_array($tipo, ['individual', 'pareja', 'equipo'], true)) {
            throw new InvalidArgumentException('Tipo de inscripción no válido.');
        }
        $atletaIds = array_values(array_unique(array_filter(array_map('intval', $atletaIds), static function (int $x): bool {
            return $x > 0;
        })));

        if ($tipo === 'individual') {
            if (count($atletaIds) !== 1) {
                throw new InvalidArgumentException('Modalidad individual: seleccione exactamente un atleta.');
            }
            self::validarIndividual($pdo, $atletaIds[0], $torneoId, $asociacionId);
            self::assertCupoParaNuevasPlazas($pdo, $torneoId, $asociacionId, 1);

            return self::insertarFilas($pdo, $torneoId, $asociacionId, $atletaIds, 0);
        }

        if ($tipo === 'pareja') {
            if (count($atletaIds) !== 2) {
                throw new InvalidArgumentException('Modalidad parejas: seleccione exactamente dos atletas.');
            }
            self::validarPareja($pdo, $atletaIds[0], $atletaIds[1], $torneoId, $asociacionId);
            $f1 = self::filaInscripcionPorCedula(
                $pdo,
                $torneoId,
                $asociacionId,
                self::cedulaNumerica(self::atletaEnAsociacion($pdo, $atletaIds[0], $asociacionId))
            );
            if ($f1 !== null) {
                return 0;
            }
            self::assertCupoParaNuevasPlazas($pdo, $torneoId, $asociacionId, 2);
            $eq = self::siguienteNumeroEquipo($pdo, $torneoId, $asociacionId);
            $pdo->beginTransaction();
            try {
                $n = self::insertarFilas($pdo, $torneoId, $asociacionId, $atletaIds, $eq);
                $pdo->commit();

                return $n;
            } catch (Throwable $e) {
                $pdo->rollBack();
                throw $e;
            }
        }

        $torneo = self::torneoReglas($pdo, $torneoId);
        if ($torneo === null) {
            throw new InvalidArgumentException('Torneo no encontrado.');
        }
        $need = self::integrantesEquipoRequeridos($torneo);
        if (count($atletaIds) !== $need) {
            throw new InvalidArgumentException("Modalidad equipos: se requieren {$need} integrantes.");
        }
        self::validarEquipo($pdo, $atletaIds, $torneoId, $asociacionId);
        self::assertCupoParaNuevasPlazas($pdo, $torneoId, $asociacionId, count($atletaIds));
        $eq = self::siguienteNumeroEquipo($pdo, $torneoId, $asociacionId);
        $pdo->beginTransaction();
        try {
            $n = self::insertarFilas($pdo, $torneoId, $asociacionId, $atletaIds, $eq);
            $pdo->commit();

            return $n;
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Lote de inscripciones individuales (checkboxes). Solo para torneos clase = 1.
     *
     * @param list<int> $atletaIds
     */
    public static function registrarMultiplesIndividuales(PDO $pdo, int $torneoId, int $asociacionId, array $atletaIds): int
    {
        if (DelegadoTorneoVentanasService::aplicaRestriccionDelegado()) {
            DelegadoTorneoVentanasService::assertPuedeInscripcionesRetiros($pdo, $torneoId);
        }
        if (!\AuthService::canManageAsociacion($asociacionId)) {
            throw new RuntimeException('No puede inscribir para otra asociación.');
        }
        $atletaIds = array_values(array_unique(array_filter(array_map('intval', $atletaIds), static function (int $x): bool {
            return $x > 0;
        })));
        if ($atletaIds === []) {
            return 0;
        }
        $torneo = self::torneoReglas($pdo, $torneoId);
        if ($torneo === null) {
            throw new InvalidArgumentException('Torneo no encontrado.');
        }
        $cl = self::normalizarClaseTorneo($torneo);
        if ($cl !== self::CLASE_INDIVIDUAL) {
            throw new InvalidArgumentException(
                'Este torneo no es individual. Use el panel de inscripción por modalidad (parejas o equipos).'
            );
        }
        self::assertConvocatoria($pdo, $torneoId, $asociacionId);
        foreach ($atletaIds as $id) {
            self::validarIndividual($pdo, (int) $id, $torneoId, $asociacionId);
        }
        self::assertCupoParaNuevasPlazas($pdo, $torneoId, $asociacionId, count($atletaIds));
        $pdo->beginTransaction();
        try {
            $n = 0;
            foreach ($atletaIds as $id) {
                $n += self::insertarFilas($pdo, $torneoId, $asociacionId, [(int) $id], 0);
            }
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        return $n;
    }

    /**
     * Plazas usadas contando atletas con inscripcion=1 y torneo_id (modo delegado / legado sin filas en inscripcion_torneo).
     */
    public static function contarPlazasUsadasBandera(PDO $pdo, int $torneoId, int $asociacionId): int
    {
        try {
            $st = $pdo->prepare(
                'SELECT COUNT(*) FROM atletas WHERE asociacion = :a AND torneo_id = :t AND COALESCE(inscripcion, 0) = 1'
            );
            $st->execute([':a' => $asociacionId, ':t' => $torneoId]);

            return (int) $st->fetchColumn();
        } catch (PDOException $e) {
            if (self::pdoEsTablaOColumnaAusente($e)) {
                return 0;
            }
            throw $e;
        }
    }

    /**
     * @return array{usado: int, max: int|null, restante: int|null}
     */
    public static function estadoCupoAsociacionBandera(PDO $pdo, int $torneoId, int $asociacionId): array
    {
        try {
            $usado = self::contarPlazasUsadasBandera($pdo, $torneoId, $asociacionId);
            $max = self::cupoMaxDesdeConvocatoria($pdo, $torneoId, $asociacionId);
            $restante = $max === null ? null : max(0, $max - $usado);

            return ['usado' => $usado, 'max' => $max, 'restante' => $restante];
        } catch (PDOException $e) {
            if (self::pdoEsTablaOColumnaAusente($e)) {
                return ['usado' => 0, 'max' => null, 'restante' => null];
            }
            throw $e;
        }
    }

    public static function assertCupoParaNuevasPlazasBandera(PDO $pdo, int $torneoId, int $asociacionId, int $nuevasPlazas): void
    {
        if ($nuevasPlazas <= 0) {
            return;
        }
        $max = self::cupoMaxDesdeConvocatoria($pdo, $torneoId, $asociacionId);
        if ($max === null) {
            return;
        }
        $usado = self::contarPlazasUsadasBandera($pdo, $torneoId, $asociacionId);
        if ($usado + $nuevasPlazas > $max) {
            throw new InvalidArgumentException(
                'Cupo insuficiente: quedan ' . max(0, $max - $usado) . ' plazas y se solicitan ' . $nuevasPlazas . '.'
            );
        }
    }

    private static function delegadoInscripcionRelajada(): bool
    {
        return \AuthService::isDelegadoAsociacion();
    }

    /**
     * Solo evita duplicado en el mismo torneo. Si venía inscrito en otro, el UPDATE de bandera reasigna torneo_id.
     *
     * @param array<string, mixed> $atletaRow
     */
    public static function assertAtletaDisponibleParaTorneoBandera(array $atletaRow, int $torneoId): void
    {
        $insc = (int) ($atletaRow['inscripcion'] ?? 0);
        $tid = (int) ($atletaRow['torneo_id'] ?? 0);
        if ($insc === 1 && $tid === $torneoId) {
            throw new InvalidArgumentException('El atleta ya figura inscrito en este torneo.');
        }
    }

    public static function validarIndividualBandera(PDO $pdo, int $atletaId, int $torneoId, int $asociacionId): void
    {
        if (!\AuthService::canManageAsociacion($asociacionId)) {
            throw new RuntimeException('No puede inscribir para otra asociación.');
        }
        $torneo = self::torneoReglas($pdo, $torneoId);
        if ($torneo === null) {
            throw new InvalidArgumentException('Torneo no encontrado.');
        }
        $cl = self::normalizarClaseTorneo($torneo);
        if ($cl !== self::CLASE_INDIVIDUAL) {
            throw new InvalidArgumentException('Este torneo no es de modalidad individual.');
        }
        self::assertConvocatoria($pdo, $torneoId, $asociacionId);
        $row = self::atletaEnAsociacion($pdo, $atletaId, $asociacionId);
        if (!self::delegadoInscripcionRelajada()) {
            self::assertSolvenciaYActivo($row);
        }
        self::assertAtletaDisponibleParaTorneoBandera($row, $torneoId);
    }

    public static function validarParejaBandera(PDO $pdo, int $atleta1Id, int $atleta2Id, int $torneoId, int $asociacionId): void
    {
        if (!\AuthService::canManageAsociacion($asociacionId)) {
            throw new RuntimeException('No puede inscribir para otra asociación.');
        }
        if ($atleta1Id === $atleta2Id) {
            throw new InvalidArgumentException('Debe elegir dos atletas distintos.');
        }
        $torneo = self::torneoReglas($pdo, $torneoId);
        if ($torneo === null) {
            throw new InvalidArgumentException('Torneo no encontrado.');
        }
        $cl = self::normalizarClaseTorneo($torneo);
        if ($cl !== self::CLASE_PAREJAS) {
            throw new InvalidArgumentException('Este torneo no es de modalidad parejas.');
        }
        self::assertConvocatoria($pdo, $torneoId, $asociacionId);
        $r1 = self::atletaEnAsociacion($pdo, $atleta1Id, $asociacionId);
        $r2 = self::atletaEnAsociacion($pdo, $atleta2Id, $asociacionId);
        if (!self::delegadoInscripcionRelajada()) {
            self::assertSolvenciaYActivo($r1);
            self::assertSolvenciaYActivo($r2);
        }
        self::assertAtletaDisponibleParaTorneoBandera($r1, $torneoId);
        self::assertAtletaDisponibleParaTorneoBandera($r2, $torneoId);
    }

    /**
     * @param list<int> $atletasIds
     */
    public static function validarEquipoBandera(PDO $pdo, array $atletasIds, int $torneoId, int $asociacionId): void
    {
        if (!\AuthService::canManageAsociacion($asociacionId)) {
            throw new RuntimeException('No puede inscribir para otra asociación.');
        }
        $torneo = self::torneoReglas($pdo, $torneoId);
        if ($torneo === null) {
            throw new InvalidArgumentException('Torneo no encontrado.');
        }
        $cl = self::normalizarClaseTorneo($torneo);
        if ($cl !== self::CLASE_EQUIPOS) {
            throw new InvalidArgumentException('Este torneo no es de modalidad equipos.');
        }
        $need = self::integrantesEquipoRequeridos($torneo);
        if (count($atletasIds) !== $need) {
            throw new InvalidArgumentException("El reglamento exige {$need} integrantes en este torneo.");
        }
        $uniq = [];
        foreach ($atletasIds as $id) {
            $id = (int) $id;
            if ($id <= 0) {
                throw new InvalidArgumentException('Identificador de atleta no válido.');
            }
            if (isset($uniq[$id])) {
                throw new InvalidArgumentException('No repita atletas en el mismo equipo.');
            }
            $uniq[$id] = true;
        }
        self::assertConvocatoria($pdo, $torneoId, $asociacionId);
        foreach (array_keys($uniq) as $aid) {
            $row = self::atletaEnAsociacion($pdo, (int) $aid, $asociacionId);
            if (!self::delegadoInscripcionRelajada()) {
                self::assertSolvenciaYActivo($row);
            }
            self::assertAtletaDisponibleParaTorneoBandera($row, $torneoId);
        }
    }

    /**
     * @param list<int> $atletaIds
     */
    public static function marcarInscripcionBandera(PDO $pdo, int $torneoId, int $asociacionId, array $atletaIds): int
    {
        if (DelegadoTorneoVentanasService::aplicaRestriccionDelegado()) {
            DelegadoTorneoVentanasService::assertPuedeInscripcionesRetiros($pdo, $torneoId);
        }
        $st = $pdo->prepare(
            'UPDATE atletas SET inscripcion = 1, torneo_id = :t WHERE id = :id AND asociacion = :a'
        );
        $n = 0;
        foreach ($atletaIds as $rawId) {
            $id = (int) $rawId;
            if ($id <= 0) {
                continue;
            }
            $st->execute([':t' => $torneoId, ':id' => $id, ':a' => $asociacionId]);
            if ($st->rowCount() > 0) {
                ++$n;
            }
        }
        if ($n > 0) {
            self::sincronizarDeudaBandera($pdo, $torneoId, $asociacionId);
        }

        return $n;
    }

    /**
     * @param list<int> $atletaIds
     */
    public static function registrarInscripcionBandera(PDO $pdo, int $torneoId, int $asociacionId, string $tipo, array $atletaIds): int
    {
        $tipo = strtolower(trim($tipo));
        if (!in_array($tipo, ['individual', 'pareja', 'equipo'], true)) {
            throw new InvalidArgumentException('Tipo de inscripción no válido.');
        }
        $atletaIds = array_values(array_unique(array_filter(array_map('intval', $atletaIds), static function (int $x): bool {
            return $x > 0;
        })));

        if ($tipo === 'individual') {
            if (count($atletaIds) !== 1) {
                throw new InvalidArgumentException('Modalidad individual: seleccione exactamente un atleta.');
            }
            self::validarIndividualBandera($pdo, $atletaIds[0], $torneoId, $asociacionId);
            if (!self::delegadoInscripcionRelajada()) {
                self::assertCupoParaNuevasPlazasBandera($pdo, $torneoId, $asociacionId, 1);
            }

            return self::marcarInscripcionBandera($pdo, $torneoId, $asociacionId, $atletaIds);
        }

        if ($tipo === 'pareja') {
            if (count($atletaIds) !== 2) {
                throw new InvalidArgumentException('Modalidad parejas: seleccione exactamente dos atletas.');
            }
            self::validarParejaBandera($pdo, $atletaIds[0], $atletaIds[1], $torneoId, $asociacionId);
            if (!self::delegadoInscripcionRelajada()) {
                self::assertCupoParaNuevasPlazasBandera($pdo, $torneoId, $asociacionId, 2);
            }
            $pdo->beginTransaction();
            try {
                $n = self::marcarInscripcionBandera($pdo, $torneoId, $asociacionId, $atletaIds);
                $pdo->commit();

                return $n;
            } catch (Throwable $e) {
                $pdo->rollBack();
                throw $e;
            }
        }

        $torneo = self::torneoReglas($pdo, $torneoId);
        if ($torneo === null) {
            throw new InvalidArgumentException('Torneo no encontrado.');
        }
        $need = self::integrantesEquipoRequeridos($torneo);
        if (count($atletaIds) !== $need) {
            throw new InvalidArgumentException("Modalidad equipos: se requieren {$need} integrantes.");
        }
        self::validarEquipoBandera($pdo, $atletaIds, $torneoId, $asociacionId);
        if (!self::delegadoInscripcionRelajada()) {
            self::assertCupoParaNuevasPlazasBandera($pdo, $torneoId, $asociacionId, count($atletaIds));
        }
        $pdo->beginTransaction();
        try {
            $n = self::marcarInscripcionBandera($pdo, $torneoId, $asociacionId, $atletaIds);
            $pdo->commit();

            return $n;
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * @param list<int> $atletaIds
     */
    public static function registrarMultiplesIndividualesBandera(PDO $pdo, int $torneoId, int $asociacionId, array $atletaIds): int
    {
        if (!\AuthService::canManageAsociacion($asociacionId)) {
            throw new RuntimeException('No puede inscribir para otra asociación.');
        }
        $atletaIds = array_values(array_unique(array_filter(array_map('intval', $atletaIds), static function (int $x): bool {
            return $x > 0;
        })));
        if ($atletaIds === []) {
            return 0;
        }
        $torneo = self::torneoReglas($pdo, $torneoId);
        if ($torneo === null) {
            throw new InvalidArgumentException('Torneo no encontrado.');
        }
        $cl = self::normalizarClaseTorneo($torneo);
        if ($cl !== self::CLASE_INDIVIDUAL) {
            throw new InvalidArgumentException(
                'Este torneo no es individual. Use el panel de inscripción por modalidad (parejas o equipos).'
            );
        }
        self::assertConvocatoria($pdo, $torneoId, $asociacionId);
        foreach ($atletaIds as $id) {
            self::validarIndividualBandera($pdo, (int) $id, $torneoId, $asociacionId);
        }
        if (!self::delegadoInscripcionRelajada()) {
            self::assertCupoParaNuevasPlazasBandera($pdo, $torneoId, $asociacionId, count($atletaIds));
        }
        $pdo->beginTransaction();
        try {
            $n = self::marcarInscripcionBandera($pdo, $torneoId, $asociacionId, $atletaIds);
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        return $n;
    }

    public static function retirarInscripcionBandera(PDO $pdo, int $torneoId, int $asociacionId, int $atletaId): bool
    {
        if (DelegadoTorneoVentanasService::aplicaRestriccionDelegado()) {
            DelegadoTorneoVentanasService::assertPuedeInscripcionesRetiros($pdo, $torneoId);
        }
        if (!\AuthService::canManageAsociacion($asociacionId)) {
            throw new RuntimeException('No puede retirar inscripciones de otra asociación.');
        }
        $st = $pdo->prepare(
            'UPDATE atletas SET inscripcion = 0, torneo_id = 0
             WHERE id = :id AND asociacion = :a AND torneo_id = :t AND COALESCE(inscripcion, 0) = 1'
        );
        $st->execute([':id' => $atletaId, ':a' => $asociacionId, ':t' => $torneoId]);
        $ok = $st->rowCount() > 0;
        if ($ok) {
            self::sincronizarDeudaBandera($pdo, $torneoId, $asociacionId);
        }

        return $ok;
    }

    /**
     * Quita una fila de inscripción individual (equipo = 0) en `inscripcion_torneo`.
     */
    public static function retirarInscripcionTablaIndividual(PDO $pdo, int $torneoId, int $asociacionId, int $cedulaNum): bool
    {
        if (DelegadoTorneoVentanasService::aplicaRestriccionDelegado()) {
            DelegadoTorneoVentanasService::assertPuedeInscripcionesRetiros($pdo, $torneoId);
        }
        if (!\AuthService::canManageAsociacion($asociacionId)) {
            throw new RuntimeException('No puede retirar inscripciones de otra asociación.');
        }
        if ($cedulaNum <= 0) {
            throw new InvalidArgumentException('Cédula no válida.');
        }
        $st = $pdo->prepare(
            'DELETE FROM inscripcion_torneo
             WHERE torneo_id = :t AND asociacion_id = :a AND cedula = :c AND equipo = 0 LIMIT 1'
        );
        $st->execute([':t' => $torneoId, ':a' => $asociacionId, ':c' => $cedulaNum]);

        return $st->rowCount() > 0;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function listarInscritosBandera(PDO $pdo, int $torneoId, int $asociacionId): array
    {
        $st = $pdo->prepare(
            'SELECT a.id, a.cedula, a.nombre, a.numfvd, a.foto, aso.nombre AS asociacion_nombre
             FROM atletas a
             LEFT JOIN asociaciones aso ON aso.id = a.asociacion
             WHERE a.asociacion = :a AND a.torneo_id = :t AND COALESCE(a.inscripcion, 0) = 1
             ORDER BY a.nombre ASC'
        );
        $st->execute([':a' => $asociacionId, ':t' => $torneoId]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public static function normalizarSexoAtletaParaSelect(mixed $sx): string
    {
        $u = strtoupper(trim((string) $sx));
        if ($u === 'F' || $u === '2' || str_contains($u, 'MUJ')) {
            return 'F';
        }
        if ($u === 'M' || $u === '1' || str_contains($u, 'HOM')) {
            return 'M';
        }

        return 'O';
    }

    /**
     * Búsqueda por cédula para flujo tipo «inscripción en sitio» (MisTorneos).
     *
     * @return array{
     *   resultado: 'ya_inscrito'|'atleta'|'no_encontrado'|'error',
     *   mensaje?: string,
     *   atleta?: array{id:int,nombre:string,cedula:string,numfvd:int,sexo:string,foto:string}
     * }
     */
    public static function buscarCedulaInscripcionSitio(
        PDO $pdo,
        int $torneoId,
        int $asociacionId,
        string $nacionalidad,
        string $cedulaDigitos,
        bool $modoBandera
    ): array {
        if ($torneoId <= 0 || $asociacionId <= 0) {
            return ['resultado' => 'error', 'mensaje' => 'Torneo o asociación no válidos.'];
        }
        $nac = strtoupper(trim($nacionalidad));
        if (!in_array($nac, ['V', 'E', 'J', 'P'], true)) {
            $nac = 'V';
        }
        $dig = preg_replace('/\D+/', '', $cedulaDigitos);
        if (strlen($dig) < 4) {
            return ['resultado' => 'error', 'mensaje' => 'Indique al menos 4 dígitos de cédula.'];
        }
        $cedInt = (int) $dig;
        if ($cedInt <= 0) {
            return ['resultado' => 'error', 'mensaje' => 'Cédula no válida.'];
        }

        if ($modoBandera) {
            $stY = $pdo->prepare(
                'SELECT 1 FROM atletas WHERE asociacion = :a AND torneo_id = :t AND COALESCE(inscripcion, 0) = 1
                 AND REPLACE(REPLACE(REPLACE(REPLACE(UPPER(TRIM(cedula)), \'V\', \'\'), \'E\', \'\'), \'J\', \'\'), \'P\', \'\') = :d
                 LIMIT 1'
            );
            $stY->execute([':a' => $asociacionId, ':t' => $torneoId, ':d' => $dig]);
            if ($stY->fetchColumn()) {
                return ['resultado' => 'ya_inscrito', 'mensaje' => 'Esta cédula ya está inscrita en este torneo.'];
            }
        } else {
            $stY = $pdo->prepare(
                'SELECT 1 FROM inscripcion_torneo WHERE torneo_id = :t AND asociacion_id = :a AND cedula = :c LIMIT 1'
            );
            $stY->execute([':t' => $torneoId, ':a' => $asociacionId, ':c' => $cedInt]);
            if ($stY->fetchColumn()) {
                return ['resultado' => 'ya_inscrito', 'mensaje' => 'Esta cédula ya está inscrita en este torneo.'];
            }
        }

        $nacDig = $nac . $dig;
        $st = $pdo->prepare(
            'SELECT id, cedula, nombre, numfvd, sexo, foto FROM atletas WHERE asociacion = :a
             AND (
                REPLACE(REPLACE(REPLACE(REPLACE(UPPER(TRIM(cedula)), \'V\', \'\'), \'E\', \'\'), \'J\', \'\'), \'P\', \'\') = :d
                OR TRIM(cedula) = :digOnly
                OR TRIM(cedula) = :nacDig
             )'
        );
        $st->execute([':a' => $asociacionId, ':d' => $dig, ':digOnly' => $dig, ':nacDig' => $nacDig]);
        $found = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        if (count($found) > 1) {
            return ['resultado' => 'error', 'mensaje' => 'Hay más de un atleta con esa cédula en el club. Corrija el maestro de atletas.'];
        }
        if ($found === []) {
            return [
                'resultado' => 'no_encontrado',
                'mensaje' => 'No hay atleta con esa cédula en su asociación. Regístrelo en Atletas y vuelva a buscar.',
            ];
        }
        $a = $found[0];

        return [
            'resultado' => 'atleta',
            'atleta' => [
                'id' => (int) ($a['id'] ?? 0),
                'nombre' => (string) ($a['nombre'] ?? ''),
                'cedula' => (string) ($a['cedula'] ?? ''),
                'numfvd' => (int) ($a['numfvd'] ?? 0),
                'sexo' => self::normalizarSexoAtletaParaSelect($a['sexo'] ?? 'M'),
                'foto' => isset($a['foto']) ? (string) $a['foto'] : '',
            ],
        ];
    }
}
