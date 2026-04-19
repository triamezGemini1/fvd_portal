<?php

declare(strict_types=1);

require_once __DIR__ . '/services/AuthService.php';
AuthService::ensureSession();
AuthService::requireLogin();
AuthService::requireRoles([AuthService::ROLE_FVD_ADMIN]);

$projRoot = dirname(__DIR__);
if (!function_exists('url')) {
    require_once $projRoot . '/config/paths.php';
}

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/master_panel_state.php';

$tid = isset($_GET['torneo_id']) ? (int) $_GET['torneo_id'] : 0;
$ctx = $tid > 0 ? $tid : null;

$pdo = fvd_db();
$state = fvd_master_panel_build_initial_state($pdo, $ctx);

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
echo json_encode($state, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
