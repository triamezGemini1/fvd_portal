-- Agrupa torneos simultáneos (género, subcategorías) para el selector del delegado.
-- Histórico de movimientos al dar un torneo por concluido.

ALTER TABLE `torneosact`
  ADD COLUMN `grupo_evento_id` INT UNSIGNED NULL DEFAULT NULL COMMENT 'Mismo ID = mismo evento (varios torneos)' AFTER `torneo`,
  ADD COLUMN `apertura_anual` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = al crear: marcar anualidad=1 en todos los atletas' AFTER `grupo_evento_id`,
  ADD COLUMN `finalizado_en` DATETIME NULL DEFAULT NULL COMMENT 'Al concluir torneo: cierre y snapshot' AFTER `apertura_anual`;

CREATE TABLE IF NOT EXISTS `torneo_movimiento_historico` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `torneo_id` INT NOT NULL,
  `atleta_id` INT NULL DEFAULT NULL,
  `numfvd` INT NOT NULL DEFAULT 0,
  `tipo` VARCHAR(32) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'anualidad,carnet,afiliacion,traspaso,inscripcion_bandera,inscripcion_tabla',
  `valor_anterior` VARCHAR(64) COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `valor_nuevo` VARCHAR(64) COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `notas` VARCHAR(512) COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_torneo_mov_torneo` (`torneo_id`),
  KEY `idx_torneo_mov_numfvd` (`numfvd`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
