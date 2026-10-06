<?php
/**
 * rubro/crear_rubro.php
 * Endpoint AJAX para la creación de un nuevo rubro.
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

require_once __DIR__ . '/../clases/rubro.php';

try {
    $input = $_POST;
    if (empty($input)) {
        $raw = file_get_contents('php://input');
        $json = json_decode($raw, true);
        if (is_array($json)) {
            $input = $json;
        }
    }

    $nombre = isset($input['nombre']) ? trim((string)$input['nombre']) : '';
    $descripcion = isset($input['descripcion']) ? trim((string)$input['descripcion']) : null;

    if ($nombre === '') {
        throw new Exception('El nombre del rubro es obligatorio.');
    }

    $rubroModel = new Rubro();

    // Validar si el nombre ya existe
    if ($rubroModel->existeNombre($nombre)) {
        throw new Exception("Ya existe un rubro registrado con el nombre '{$nombre}'.");
    }

    $datos = [
        'nombre'      => $nombre,
        'descripcion' => ($descripcion !== '' && $descripcion !== null) ? $descripcion : null
    ];

    $resultado = $rubroModel->create($datos);

    if ($resultado) {
        // Obtenemos el registro recién creado
        $nuevoRubro = $rubroModel->getByNombre($nombre);
        $idRubro = $nuevoRubro ? $nuevoRubro['id_rubro'] : null;

        echo json_encode([
            'success'  => true,
            'message'  => "Rubro '{$nombre}' registrado exitosamente.",
            'id_rubro' => $idRubro
        ]);
    } else {
        throw new Exception('No se pudo registrar el rubro en la base de datos.');
    }
} catch (Throwable $e) {
    echo json_encode([
        'success' => false,
        'error'   => safeErrorMessage($e)
    ]);
}
exit;
