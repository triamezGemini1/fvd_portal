<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/FvdModuleController.php';
require_once dirname(__DIR__, 3) . '/src/Services/FvdAdminService.php';
require_once dirname(__DIR__, 2) . '/services/AuthService.php';

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
     * Torneos para el selector: administrador FVD solo el torneo ancla (URL o contexto) y el mismo grupo_evento_id;
     * delegado/asoc solo torneos con atletas del club + contexto.
     *
     * @param int $preferTorneoId Prioridad de ancla para FVD admin: torneo_id en URL si es positivo, si no contexto de sesión.
     *
     * @return list<array{torneo:int|string,nombre:string}>
     */
    public function listTorneosParaSelector(?int $asociacionId, ?int $ctxTorneoId, int $preferTorneoId = 0): array
    {
        if (AuthService::role() === AuthService::ROLE_FVD_ADMIN) {
            $anchor = $preferTorneoId > 0
                ? $preferTorneoId
                : ($ctxTorneoId !== null && $ctxTorneoId > 0 ? $ctxTorneoId : 0);

            return $this->listTorneosFvdAdminPorGrupo($anchor);
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
     * Administrador FVD: torneo activo (ancla) y demás filas de torneosact con el mismo grupo_evento_id.
     * Sin ancla o sin fila en BD devuelve lista vacía. Si no existe grupo_evento_id en la tabla, solo el ancla.
     *
     * @return list<array{torneo:int|string,nombre:string}>
     */
    private function listTorneosFvdAdminPorGrupo(int $anchorTorneoId): array
    {
        if ($anchorTorneoId <= 0) {
            return [];
        }
        $svc = new \FvdAdminService($this->pdo);
        if (!$svc->torneosactGrupoEventoColumnExists()) {
            $one = $this->fetchTorneoActo($anchorTorneoId);

            return $one !== null ? [$one] : [];
        }
        try {
            $st = $this->pdo->prepare(
                'SELECT torneo, nombre, COALESCE(grupo_evento_id, 0) AS grupo_evento_id
                FROM torneosact WHERE torneo = :t LIMIT 1'
            );
            $st->execute([':t' => $anchorTorneoId]);
            $row = $st->fetch(\PDO::FETCH_ASSOC);
            if (!is_array($row)) {
                return [];
            }
            $gid = (int) ($row['grupo_evento_id'] ?? 0);
            if ($gid <= 0) {
                return [['torneo' => $row['torneo'], 'nombre' => (string) ($row['nombre'] ?? '')]];
            }
            $st2 = $this->pdo->prepare(
                'SELECT torneo, nombre FROM torneosact
                WHERE COALESCE(grupo_evento_id, 0) = :g
                ORDER BY COALESCE(tipo, 0) ASC, nombre ASC'
            );
            $st2->execute([':g' => $gid]);
            $rows = $st2->fetchAll(\PDO::FETCH_ASSOC);

            return is_array($rows) ? $rows : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Torneos del campeonato (grupo_evento_id) con convocatoria para la asociación del delegado.
     * Requiere {@see $campeonatoParam} resuelto a un grupo válido (ID de torneo o de grupo).
     *
     * @return list<array<string, mixed>>
     */
    public function listTorneosPorCampeonatoParaDelegado(int $asociacionId, int $campeonatoParam, ?int $contextTorneoId = null): array
    {
        if ($asociacionId <= 0 || $campeonatoParam <= 0) {
            return [];
        }
        $svc = new \FvdAdminService($this->pdo);
        $grupo = $svc->resolverGrupoDesdeCampeonatoParam($campeonatoParam);
        if ($grupo === null || $grupo <= 0) {
            return [];
        }

        $ctx = $contextTorneoId !== null && $contextTorneoId > 0
            ? $contextTorneoId
            : (int) (\AuthService::delegadoTorneoContextId() ?? 0);
        $rows = $svc->torneosPorGrupoCampeonato($asociacionId, $grupo, $ctx > 0 ? $ctx : null);
        if ($rows !== []) {
            AuthService::setDelegadoCampeonatoGrupo($grupo);
        }

        return $rows;
    }

    /**
     * Listado para reportes (maestro EUR, etc.): solo asociaciones activas, misma regla que el catálogo admin.
     *
     * @return list<array{id:int|string,nombre:string}>
     */
    public function listAsociacionesParaAdmin(): array
    {
        if (AuthService::role() !== AuthService::ROLE_FVD_ADMIN) {
            return [];
        }
        try {
            $est = \FvdAdminService::asociacionesSqlFiltroEstatus('asociaciones', 'activas');
            $st = $this->pdo->query('SELECT id, nombre FROM asociaciones WHERE 1=1' . $est . ' ORDER BY nombre');
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

    /**
     * Estadísticas y deuda para un torneo + asociación (marcas en atletas, deuda_asociaciones, pagos).
     *
     * @return array<string, mixed>|null
     */
    public function reportStatsTorneoAsociacion(int $torneoId, int $asociacionId): ?array
    {
        if ($torneoId <= 0 || $asociacionId <= 0) {
            return null;
        }
        try {
            $st = $this->pdo->prepare(
                'SELECT
                    SUM(CASE WHEN COALESCE(a.inscripcion,0)=1 THEN 1 ELSE 0 END) AS n_insc,
                    SUM(CASE WHEN COALESCE(a.carnet,0)=1 THEN 1 ELSE 0 END) AS n_carn,
                    SUM(CASE WHEN COALESCE(a.afiliacion,0)=1 THEN 1 ELSE 0 END) AS n_afi,
                    SUM(CASE WHEN COALESCE(a.anualidad,0)=1 THEN 1 ELSE 0 END) AS n_anu,
                    SUM(CASE WHEN COALESCE(a.traspaso,0)=1 THEN 1 ELSE 0 END) AS n_tras
                FROM atletas a
                WHERE a.torneo_id = :t AND a.asociacion = :a'
            );
            $st->execute([':t' => $torneoId, ':a' => $asociacionId]);
            $rowA = $st->fetch(\PDO::FETCH_ASSOC) ?: [];

            $nomAsoc = '';
            $stN = $this->pdo->prepare('SELECT nombre FROM asociaciones WHERE id = :id LIMIT 1');
            $stN->execute([':id' => $asociacionId]);
            $rN = $stN->fetch(\PDO::FETCH_ASSOC);
            if (is_array($rN)) {
                $nomAsoc = trim((string) ($rN['nombre'] ?? ''));
            }

            $montoBs = 0.0;
            $montoEur = null;
            $rowD = null;
            $stD = $this->pdo->prepare('SELECT * FROM deuda_asociaciones WHERE torneo_id = :t AND asociacion_id = :a LIMIT 1');
            $stD->execute([':t' => $torneoId, ':a' => $asociacionId]);
            $rowD = $stD->fetch(\PDO::FETCH_ASSOC);
            if (is_array($rowD)) {
                $montoBs = (float) ($rowD['monto_total'] ?? 0);
                if (isset($rowD['monto_total_eur']) && $rowD['monto_total_eur'] !== null && $rowD['monto_total_eur'] !== '') {
                    $montoEur = (float) $rowD['monto_total_eur'];
                }
            }

            $pagadoEur = 0.0;
            $stP = $this->pdo->prepare('SELECT COALESCE(SUM(monto_dolares),0) FROM relacion_pagos WHERE torneo_id = :t AND asociacion_id = :a');
            $stP->execute([':t' => $torneoId, ':a' => $asociacionId]);
            $pagadoEur = (float) $stP->fetchColumn();

            $saldoEur = null;
            if ($montoEur !== null && $montoEur > 0) {
                $saldoEur = max(0.0, round($montoEur - $pagadoEur, 2));
            }

            return [
                'torneo_id'      => $torneoId,
                'asociacion_id'  => $asociacionId,
                'asoc_nombre'    => $nomAsoc,
                'n_inscritos'    => (int) ($rowA['n_insc'] ?? 0),
                'n_carnets'      => (int) ($rowA['n_carn'] ?? 0),
                'n_afiliados'    => (int) ($rowA['n_afi'] ?? 0),
                'n_anualidad'    => (int) ($rowA['n_anu'] ?? 0),
                'n_traspasos'    => (int) ($rowA['n_tras'] ?? 0),
                'monto_total_bs' => $montoBs,
                'monto_total_eur'=> $montoEur,
                'pagado_eur'     => round($pagadoEur, 2),
                'saldo_eur'      => $saldoEur,
                'deuda'          => is_array($rowD) ? $rowD : null,
            ];
        } catch (\Throwable $e) {
            error_log('[InscripcionesController::reportStatsTorneoAsociacion] ' . $e->getMessage());

            return null;
        }
    }

    /**
     * Reparte {@see deuda_asociaciones.monto_total_eur} entre conceptos en proporción a montos Bs (referencia de tasas).
     *
     * @return array{inscripciones: float, afiliacion: float, carnets: float, traspasos: float, anualidad: float}
     */
    public function allocDeudaEurPorConcepto(array $d): array
    {
        $keys = [
            'inscripciones' => (float) ($d['monto_inscritos'] ?? 0),
            'afiliacion'    => (float) ($d['monto_afiliados'] ?? 0),
            'carnets'       => (float) ($d['monto_carnets'] ?? 0),
            'traspasos'     => (float) ($d['monto_traspasos'] ?? 0),
            'anualidad'     => (float) ($d['monto_anualidad'] ?? 0),
        ];
        $bsTotal = (float) ($d['monto_total'] ?? 0);
        $eurTotal = $d['monto_total_eur'] ?? null;
        $eur = $eurTotal !== null && $eurTotal !== '' ? (float) $eurTotal : null;
        if ($eur === null || $eur <= 0 || $bsTotal <= 0) {
            return array_fill_keys(array_keys($keys), 0.0);
        }
        $out = [];
        foreach ($keys as $k => $bs) {
            $out[$k] = round($eur * ($bs / $bsTotal), 2);
        }

        return $out;
    }

    /**
     * Recibos en EUR (monto_dolares) para un torneo + asociación.
     *
     * @return list<array<string, mixed>>
     */
    public function listPagosTorneoAsociacionEur(int $torneoId, int $asociacionId, int $limit = 200): array
    {
        if ($torneoId <= 0 || $asociacionId <= 0) {
            return [];
        }
        try {
            $st = $this->pdo->prepare(
                'SELECT r.id, r.torneo_id, r.asociacion_id, r.monto_dolares, r.monto_total, r.fecha, r.secuencia, r.tipo_pago, r.moneda, r.referencia, r.observaciones
                 FROM relacion_pagos r
                 WHERE r.torneo_id = :t AND r.asociacion_id = :a
                 ORDER BY r.fecha DESC, r.id DESC
                 LIMIT ' . max(1, $limit)
            );
            $st->execute([':t' => $torneoId, ':a' => $asociacionId]);

            return $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            error_log('[InscripcionesController::listPagosTorneoAsociacionEur] ' . $e->getMessage());

            return [];
        }
    }
}
