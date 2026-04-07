<?php
/**
 * Inicialización de Protección para Módulos
 * Incluir este archivo al inicio de cada módulo para protegerlo
 * 
 * Uso:
 * require_once __DIR__ . '/../config/init_protection.php';
 */

// Asegurarse de que se cargue el bootstrap primero
if (!defined('BASE_PATH')) {
    // Intentar cargar desde diferentes niveles
    if (file_exists(__DIR__ . '/../bootstrap.php')) {
        require_once __DIR__ . '/../bootstrap.php';
    } elseif (file_exists(__DIR__ . '/../../bootstrap.php')) {
        require_once __DIR__ . '/../../bootstrap.php';
    } else {
        die('ERROR: No se pudo cargar bootstrap.php');
    }
}

// Cargar helpers de seguridad
if (!class_exists('SecurityHelper')) {
    require_once BASE_PATH . '/helpers/SecurityHelper.php';
}

// Iniciar sesión
SecurityHelper::startSession();

// Verificar autenticación
SecurityHelper::requireAuth();

// Actualizar tiempo de actividad
if (isset($_SESSION['login_time'])) {
    $inactive = env('SESSION_LIFETIME', 1800);
    if (time() - $_SESSION['login_time'] > $inactive) {
        SecurityHelper::logout();
        header('Location: ' . url('login.php?timeout=1'));
        exit;
    }
    $_SESSION['login_time'] = time();
}

// Protección CSRF para peticiones POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verificar token CSRF si está habilitado
    if (env('CSRF_PROTECTION', true)) {
        if (isset($_POST['csrf_token'])) {
            if (!SecurityHelper::verifyCSRFToken($_POST['csrf_token'])) {
                die('ERROR: Token CSRF inválido');
            }
        }
    }
}

// Generar token CSRF para formularios
if (!isset($csrf_token)) {
    $csrf_token = SecurityHelper::generateCSRFToken();
}


