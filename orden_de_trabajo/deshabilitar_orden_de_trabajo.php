<?php
/**
 * orden_de_trabajo/deshabilitar_orden_de_trabajo.php
 * Endpoint AJAX para anular (soft-delete) o reactivar una orden de trabajo. No borra nada: los
 * movimientos vinculados y la comisión se conservan. Solo Administrador y Pañolero.
 * Parámetros POST: id_odt, accion ('deshabilitar' por defecto | 'habilitar').
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

    $accion = isset($_POST['accion']) ? trim((string)$_POST['accion']) : 'deshabilitar';
    if (!in_array($accion, ['deshabilitar', 'habilitar'], true)) {
        throw new Exception('Acción no válida.');
    }

    $odtModel = new OrdenDeTrabajo();
    $orden = $odtModel->getEstadoById($idOdt);
    if (!$orden) {
        throw new Exception("La Orden de Trabajo #{$idOdt} no existe.");
    }

    if ($accion === 'habilitar') {
        if ($orden['activo']) {
            throw new Exception("La Orden de Trabajo '{$orden['numero_ot']}' ya se encuentra habilitada.");
        }
        $odtModel->reactivar($idOdt);
        $mensaje = "La Orden de Trabajo '{$orden['numero_ot']}' fue habilitada correctamente.";
    } else {
        if (!$orden['activo']) {
            throw new Exception("La Orden de Trabajo '{$orden['numero_ot']}' ya se encuentra deshabilitada.");
        }
        $odtModel->anular($idOdt);
        $mensaje = "La Orden de Trabajo '{$orden['numero_ot']}' fue deshabilitada correctamente.";
    }

    echo json_encode([
        'success' => true,
        'message' => $mensaje,
        'id_odt'  => $idOdt,
        'activo'  => ($accion === 'habilitar')
    ]);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'error' => safeErrorMessage($e)]);
}
exit;
