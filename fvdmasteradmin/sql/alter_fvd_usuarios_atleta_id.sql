-- Vincula cuentas fvd_usuarios con atletas para el portal del atleta.
-- Ejecutar una vez contra la misma base que usa FVD Master Admin (p. ej. fvdmasteradmin).

ALTER TABLE `fvd_usuarios`
    ADD COLUMN `atleta_id` INT UNSIGNED NULL DEFAULT NULL COMMENT 'atletas.id — acceso portal atleta' AFTER `id_asociacion`,
    ADD KEY `idx_fvd_usuarios_atleta_id` (`atleta_id`);
