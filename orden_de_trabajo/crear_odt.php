<?php
/**
 * orden_de_trabajo/crear_odt.php
 * Endpoint AJAX para registrar una nueva orden de trabajo con adjunto PDF opcional.
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

require_once __DIR__ . '/../clases/orden_de_trabajo.php';

try {
    $numeroOt = isset($_POST['numero_ot']) ? trim((string)$_POST['numero_ot']) : '';
    $pa = isset($_POST['pa']) ? trim((string)$_POST['pa']) : null;
    $trabajo = isset($_POST['trabajo_a_realizar']) ? trim((string)$_POST['trabajo_a_realizar']) : '';
    $idLocalidad = !empty($_POST['id_localidad']) ? (int)$_POST['id_localidad'] : null;
    $idJurisdiccion = !empty($_POST['id_jurisdiccion']) ? (int)$_POST['id_jurisdiccion'] : null;
    $fechaInicio = !empty($_POST['fecha_inicio']) ? trim((string)$_POST['fecha_inicio']) : null;
    $fechaFinal = !empty($_POST['fecha_final']) ? trim((string)$_POST['fecha_final']) : null;
    $horaInicio = !empty($_POST['hora_inicio']) ? trim((string)$_POST['hora_inicio']) : null;
    $horaFinal = !empty($_POST['hora_final']) ? trim((string)$_POST['hora_final']) : null;
    $estado = !empty($_POST['estado']) ? trim((string)$_POST['estado']) : 'Pendiente';
    $observaciones = isset($_POST['observaciones']) ? trim((string)$_POST['observaciones']) : null;

    if ($numeroOt === '') {
        throw new Exception('El Número de Orden de Trabajo (OT) es obligatorio.');
    }

    $nombreArchivoPdf = null;

    // Procesamiento del archivo PDF adjunto
    if (isset($_FILES['archivo_pdf']) && $_FILES['archivo_pdf']['error'] !== UPLOAD_ERR_NO_FILE) {
        $fileError = $_FILES['archivo_pdf']['error'];
        if ($fileError !== UPLOAD_ERR_OK) {
            throw new Exception("Error al cargar el archivo PDF adjunto (código de error: {$fileError}).");
        }

        $fileName = $_FILES['archivo_pdf']['name'];
        $fileTmpPath = $_FILES['archivo_pdf']['tmp_name'];
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        if ($fileExtension !== 'pdf') {
            throw new Exception('El archivo adjunto debe ser estrictamente en formato PDF.');
        }

        $uploadDir = __DIR__ . '/../uploads/pa_pdfs/';
        if (!is_dir($uploadDir)) {
            if (!mkdir($uploadDir, 0777, true)) {
                throw new Exception('No se pudo inicializar el directorio de almacenamiento de PDFs.');
            }
        }

        // Nombre de archivo seguro y único
        $nombreSeguro = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', pathinfo($fileName, PATHINFO_FILENAME));
        $nombreArchivoPdf = 'ot_' . time() . '_' . substr(bin2hex(random_bytes(4)), 0, 8) . '_' . substr($nombreSeguro, 0, 30) . '.pdf';
        $destPath = $uploadDir . $nombreArchivoPdf;

        if (!move_uploaded_file($fileTmpPath, $destPath)) {
            throw new Exception('No se pudo guardar el archivo PDF adjunto en el servidor.');
        }
    }

    $odtModel = new OrdenDeTrabajo();

    $datos = [
        'numero_ot'          => $numeroOt,
        'pa'                 => ($pa !== '') ? $pa : null,
        'trabajo_a_realizar' => ($trabajo !== '') ? $trabajo : null,
        'id_localidad'       => $idLocalidad,
        'id_jurisdiccion'    => $idJurisdiccion,
        'fecha_inicio'       => $fechaInicio,
        'fecha_final'        => $fechaFinal,
        'hora_inicio'        => $horaInicio,
        'hora_final'         => $horaFinal,
        'estado'             => $estado,
        'archivo_pdf'        => $nombreArchivoPdf,
        'observaciones'      => ($observaciones !== '') ? $observaciones : null
    ];

    $resultado = $odtModel->crear($datos);

    if ($resultado) {
        echo json_encode([
            'success'   => true,
            'message'   => "Orden de Trabajo '{$numeroOt}' registrada exitosamente.",
            'numero_ot' => $numeroOt
        ]);
    } else {
        throw new Exception('No se pudo registrar la Orden de Trabajo en la base de datos.');
    }
} catch (Throwable $e) {
    echo json_encode([
        'success' => false,
        'error'   => $e->getMessage()
    ]);
}
exit;
