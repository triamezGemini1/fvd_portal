<?php

/**
 * Compatibilidad con PHP 7.x (p. ej. WAMP con 7.4).
 * str_contains() y str_starts_with() son nativos desde PHP 8.0.
 */
if (!function_exists('str_starts_with')) {
    /**
     * @param string $haystack
     * @param string $needle
     */
    function str_starts_with($haystack, $needle): bool
    {
        if ($needle === '') {
            return true;
        }

        return strncmp((string) $haystack, (string) $needle, strlen((string) $needle)) === 0;
    }
}

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

if (!function_exists('str_ends_with')) {
    /**
     * @param string $haystack
     * @param string $needle
     */
    function str_ends_with($haystack, $needle): bool
    {
        if ($needle === '') {
            return true;
        }
        $haystack = (string) $haystack;
        $needle = (string) $needle;
        $len = strlen($needle);
        if ($len > strlen($haystack)) {
            return false;
        }

        return substr($haystack, -$len) === $needle;
    }
}
