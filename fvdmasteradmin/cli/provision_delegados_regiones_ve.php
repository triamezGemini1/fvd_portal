<?php

declare(strict_types=1);

/**
 * Crea cuentas delegado (tabla delegados) ligadas a una asociación por región venezolana (pruebas).
 *
 * Intenta localizar una fila existente en `asociaciones` por nombre/dirección; si no hay coincidencia,
 * inserta una asociación de prueba con nombre "FVD Prueba — {región}".
 *
 * Uso (desde la raíz del proyecto):
 *   php fvdmasteradmin/cli/provision_delegados_regiones_ve.php
 *   php fvdmasteradmin/cli/provision_delegados_regiones_ve.php --dry-run
 *
 * Contraseña: FVD_TEST_PASSWORD (fvdmasteradmin/config/test_accounts.php)
 */

$root = dirname(__DIR__, 2);
chdir($root);

require_once $root . '/fvdmasteradmin/config/db.php';
require_once dirname(__DIR__) . '/config/test_accounts.php';

$opts = getopt('', ['dry-run']);
$dry = isset($opts['dry-run']);

$regions = [
    [
        'slug' => 'miranda',
        'label' => 'Miranda',
        'tokens' => ['miranda'],
    ],
    [
        'slug' => 'aragua',
        'label' => 'Aragua',
        'tokens' => ['aragua'],
    ],
    [
        'slug' => 'capital',
        'label' => 'Capital / Distrito Capital',
        'tokens' => [],
    ],
    [
        'slug' => 'anzoategui',
        'label' => 'Anzoátegui',
        'tokens' => ['anzoategui', 'anzoátegui', 'barcelona', 'lecherias', 'lecherías'],
    ],
    [
        'slug' => 'bolivar',
        'label' => 'Bolívar',
        'tokens' => ['bolivar', 'bolívar', 'ciudad bolivar', 'ciudad bolívar', 'puerto ordaz', 'ciudad guayana'],
    ],
];

/**
 * Distrito Capital / Gran Caracas: solo por nombre (evita coincidencias en dirección).
 *
 * @param list<int> $excludeIds
 * @return array{id:int,nombre:string}|null
 */
