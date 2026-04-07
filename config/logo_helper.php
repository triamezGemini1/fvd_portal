<?php
/**
 * Helper para obtener y mostrar el logo de la FVD
 */

if (!function_exists('get_fvd_logo')) {
    /**
     * Obtiene el logo de la FVD desde la base de datos
     * 
     * @return string|null Nombre del archivo del logo o null si no existe
     */
    function get_fvd_logo() {
        static $logo = null;
        
        if ($logo === null) {
            $logo = false;
            try {
                if (!function_exists('env')) {
                    require_once __DIR__ . '/env.php';
                    Env::load();
                }
                $host = env('DB_HOST', '127.0.0.1');
                $port = env('DB_PORT', '3306');
                $db   = env('DB_DATABASE', 'fvdmasteradmin');
                $user = env('DB_USERNAME', 'root');
                $pass = env('DB_PASSWORD', '');
                $dsn = "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4";
                $opts = [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ];
                $pdo = new PDO($dsn, (string) $user, (string) $pass, $opts);
                $stmt = $pdo->query("
                    SELECT logo 
                    FROM asociaciones 
                    WHERE nombre LIKE '%FVD%' OR nombre LIKE '%Federación%' 
                    LIMIT 1
                ");
                $result = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
                $logo = $result ? $result['logo'] : false;
            } catch (Throwable $e) {
                $logo = false;
            }
        }
        
        return $logo ?: null;
    }
}

if (!function_exists('fvd_logo_html')) {
    /**
     * Retorna el HTML para mostrar el logo de la FVD
     * 
     * @param string $size Tamaño del logo (small, medium, large)
     * @param string $class Clases CSS adicionales
     * @return string HTML del logo o emoji por defecto
     */
    function fvd_logo_html($size = 'small', $class = '') {
        $logo = get_fvd_logo();
        
        if (!$logo) {
            // Fallback a emoji si no hay logo
            return '<span class="fvd-icon">🏆</span>';
        }
        
        // Determinar dimensiones según tamaño
        $dimensions = [
            'small' => '30px',
            'medium' => '50px',
            'large' => '100px'
        ];
        
        $height = $dimensions[$size] ?? $dimensions['small'];
        
        if (!function_exists('env')) {
            require_once __DIR__ . '/env.php';
            Env::load();
        }
        $bp = rtrim((string) env('APP_BASE_PATH', '/fvd_portal'), '/');
        $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $logo_url = $proto . '://' . $host . $bp . '/uploads/' . str_replace(['\\', "\0"], '', $logo);
        
        return sprintf(
            '<img src="%s" alt="FVD Logo" class="fvd-logo %s" style="height: %s; width: auto; object-fit: contain; vertical-align: middle;">',
            htmlspecialchars($logo_url),
            htmlspecialchars($class),
            htmlspecialchars($height)
        );
    }
}

if (!function_exists('fvd_logo_inline')) {
    /**
     * Retorna el logo inline para usar en títulos
     * 
     * @return string HTML del logo inline
     */
    function fvd_logo_inline() {
        return fvd_logo_html('small', 'd-inline-block me-2');
    }
}





