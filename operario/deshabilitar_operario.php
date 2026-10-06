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

require_once __DIR__ . '/../config/config.php';
if (!isAuthenticated()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Sesión no iniciada. Inicie sesión nuevamente.']);
    exit;
}
requireRole(['admin']);

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

    $idOperario = !empty($input['id_operario']) ? (int)$input['id_operario'] : 0;

    if ($idOperario <= 0) {
        throw new Exception('El identificador del operario es obligatorio para deshabilitarlo.');
    }

    $operarioModel = new Operario();

    $operario = $operarioModel->getById($idOperario);
    if (!$operario || !$operario['activo']) {
        throw new Exception("El operario indicado no existe o ya está deshabilitado.");
    }

    $resultado = $operarioModel->deshabilitar($idOperario);

    if ($resultado) {
        echo json_encode([
            'success' => true,
            'message' => "El operario {$operario['nombre']} {$operario['apellido']} (DNI: {$operario['dni']}) ha sido deshabilitado correctamente.",
            'id_operario' => $idOperario
        ]);
    } else {
        throw new Exception('No se pudo deshabilitar el operario en la base de datos.');
    }
} catch (Throwable $e) {
    echo json_encode([
        'success' => false,
        'error'   => safeErrorMessage($e)
    ]);
}
exit;
