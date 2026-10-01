<?php
/**
 * rubro/editar_rubro.php
 * Endpoint AJAX para la edición de un rubro existente.
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
    $nombre = isset($input['nombre']) ? trim((string)$input['nombre']) : '';
    $descripcion = isset($input['descripcion']) ? trim((string)$input['descripcion']) : null;

    if ($id_rubro <= 0) {
        throw new Exception('El identificador del rubro es inválido.');
    }

    if ($nombre === '') {
        throw new Exception('El nombre del rubro no puede estar vacío.');
    }

    $rubroModel = new Rubro();

    // Validar existencia previa
    $rubroActual = $rubroModel->getById($id_rubro);
    if (!$rubroActual) {
        throw new Exception("El rubro con ID {$id_rubro} no existe.");
    }

    // Validar nombre duplicado (excluyendo el rubro actual)
    if ($rubroModel->existeNombre($nombre, $id_rubro)) {
        throw new Exception("Ya existe otro rubro con el nombre '{$nombre}'.");
    }

    $datos = [
        'nombre'      => $nombre,
        'descripcion' => ($descripcion !== '' && $descripcion !== null) ? $descripcion : null
    ];

    $resultado = $rubroModel->update($id_rubro, $datos);

    if ($resultado) {
        echo json_encode([
            'success'  => true,
            'message'  => "Rubro '{$nombre}' actualizado exitosamente.",
            'id_rubro' => $id_rubro
        ]);
    } else {
        throw new Exception('No se pudo actualizar el rubro en la base de datos.');
    }
} catch (Throwable $e) {
    echo json_encode([
        'success' => false,
        'error'   => $e->getMessage()
    ]);
}
exit;
