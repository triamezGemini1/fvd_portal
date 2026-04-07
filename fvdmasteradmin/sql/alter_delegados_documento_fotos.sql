-- Número de cédula y archivos de imagen para perfil del delegado.
-- Ejecutar si ya existe la tabla `delegados`.

ALTER TABLE `delegados`
  ADD COLUMN `documento_identidad` VARCHAR(32) NULL DEFAULT NULL COMMENT 'Número de cédula',
  ADD COLUMN `foto_carnet` VARCHAR(255) NULL DEFAULT NULL COMMENT 'Ruta en uploads/',
  ADD COLUMN `foto_cedula` VARCHAR(255) NULL DEFAULT NULL COMMENT 'Imagen de la cédula';
