<?php

/**
 * Compatibilidad con PHP 7.x (p. ej. WAMP con 7.4).
 * str_contains() es nativo desde PHP 8.0.
 */
if (!function_exists('str_contains')) {
    /**
     * @param string $haystack
     * @param string $needle
     */
    function str_contains($haystack, $needle): bool
    {
        if ($needle === '') {
            return true;
        }

        return strpos((string) $haystack, (string) $needle) !== false;
    }
}
