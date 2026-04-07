<?php
/**
 * Crea/actualiza usuarios de prueba con password_hash() en PHP (evita errores al pegar SQL).
 * Uso (desde la raíz del proyecto): php fvdmasteradmin/cli/seed_test_users.php
 */
declare(strict_types=1);

$root = dirname(__DIR__, 2);
chdir($root);

require_once $root . '/fvdmasteradmin/config/db.php';

$fvdDb = env('FVD_DB_DATABASE', env('DB_DATABASE', '?'));
echo "Base de datos (FVD): {$fvdDb}\n";

try {
    $pdo = fvd_db();
} catch (Throwable $e) {
    echo "ERROR conexión: " . $e->getMessage() . "\n";
    exit(1);
}

$sqlCreate = <<<'SQL'
CREATE TABLE IF NOT EXISTS `fvd_usuarios` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `email` VARCHAR(190) NOT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `nombre` VARCHAR(120) NULL DEFAULT NULL,
    `rol` ENUM('fvd_admin', 'aso_admin', 'usuario') NOT NULL DEFAULT 'usuario',
    `id_asociacion` INT UNSIGNED NULL DEFAULT NULL,
    `activo` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_fvd_usuarios_email` (`email`),
    KEY `idx_fvd_usuarios_rol` (`rol`),
    KEY `idx_fvd_usuarios_id_asociacion` (`id_asociacion`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;

$pdo->exec($sqlCreate);
echo "Tabla fvd_usuarios: OK\n";

try {
    $pdo->exec(
        "INSERT INTO asociaciones (id, nombre, direccion, telefono, email, numreg, delegado, estatus, logo) VALUES
        (9901, 'Asociación Prueba Región A', 'Prueba', '', 'asoc-a@test.fvd', 'REG-9901', 'Delegado A', 'activo', NULL),
        (9902, 'Asociación Prueba Región B', 'Prueba', '', 'asoc-b@test.fvd', 'REG-9902', 'Delegado B', 'activo', NULL)
        ON DUPLICATE KEY UPDATE nombre = VALUES(nombre), delegado = VALUES(delegado), estatus = VALUES(estatus)"
    );
    echo "Asociaciones 9901/9902: OK\n";
} catch (Throwable $e) {
    echo "Aviso asociaciones (opcional): " . $e->getMessage() . "\n";
}

$plain = 'npi$2025';
$users = [
    ['Trinoamez', $plain, 'Trinoamez', 'fvd_admin', null],
    ['Asociacion', $plain, 'Asociación', 'aso_admin', 9901],
    ['usuario', $plain, 'Usuario', 'usuario', 9901],
];

$ins = $pdo->prepare(
    'INSERT INTO fvd_usuarios (email, password_hash, nombre, rol, id_asociacion, activo)
     VALUES (:email, :ph, :nombre, :rol, :asoc, 1)
     ON DUPLICATE KEY UPDATE
       password_hash = VALUES(password_hash),
       nombre = VALUES(nombre),
       rol = VALUES(rol),
       id_asociacion = VALUES(id_asociacion),
       activo = 1'
);

foreach ($users as $u) {
    $hash = password_hash($u[1], PASSWORD_BCRYPT);
    $ins->execute([
        ':email' => $u[0],
        ':ph' => $hash,
        ':nombre' => $u[2],
        ':rol' => $u[3],
        ':asoc' => $u[4],
    ]);
    $ok = password_verify($u[1], $hash);
    echo "Usuario {$u[0]}: guardado, verify local: " . ($ok ? 'OK' : 'FALLO') . "\n";
}

echo "\nListo. Pruebe en el navegador con las mismas claves.\n";
