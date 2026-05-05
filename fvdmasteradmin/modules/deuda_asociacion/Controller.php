<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/FvdModuleController.php';

class DeudaAsociacionController extends FvdModuleController
{
    public const TABLE = 'deuda_asociaciones';

    private const SCOPE_COL = 'd.asociacion_id';
    private static $tieneMontoTotalEur = null;

    /** Clave de concepto (tabla costos / UI) → columna booleana en `atletas`. */
    private const CONCEPTO_ATLETA_COL = [
        'inscripciones' => 'inscripcion',
        'afiliacion' => 'afiliacion',
        'carnets' => 'carnet',
        'traspasos' => 'traspaso',
        'anualidad' => 'anualidad',
    ];

    /** @var array<string, string> Etiquetas para reporte por concepto. */
    public const ETIQUETAS_CONCEPTO = [
        'inscripciones' => 'Inscripciones',
        'afiliacion' => 'Afiliación',
        'carnets' => 'Carnets',
        'traspasos' => 'Traspasos',
        'anualidad' => 'Anualidad',
    ];

    /** @var list<string> */
    public const ALLOW_UPDATE = [
        'total_inscritos', 'monto_inscritos', 'total_afiliados', 'monto_afiliados',
        'total_carnets', 'monto_carnets', 'total_traspasos', 'monto_traspasos',
        'total_anualidad', 'monto_anualidad', 'monto_total', 'monto_total_eur',
    ];

    private static function parseNonNegativeFloat($value): float
    {
        if ($value === '' || $value === null) {
            return 0.0;
        }
        $out = (float) $value;
        if ($out < 0) {
            throw new InvalidArgumentException('No se permiten montos negativos.');
        }

        return $out;
    }

    private function deudaAsociacionesTieneMontoTotalEur(): bool
    {
        if (self::$tieneMontoTotalEur !== null) {
            return self::$tieneMontoTotalEur;
        }
        try {
            $st = $this->pdo->query("SHOW COLUMNS FROM deuda_asociaciones LIKE 'monto_total_eur'");
            $row = $st ? $st->fetch(PDO::FETCH_ASSOC) : false;
            self::$tieneMontoTotalEur = $row !== false;
        } catch (Throwable $e) {
            self::$tieneMontoTotalEur = false;
        }

        return self::$tieneMontoTotalEur;
    }

    public function paginateList(int $page, int $perPage): array
    {
        $tieneMontoTotalEur = $this->deudaAsociacionesTieneMontoTotalEur();
        $deudaEurExpr = $tieneMontoTotalEur ? 'COALESCE(d.monto_total_eur, 0)' : 'COALESCE(d.monto_total, 0)';
        $saldoExpr = $tieneMontoTotalEur
            ? 'CASE
                    WHEN COALESCE(d.monto_total_eur, 0) > 0
                        THEN GREATEST(ROUND(d.monto_total_eur - COALESCE(rp.pagado_eur, 0), 2), 0)
                    ELSE NULL
               END'
            : 'CASE
                    WHEN COALESCE(d.monto_total, 0) > 0
                        THEN GREATEST(ROUND(d.monto_total - COALESCE(rp.pagado_eur, 0), 2), 0)
                    ELSE NULL
               END';
        $params = [];
        require_once $this->projectRoot() . '/src/Services/FvdAdminService.php';
        $soloAsocActivas = \FvdAdminService::asociacionesSqlFiltroEstatus('a', 'activas');
        $countSql = 'SELECT COUNT(*) FROM deuda_asociaciones d
            LEFT JOIN asociaciones a ON d.asociacion_id = a.id
            WHERE 1=1' . $soloAsocActivas;
        $dataSql = 'SELECT d.*, t.nombre AS torneo_nombre, a.nombre AS asoc_nombre,
                ROUND(' . $deudaEurExpr . ', 2) AS monto_total_eur,
                ROUND(COALESCE(rp.pagado_eur, 0), 2) AS pagado_eur,
                ' . $saldoExpr . ' AS saldo_eur
            FROM deuda_asociaciones d
            LEFT JOIN torneosact t ON d.torneo_id = t.torneo
            LEFT JOIN asociaciones a ON d.asociacion_id = a.id
            LEFT JOIN (
                SELECT torneo_id, asociacion_id, SUM(COALESCE(monto_dolares, 0)) AS pagado_eur
                FROM relacion_pagos
                GROUP BY torneo_id, asociacion_id
            ) rp ON rp.torneo_id = d.torneo_id AND rp.asociacion_id = d.asociacion_id
            WHERE 1=1' . $soloAsocActivas . '
            ORDER BY d.fecha_creacion DESC';

        return self::paginateWithAsociacionScope($this->pdo, $countSql, $dataSql, $params, $page, $perPage, self::SCOPE_COL);
    }

