-- Maestro de nombres nominales por grupo de evento (varios campeonatos vinculados).
CREATE TABLE IF NOT EXISTS `fvd_campeonato_grupo` (
  `grupo_evento_id` int unsigned NOT NULL,
  `nombre_nominal` varchar(255) NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`grupo_evento_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
