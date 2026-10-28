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
defined('DB_PASS') || define('DB_PASS', '1234');                   // Contraseña del usuario

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
function csrfToken()
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Campo oculto con el token CSRF para formularios HTML clásicos.
 */
function csrfField()
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * Valida el token enviado en el header X-CSRF-Token o en el campo csrf_token.
 */
function csrfVerify()
{
    $enviado = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? '');
    return is_string($enviado) && $enviado !== '' && hash_equals(csrfToken(), $enviado);
}

/**
 * Mensaje apto para mostrar al usuario: los errores de base de datos (PDO / SQLSTATE) se
 * registran en el log y se reemplazan por un texto genérico; los de validación pasan tal cual.
 */
function safeErrorMessage($e)
{
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
    && !csrfVerify()
) {
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
function redirect($url)
{
    header("Location: " . BASE_URL . $url);
    exit;
}

/**
 * Establecer un mensaje de alerta en sesión
 */
function setAlert($message, $type = 'info')
{
    $_SESSION['alert'] = [
        'message' => $message,
        'type' => $type  // 'success', 'danger', 'warning', 'info'
    ];
}

/**
 * Obtener y limpiar la alerta de sesión
 */
function getAlert()
{
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
function isAuthenticated()
{
    return isset($_SESSION['dni_usuario']);
}

/**
 * Rol del usuario en sesión normalizado: minúsculas y sin tildes.
 * Roles del sistema: 'admin', 'panolero', 'vista'.
 */
function currentRole()
{
    $rol = $_SESSION['nombre_rol'] ?? '';
    $rol = mb_strtolower(trim($rol), 'UTF-8');
    $rol = strtr($rol, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n']);
    return $rol;
}

/**
 * Verificar si el usuario es Administrador
 */
function isAdmin()
{
    return currentRole() === 'admin';
}

/**
 * Verificar si el usuario es Pañolero (o Admin)
 */
function isPanolero()
{
    return in_array(currentRole(), ['admin', 'panolero'], true);
}

/**
 * Verificar si el usuario tiene rol de solo lectura (Vista)
 */
function isVista()
{
    return currentRole() === 'vista';
}

/**
 * Verificar si el usuario puede editar (Admin/Pañolero)
 */
function canEdit()
{
    return isPanolero();
}

/**
 * Verificar si el usuario puede gestionar otros usuarios (solo Administrador)
 */
function canManageUsers()
{
    return isAdmin();
}

/**
 * Exige que el usuario autenticado tenga alguno de los roles indicados ('admin', 'panolero', 'vista').
 * - Peticiones POST / AJAX: responde 403 en JSON y corta.
 * - Páginas: guarda una alerta y redirige al Panel de Control.
 */
function requireRole(array $roles)
{
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
function permisosActuales()
{
    return [
        'rol'    => currentRole(),
        'admin'  => isAdmin(),
        'editar' => canEdit(),
        'dni'    => $_SESSION['dni_usuario'] ?? ''
    ];
}

/**
 * Retorna el SVG de un icono de acción del sistema (estilo unificado Feather / Lucide).
 * Tipos admitidos: 'view', 'edit', 'delete', 'disable', 'enable', 'dispatch'.
 */
function iconoAccion($tipo, $size = 15)
{
    switch ($tipo) {
        case 'view':
            return '<svg width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>';
        case 'edit':
            return '<svg width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>';
        case 'delete':
        case 'disable':
            return '<svg width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg>';
        case 'enable':
            return '<svg width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="1 4 1 10 7 10"></polyline><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"></path></svg>';
        case 'dispatch':
            return '<svg width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 17l5-5-5-5"></path><path d="M21 12H9"></path><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path></svg>';
        default:
            return '';
    }
}

// =========================================================
// AUDITORÍA Y TRAZABILIDAD
// =========================================================
require_once __DIR__ . '/../clases/auditoria.php';

/**
 * Función global reutilizable para registrar eventos de auditoría y trazabilidad.
 *
 * @param string      $accion           Acción realizada (LOGIN, LOGOUT, CREAR, MODIFICAR, DESHABILITAR, HABILITAR, ENTREGA, DEVOLUCION, etc.)
 * @param string      $modulo           Módulo afectado (AUTH, USUARIOS, INSUMOS, MOVIMIENTOS, ORDENES_TRABAJO, OPERARIOS, RUBROS, etc.)
 * @param string      $descripcion      Descripción humana clara y contextual del evento
 * @param array|null  $datosAnteriores  Estado previo de los datos (o arreglo con 'antes')
 * @param array|null  $datosNuevos      Nuevo estado de los datos (o arreglo con 'despues')
 * @param string|null $dniUsuario       DNI explícito (si no se especifica, se toma de la sesión actual)
 * @param string|null $nombreUsuario    Nombre explícito (si no se especifica, se toma de la sesión actual)
 * @param string|null $rolUsuario       Rol explícito (si no se especifica, se toma de la sesión actual)
 * @return bool True si se registró correctamente, false si ocurrió un error
 */
function registrarAuditoria(
    string $accion,
    string $modulo,
    string $descripcion,
    ?array $datosAnteriores = null,
    ?array $datosNuevos = null,
    ?string $dniUsuario = null,
    ?string $nombreUsuario = null,
    ?string $rolUsuario = null
): bool {
    try {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        if (str_contains($ip, ',')) {
            $ip = trim(explode(',', $ip)[0]);
        }

        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Desconocido';

        $dni = $dniUsuario ?? ($_SESSION['dni_usuario'] ?? null);
        $nombre = $nombreUsuario ?? ($_SESSION['usuario_nombre'] ?? null);
        $rol = $rolUsuario ?? ($_SESSION['nombre_rol'] ?? null);

        static $instanciaAuditoria = null;
        if ($instanciaAuditoria === null) {
            $instanciaAuditoria = new Auditoria();
        }

        return (bool)$instanciaAuditoria->registrar([
            'dni_usuario'      => $dni,
            'nombre_usuario'   => $nombre,
            'rol_usuario'      => $rol,
            'accion'           => $accion,
            'modulo'           => $modulo,
            'descripcion'      => $descripcion,
            'ip'               => $ip,
            'user_agent'       => $userAgent,
            'datos_anteriores' => $datosAnteriores,
            'datos_nuevos'     => $datosNuevos
        ]);
    } catch (Throwable $e) {
        error_log('[registrarAuditoria] Error: ' . $e->getMessage());
        return false;
    }
}
