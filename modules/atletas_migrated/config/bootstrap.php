<?php

declare(strict_types=1);

/**
 * Bootstrap local del módulo migrado de Atletas.
 * Mantiene compatibilidad con el entorno actual del portal.
 */

if (!defined('FVD_PROJECT_ROOT')) {
    require_once dirname(__DIR__, 3) . '/admin/_init.php';
}

require_once FVD_PROJECT_ROOT . '/src/Services/QueryHelper.php';
require_once FVD_PROJECT_ROOT . '/src/Services/PaginationView.php';
require_once FVD_MASTER_ROOT . '/includes/fvd_delegado_internal_nav.php';
require_once FVD_PROJECT_ROOT . '/admin/modules/atletas/list_filters.inc.php';

if (!function_exists('fvd_atletas_migrated_root')) {
    function fvd_atletas_migrated_root(): string
    {
        return dirname(__DIR__);
    }
}