    /**
     * Última fila de tarifas en `costos` (misma lógica que el generador de deudas).
     *
     * @return array<string, mixed>|null
     */
    public function ultimoCostoTarifa(): ?array
    {
        require_once $this->projectRoot() . '/src/Services/DeudaAsociacionGeneratorService.php';

        return \FvdPortal\Services\DeudaAsociacionGeneratorService::ultimoCosto($this->pdo);
    }

    public function find(int $torneoId, int $asociacionId): ?array
    {
        $params = [':tid' => $torneoId, ':aid' => $asociacionId];
        $scope = self::asociacionScopeSql(self::SCOPE_COL, $params);
        $sql = 'SELECT d.* FROM deuda_asociaciones d WHERE d.torneo_id = :tid AND d.asociacion_id = :aid ' . $scope;
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * Listados por concepto. Si $soloConcepto es una clave válida de ETIQUETAS_CONCEPTO, solo ese bloque.
     *
     * @return array<string, list<array{atleta_id:int,numfvd:int,nombre:string,cedula:string}>>
     */
    public function detallePorConceptos(int $torneoId, int $asociacionId, ?string $soloConcepto = null): array
    {
        $this->enforceAsociacionId($asociacionId);
        if ($this->find($torneoId, $asociacionId) === null) {
            throw new InvalidArgumentException('Deuda no encontrada.');
        }

        if ($soloConcepto !== null) {
            if (!isset(self::ETIQUETAS_CONCEPTO[$soloConcepto])) {
                throw new InvalidArgumentException('Concepto no válido.');
            }
            $col = self::CONCEPTO_ATLETA_COL[$soloConcepto];

            return [$soloConcepto => $this->fetchAtletasMarcados($torneoId, $asociacionId, $col)];
        }

        $out = [];
        foreach (array_keys(self::ETIQUETAS_CONCEPTO) as $key) {
            $col = self::CONCEPTO_ATLETA_COL[$key];
            $out[$key] = $this->fetchAtletasMarcados($torneoId, $asociacionId, $col);
        }

        return $out;
    }

    /**
     * @return list<array{atleta_id:int,numfvd:int,nombre:string,cedula:string}>
     */
    private function fetchAtletasMarcados(int $torneoId, int $asociacionId, string $col): array
    {
        static $allowed = ['inscripcion', 'afiliacion', 'carnet', 'traspaso', 'anualidad'];
        if (!in_array($col, $allowed, true)) {
            return [];
        }

        if (!class_exists('FvdAdminService', false)) {
            require_once dirname(__DIR__, 3) . '/src/Services/FvdAdminService.php';
        }
        $fvdDeu = new \FvdAdminService($this->pdo);
        $sexoSqlDeu = $fvdDeu->sqlAtletasFiltroSexoSegunTorneoTipo($torneoId, 'a');

        $params = [':tid' => $torneoId, ':aid' => $asociacionId];
        $scope = self::asociacionScopeSql('a.asociacion', $params);
        $sql = 'SELECT a.id AS atleta_id, a.numfvd, a.nombre, a.cedula
            FROM atletas a
            WHERE a.torneo_id = :tid AND a.asociacion = :aid
            AND COALESCE(a.' . $col . ', 0) = 1 ' . $sexoSqlDeu . $scope . '
            ORDER BY a.nombre ASC, a.numfvd ASC';
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        $list = [];
        foreach ($rows as $r) {
            $list[] = [
                'atleta_id' => (int) ($r['atleta_id'] ?? 0),
                'numfvd' => (int) ($r['numfvd'] ?? 0),
                'nombre' => (string) ($r['nombre'] ?? ''),
                'cedula' => (string) ($r['cedula'] ?? ''),
            ];
        }

        return $list;
    }

    public function save(int $torneoId, int $asociacionId, array $post): void
    {
        $row = $this->find($torneoId, $asociacionId);
        if ($row === null) {
            throw new InvalidArgumentException('Deuda no encontrada.');
        }
        $this->enforceAsociacionId($asociacionId);

        $data = [];
        foreach (self::ALLOW_UPDATE as $col) {
            if ($col === 'monto_total_eur') {
                continue;
            }
            $v = $post[$col] ?? null;
            $data[$col] = self::parseNonNegativeFloat($v);
        }
        if ($this->deudaAsociacionesTieneMontoTotalEur()) {
            $montoTotalEurPost = $post['monto_total_eur'] ?? null;
            if ($montoTotalEurPost === '' || $montoTotalEurPost === null) {
                // Compatibilidad: si no llega el campo EUR, conserva el valor previo.
                $data['monto_total_eur'] = isset($row['monto_total_eur']) ? (float) $row['monto_total_eur'] : 0.0;
            } else {
                $data['monto_total_eur'] = round(self::parseNonNegativeFloat($montoTotalEurPost), 2);
            }
        }
        $data['monto_total'] = round((float) ($data['monto_total'] ?? 0), 2);

        $where = 'torneo_id = :t AND asociacion_id = :a';
        $wparams = [':t' => $torneoId, ':a' => $asociacionId];
        $allow = self::ALLOW_UPDATE;
        if (!$this->deudaAsociacionesTieneMontoTotalEur()) {
            $allow = array_values(array_filter($allow, static function (string $col): bool {
                return $col !== 'monto_total_eur';
            }));
        }
        self::update($this->pdo, self::TABLE, $data, $allow, $where, $wparams);

        $st = $this->pdo->prepare('UPDATE deuda_asociaciones SET fecha_actualizacion = NOW() WHERE torneo_id = ? AND asociacion_id = ?');
        $st->execute([$torneoId, $asociacionId]);
    }

    public function delete(int $torneoId, int $asociacionId): void
    {
        if (AuthService::role() !== AuthService::ROLE_FVD_ADMIN) {
            http_response_code(403);
            exit;
        }
        $params = [':tid' => $torneoId, ':aid' => $asociacionId];
        $scope = self::asociacionScopeSql(self::SCOPE_COL, $params);
        $sql = 'DELETE FROM deuda_asociaciones d WHERE d.torneo_id = :tid AND d.asociacion_id = :aid ' . $scope;
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
    }

    public function torneoEstaFinalizado(int $torneoId): bool
    {
        if ($torneoId <= 0) {
            return true;
        }
        $st = $this->pdo->prepare('SELECT estatus FROM torneosact WHERE torneo = :tid LIMIT 1');
        $st->execute([':tid' => $torneoId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return true;
        }
        $estatus = (int) ($row['estatus'] ?? 0);

        // Convención actual: 1=en proceso, 0=planificado, 2=finalizado.
        return $estatus === 2;
    }

    /**
     * Recalcula la deuda del torneo+asociación desde `inscripcion_torneo` (si existe la tabla) o desde `atletas`:
     * cuenta marcas por concepto; aplica precios de la última fila de `costos` y persiste en `deuda_asociaciones`.
     */
    public function actualizarDeudaDesdeAtletas(int $torneoId, int $asociacionId): void
    {
        $this->enforceAsociacionId($asociacionId);
        if ($this->torneoEstaFinalizado($torneoId)) {
            throw new RuntimeException('No se puede actualizar la deuda porque el torneo ya finalizó.');
        }
        require_once $this->projectRoot() . '/src/Services/DeudaAsociacionGeneratorService.php';
        \FvdPortal\Services\DeudaAsociacionGeneratorService::generarParaTorneoYAsociacion($this->pdo, $torneoId, $asociacionId);
    }

    /**
     * Pares torneo+asociación con fila en deuda_asociaciones, respetando alcance (admin: todas; delegado: solo su club).
     *
     * @return list<array{torneo_id:int, asociacion_id:int}>
     */
    public function listarParesDeudaParaSincronizacion(): array
    {
        $params = [];
        $scope = self::asociacionScopeSql('d.asociacion_id', $params);
        $sql = 'SELECT d.torneo_id, d.asociacion_id FROM deuda_asociaciones d WHERE 1=1 ' . $scope
            . ' ORDER BY d.torneo_id DESC, d.asociacion_id ASC';
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        if (!is_array($rows)) {
            return [];
        }
        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'torneo_id' => (int) ($r['torneo_id'] ?? 0),
                'asociacion_id' => (int) ($r['asociacion_id'] ?? 0),
            ];
        }

        return $out;
    }

