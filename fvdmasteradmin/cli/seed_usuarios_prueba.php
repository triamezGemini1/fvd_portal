<?php

declare(strict_types=1);

/**
 * Usuarios de prueba (clave común npi$2025).
 * Login: campo email en minúsculas (AuthService::attemptLogin).
 *
 * Uso: php fvdmasteradmin/cli/seed_usuarios_prueba.php
 */

$root = dirname(__DIR__, 2);
chdir($root);

require_once $root . '/config/paths.php';
require_once dirname(__DIR__) . '/config/db.php';

try {
    $pdo = fvd_db();
} catch (Throwable $e) {
    fwrite(STDERR, 'ERROR conexión: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

$sqlDir = dirname(__DIR__) . '/sql';
$runAlterFile = static function (PDO $pdo, string $path): void {
    if (!is_readable($path)) {
        return;
    }
    $sql = file_get_contents($path);
    if ($sql === false || trim($sql) === '') {
        return;
    }
    try {
        $pdo->exec($sql);
    } catch (PDOException $e) {
        if (str_contains($e->getMessage(), 'Duplicate column')
            || str_contains($e->getMessage(), 'check that column/key exists')) {
            return;
        }
        throw $e;
    }
};

$runAlterFile($pdo, $sqlDir . '/alter_fvd_usuarios_perfil.sql');
$runAlterFile($pdo, $sqlDir . '/alter_fvd_usuarios_foto_sexo.sql');
$runAlterFile($pdo, $sqlDir . '/alter_fvd_usuarios_atleta_id.sql');

$dbName = (string) env('FVD_DB_DATABASE', env('DB_DATABASE', 'fvdmasteradmin'));
echo "Base de datos: {$dbName}" . PHP_EOL;

$plain = 'npi$2025';
$hash = password_hash($plain, PASSWORD_DEFAULT);

/** @return array{0: ?int, 1: ?int} [atleta_id, asociacion_id] */
$lookupAtleta = static function (PDO $pdo, string $cedula): array {
    $digits = preg_replace('/\D+/', '', $cedula);
    $st = $pdo->prepare('SELECT id, asociacion FROM atletas WHERE cedula = :c OR cedula = :d LIMIT 1');
    $st->execute([':c' => $cedula, ':d' => $digits !== '' ? $digits : $cedula]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return [null, null];
    }
    $aid = isset($row['id']) ? (int) $row['id'] : null;
    $asoc = isset($row['asociacion']) && $row['asociacion'] !== null && $row['asociacion'] !== ''
        ? (int) $row['asociacion']
        : null;

    return [$aid, $asoc];
};

[$idTrinoAtleta, $asocTrino] = $lookupAtleta($pdo, '4978399');
[$idYoliAtleta, $asocYoli] = $lookupAtleta($pdo, '5608138');
[$idKelvis, $asocKelvis] = $lookupAtleta($pdo, '11897643');

echo '4978399 → atleta id=' . ($idTrinoAtleta ?? '—') . ', asociación=' . ($asocTrino ?? '—') . PHP_EOL;
echo '5608138 → atleta id=' . ($idYoliAtleta ?? '—') . ', asociación=' . ($asocYoli ?? '—') . PHP_EOL;
echo '11897643 → atleta id=' . ($idKelvis ?? '—') . ', asociación=' . ($asocKelvis ?? '—') . PHP_EOL;

$users = [
    [
        'email'               => 'trinoamez',
        'nombre'              => 'Trino Amezquita',
        'rol'                 => 'fvd_admin',
        'id_asociacion'       => null,
        'atleta_id'           => null,
        'documento_identidad' => '4978399',
    ],
    [
        'email'               => 'yolicoro',
        'nombre'              => 'Yolimar Guevara',
        'rol'                 => 'aso_admin',
        'id_asociacion'       => $asocYoli,
        'atleta_id'           => null,
        'documento_identidad' => '5608138',
    ],
    [
        'email'               => 'usuario',
        'nombre'              => 'Kelvis Tomás Bermúdez Froilán',
        'rol'                 => 'usuario',
        'id_asociacion'       => $asocKelvis,
        'atleta_id'           => $idKelvis,
        'documento_identidad' => '11897643',
    ],
];

if ($users[1]['id_asociacion'] === null) {
    fwrite(STDERR, "Aviso: sin id_asociacion para yolicoro (no hay atleta 5608138 en BD)." . PHP_EOL);
}
if ($users[2]['atleta_id'] === null) {
    fwrite(STDERR, "Aviso: sin atleta_id para usuario (no hay atleta 11897643 en BD)." . PHP_EOL);
}

$cols = $pdo->query('SHOW COLUMNS FROM fvd_usuarios');
$colNames = $cols ? $cols->fetchAll(PDO::FETCH_COLUMN) : [];
if ($colNames === []) {
    fwrite(STDERR, "Falta tabla fvd_usuarios. Ejecute install_fvd_usuarios.sql." . PHP_EOL);
    exit(1);
}

$useAtleta = in_array('atleta_id', $colNames, true);
$useDoc = in_array('documento_identidad', $colNames, true);

$sql = 'INSERT INTO fvd_usuarios (email, password_hash, nombre, rol, id_asociacion';
$sql .= $useAtleta ? ', atleta_id' : '';
$sql .= $useDoc ? ', documento_identidad' : '';
$sql .= ', activo) VALUES (:email, :ph, :nombre, :rol, :asoc';
$sql .= $useAtleta ? ', :atleta' : '';
$sql .= $useDoc ? ', :doc' : '';
$sql .= ', 1) ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash), nombre = VALUES(nombre), rol = VALUES(rol), id_asociacion = VALUES(id_asociacion)';
$sql .= $useAtleta ? ', atleta_id = VALUES(atleta_id)' : '';
$sql .= $useDoc ? ', documento_identidad = VALUES(documento_identidad)' : '';
$sql .= ', activo = 1';

$st = $pdo->prepare($sql);

foreach ($users as $u) {
    $params = [
        ':email'  => $u['email'],
        ':ph'     => $hash,
        ':nombre' => $u['nombre'],
        ':rol'    => $u['rol'],
        ':asoc'   => $u['id_asociacion'],
    ];
    if ($useAtleta) {
        $params[':atleta'] = $u['atleta_id'];
    }
    if ($useDoc) {
        $params[':doc'] = $u['documento_identidad'];
    }
    try {
        $st->execute($params);
    } catch (PDOException $e) {
        fwrite(STDERR, "Error {$u['email']}: " . $e->getMessage() . PHP_EOL);
        exit(1);
    }
    echo "OK: {$u['email']} ({$u['rol']})" . PHP_EOL;
}

echo PHP_EOL . 'Clave: npi$2025' . PHP_EOL;
echo 'Usuarios (escribir así en el login): trinoamez · yolicoro · usuario' . PHP_EOL;
