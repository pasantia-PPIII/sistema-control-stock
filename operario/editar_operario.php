<?php
/**
 * operario/editar_operario.php
 * Endpoint AJAX para la edición de un operario existente.
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
    $apellido = isset($input['apellido']) ? trim((string)$input['apellido']) : '';
    $nombre = isset($input['nombre']) ? trim((string)$input['nombre']) : '';
    $legajo = isset($input['legajo']) ? trim((string)$input['legajo']) : '';
    $id_rubro = !empty($input['id_rubro']) ? (int)$input['id_rubro'] : null;

    if ($idOperario <= 0) {
        throw new Exception('El identificador del operario es obligatorio.');
    }

    if ($apellido === '') {
        throw new Exception('El apellido del operario no puede estar vacío.');
    }

    if ($nombre === '') {
        throw new Exception('El nombre del operario no puede estar vacío.');
    }

    $operarioModel = new Operario();

    // Validar existencia
    $operarioActual = $operarioModel->getById($idOperario);
    if (!$operarioActual) {
        throw new Exception("No se encontró al operario indicado.");
    }

    $datosActualizar = [
        'apellido'      => $apellido,
        'nombre'        => $nombre,
        'legajo'        => $legajo !== '' ? $legajo : null,
        'id_rubro'      => $id_rubro
    ];

    $resultado = $operarioModel->update($idOperario, $datosActualizar);

    if ($resultado) {
        echo json_encode([
            'success' => true,
            'message' => 'Operario actualizado exitosamente.',
            'id_operario' => $idOperario
        ]);
    } else {
        throw new Exception('No se pudo actualizar el operario.');
    }
} catch (Throwable $e) {
    echo json_encode([
        'success' => false,
        'error'   => safeErrorMessage($e)
    ]);
}
exit;
