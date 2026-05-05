<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

fvd_api_json_out([
    'ok' => true,
    'message' => 'api activa',
    'time' => date('c'),
]);
