<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/services/AuthService.php';
AuthService::ensureSession();
AuthService::requireLogin();
AuthService::requireRoles([AuthService::ROLE_FVD_ADMIN]);

require_once dirname(__DIR__) . '/includes/workspace_module_redirect.php';
/* Cartera del panel: listado de deudas por torneo/asociación (no /admin/modules/ — ese path no existe). */
fvd_workspace_redirect_to_fvd_module('deuda_asociacion/index.php');
