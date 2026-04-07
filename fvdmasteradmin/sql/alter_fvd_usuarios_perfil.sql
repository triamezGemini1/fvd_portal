-- Datos personales extendidos para Mi perfil (FVD Master Admin).
-- Ejecutar una vez si la tabla fvd_usuarios ya existe.

ALTER TABLE `fvd_usuarios`
    ADD COLUMN `apellidos` VARCHAR(120) NULL DEFAULT NULL AFTER `nombre`,
    ADD COLUMN `telefono` VARCHAR(40) NULL DEFAULT NULL AFTER `apellidos`,
    ADD COLUMN `documento_identidad` VARCHAR(64) NULL DEFAULT NULL AFTER `telefono`,
    ADD COLUMN `direccion` VARCHAR(255) NULL DEFAULT NULL AFTER `documento_identidad`,
    ADD COLUMN `ciudad` VARCHAR(120) NULL DEFAULT NULL AFTER `direccion`,
    ADD COLUMN `fecha_nacimiento` DATE NULL DEFAULT NULL AFTER `ciudad`;

-- Después, para foto y sexo:
-- fvdmasteradmin/sql/alter_fvd_usuarios_foto_sexo.sql
