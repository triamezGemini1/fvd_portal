-- Cupo máximo de plazas (filas en inscripcion_torneo) por asociación y torneo.
-- NULL = sin límite explícito. Ejecutar una vez si la columna no existe.

ALTER TABLE `torneo_convocatoria_asoc`
  ADD COLUMN `cupo_max_inscripciones` int DEFAULT NULL
    COMMENT 'Máx. inscripciones de la asociación en este torneo; NULL = ilimitado'
    AFTER `notas`;