    /**
     * Recalcula montos desde atletas para cada par visible según rol (admin: todas las asociaciones; delegado: solo la suya).
     *
     * @return array{ok:int, omitidos:int, errores:int, mensajes:list<string>}
     */
    public function sincronizarTodasLasDeudasDesdeAtletas(): array
    {
        $pares = $this->listarParesDeudaParaSincronizacion();
        $ok = 0;
        $omitidos = 0;
        $errores = 0;
        $mensajes = [];
        foreach ($pares as $p) {
            $tid = $p['torneo_id'];
            $aid = $p['asociacion_id'];
            if ($tid <= 0 || $aid <= 0) {
                continue;
            }
            if ($this->torneoEstaFinalizado($tid)) {
                $omitidos++;

                continue;
            }
            try {
                $this->actualizarDeudaDesdeAtletas($tid, $aid);
                $ok++;
            } catch (Throwable $e) {
                $errores++;
                $mensajes[] = sprintf('Torneo %d / Asoc. %d: %s', $tid, $aid, $e->getMessage());
                error_log('[DeudaAsociacion] sincronización masiva: ' . $e->getMessage());
            }
        }

        return [
            'ok' => $ok,
            'omitidos' => $omitidos,
            'errores' => $errores,
            'mensajes' => $mensajes,
        ];
    }

