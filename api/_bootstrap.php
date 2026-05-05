<?php

declare(strict_types=1);

/**
 * Cabeceras comunes para endpoints JSON en /api/.
 */
function fvd_api_json_headers(): void
{
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-store, no-cache, must-revalidate');
    }
}

/**
 * @param array<string, mixed> $payload
 */
function fvd_api_json_out(array $payload, int $code = 200): void
{
    fvd_api_json_headers();
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
}
