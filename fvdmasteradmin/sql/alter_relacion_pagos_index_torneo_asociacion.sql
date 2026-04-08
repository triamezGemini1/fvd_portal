-- Índice para acelerar agregaciones de pagos por torneo/asociación.
-- Seguro de ejecutar varias veces: solo crea el índice si no existe.

SET @idx_exists := (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'relacion_pagos'
      AND index_name = 'idx_relacion_pagos_torneo_asociacion'
);

SET @sql := IF(
    @idx_exists = 0,
    'ALTER TABLE relacion_pagos ADD INDEX idx_relacion_pagos_torneo_asociacion (torneo_id, asociacion_id)',
    'SELECT "idx_relacion_pagos_torneo_asociacion ya existe"'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
