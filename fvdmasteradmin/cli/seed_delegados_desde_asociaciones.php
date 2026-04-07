<?php
/**
 * Genera filas en `delegados` a partir de `asociaciones` (una por asociación).
 * - Correo de acceso: email de la asociación si es válido y no está duplicado (activo=1).
 * - Si no hay email válido, email duplicado entre asociaciones o conflicto con otro delegado: correo sintético único y activo=0.
 * - Contraseña inicial (todas las filas nuevas): CambiarClave2026! — el delegado debe cambiarla desde Mi perfil.
 *
 * Uso (desde la raíz del proyecto):
 *   php fvdmasteradmin/cli/seed_delegados_desde_asociaciones.php
 */
declare(strict_types=1);

$root = dirname(__DIR__, 2);
chdir($root);

require_once $root . '/fvdmasteradmin/config/db.php';

echo "Base: " . (string) env('FVD_DB_DATABASE', env('DB_DATABASE', '?')) . "\n";

try {
    $pdo = fvd_db();
} catch (Throwable $e) {
    echo "ERROR conexión: " . $e->getMessage() . "\n";
    exit(1);
}

$ddlDelegados = file_get_contents($root . '/fvdmasteradmin/sql/install_delegados.sql');
if ($ddlDelegados !== false && strpos($ddlDelegados, 'CREATE TABLE') !== false) {
    $pdo->exec($ddlDelegados);
    echo "Tabla delegados: verificada/creada.\n";
}

$passwordPlain = 'CambiarClave2026!';
$hash = password_hash($passwordPlain, PASSWORD_DEFAULT);
if ($hash === false) {
    echo "ERROR: no se pudo generar password_hash.\n";
    exit(1);
}

$stAsoc = $pdo->query('SELECT id, nombre, email, delegado, telefono FROM asociaciones ORDER BY id ASC');
$asociaciones = $stAsoc ? $stAsoc->fetchAll(PDO::FETCH_ASSOC) : [];
if ($asociaciones === []) {
    echo "No hay asociaciones.\n";
    echo "\n========== REPORTE DE ACTIVIDAD ==========\n";
    echo "Asociaciones leídas:              0\n";
    echo "Delegados insertados (nuevos):    0\n";
    echo "No insertados (total):            0\n";
    echo "========================================\n";
    exit(0);
}

$stEx = $pdo->query('SELECT asociacion_id, email_acceso FROM delegados');
$existingByAsoc = [];
$claimedEmails = [];
while ($stEx && ($r = $stEx->fetch(PDO::FETCH_ASSOC))) {
    $aid = (int) ($r['asociacion_id'] ?? 0);
    $existingByAsoc[$aid] = true;
    $em = strtolower(trim((string) ($r['email_acceso'] ?? '')));
    if ($em !== '') {
        $claimedEmails[$em] = $aid;
    }
}

$insert = $pdo->prepare(
    'INSERT INTO delegados (asociacion_id, email_acceso, password_hash, nombre_contacto, telefono, activo)
     VALUES (:aid, :email, :ph, :nom, :tel, :act)'
);

$nRead = count($asociaciones);
$nIns = 0;
$nSkip = 0;
$nFail = 0;
$nBadId = 0;
$nAct = 0;
$nInact = 0;

foreach ($asociaciones as $row) {
    $aid = (int) ($row['id'] ?? 0);
    if ($aid <= 0) {
        ++$nBadId;
        continue;
    }
    if (isset($existingByAsoc[$aid])) {
        ++$nSkip;
        echo "[omitir] asociacion_id={$aid} ya tiene delegado.\n";
        continue;
    }

    $rawEmail = isset($row['email']) ? trim((string) $row['email']) : '';
    $lower = strtolower($rawEmail);
    $invalidToken = ($lower === '' || $lower === '0' || $rawEmail === '0');

    $validEmail = !$invalidToken && filter_var($lower, FILTER_VALIDATE_EMAIL) !== false;

    $nombre = trim((string) ($row['delegado'] ?? ''));
    if ($nombre === '') {
        $nombre = trim((string) ($row['nombre'] ?? 'Delegado'));
    }
    if (strlen($nombre) > 250) {
        $nombre = substr($nombre, 0, 250);
    }

    $tel = trim((string) ($row['telefono'] ?? ''));
    if ($tel === '' || $tel === '0') {
        $tel = null;
    } elseif (strlen($tel) > 64) {
        $tel = substr($tel, 0, 64);
    }

    $activo = 1;
    if (!$validEmail) {
        $activo = 0;
        $emailAcceso = 'delegado.asoc.' . $aid . '@fvd-sin-correo.local';
    } elseif (isset($claimedEmails[$lower])) {
        $activo = 0;
        $emailAcceso = 'delegado.asoc.' . $aid . '.dup@fvd-sin-correo.local';
    } else {
        $emailAcceso = $lower;
    }

    $k = strtolower($emailAcceso);
    if (isset($claimedEmails[$k]) && (int) $claimedEmails[$k] !== $aid) {
        $activo = 0;
        $emailAcceso = 'delegado.asoc.' . $aid . '.conflicto@fvd-sin-correo.local';
        $k = strtolower($emailAcceso);
    }
    $claimedEmails[$k] = $aid;

    try {
        $insert->execute([
            ':aid'   => $aid,
            ':email' => $emailAcceso,
            ':ph'    => $hash,
            ':nom'   => $nombre,
            ':tel'   => $tel,
            ':act'   => $activo,
        ]);
        ++$nIns;
        if ($activo) {
            ++$nAct;
        } else {
            ++$nInact;
        }
        echo '[ok] asoc=' . $aid . ' email=' . $emailAcceso . ' activo=' . $activo . "\n";
    } catch (Throwable $e) {
        $errMsg = $e->getMessage();
        if (strpos($errMsg, '1062') !== false || stripos($errMsg, 'Duplicate') !== false) {
            $activo = 0;
            $emailAcceso = 'delegado.asoc.' . $aid . '.uniq.' . substr(sha1((string) microtime(true)), 0, 10) . '@fvd-sin-correo.local';
            try {
                $insert->execute([
                    ':aid'   => $aid,
                    ':email' => $emailAcceso,
                    ':ph'    => $hash,
                    ':nom'   => $nombre,
                    ':tel'   => $tel,
                    ':act'   => $activo,
                ]);
                ++$nIns;
                ++$nInact;
                echo '[recuperado] asoc=' . $aid . ' email=' . $emailAcceso . ' activo=0 (duplicado en BD)' . "\n";
            } catch (Throwable $e2) {
                ++$nFail;
                echo '[ERROR] asoc=' . $aid . ' ' . $e2->getMessage() . "\n";
            }
        } else {
            ++$nFail;
            echo '[ERROR] asoc=' . $aid . ' ' . $e->getMessage() . "\n";
        }
    }
}

$nNoInsertados = $nSkip + $nFail + $nBadId;

echo "\n";
echo "========== REPORTE DE ACTIVIDAD ==========\n";
echo "Asociaciones leídas:              {$nRead}\n";
echo "Delegados insertados (nuevos):    {$nIns}\n";
echo "No insertados (total):            {$nNoInsertados}\n";
echo "  - Ya existía delegado:          {$nSkip}\n";
echo "  - ID asociación inválido (≤0):  {$nBadId}\n";
echo "  - Error al insertar:            {$nFail}\n";
echo "Detalle filas nuevas — activos:   {$nAct} | inactivos: {$nInact}\n";
echo "========================================\n";
echo "Contraseña inicial (filas nuevas): {$passwordPlain}\n";
