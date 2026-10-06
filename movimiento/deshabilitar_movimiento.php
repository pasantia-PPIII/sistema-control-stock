<?php
/**
 * movimiento/deshabilitar_movimiento.php
 * Endpoint AJAX para anular un movimiento (soft-delete). Revierte su efecto sobre el stock
 * y el estado de las herramientas (Movimiento::anular). Solo Administrador y Pañolero.
 * Responde exclusivamente en formato JSON.
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

    $movimientoModel = new Movimiento();
    $movimientoModel->anular($idMovimiento);

    echo json_encode([
        'success'       => true,
        'message'       => "El movimiento #{$idMovimiento} fue anulado y su efecto sobre el stock quedó revertido.",
        'id_movimiento' => $idMovimiento
    ]);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'error' => safeErrorMessage($e)]);
}
exit;
