<?php

declare(strict_types=1);

/**
 * Entrada estable para auto_prepend_file: rutas absolutas vía __DIR__.
 * Evita HTTP 500 cuando el CWD del script es p. ej. fvdmasteradmin/ y
 * "includes/portal_rbac_auto_start.php" no existe allí.
 */
require_once __DIR__ . '/includes/portal_rbac_auto_start.php';
