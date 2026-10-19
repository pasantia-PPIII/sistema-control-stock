<?php
/**
 * operario/deshabilitar_operario.php
 * Endpoint AJAX para deshabilitar o habilitar (soft delete/reactivar) a un operario.
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
        throw new Exception('El identificador del operario es obligatorio y debe ser válido.');
    }

    $operarioModel = new Operario();

    $operario = $operarioModel->getById($idOperario);
    if (!$operario) {
        throw new Exception("El operario con ID {$idOperario} no existe.");
    }

    $nombreCompleto = trim($operario['nombre'] . ' ' . $operario['apellido']);
    $dni = $operario['dni'];

    if ($accion === 'habilitar') {
        $resultado = $operarioModel->habilitar($idOperario);
        $mensaje = "El operario {$nombreCompleto} (DNI: {$dni}) ha sido reactivado y habilitado correctamente.";
    } elseif ($accion === 'deshabilitar') {
        $resultado = $operarioModel->deshabilitar($idOperario);
        $mensaje = "El operario {$nombreCompleto} (DNI: {$dni}) ha sido deshabilitado correctamente.";
    } else {
        throw new Exception("Acción no válida solicitada. Se esperaba 'deshabilitar' o 'habilitar'.");
    }

    if ($resultado) {
        echo json_encode([
            'success'     => true,
            'message'     => $mensaje,
            'id_operario' => $idOperario,
            'accion'      => $accion
        ]);
    } else {
        throw new Exception("No se pudo procesar la acción '{$accion}' sobre el operario.");
    }
} catch (Throwable $e) {
    echo json_encode([
        'success' => false,
        'error'   => safeErrorMessage($e)
    ]);
}
exit;
