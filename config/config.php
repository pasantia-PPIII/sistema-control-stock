<?php
// Iniciar sesión si no está iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// =========================================================
// CONFIGURACIÓN DE LA BASE DE DATOS (PostgreSQL)
// =========================================================
// Las credenciales reales van en config/env.php (no versionado); se cargan primero
// y los valores de abajo solo se usan como respaldo si env.php no existe.
if (file_exists(__DIR__ . '/env.php')) {
    require_once __DIR__ . '/env.php';
}
defined('DB_HOST') || define('DB_HOST', 'localhost');          // Servidor de la base de datos
defined('DB_PORT') || define('DB_PORT', '5432');               // Puerto de PostgreSQL (por defecto 5432)
defined('DB_NAME') || define('DB_NAME', 'db_control_stock');   // Nombre de la base de datos
defined('DB_USER') || define('DB_USER', 'postgres');           // Usuario de la base de datos
defined('DB_PASS') || define('DB_PASS', '');                   // Contraseña del usuario

// =========================================================
// CONFIGURACIÓN DE LA APLICACIÓN
// =========================================================
define('SITE_NAME', 'Control de Stock - Taller ONABE');  // Nombre del sistema
define('BASE_URL', 'http://sistema-control-stock/');     // URL base del proyecto
define('BASE_PATH', __DIR__ . '/../');                     // Ruta absoluta del proyecto

// =========================================================
// CONFIGURACIÓN DE ARCHIVOS SUBIDOS
// =========================================================
define('UPLOAD_PATH', BASE_PATH . 'uploads/');             // Carpeta donde se guardan archivos
define('MAX_FILE_SIZE', 10 * 1024 * 1024);                 // Tamaño máximo: 10 MB

// =========================================================
// CONFIGURACIÓN DE ERRORES
// =========================================================
define('DEBUG_MODE', true);  // true en desarrollo, false en producción

if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// =========================================================
// FUNCIONES GLOBALES DE AYUDA


/*
 * Redireccionar a una URL interna
 */
function redirect($url) {
    header("Location: " . BASE_URL . $url);
    exit;
}

/**
 * Establecer un mensaje de alerta en sesión
 */
function setAlert($message, $type = 'info') {
    $_SESSION['alert'] = [
        'message' => $message,
        'type' => $type  // 'success', 'danger', 'warning', 'info'
    ];
}

/**
 * Obtener y limpiar la alerta de sesión
 */
function getAlert() {
    if (isset($_SESSION['alert'])) {
        $alert = $_SESSION['alert'];
        unset($_SESSION['alert']);
        return $alert;
    }
    return null;
}

/**
 * Verificar si el usuario está autenticado
 */
function isAuthenticated() {
    return isset($_SESSION['dni_usuario']);
}

/**
 * Verificar si el usuario es Administrador
 */
function isAdmin() {
    return isset($_SESSION['nombre_rol']) && $_SESSION['nombre_rol'] === 'administrador';
}

/**
 * Verificar si el usuario es Pañolero (o Admin)
 */
function isPanolero() {
    return isset($_SESSION['nombre_rol']) && 
           in_array($_SESSION['nombre_rol'], ['administrador', 'panolero']);
}

/**
 * Verificar si el usuario puede editar (Admin/Pañolero)
 */
function canEdit() {
    return isPanolero();
}

/**
 * Verificar si el usuario puede gestionar otros usuarios (solo Administrador)
 */
function canManageUsers() {
    return isAdmin();
}
?>