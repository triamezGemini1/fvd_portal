<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/FvdModuleController.php';

class DeudaAsociacionController extends FvdModuleController
{
    public const TABLE = 'deuda_asociaciones';

    private const SCOPE_COL = 'd.asociacion_id';

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
        'total_anualidad', 'monto_anualidad', 'monto_total',
    ];

    public function paginateList(int $page, int $perPage): array
    {
        $params = [];
        $countSql = 'SELECT COUNT(*) FROM deuda_asociaciones d WHERE 1=1';
        $dataSql = 'SELECT d.*, t.nombre AS torneo_nombre, a.nombre AS asoc_nombre
            FROM deuda_asociaciones d
            LEFT JOIN torneosact t ON d.torneo_id = t.torneo
            LEFT JOIN asociaciones a ON d.asociacion_id = a.id
            WHERE 1=1
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

        $params = [':tid' => $torneoId, ':aid' => $asociacionId];
        $scope = self::asociacionScopeSql('a.asociacion', $params);
        $sql = 'SELECT a.id AS atleta_id, a.numfvd, a.nombre, a.cedula
            FROM atletas a
            WHERE a.torneo_id = :tid AND a.asociacion = :aid
            AND COALESCE(a.' . $col . ', 0) = 1 ' . $scope . '
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
            $v = $post[$col] ?? null;
            $data[$col] = $v === '' || $v === null ? 0 : (float) $v;
        }

        $where = 'torneo_id = :t AND asociacion_id = :a';
        $wparams = [':t' => $torneoId, ':a' => $asociacionId];
        self::update($this->pdo, self::TABLE, $data, self::ALLOW_UPDATE, $where, $wparams);

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
}
