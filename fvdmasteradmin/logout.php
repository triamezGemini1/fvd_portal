<?php

declare(strict_types=1);

require_once __DIR__ . '/services/AuthService.php';

AuthService::ensureSession();
AuthService::logout();
header('Location: ' . AuthService::loginUrl());
exit;
