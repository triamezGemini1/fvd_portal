-- Campos adicionales presentes en el volcado fvdmasteradminact (tabla asociaciones).
-- Requiere MySQL 8.0.12+ / MariaDB 10.3.3+ para IF NOT EXISTS en ADD COLUMN.

ALTER TABLE `asociaciones`
  ADD COLUMN IF NOT EXISTS `providencia` varchar(255) NULL DEFAULT NULL COMMENT 'Providencia / notas territoriales' AFTER `numreg`,
  ADD COLUMN IF NOT EXISTS `indica` int NOT NULL DEFAULT 0 COMMENT 'Indicador interno' AFTER `delegado`,
  ADD COLUMN IF NOT EXISTS `fechreg` date NULL DEFAULT NULL COMMENT 'Fecha registro' AFTER `estatus`,
  ADD COLUMN IF NOT EXISTS `fechprovi` date NULL DEFAULT NULL COMMENT 'Fecha providencia' AFTER `fechreg`,
  ADD COLUMN IF NOT EXISTS `ultelECC` date NULL DEFAULT NULL COMMENT 'Última fecha ECC' AFTER `fechprovi`;
