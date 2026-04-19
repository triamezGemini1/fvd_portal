<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/services/AuthService.php';
AuthService::ensureSession();
AuthService::requireLogin();
AuthService::requireRoles([AuthService::ROLE_FVD_ADMIN]);

require_once dirname(__DIR__) . '/includes/workspace_module_redirect.php';
fvd_workspace_redirect_to_admin_module('atletas/index.php?action=list');
