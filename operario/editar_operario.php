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

    $dniOriginal = isset($input['dni_original']) ? trim((string)$input['dni_original']) : (isset($input['dni']) ? trim((string)$input['dni']) : '');
    $apellido = isset($input['apellido']) ? trim((string)$input['apellido']) : '';
    $nombre = isset($input['nombre']) ? trim((string)$input['nombre']) : '';
    $legajo = isset($input['legajo']) ? trim((string)$input['legajo']) : (isset($input['codigo']) ? trim((string)$input['codigo']) : '');
    $telefono = isset($input['telefono']) ? trim((string)$input['telefono']) : '';
    $id_rubro = !empty($input['id_rubro']) ? (int)$input['id_rubro'] : null;
    $observaciones = isset($input['observaciones']) ? trim((string)$input['observaciones']) : null;

    if ($dniOriginal === '') {
        throw new Exception('El DNI original del operario es obligatorio.');
    }

    if ($apellido === '') {
        throw new Exception('El apellido del operario no puede estar vacío.');
    }

    if ($nombre === '') {
        throw new Exception('El nombre del operario no puede estar vacío.');
    }

    $operarioModel = new Operario();

    // Validar existencia
    $operarioActual = $operarioModel->getByDni($dniOriginal);
    if (!$operarioActual) {
        throw new Exception("No se encontró al operario con DNI '{$dniOriginal}'.");
    }

    $datosActualizar = [
        'apellido'      => $apellido,
        'nombre'        => $nombre,
        'legajo'        => $legajo !== '' ? $legajo : null,
        'telefono'      => $telefono !== '' ? $telefono : null,
        'id_rubro'      => $id_rubro,
        'observaciones' => $observaciones !== '' ? $observaciones : null
    ];

    $resultado = $operarioModel->update($dniOriginal, $datosActualizar);

    if ($resultado) {
        echo json_encode([
            'success' => true,
            'message' => 'Operario actualizado exitosamente.',
            'dni'     => $dniOriginal
        ]);
    } else {
        throw new Exception('No se pudo actualizar el operario.');
    }
} catch (Throwable $e) {
    echo json_encode([
        'success' => false,
        'error'   => $e->getMessage()
    ]);
}
exit;
