<?php
/**
 * orden_de_trabajo/pdf_upload.php
 * Utilidades compartidas por crear_orden_de_trabajo.php y editar_orden_de_trabajo.php
 * para procesar el PDF adjunto y los datos comunes del formulario.
 */

/**
 * Guarda el PDF subido en uploads/pa_pdfs/ y devuelve el nombre generado,
 * o null si no se adjuntó ningún archivo. Valida extensión, tamaño y contenido real (MIME).
 */
function guardarPdfOrden() {
    if (!isset($_FILES['archivo_pdf']) || $_FILES['archivo_pdf']['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    $archivo = $_FILES['archivo_pdf'];
    if ($archivo['error'] !== UPLOAD_ERR_OK) {
        throw new Exception("Error al cargar el archivo PDF adjunto (código de error: {$archivo['error']}).");
    }
    if ($archivo['size'] > MAX_FILE_SIZE) {
        throw new Exception('El archivo PDF supera el tamaño máximo permitido (10 MB).');
    }
    if (strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION)) !== 'pdf') {
        throw new Exception('El archivo adjunto debe ser estrictamente en formato PDF.');
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($archivo['tmp_name']);
    if ($mime !== 'application/pdf') {
        throw new Exception('El contenido del archivo adjunto no es un PDF válido.');
    }

    $directorio = __DIR__ . '/../uploads/pa_pdfs/';
    if (!is_dir($directorio) && !mkdir($directorio, 0755, true)) {
        throw new Exception('No se pudo inicializar el directorio de almacenamiento de PDFs.');
    }

    // Nombre de archivo seguro y único
    $base = preg_replace('/[^a-zA-Z0-9_\-]/', '_', pathinfo($archivo['name'], PATHINFO_FILENAME));
    $nombre = 'ot_' . time() . '_' . bin2hex(random_bytes(4)) . '_' . substr($base, 0, 30) . '.pdf';

    if (!move_uploaded_file($archivo['tmp_name'], $directorio . $nombre)) {
        throw new Exception('No se pudo guardar el archivo PDF adjunto en el servidor.');
    }
    return $nombre;
}

/**
 * Lee del POST los campos comunes de la orden de trabajo (sin id ni PDF).
 */
function datosOrdenDesdePost() {
    $texto = function ($clave) {
        return isset($_POST[$clave]) ? trim((string)$_POST[$clave]) : '';
    };
    return [
        'numero_ot'          => $texto('numero_ot'),
        'pa'                 => $texto('pa'),
        'trabajo_a_realizar' => $texto('trabajo_a_realizar'),
        'id_localidad'       => $_POST['id_localidad'] ?? null,
        'id_jurisdiccion'    => $_POST['id_jurisdiccion'] ?? null,
        'fecha_inicio'       => $texto('fecha_inicio'),
        'fecha_final'        => $texto('fecha_final'),
        'hora_inicio'        => $texto('hora_inicio'),
        'hora_final'         => $texto('hora_final'),
        'estado'             => $texto('estado'),
        'observaciones'      => $texto('observaciones'),
    ];
}

/**
 * Operarios de la comisión enviados como operarios[] (ids de operario).
 */
function operariosDesdePost() {
    $ids = $_POST['operarios'] ?? [];
    return is_array($ids) ? array_values(array_filter(array_map('intval', $ids))) : [];
}
