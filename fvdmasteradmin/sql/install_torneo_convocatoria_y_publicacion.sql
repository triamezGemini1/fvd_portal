-- Publicación en landing y convocatoria por asociación (invitaciones + seguimiento).
-- Ejecutar tras install_torneosact.sql.
-- Si la columna publicar_landing ya existe, omita el bloque ALTER (MySQL devolverá error 1060).

ALTER TABLE `torneosact`
  ADD COLUMN `publicar_landing` tinyint(1) NOT NULL DEFAULT 1
    COMMENT '1 = visible en sitio público (próximos/calendario/en vivo)'
    AFTER `afiche`;

CREATE TABLE IF NOT EXISTS `torneo_convocatoria_asoc` (
  `id` int NOT NULL AUTO_INCREMENT,
  `torneo_id` int NOT NULL COMMENT 'torneosact.torneo',
  `asociacion_id` int NOT NULL COMMENT 'asociaciones.id',
  `invitado_en` timestamp NULL DEFAULT NULL COMMENT 'Registro de invitación enviada (auditoría)',
  `estado_respuesta` varchar(24) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pendiente'
    COMMENT 'pendiente, aceptada, declinada',
  `notas` varchar(512) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_torneo_asoc` (`torneo_id`,`asociacion_id`),
  KEY `idx_torneo` (`torneo_id`),
  KEY `idx_asoc` (`asociacion_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
