<?php
/**
 * operario/crear_operario.php
 * Endpoint AJAX para la creación de un nuevo operario.
 * Responde exclusivamente en formato JSON.
 */

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'error' => 'Método no permitido. Se requiere petición POST.'
    ]);
    exit;
}

require_once __DIR__ . '/../clases/operario.php';

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
    $apellido = isset($input['apellido']) ? trim((string)$input['apellido']) : '';
    $nombre = isset($input['nombre']) ? trim((string)$input['nombre']) : '';
    $legajo = isset($input['legajo']) ? trim((string)$input['legajo']) : (isset($input['codigo']) ? trim((string)$input['codigo']) : '');
    $telefono = isset($input['telefono']) ? trim((string)$input['telefono']) : '';
    $id_rubro = !empty($input['id_rubro']) ? (int)$input['id_rubro'] : null;
    $observaciones = isset($input['observaciones']) ? trim((string)$input['observaciones']) : null;

    if ($dni === '') {
        throw new Exception('El DNI del operario es obligatorio.');
    }

    if ($apellido === '') {
        throw new Exception('El apellido del operario es obligatorio.');
    }

    if ($nombre === '') {
        throw new Exception('El nombre del operario es obligatorio.');
    }

    $operarioModel = new Operario();

    // Validar si el DNI ya existe
    if ($operarioModel->existeDni($dni)) {
        throw new Exception("Ya existe un operario registrado con el DNI '{$dni}'.");
    }

    $datos = [
        'dni'           => $dni,
        'apellido'      => $apellido,
        'nombre'        => $nombre,
        'legajo'        => $legajo !== '' ? $legajo : null,
        'telefono'      => $telefono !== '' ? $telefono : null,
        'id_rubro'      => $id_rubro,
        'observaciones' => $observaciones !== '' ? $observaciones : null
    ];

    $resultado = $operarioModel->create($datos);

    if ($resultado) {
        echo json_encode([
            'success' => true,
            'message' => 'Operario registrado exitosamente.',
            'dni'     => $dni
        ]);
    } else {
        throw new Exception('No se pudo registrar el operario en la base de datos.');
    }
} catch (Throwable $e) {
    echo json_encode([
        'success' => false,
        'error'   => $e->getMessage()
    ]);
}
exit;
