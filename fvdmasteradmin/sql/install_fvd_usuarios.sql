-- FVD Master Admin: usuarios y roles regionales
-- Ejecutar una vez contra la base del proyecto (mismo esquema que convenva / .env).

CREATE TABLE IF NOT EXISTS `fvd_usuarios` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `email` VARCHAR(190) NOT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `nombre` VARCHAR(120) NULL DEFAULT NULL,
    `apellidos` VARCHAR(120) NULL DEFAULT NULL,
    `telefono` VARCHAR(40) NULL DEFAULT NULL,
    `documento_identidad` VARCHAR(64) NULL DEFAULT NULL,
    `direccion` VARCHAR(255) NULL DEFAULT NULL,
    `ciudad` VARCHAR(120) NULL DEFAULT NULL,
    `fecha_nacimiento` DATE NULL DEFAULT NULL,
    `sexo` ENUM('M', 'F') NULL DEFAULT NULL,
    `foto` VARCHAR(255) NULL DEFAULT NULL,
    `rol` ENUM('fvd_admin', 'aso_admin', 'usuario') NOT NULL DEFAULT 'usuario',
    `id_asociacion` INT UNSIGNED NULL DEFAULT NULL COMMENT 'Filtrado regional; NULL permitido para fvd_admin',
    `atleta_id` INT UNSIGNED NULL DEFAULT NULL COMMENT 'atletas.id — portal atleta',
    `activo` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_fvd_usuarios_email` (`email`),
    KEY `idx_fvd_usuarios_rol` (`rol`),
    KEY `idx_fvd_usuarios_id_asociacion` (`id_asociacion`),
    KEY `idx_fvd_usuarios_atleta_id` (`atleta_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Primer usuario (ejemplo): generar hash en PHP:
--   php -r "echo password_hash('TU_CLAVE', PASSWORD_DEFAULT), PHP_EOL;"
-- INSERT INTO fvd_usuarios (email, password_hash, nombre, rol, id_asociacion, activo)
-- VALUES ('admin@fvd.local', 'PEGAR_HASH_AQUI', 'Administrador', 'fvd_admin', NULL, 1);
