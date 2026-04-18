<?php

declare(strict_types=1);

namespace FvdPortal\Services;

/**
 * HTML de paginación reutilizable en listados e informes (misma clase CSS que el listado de atletas).
 */
final class PaginationView
{
    /**
     * @param array<string, scalar|null> $queryParams Parámetros a conservar (incluya action, tab, filtros, etc.).
     * @param string $navId id HTML del &lt;nav&gt; (para enlazar JS / accesibilidad).
     */
    public static function navHtml(
        string $selfUrl,
        int $page,
        int $pages,
        int $total,
        array $queryParams,
        string $navId = 'fvd-mod-pager'
    ): string {
        $page = max(1, $page);
        $pages = max(1, $pages);
        $total = max(0, $total);

        $q = $queryParams;
        unset($q['page']);

        $sep = str_contains($selfUrl, '?') ? '&' : '?';

        $href = static function (int $p) use ($selfUrl, $sep, $q): string {
            $q['page'] = $p;

            return $selfUrl . $sep . http_build_query($q);
        };

        $prev = $page > 1
            ? '<a href="' . htmlspecialchars($href($page - 1), ENT_QUOTES, 'UTF-8') . '">Anterior</a>'
            : '';
        $next = $page < $pages
            ? '<a href="' . htmlspecialchars($href($page + 1), ENT_QUOTES, 'UTF-8') . '">Siguiente</a>'
            : '';

        return '<nav id="' . htmlspecialchars($navId, ENT_QUOTES, 'UTF-8')
            . '" class="fvd-mod-pager no-print"><span>'
            . (int) $total . ' reg. · pág. ' . (int) $page . '/' . (int) $pages . '</span> '
            . $prev . ($prev !== '' && $next !== '' ? ' ' : '')
            . $next . '</nav>';
    }

    /** Enlaces con <code>data-fvd-page</code> para recarga vía fetch (sin navegar). */
    public static function navPrefetchHtml(int $page, int $pages, int $total, string $navId = 'fvd-atletas-pager'): string
    {
        $page = max(1, $page);
        $pages = max(1, $pages);
        $total = max(0, $total);

        $prev = $page > 1
            ? '<a href="#" data-fvd-page="' . ($page - 1) . '">Anterior</a>'
            : '';
        $next = $page < $pages
            ? '<a href="#" data-fvd-page="' . ($page + 1) . '">Siguiente</a>'
            : '';

        return '<nav id="' . htmlspecialchars($navId, ENT_QUOTES, 'UTF-8')
            . '" class="fvd-mod-pager no-print"><span>'
            . (int) $total . ' reg. · pág. ' . (int) $page . '/' . (int) $pages . '</span> '
            . $prev . ($prev !== '' && $next !== '' ? ' ' : '')
            . $next . '</nav>';
    }
}
