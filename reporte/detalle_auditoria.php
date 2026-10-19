<?php
// reporte/detalle_auditoria.php
session_start();

// Desactivar salida de errores en HTML para no corromper el JSON
ini_set('display_errors', 0);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../clases/reportes.php';

$id_movimiento = (int) ($_GET['id'] ?? 0);

if ($id_movimiento <= 0) {
    echo json_encode(['success' => false, 'error' => 'ID de movimiento inválido']);
    exit;
}

try {
    $detalles = obtenerDetalleAuditoriaPorId($id_movimiento);
    echo json_encode(['success' => true, 'detalles' => $detalles]);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
exit;