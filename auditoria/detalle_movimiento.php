<?php
// auditoria/detalle_movimiento.php
// Endpoint JSON con el detalle de un movimiento para el modal de la pantalla de Auditoría (solo Administrador).
// config.php inicia la sesión con la cookie endurecida.
require_once __DIR__ . '/../config/config.php';

// Desactivar salida de errores en HTML para no corromper el JSON
ini_set('display_errors', 0);
header('Content-Type: application/json; charset=utf-8');

if (!isAuthenticated()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Sesión no iniciada. Inicie sesión nuevamente.']);
    exit;
}
if (!canViewAudit()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'No tiene permisos para ver la auditoría.']);
    exit;
}

require_once __DIR__ . '/../clases/auditoria.php';

$id_movimiento = (int) ($_GET['id'] ?? 0);

if ($id_movimiento <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'ID de movimiento inválido']);
    exit;
}

try {
    echo json_encode(['success' => true, 'detalles' => Auditoria::detalleMovimiento($id_movimiento)]);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'error' => safeErrorMessage($e)]);
}
exit;
