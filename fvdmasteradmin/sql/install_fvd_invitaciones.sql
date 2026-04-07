-- Invitaciones de registro (atleta / club). Ejecutar en la BD del portal.
-- El enlace público usa solo `token` opaco (no expone id incremental).

CREATE TABLE IF NOT EXISTS `fvd_invitaciones` (
  `id` int NOT NULL AUTO_INCREMENT,
  `token` varchar(64) NOT NULL COMMENT 'Token opaco (hex); único en URL',
  `tipo` enum('atleta','club') NOT NULL DEFAULT 'atleta',
  `documento` varchar(48) NOT NULL COMMENT 'Cédula o RIF normalizado (anti-duplicado)',
  `email_destino` varchar(255) DEFAULT NULL,
  `asociacion_emisor_id` int DEFAULT NULL COMMENT 'Delegado: su asociación; FVD: NULL',
  `estado` enum('pendiente','aceptada') NOT NULL DEFAULT 'pendiente',
  `un_solo_uso` tinyint(1) NOT NULL DEFAULT 1,
  `usada_en` datetime DEFAULT NULL,
  `expira_en` datetime NOT NULL,
  `titulo_memo` varchar(255) DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `creado_por_user_id` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_fvd_inv_token` (`token`),
  KEY `idx_fvd_inv_expira` (`expira_en`),
  KEY `idx_fvd_inv_emisor` (`asociacion_emisor_id`),
  KEY `idx_fvd_inv_estado` (`estado`),
  KEY `idx_fvd_inv_doc_tipo` (`tipo`,`documento`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
