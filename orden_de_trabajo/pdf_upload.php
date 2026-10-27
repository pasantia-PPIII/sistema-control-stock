<?php
/**
 * orden_de_trabajo/pdf_upload.php
 * Utilidades para carga y validación de PDF oficial y lectura de POST.
 */

function guardarPdfOrden() {
    if (!isset($_FILES['archivo_pdf']) || $_FILES['archivo_pdf']['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    $archivo = $_FILES['archivo_pdf'];
    if ($archivo['error'] !== UPLOAD_ERR_OK) {
        throw new Exception("Error al cargar el archivo PDF adjunto (código: {$archivo['error']}).");
    }
    if ($archivo['size'] > MAX_FILE_SIZE) {
        throw new Exception('El archivo PDF supera el tamaño máximo permitido (10 MB).');
    }
    if (strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION)) !== 'pdf') {
        throw new Exception('El archivo debe estar estrictamente en formato PDF.');
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($archivo['tmp_name']);
    if ($mime !== 'application/pdf') {
        throw new Exception('El contenido del archivo no es un PDF válido.');
    }

    $directorio = __DIR__ . '/../uploads/pa_pdfs/';
    if (!is_dir($directorio) && !mkdir($directorio, 0755, true)) {
        throw new Exception('No se pudo inicializar la carpeta de almacenamiento de PDFs.');
    }

    $base = preg_replace('/[^a-zA-Z0-9_\-]/', '_', pathinfo($archivo['name'], PATHINFO_FILENAME));
    $nombre = 'ot_' . time() . '_' . bin2hex(random_bytes(4)) . '_' . substr($base, 0, 30) . '.pdf';

    if (!move_uploaded_file($archivo['tmp_name'], $directorio . $nombre)) {
        throw new Exception('No se pudo guardar el archivo PDF en el servidor.');
    }
    return $nombre;
}

function datosOrdenDesdePost() {
    $texto = function ($clave) {
        return isset($_POST[$clave]) ? trim((string)$_POST[$clave]) : '';
    };
    return [
        'numero_ot'          => $texto('numero_ot'),
        'pa'                 => $texto('pa'),
        'trabajo_a_realizar' => $texto('trabajo_a_realizar'),
        'id_localidad'       => !empty($_POST['id_localidad']) ? (int)$_POST['id_localidad'] : null,
        'id_jurisdiccion'    => !empty($_POST['id_jurisdiccion']) ? (int)$_POST['id_jurisdiccion'] : null,
        'fecha_inicio'       => $texto('fecha_inicio') ?: null,
        'fecha_final'        => $texto('fecha_final') ?: null,
        'estado'             => $texto('estado') ?: 'Pendiente',
        'observaciones'      => $texto('observaciones') ?: null,
    ];
}