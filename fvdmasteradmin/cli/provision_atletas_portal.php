<?php
/**
 * Crea filas en fvd_usuarios por cada atleta con correo, inactivas hasta que el atleta solicite acceso.
 * Uso (desde la raíz del proyecto): php fvdmasteradmin/cli/provision_atletas_portal.php
 */
declare(strict_types=1);

$root = dirname(__DIR__, 2);
chdir($root);

require_once $root . '/fvdmasteradmin/config/db.php';
require_once $root . '/fvdmasteradmin/services/AtletaAccesoService.php';

$fvdDb = env('FVD_DB_DATABASE', env('DB_DATABASE', '?'));
echo "Base de datos (FVD): {$fvdDb}\n";

try {
    $pdo = fvd_db();
} catch (Throwable $e) {
    echo "ERROR conexión: " . $e->getMessage() . "\n";
    exit(1);
}

$chk = $pdo->query("SHOW COLUMNS FROM fvd_usuarios LIKE 'atleta_id'")->fetch(PDO::FETCH_ASSOC);
if (!$chk) {
    echo "Falta la columna atleta_id en fvd_usuarios. Ejecute: fvdmasteradmin/sql/alter_fvd_usuarios_atleta_id.sql\n";
    exit(1);
}

$stSel = $pdo->query(
    'SELECT id, cedula, nombre, email, numfvd, asociacion, celular FROM atletas
     WHERE email IS NOT NULL AND TRIM(email) <> \'\''
);
$atletas = $stSel ? $stSel->fetchAll(PDO::FETCH_ASSOC) : [];
$ins = 0;
$skip = 0;
$warn = 0;

$stUser = $pdo->prepare(
    'SELECT id, atleta_id, activo FROM fvd_usuarios WHERE LOWER(TRIM(email)) = :e LIMIT 1'
);
$stInsert = $pdo->prepare(
    'INSERT INTO fvd_usuarios (email, password_hash, nombre, rol, id_asociacion, atleta_id, activo)
     VALUES (:e, :h, :n, :rol, :ida, :aid, 0)'
);

foreach ($atletas as $a) {
    $email = strtolower(trim((string) ($a['email'] ?? '')));
    if ($email === '') {
        continue;
    }
    $aid = (int) ($a['id'] ?? 0);
    if ($aid <= 0) {
        continue;
    }

    $stUser->execute([':e' => $email]);
    $u = $stUser->fetch(PDO::FETCH_ASSOC);
    if ($u) {
        $uidA = isset($u['atleta_id']) && $u['atleta_id'] !== null && $u['atleta_id'] !== ''
            ? (int) $u['atleta_id']
            : null;
        if ($uidA === null) {
            echo "[AVISO] {$email}: correo ya usado por usuario sin vínculo de atleta. Omitido.\n";
            ++$warn;
            continue;
        }
        if ($uidA === $aid) {
            ++$skip;
            continue;
        }
        echo "[AVISO] {$email}: correo asociado a otro atleta_id={$uidA}. Omitido.\n";
        ++$warn;
        continue;
    }

    $plain = AtletaAccesoService::initialPlainPasswordFromAtletaRow($a);
    $hash = password_hash($plain, PASSWORD_DEFAULT);
    if ($hash === false) {
        echo "[ERROR] No se pudo hashear clave para atleta id={$aid}\n";
        ++$warn;
        continue;
    }

    $nombre = trim((string) ($a['nombre'] ?? ''));
    $idAsoc = $a['asociacion'] ?? null;
    $idAsocSql = $idAsoc === null || $idAsoc === '' ? null : (int) $idAsoc;

    try {
        $stInsert->execute([
            ':e' => $email,
            ':h' => $hash,
            ':n' => $nombre !== '' ? $nombre : null,
            ':rol' => 'usuario',
            ':ida' => $idAsocSql,
            ':aid' => $aid,
        ]);
        ++$ins;
    } catch (Throwable $e) {
        echo "[ERROR] atleta id={$aid} {$email}: " . $e->getMessage() . "\n";
        ++$warn;
    }
}

echo "Listo. Insertados: {$ins}, ya existían (mismo atleta): {$skip}, avisos/errores: {$warn}\n";
echo "Los atletas deben usar «Solicitar acceso» (correo + teléfono) para activar la cuenta (activo=1).\n";
