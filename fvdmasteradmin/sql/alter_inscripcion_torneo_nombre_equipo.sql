-- Nombre de equipo / pareja en inscripciones por tabla (canal sitio).
-- Ejecutar una vez en bases que ya tengan `inscripcion_torneo`.

ALTER TABLE `inscripcion_torneo`
  ADD COLUMN `nombre_equipo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
  COMMENT 'Nombre de la pareja o del equipo (misma fila en todos los integrantes del mismo número equipo)'
  AFTER `nombre`;
