<?php

declare(strict_types=1);

namespace FvdPortal\Services;

use PDO;
use PDOException;
use RuntimeException;

require_once dirname(__DIR__, 2) . '/config/paths.php';
require_once dirname(__DIR__, 2) . '/fvdmasteradmin/services/AuthService.php';
require_once __DIR__ . '/DelegadoTorneoVentanasService.php';

/**
 * Carnet: la columna `atletas.carnet` en BD es el marcador para informes y estadísticas.
 * — 0 / NULL: pendiente de solicitud (reporte «elaboración de carnets»).
 * — 1: carnet solicitado al prestador (reporte «carnets solicitados», montos/indicadores).
 * Otros valores numéricos no se usan en cómputos estadísticos (solo cuenta exactamente 1).
 * Sin HTML.
 */
final class CarnetService
{
    /**
     * Completa asociacion_nombre si falta (p. ej. fila de atletasFind).
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public static function enriquecerAsociacion(PDO $pdo, array $row): array
    {
        $nom = trim((string) ($row['asociacion_nombre'] ?? ''));
        if ($nom !== '') {
            return $row;
        }
        $aid = isset($row['asociacion']) ? (int) $row['asociacion'] : 0;
        if ($aid <= 0) {
            $row['asociacion_nombre'] = '—';

            return $row;
        }
        $st = $pdo->prepare('SELECT nombre FROM asociaciones WHERE id = :id LIMIT 1');
        $st->execute([':id' => $aid]);
        $n = $st->fetchColumn();
        $row['asociacion_nombre'] = $n !== false ? trim((string) $n) : '—';

        return $row;
    }

    /**
     * @param array<string, mixed> $row Fila atleta con asociacion_nombre si está disponible
     * @return array{atleta_id: int, nombre: string, cedula: string, asociacion: string, numfvd: string, foto_url: string|null, foto_alt: string, carnet_solicitado: bool, traspaso_marcado: bool}
     */
    public static function prepararTarjeta(array $row, string $projectRoot): array
    {
        $atletaId = (int) ($row['id'] ?? 0);
        $carnetSolicitado = ((int) ($row['carnet'] ?? 0)) === 1;
        $traspasoMarcado = ((int) ($row['traspaso'] ?? 0)) === 1;
        $nombre = trim((string) ($row['nombre'] ?? ''));
        $cedula = trim((string) ($row['cedula'] ?? ''));
        $asoc = trim((string) ($row['asociacion_nombre'] ?? ''));
        if ($asoc === '') {
            $asoc = '—';
        }
        $nf = (int) ($row['numfvd'] ?? 0);
        $numfvd = $nf > 0 ? (string) $nf : '—';
        $fotoFn = isset($row['foto']) ? trim((string) $row['foto']) : '';
        $fotoUrl = null;
        if ($fotoFn !== '') {
            $rel = self::resolverRutaFotoPreferWebp($projectRoot, $fotoFn);
            if ($rel !== null) {
                $fotoUrl = url('crud_atletas/uploads/' . $rel);
            }
        }

        return [
            'atleta_id'         => $atletaId,
            'nombre'            => $nombre !== '' ? $nombre : '—',
            'cedula'            => $cedula !== '' ? $cedula : '—',
            'asociacion'        => $asoc,
            'numfvd'            => $numfvd,
            'foto_url'          => $fotoUrl,
            'foto_alt'          => $nombre,
            'carnet_solicitado' => $carnetSolicitado,
            'traspaso_marcado'  => $traspasoMarcado,
        ];
    }

    /**
     * Si existe homólogo .webp junto al archivo original, usar ese nombre para URL.
     */
    public static function resolverRutaFotoPreferWebp(string $projectRoot, string $fotoFilename): ?string
    {
        $base = $projectRoot . DIRECTORY_SEPARATOR . 'crud_atletas' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR;
        $safe = ltrim(str_replace(['..', '\\'], ['', '/'], $fotoFilename), '/');
        if ($safe === '' || str_contains($safe, '..')) {
            return null;
        }
        $full = $base . $safe;
        if (!is_file($full)) {
            return null;
        }
        $pi = pathinfo($safe);
        $dir = ($pi['dirname'] ?? '.') !== '.' ? ($pi['dirname'] . '/') : '';
        $stem = (string) ($pi['filename'] ?? '');
        $webpRel = $dir . $stem . '.webp';
        if (is_file($base . str_replace('/', DIRECTORY_SEPARATOR, $webpRel))) {
            return $webpRel;
        }

        return $safe;
    }

