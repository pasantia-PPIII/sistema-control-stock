<?php
/**
 * movimiento/editar_movimiento.php
 * Endpoint AJAX para vincular un movimiento (entrega o devolución) a una Orden de Trabajo, cambiarlo de
 * orden o dejarlo como "Sin OT / Uso general" (RF-27). Parámetros POST: id_movimiento, id_odt (vacío = sin orden).
 * Solo se modifica ese vínculo: la fecha, el tipo y el detalle no se editan (auditoría).
 * Solo Administrador y Pañolero. Responde exclusivamente en formato JSON.
 */

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Método no permitido. Se requiere petición POST.']);
    exit;
}

require_once __DIR__ . '/../config/config.php';
if (!isAuthenticated()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Sesión no iniciada. Inicie sesión nuevamente.']);
    exit;
}
requireRole(['admin', 'panolero']);

require_once __DIR__ . '/../clases/movimiento.php';

try {
    $idMovimiento = isset($_POST['id_movimiento']) ? (int)$_POST['id_movimiento'] : 0;
    if ($idMovimiento <= 0) {
        throw new Exception('El identificador del movimiento es obligatorio.');
    }
    $idOdt = !empty($_POST['id_odt']) ? (int)$_POST['id_odt'] : null;

    $movimientoModel = new Movimiento();
    $movimientoModel->update($idMovimiento, ['id_odt' => $idOdt]);

    echo json_encode([
        'success'       => true,
        'message'       => $idOdt === null
            ? "El movimiento #{$idMovimiento} quedó sin Orden de Trabajo (uso general)."
            : "El movimiento #{$idMovimiento} quedó vinculado a la Orden de Trabajo seleccionada.",
        'id_movimiento' => $idMovimiento,
        'id_odt'        => $idOdt
    ]);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'error' => safeErrorMessage($e)]);
}
exit;
