<?php
/**
 * operario/deshabilitar_operario.php
 * Endpoint AJAX para dar de baja / deshabilitar (soft delete) a un operario.
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

    if ($dni === '') {
        throw new Exception('El DNI del operario es obligatorio para deshabilitarlo.');
    }

    $operarioModel = new Operario();

    $operario = $operarioModel->getByDni($dni);
    if (!$operario) {
        throw new Exception("El operario con DNI '{$dni}' no existe o ya está deshabilitado.");
    }

    $resultado = $operarioModel->deshabilitar($dni);

    if ($resultado) {
        echo json_encode([
            'success' => true,
            'message' => "El operario {$operario['nombre']} {$operario['apellido']} (DNI: {$dni}) ha sido deshabilitado correctamente.",
            'dni'     => $dni
        ]);
    } else {
        throw new Exception('No se pudo deshabilitar el operario en la base de datos.');
    }
} catch (Throwable $e) {
    echo json_encode([
        'success' => false,
        'error'   => $e->getMessage()
    ]);
}
exit;
