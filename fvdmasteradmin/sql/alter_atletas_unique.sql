-- Índices UNIQUE en atletas: cédula, email (válidos no nulos) y numfvd solo cuando > 0.
-- Hacer copia de seguridad antes de ejecutar.
-- Si algún ALTER falla, revise duplicados con las consultas comentadas abajo.

-- Normalizar correos “vacíos” o marcadores a NULL (varios NULL están permitidos en UNIQUE).
UPDATE atletas SET email = NULL
WHERE email IS NOT NULL AND (
  TRIM(email) = ''
  OR TRIM(LOWER(email)) IN ('@', '-', 'n/a', 'na', 'sin email', 's/email', '.')
);

UPDATE atletas SET cedula = TRIM(cedula);

-- Comprobar duplicados (debe devolver 0 filas antes de crear UNIQUE):
-- SELECT cedula, COUNT(*) c FROM atletas GROUP BY cedula HAVING c > 1;
-- SELECT email, COUNT(*) c FROM atletas WHERE email IS NOT NULL GROUP BY email HAVING c > 1;
-- SELECT numfvd, COUNT(*) c FROM atletas WHERE numfvd > 0 GROUP BY numfvd HAVING c > 1;

ALTER TABLE atletas ADD UNIQUE KEY uq_atletas_cedula (cedula);

ALTER TABLE atletas ADD UNIQUE KEY uq_atletas_email (email);

-- numfvd: varios registros pueden tener 0 (pendiente); solo valores > 0 deben ser únicos.
-- MySQL 8+ / MariaDB 10.2+ (columna generada). Si falla, adapte la sintaxis a su motor.
ALTER TABLE atletas
  ADD COLUMN numfvd_uq INT
    GENERATED ALWAYS AS (CASE WHEN numfvd > 0 THEN numfvd ELSE NULL END) STORED,
  ADD UNIQUE KEY uq_atletas_numfvd_pos (numfvd_uq);
