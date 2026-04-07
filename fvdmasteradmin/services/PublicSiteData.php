<?php
/**
 * Consultas de solo lectura para el sitio público (sin filtro por sesión).
 * Usa fvd_db(); el landing usa QueryHelper::paginate para rankings y listados acotados.
 */
declare(strict_types=1);

final class PublicSiteData
{
    /** @var bool|null null = aún no comprobado */
    private static ?bool $torneosactHasPublicarLanding = null;

    private static function pdo(): PDO
    {
        return fvd_db();
    }

    private static function torneosactHasPublicarLandingColumn(): bool
    {
        if (self::$torneosactHasPublicarLanding !== null) {
            return self::$torneosactHasPublicarLanding;
        }
        try {
            $pdo = self::pdo();
            $db = $pdo->query('SELECT DATABASE()')->fetchColumn();
            if (!is_string($db) || $db === '') {
                self::$torneosactHasPublicarLanding = false;

                return false;
            }
            $st = $pdo->prepare(
                'SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
                WHERE TABLE_SCHEMA = :db AND TABLE_NAME = :tbl AND COLUMN_NAME = :col LIMIT 1'
            );
            $st->execute([':db' => $db, ':tbl' => 'torneosact', ':col' => 'publicar_landing']);
            self::$torneosactHasPublicarLanding = (bool) $st->fetchColumn();
        } catch (Throwable $e) {
            self::$torneosactHasPublicarLanding = false;
        }

        return self::$torneosactHasPublicarLanding;
    }

    /**
     * Filtro de torneos visibles en landing/calendario. Si no existe la columna publicar_landing,
     * no se aplica filtro (compatibilidad con BD antigua); ejecute alter_torneosact_publicar_landing.sql.
     */
    private static function sqlTorneoVisibleLanding(): string
    {
        return self::torneosactHasPublicarLandingColumn()
            ? ' AND (COALESCE(t.publicar_landing, 1) = 1) '
            : '';
    }

    public static function torneosactPublicarLandingColumnPresent(): bool
    {
        return self::torneosactHasPublicarLandingColumn();
    }

    /**
     * @param array<string, mixed> $params
     * @return array{total:int, page:int, per_page:int, pages:int, rows:list<array<string,mixed>>}
     */
    private static function paginate(
        PDO $pdo,
        string $countSql,
        string $dataSql,
        array $params,
        int $page,
        int $perPage
    ): array {
        $perPage = max(1, min(100, $perPage));
        $page = max(1, $page);
        $offset = ($page - 1) * $perPage;

        $stmtCount = $pdo->prepare($countSql);
        self::bindNamed($stmtCount, $params);
        $stmtCount->execute();
        $total = (int) $stmtCount->fetchColumn();

        $sql = $dataSql . ' LIMIT ' . (int) $perPage . ' OFFSET ' . (int) $offset;
        $stmt = $pdo->prepare($sql);
        self::bindNamed($stmt, $params);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $pages = $total > 0 ? (int) ceil($total / $perPage) : 1;

        return [
            'total'    => $total,
            'page'     => $page,
            'per_page' => $perPage,
            'pages'    => $pages,
            'rows'     => $rows,
        ];
    }

    /**
     * @param array<string, mixed> $params
     */
    private static function bindNamed(PDOStatement $stmt, array $params): void
    {
        foreach ($params as $k => $v) {
            $key = (string) $k;
            if ($key !== '' && $key[0] !== ':') {
                $key = ':' . ltrim($key, ':');
            }
            $type = PDO::PARAM_STR;
            if (is_int($v)) {
                $type = PDO::PARAM_INT;
            } elseif (is_bool($v)) {
                $type = PDO::PARAM_BOOL;
            } elseif ($v === null) {
                $type = PDO::PARAM_NULL;
            }
            $stmt->bindValue($key, $v, $type);
        }
    }

    /**
     * Próximos torneos (fecha >= hoy), más cercanos primero.
     *
     * @return list<array<string, mixed>>
     */
    public static function torneosProximos(int $limit = 12): array
    {
        $lim = max(1, min(200, $limit));
        $sql = 'SELECT t.torneo, t.nombre, t.lugar, DATE(t.fechator) AS fechator, t.afiche, t.clavetor, t.tipo, t.clase,
                o.nombre AS org_nombre
            FROM torneosact t
            LEFT JOIN asociaciones o ON t.organizacion_id = o.id
            WHERE DATE(t.fechator) >= CURDATE() ' . self::sqlTorneoVisibleLanding() . '
            ORDER BY t.fechator ASC
            LIMIT ' . $lim;
        $st = self::pdo()->query($sql);

        return $st ? $st->fetchAll(PDO::FETCH_ASSOC) : [];
    }

