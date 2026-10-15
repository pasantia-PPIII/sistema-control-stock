<?php
/**
 * operario/deshabilitar_operario.php
 * Endpoint AJAX para deshabilitar (soft delete) o volver a habilitar a un operario.
 * Parámetros POST: id_operario, accion ('deshabilitar' por defecto | 'habilitar').
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
    $accion = isset($input['accion']) ? trim((string)$input['accion']) : 'deshabilitar';

    if ($idOperario <= 0) {
        throw new Exception('El identificador del operario es obligatorio.');
    }
    if (!in_array($accion, ['deshabilitar', 'habilitar'], true)) {
        throw new Exception('Acción no válida.');
    }

    $operarioModel = new Operario();

    $operario = $operarioModel->getById($idOperario);
    if (!$operario) {
        throw new Exception("El operario indicado no existe.");
    }

    if ($accion === 'habilitar') {
        if ($operario['activo']) {
            throw new Exception("El operario indicado ya está habilitado.");
        }
        $resultado = $operarioModel->habilitar($idOperario);
        $mensaje = "El operario {$operario['nombre']} {$operario['apellido']} (DNI: {$operario['dni']}) ha sido habilitado correctamente.";
    } else {
        if (!$operario['activo']) {
            throw new Exception("El operario indicado ya está deshabilitado.");
        }
        $resultado = $operarioModel->deshabilitar($idOperario);
        $mensaje = "El operario {$operario['nombre']} {$operario['apellido']} (DNI: {$operario['dni']}) ha sido deshabilitado correctamente.";
    }

    if ($resultado) {
        echo json_encode([
            'success' => true,
            'message' => $mensaje,
            'id_operario' => $idOperario,
            'activo' => ($accion === 'habilitar')
        ]);
    } else {
        throw new Exception('No se pudo procesar la acción sobre el operario en la base de datos.');
    }
} catch (Throwable $e) {
    echo json_encode([
        'success' => false,
        'error'   => safeErrorMessage($e)
    ]);
}
exit;
