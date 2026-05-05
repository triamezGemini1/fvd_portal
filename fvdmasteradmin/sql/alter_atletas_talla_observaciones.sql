-- Campos opcionales para el formulario de afiliado (talla de camisa, observaciones).
-- Ejecutar una vez en la misma BD que `atletas`. Si las columnas ya existen, omita este script.

ALTER TABLE `atletas`
    ADD COLUMN `talla_camisa` VARCHAR(16) NULL DEFAULT NULL COMMENT 'Talla de camisa' AFTER `celular`,
    ADD COLUMN `observaciones` TEXT NULL COMMENT 'Notas internas' AFTER `direccion`;
