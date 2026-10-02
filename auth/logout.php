<?php
// auth/logout.php
session_start();

// Vaciar arreglo de sesión
$_SESSION = [];

// Invalidar la cookie de sesión si existe
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destruir la sesión en el servidor
session_destroy();

// Redirigir al inicio de sesión
header("Location: login.php");
exit;