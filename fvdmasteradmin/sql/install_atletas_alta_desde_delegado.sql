-- Alta de atletas por delegado: bandera para revisión del administrador general FVD.
-- (El servicio PHP también intenta ALTER automático si la columna no existe.)

ALTER TABLE `atletas`
  ADD COLUMN `alta_desde_delegado` TINYINT(1) NOT NULL DEFAULT 0
  COMMENT '1=alta ingresada por delegado, pendiente validación FVD'
  AFTER `estatus`;
