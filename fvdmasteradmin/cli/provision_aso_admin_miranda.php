<?php

declare(strict_types=1);

/**
 * Crea o actualiza un usuario fvd_usuarios con rol aso_admin vinculado a la asociación cuyo nombre contiene «Miranda».
 * Ese rol entra al panel estándar (menú lateral a /admin/modules/…), con alcance regional de su club — no al panel reducido de delegado.
 *
 * Uso (desde la raíz del proyecto, **misma máquina / misma BD que el login web**):
 *   php fvdmasteradmin/cli/provision_aso_admin_miranda.php
 *   php fvdmasteradmin/cli/provision_aso_admin_miranda.php --asociacion-id=123
 *   php fvdmasteradmin/cli/provision_aso_admin_miranda.php --password="SuClave"
 *
 * Contraseña: --password (prioridad) > FVD_MIRANDA_ASO_PASSWORD en .env > predeterminada MirandaQA_2026.
 *
 * Usuario de login (campo email): aso_miranda
 */

$root = dirname(__DIR__, 2);
chdir($root);

require_once $root . '/fvdmasteradmin/config/db.php';

$opts = getopt('', ['asociacion-id::', 'password::'], $restIndex);
$forcedId = isset($opts['asociacion-id']) ? max(0, (int) $opts['asociacion-id']) : 0;

$fvdDb = env('FVD_DB_DATABASE', env('DB_DATABASE', '?'));
echo "Base de datos (FVD): {$fvdDb}\n";

try {
    $pdo = fvd_db();
} catch (Throwable $e) {
    echo "ERROR conexión: " . $e->getMessage() . "\n";
    exit(1);
}

$asocId = 0;
$asocNombre = '';

if ($forcedId > 0) {
    $st = $pdo->prepare('SELECT id, nombre FROM asociaciones WHERE id = :id LIMIT 1');
    $st->execute([':id' => $forcedId]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if ($row === false) {
        echo "ERROR: no existe asociaciones.id = {$forcedId}\n";
        exit(1);
    }
    $asocId = (int) $row['id'];
    $asocNombre = (string) ($row['nombre'] ?? '');
} else {
    $st = $pdo->query(
        "SELECT id, nombre FROM asociaciones
         WHERE LOWER(TRIM(nombre)) LIKE '%miranda%'
         ORDER BY id ASC"
    );
    $rows = $st ? $st->fetchAll(PDO::FETCH_ASSOC) : [];
    if ($rows === []) {
        echo "ERROR: no hay ninguna asociación con «Miranda» en el nombre.\n";
        echo "Indique el id manualmente: php fvdmasteradmin/cli/provision_aso_admin_miranda.php --asociacion-id=ID\n";
        echo "O cree la asociación en el CRUD (admin / asociaciones).\n";
        exit(1);
    }
    if (count($rows) > 1) {
        echo "Hay varias coincidencias; use --asociacion-id para elegir una:\n";
        foreach ($rows as $r) {
            echo '  id=' . (int) ($r['id'] ?? 0) . '  ' . trim((string) ($r['nombre'] ?? '')) . "\n";
        }
        exit(1);
    }
    $asocId = (int) ($rows[0]['id'] ?? 0);
    $asocNombre = (string) ($rows[0]['nombre'] ?? '');
}

$plain = '';
if (isset($opts['password']) && is_string($opts['password']) && $opts['password'] !== '') {
    $plain = $opts['password'];
} else {
    $plain = (string) env('FVD_MIRANDA_ASO_PASSWORD', '');
    if ($plain === '') {
        $plain = 'MirandaQA_2026';
    }
}

$loginEmail = 'aso_miranda';
$hash = password_hash($plain, PASSWORD_BCRYPT);
if ($hash === false) {
    echo "ERROR: no se pudo generar hash.\n";
    exit(1);
}

$sql = 'INSERT INTO fvd_usuarios (email, password_hash, nombre, rol, id_asociacion, activo)
    VALUES (:email, :ph, :nombre, :rol, :asoc, 1)
    ON DUPLICATE KEY UPDATE
      password_hash = VALUES(password_hash),
      nombre = VALUES(nombre),
      rol = VALUES(rol),
      id_asociacion = VALUES(id_asociacion),
      activo = 1';

try {
    $ins = $pdo->prepare($sql);
    $ins->execute([
        ':email' => $loginEmail,
        ':ph' => $hash,
        ':nombre' => 'QA Admin Miranda',
        ':rol' => 'aso_admin',
        ':asoc' => $asocId,
    ]);
} catch (Throwable $e) {
    echo 'ERROR al guardar usuario: ' . $e->getMessage() . "\n";
    exit(1);
}

echo "\n--- Listo ---\n";
echo "Asociación: {$asocNombre} (id={$asocId})\n";
echo "Usuario (campo login): {$loginEmail}\n";
$fromCli = isset($opts['password']) && is_string($opts['password']) && $opts['password'] !== '';
$fromEnv = trim((string) env('FVD_MIRANDA_ASO_PASSWORD', '')) !== '';
$src = $fromCli ? '--password' : ($fromEnv ? '.env FVD_MIRANDA_ASO_PASSWORD' : 'predeterminada del script');
echo "Contraseña aplicada (origen: {$src}): {$plain}\n";
echo "Rol: aso_admin — panel con menú lateral; gestión acotada a esta asociación en los módulos /admin/modules/.\n";
echo "Entrada: /fvdmasteradmin/login.php\n";
echo "\nSeguridad: cambie la clave y, si no desea este usuario en producción, desactívelo o bórrelo de fvd_usuarios.\n";
