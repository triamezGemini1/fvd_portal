-- Tabla principal de torneos usada por FVD Master Admin, invitorfvd, gestión financiera, etc.
-- El volcado reference_fvdmasteradminact.sql tenía la sección torneosact truncada; este script reconstruye el esquema esperado por el código PHP.

CREATE TABLE IF NOT EXISTS `torneosact` (
  `torneo` int NOT NULL AUTO_INCREMENT,
  `organizacion_id` int UNSIGNED DEFAULT NULL COMMENT 'asociaciones.id organizadora',
  `clavetor` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nombre` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `lugar` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fechator` date DEFAULT NULL,
  `tipo` int DEFAULT NULL COMMENT '1=Masculino, 2=Femenino, 3=Mixto',
  `es_campeonato` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1=campeonato (agrupa categorías), 0=torneo',
  `clase` int DEFAULT NULL COMMENT '1=Ind, 2=Parejas, 3=Equipos',
  `tiempo` int NOT NULL DEFAULT 0,
  `puntos` int NOT NULL DEFAULT 0,
  `rondas` int NOT NULL DEFAULT 0,
  `estatus` int NOT NULL DEFAULT 0,
  `costotor` decimal(12,2) DEFAULT NULL,
  `ranking` int NOT NULL DEFAULT 0,
  `pareclub` int NOT NULL DEFAULT 0,
  `invitacion` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `afiche` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `publicar_landing` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1 = visible en sitio público',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`torneo`),
  KEY `idx_organizacion_id` (`organizacion_id`),
  KEY `idx_fechator` (`fechator`),
  KEY `idx_estatus` (`estatus`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
