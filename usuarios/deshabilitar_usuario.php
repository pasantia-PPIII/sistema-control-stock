<?php
/**
 * usuarios/deshabilitar_usuario.php
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

    $usuarioModel = new Usuario();

    $nuevoEstado = $usuarioModel->alternarEstado($dni);

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
        'error'   => $e->getMessage()
    ]);
}
exit;
