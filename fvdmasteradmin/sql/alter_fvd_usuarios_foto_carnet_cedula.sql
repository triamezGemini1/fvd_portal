-- Foto tipo carnet e imagen del documento de identidad (Mi perfil).
-- Ejecutar en la BD del portal si ya existe `fvd_usuarios`.

ALTER TABLE `fvd_usuarios`
  ADD COLUMN `foto_carnet` VARCHAR(255) NULL DEFAULT NULL COMMENT 'Ruta relativa en uploads/',
  ADD COLUMN `foto_cedula` VARCHAR(255) NULL DEFAULT NULL COMMENT 'Imagen escaneada/foto de la cédula';
