<?php
/**
 * orden_de_trabajo/deshabilitar_orden_de_trabajo.php
 * Endpoint AJAX para anular (soft-delete) una orden de trabajo. No borra nada: los movimientos
 * vinculados y la comisión se conservan. Solo Administrador y Pañolero.
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

require_once __DIR__ . '/../clases/orden_de_trabajo.php';

try {
    $idOdt = isset($_POST['id_odt']) ? (int)$_POST['id_odt'] : 0;
    if ($idOdt <= 0) {
        throw new Exception('El identificador de la Orden de Trabajo es obligatorio.');
    }

    $odtModel = new OrdenDeTrabajo();
    $orden = $odtModel->getById($idOdt);
    if (!$orden) {
        throw new Exception("La Orden de Trabajo #{$idOdt} no existe o ya fue anulada.");
    }

    $odtModel->anular($idOdt);

    echo json_encode([
        'success' => true,
        'message' => "La Orden de Trabajo '{$orden['numero_ot']}' fue deshabilitada correctamente.",
        'id_odt'  => $idOdt
    ]);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'error' => safeErrorMessage($e)]);
}
exit;
