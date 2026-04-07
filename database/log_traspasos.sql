-- Auditoría de traspasos de atletas entre asociaciones (ejecutar una vez en MySQL/MariaDB).
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
