-- Delegado de prueba (rol delegado_asoc vía tabla `delegados`).
-- Requiere una asociación existente (ej. id 9901 tras test_users / seed_test_users).
-- Contraseña en claro: Delegado2026!
-- Ejecutar en la misma BD que .env o, mejor, usar: php fvdmasteradmin/cli/seed_delegado_prueba.php

INSERT INTO `delegados` (`asociacion_id`, `email_acceso`, `password_hash`, `nombre_contacto`, `activo`)
VALUES (
    9901,
    'delegado.prueba@fvd.local',
    '$2y$10$gev.qNCs1l7wRB74oqCkluyPJUWo77rtH7jlwH9iyeZoZyHVubFuK',
    'Delegado de prueba',
    1
)
ON DUPLICATE KEY UPDATE
    `email_acceso` = VALUES(`email_acceso`),
    `password_hash` = VALUES(`password_hash`),
    `nombre_contacto` = VALUES(`nombre_contacto`),
    `activo` = 1;
