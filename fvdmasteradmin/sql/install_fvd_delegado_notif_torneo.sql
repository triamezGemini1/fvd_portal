-- Notificaciones web para delegados por torneo (PDF de invitación + acceso al panel del evento).
-- Ejecutar en la misma base que `delegados` y `torneosact`.

CREATE TABLE IF NOT EXISTS `fvd_delegado_notif_torneo` (
  `id` int NOT NULL AUTO_INCREMENT,
  `delegado_id` int NOT NULL COMMENT 'delegados.id',
  `torneo_id` int NOT NULL COMMENT 'torneosact.torneo',
  `invitacion_archivo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Nombre de archivo en uploads/ al momento del aviso',
  `creado_en` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `visto_en` timestamp NULL DEFAULT NULL COMMENT 'Primera apertura del panel del torneo desde la notificación',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_delegado_torneo` (`delegado_id`,`torneo_id`),
  KEY `idx_delegado` (`delegado_id`),
  KEY `idx_torneo` (`torneo_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
