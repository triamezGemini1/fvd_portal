<?php

declare(strict_types=1);

/**
 * Crea o actualiza la cuenta de delegado (tabla delegados) para una asociación concreta.
 *
 * Uso (desde la raíz del proyecto):
 *   php fvdmasteradmin/cli/provision_delegado_asociacion.php --asociacion-id=4
 *   php fvdmasteradmin/cli/provision_delegado_asociacion.php --asociacion-id=4 --email=delegado.miranda4@test.fvd --password="Clave"
 *
 * Por defecto usa la misma contraseña que las cuentas de prueba (FVD_TEST_PASSWORD).
 */

$root = dirname(__DIR__, 2);
chdir($root);

require_once $root . '/fvdmasteradmin/config/db.php';
require_once dirname(__DIR__) . '/config/test_accounts.php';

$opts = getopt('', ['asociacion-id:', 'email::', 'password::']);
$aid = isset($opts['asociacion-id']) ? max(0, (int) $opts['asociacion-id']) : 0;
if ($aid <= 0) {
    fwrite(STDERR, "Uso: --asociacion-id=ID\n");
    exit(1);
}

$email = isset($opts['email']) && is_string($opts['email']) && trim($opts['email']) !== ''
    ? strtolower(trim($opts['email']))
    : 'delegado.asoc' . $aid . '@test.fvd';
$plain = isset($opts['password']) && is_string($opts['password']) && $opts['password'] !== ''
    ? $opts['password']
    : FVD_TEST_PASSWORD;

try {
    $pdo = fvd_db();
} catch (Throwable $e) {
    fwrite(STDERR, 'ERROR conexión: ' . $e->getMessage() . "\n");
    exit(1);
}

$ddl = @file_get_contents($root . '/fvdmasteradmin/sql/install_delegados.sql');
if ($ddl !== false && str_contains($ddl, 'CREATE TABLE')) {
    $pdo->exec($ddl);
}

$st = $pdo->prepare('SELECT id, nombre FROM asociaciones WHERE id = :id LIMIT 1');
$st->execute([':id' => $aid]);
$row = $st->fetch(PDO::FETCH_ASSOC);
if ($row === false) {
    fwrite(STDERR, "No existe asociaciones.id = {$aid}\n");
    exit(1);
}

$hash = password_hash($plain, PASSWORD_DEFAULT);
if ($hash === false) {
    fwrite(STDERR, "ERROR: password_hash\n");
    exit(1);
}

// Una fila por asociación: borrar delegado previo de otra cuenta en mismo asoc si cambia email (email es UNIQUE global)
$stOld = $pdo->prepare('SELECT id, email_acceso FROM delegados WHERE asociacion_id = :a LIMIT 1');
$stOld->execute([':a' => $aid]);
$prev = $stOld->fetch(PDO::FETCH_ASSOC);
if ($prev !== false && strtolower((string) ($prev['email_acceso'] ?? '')) !== $email) {
    $pdo->prepare('DELETE FROM delegados WHERE id = :id')->execute([':id' => (int) $prev['id']]);
}

$ins = $pdo->prepare(
    'INSERT INTO delegados (asociacion_id, email_acceso, password_hash, nombre_contacto, activo)
     VALUES (:aid, :em, :ph, :nom, 1)
     ON DUPLICATE KEY UPDATE
        email_acceso = VALUES(email_acceso),
        password_hash = VALUES(password_hash),
        nombre_contacto = VALUES(nombre_contacto),
        activo = 1'
);
$ins->execute([
    ':aid' => $aid,
    ':em' => $email,
    ':ph' => $hash,
    ':nom' => 'Delegado prueba ' . trim((string) ($row['nombre'] ?? '')),
]);

echo "Delegado listo.\n";
echo '  Asociación: ' . trim((string) ($row['nombre'] ?? '')) . " (id={$aid})\n";
echo "  Login (email): {$email}\n";
echo "  Contraseña:    {$plain}\n";
echo '  URL: ' . rtrim((string) env('APP_BASE_PATH', ''), '/') . "/fvdmasteradmin/login.php\n";
