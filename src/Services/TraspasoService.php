<?php

declare(strict_types=1);

namespace FvdPortal\Services;

use PDO;
use PDOException;
use RuntimeException;
use Throwable;

require_once dirname(__DIR__, 2) . '/fvdmasteradmin/services/AuthService.php';
require_once __DIR__ . '/DeudaAsociacionGeneratorService.php';

/**
 * Traspaso: actualiza `atletas.asociacion` y marca `atletas.traspaso` = 1 para informes.
 * Registro detallado en log_traspasos (reporte independiente de traspasos).
 * Sin HTML. Los totales del dashboard (StatsService) leen siempre la BD actualizada.
 */
final class TraspasoService
{
    private const DDL = <<<'SQL'
CREATE TABLE IF NOT EXISTS `log_traspasos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `atleta_id` int(11) NOT NULL,
  `asociacion_origen_id` int(11) DEFAULT NULL,
  `asociacion_destino_id` int(11) NOT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_atleta` (`atleta_id`),
  KEY `idx_fecha` (`creado_en`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;

    public static function ensureLogTable(PDO $pdo): void
    {
        try {
            $pdo->exec(self::DDL);
        } catch (PDOException $e) {
            error_log('[TraspasoService] ensureLogTable: ' . $e->getMessage());
        }
    }

    /**
     * Solo administración FVD puede fijar asociación destino arbitraria.
     *
     * @throws RuntimeException
     */
    public static function ejecutar(
        PDO $pdo,
        int $atletaId,
        int $asociacionDestinoId,
        ?int $usuarioId
    ): void {
        if ($atletaId <= 0 || $asociacionDestinoId <= 0) {
            throw new RuntimeException('Datos de traspaso no válidos.');
        }

        if (\AuthService::role() !== \AuthService::ROLE_FVD_ADMIN) {
            throw new RuntimeException('Solo el personal FVD puede registrar traspasos.');
        }

        self::ensureLogTable($pdo);

        $legacy = dirname(__DIR__, 2) . '/fvdmasteradmin/services/QueryHelper.php';
        if (!\class_exists('QueryHelper', false)) {
            require_once $legacy;
        }

        $paramsFind = [':id' => $atletaId];
        $scope = \QueryHelper::asociacionScopeSql('a.asociacion', $paramsFind);
        $sqlFind = 'SELECT a.id, a.asociacion, a.torneo_id FROM atletas a WHERE a.id = :id ' . $scope;
        $st = $pdo->prepare($sqlFind);
        $st->execute($paramsFind);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            throw new RuntimeException('Atleta no encontrado o fuera de su ámbito.');
        }

        $origen = isset($row['asociacion']) && $row['asociacion'] !== null && $row['asociacion'] !== ''
            ? (int) $row['asociacion'] : null;
        if ($origen === $asociacionDestinoId) {
            throw new RuntimeException('El atleta ya pertenece a esa asociación.');
        }

        $stChk = $pdo->prepare('SELECT id FROM asociaciones WHERE id = :id LIMIT 1');
        $stChk->execute([':id' => $asociacionDestinoId]);
        if ($stChk->fetchColumn() === false) {
            throw new RuntimeException('Asociación destino no válida.');
        }

        $paramsUp = [':dest' => $asociacionDestinoId, ':id' => $atletaId];
        $scopeUp = \QueryHelper::asociacionScopeSql('a.asociacion', $paramsUp);
        $sqlUp = 'UPDATE atletas a SET a.asociacion = :dest, a.traspaso = 1 WHERE a.id = :id ' . $scopeUp;

        try {
            $pdo->beginTransaction();
            $stU = $pdo->prepare($sqlUp);
            $stU->execute($paramsUp);
            if ($stU->rowCount() < 1) {
                $pdo->rollBack();
                throw new RuntimeException('No se pudo actualizar el atleta.');
            }

            $stLog = $pdo->prepare(
                'INSERT INTO log_traspasos (atleta_id, asociacion_origen_id, asociacion_destino_id, usuario_id)
                 VALUES (:aid, :orig, :dest, :uid)'
            );
            $stLog->execute([
                ':aid'  => $atletaId,
                ':orig' => $origen,
                ':dest' => $asociacionDestinoId,
                ':uid'  => $usuarioId,
            ]);
            $pdo->commit();
            $torneoId = (int) ($row['torneo_id'] ?? 0);
            if ($torneoId > 0) {
                try {
                    if ($origen !== null && $origen > 0) {
                        DeudaAsociacionGeneratorService::generarParaTorneoYAsociacion($pdo, $torneoId, $origen);
                    }
                    DeudaAsociacionGeneratorService::generarParaTorneoYAsociacion($pdo, $torneoId, $asociacionDestinoId);
                } catch (Throwable $e) {
                    error_log('[TraspasoService] sync deuda: ' . $e->getMessage());
                }
            }
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($e instanceof RuntimeException) {
                throw $e;
            }
            error_log('[TraspasoService] ejecutar: ' . $e->getMessage());
            throw new RuntimeException('Error al registrar el traspaso.');
        }
    }
}
