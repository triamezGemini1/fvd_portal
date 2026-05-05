<?php

declare(strict_types=1);

namespace FvdPortal\Services;

use DateTimeImmutable;
use PDO;
use PDOException;

/**
 * Contexto de torneo / grupo (campeonato vinculado) para el panel maestro y finanzas consolidadas.
 */
final class MasterPanelContextService
{
    /** Último torneo elegido en el panel maestro (admin gral), para reentrada sin URL. */
    public const SESSION_MASTER_PANEL_CTX_TORNEO = 'fvd_master_panel_ctx_torneo';

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

    /**
     * Normaliza {@see torneosact.fechator} a Y-m-d o null.
     */
    public static function normalizarFechator(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }
        $s = substr(trim((string) $raw), 0, 10);

        return $s !== '' ? $s : null;
    }

    /**
     * Excluye torneos cuya fecha del evento ({@see fechator}) es estrictamente anterior a hoy.
     * Sin fecha se consideran vigentes. Si el filtro deja la lista vacía, se devuelve $rows intacto.
     *
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    public static function filterContextTorneosFechaNoPasada(array $rows): array
    {
        if ($rows === []) {
            return [];
        }
        $today = (new DateTimeImmutable('today'))->format('Y-m-d');
        $out = [];
        foreach ($rows as $r) {
            $f = self::normalizarFechator(isset($r['fechator']) ? (string) $r['fechator'] : null);
            if ($f === null || $f >= $today) {
                $out[] = $r;
            }
        }

        return $out !== [] ? $out : $rows;
    }

    /**
     * Torneo ancla por defecto al abrir el panel: prioriza eventos con grupo y fecha reciente.
     *
     * @return int ID de {@see torneosact.torneo} o 0
     */
    public static function resolveDefaultAnchorTorneoId(PDO $pdo): int
    {
        try {
            if (self::grupoEventoColumnExists($pdo)) {
                $st = $pdo->query(
                    'SELECT torneo FROM torneosact
                     WHERE COALESCE(grupo_evento_id, 0) > 0
                     ORDER BY COALESCE(fechator, \'1970-01-01\') DESC, torneo DESC
                     LIMIT 1'
                );
                if ($st !== false) {
                    $tid = (int) $st->fetchColumn();
                    if ($tid > 0) {
                        return $tid;
                    }
                }
            }
            $st2 = $pdo->query(
                'SELECT torneo FROM torneosact
                 ORDER BY COALESCE(fechator, \'1970-01-01\') DESC, torneo DESC
                 LIMIT 1'
            );
            if ($st2 !== false) {
                $tid2 = (int) $st2->fetchColumn();

                return $tid2 > 0 ? $tid2 : 0;
            }
        } catch (PDOException $e) {
            error_log('[MasterPanelContextService::resolveDefaultAnchorTorneoId] ' . $e->getMessage());
        }

        return 0;
    }

    /**
     * Torneos del mismo {@see grupo_evento_id} que el ancla, o solo el ancla si no tiene grupo.
     * Lista vacía si no hay ancla o no existe la fila en {@see torneosact}.
     *
     * @return list<array{torneo:int,nombre:string,grupo_evento_id:int,grupo_label:string,fechator:?string}>
     */
    public static function listTorneosDelGrupoAncla(PDO $pdo, int $anchorTorneoId): array
    {
        if ($anchorTorneoId <= 0) {
            return [];
        }
        if (!self::grupoEventoColumnExists($pdo)) {
            try {
                $st = $pdo->prepare('SELECT torneo, nombre, fechator FROM torneosact WHERE torneo = :t LIMIT 1');
                $st->execute([':t' => $anchorTorneoId]);
                $r = $st->fetch(PDO::FETCH_ASSOC);
                if (!is_array($r)) {
                    return [];
                }

                return [[
                    'torneo' => (int) ($r['torneo'] ?? 0),
                    'nombre' => trim((string) ($r['nombre'] ?? '')),
                    'grupo_evento_id' => 0,
                    'grupo_label' => 'Torneo',
                    'fechator' => self::normalizarFechator(isset($r['fechator']) ? (string) $r['fechator'] : null),
                ]];
            } catch (PDOException $e) {
                error_log('[MasterPanelContextService::listTorneosDelGrupoAncla] ' . $e->getMessage());

                return [];
            }
        }
        try {
            $st = $pdo->prepare(
                'SELECT t.torneo, t.nombre, t.fechator, COALESCE(t.grupo_evento_id, 0) AS grupo_evento_id,
                        COALESCE(cg.nombre_nominal, \'\') AS nom_grupo
                 FROM torneosact t
                 LEFT JOIN fvd_campeonato_grupo cg ON cg.grupo_evento_id = t.grupo_evento_id
                 WHERE t.torneo = :tid LIMIT 1'
            );
            $st->execute([':tid' => $anchorTorneoId]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            if (!is_array($row)) {
                return [];
            }
            $gid = (int) ($row['grupo_evento_id'] ?? 0);
            $nomG = trim((string) ($row['nom_grupo'] ?? ''));
            $labelBase = $nomG !== '' ? $nomG : ($gid > 0 ? ('Grupo #' . $gid) : 'Torneo');

            if ($gid <= 0) {
                return [[
                    'torneo' => (int) ($row['torneo'] ?? 0),
                    'nombre' => trim((string) ($row['nombre'] ?? '')),
                    'grupo_evento_id' => 0,
                    'grupo_label' => $labelBase,
                    'fechator' => self::normalizarFechator(isset($row['fechator']) ? (string) $row['fechator'] : null),
                ]];
            }

            $st2 = $pdo->prepare(
                'SELECT t.torneo, t.nombre, t.fechator, t.grupo_evento_id, COALESCE(cg.nombre_nominal, \'\') AS nom_grupo
                 FROM torneosact t
                 LEFT JOIN fvd_campeonato_grupo cg ON cg.grupo_evento_id = t.grupo_evento_id
                 WHERE t.grupo_evento_id = :g
                 ORDER BY COALESCE(t.tipo, 0) ASC, t.nombre ASC, t.torneo ASC'
            );
            $st2->execute([':g' => $gid]);
            $out = [];
            while ($r = $st2->fetch(PDO::FETCH_ASSOC)) {
                $g = (int) ($r['grupo_evento_id'] ?? 0);
                $ng = trim((string) ($r['nom_grupo'] ?? ''));
                $label = $ng !== '' ? $ng : ($g > 0 ? ('Grupo #' . $g) : 'Torneo');
                $out[] = [
                    'torneo' => (int) ($r['torneo'] ?? 0),
                    'nombre' => trim((string) ($r['nombre'] ?? '')),
                    'grupo_evento_id' => $g,
                    'grupo_label' => $label,
                    'fechator' => self::normalizarFechator(isset($r['fechator']) ? (string) $r['fechator'] : null),
                ];
            }

            return $out;
        } catch (PDOException $e) {
            try {
                $st = $pdo->prepare(
                    'SELECT torneo, nombre, fechator, COALESCE(grupo_evento_id, 0) AS grupo_evento_id FROM torneosact WHERE torneo = :tid LIMIT 1'
                );
                $st->execute([':tid' => $anchorTorneoId]);
                $row = $st->fetch(PDO::FETCH_ASSOC);
                if (!is_array($row)) {
                    return [];
                }
                $gid = (int) ($row['grupo_evento_id'] ?? 0);
                if ($gid <= 0) {
                    return [[
                        'torneo' => (int) ($row['torneo'] ?? 0),
                        'nombre' => trim((string) ($row['nombre'] ?? '')),
                        'grupo_evento_id' => 0,
                        'grupo_label' => 'Torneo',
                        'fechator' => self::normalizarFechator(isset($row['fechator']) ? (string) $row['fechator'] : null),
                    ]];
                }
                $st2 = $pdo->prepare(
                    'SELECT torneo, nombre, fechator, grupo_evento_id, \'\' AS nom_grupo FROM torneosact
                     WHERE grupo_evento_id = :g
                     ORDER BY COALESCE(tipo, 0) ASC, nombre ASC, torneo ASC'
                );
                $st2->execute([':g' => $gid]);
                $out = [];
                while ($r = $st2->fetch(PDO::FETCH_ASSOC)) {
                    $g = (int) ($r['grupo_evento_id'] ?? 0);
                    $out[] = [
                        'torneo' => (int) ($r['torneo'] ?? 0),
                        'nombre' => trim((string) ($r['nombre'] ?? '')),
                        'grupo_evento_id' => $g,
                        'grupo_label' => $g > 0 ? ('Grupo #' . $g) : 'Torneo',
                        'fechator' => self::normalizarFechator(isset($r['fechator']) ? (string) $r['fechator'] : null),
                    ];
                }

                return $out;
            } catch (PDOException $e2) {
                error_log('[MasterPanelContextService::listTorneosDelGrupoAncla] ' . $e2->getMessage());

                return [];
            }
        }
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
