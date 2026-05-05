-- Campeonatos: agrupa varios torneos (torneosact) bajo un mismo grupo_evento_id.
-- Notificaciones delegados: avisos de novedad (p. ej. torneo nuevo) independientes de fvd_delegado_notif_torneo.

CREATE TABLE IF NOT EXISTS `fvd_campeonatos` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `nombre_base` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `plantilla` enum('adulto_mf','sub_categorias') COLLATE utf8mb4_unicode_ci NOT NULL,
  `grupo_evento_id` int unsigned DEFAULT NULL COMMENT 'Mismo código que torneosact.grupo_evento_id',
  `creado_en` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_fvd_campeonatos_grupo` (`grupo_evento_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `fvd_campeonato_torneo` (
  `campeonato_id` int unsigned NOT NULL,
  `torneo_id` int NOT NULL,
  `orden` smallint NOT NULL DEFAULT 0,
  `etiqueta` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`campeonato_id`,`torneo_id`),
  KEY `idx_campeonato_torneo_tid` (`torneo_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `fvd_campeonato_sub_categoria` (
  `id` smallint unsigned NOT NULL AUTO_INCREMENT,
  `codigo` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `etiqueta` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `orden` smallint NOT NULL DEFAULT 0,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_fvd_campeonato_sub_codigo` (`codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `fvd_campeonato_sub_categoria` (`codigo`, `etiqueta`, `orden`, `activo`) VALUES
  ('SUB12', 'Sub-12', 1, 1),
  ('SUB15', 'Sub-15', 2, 1),
  ('SUB18', 'Sub-18', 3, 1);

-- Tabla lógica "notificaciones_delegados" en esquema FVD (prefijo fvd_)
CREATE TABLE IF NOT EXISTS `fvd_notificaciones_delegados` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `delegado_id` int NOT NULL COMMENT 'delegados.id',
  `tipo` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'torneo_nuevo',
  `mensaje` varchar(512) COLLATE utf8mb4_unicode_ci NOT NULL,
  `torneo_id` int DEFAULT NULL,
  `campeonato_id` int unsigned DEFAULT NULL,
  `leido_en` datetime DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_fvd_notif_del_delegado` (`delegado_id`,`leido_en`),
  KEY `idx_fvd_notif_del_torneo` (`torneo_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
