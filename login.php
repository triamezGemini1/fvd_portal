<?php

declare(strict_types=1);

/**
 * Entrada canónica al login (misma sesión que fvdmasteradmin/login.php).
 * Evita bucles de redirección y permite anclar /{APP_BASE}/login.php en logout y documentación.
 */
require_once __DIR__ . '/config/paths.php';

header('Location: ' . url('fvdmasteradmin/login.php'), true, 302);
exit;
