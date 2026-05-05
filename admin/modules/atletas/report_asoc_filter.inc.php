<?php

declare(strict_types=1);

/**
 * Filtro opcional por asociación en reportes (solo admin FVD vía GET).
 */
function fvd_report_filter_asociacion_id_from_get(): int
{
    if (!class_exists('AuthService', false)) {
        return 0;
    }
    AuthService::ensureSession();
    if (AuthService::role() !== AuthService::ROLE_FVD_ADMIN) {
        return 0;
    }
    $v = isset($_GET['asociacion_id']) ? (int) $_GET['asociacion_id'] : 0;

    return $v > 0 ? $v : 0;
}
