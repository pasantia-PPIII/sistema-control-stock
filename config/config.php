<?php
// =========================================================
// ENTORNO: credenciales y ajustes locales (config/env.php, no versionado)
// =========================================================
// env.php puede definir DB_*, APP_DEBUG (true solo en desarrollo) y, opcionalmente, BASE_URL.
if (file_exists(__DIR__ . '/env.php')) {
    require_once __DIR__ . '/env.php';
}
defined('DB_HOST') || define('DB_HOST', 'localhost');          // Servidor de la base de datos
defined('DB_PORT') || define('DB_PORT', '5432');               // Puerto de PostgreSQL (por defecto 5432)
defined('DB_NAME') || define('DB_NAME', 'db_control_stock');   // Nombre de la base de datos
defined('DB_USER') || define('DB_USER', 'postgres');           // Usuario de la base de datos
defined('DB_PASS') || define('DB_PASS', '');                   // Contraseña del usuario

// =========================================================
// SESIÓN (cookie endurecida)
// =========================================================
$esHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (($_SERVER['SERVER_PORT'] ?? '') == 443);

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $esHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// =========================================================
// CONFIGURACIÓN DE LA APLICACIÓN
// =========================================================
define('SITE_NAME', 'Control de Stock - Taller ONABE');  // Nombre del sistema
define('BASE_PATH', __DIR__ . '/../');                     // Ruta absoluta del proyecto

// BASE_URL (siempre termina en "/"): se toma de env.php si está definida; si no, se deduce
// del host de la petición y de la ubicación del proyecto respecto del document root.
if (!defined('BASE_URL')) {
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    if (!preg_match('/^[A-Za-z0-9.\-]+(:\d{1,5})?$/', $host)) {
        $host = 'localhost';
    }

    $subRuta = '';
    $docRoot = isset($_SERVER['DOCUMENT_ROOT']) ? realpath($_SERVER['DOCUMENT_ROOT']) : false;
    $proyecto = realpath(__DIR__ . '/..');
    if ($docRoot && $proyecto) {
        $docRoot = rtrim(str_replace('\\', '/', $docRoot), '/');
        $proyecto = str_replace('\\', '/', $proyecto);
        if (stripos($proyecto, $docRoot) === 0) {
            $subRuta = rtrim(substr($proyecto, strlen($docRoot)), '/');
        }
    }

    define('BASE_URL', ($esHttps ? 'https' : 'http') . '://' . $host . $subRuta . '/');
}

// =========================================================
// CONFIGURACIÓN DE ARCHIVOS SUBIDOS
// =========================================================
define('UPLOAD_PATH', BASE_PATH . 'uploads/');             // Carpeta donde se guardan archivos
define('MAX_FILE_SIZE', 10 * 1024 * 1024);                 // Tamaño máximo: 10 MB

// =========================================================
// CONFIGURACIÓN DE ERRORES
// =========================================================
// Por defecto producción: no se muestran errores. Para desarrollo definir APP_DEBUG=true en env.php.
define('DEBUG_MODE', defined('APP_DEBUG') && APP_DEBUG === true);

error_reporting(E_ALL);
ini_set('log_errors', '1');
ini_set('display_errors', DEBUG_MODE ? '1' : '0');

// =========================================================
// SEGURIDAD: CSRF Y RESPUESTAS DE ERROR SEGURAS
// =========================================================

/**
 * Token CSRF de la sesión actual (se genera una sola vez por sesión).
 */
function csrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Campo oculto con el token CSRF para formularios HTML clásicos.
 */
function csrfField() {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * Valida el token enviado en el header X-CSRF-Token o en el campo csrf_token.
 */
function csrfVerify() {
    $enviado = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? '');
    return is_string($enviado) && $enviado !== '' && hash_equals(csrfToken(), $enviado);
}

/**
 * Mensaje apto para mostrar al usuario: los errores de base de datos (PDO / SQLSTATE) se
 * registran en el log y se reemplazan por un texto genérico; los de validación pasan tal cual.
 */
function safeErrorMessage($e) {
    $mensaje = $e->getMessage();
    if ($e instanceof PDOException || stripos($mensaje, 'SQLSTATE') !== false) {
        error_log('[DB] ' . $mensaje);
        return DEBUG_MODE ? $mensaje : 'Ocurrió un error al procesar la solicitud. Intente nuevamente.';
    }
    return $mensaje;
}

// Toda petición POST autenticada debe traer un token CSRF válido (los endpoints no autenticados
// responden 401 por sí mismos). login.php define CSRF_REQUIRE para validar también antes de autenticar.
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'
    && (defined('CSRF_REQUIRE') || isset($_SESSION['dni_usuario']))
    && !csrfVerify()) {
    http_response_code(403);
    if (defined('CSRF_REQUIRE')) {
        die('Solicitud no válida (token de seguridad inválido o vencido). Recargue la página e intente nuevamente.');
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => 'Token de seguridad inválido o vencido. Recargue la página e intente nuevamente.']);
    exit;
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
 * Rol del usuario en sesión normalizado: minúsculas y sin tildes.
 * Roles del sistema: 'admin', 'panolero', 'vista'.
 */
function currentRole() {
    $rol = $_SESSION['nombre_rol'] ?? '';
    $rol = mb_strtolower(trim($rol), 'UTF-8');
    $rol = strtr($rol, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n']);
    return $rol;
}

/**
 * Verificar si el usuario es Administrador
 */
function isAdmin() {
    return currentRole() === 'admin';
}

/**
 * Verificar si el usuario es Pañolero (o Admin)
 */
function isPanolero() {
    return in_array(currentRole(), ['admin', 'panolero'], true);
}

/**
 * Verificar si el usuario tiene rol de solo lectura (Vista)
 */
function isVista() {
    return currentRole() === 'vista';
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

/**
 * Exige que el usuario autenticado tenga alguno de los roles indicados ('admin', 'panolero', 'vista').
 * - Peticiones POST / AJAX: responde 403 en JSON y corta.
 * - Páginas: guarda una alerta y redirige al Panel de Control.
 */
function requireRole(array $roles) {
    if (in_array(currentRole(), $roles, true)) {
        return;
    }

    $esAjax = (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST')
        || isset($_GET['ajax'])
        || isset($_GET['action'])
        || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');

    if ($esAjax) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => 'No tiene permisos para realizar esta acción.']);
        exit;
    }

    setAlert('No tiene permisos para acceder a esa sección.', 'danger');
    redirect('panel/dashboard.php');
}

/**
 * Permisos del usuario actual en formato listo para publicar al JavaScript de las vistas.
 */
function permisosActuales() {
    return [
        'rol'    => currentRole(),
        'admin'  => isAdmin(),
        'editar' => canEdit(),
        'dni'    => $_SESSION['dni_usuario'] ?? ''
    ];
}
