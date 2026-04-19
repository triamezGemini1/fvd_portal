<?php

/**
 * Crea/actualiza usuarios de prueba en `fvd_usuarios` (password_hash en PHP).
 * Contraseña común: ver FVD_TEST_PASSWORD en fvdmasteradmin/config/test_accounts.php
 *
 * Uso (desde la raíz del proyecto): php fvdmasteradmin/cli/seed_test_users.php
 */
declare(strict_types=1);

$root = dirname(__DIR__, 2);
chdir($root);

require_once $root . '/fvdmasteradmin/config/db.php';
require_once dirname(__DIR__) . '/config/test_accounts.php';

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
    `atleta_id` INT UNSIGNED NULL DEFAULT NULL,
    `activo` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_fvd_usuarios_email` (`email`),
    KEY `idx_fvd_usuarios_rol` (`rol`),
    KEY `idx_fvd_usuarios_id_asociacion` (`id_asociacion`),
    KEY `idx_fvd_usuarios_atleta_id` (`atleta_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;

$pdo->exec($sqlCreate);
echo "Tabla fvd_usuarios: OK\n";

try {
    $pdo->exec('ALTER TABLE fvd_usuarios ADD COLUMN atleta_id INT UNSIGNED NULL DEFAULT NULL AFTER id_asociacion');
    echo "Columna atleta_id: añadida\n";
} catch (Throwable $e) {
    if (stripos($e->getMessage(), 'Duplicate column') === false) {
        echo "Aviso atleta_id: " . $e->getMessage() . "\n";
    }
}

try {
    $pdo->exec(
        "INSERT INTO asociaciones (id, nombre, direccion, telefono, email, numreg, delegado, estatus, logo) VALUES
        (9901, 'Asociación Prueba Región A', 'Prueba', '', 'asoc-a@test.fvd', 'REG-9901', 'Delegado A', 'activo', NULL),
        (9902, 'Asociación Prueba Región B', 'Prueba', '', 'asoc-b@test.fvd', 'REG-9902', 'Delegado B', 'activo', NULL)
        ON DUPLICATE KEY UPDATE nombre = VALUES(nombre), delegado = VALUES(delegado), estatus = VALUES(estatus)"
    );
    echo "Asociaciones 9901/9902: OK\n";
} catch (Throwable $e) {
    echo "Aviso asociaciones: " . $e->getMessage() . "\n";
}

$atletaIdPortal = 0;
try {
    $st = $pdo->query('SELECT id FROM atletas WHERE asociacion = 9901 ORDER BY id ASC LIMIT 1');
    if ($st !== false) {
        $atletaIdPortal = (int) $st->fetchColumn();
    }
    if ($atletaIdPortal <= 0) {
        $st2 = $pdo->query('SELECT id FROM atletas WHERE asociacion = 9902 ORDER BY id ASC LIMIT 1');
        if ($st2 !== false) {
            $atletaIdPortal = (int) $st2->fetchColumn();
        }
    }
} catch (Throwable $e) {
    echo "Aviso lectura atletas: " . $e->getMessage() . "\n";
}

if ($atletaIdPortal <= 0) {
    try {
        $pdo->exec(
            "INSERT INTO atletas (cedula, nombre, sexo, numfvd, asociacion, torneo_id, estatus, afiliacion, anualidad, carnet, traspaso, inscripcion, categ, email)
             VALUES ('V-FVD-PRUEBA-PORTAL', 'Atleta Prueba Portal', 1, 0, 9901, 0, 1, 1, 1, 1, 0, 0, 0, 'prueba.atleta@test.fvd')"
        );
        $atletaIdPortal = (int) $pdo->lastInsertId();
        echo "Atleta de prueba para portal: id {$atletaIdPortal}\n";
    } catch (Throwable $e) {
        echo "AVISO: no se pudo crear atleta de prueba (portal atleta omitido): " . $e->getMessage() . "\n";
    }
} else {
    echo "atleta_id para portal atleta: {$atletaIdPortal}\n";
}

$plain = FVD_TEST_PASSWORD;
$users = [
    ['prueba.fvd.admin@test.fvd', $plain, 'Admin prueba FVD', 'fvd_admin', null, null],
    ['prueba.aso.admin@test.fvd', $plain, 'Admin asociación prueba', 'aso_admin', 9901, null],
    ['prueba.usuario@test.fvd', $plain, 'Usuario prueba', 'usuario', 9901, null],
];

if ($atletaIdPortal > 0) {
    $users[] = ['prueba.atleta@test.fvd', $plain, 'Atleta portal prueba', 'usuario', 9901, $atletaIdPortal];
}

$ins = $pdo->prepare(
    'INSERT INTO fvd_usuarios (email, password_hash, nombre, rol, id_asociacion, atleta_id, activo)
     VALUES (:email, :ph, :nombre, :rol, :asoc, :atl, 1)
     ON DUPLICATE KEY UPDATE
       password_hash = VALUES(password_hash),
       nombre = VALUES(nombre),
       rol = VALUES(rol),
       id_asociacion = VALUES(id_asociacion),
       atleta_id = VALUES(atleta_id),
       activo = 1'
);

foreach ($users as $u) {
    $hash = password_hash($u[1], PASSWORD_DEFAULT);
    if ($hash === false) {
        echo "ERROR password_hash para {$u[0]}\n";
        continue;
    }
    $ins->execute([
        ':email' => $u[0],
        ':ph' => $hash,
        ':nombre' => $u[2],
        ':rol' => $u[3],
        ':asoc' => $u[4],
        ':atl' => $u[5],
    ]);
    echo "Usuario {$u[0]} ({$u[3]}): OK\n";
}

echo "\nListo. Contraseña común: {$plain}\n";
