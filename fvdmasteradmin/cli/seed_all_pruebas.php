<?php

/**
 * Ejecuta todos los seeds de cuentas de prueba (fvd_usuarios + delegados).
 *
 * Uso (desde la raíz del proyecto): php fvdmasteradmin/cli/seed_all_pruebas.php
 */
declare(strict_types=1);

$root = dirname(__DIR__, 2);
chdir($root);

echo "=== seed_test_users.php ===\n";
require __DIR__ . '/seed_test_users.php';

echo "\n=== seed_delegado_prueba.php ===\n";
require __DIR__ . '/seed_delegado_prueba.php';

echo "\nTodo integrado. Revise fvdmasteradmin/dev_accesos_prueba.php (solo con APP_DEBUG).\n";
