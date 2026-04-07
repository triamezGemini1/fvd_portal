-- Un delegado por asociación (credenciales propias). Ejecutar en la BD del portal.

CREATE TABLE IF NOT EXISTS `delegados` (
  `id` int NOT NULL AUTO_INCREMENT,
  `asociacion_id` int NOT NULL COMMENT 'FK asociaciones.id, único',
  `email_acceso` varchar(255) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `nombre_contacto` varchar(255) NOT NULL DEFAULT '',
  `telefono` varchar(64) DEFAULT NULL,
  `ultimo_acceso` datetime DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `creado_en` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_delegados_asociacion` (`asociacion_id`),
  UNIQUE KEY `uk_delegados_email` (`email_acceso`),
  KEY `idx_delegados_activo` (`activo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
