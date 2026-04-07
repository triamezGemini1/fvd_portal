<?php
/**
 * Arranque para páginas públicas FVD: rutas, env, PDO (fvd_db) y QueryHelper.
 * No inicia sesión ni exige login.
 */
declare(strict_types=1);

if (!defined('FVD_MASTER_ROOT')) {
    define('FVD_MASTER_ROOT', __DIR__);
}
if (!defined('FVD_PROJECT_ROOT')) {
    define('FVD_PROJECT_ROOT', dirname(FVD_MASTER_ROOT));
}

require_once FVD_PROJECT_ROOT . '/config/paths.php';
require_once FVD_MASTER_ROOT . '/config/db.php';
require_once FVD_MASTER_ROOT . '/services/QueryHelper.php';
require_once FVD_MASTER_ROOT . '/services/PublicSiteData.php';
