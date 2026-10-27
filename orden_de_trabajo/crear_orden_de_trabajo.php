<?php
/**
 * orden_de_trabajo/crear_orden_de_trabajo.php
 * Endpoint AJAX para dar de alta una orden mediante la subida de su PDF oficial.
 */

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Método no permitido.']);
    exit;
}

require_once __DIR__ . '/../config/config.php';
if (!isAuthenticated()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Sesión no iniciada.']);
    exit;
}
requireRole(['admin', 'panolero']);

require_once __DIR__ . '/../clases/orden_de_trabajo.php';
require_once __DIR__ . '/pdf_upload.php';

try {
    $datos = datosOrdenDesdePost();

    if (empty($datos['numero_ot'])) {
        throw new Exception('El Número de Orden de Trabajo (OT) es obligatorio.');
    }
    if (empty($datos['trabajo_a_realizar'])) {
        throw new Exception('El detalle o motivo del trabajo es obligatorio.');
    }

    $nombrePdf = guardarPdfOrden();
    if ($nombrePdf !== null) {
        $datos['archivo_pdf'] = $nombrePdf;
    }

    $odtModel = new OrdenDeTrabajo();
    $orden = $odtModel->create($datos, [], null, null);

    echo json_encode([
        'success' => true,
        'message' => "Solicitud de Orden '{$datos['numero_ot']}' cargada exitosamente.",
        'numero_ot' => $datos['numero_ot'],
        'id_odt' => (int) $orden['id_odt']
    ]);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'error' => safeErrorMessage($e)]);
}
exit;