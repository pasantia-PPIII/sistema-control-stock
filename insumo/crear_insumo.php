<?php
/**
 * insumo/crear_insumo.php
 * Endpoint AJAX para la creación de un nuevo insumo.
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
    // Soporte para entrada estándar $_POST o JSON en el cuerpo
    $input = $_POST;
    if (empty($input)) {
        $raw = file_get_contents('php://input');
        $json = json_decode($raw, true);
        if (is_array($json)) {
            $input = $json;
        }
    }

    $codigo = isset($input['codigo']) ? trim((string)$input['codigo']) : '';
    $nombre = isset($input['nombre']) ? trim((string)$input['nombre']) : '';

    if ($codigo === '') {
        throw new Exception('El código del insumo es obligatorio.');
    }

    if ($nombre === '') {
        throw new Exception('El nombre o descripción del insumo es obligatorio.');
    }

    $insumoModel = new Insumo();

    // Validar si el código ya existe
    $existente = $insumoModel->getByCodigo($codigo);
    if ($existente) {
        throw new Exception("Ya existe un insumo activo con el código '{$codigo}'.");
    }

    $id_rubro = !empty($input['id_rubro']) ? (int)$input['id_rubro'] : (!empty($input['rubro_id']) ? (int)$input['rubro_id'] : null);
    $id_tipo = !empty($input['id_tipo']) ? (int)$input['id_tipo'] : null;
    $id_unidad_medida = !empty($input['id_unidad_medida']) ? (int)$input['id_unidad_medida'] : null;
    $id_ubicacion = !empty($input['id_ubicacion']) ? (int)$input['id_ubicacion'] : null;
    $stock_minimo = isset($input['stock_minimo']) && $input['stock_minimo'] !== '' ? (float)$input['stock_minimo'] : 0;
    $es_perecedero = !empty($input['es_perecedero']) && ($input['es_perecedero'] === '1' || $input['es_perecedero'] === true || $input['es_perecedero'] === 'true');
    $fecha_vencimiento = (!empty($input['fecha_vencimiento']) && $es_perecedero) ? trim((string)$input['fecha_vencimiento']) : null;

    $datosInsumo = [
        'codigo'            => $codigo,
        'nombre'            => $nombre,
        'id_rubro'          => $id_rubro,
        'id_tipo'           => $id_tipo,
        'id_unidad_medida'  => $id_unidad_medida,
        'id_ubicacion'      => $id_ubicacion,
        'stock_minimo'      => $stock_minimo,
        'es_perecedero'     => $es_perecedero,
        'fecha_vencimiento' => $fecha_vencimiento
    ];

    // Limpiar claves con valor null si no están presentes
    $datosLimpios = array_filter($datosInsumo, function ($val) {
        return $val !== null;
    });

    $resultado = $insumoModel->create($datosLimpios);

    if ($resultado) {
        echo json_encode([
            'success' => true,
            'message' => 'Insumo creado correctamente.',
            'codigo'  => $codigo
        ]);
    } else {
        throw new Exception('No se pudo registrar el insumo en la base de datos.');
    }
} catch (Throwable $e) {
    echo json_encode([
        'success' => false,
        'error'   => safeErrorMessage($e)
    ]);
}
exit;