    /**
     * Torneos ya realizados (fecha < hoy), más recientes primero.
     *
     * @return list<array<string, mixed>>
     */
    public static function torneosPasados(int $limit = 80): array
    {
        $lim = max(1, min(500, $limit));
        $sql = 'SELECT t.torneo, t.nombre, t.lugar, DATE(t.fechator) AS fechator, t.afiche, t.clavetor, t.tipo, t.clase,
                o.nombre AS org_nombre
            FROM torneosact t
            LEFT JOIN asociaciones o ON t.organizacion_id = o.id
            WHERE DATE(t.fechator) < CURDATE() ' . self::sqlTorneoVisibleLanding() . '
            ORDER BY t.fechator DESC
            LIMIT ' . $lim;
        $st = self::pdo()->query($sql);

        return $st ? $st->fetchAll(PDO::FETCH_ASSOC) : [];
    }

    /**
     * Mezcla para landing: algunos próximos + algunos recientes.
     *
     * @return array{proximos: list<array<string,mixed>>, recientes: list<array<string,mixed>>}
     */
    public static function torneosDestacadosLanding(int $nProximos = 4, int $nRecientes = 4): array
    {
        return [
            'proximos' => self::torneosProximos($nProximos),
            'recientes' => self::torneosPasados($nRecientes),
        ];
    }

    /**
     * Listado paginado de asociaciones.
     *
     * @return array{total:int, page:int, per_page:int, pages:int, rows:list<array<string,mixed>>}
     */
    public static function asociacionesPaginadas(int $page, int $perPage = 60): array
    {
        $params = [];
        $where = ' WHERE (a.estatus = \'activo\' OR a.estatus IS NULL OR a.estatus = \'\') ';
        $countSql = 'SELECT COUNT(*) FROM asociaciones a' . $where;
        $dataSql = 'SELECT a.id, a.nombre, a.direccion, a.telefono, a.email, a.numreg, a.delegado, a.estatus, a.logo
            FROM asociaciones a' . $where . ' ORDER BY a.nombre ASC';

        return self::paginate(self::pdo(), $countSql, $dataSql, $params, $page, $perPage);
    }

    /**
     * Calendario completo con paginación independiente para próximos y pasados.
     *
     * @return array{proximos: array<string,mixed>, pasados: array<string,mixed>}
     */
    public static function calendarioPaginado(int $pageProx, int $pagePast, int $perProx = 25, int $perPast = 40): array
    {
        $p1 = [];
        $wUp = ' WHERE DATE(t.fechator) >= CURDATE() ' . self::sqlTorneoVisibleLanding() . ' ';
        $countUp = 'SELECT COUNT(*) FROM torneosact t' . $wUp;
        $dataUp = 'SELECT t.torneo, t.nombre, t.lugar, DATE(t.fechator) AS fechator, t.clavetor, t.tipo, t.clase,
            t.afiche, o.nombre AS org_nombre
            FROM torneosact t
            LEFT JOIN asociaciones o ON t.organizacion_id = o.id' . $wUp . ' ORDER BY t.fechator ASC';

        $p2 = [];
        $wPast = ' WHERE DATE(t.fechator) < CURDATE() ' . self::sqlTorneoVisibleLanding() . ' ';
        $countPast = 'SELECT COUNT(*) FROM torneosact t' . $wPast;
        $dataPast = 'SELECT t.torneo, t.nombre, t.lugar, DATE(t.fechator) AS fechator, t.clavetor, t.tipo, t.clase,
            t.afiche, o.nombre AS org_nombre
            FROM torneosact t
            LEFT JOIN asociaciones o ON t.organizacion_id = o.id' . $wPast . ' ORDER BY t.fechator DESC';

        return [
            'proximos' => self::paginate(self::pdo(), $countUp, $dataUp, $p1, $pageProx, $perProx),
            'pasados'  => self::paginate(self::pdo(), $countPast, $dataPast, $p2, $pagePast, $perPast),
        ];
    }