    /**
     * Recibos de pago (`relacion_pagos`) del torneo y asociación, orden cronológico descendente.
     *
     * @return list<array<string, mixed>>
     */
    public function listPagosRecibos(int $torneoId, int $asociacionId): array
    {
        if ($torneoId <= 0 || $asociacionId <= 0) {
            return [];
        }
        $this->enforceAsociacionId($asociacionId);
        $params = [':tid' => $torneoId, ':aid' => $asociacionId];
        $scope = self::asociacionScopeSql('r.asociacion_id', $params);
        $sql = 'SELECT r.id, r.fecha, r.secuencia, r.tipo_pago, r.monto_dolares, r.monto_total, r.tasa_cambio, r.referencia
            FROM relacion_pagos r
            WHERE r.torneo_id = :tid AND r.asociacion_id = :aid ' . $scope . '
            ORDER BY r.fecha DESC, r.secuencia DESC, r.id DESC';
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);

        return is_array($rows) ? $rows : [];
    }

    /**
     * @return list<array<string, mixed>> filas con id (torneo) y nombre
     */
    public function listTorneosParaSelector(): array
    {
        try {
            $st = $this->pdo->query('SELECT torneo AS id, nombre FROM torneosact ORDER BY torneo DESC');
            $rows = $st ? $st->fetchAll(PDO::FETCH_ASSOC) : [];

            return is_array($rows) ? $rows : [];
        } catch (Throwable $e) {
            return [];
        }
    }

    public function nombreTorneo(int $torneoId): string
    {
        if ($torneoId <= 0) {
            return '';
        }
        $st = $this->pdo->prepare('SELECT nombre FROM torneosact WHERE torneo = :t LIMIT 1');
        $st->execute([':t' => $torneoId]);
        $n = $st->fetchColumn();

        return $n !== false ? (string) $n : '';
    }

    /**
     * Estadísticas solo lectura por torneo: preferencia `inscripcion_torneo`; si no existe la tabla, `atletas.torneo_id`.
     * Métricas: {@see \FvdPortal\Services\QueryHelper::sqlSelectMetricasTorneoPorInscripcionTorneo} o
     * {@see \FvdPortal\Services\QueryHelper::sqlSelectMetricasTorneoPorAsociacion}.
     *
     * @return array{tabla_ok:bool, rows:list<array<string, mixed>>, fuente: 'inscripcion_torneo'|'atletas'}
     */
    public function estadisticasInscripcionOrigenPorTorneo(int $torneoId): array
    {
        require_once $this->projectRoot() . '/src/Services/InscripcionTorneoEstadisticasService.php';
        $tieneIt = \FvdPortal\Services\InscripcionTorneoEstadisticasService::tablaInscripcionTorneoExiste($this->pdo);
        $tieneA = \FvdPortal\Services\InscripcionTorneoEstadisticasService::tablaAtletasExiste($this->pdo);
        if (!$tieneIt && !$tieneA) {
            return [
                'tabla_ok' => false,
                'rows' => [],
                'fuente' => 'atletas',
            ];
        }
        $params = [':tid' => $torneoId];
        $scope = self::asociacionScopeSql('a.asociacion', $params);
        $rows = \FvdPortal\Services\InscripcionTorneoEstadisticasService::estadisticasPorTorneoAgrupadas($this->pdo, $scope, $params);

        return [
            'tabla_ok' => true,
            'rows' => $rows,
            'fuente' => $tieneIt ? 'inscripcion_torneo' : 'atletas',
        ];
    }
}
