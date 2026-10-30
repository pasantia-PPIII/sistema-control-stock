<?php
/**
 * clases/foto_perfil.php
 * Foto de perfil de los usuarios: validación y guardado del archivo en uploads/fotos_perfil/,
 * borrado de la anterior y URL a mostrar (la imagen genérica cuando el usuario no tiene foto).
 * En la base solo se guarda el nombre del archivo (usuario.foto_perfil, opcional).
 */
class FotoPerfil {
    const CARPETA = 'uploads/fotos_perfil/';
    const GENERICA = 'assets/img/usuario_generico.svg';
    const MAX_BYTES = 2 * 1024 * 1024; // 2 MB

    // Tipos de imagen admitidos: se decide por el contenido real del archivo, no por el nombre que trae
    const TIPOS = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    private static function directorio(): string {
        return dirname(__DIR__) . '/' . self::CARPETA;
    }

    /**
     * Detecta que la petición superó post_max_size: PHP descarta entonces todo el formulario ($_POST y $_FILES
     * quedan vacíos) y, sin este aviso, el usuario vería un error engañoso ("el DNI es obligatorio").
     * Llamar al comienzo del endpoint, antes de leer los datos.
     */
    public static function verificarLimiteDePeticion(): void {
        $tamano = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
        $limite = self::bytesDeIni((string)ini_get('post_max_size'));
        if ($tamano > 0 && $limite > 0 && $tamano > $limite && empty($_POST) && empty($_FILES)) {
            throw new Exception('La foto de perfil supera el tamaño máximo permitido (' . self::mbMaximo() . ' MB).');
        }
    }

    /** Convierte valores de php.ini como "8M" o "512K" a bytes (0 o -1 = sin límite). */
    private static function bytesDeIni(string $valor): int {
        $valor = trim($valor);
        $numero = (int)$valor;
        switch (strtolower(substr($valor, -1))) {
            case 'g': $numero *= 1024;
            // no break
            case 'm': $numero *= 1024;
            // no break
            case 'k': $numero *= 1024;
        }
        return $numero;
    }

    /**
     * Guarda la foto subida en el campo $_FILES y devuelve el nombre del archivo generado.
     * Devuelve null si no se adjuntó ninguna (la foto es opcional); lanza Exception si el archivo no es válido.
     * @param array|null $archivo Entrada de $_FILES (p. ej. $_FILES['foto_perfil'])
     * @param string $dni DNI del usuario (forma parte del nombre del archivo)
     */
    public static function guardar(?array $archivo, string $dni): ?string {
        if ($archivo === null || ($archivo['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        $error = $archivo['error'];
        if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
            throw new Exception('La foto de perfil supera el tamaño máximo permitido (' . self::mbMaximo() . ' MB).');
        }
        if ($error !== UPLOAD_ERR_OK) {
            throw new Exception("No se pudo cargar la foto de perfil (código de error: {$error}). Intente nuevamente.");
        }
        if ($archivo['size'] > self::MAX_BYTES) {
            throw new Exception('La foto de perfil supera el tamaño máximo permitido (' . self::mbMaximo() . ' MB).');
        }

        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($archivo['tmp_name']);
        if (!isset(self::TIPOS[$mime]) || @getimagesize($archivo['tmp_name']) === false) {
            throw new Exception('La foto de perfil debe ser una imagen JPG, PNG o WEBP.');
        }

        $directorio = self::directorio();
        if (!is_dir($directorio) && !mkdir($directorio, 0755, true)) {
            throw new Exception('No se pudo preparar la carpeta donde se guardan las fotos de perfil.');
        }

        // Nombre propio (el original no se usa): evita choques y nombres con caracteres peligrosos
        $nombre = 'u_' . preg_replace('/\D/', '', $dni) . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . self::TIPOS[$mime];
        if (!move_uploaded_file($archivo['tmp_name'], $directorio . $nombre)) {
            throw new Exception('No se pudo guardar la foto de perfil en el servidor.');
        }
        return $nombre;
    }

    /** Borra un archivo de foto (si existe). Ignora nombres vacíos y rutas que intenten salir de la carpeta. */
    public static function eliminar(?string $nombre): void {
        if ($nombre === null || $nombre === '' || $nombre !== basename($nombre)) {
            return;
        }
        $ruta = self::directorio() . $nombre;
        if (is_file($ruta)) {
            @unlink($ruta);
        }
    }

    /** URL de la foto del usuario, o de la imagen genérica si no tiene (o si el archivo ya no existe). */
    public static function url(?string $nombre): string {
        $base = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '';
        if ($nombre !== null && $nombre !== '' && $nombre === basename($nombre) && is_file(self::directorio() . $nombre)) {
            return $base . '/' . self::CARPETA . rawurlencode($nombre);
        }
        return $base . '/' . self::GENERICA;
    }

    private static function mbMaximo(): int {
        return (int)(self::MAX_BYTES / 1024 / 1024);
    }
}
