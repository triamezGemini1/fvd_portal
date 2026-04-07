<?php
/**
 * Conexión PDO centralizada para FVD Master Admin.
 */

declare(strict_types=1);

if (!function_exists('env')) {
    $envBootstrap = dirname(__DIR__, 2) . '/config/env.php';
    if (is_file($envBootstrap)) {
        require_once $envBootstrap;
    }
}

if (!function_exists('env')) {
    /**
     * @param mixed $default
     * @return mixed
     */
    function env(string $key, $default = null)
    {
        if (array_key_exists($key, $_ENV)) {
            return $_ENV[$key];
        }
        if (array_key_exists($key, $_SERVER)) {
            return $_SERVER[$key];
        }
        $g = getenv($key);

        return $g !== false ? $g : $default;
    }
}

/**
 * Obtiene una instancia PDO con opciones seguras y manejo uniforme de errores.
 *
 * @throws PDOException Si la conexión falla (el llamador puede capturarla).
 */
function fvd_db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    // FVD_DB_* permite otra base/credenciales que DB_* (ej. app en federaci1_* y usuarios FVD en fvdmasteradmin).
    $host = (string) env('FVD_DB_HOST', env('DB_HOST', '127.0.0.1'));
    $port = (string) env('FVD_DB_PORT', env('DB_PORT', '3306'));
    $db   = (string) env('FVD_DB_DATABASE', env('DB_DATABASE', 'fvdmasteradmin'));
    $user = (string) env('FVD_DB_USERNAME', env('DB_USERNAME', 'root'));
    $pass = (string) env('FVD_DB_PASSWORD', env('DB_PASSWORD', ''));

    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        $host,
        $port,
        $db
    );

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci',
    ];

    $pdo = new PDO($dsn, (string) $user, (string) $pass, $options);

    return $pdo;
}

/**
 * Prueba la conexión; devuelve false si falla (sin lanzar al usuario final).
 */
function fvd_db_ping(): bool
{
    try {
        fvd_db()->query('SELECT 1');

        return true;
    } catch (PDOException $e) {
        $isProd = (string) env('APP_ENV', 'production') === 'production';
        if ($isProd) {
            error_log('[fvdmasteradmin/db] ' . $e->getMessage());
        }

        return false;
    }
}
