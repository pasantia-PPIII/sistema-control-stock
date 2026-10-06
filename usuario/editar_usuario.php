<?php
/**
 * usuario/editar_usuario.php
 * Endpoint AJAX para la edición de un usuario existente.
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
    $id_rol = isset($input['id_rol']) ? (int)$input['id_rol'] : 0;
    $password = isset($input['password']) ? trim((string)$input['password']) : (isset($input['contrasena']) ? trim((string)$input['contrasena']) : '');
    $nombre = isset($input['nombre']) ? trim((string)$input['nombre']) : '';
    $apellido = isset($input['apellido']) ? trim((string)$input['apellido']) : '';
    $id_operario = !empty($input['id_operario']) ? (int)$input['id_operario'] : null;

    if ($dni === '') {
        throw new Exception('El DNI del usuario es obligatorio para editarlo.');
    }

    if ($id_rol <= 0) {
        throw new Exception('Debe seleccionar un rol válido para el usuario.');
    }

    $usuarioModel = new Usuario();

    $datos = [
        'id_rol'       => $id_rol,
        'id_operario'  => $id_operario
    ];

    if ($nombre !== '') {
        $datos['nombre'] = $nombre;
    }
    if ($apellido !== '') {
        $datos['apellido'] = $apellido;
    }

    if ($password !== '') {
        $datos['password'] = $password;
    }

    $resultado = $usuarioModel->update($dni, $datos);

    if ($resultado) {
        echo json_encode([
            'success' => true,
            'message' => "Usuario con DNI '{$dni}' actualizado exitosamente.",
            'dni'     => $dni
        ]);
    } else {
        throw new Exception('No se pudo actualizar el usuario en la base de datos.');
    }
} catch (Throwable $e) {
    echo json_encode([
        'success' => false,
        'error'   => safeErrorMessage($e)
    ]);
}
exit;
