<?php

declare(strict_types=1);

/**
 * Pagos: el importe contable frente a la deuda en EUR se guarda en `monto_dolares` (EUR).
 * `monto_total` (Bs) y `tasa_cambio` (Bs/EUR del día del recibo) son referenciales para verificación y arqueo de caja
 * cuando el cobro se liquidó o se expresa en bolívares según el BCV.
 */
require_once dirname(__DIR__) . '/FvdModuleController.php';

class RelacionPagoController extends FvdModuleController
{
    public const TABLE = 'relacion_pagos';

    private const SCOPE_COL = 'r.asociacion_id';

    /** @var bool|null Cache: tabla deuda_asociaciones tiene monto_total_eur y abono_eur */
    private static $deudaTieneColsEur = null;

    /** @var array<string, string> Código interno => etiqueta */
    public const TIPOS_PAGO_OPCIONES = [
        'efectivo_divisas' => 'Efectivo divisas (EUR)',
        'efectivo_bs' => 'Efectivo Bs',
        'transferencia_bs' => 'Transferencia Bs',
        'transferencia_divisas' => 'Transferencia divisas (EUR)',
        'pago_movil_bs' => 'Pago móvil Bs',
    ];

    /** @var list<string> */
    public const ALLOW_PERSIST = [
        'torneo_id', 'asociacion_id', 'secuencia', 'fecha', 'tasa_cambio', 'tipo_pago', 'moneda',
        'monto_total', 'monto_dolares', 'referencia', 'banco', 'observaciones',
    ];

    /**
     * @param int $filtroAsociacionId Si es &gt; 0, limita a esa asociación (super admin o misma asociación que la sesión).
     */
    public function paginateList(int $page, int $perPage, int $filtroAsociacionId = 0): array
    {
        $params = [];
        $filtroSql = '';
        if ($filtroAsociacionId > 0) {
            $ok = false;
            if (AuthService::isSuperAdmin()) {
                $ok = true;
            } else {
                $mine = AuthService::idAsociacion();
                if ($mine !== null && (int) $mine === $filtroAsociacionId) {
                    $ok = true;
                }
            }
            if ($ok) {
                $filtroSql = ' AND r.asociacion_id = :fvd_rp_filtro_aid ';
                $params[':fvd_rp_filtro_aid'] = $filtroAsociacionId;
            }
        }
        require_once $this->projectRoot() . '/src/Services/FvdAdminService.php';
        $soloAsocActivas = \FvdAdminService::asociacionesSqlFiltroEstatus('a', 'activas');
        $countSql = 'SELECT COUNT(*) FROM relacion_pagos r
            LEFT JOIN asociaciones a ON r.asociacion_id = a.id
            WHERE 1=1' . $filtroSql . $soloAsocActivas;
        $dataSql = 'SELECT r.*, a.nombre AS asoc_nombre, t.nombre AS torneo_nombre
            FROM relacion_pagos r
            LEFT JOIN asociaciones a ON r.asociacion_id = a.id
            LEFT JOIN torneosact t ON r.torneo_id = t.torneo
            WHERE 1=1' . $filtroSql . $soloAsocActivas . '
            ORDER BY r.fecha DESC';

        return self::paginateWithAsociacionScope($this->pdo, $countSql, $dataSql, $params, $page, $perPage, self::SCOPE_COL);
    }

