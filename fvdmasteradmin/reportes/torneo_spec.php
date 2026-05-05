<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/services/AuthService.php';
AuthService::ensureSession();
AuthService::requireLogin();
AuthService::requireRoles([AuthService::ROLE_FVD_ADMIN]);

require_once dirname(__DIR__) . '/includes/workspace_module_redirect.php';
/* Finanzas por torneo: informes y movimientos EUR (no pantalla de inscripción en sitio). */
fvd_workspace_redirect_to_fvd_module('inscripciones/index.php');
