<?php

declare(strict_types=1);

namespace FvdPortal\Services;

use PDO;
use PDOException;

final class NotificacionService
{
    private const TABLE = 'fvd_admin_notificaciones';

    private const DDL = <<<'SQL'
CREATE TABLE IF NOT EXISTS `fvd_admin_notificaciones` (
  `id` int NOT NULL AUTO_INCREMENT,
  `admin_user_id` int DEFAULT NULL,
  `tipo` varchar(64) NOT NULL,
  `mensaje` varchar(512) NOT NULL,
  `leido` tinyint(1) NOT NULL DEFAULT 0,
  `creado_en` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_fvd_admin_notif_leido` (`leido`),
  KEY `idx_fvd_admin_notif_creado` (`creado_en`),
  KEY `idx_fvd_admin_notif_admin` (`admin_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;

    public static function ensureTable(PDO $pdo): void
    {
        try {
            $pdo->exec(self::DDL);
        } catch (PDOException $e) {
            error_log('[NotificacionService::ensureTable] ' . $e->getMessage());
        }
    }

    public static function resolverAdminGeneralId(PDO $pdo): ?int
    {
        try {
            $st = $pdo->query(
                "SELECT id
                 FROM fvd_usuarios
                 WHERE rol = 'fvd_admin' AND COALESCE(activo, 1) = 1
                 ORDER BY id ASC
                 LIMIT 1"
            );
            $id = $st ? $st->fetchColumn() : false;
            if ($id === false || $id === null) {
                return null;
            }

            return (int) $id;
        } catch (PDOException $e) {
            error_log('[NotificacionService::resolverAdminGeneralId] ' . $e->getMessage());

            return null;
        }
    }

    public static function crear(PDO $pdo, ?int $adminId, string $tipo, string $mensaje): void
    {
        self::ensureTable($pdo);
        $tipo = trim($tipo);
        $mensaje = trim($mensaje);
        if ($tipo === '' || $mensaje === '') {
            return;
        }

        try {
            $st = $pdo->prepare(
                'INSERT INTO ' . self::TABLE . ' (admin_user_id, tipo, mensaje, leido, creado_en)
                 VALUES (:a, :t, :m, 0, NOW())'
            );
            $st->execute([
                ':a' => $adminId !== null && $adminId > 0 ? $adminId : null,
                ':t' => mb_substr($tipo, 0, 64),
                ':m' => mb_substr($mensaje, 0, 512),
            ]);
        } catch (PDOException $e) {
            error_log('[NotificacionService::crear] ' . $e->getMessage());
        }
    }

    /**
     * @return list<array<string,mixed>>
     */
    public static function listarNoLeidas(PDO $pdo, ?int $adminId, int $limit = 10): array
    {
        self::ensureTable($pdo);
        $limit = max(1, min(100, $limit));
        try {
            if ($adminId !== null && $adminId > 0) {
                $st = $pdo->prepare(
                    'SELECT id, tipo, mensaje, creado_en
                     FROM ' . self::TABLE . '
                     WHERE leido = 0 AND (admin_user_id = :a OR admin_user_id IS NULL)
                     ORDER BY id DESC
                     LIMIT ' . $limit
                );
                $st->execute([':a' => $adminId]);
            } else {
                $st = $pdo->query(
                    'SELECT id, tipo, mensaje, creado_en
                     FROM ' . self::TABLE . '
                     WHERE leido = 0
                     ORDER BY id DESC
                     LIMIT ' . $limit
                );
            }

            return $st ? ($st->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
        } catch (PDOException $e) {
            error_log('[NotificacionService::listarNoLeidas] ' . $e->getMessage());

            return [];
        }
    }
}
