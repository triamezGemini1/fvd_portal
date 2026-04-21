<?php

declare(strict_types=1);

namespace FvdPortal\Services;

use InvalidArgumentException;
use PDO;
use PDOException;
use RuntimeException;

require_once dirname(__DIR__, 2) . '/fvdmasteradmin/services/AuthService.php';
require_once __DIR__ . '/TraspasoService.php';
require_once __DIR__ . '/CarnetService.php';
require_once __DIR__ . '/NotificacionService.php';

/**
 * Cola de solicitudes de delegados (traspaso / carnet / afiliación) para aprobación FVD.
 */
final class DelegadoSolicitudService
{
    private static function atletaHasColumn(PDO $pdo, string $column): bool
    {
        try {
            $st = $pdo->prepare(
                'SELECT 1 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = :t
                   AND COLUMN_NAME = :c
                 LIMIT 1'
            );
            $st->execute([':t' => 'atletas', ':c' => $column]);

            return $st->fetchColumn() !== false;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private const DDL = <<<'SQL'
CREATE TABLE IF NOT EXISTS `fvd_solicitudes_delegado` (
  `id` int NOT NULL AUTO_INCREMENT,
  `tipo` enum('traspaso','carnet','afiliacion') NOT NULL,
  `asociacion_id` int NOT NULL,
  `atleta_id` int NOT NULL,
  `delegado_id` int DEFAULT NULL,
  `asociacion_destino_id` int DEFAULT NULL,
  `nota` varchar(512) DEFAULT NULL,
  `estado` enum('pendiente','aprobada','rechazada') NOT NULL DEFAULT 'pendiente',
  `creado_en` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `resuelto_en` datetime DEFAULT NULL,
  `resuelto_por_user_id` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_fvd_sol_estado` (`estado`),
  KEY `idx_fvd_sol_asoc` (`asociacion_id`),
  KEY `idx_fvd_sol_atleta` (`atleta_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;

    public static function ensureTable(PDO $pdo): void
    {
        try {
            $pdo->exec(self::DDL);
        } catch (PDOException $e) {
            error_log('[DelegadoSolicitudService] ensureTable: ' . $e->getMessage());
        }
    }

    public static function crear(
        PDO $pdo,
        string $tipo,
        int $atletaId,
        ?int $asociacionDestinoId,
        ?string $nota
    ): void {
        if (!\AuthService::checkAccess([\AuthService::ROLE_ASO_ADMIN, \AuthService::ROLE_DELEGADO_ASOC])) {
            throw new RuntimeException('Solo delegados pueden crear esta solicitud.');
        }
        $asoc = \AuthService::idAsociacion();
        if ($asoc === null || $asoc <= 0) {
            throw new RuntimeException('Sin asociación en sesión.');
        }

        self::ensureTable($pdo);

        $tipo = trim(strtolower($tipo));
        if (!in_array($tipo, ['traspaso', 'carnet', 'afiliacion'], true)) {
            throw new InvalidArgumentException('Tipo de solicitud no válido.');
        }
        if ($tipo === 'traspaso' && ($asociacionDestinoId === null || $asociacionDestinoId <= 0)) {
            throw new InvalidArgumentException('Indique asociación destino para el traspaso.');
        }
        if ($tipo === 'carnet' || $tipo === 'afiliacion') {
            $asociacionDestinoId = null;
        }

        $st = $pdo->prepare('SELECT id, nombre, asociacion FROM atletas WHERE id = :id LIMIT 1');
        $st->execute([':id' => $atletaId]);
        $a = $st->fetch(PDO::FETCH_ASSOC);
        if ($a === false || (int) ($a['asociacion'] ?? 0) !== (int) $asoc) {
            throw new InvalidArgumentException('El atleta no pertenece a su asociación.');
        }

        $delegadoId = \AuthService::isDelegadoAsociacion() ? \AuthService::userId() : null;

        $ins = $pdo->prepare(
            'INSERT INTO fvd_solicitudes_delegado (tipo, asociacion_id, atleta_id, delegado_id, asociacion_destino_id, nota, estado)
             VALUES (:tipo, :asoc, :aid, :did, :dest, :nota, \'pendiente\')'
        );
        $ins->execute([
            ':tipo' => $tipo,
            ':asoc' => $asoc,
            ':aid'  => $atletaId,
            ':did'  => $delegadoId,
            ':dest' => $asociacionDestinoId,
            ':nota'=> $nota !== null && trim($nota) !== '' ? trim($nota) : null,
        ]);

        if ($tipo === 'afiliacion') {
            if (self::atletaHasColumn($pdo, 'afiliacion_fecha')) {
                $pdo->prepare('UPDATE atletas SET afiliacion_fecha = NOW() WHERE id = :id')->execute([':id' => $atletaId]);
            }
            if (self::atletaHasColumn($pdo, 'verificado')) {
                $pdo->prepare('UPDATE atletas SET verificado = 0 WHERE id = :id')->execute([':id' => $atletaId]);
            }
        }

        if ($tipo === 'carnet') {
            if (self::atletaHasColumn($pdo, 'carnet_solicitud_fecha')) {
                $pdo->prepare('UPDATE atletas SET carnet_solicitud_fecha = NOW() WHERE id = :id')->execute([':id' => $atletaId]);
            }
            if (self::atletaHasColumn($pdo, 'carnet_status')) {
                $pdo->prepare("UPDATE atletas SET carnet_status = 'SOLICITADO' WHERE id = :id")->execute([':id' => $atletaId]);
            }
        }

        if ($tipo === 'traspaso') {
            if (self::atletaHasColumn($pdo, 'traspaso_asoc_destino')) {
                $pdo->prepare('UPDATE atletas SET traspaso_asoc_destino = :dest WHERE id = :id')
                    ->execute([':dest' => $asociacionDestinoId, ':id' => $atletaId]);
            }
            if (self::atletaHasColumn($pdo, 'estatus_traspaso')) {
                $pdo->prepare("UPDATE atletas SET estatus_traspaso = 'PROCESANDO' WHERE id = :id")
                    ->execute([':id' => $atletaId]);
            }

            $asocOrigenNombre = '';
            $asocDestinoNombre = '';
            try {
                $stAs = $pdo->prepare('SELECT nombre FROM asociaciones WHERE id = :id LIMIT 1');
                $stAs->execute([':id' => $asoc]);
                $asocOrigenNombre = trim((string) ($stAs->fetchColumn() ?: ''));
                if ($asociacionDestinoId !== null && $asociacionDestinoId > 0) {
                    $stAs->execute([':id' => $asociacionDestinoId]);
                    $asocDestinoNombre = trim((string) ($stAs->fetchColumn() ?: ''));
                }
            } catch (\Throwable $e) {
                // fallback
            }
            if ($asocOrigenNombre === '') {
                $asocOrigenNombre = 'Asociación origen';
            }
            if ($asocDestinoNombre === '') {
                $asocDestinoNombre = 'Asociación destino';
            }
            $adminId = NotificacionService::resolverAdminGeneralId($pdo);
            $nombreAtleta = trim((string) ($a['nombre'] ?? ''));
            if ($nombreAtleta === '') {
                $nombreAtleta = 'Atleta #' . $atletaId;
            }
            NotificacionService::crear(
                $pdo,
                $adminId,
                'SOLICITUD_TRASPASO',
                'Solicitud de traspaso: Atleta ' . $nombreAtleta . ' de ' . $asocOrigenNombre . ' hacia ' . $asocDestinoNombre
            );
        }
    }

    /**
     * @param 'traspaso'|'carnet_afiliacion'|null $soloTipo null = todas las pendientes
     * @return list<array<string, mixed>>
     */
    public static function listarPendientes(PDO $pdo, ?string $soloTipo = null): array
    {
        self::ensureTable($pdo);
        $extra = '';
        if ($soloTipo === 'traspaso') {
            $extra = " AND s.tipo = 'traspaso' ";
        } elseif ($soloTipo === 'carnet_afiliacion') {
            $extra = " AND s.tipo IN ('carnet','afiliacion') ";
        }
        $sql = 'SELECT s.*, at.nombre AS atleta_nombre, at.cedula AS atleta_cedula,
            ao.nombre AS asoc_origen_nombre, ad.nombre AS asoc_destino_nombre
            FROM fvd_solicitudes_delegado s
            INNER JOIN atletas at ON at.id = s.atleta_id
            LEFT JOIN asociaciones ao ON ao.id = s.asociacion_id
            LEFT JOIN asociaciones ad ON ad.id = s.asociacion_destino_id
            WHERE s.estado = \'pendiente\' ' . $extra . '
            ORDER BY s.id ASC';
        $st = $pdo->query($sql);

        return $st ? ($st->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
    }

    /**
     * Solicitudes pendientes donde la asociación del delegado es origen o destino (traspasos).
     *
     * @return list<array<string, mixed>>
     */
    public static function listarPendientesParaAsociacion(PDO $pdo, int $asociacionId): array
    {
        if ($asociacionId <= 0) {
            return [];
        }
        self::ensureTable($pdo);
        $sql = 'SELECT s.*, at.nombre AS atleta_nombre, at.cedula AS atleta_cedula,
            ao.nombre AS asoc_origen_nombre, ad.nombre AS asoc_destino_nombre
            FROM fvd_solicitudes_delegado s
            INNER JOIN atletas at ON at.id = s.atleta_id
            LEFT JOIN asociaciones ao ON ao.id = s.asociacion_id
            LEFT JOIN asociaciones ad ON ad.id = s.asociacion_destino_id
            WHERE s.estado = \'pendiente\'
            AND (
                s.asociacion_id = :a
                OR (s.tipo = \'traspaso\' AND s.asociacion_destino_id = :a2)
            )
            ORDER BY s.id ASC';
        try {
            $st = $pdo->prepare($sql);
            $st->execute([':a' => $asociacionId, ':a2' => $asociacionId]);

            return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (PDOException $e) {
            error_log('[DelegadoSolicitudService::listarPendientesParaAsociacion] ' . $e->getMessage());

            return [];
        }
    }

    public static function aprobar(PDO $pdo, int $solicitudId, ?int $fvdUsuarioId): void
    {
        if (!\AuthService::isSuperAdmin()) {
            throw new RuntimeException('Solo el administrador general puede aprobar.');
        }
        self::ensureTable($pdo);

        $st = $pdo->prepare('SELECT * FROM fvd_solicitudes_delegado WHERE id = :id AND estado = \'pendiente\' LIMIT 1');
        $st->execute([':id' => $solicitudId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            throw new InvalidArgumentException('Solicitud no encontrada o ya resuelta.');
        }

        $tipo = (string) ($row['tipo'] ?? '');
        $atletaId = (int) ($row['atleta_id'] ?? 0);
        $dest = isset($row['asociacion_destino_id']) ? (int) $row['asociacion_destino_id'] : 0;

        if ($tipo === 'traspaso') {
            TraspasoService::ejecutar($pdo, $atletaId, $dest, $fvdUsuarioId);
        } elseif ($tipo === 'carnet') {
            CarnetService::emitirCarnet($pdo, [$atletaId]);
        } elseif ($tipo === 'afiliacion') {
            $upA = $pdo->prepare('UPDATE atletas SET afiliacion = 1 WHERE id = :id');
            $upA->execute([':id' => $atletaId]);
        } else {
            throw new RuntimeException('Tipo de solicitud no soportado.');
        }

        $up = $pdo->prepare(
            'UPDATE fvd_solicitudes_delegado SET estado = \'aprobada\', resuelto_en = NOW(), resuelto_por_user_id = :u WHERE id = :id'
        );
        $up->execute([':u' => $fvdUsuarioId, ':id' => $solicitudId]);
    }

    public static function rechazar(PDO $pdo, int $solicitudId, ?int $fvdUsuarioId): void
    {
        if (!\AuthService::isSuperAdmin()) {
            throw new RuntimeException('Solo el administrador general puede rechazar.');
        }
        self::ensureTable($pdo);
        $up = $pdo->prepare(
            'UPDATE fvd_solicitudes_delegado SET estado = \'rechazada\', resuelto_en = NOW(), resuelto_por_user_id = :u
             WHERE id = :id AND estado = \'pendiente\''
        );
        $up->execute([':u' => $fvdUsuarioId, ':id' => $solicitudId]);
        if ($up->rowCount() < 1) {
            throw new InvalidArgumentException('Solicitud no encontrada o ya resuelta.');
        }
    }
}