    /**
     * Ranking por pestaña: M / F / menores (< 18 años con fecha de nacimiento válida).
     * Orden: número FVD ascendente (vacíos al final), luego nombre.
     *
     * @return list<array<string, mixed>>
     */
    public static function rankingAtletas(string $tab, int $limit = 100): array
    {
        $tab = strtolower(trim($tab));
        $lim = max(1, min(300, $limit));

        $adultSql = '(a.fechnac IS NULL OR a.fechnac = \'0000-00-00\' OR TIMESTAMPDIFF(YEAR, a.fechnac, CURDATE()) >= 18)';
        $minorSql = '(a.fechnac IS NOT NULL AND a.fechnac > \'1900-01-01\' AND TIMESTAMPDIFF(YEAR, a.fechnac, CURDATE()) < 18)';

        if ($tab === 'femenino' || $tab === 'f') {
            $where = " WHERE a.sexo = 'F' AND {$adultSql} ";
        } elseif ($tab === 'menores' || $tab === 'juveniles') {
            $where = " WHERE {$minorSql} ";
        } else {
            $where = " WHERE a.sexo = 'M' AND {$adultSql} ";
        }

        $sql = 'SELECT a.id, a.cedula, a.nombre, a.sexo, a.numfvd, a.fechnac, s.nombre AS asociacion_nombre
            FROM atletas a
            LEFT JOIN asociaciones s ON a.asociacion = s.id' . $where . '
            ORDER BY (a.numfvd IS NULL OR a.numfvd = 0) ASC, a.numfvd ASC, a.nombre ASC
            LIMIT ' . $lim;
        $st = self::pdo()->query($sql);

        return $st ? $st->fetchAll(PDO::FETCH_ASSOC) : [];
    }

    /**
     * Top 5 nacional (adultos / sin fecha de nacimiento) vía QueryHelper::paginate.
     *
     * @return list<array<string, mixed>>
     */
    public static function landingTop5NacionalQueryHelper(string $sexoMOrF): array
    {
        $sexo = strtoupper($sexoMOrF) === 'F' ? 'F' : 'M';
        $pdo = fvd_db();
        $adultSql = '(a.fechnac IS NULL OR a.fechnac = \'0000-00-00\' OR TIMESTAMPDIFF(YEAR, a.fechnac, CURDATE()) >= 18)';
        $where = $sexo === 'F'
            ? " WHERE a.sexo = 'F' AND {$adultSql} "
            : " WHERE a.sexo = 'M' AND {$adultSql} ";
        $params = [];
        $countSql = 'SELECT COUNT(*) FROM atletas a' . $where;
        $dataSql = 'SELECT a.nombre, a.numfvd, s.nombre AS asociacion_nombre
            FROM atletas a
            LEFT JOIN asociaciones s ON a.asociacion = s.id' . $where . '
            ORDER BY (a.numfvd IS NULL OR a.numfvd = 0) ASC, a.numfvd ASC, a.nombre ASC';

        $page = QueryHelper::paginate($pdo, $countSql, $dataSql, $params, 1, 5);

        return $page['rows'];
    }

    /**
     * Tarjetas de asociaciones para el landing (QueryHelper::paginate).
     *
     * @return list<array<string, mixed>>
     */
    public static function landingAsociacionesCardsQueryHelper(int $perPage = 12): array
    {
        $pdo = fvd_db();
        $params = [];
        $where = ' WHERE (a.estatus = \'activo\' OR a.estatus IS NULL OR a.estatus = \'\') ';
        $countSql = 'SELECT COUNT(*) FROM asociaciones a' . $where;
        $dataSql = 'SELECT a.id, a.nombre, a.email, a.telefono, a.delegado, a.logo
            FROM asociaciones a' . $where . ' ORDER BY a.nombre ASC';
        $page = QueryHelper::paginate($pdo, $countSql, $dataSql, $params, 1, max(1, min(24, $perPage)));

        return $page['rows'];
    }

    /**
     * Torneos con estatus "En proceso" (estatus = 1) o "Próximo" (fecha futura y estatus 0 o NULL).
     * Ajuste los códigos numéricos en BD si su convención difiere.
     *
     * @return list<array<string, mixed>>
     */
    public static function landingTorneosEnProcesoOProximoQueryHelper(int $perPage = 8): array
    {
        $pdo = fvd_db();
        $params = [];
        $where = ' WHERE (t.estatus = 1 OR (DATE(t.fechator) >= CURDATE() AND (t.estatus IS NULL OR t.estatus = 0))) '
            . self::sqlTorneoVisibleLanding() . ' ';
        $countSql = 'SELECT COUNT(*) FROM torneosact t' . $where;
        $dataSql = 'SELECT t.nombre, t.lugar, DATE(t.fechator) AS fechator, t.estatus,
            CASE
                WHEN t.estatus = 1 THEN \'En proceso\'
                ELSE \'Próximo\'
            END AS estado_label
            FROM torneosact t' . $where . '
            ORDER BY (t.estatus = 1) DESC, t.fechator ASC';
        $page = QueryHelper::paginate($pdo, $countSql, $dataSql, $params, 1, max(1, min(20, $perPage)));

        return $page['rows'];
    }

