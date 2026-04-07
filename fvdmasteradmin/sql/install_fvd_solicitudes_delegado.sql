-- Solicitudes de delegados (traspaso / carnet / afiliación) pendientes de aprobación FVD.

CREATE TABLE IF NOT EXISTS `fvd_solicitudes_delegado` (
  `id` int NOT NULL AUTO_INCREMENT,
  `tipo` enum('traspaso','carnet','afiliacion') NOT NULL,
  `asociacion_id` int NOT NULL,
  `atleta_id` int NOT NULL,
  `delegado_id` int DEFAULT NULL COMMENT 'delegados.id',
  `asociacion_destino_id` int DEFAULT NULL COMMENT 'solo traspaso',
  `nota` varchar(512) DEFAULT NULL,
  `estado` enum('pendiente','aprobada','rechazada') NOT NULL DEFAULT 'pendiente',
  `creado_en` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `resuelto_en` datetime DEFAULT NULL,
  `resuelto_por_user_id` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_fvd_sol_estado` (`estado`),
  KEY `idx_fvd_sol_asoc` (`asociacion_id`),
  KEY `idx_fvd_sol_atleta` (`atleta_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
