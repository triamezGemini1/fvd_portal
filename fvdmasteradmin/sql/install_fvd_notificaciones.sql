-- Notificaciones panel (convocatoria nacional); token_acceso enlaza con fvd_delegado_notif_torneo.access_token

CREATE TABLE IF NOT EXISTS `fvd_notificaciones` (
  `id` int NOT NULL AUTO_INCREMENT,
  `delegado_id` int NOT NULL COMMENT 'delegados.id',
  `torneo_id` int NOT NULL COMMENT 'torneosact.torneo',
  `token_acceso` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `creado_en` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `visto_en` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_delegado_torneo` (`delegado_id`,`torneo_id`),
  UNIQUE KEY `uk_token_acceso` (`token_acceso`),
  KEY `idx_delegado` (`delegado_id`),
  KEY `idx_torneo` (`torneo_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
