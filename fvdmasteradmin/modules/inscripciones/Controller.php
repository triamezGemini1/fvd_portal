<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/FvdModuleController.php';

/**
 * Hub de reportes por torneo + asociación (PDF/HTML vía ReportService).
 */
class InscripcionesController extends FvdModuleController
{
    public function legacyGestionUrl(): string
    {
        $b = rtrim((string) env('APP_BASE_PATH', ''), '/');

        return $b . '/inscripciones/gestionar.php';
    }

    public function legacyIndexUrl(): string
    {
        $b = rtrim((string) env('APP_BASE_PATH', ''), '/');

        return $b . '/inscripciones/index.php';
    }

    /**
     * Torneos para el selector: administrador FVD ve todos; delegado/asoc solo torneos con atletas del club + contexto.
     *
     * @return list<array{torneo:int|string,nombre:string}>
     */
    public function listTorneosParaSelector(?int $asociacionId, ?int $ctxTorneoId): array
    {
        if (AuthService::role() === AuthService::ROLE_FVD_ADMIN) {
            try {
                $st = $this->pdo->query('SELECT torneo, nombre FROM torneosact ORDER BY torneo DESC LIMIT 400');
                $rows = $st ? $st->fetchAll(\PDO::FETCH_ASSOC) : [];

                return is_array($rows) ? $rows : [];
            } catch (\Throwable $e) {
                return [];
            }
        }
        if ($asociacionId === null || $asociacionId <= 0) {
            return [];
        }
        $ctx = $ctxTorneoId !== null && $ctxTorneoId > 0 ? $ctxTorneoId : 0;
        $params = [':aid' => $asociacionId, ':ctx' => $ctx];
        $sql = '(SELECT DISTINCT t.torneo, t.nombre
            FROM torneosact t
            INNER JOIN atletas a ON a.torneo_id = t.torneo AND a.asociacion = :aid
            WHERE a.torneo_id > 0)
            UNION
            (SELECT torneo, nombre FROM torneosact WHERE torneo = :ctx AND :ctx > 0)
            ORDER BY torneo DESC';
        try {
            $st = $this->pdo->prepare($sql);
            $st->execute($params);
            $rows = $st->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            return [];
        }

        return is_array($rows) ? $rows : [];
    }

    /**
     * @return list<array{id:int|string,nombre:string}>
     */
    public function listAsociacionesParaAdmin(): array
    {
        if (AuthService::role() !== AuthService::ROLE_FVD_ADMIN) {
            return [];
        }
        try {
            $st = $this->pdo->query('SELECT id, nombre FROM asociaciones ORDER BY nombre');
            $rows = $st ? $st->fetchAll(\PDO::FETCH_ASSOC) : [];

            return is_array($rows) ? $rows : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    public function reportExportUrl(string $tipo, int $torneoId, int $asociacionId): string
    {
        $q = [
            'tipo' => $tipo,
            'torneo_id' => (string) $torneoId,
            'asociacion_id' => (string) $asociacionId,
        ];

        return fvd_module_url('inscripciones/report_export.php?' . http_build_query($q));
    }

    /**
     * @return array{torneo:int|string,nombre:string}|null
     */
    public function fetchTorneoActo(int $torneoId): ?array
    {
        if ($torneoId <= 0) {
            return null;
        }
        try {
            $st = $this->pdo->prepare('SELECT torneo, nombre FROM torneosact WHERE torneo = :t LIMIT 1');
            $st->execute([':t' => $torneoId]);
            $r = $st->fetch(\PDO::FETCH_ASSOC);

            return is_array($r) ? $r : null;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