function fvd_find_asociacion_capital(PDO $pdo, array $excludeIds): ?array
{
    $likes = [
        '%caracas%',
        '%chacao%',
        '%baruta%',
        '%petare%',
        '%hatillo%',
        '%libertador%',
        '%palos grande%',
        '%coche%',
        '%el valle%',
        '%d.f.%',
        '%distrito capital%',
        '%distrito metropolitano%',
    ];
    foreach ($likes as $like) {
        $sql = 'SELECT id, nombre FROM asociaciones WHERE LOWER(nombre) LIKE :l1';
        if ($excludeIds !== []) {
            $sql .= ' AND id NOT IN (' . implode(',', array_map('intval', $excludeIds)) . ')';
        }
        $sql .= ' ORDER BY id ASC LIMIT 1';
        try {
            $st = $pdo->prepare($sql);
            $st->execute([':l1' => $like]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            if (is_array($row) && (int) ($row['id'] ?? 0) > 0) {
                return ['id' => (int) $row['id'], 'nombre' => trim((string) ($row['nombre'] ?? ''))];
            }
        } catch (Throwable $e) {
            fwrite(STDERR, '[buscar capital] ' . $e->getMessage() . "\n");
        }
    }

    return null;
}

/**
 * @param list<int> $excludeIds
 * @return array{id:int,nombre:string}|null
 */
function fvd_find_asociacion_por_tokens(PDO $pdo, array $tokens, array $excludeIds): ?array
{
    foreach ($tokens as $tok) {
        $tok = trim((string) $tok);
        if ($tok === '') {
            continue;
        }
        $esc = str_replace(['%', '_'], ['\\%', '\\_'], strtolower($tok));
        $like = '%' . $esc . '%';
        $sql = 'SELECT id, nombre FROM asociaciones WHERE (LOWER(nombre) LIKE :l1 OR LOWER(COALESCE(direccion, \'\')) LIKE :l2)';
        if ($excludeIds !== []) {
            $sql .= ' AND id NOT IN (' . implode(',', array_map('intval', $excludeIds)) . ')';
        }
        $sql .= ' ORDER BY id ASC LIMIT 1';
        try {
            $st = $pdo->prepare($sql);
            $st->execute([':l1' => $like, ':l2' => $like]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            if (is_array($row) && (int) ($row['id'] ?? 0) > 0) {
                return ['id' => (int) $row['id'], 'nombre' => trim((string) ($row['nombre'] ?? ''))];
            }
        } catch (Throwable $e) {
            fwrite(STDERR, '[buscar] ' . $e->getMessage() . "\n");
        }
    }

    return null;
}

/**
 * @return array{id:int,nombre:string}
 */
function fvd_create_asociacion_prueba(PDO $pdo, string $label, string $slug): array
{
    $numreg = 'FVD-PRUEBA-' . strtoupper($slug) . '-' . date('Ymd');
    $nombre = 'FVD Prueba delegado — ' . $label;
    $email = 'asoc.prueba.' . $slug . '@test.fvd';
    $ins = $pdo->prepare(
        'INSERT INTO asociaciones (nombre, direccion, telefono, email, numreg, delegado, estatus, logo)
         VALUES (:nom, :dir, \'\', :em, :nr, :del, \'activo\', NULL)'
    );
    $ins->execute([
        ':nom' => $nombre,
        ':dir' => 'Creada por provision_delegados_regiones_ve.php',
        ':em' => $email,
        ':nr' => $numreg,
        ':del' => 'Delegado prueba ' . $label,
    ]);
    $id = (int) $pdo->lastInsertId();
    if ($id <= 0) {
        throw new RuntimeException('No se obtuvo id tras INSERT en asociaciones.');
    }

    return ['id' => $id, 'nombre' => $nombre];
}

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

$plain = FVD_TEST_PASSWORD;
$hash = password_hash($plain, PASSWORD_DEFAULT);
if ($hash === false) {
    fwrite(STDERR, "ERROR password_hash\n");
    exit(1);
}

$usedAsocIds = [];
$rowsOut = [];

foreach ($regions as $spec) {
    $slug = (string) $spec['slug'];
    $label = (string) $spec['label'];
    $tokens = $spec['tokens'];
    $found = $slug === 'capital'
        ? fvd_find_asociacion_capital($pdo, $usedAsocIds)
        : fvd_find_asociacion_por_tokens($pdo, $tokens, $usedAsocIds);
    $creada = false;
    if ($found === null) {
        if ($dry) {
            $rowsOut[] = [
                'region' => $label,
                'asoc_id' => null,
                'asoc_nombre' => '(dry-run: se crearía asociación de prueba)',
                'email' => 'delegado.prueba.' . $slug . '@test.fvd',
                'creada' => false,
            ];
            continue;
        }
        $found = fvd_create_asociacion_prueba($pdo, $label, $slug);
        $creada = true;
    }

    $aid = (int) ($found['id'] ?? 0);
    if ($aid <= 0) {
        continue;
    }
    $usedAsocIds[] = $aid;
    $email = 'delegado.prueba.' . $slug . '@test.fvd';
    $rowsOut[] = [
        'region' => $label,
        'asoc_id' => $aid,
        'asoc_nombre' => $found['nombre'],
        'email' => $email,
        'creada' => $creada,
    ];

    if ($dry) {
        continue;
    }

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
        ':nom' => 'Delegado prueba ' . $label,
    ]);
}

$basePath = rtrim((string) (function_exists('env') ? env('APP_BASE_PATH', '') : ''), '/');
$loginUrl = $basePath . '/login.php';

echo "=== Delegados de prueba por región ===\n";
echo "URL de acceso: {$loginUrl}\n";
echo 'Contraseña común: ' . $plain . "\n\n";

foreach ($rowsOut as $r) {
    echo sprintf(
        "%s | asoc #%s | %s | login: %s\n",
        $r['region'],
        $r['asoc_id'] === null ? '—' : (string) $r['asoc_id'],
        $r['asoc_nombre'],
        $r['email']
    );
}

if ($dry) {
    echo "\n(dry-run: no se escribió en la base de datos)\n";
    exit(0);
}

echo "\nListo. En producción defina FVD_DELEGADO_CALENDARIO_STRICT=true si desea ventanas por fecha del torneo.\n";
