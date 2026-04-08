-- Deuda canónica en EUR: totales y abonos en euros; Bs solo como contraparte al tipo BCV en pagos.
-- Tras aplicar: regenerar deudas o asignar monto_total_eur manualmente según corresponda.
-- Las filas antiguas con monto_total_eur NULL siguen usando monto_total/abono en Bs hasta migrar.

ALTER TABLE `deuda_asociaciones`
  ADD COLUMN `monto_total_eur` DECIMAL(14, 6) NULL DEFAULT NULL COMMENT 'Deuda total en EUR' AFTER `monto_total`,
  ADD COLUMN `abono_eur` DECIMAL(14, 6) NULL DEFAULT NULL COMMENT 'Suma de pagos en EUR (monto_dolares)' AFTER `abono`;
