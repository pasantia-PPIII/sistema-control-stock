<?php
/**
 * insumo/deshabilitar_insumo.php
 * Endpoint AJAX para dar de baja / deshabilitar (soft-delete) un insumo.
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
requireRole(['admin', 'panolero']);

require_once __DIR__ . '/../clases/insumo.php';

try {
    $input = $_POST;
    if (empty($input)) {
        $raw = file_get_contents('php://input');
        $json = json_decode($raw, true);
        if (is_array($json)) {
            $input = $json;
        }
    }

    $codigo = isset($input['codigo']) ? trim((string)$input['codigo']) : '';

    if ($codigo === '') {
        throw new Exception('El código del insumo es obligatorio para darlo de baja.');
    }

    $insumoModel = new Insumo();

    $existente = $insumoModel->getByCodigo($codigo);
    if (!$existente) {
        throw new Exception("El insumo con código '{$codigo}' no existe o ya se encuentra deshabilitado.");
    }

    $resultado = $insumoModel->deshabilitar($codigo);

    if ($resultado) {
        echo json_encode([
            'success' => true,
            'message' => "El insumo '{$codigo}' ha sido deshabilitado correctamente.",
            'codigo'  => $codigo
        ]);
    } else {
        throw new Exception('No se pudo deshabilitar el insumo en la base de datos.');
    }
} catch (Throwable $e) {
    echo json_encode([
        'success' => false,
        'error'   => safeErrorMessage($e)
    ]);
}
exit;
