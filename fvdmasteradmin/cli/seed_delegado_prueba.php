<?php
/**
 * Crea o actualiza UN delegado de prueba (rol delegado_asoc al iniciar sesión).
 *
 * Requisito: exista al menos una fila en `asociaciones`. Usa la asociación con id más bajo
 * (o ASOCIACION_ID en entorno si la define).
 *
 * Credenciales por defecto (cambiar en producción):
 *   Email (campo login): delegado.prueba@fvd.local
 *   Contraseña:           Delegado2026!
 *
 * Uso (desde la raíz del proyecto):
 *   php fvdmasteradmin/cli/seed_delegado_prueba.php
 */
declare(strict_types=1);

$root = dirname(__DIR__, 2);
chdir($root);

require_once $root . '/fvdmasteradmin/config/db.php';

$email = 'delegado.prueba@fvd.local';
$plain = 'Delegado2026!';
$forcedAid = (int) (getenv('ASOCIACION_ID') ?: 0);

try {
    $pdo = fvd_db();
} catch (Throwable $e) {
    fwrite(STDERR, 'ERROR conexión: ' . $e->getMessage() . "\n");
    exit(1);
}

$ddl = file_get_contents($root . '/fvdmasteradmin/sql/install_delegados.sql');
if ($ddl !== false && strpos($ddl, 'CREATE TABLE') !== false) {
    $pdo->exec($ddl);
}

$aid = $forcedAid;
if ($aid <= 0) {
    $st = $pdo->query('SELECT id FROM asociaciones ORDER BY id ASC LIMIT 1');
    $aid = $st ? (int) $st->fetchColumn() : 0;
}
if ($aid <= 0) {
    fwrite(STDERR, "No hay asociaciones. Cree una en el CRUD o ejecute seed_test_users.sql / seed_test_users.php.\n");
    exit(1);
}

$hash = password_hash($plain, PASSWORD_DEFAULT);
if ($hash === false) {
    fwrite(STDERR, "ERROR: password_hash falló.\n");
    exit(1);
}

$nom = 'Delegado de prueba';

try {
    $ins = $pdo->prepare(
        'INSERT INTO delegados (asociacion_id, email_acceso, password_hash, nombre_contacto, activo)
         VALUES (:aid, :em, :ph, :nom, 1)
         ON DUPLICATE KEY UPDATE
            email_acceso = VALUES(email_acceso),
            password_hash = VALUES(password_hash),
            nombre_contacto = VALUES(nombre_contacto),
            activo = 1'
    );
    $ins->execute([':aid' => $aid, ':em' => $email, ':ph' => $hash, ':nom' => $nom]);
} catch (Throwable $e) {
    fwrite(STDERR, 'ERROR SQL: ' . $e->getMessage() . "\n");
    exit(1);
}

echo "Delegado de prueba listo.\n";
echo "  asociacion_id: {$aid}\n";
echo "  Login (email): {$email}\n";
echo "  Contraseña:    {$plain}\n";
echo "  URL login:       " . rtrim((string) env('APP_BASE_PATH', ''), '/') . "/fvdmasteradmin/login.php\n";
