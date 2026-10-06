<?php
/**
 * auth/logout.php
 * Cierra la sesión. Solo acepta POST: config.php ya valida el token CSRF de toda petición POST
 * autenticada, por lo que un enlace o imagen externa no puede cerrar la sesión del usuario.
 */
require_once __DIR__ . '/../config/config.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    // Entrar por URL (GET) no cierra la sesión: se vuelve a donde corresponda
    redirect(isAuthenticated() ? 'panel/dashboard.php' : 'auth/login.php');
}

// Vaciar arreglo de sesión
$_SESSION = [];

// Invalidar la cookie de sesión si existe
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params['path'], $params['domain'],
        $params['secure'], $params['httponly']
    );
}

// Destruir la sesión en el servidor
session_destroy();

// Redirigir al inicio de sesión
header('Location: login.php');
exit;
