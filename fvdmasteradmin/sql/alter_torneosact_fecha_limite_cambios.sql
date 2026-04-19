ALTER TABLE `torneosact`
  ADD COLUMN `fecha_limite_cambios` date DEFAULT NULL COMMENT 'Tras esta fecha: nómina solo consulta (delegado)';
