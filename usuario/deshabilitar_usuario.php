<?php
/**
 * usuario/deshabilitar_usuario.php
 * Endpoint AJAX para alternar el estado (deshabilitar / habilitar) de un usuario.
 * Responde exclusivamente en formato JSON.
 */

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'error'   => 'Método no permitido. Se requiere petición POST.'
    ]);
    exit;
}

require_once __DIR__ . '/../config/config.php';
if (!isAuthenticated()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Sesión no iniciada. Inicie sesión nuevamente.']);
    exit;
}
requireRole(['admin']);

require_once __DIR__ . '/../clases/usuario.php';

try {
    $input = $_POST;
    if (empty($input)) {
        $raw = file_get_contents('php://input');
        $json = json_decode($raw, true);
        if (is_array($json)) {
            $input = $json;
        }
    }

    $dni = isset($input['dni']) ? trim((string)$input['dni']) : '';

    if ($dni === '') {
        throw new Exception('El DNI del usuario es obligatorio para cambiar su estado.');
    }

    // Un administrador no puede deshabilitar su propia cuenta
    if ($dni === (string)($_SESSION['dni_usuario'] ?? '')) {
        throw new Exception('No puede deshabilitar su propio usuario.');
    }

    $usuarioModel = new Usuario();

    // getByDni() solo devuelve usuarios activos: si lo encuentra se deshabilita; si no, se verifica que exista y se habilita
    if ($usuarioModel->getByDni($dni)) {
        $usuarioModel->deshabilitar($dni);
        $nuevoEstado = false;
    } else {
        $existe = array_filter($usuarioModel->getAll(true), function ($u) use ($dni) {
            return (string)$u['dni'] === $dni;
        });
        if (empty($existe)) {
            throw new Exception("No existe un usuario con DNI '{$dni}'.");
        }
        $usuarioModel->habilitar($dni);
        $nuevoEstado = true;
    }

    $mensaje = $nuevoEstado 
        ? "El usuario con DNI '{$dni}' ha sido habilitado exitosamente." 
        : "El usuario con DNI '{$dni}' ha sido deshabilitado correctamente.";

    echo json_encode([
        'success' => true,
        'message' => $mensaje,
        'dni'     => $dni,
        'activo'  => $nuevoEstado
    ]);
} catch (Throwable $e) {
    echo json_encode([
        'success' => false,
        'error'   => safeErrorMessage($e)
    ]);
}
exit;
