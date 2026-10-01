<?php
/**
 * movimiento/crear_devolucion.php
 * Endpoint AJAX para registrar una Devolución de herramientas y materiales de un operario.
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

if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}

require_once __DIR__ . '/../clases/movimiento.php';
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

    $idOperario = isset($input['id_operario']) ? (int)$input['id_operario'] : 0;
    $fecha = isset($input['fecha']) && !empty($input['fecha']) ? trim($input['fecha']) : date('Y-m-d');
    $hora = isset($input['hora']) && !empty($input['hora']) ? trim($input['hora']) : date('H:i:s');
    $observaciones = isset($input['observaciones']) ? trim((string)$input['observaciones']) : null;
    $idOdt = !empty($input['id_odt']) ? (int)$input['id_odt'] : null;

    // Validación de operario
    if ($idOperario <= 0) {
        throw new Exception('Debe seleccionar el operario que realiza la devolución.');
    }

    $operarioModel = new Operario();
    $operario = $operarioModel->getById($idOperario);
    if (!$operario) {
        throw new Exception("El operario seleccionado no existe o no se encuentra activo.");
    }

    // Usuario que registra el movimiento (de la sesión o primer usuario administrador)
    $idUsuario = isset($_SESSION['usuario_id']) ? (int)$_SESSION['usuario_id'] : 1;
    if ($idUsuario <= 0) {
        $idUsuario = 1;
    }

    // Procesar lista de ítems a devolver
    $items = [];
    if (isset($input['items'])) {
        if (is_string($input['items'])) {
            $itemsDecoded = json_decode($input['items'], true);
            $items = is_array($itemsDecoded) ? $itemsDecoded : [];
        } elseif (is_array($input['items'])) {
            $items = $input['items'];
        }
    }

    if (empty($items)) {
        throw new Exception('Debe agregar al menos un ítem a la devolución.');
    }

    $detalles = [];
    foreach ($items as $item) {
        $idInsumo = isset($item['id_insumo']) ? (int)$item['id_insumo'] : 0;
        $cantidad = isset($item['cantidad']) ? (float)$item['cantidad'] : 0;
        // Estado con el que ingresa la herramienta (1 = Disponible, 3 = En Reparación, 4 = Baja)
        $idEstadoHerramienta = !empty($item['id_estado_herramienta']) ? (int)$item['id_estado_herramienta'] : 1;

        if ($idInsumo <= 0) {
            throw new Exception('Se detectó un ítem con identificación de insumo inválida.');
        }

        if ($cantidad <= 0) {
            throw new Exception("La cantidad a devolver debe ser un número mayor a cero.");
        }

        $detalles[] = [
            'id_insumo'             => $idInsumo,
            'cantidad'              => $cantidad,
            'id_estado_herramienta' => $idEstadoHerramienta
        ];
    }

    $cabecera = [
        'id_tipo_mov'   => 2, // 2 = Devolución
        'id_usuario'    => $idUsuario,
        'id_operario'   => $idOperario,
        'id_odt'        => $idOdt,
        'fecha'         => $fecha,
        'hora'          => $hora,
        'observaciones' => $observaciones
    ];

    $movimientoModel = new Movimiento();
    $idMovimiento = $movimientoModel->create($cabecera, $detalles);

    if ($idMovimiento > 0) {
        $nombreOp = htmlspecialchars($operario['nombre'] . ' ' . $operario['apellido']);
        echo json_encode([
            'success'       => true,
            'message'       => "Devolución #{$idMovimiento} registrada exitosamente para {$nombreOp}.",
            'id_movimiento' => $idMovimiento
        ]);
    } else {
        throw new Exception('No se pudo completar el registro de la devolución.');
    }
} catch (Throwable $e) {
    echo json_encode([
        'success' => false,
        'error'   => $e->getMessage()
    ]);
}
exit;
