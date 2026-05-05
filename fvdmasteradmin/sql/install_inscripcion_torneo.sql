-- Inscripciones explícitas por torneo (modelo del volcado fvdmasteradminact).
-- Complementa el flujo legado que actualiza campos en `atletas`.

CREATE TABLE IF NOT EXISTS `inscripcion_torneo` (
  `id` int NOT NULL AUTO_INCREMENT,
  `asociacion_id` int NOT NULL COMMENT 'ID de la asociación',
  `torneo_id` int NOT NULL COMMENT 'torneosact.torneo',
  `equipo` int NOT NULL DEFAULT 0 COMMENT 'Número de equipo',
  `cedula` int NOT NULL COMMENT 'Cédula numérica (legado)',
  `nombre` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre_equipo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Nombre pareja/equipo (misma en integrantes del mismo equipo)',
  `numfvd` int NOT NULL DEFAULT 0,
  `sexo` int NOT NULL DEFAULT 0 COMMENT '1=M, 2=F',
  `telefono` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `afiliacion` int NOT NULL DEFAULT 0,
  `anualidad` int NOT NULL DEFAULT 0,
  `carnet` int NOT NULL DEFAULT 0,
  `traspaso` int NOT NULL DEFAULT 0,
  `inscripcion` int NOT NULL DEFAULT 1,
  `fecha_inscripcion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_actualizacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_inscripcion` (`torneo_id`,`cedula`),
  KEY `idx_asociacion_id` (`asociacion_id`),
  KEY `idx_torneo_id` (`torneo_id`),
  KEY `idx_cedula` (`cedula`),
  KEY `idx_numfvd` (`numfvd`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