    public function find(?int $id): ?array
    {
        if ($id === null) {
            return null;
        }
        $params = [':id' => $id];
        $scope = self::asociacionScopeSql(self::SCOPE_COL, $params);
        $sql = 'SELECT r.* FROM relacion_pagos r WHERE r.id = :id ' . $scope;
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function listAsociacionesForSelect(): array
    {
        if (AuthService::role() === AuthService::ROLE_FVD_ADMIN) {
            require_once $this->projectRoot() . '/src/Services/FvdAdminService.php';
            $w = \FvdAdminService::asociacionesSqlFiltroEstatus('asociaciones', 'activas');
            $st = $this->pdo->query('SELECT id, nombre FROM asociaciones WHERE 1=1' . $w . ' ORDER BY nombre');

            return $st ? $st->fetchAll(PDO::FETCH_ASSOC) : [];
        }
        $mine = AuthService::idAsociacion();
        if ($mine === null) {
            return [];
        }
        $st = $this->pdo->prepare('SELECT id, nombre FROM asociaciones WHERE id = :id');
        $st->execute([':id' => $mine]);

        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Torneo que fija el recibo: delegado → torneo en contexto de sesión;
     * fvd_admin / aso_admin → torneo con estatus = 1 (en proceso) más reciente por fecha, con alcance regional.
     *
     * @return array{torneo_id:int,nombre:string}|null
     */
    public function torneoActivoParaRecibo(): ?array
    {
        if (AuthService::isDelegadoAsociacion()) {
            $ctx = AuthService::delegadoTorneoContextId();
            if ($ctx === null || $ctx <= 0) {
                return null;
            }
            $st = $this->pdo->prepare('SELECT torneo, nombre FROM torneosact WHERE torneo = :id LIMIT 1');
            $st->execute([':id' => $ctx]);
            $row = $st->fetch(PDO::FETCH_ASSOC);

            return $row ? ['torneo_id' => (int) $row['torneo'], 'nombre' => (string) $row['nombre']] : null;
        }

        $params = [];
        $sql = 'SELECT t.torneo, t.nombre FROM torneosact t WHERE t.estatus = 1 ';
        $scope = self::asociacionScopeSql('t.organizacion_id', $params);
        $sql .= $scope . ' ORDER BY t.fechator DESC, t.torneo DESC LIMIT 1';
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ? ['torneo_id' => (int) $row['torneo'], 'nombre' => (string) $row['nombre']] : null;
    }

    public function torneoNombrePorId(int $torneoId): string
    {
        if ($torneoId <= 0) {
            return '—';
        }
        $st = $this->pdo->prepare('SELECT nombre FROM torneosact WHERE torneo = ? LIMIT 1');
        $st->execute([$torneoId]);
        $n = $st->fetchColumn();

        return $n !== false ? (string) $n : ('#' . $torneoId);
    }

    /**
     * True si existen migraciones EUR en `deuda_asociaciones` (si no, solo modo Bs).
     */
    private function deudaAsociacionesTieneColumnasEur(): bool
    {
        if (self::$deudaTieneColsEur !== null) {
            return self::$deudaTieneColsEur;
        }
        try {
            $st = $this->pdo->query(
                "SHOW COLUMNS FROM deuda_asociaciones WHERE Field IN ('monto_total_eur','abono_eur')"
            );
            $rows = $st ? $st->fetchAll(PDO::FETCH_ASSOC) : [];
            self::$deudaTieneColsEur = count($rows) >= 2;
        } catch (Throwable $e) {
            self::$deudaTieneColsEur = false;
        }

        return self::$deudaTieneColsEur;
    }

    /**
     * Resumen para estado de cuenta: siempre en EUR (deuda total EUR vs SUM(monto_dolares)).
     * Los Bs (monto_total en pagos y deuda) solo se devuelven como referencia de arqueo, no para saldo.
     * modo `bs`: solo si la BD no tiene columnas EUR en deuda_asociaciones (legado).
     *
     * @return array<string, mixed>
     */
    public function deudaResumenParaRecibo(int $torneoId, int $asociacionId): array
    {
        $empty = [
            'ok' => true,
            'modo' => 'eur',
            'tiene_deuda' => false,
            'tiene_total_eur_deuda' => false,
            'monto_total_deuda' => 0.0,
            'pagado_bs' => 0.0,
            'pendiente_bs' => 0.0,
            'monto_total_deuda_eur' => 0.0,
            'pagado_eur' => 0.0,
            'pendiente_eur' => null,
        ];
        if ($torneoId <= 0 || $asociacionId <= 0) {
            return $empty;
        }

        $params = [':tid' => $torneoId, ':aid' => $asociacionId];
        $scopeD = self::asociacionScopeSql('d.asociacion_id', $params);
        $sqlD = $this->deudaAsociacionesTieneColumnasEur()
            ? 'SELECT d.monto_total, d.monto_total_eur FROM deuda_asociaciones d WHERE d.torneo_id = :tid AND d.asociacion_id = :aid ' . $scopeD . ' LIMIT 1'
            : 'SELECT d.monto_total FROM deuda_asociaciones d WHERE d.torneo_id = :tid AND d.asociacion_id = :aid ' . $scopeD . ' LIMIT 1';
        $st = $this->pdo->prepare($sqlD);
        $st->execute($params);
        $deudaRow = $st->fetch(PDO::FETCH_ASSOC);

        $params2 = [':tid' => $torneoId, ':aid' => $asociacionId];
        $scopeR = self::asociacionScopeSql('r.asociacion_id', $params2);
        $sqlSumBs = 'SELECT COALESCE(SUM(r.monto_total), 0) FROM relacion_pagos r WHERE r.torneo_id = :tid AND r.asociacion_id = :aid ' . $scopeR;
        $sqlSumEur = 'SELECT COALESCE(SUM(r.monto_dolares), 0) FROM relacion_pagos r WHERE r.torneo_id = :tid AND r.asociacion_id = :aid ' . $scopeR;

        $stBs = $this->pdo->prepare($sqlSumBs);
        $stBs->execute($params2);
        $pagadoBs = (float) $stBs->fetchColumn();

        $stEur = $this->pdo->prepare($sqlSumEur);
        $stEur->execute($params2);
        $pagadoEur = (float) $stEur->fetchColumn();

        if ($deudaRow === false) {
            return $empty;
        }

        $totalBsCol = (float) ($deudaRow['monto_total'] ?? 0);
        $tieneColsEur = $this->deudaAsociacionesTieneColumnasEur();
        $totalEurCol = $tieneColsEur ? (float) ($deudaRow['monto_total_eur'] ?? 0) : 0.0;

        if ($tieneColsEur) {
            $tieneTotalEur = $totalEurCol > 0;
            $pendienteEur = null;
            if ($tieneTotalEur) {
                $pendienteEur = max(0.0, round($totalEurCol - $pagadoEur, 6));
            }

            return [
                'ok' => true,
                'modo' => 'eur',
                'tiene_deuda' => true,
                'tiene_total_eur_deuda' => $tieneTotalEur,
                'monto_total_deuda' => round($totalBsCol, 2),
                'pagado_bs' => round($pagadoBs, 2),
                'pendiente_bs' => 0.0,
                'monto_total_deuda_eur' => round($totalEurCol, 6),
                'pagado_eur' => round($pagadoEur, 6),
                'pendiente_eur' => $pendienteEur,
            ];
        }

        $pendienteBs = max(0.0, round($totalBsCol - $pagadoBs, 2));

        return [
            'ok' => true,
            'modo' => 'bs',
            'tiene_deuda' => true,
            'tiene_total_eur_deuda' => false,
            'monto_total_deuda' => round($totalBsCol, 2),
            'pagado_bs' => round($pagadoBs, 2),
            'pendiente_bs' => $pendienteBs,
            'monto_total_deuda_eur' => 0.0,
            'pagado_eur' => round($pagadoEur, 6),
            'pendiente_eur' => null,
        ];
    }

    private function nextSecuencia(int $torneoId, int $asociacionId): int
    {
        $st = $this->pdo->prepare(
            'SELECT COALESCE(MAX(secuencia), 0) + 1 FROM relacion_pagos WHERE torneo_id = ? AND asociacion_id = ?'
        );
        $st->execute([$torneoId, $asociacionId]);

        return (int) $st->fetchColumn();
    }

    private function sincronizarAbonoDeuda(int $torneoId, int $asociacionId): void
    {
        if ($torneoId <= 0 || $asociacionId <= 0) {
            return;
        }
        $params = [':tid' => $torneoId, ':aid' => $asociacionId];
        $scope = self::asociacionScopeSql('d.asociacion_id', $params);
        if ($this->deudaAsociacionesTieneColumnasEur()) {
            $sql = 'UPDATE deuda_asociaciones d SET
            d.abono_eur = (
                SELECT COALESCE(SUM(r.monto_dolares), 0) FROM relacion_pagos r
                WHERE r.torneo_id = d.torneo_id AND r.asociacion_id = d.asociacion_id
            ),
            d.abono = (
                SELECT COALESCE(SUM(r.monto_total), 0) FROM relacion_pagos r
                WHERE r.torneo_id = d.torneo_id AND r.asociacion_id = d.asociacion_id
            )
            WHERE d.torneo_id = :tid AND d.asociacion_id = :aid ' . $scope;
        } else {
            $sql = 'UPDATE deuda_asociaciones d SET
            d.abono = (
                SELECT COALESCE(SUM(r.monto_total), 0) FROM relacion_pagos r
                WHERE r.torneo_id = d.torneo_id AND r.asociacion_id = d.asociacion_id
            )
            WHERE d.torneo_id = :tid AND d.asociacion_id = :aid ' . $scope;
        }
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
    }

    private static function monedaDesdeTipoPago(string $tipo): string
    {
        return ($tipo === 'efectivo_divisas' || $tipo === 'transferencia_divisas') ? 'divisas' : 'Bs';
    }

    public function save(?int $id, array $post): void
    {
        if ($id !== null) {
            throw new RuntimeException('Solo se permiten altas de pago; la consulta de recibos no admite edición.');
        }

        $tipoPago = isset($post['tipo_pago']) ? trim((string) $post['tipo_pago']) : '';
        if (!isset(self::TIPOS_PAGO_OPCIONES[$tipoPago])) {
            $legacyMap = [
                'efectivo' => 'efectivo_bs',
                'transferencia' => 'transferencia_bs',
                'pago_movil' => 'pago_movil_bs',
            ];
            if (isset($legacyMap[$tipoPago])) {
                $tipoPago = $legacyMap[$tipoPago];
            } else {
                throw new InvalidArgumentException('Tipo de pago no válido.');
            }
        }

        $tasa = isset($post['tasa_cambio']) && $post['tasa_cambio'] !== '' ? (float) $post['tasa_cambio'] : 0.0;
        $montoBs = isset($post['monto_bs']) && $post['monto_bs'] !== '' ? (float) $post['monto_bs'] : 0.0;
        $montoEur = isset($post['monto_eur']) && $post['monto_eur'] !== '' ? (float) $post['monto_eur'] : 0.0;

        if ($tasa > 0) {
            if ($montoEur > 0) {
                $montoBs = round($montoEur * $tasa, 2);
            } elseif ($montoBs > 0) {
                $montoEur = round($montoBs / $tasa, 6);
            }
        }

        if ($montoEur <= 0) {
            throw new RuntimeException('Indique un monto en EUR mayor que cero (o en Bs con tasa BCV cargada para derivar el equivalente).');
        }
        if ($montoBs <= 0) {
            throw new RuntimeException('No se pudo calcular el monto en Bs: cargue la tasa BCV (Bs por EUR).');
        }
        if ($tasa <= 0) {
            throw new RuntimeException('La tasa de cambio (Bs por EUR) debe ser mayor que cero.');
        }

        $data = [];
        foreach (self::ALLOW_PERSIST as $col) {
            if ($col === 'torneo_id' || $col === 'monto_total' || $col === 'monto_dolares' || $col === 'moneda' || $col === 'secuencia') {
                continue;
            }
            $v = $post[$col] ?? null;
            if ($col === 'asociacion_id') {
                $data[$col] = $v === '' || $v === null ? null : (int) $v;
            } elseif ($col === 'tasa_cambio') {
                $data[$col] = $tasa;
            } elseif ($col === 'tipo_pago') {
                $data[$col] = $tipoPago;
            } else {
                $data[$col] = $v === '' ? null : (string) $v;
            }
        }

        $data['monto_total'] = round($montoBs, 2);
        $data['monto_dolares'] = round($montoEur, 6);
        $data['moneda'] = self::monedaDesdeTipoPago($tipoPago);

        $active = $this->torneoActivoParaRecibo();
        if ($active === null) {
            throw new RuntimeException('No hay torneo activo: entre al panel del torneo (delegado) o tenga al menos un torneo con estatus en proceso.');
        }
        $data['torneo_id'] = $active['torneo_id'];

        if (AuthService::role() !== AuthService::ROLE_FVD_ADMIN) {
            $mine = AuthService::idAsociacion();
            if ($mine === null) {
                throw new RuntimeException('Sin asociación.');
            }
            $data['asociacion_id'] = $mine;
        }

        $data['secuencia'] = $this->nextSecuencia((int) $data['torneo_id'], (int) $data['asociacion_id']);

        self::insert($this->pdo, self::TABLE, $data, self::ALLOW_PERSIST);

        $this->sincronizarAbonoDeuda((int) $data['torneo_id'], (int) $data['asociacion_id']);
    }

    public function delete(int $id): void
    {
        throw new RuntimeException('No está permitido eliminar pagos (recibo #' . $id . ').');
    }
}
