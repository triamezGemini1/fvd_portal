<?php

declare(strict_types=1);

/**
 * Punto de enganche para php.ini / .htaccess: auto_prepend_file.
 * Debe vivir en la misma carpeta que {@see portal_rbac_gate.php}.
 */
require_once __DIR__ . '/portal_rbac_gate.php';
