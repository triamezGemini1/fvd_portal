<?php
/**
 * Clase de Conexión a Base de Datos
 * Utiliza variables de entorno para configuración
 */

// Cargar configuración de entorno
if (!function_exists('env')) {
    require_once __DIR__ . '/env.php';
    Env::load();
}

class Database {
    private static $connection = null;
    
    public static function getConnection() {
        // Retornar conexión existente si ya está creada
        if (self::$connection !== null) {
            return self::$connection;
        }

        // Obtener configuración desde variables de entorno
        $host = env('DB_HOST', 'localhost');
        $db   = env('DB_DATABASE', 'fvdmasteradmin');
        $user = env('DB_USERNAME', 'root');
        $pass = env('DB_PASSWORD', '');
        $port = env('DB_PORT', '3306');

        try {
            $dsn = "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
            ];
            
            self::$connection = new PDO($dsn, $user, $pass, $options);
            
            return self::$connection;
        } catch (PDOException $e) {
            // En producción, no mostrar detalles del error
            if (env('APP_ENV') === 'production') {
                error_log("Database Connection Error: " . $e->getMessage());
                die("❌ Error de conexión a la base de datos. Contacte al administrador.");
            } else {
                die("❌ Error de conexión: " . $e->getMessage());
            }
        }
    }

    /**
     * Cerrar conexión
     */
    public static function closeConnection() {
        self::$connection = null;
    }

    /**
     * Verificar conexión
     */
    public static function testConnection() {
        try {
            $pdo = self::getConnection();
            $stmt = $pdo->query("SELECT 1");
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
}
