<?php
/**
 * Manejador de Variables de Entorno
 * Lee y procesa el archivo .env
 */

class Env {
    private static $loaded = false;
    private static $variables = [];

    /**
     * Cargar variables del archivo .env
     */
    public static function load($path = null) {
        if (self::$loaded) {
            return;
        }

        if ($path === null) {
            $path = dirname(__DIR__) . '/.env';
        }

        if (!file_exists($path)) {
            // Si no existe .env, intentar cargar valores por defecto
            self::loadDefaults();
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        foreach ($lines as $line) {
            // Ignorar comentarios
            if (strpos(trim($line), '#') === 0) {
                continue;
            }

            // Parsear línea
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);

                // Remover comillas
                $value = trim($value, '"\'');

                // Guardar en array y en $_ENV
                self::$variables[$key] = $value;
                $_ENV[$key] = $value;
                putenv("$key=$value");
            }
        }

        self::$loaded = true;
    }

    /**
     * Cargar valores por defecto si no existe .env
     */
    private static function loadDefaults() {
        $defaults = [
            'APP_NAME' => 'Sistema FVD',
            'APP_ENV' => 'production',
            'APP_DEBUG' => 'false',
            'APP_URL' => 'https://federacionvenezolanadedomino.com',
            'APP_BASE_PATH' => '/fvd_portal',
            'DB_HOST' => 'localhost',
            'DB_DATABASE' => 'fvdmasteradmin',
            'DB_USERNAME' => 'root',
            'DB_PASSWORD' => '',
            'SESSION_LIFETIME' => '1800',
            'APP_TIMEZONE' => 'America/Caracas',
        ];

        foreach ($defaults as $key => $value) {
            self::$variables[$key] = $value;
            $_ENV[$key] = $value;
            putenv("$key=$value");
        }

        self::$loaded = true;
    }

    /**
     * Obtener valor de variable de entorno
     */
    public static function get($key, $default = null) {
        if (!self::$loaded) {
            self::load();
        }

        // Buscar en orden: variables cargadas, $_ENV, $_SERVER, getenv
        if (isset(self::$variables[$key])) {
            return self::$variables[$key];
        }

        if (isset($_ENV[$key])) {
            return $_ENV[$key];
        }

        if (isset($_SERVER[$key])) {
            return $_SERVER[$key];
        }

        $value = getenv($key);
        if ($value !== false) {
            return $value;
        }

        return $default;
    }

    /**
     * Obtener valor booleano
     */
    public static function getBool($key, $default = false) {
        $value = self::get($key, $default);
        
        if (is_bool($value)) {
            return $value;
        }

        return in_array(strtolower($value), ['true', '1', 'yes', 'on']);
    }

    /**
     * Obtener valor entero
     */
    public static function getInt($key, $default = 0) {
        return (int) self::get($key, $default);
    }
}

/**
 * Helper function para acceso rápido
 */
function env($key, $default = null) {
    return Env::get($key, $default);
}


