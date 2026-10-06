<?php
/**
 * orden_de_trabajo/editar_orden_de_trabajo.php
 * Endpoint AJAX para editar una orden de trabajo: datos, comisión de operarios,
 * movimiento de entrega / devolución (opcionales) y PDF (si se envía uno nuevo, reemplaza al anterior).
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

require_once __DIR__ . '/../clases/orden_de_trabajo.php';
require_once __DIR__ . '/pdf_upload.php';

try {
    $idOdt = isset($_POST['id_odt']) ? (int)$_POST['id_odt'] : 0;
    if ($idOdt <= 0) {
        throw new Exception('El identificador de la Orden de Trabajo es obligatorio.');
    }

    $odtModel = new OrdenDeTrabajo();
    if (!$odtModel->getById($idOdt)) {
        throw new Exception("La Orden de Trabajo #{$idOdt} no existe o fue anulada.");
    }

    $datos = datosOrdenDesdePost();
    if ($datos['numero_ot'] === '') {
        throw new Exception('El Número de Orden de Trabajo (OT) es obligatorio.');
    }

    $nombrePdf = guardarPdfOrden();
    if ($nombrePdf !== null) {
        $datos['archivo_pdf'] = $nombrePdf;
    }

    // Los movimientos vienen siempre en el formulario: vacío = sin movimiento vinculado
    $vinculos = [
        'id_mov_egreso'     => !empty($_POST['id_mov_egreso']) ? (int)$_POST['id_mov_egreso'] : null,
        'id_mov_devolucion' => !empty($_POST['id_mov_devolucion']) ? (int)$_POST['id_mov_devolucion'] : null,
    ];

    $odtModel->update($idOdt, $datos, operariosDesdePost(), $vinculos);

    echo json_encode([
        'success' => true,
        'message' => "Orden de Trabajo '{$datos['numero_ot']}' actualizada exitosamente.",
        'id_odt'  => $idOdt
    ]);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'error' => safeErrorMessage($e)]);
}
exit;
