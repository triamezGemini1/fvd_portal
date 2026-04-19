<?php

declare(strict_types=1);

namespace FvdPortal\Services;

use PDO;
use PDOException;

/**
 * Contexto de torneo / grupo (campeonato vinculado) para el panel maestro y finanzas consolidadas.
 */
final class MasterPanelContextService
{
    public static function grupoEventoColumnExists(PDO $pdo): bool
    {
        try {
            $pdo->query('SELECT grupo_evento_id FROM torneosact LIMIT 0');

            return true;
        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * Torneos con grupo_evento_id asignado (para selector).
     *
     * @return list<array{torneo:int,nombre:string,grupo_evento_id:int,grupo_label:string}>
     */
    public static function listTorneosConGrupo(PDO $pdo): array
    {
        if (!self::grupoEventoColumnExists($pdo)) {
            return [];
        }
        $rows = [];
        try {
            $st = $pdo->query(
                'SELECT t.torneo, t.nombre, t.grupo_evento_id,
                        COALESCE(cg.nombre_nominal, \'\') AS nom_grupo
                 FROM torneosact t
                 LEFT JOIN fvd_campeonato_grupo cg ON cg.grupo_evento_id = t.grupo_evento_id
                 WHERE t.grupo_evento_id IS NOT NULL AND t.grupo_evento_id > 0
                 ORDER BY t.grupo_evento_id ASC, t.torneo ASC'
            );
        } catch (PDOException $e1) {
            try {
                $st = $pdo->query(
                    'SELECT torneo, nombre, grupo_evento_id, \'\' AS nom_grupo FROM torneosact
                     WHERE grupo_evento_id IS NOT NULL AND grupo_evento_id > 0
                     ORDER BY grupo_evento_id ASC, torneo ASC'
                );
            } catch (PDOException $e2) {
                error_log('[MasterPanelContextService::listTorneosConGrupo] ' . $e2->getMessage());

                return [];
            }
        }
        try {
            if ($st === false) {
                return [];
            }
            while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
                $gid = (int) ($r['grupo_evento_id'] ?? 0);
                $nomG = trim((string) ($r['nom_grupo'] ?? ''));
                $label = $nomG !== '' ? $nomG : ('Grupo #' . $gid);
                $rows[] = [
                    'torneo' => (int) ($r['torneo'] ?? 0),
                    'nombre' => trim((string) ($r['nombre'] ?? '')),
                    'grupo_evento_id' => $gid,
                    'grupo_label' => $label,
                ];
            }
        } catch (PDOException $e) {
            error_log('[MasterPanelContextService::listTorneosConGrupo] ' . $e->getMessage());
        }

        return $rows;
    }

    public static function resolveGrupoFromTorneo(PDO $pdo, int $torneoId): ?int
    {
        if ($torneoId <= 0 || !self::grupoEventoColumnExists($pdo)) {
            return null;
        }
        try {
            $st = $pdo->prepare('SELECT grupo_evento_id FROM torneosact WHERE torneo = :t LIMIT 1');
            $st->execute([':t' => $torneoId]);
            $g = $st->fetchColumn();
            if ($g === false || $g === null) {
                return null;
            }
            $gi = (int) $g;

            return $gi > 0 ? $gi : null;
        } catch (PDOException $e) {
            error_log('[MasterPanelContextService::resolveGrupoFromTorneo] ' . $e->getMessage());

            return null;
        }
    }

    /**
     * Suma de pagos de todos los torneos del mismo grupo_evento_id (vista consolidada).
     *
     * @return array{sum_monto_total:float,sum_monto_dolares:float,n_registros:int}|null
     */
    public static function aggregatePagosPorGrupo(PDO $pdo, int $grupoEventoId): ?array
    {
        if ($grupoEventoId <= 0 || !self::grupoEventoColumnExists($pdo)) {
            return null;
        }
        try {
            $st = $pdo->prepare(
                'SELECT COUNT(*) AS n, COALESCE(SUM(r.monto_total), 0) AS s_bs, COALESCE(SUM(r.monto_dolares), 0) AS s_usd
                 FROM relacion_pagos r
                 INNER JOIN torneosact t ON t.torneo = r.torneo_id
                 WHERE t.grupo_evento_id = :g'
            );
            $st->execute([':g' => $grupoEventoId]);
            $row = $st->fetch(PDO::FETCH_ASSOC);

            return [
                'sum_monto_total' => (float) ($row['s_bs'] ?? 0),
                'sum_monto_dolares' => (float) ($row['s_usd'] ?? 0),
                'n_registros' => (int) ($row['n'] ?? 0),
            ];
        } catch (PDOException $e) {
            error_log('[MasterPanelContextService::aggregatePagosPorGrupo] ' . $e->getMessage());

            return null;
        }
    }
}
