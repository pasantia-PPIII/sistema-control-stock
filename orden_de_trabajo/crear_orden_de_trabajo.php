<?php
/**
 * orden_de_trabajo/crear_orden_de_trabajo.php
 * Endpoint AJAX para registrar una nueva orden de trabajo con:
 *  - comisión de operarios (operarios[]),
 *  - movimiento de entrega y movimiento de devolución (ambos opcionales),
 *  - adjunto PDF opcional.
 * Solo Administrador y Pañolero. Responde exclusivamente en formato JSON.
 */

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'error'   => 'Método no permitido. Se requiere petición POST.'
    ]);
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
require_once __DIR__ . '/pdf_upload.php';

try {
    $datos = datosOrdenDesdePost();

    if ($datos['numero_ot'] === '') {
        throw new Exception('El Número de Orden de Trabajo (OT) es obligatorio.');
    }

    $nombrePdf = guardarPdfOrden();
    if ($nombrePdf !== null) {
        $datos['archivo_pdf'] = $nombrePdf;
    }

    $idEgreso = !empty($_POST['id_mov_egreso']) ? (int)$_POST['id_mov_egreso'] : null;
    $idDevolucion = !empty($_POST['id_mov_devolucion']) ? (int)$_POST['id_mov_devolucion'] : null;

    $odtModel = new OrdenDeTrabajo();
    $orden = $odtModel->create($datos, operariosDesdePost(), $idEgreso, $idDevolucion);

    echo json_encode([
        'success'   => true,
        'message'   => "Orden de Trabajo '{$datos['numero_ot']}' registrada exitosamente.",
        'numero_ot' => $datos['numero_ot'],
        'id_odt'    => (int)$orden['id_odt']
    ]);
} catch (Throwable $e) {
    echo json_encode([
        'success' => false,
        'error'   => safeErrorMessage($e)
    ]);
}
exit;
