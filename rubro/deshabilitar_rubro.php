<?php
/**
 * rubro/deshabilitar_rubro.php
 * Endpoint AJAX para deshabilitar o habilitar (soft delete/reactivar) un rubro.
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

    $id_rubro = isset($input['id_rubro']) ? (int)$input['id_rubro'] : 0;
    $accion = isset($input['accion']) ? trim((string)$input['accion']) : 'deshabilitar';

    if ($id_rubro <= 0) {
        throw new Exception('El identificador del rubro es obligatorio y debe ser válido.');
    }

    $rubroModel = new Rubro();

    $rubro = $rubroModel->getById($id_rubro);
    if (!$rubro) {
        throw new Exception("El rubro con ID {$id_rubro} no existe.");
    }

    $nombre = $rubro['nombre'];
    $tieneInsumos = $rubroModel->tieneInsumos($id_rubro);

    if ($accion === 'habilitar') {
        $resultado = $rubroModel->habilitar($id_rubro);
        $mensaje = "El rubro '{$nombre}' ha sido reactivado y habilitado correctamente.";
    } else {
        $resultado = $rubroModel->deshabilitar($id_rubro);
        $mensaje = "El rubro '{$nombre}' ha sido deshabilitado correctamente.";
        if ($tieneInsumos) {
            $mensaje .= " (Nota: Los insumos asociados continúan en el sistema para historial).";
        }
    }

    if ($resultado) {
        echo json_encode([
            'success'       => true,
            'message'       => $mensaje,
            'id_rubro'      => $id_rubro,
            'tiene_insumos' => $tieneInsumos
        ]);
    } else {
        throw new Exception("No se pudo procesar la acción sobre el rubro.");
    }
} catch (Throwable $e) {
    echo json_encode([
        'success' => false,
        'error'   => safeErrorMessage($e)
    ]);
}
exit;
