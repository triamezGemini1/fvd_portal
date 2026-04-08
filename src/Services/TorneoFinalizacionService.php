<?php

declare(strict_types=1);

namespace FvdPortal\Services;

use PDO;
use PDOException;
use RuntimeException;

/**
 * Cierre de torneo: histórico de movimientos y reversión de banderas en atletas inscritos.
 */
final class TorneoFinalizacionService
{
    public static function tablaHistoricoExiste(PDO $pdo): bool
    {
        try {
            $pdo->query('SELECT 1 FROM torneo_movimiento_historico LIMIT 0');

            return true;
        } catch (PDOException $e) {
            return false;
        }
    }

    public static function columnaFinalizadoExiste(PDO $pdo): bool
    {
        try {
            $pdo->query('SELECT finalizado_en FROM torneosact LIMIT 0');

            return true;
        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * @return array{movimientos:int, participantes_bandera:int, filas_tabla:int}
     */
    public static function finalizarTorneo(PDO $pdo, int $torneoId): array
    {
        if ($torneoId <= 0) {
            throw new \InvalidArgumentException('Torneo no válido.');
        }
        if (!self::tablaHistoricoExiste($pdo) || !self::columnaFinalizadoExiste($pdo)) {
            throw new RuntimeException(
                'Ejecute en MySQL: fvdmasteradmin/sql/install_torneo_grupo_historico.sql'
            );
        }

        $stChk = $pdo->prepare('SELECT torneo, finalizado_en FROM torneosact WHERE torneo = :t LIMIT 1');
        $stChk->execute([':t' => $torneoId]);
        $trow = $stChk->fetch(PDO::FETCH_ASSOC);
        if ($trow === false) {
            throw new RuntimeException('Torneo no encontrado.');
        }
        if (!empty($trow['finalizado_en'])) {
            throw new RuntimeException('Este torneo ya figura como concluido.');
        }

        $insH = $pdo->prepare(
            'INSERT INTO torneo_movimiento_historico (torneo_id, atleta_id, numfvd, tipo, valor_anterior, valor_nuevo, notas)
             VALUES (:tor, :aid, :nf, :tipo, :va, :vn, :no)'
        );

        $mov = 0;
        $pdo->beginTransaction();
        try {
            $stA = $pdo->prepare(
                'SELECT id, numfvd, anualidad, carnet, afiliacion, traspaso, inscripcion, torneo_id
                 FROM atletas WHERE torneo_id = :t AND COALESCE(inscripcion, 0) = 1'
            );
            $stA->execute([':t' => $torneoId]);
            $banderaRows = $stA->fetchAll(PDO::FETCH_ASSOC) ?: [];
            foreach ($banderaRows as $row) {
                $aid = (int) ($row['id'] ?? 0);
                $nf = (int) ($row['numfvd'] ?? 0);
                foreach (['anualidad', 'carnet', 'afiliacion', 'traspaso'] as $campo) {
                    $v = (int) ($row[$campo] ?? 0);
                    if ($v !== 0) {
                        $insH->execute([
                            ':tor' => $torneoId,
                            ':aid' => $aid,
                            ':nf' => $nf,
                            ':tipo' => $campo,
                            ':va' => (string) $v,
                            ':vn' => '0',
                            ':no' => 'Cierre de torneo',
                        ]);
                        ++$mov;
                    }
                }
                $insH->execute([
                    ':tor' => $torneoId,
                    ':aid' => $aid,
                    ':nf' => $nf,
                    ':tipo' => 'inscripcion_bandera',
                    ':va' => '1',
                    ':vn' => '0',
                    ':no' => 'Limpieza inscripción / torneo_id',
                ]);
                ++$mov;
            }

            if ($banderaRows !== []) {
                $pdo->prepare(
                    'UPDATE atletas SET anualidad = 0, carnet = 0, afiliacion = 0, traspaso = 0, inscripcion = 0, torneo_id = 0
                     WHERE torneo_id = :t AND COALESCE(inscripcion, 0) = 1'
                )->execute([':t' => $torneoId]);
            }

            $filasTabla = 0;
            try {
                $stIt = $pdo->prepare(
                    'SELECT asociacion_id, cedula, nombre, numfvd FROM inscripcion_torneo WHERE torneo_id = :t'
                );
                $stIt->execute([':t' => $torneoId]);
                $itRows = $stIt->fetchAll(PDO::FETCH_ASSOC) ?: [];
                foreach ($itRows as $ir) {
                    $nf = (int) ($ir['numfvd'] ?? 0);
                    $ced = (string) ($ir['cedula'] ?? '');
                    $insH->execute([
                        ':tor' => $torneoId,
                        ':aid' => null,
                        ':nf' => $nf,
                        ':tipo' => 'inscripcion_tabla',
                        ':va' => $ced,
                        ':vn' => null,
                        ':no' => (string) ($ir['nombre'] ?? ''),
                    ]);
                    ++$mov;
                }
                $filasTabla = count($itRows);
                if ($filasTabla > 0) {
                    $pdo->prepare('DELETE FROM inscripcion_torneo WHERE torneo_id = :t')->execute([':t' => $torneoId]);
                }
            } catch (PDOException $e) {
                if (strpos($e->getMessage(), '1146') === false && stripos($e->getMessage(), 'doesn\'t exist') === false) {
                    throw $e;
                }
            }

            $pdo->prepare('UPDATE torneosact SET finalizado_en = CURRENT_TIMESTAMP WHERE torneo = :t')->execute([':t' => $torneoId]);
            $pdo->commit();

            return [
                'movimientos' => $mov,
                'participantes_bandera' => count($banderaRows),
                'filas_tabla' => $filasTabla,
            ];
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function listarHistorico(PDO $pdo, int $torneoId): array
    {
        if ($torneoId <= 0 || !self::tablaHistoricoExiste($pdo)) {
            return [];
        }
        $st = $pdo->prepare(
            'SELECT id, atleta_id, numfvd, tipo, valor_anterior, valor_nuevo, notas, created_at
             FROM torneo_movimiento_historico WHERE torneo_id = :t ORDER BY id ASC'
        );
        $st->execute([':t' => $torneoId]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
