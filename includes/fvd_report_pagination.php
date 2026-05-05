<?php

declare(strict_types=1);

/**
 * Paginación unificada para informes HTML: ~13" sin scroll vertical en la tabla
 * (tamaño de página por defecto 16; override con FVD_REPORT_PER_PAGE en .env).
 *
 * Parámetro GET: fvd_p (1-based). No usar en export CSV/PDF (omitir en enlaces de descarga).
 */

if (!function_exists('fvd_report_paginator_per_page')) {
    function fvd_report_paginator_per_page(): int
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }
        $raw = '';
        if (function_exists('env')) {
            $e = env('FVD_REPORT_PER_PAGE', '');
            $raw = is_string($e) ? trim($e) : '';
        }
        if ($raw === '') {
            $g = getenv('FVD_REPORT_PER_PAGE');
            $raw = $g !== false ? trim((string) $g) : '';
        }
        $n = $raw !== '' ? (int) $raw : 16;
        $cached = max(8, min(40, $n > 0 ? $n : 16));

        return $cached;
    }

    function fvd_report_paginator_current_page(): int
    {
        $p = isset($_GET['fvd_p']) ? (int) $_GET['fvd_p'] : 1;

        return max(1, $p);
    }

    /**
     * @template T
     * @param list<T> $rows
     * @return array{page:int, pages:int, per_page:int, total:int, slice:list<T>}
     */
    function fvd_report_paginator_slice(array $rows): array
    {
        $per = fvd_report_paginator_per_page();
        $total = count($rows);
        $pages = $total > 0 ? (int) max(1, (int) ceil($total / $per)) : 1;
        $page = min(fvd_report_paginator_current_page(), $pages);
        $offset = ($page - 1) * $per;
        $slice = $total > 0 ? array_values(array_slice($rows, $offset, $per)) : [];

        return [
            'page' => $page,
            'pages' => $pages,
            'per_page' => $per,
            'total' => $total,
            'slice' => $slice,
        ];
    }

    /**
     * @param list<string> $omit
     * @param array<string, scalar|null> $extra
     * @return array<string, scalar|null>
     */
    function fvd_report_paginator_preserve_query(array $omit = ['fvd_p', 'format'], array $extra = []): array
    {
        $out = [];
        foreach ($_GET as $k => $v) {
            if (!is_string($k) || in_array($k, $omit, true)) {
                continue;
            }
            if (is_array($v)) {
                continue;
            }
            if ($v === null || is_scalar($v)) {
                $out[$k] = $v;
            }
        }
        foreach ($extra as $k => $v) {
            if (!is_string($k) || in_array($k, $omit, true)) {
                continue;
            }
            if ($v === null || is_scalar($v)) {
                $out[$k] = $v;
            }
        }

        return $out;
    }

    function fvd_report_paginator_url(string $selfUrl, int $page, array $extraQuery = []): string
    {
        $q = fvd_report_paginator_preserve_query(['fvd_p', 'format'], $extraQuery);
        if ($page > 1) {
            $q['fvd_p'] = (string) $page;
        }
        $qs = http_build_query($q, '', '&', PHP_QUERY_RFC3986);

        return $qs !== '' ? ($selfUrl . '?' . $qs) : $selfUrl;
    }
}
