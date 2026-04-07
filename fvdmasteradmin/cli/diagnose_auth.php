<?php
/**
 * Diagnóstico de autenticación FVD (CLI). No imprime contraseñas.
 * Uso: php fvdmasteradmin/cli/diagnose_auth.php
 */
declare(strict_types=1);

$root = dirname(__DIR__, 2);
chdir($root);

require_once $root . '/fvdmasteradmin/config/db.php';

$fvdDb = env('FVD_DB_DATABASE', env('DB_DATABASE', '?'));
$fvdHost = env('FVD_DB_HOST', env('DB_HOST', '?'));
echo "FVD Master Admin → DB efectiva: {$fvdDb} @ {$fvdHost}\n";

try {
    $pdo = fvd_db();
    echo "Conexión PDO: OK\n";
} catch (Throwable $e) {
    echo "Conexión PDO: FALLO — " . $e->getMessage() . "\n";
    exit(1);
}

try {
    $pdo->query('SELECT 1 FROM fvd_usuarios LIMIT 1');
    echo "Tabla fvd_usuarios: existe\n";
} catch (Throwable $e) {
    echo "Tabla fvd_usuarios: FALLO — " . $e->getMessage() . "\n";
    echo "Ejecute: fvdmasteradmin/sql/install_fvd_usuarios.sql\n";
    exit(1);
}

$st = $pdo->query('SELECT id, email, LENGTH(password_hash) AS hlen, rol, activo FROM fvd_usuarios ORDER BY id');
$rows = $st ? $st->fetchAll(PDO::FETCH_ASSOC) : [];
echo "Filas en fvd_usuarios: " . count($rows) . "\n";
foreach ($rows as $r) {
    echo "  - id={$r['id']} email=" . json_encode($r['email'], JSON_UNESCAPED_UNICODE)
        . " hash_len={$r['hlen']} rol={$r['rol']} activo={$r['activo']}\n";
}

$creds = [
    ['Trinoamez', 'npi$2025'],
    ['Asociacion', 'npi$2025'],
    ['usuario', 'npi$2025'],
];

require_once $root . '/fvdmasteradmin/services/AuthService.php';

foreach ($creds as [$user, $pass]) {
    $ok = AuthService::attemptLogin($user, $pass);
    echo "attemptLogin(" . json_encode($user) . "): " . ($ok ? 'OK' : 'FALLO') . "\n";
    if ($ok) {
        AuthService::logout();
    }
}

echo "\nPrueba SQL directa (misma consulta que AuthService):\n";
$e = strtolower(trim('Trinoamez'));
$stmt = $pdo->prepare(
    'SELECT id, email, password_hash, activo FROM fvd_usuarios WHERE LOWER(TRIM(email)) = :e LIMIT 1'
);
$stmt->execute([':e' => $e]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$row) {
    echo "  Usuario Trinoamez: no encontrado con LOWER(TRIM(email))\n";
} else {
    $v = password_verify('npi$2025', (string) $row['password_hash']);
    echo "  Fila encontrada id={$row['id']}, password_verify(npi\$2025): " . ($v ? 'OK' : 'FALLO') . "\n";
    if (!$v && isset($row['password_hash'])) {
        $h = (string) $row['password_hash'];
        echo "  Hash en BD (primeros 20 chars): " . substr($h, 0, 20) . "... len=" . strlen($h) . "\n";
    }
}