    /**
     * Torneos con estatus "en proceso" (estatus = 1) para avisos en vivo.
     *
     * @return list<array<string, mixed>>
     */
    public static function torneosEnVivo(int $limit = 12): array
    {
        $lim = max(1, min(40, $limit));
        $sql = 'SELECT t.torneo, t.nombre, t.lugar, DATE(t.fechator) AS fechator, t.estatus, t.afiche,
                o.nombre AS org_nombre
            FROM torneosact t
                LEFT JOIN asociaciones o ON t.organizacion_id = o.id
                WHERE t.estatus = 1 ' . self::sqlTorneoVisibleLanding() . '
                ORDER BY t.fechator ASC
            LIMIT ' . $lim;
        $st = self::pdo()->query($sql);

        return $st ? $st->fetchAll(PDO::FETCH_ASSOC) : [];
    }

    /**
     * Ficha pública de un torneo (solo si está marcado para landing o NULL=visible por defecto).
     *
     * @return array<string, mixed>|null
     */
    public static function torneoPublicoPorId(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }
        $sql = 'SELECT t.torneo, t.nombre, t.lugar, DATE(t.fechator) AS fechator, t.afiche, t.invitacion, t.clavetor, t.tipo, t.clase,
                t.estatus, t.costotor, o.nombre AS org_nombre, o.email AS org_email, o.telefono AS org_telefono
            FROM torneosact t
            LEFT JOIN asociaciones o ON t.organizacion_id = o.id
            WHERE t.torneo = :id ' . self::sqlTorneoVisibleLanding();
        $st = self::pdo()->prepare($sql);
        $st->execute([':id' => $id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? $row : null;
    }

    /**
     * Ranking público paginado vía QueryHelper::paginate (atletas + asociación).
     * Bandas de edad exclusivas con fecha de nacimiento válida: Sub-12 &lt;12 años;
     * Sub-15 entre 12 y 14; Sub-18 entre 15 y 17; Libre ≥18 años o sin fecha válida.
     *
     * @return array{total:int, page:int, per_page:int, pages:int, rows:list<array<string,mixed>>}
     */
    public static function rankingPublicoPaginadoQueryHelper(
        string $categoria,
        string $sexoMOrF,
        int $page,
        int $perPage
    ): array {
        $cat = strtolower(trim($categoria));
        $allowed = ['libre', 'sub18', 'sub15', 'sub12'];
        if (!in_array($cat, $allowed, true)) {
            $cat = 'libre';
        }
        $sexo = strtoupper(trim($sexoMOrF)) === 'F' ? 'F' : 'M';

        $validDob = "(a.fechnac IS NOT NULL AND a.fechnac > '1900-01-01' AND a.fechnac <> '0000-00-00')";
        $age = 'TIMESTAMPDIFF(YEAR, a.fechnac, CURDATE())';
        $adult = '(NOT ' . $validDob . ' OR ' . $age . ' >= 18)';

        if ($cat === 'libre') {
            $where = ' WHERE a.sexo = :sexo AND ' . $adult . ' ';
        } elseif ($cat === 'sub18') {
            $where = ' WHERE a.sexo = :sexo AND ' . $validDob . ' AND ' . $age . ' >= 15 AND ' . $age . ' < 18 ';
        } elseif ($cat === 'sub15') {
            $where = ' WHERE a.sexo = :sexo AND ' . $validDob . ' AND ' . $age . ' >= 12 AND ' . $age . ' < 15 ';
        } else {
            $where = ' WHERE a.sexo = :sexo AND ' . $validDob . ' AND ' . $age . ' < 12 ';
        }

        $params = ['sexo' => $sexo];
        $pdo = fvd_db();
        $countSql = 'SELECT COUNT(*) FROM atletas a' . $where;
        $dataSql = 'SELECT a.id, a.cedula, a.nombre, a.sexo, a.numfvd, a.fechnac, s.nombre AS asociacion_nombre
            FROM atletas a
            LEFT JOIN asociaciones s ON a.asociacion = s.id' . $where . '
            ORDER BY (a.numfvd IS NULL OR a.numfvd = 0) ASC, a.numfvd ASC, a.nombre ASC';

        return QueryHelper::paginate($pdo, $countSql, $dataSql, $params, $page, $perPage);
    }

    /**
     * Texto corto para mostrar como "estado" en tarjetas (sin columna estado en BD).
     */
    public static function inferEstadoLabelFromNombre(string $nombre): string
    {
        $nombre = trim($nombre);
        if ($nombre === '') {
            return '—';
        }
        $parts = preg_split('/\s*[-–—]\s*/u', $nombre, 2);

        return trim($parts[0]) !== '' ? trim($parts[0]) : $nombre;
    }
}