    private static function torneoMovimientoHistoricoTableExists(PDO $pdo): bool
    {
        try {
            $pdo->query('SELECT 1 FROM torneo_movimiento_historico LIMIT 0');

            return true;
        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * Marca `atletas.carnet` = 1 (carnet solicitado) para informes y paneles.
     * Delegado: exige torneo en contexto (panel / invitación), ventana de fase 1, alinea `torneo_id` si faltaba
     * y registra movimiento en `torneo_movimiento_historico` cuando la tabla existe.
     *
     * @param list<int> $ids
     */
    public static function emitirCarnet(PDO $pdo, array $ids): int
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $i): bool => $i > 0)));
        if ($ids === []) {
            return 0;
        }

        $legacy = dirname(__DIR__, 2) . '/fvdmasteradmin/services/QueryHelper.php';
        if (!\class_exists('QueryHelper', false)) {
            require_once $legacy;
        }

        $esDelegado = \AuthService::isDelegadoAsociacion();
        $ctxTorneo = null;
        if ($esDelegado) {
            $ctxTorneo = \AuthService::delegadoTorneoContextId();
            if ($ctxTorneo === null || $ctxTorneo <= 0) {
                throw new RuntimeException(
                    'Debe seleccionar el torneo desde el panel (entrada por invitación) para enviar solicitudes según el calendario del evento.'
                );
            }
            DelegadoTorneoVentanasService::assertPuedeFase1Administrativa($pdo, $ctxTorneo);
        }

        $params = [];
        $scope = \QueryHelper::asociacionScopeSql('a.asociacion', $params);
        $in = implode(',', $ids);
        $sqlPre = 'SELECT a.id, a.carnet, a.torneo_id, a.numfvd FROM atletas a WHERE a.id IN (' . $in . ') ' . $scope;
        try {
            $stPre = $pdo->prepare($sqlPre);
            $stPre->execute($params);
            $antes = $stPre->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (PDOException $e) {
            error_log('[CarnetService] emitirCarnet preselect: ' . $e->getMessage());
            throw new RuntimeException('No se pudo validar los atletas.');
        }

        $porId = [];
        foreach ($antes as $r) {
            $iid = (int) ($r['id'] ?? 0);
            if ($iid > 0) {
                $porId[$iid] = $r;
            }
        }
        foreach ($ids as $iid) {
            if (!isset($porId[$iid])) {
                throw new RuntimeException('Algún identificador no corresponde a un atleta de su alcance.');
            }
        }

        if ($esDelegado && $ctxTorneo !== null && $ctxTorneo > 0) {
            foreach ($porId as $r) {
                $tidRow = (int) ($r['torneo_id'] ?? 0);
                $tidCheck = $tidRow > 0 ? $tidRow : $ctxTorneo;
                DelegadoTorneoVentanasService::assertPuedeFase1Administrativa($pdo, $tidCheck);
            }
        }

        $sql = 'UPDATE atletas a SET a.carnet = 1 WHERE a.id IN (' . $in . ') ' . $scope;

        try {
            $st = $pdo->prepare($sql);
            $st->execute($params);
            $n = $st->rowCount();
        } catch (PDOException $e) {
            error_log('[CarnetService] emitirCarnet: ' . $e->getMessage());
            throw new RuntimeException('No se pudo actualizar el indicador de carnet en atletas.');
        }

        if ($esDelegado && $ctxTorneo !== null && $ctxTorneo > 0 && $n > 0) {
            try {
                $pFill = $params;
                $pFill[':ctx'] = $ctxTorneo;
                $sqlFill = 'UPDATE atletas a SET a.torneo_id = :ctx WHERE a.id IN (' . $in . ') '
                    . $scope
                    . ' AND (a.torneo_id IS NULL OR a.torneo_id = 0)';
                $stFill = $pdo->prepare($sqlFill);
                $stFill->execute($pFill);
            } catch (PDOException $e) {
                error_log('[CarnetService] emitirCarnet torneo_id: ' . $e->getMessage());
            }
        }

        if ($n > 0 && self::torneoMovimientoHistoricoTableExists($pdo)) {
            $insH = $pdo->prepare(
                'INSERT INTO torneo_movimiento_historico (torneo_id, atleta_id, numfvd, tipo, valor_anterior, valor_nuevo, notas)
                 VALUES (:tor, :aid, :nf, :tipo, :va, :vn, :no)'
            );
            foreach ($ids as $iid) {
                if (!isset($porId[$iid])) {
                    continue;
                }
                $r = $porId[$iid];
                if ((int) ($r['carnet'] ?? 0) === 1) {
                    continue;
                }
                $tidMov = (int) ($r['torneo_id'] ?? 0);
                if ($esDelegado && $ctxTorneo !== null && $ctxTorneo > 0) {
                    $tidMov = $tidMov > 0 ? $tidMov : $ctxTorneo;
                }
                if ($tidMov <= 0) {
                    continue;
                }
                try {
                    $insH->execute([
                        ':tor' => $tidMov,
                        ':aid' => $iid,
                        ':nf'  => (int) ($r['numfvd'] ?? 0),
                        ':tipo'=> 'carnet',
                        ':va'  => (string) (int) ($r['carnet'] ?? 0),
                        ':vn'  => '1',
                        ':no'  => 'Solicitud carnet (gestión fichas / carnets)',
                    ]);
                } catch (PDOException $e) {
                    error_log('[CarnetService] emitirCarnet historico: ' . $e->getMessage());
                }
            }
        }

        return $n;
    }
}
