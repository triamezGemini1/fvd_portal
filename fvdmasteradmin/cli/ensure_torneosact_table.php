<?php

declare(strict_types=1);

/**
 * Crea la tabla torneosact si no existe (install_torneosact.sql).
 * Uso: php fvdmasteradmin/cli/ensure_torneosact_table.php
 */

$root = dirname(__DIR__, 2);
require_once $root . '/config/paths.php';
require_once dirname(__DIR__) . '/config/db.php';

$sqlPath = dirname(__DIR__) . '/sql/install_torneosact.sql';
$sql = file_get_contents($sqlPath);
if ($sql === false) {
    fwrite(STDERR, "No se pudo leer: {$sqlPath}\n");
    exit(1);
}

try {
    fvd_db()->exec($sql);
} catch (PDOException $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}

echo "Tabla torneosact verificada/creada correctamente.\n";
