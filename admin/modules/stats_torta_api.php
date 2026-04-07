<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/_init.php';
require_once FVD_PROJECT_ROOT . '/src/Services/StatsService.php';

use FvdPortal\Services\StatsService;

fvd_admin_require_roles();

header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

$torta = StatsService::atletasPorAsociacionParaTorta(fvd_db(), 7);
echo json_encode(['ok' => true, 'torta_asociacion' => $torta], JSON_UNESCAPED_UNICODE);
