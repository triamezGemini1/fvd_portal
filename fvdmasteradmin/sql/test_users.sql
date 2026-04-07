-- =============================================================================
-- Prueba de acceso FVD Master Admin — credenciales simples
-- =============================================================================
-- RECOMENDADO (hashes siempre correctos): desde la raíz del proyecto ejecute:
--   php fvdmasteradmin/cli/seed_test_users.php
-- Ese script crea la tabla si falta y actualiza Trinoamez / Asociacion / usuario.
-- =============================================================================
-- Usuario (campo email en BD) | Contraseña | Rol
--   Trinoamez                  | npi$2025   | fvd_admin
--   Asociacion                 | npi$2025   | aso_admin  (asociación 9901)
--   usuario                    | npi$2025   | usuario    (asociación 9901)
--
-- Hash bcrypt (password_hash con PASSWORD_BCRYPT): misma clave para los tres.
--
-- Asociaciones de prueba: IDs 9901 (región A) y 9902 (región B).
-- 5 atletas en 9901 y 5 en 9902 (cédulas V-TEST-...).
--
-- Ejecutar en la misma base que define .env.
-- Login: escriba el usuario en el primer campo (no hace falta formato correo).
-- =============================================================================

-- Si venía de la versión anterior con correos @fvd.local, elimine esas filas una vez:
-- DELETE FROM fvd_usuarios WHERE email IN (
--   'admin.test@fvd.local', 'aso.test@fvd.local', 'usuario.test@fvd.local'
-- );

-- Limpieza opcional (descomente si repite el script completo):
-- DELETE FROM atletas WHERE cedula LIKE 'V-TEST-%';
-- DELETE FROM fvd_usuarios WHERE email IN ('Trinoamez', 'Asociacion', 'usuario', 'adminfvd', 'asociafvd', 'usuariofvd');
-- DELETE FROM asociaciones WHERE id IN (9901, 9902);

INSERT INTO `asociaciones` (`id`, `nombre`, `direccion`, `telefono`, `email`, `numreg`, `delegado`, `estatus`, `logo`)
VALUES
    (9901, 'Asociación Prueba Región A', 'Dirección prueba A', '', 'asoc-a@test.fvd', 'REG-9901', 'Delegado A', 'activo', NULL),
    (9902, 'Asociación Prueba Región B', 'Dirección prueba B', '', 'asoc-b@test.fvd', 'REG-9902', 'Delegado B', 'activo', NULL)
ON DUPLICATE KEY UPDATE
    `nombre` = VALUES(`nombre`),
    `delegado` = VALUES(`delegado`),
    `estatus` = VALUES(`estatus`);

INSERT INTO `atletas` (
    `cedula`, `nombre`, `sexo`, `numfvd`, `asociacion`, `torneo_id`, `estatus`,
    `afiliacion`, `anualidad`, `carnet`, `traspaso`, `inscripcion`, `categ`,
    `profesion`, `direccion`, `celular`, `email`, `fechnac`, `fechfvd`, `fechact`, `foto`, `cedula_img`
) VALUES
    ('V-TEST-99001001', 'Atleta Prueba A1', 'M', 0, 9901, 0, 'Activo', 1, 1, 1, 0, 0, 0, NULL, NULL, '', 'a1@test.fvd', NULL, NULL, NULL, NULL, NULL),
    ('V-TEST-99001002', 'Atleta Prueba A2', 'F', 0, 9901, 0, 'Activo', 1, 1, 1, 0, 0, 0, NULL, NULL, '', 'a2@test.fvd', NULL, NULL, NULL, NULL, NULL),
    ('V-TEST-99001003', 'Atleta Prueba A3', 'M', 0, 9901, 0, 'Suspendido', 1, 1, 1, 0, 0, 0, NULL, NULL, '', 'a3@test.fvd', NULL, NULL, NULL, NULL, NULL),
    ('V-TEST-99001004', 'Atleta Prueba A4', 'F', 0, 9901, 0, 'Activo', 1, 1, 1, 0, 0, 0, NULL, NULL, '', 'a4@test.fvd', NULL, NULL, NULL, NULL, NULL),
    ('V-TEST-99001005', 'Atleta Prueba A5', 'M', 0, 9901, 0, 'Activo', 1, 1, 1, 0, 0, 0, NULL, NULL, '', 'a5@test.fvd', NULL, NULL, NULL, NULL, NULL);

INSERT INTO `atletas` (
    `cedula`, `nombre`, `sexo`, `numfvd`, `asociacion`, `torneo_id`, `estatus`,
    `afiliacion`, `anualidad`, `carnet`, `traspaso`, `inscripcion`, `categ`,
    `profesion`, `direccion`, `celular`, `email`, `fechnac`, `fechfvd`, `fechact`, `foto`, `cedula_img`
) VALUES
    ('V-TEST-99002001', 'Atleta Prueba B1', 'M', 0, 9902, 0, 'Activo', 1, 1, 1, 0, 0, 0, NULL, NULL, '', 'b1@test.fvd', NULL, NULL, NULL, NULL, NULL),
    ('V-TEST-99002002', 'Atleta Prueba B2', 'F', 0, 9902, 0, 'Activo', 1, 1, 1, 0, 0, 0, NULL, NULL, '', 'b2@test.fvd', NULL, NULL, NULL, NULL, NULL),
    ('V-TEST-99002003', 'Atleta Prueba B3', 'M', 0, 9902, 0, 'Activo', 1, 1, 1, 0, 0, 0, NULL, NULL, '', 'b3@test.fvd', NULL, NULL, NULL, NULL, NULL),
    ('V-TEST-99002004', 'Atleta Prueba B4', 'F', 0, 9902, 0, 'Activo', 1, 1, 1, 0, 0, 0, NULL, NULL, '', 'b4@test.fvd', NULL, NULL, NULL, NULL, NULL),
    ('V-TEST-99002005', 'Atleta Prueba B5', 'M', 0, 9902, 0, 'Activo', 1, 1, 1, 0, 0, 0, NULL, NULL, '', 'b5@test.fvd', NULL, NULL, NULL, NULL, NULL);

INSERT INTO `fvd_usuarios` (`email`, `password_hash`, `nombre`, `rol`, `id_asociacion`, `activo`)
VALUES
    (
        'Trinoamez',
        '$2y$10$cbtQe2DAEIrTfkQazpDrYu75lyNGWgSxTUr3ZwBFxK7ZqkdqxMm6y',
        'Trinoamez',
        'fvd_admin',
        NULL,
        1
    ),
    (
        'Asociacion',
        '$2y$10$cbtQe2DAEIrTfkQazpDrYu75lyNGWgSxTUr3ZwBFxK7ZqkdqxMm6y',
        'Asociación',
        'aso_admin',
        9901,
        1
    ),
    (
        'usuario',
        '$2y$10$cbtQe2DAEIrTfkQazpDrYu75lyNGWgSxTUr3ZwBFxK7ZqkdqxMm6y',
        'Usuario',
        'usuario',
        9901,
        1
    )
ON DUPLICATE KEY UPDATE
    `password_hash` = VALUES(`password_hash`),
    `nombre` = VALUES(`nombre`),
    `rol` = VALUES(`rol`),
    `id_asociacion` = VALUES(`id_asociacion`),
    `activo` = 1;
