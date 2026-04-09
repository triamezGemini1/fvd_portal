bbbb11


-- Foto de perfil y sexo (ejecutar si ya tiene la tabla sin estos campos).
ALTER TABLE `fvd_usuarios`
    ADD COLUMN `sexo` ENUM('M', 'F') NULL DEFAULT NULL AFTER `fecha_nacimiento`,
    ADD COLUMN `foto` VARCHAR(255) NULL DEFAULT NULL AFTER `sexo`;
