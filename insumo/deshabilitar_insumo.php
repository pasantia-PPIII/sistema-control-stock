<?php
/**
 * insumo/deshabilitar_insumo.php
 * Endpoint AJAX para deshabilitar (soft-delete) o volver a habilitar un insumo.
 * Parámetros POST: codigo, accion ('deshabilitar' por defecto | 'habilitar').
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
    $accion = isset($input['accion']) ? trim((string)$input['accion']) : 'deshabilitar';

    if ($codigo === '') {
        throw new Exception('El código del insumo es obligatorio.');
    }
    if (!in_array($accion, ['deshabilitar', 'habilitar'], true)) {
        throw new Exception('Acción no válida.');
    }

    $insumoModel = new Insumo();

    $existente = $insumoModel->getEstadoByCodigo($codigo);
    if (!$existente) {
        throw new Exception("El insumo con código '{$codigo}' no existe.");
    }

    if ($accion === 'habilitar') {
        if ($existente['activo']) {
            throw new Exception("El insumo '{$codigo}' ya se encuentra habilitado.");
        }
        $resultado = $insumoModel->habilitar($codigo);
        $mensaje = "El insumo '{$codigo}' ha sido habilitado correctamente.";
    } else {
        if (!$existente['activo']) {
            throw new Exception("El insumo '{$codigo}' ya se encuentra deshabilitado.");
        }
        $resultado = $insumoModel->deshabilitar($codigo);
        $mensaje = "El insumo '{$codigo}' ha sido deshabilitado correctamente.";
    }

    if ($resultado) {
        echo json_encode([
            'success' => true,
            'message' => $mensaje,
            'codigo'  => $codigo,
            'activo'  => ($accion === 'habilitar')
        ]);
    } else {
        throw new Exception('No se pudo procesar la acción sobre el insumo en la base de datos.');
    }
} catch (Throwable $e) {
    echo json_encode([
        'success' => false,
        'error'   => safeErrorMessage($e)
    ]);
}
exit;
