<?php
/**
 * usuario/crear_usuario.php
 * Endpoint AJAX para la creación de un nuevo usuario.
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
    $password = isset($input['password']) ? (string)$input['password'] : (isset($input['contrasena']) ? (string)$input['contrasena'] : '');
    $nombre = isset($input['nombre']) ? trim((string)$input['nombre']) : '';
    $apellido = isset($input['apellido']) ? trim((string)$input['apellido']) : '';
    $id_operario = !empty($input['id_operario']) ? (int)$input['id_operario'] : null;

    if ($dni === '') {
        throw new Exception('El DNI del usuario es obligatorio.');
    }

    if ($nombre === '' || $apellido === '') {
        throw new Exception('El nombre y el apellido del usuario son obligatorios.');
    }

    if ($id_rol <= 0) {
        throw new Exception('Debe seleccionar un rol válido para el usuario.');
    }

    if (empty($password)) {
        throw new Exception('La contraseña es obligatoria.');
    }

    $usuarioModel = new Usuario();

    $datos = [
        'dni'          => $dni,
        'nombre'       => $nombre,
        'apellido'     => $apellido,
        'id_rol'       => $id_rol,
        'id_operario'  => $id_operario
    ];

    $resultado = $usuarioModel->create($datos, $password);

    if ($resultado) {
        echo json_encode([
            'success' => true,
            'message' => "Usuario con DNI '{$dni}' registrado exitosamente.",
            'dni'     => $dni
        ]);
    } else {
        throw new Exception('No se pudo registrar el usuario en la base de datos.');
    }
} catch (Throwable $e) {
    echo json_encode([
        'success' => false,
        'error'   => safeErrorMessage($e)
    ]);
}
exit;
