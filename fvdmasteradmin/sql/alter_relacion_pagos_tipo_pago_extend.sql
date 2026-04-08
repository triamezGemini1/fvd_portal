-- Ampliar tipo_pago para códigos detallados (efectivo/transferencia × Bs/divisas, pago móvil Bs).
-- Ejecutar una vez en la base del master admin.

ALTER TABLE `relacion_pagos`
  MODIFY COLUMN `tipo_pago` VARCHAR(48) NOT NULL DEFAULT 'efectivo_bs';
