<?php
/**
 * orden_de_trabajo/cambiar_estado.php
 * Endpoint AJAX para actualizar el estado de una orden de trabajo.
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

require_once __DIR__ . '/../clases/orden_de_trabajo.php';

try {
    $input = $_POST;
    if (empty($input)) {
        $raw = file_get_contents('php://input');
        $json = json_decode($raw, true);
        if (is_array($json)) {
            $input = $json;
        }
    }

    $idOdt = isset($input['id_odt']) ? (int)$input['id_odt'] : (isset($input['id']) ? (int)$input['id'] : 0);
    $nuevoEstado = isset($input['estado']) ? trim((string)$input['estado']) : '';

    if ($idOdt <= 0) {
        throw new Exception('El identificador de la Orden de Trabajo es inválido.');
    }

    if ($nuevoEstado === '') {
        throw new Exception('Debe especificar un estado válido.');
    }

    $odtModel = new OrdenDeTrabajo();

    // Comprobar existencia
    $ordenActual = $odtModel->obtenerPorId($idOdt);
    if (!$ordenActual) {
        throw new Exception("La Orden de Trabajo #{$idOdt} no existe.");
    }

    $resultado = $odtModel->actualizarEstado($idOdt, $nuevoEstado);

    if ($resultado) {
        echo json_encode([
            'success'      => true,
            'message'      => "El estado de la Orden de Trabajo #{$idOdt} se actualizó a '{$nuevoEstado}'.",
            'id_odt'       => $idOdt,
            'nuevo_estado' => $nuevoEstado
        ]);
    } else {
        throw new Exception('No se pudo actualizar el estado de la Orden de Trabajo en la base de datos.');
    }
} catch (Throwable $e) {
    echo json_encode([
        'success' => false,
        'error'   => $e->getMessage()
    ]);
}
exit;
