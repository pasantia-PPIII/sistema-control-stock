<?php
/**
 * insumo/editar_insumo.php
 * Endpoint AJAX para la actualización de un insumo existente.
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
require_once __DIR__ . '/../clases/herramienta.php';

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
        throw new Exception('El código del insumo es obligatorio para su actualización.');
    }

    $insumoModel = new Insumo();

    // Validar que el insumo exista
    $insumoActual = $insumoModel->getByCodigo($codigo);
    if (!$insumoActual) {
        throw new Exception("No se encontró el insumo con código '{$codigo}'.");
    }

    $datosActualizar = [];

    if (isset($input['nombre'])) {
        $nombre = trim((string)$input['nombre']);
        if ($nombre === '') {
            throw new Exception('El nombre del insumo no puede estar vacío.');
        }
        $datosActualizar['nombre'] = $nombre;
    }

    if (isset($input['descripcion'])) {
        $datosActualizar['descripcion'] = trim((string)$input['descripcion']);
    }

    if (isset($input['id_rubro'])) {
        $datosActualizar['id_rubro'] = !empty($input['id_rubro']) ? (int)$input['id_rubro'] : null;
    } elseif (isset($input['rubro_id'])) {
        $datosActualizar['id_rubro'] = !empty($input['rubro_id']) ? (int)$input['rubro_id'] : null;
    }

    if (isset($input['id_tipo'])) {
        $datosActualizar['id_tipo'] = !empty($input['id_tipo']) ? (int)$input['id_tipo'] : null;
    }

    if (isset($input['id_unidad_medida'])) {
        $datosActualizar['id_unidad_medida'] = !empty($input['id_unidad_medida']) ? (int)$input['id_unidad_medida'] : null;
    }

    if (isset($input['id_ubicacion'])) {
        $datosActualizar['id_ubicacion'] = !empty($input['id_ubicacion']) ? (int)$input['id_ubicacion'] : null;
    }

    if (isset($input['stock_minimo'])) {
        // Sin castear: Insumo::update() valida que sea un número no negativo y avisa si no lo es
        $datosActualizar['stock_minimo'] = trim((string)$input['stock_minimo']) !== '' ? $input['stock_minimo'] : 0;
    }

    // Stock real del material (carga de más stock o corrección). Las herramientas no lo editan a mano:
    // su stock (1 en el pañol / 0 fuera) lo manejan las entregas y devoluciones.
    $esHerramienta = (($insumoActual['nombre_tipo'] ?? '') === 'Herramienta');
    if (!$esHerramienta && isset($input['stock_actual']) && $input['stock_actual'] !== '') {
        if (!is_numeric($input['stock_actual']) || (float)$input['stock_actual'] < 0) {
            throw new Exception('El stock debe ser un número igual o mayor a cero.');
        }
        $datosActualizar['stock_actual'] = (float)$input['stock_actual'];
    }

    if (isset($input['es_perecedero'])) {
        $datosActualizar['es_perecedero'] = in_array((string) $input['es_perecedero'], ['1', 'true', 'on'], true);
    }

    if (array_key_exists('fecha_vencimiento', $input)) {
        $datosActualizar['fecha_vencimiento'] = (!empty($datosActualizar['es_perecedero']) && trim((string) $input['fecha_vencimiento']) !== '')
            ? trim((string) $input['fecha_vencimiento'])
            : null;
    }

    if (empty($datosActualizar)) {
        throw new Exception('No se enviaron datos para actualizar.');
    }

    $resultado = $insumoModel->update($codigo, $datosActualizar);

    if ($resultado) {
        echo json_encode([
            'success' => true,
            'message' => 'Insumo actualizado correctamente.',
            'codigo'  => $codigo
        ]);
    } else {
        throw new Exception('No se pudo actualizar el insumo.');
    }
} catch (Throwable $e) {
    echo json_encode([
        'success' => false,
        'error'   => safeErrorMessage($e)
    ]);
}
exit;
