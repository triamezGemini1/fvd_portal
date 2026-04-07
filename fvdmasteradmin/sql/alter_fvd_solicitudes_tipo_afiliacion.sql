-- Añade tipo 'afiliacion' a la cola de solicitudes de delegados (ejecutar si la tabla ya existía solo con traspaso/carnet).

ALTER TABLE `fvd_solicitudes_delegado`
  MODIFY COLUMN `tipo` enum('traspaso','carnet','afiliacion') NOT NULL;
