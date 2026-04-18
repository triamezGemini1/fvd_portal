<?php

declare(strict_types=1);

namespace FvdPortal\Services;

use PDO;
use PDOException;
use RuntimeException;

require_once dirname(__DIR__, 2) . '/config/paths.php';

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

    /**
     * Marca `atletas.carnet` = 1 (carnet solicitado) para informes y paneles.
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

        $params = [];
        $scope = \QueryHelper::asociacionScopeSql('a.asociacion', $params);
        $in = implode(',', $ids);
        $sql = 'UPDATE atletas a SET a.carnet = 1 WHERE a.id IN (' . $in . ') ' . $scope;

        try {
            $st = $pdo->prepare($sql);
            $st->execute($params);

            return $st->rowCount();
        } catch (PDOException $e) {
            error_log('[CarnetService] emitirCarnet: ' . $e->getMessage());
            throw new RuntimeException('No se pudo actualizar el indicador de carnet en atletas.');
        }
    }
}
