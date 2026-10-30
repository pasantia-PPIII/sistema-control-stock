<?php
/**
 * clases/validador.php
 * Validaciones de datos de entrada compartidas por las clases del dominio y los endpoints.
 * Cada método devuelve el valor ya normalizado o lanza una Exception con un mensaje apto para el usuario.
 */
class Validador {
    // Contraseña: bcrypt solo considera los primeros 72 bytes, por eso ese es el tope
    const PASSWORD_MIN = 8;
    const PASSWORD_MAX = 72;

    /**
     * DNI: solo dígitos, 7 u 8 (sin puntos, guiones ni espacios).
     * @param mixed $valor DNI ingresado
     * @param string $etiqueta Cómo nombrarlo en el mensaje ("DNI", "DNI del operario"...)
     * @return string DNI sin espacios en los extremos
     */
    public static function dni($valor, string $etiqueta = 'DNI'): string {
        $dni = trim((string)$valor);
        if ($dni === '') {
            throw new Exception("El {$etiqueta} es obligatorio.");
        }
        if (!preg_match('/^\d{7,8}$/', $dni)) {
            throw new Exception("El {$etiqueta} debe tener 7 u 8 dígitos, solo números y sin puntos ni espacios (ingresó '{$dni}').");
        }
        return $dni;
    }

    /**
     * Contraseña: entre 8 y 72 caracteres, con al menos una letra y un número.
     * No se recorta (los espacios cuentan), para que lo validado sea lo que se guarda.
     */
    public static function password($valor): string {
        $clave = (string)$valor;
        if ($clave === '') {
            throw new Exception('La contraseña es obligatoria.');
        }
        if (strlen($clave) < self::PASSWORD_MIN) {
            throw new Exception('La contraseña debe tener al menos ' . self::PASSWORD_MIN . ' caracteres (tiene ' . mb_strlen($clave, 'UTF-8') . ').');
        }
        if (strlen($clave) > self::PASSWORD_MAX) {
            throw new Exception('La contraseña no puede superar los ' . self::PASSWORD_MAX . ' caracteres.');
        }
        if (!preg_match('/\p{L}/u', $clave) || !preg_match('/\d/', $clave)) {
            throw new Exception('La contraseña debe combinar letras y números.');
        }
        return $clave;
    }

    /**
     * Fecha en formato AAAA-MM-DD que exista en el calendario (rechaza 2026-02-30, 2026-13-01, etc.).
     * @param string $etiqueta Ej.: "fecha de vencimiento" (se usa en el mensaje)
     * @return string La fecha normalizada
     */
    public static function fecha($valor, string $etiqueta = 'fecha'): string {
        $texto = trim((string)$valor);
        $f = DateTime::createFromFormat('!Y-m-d', $texto);
        if (!$f || $f->format('Y-m-d') !== $texto) {
            throw new Exception("La {$etiqueta} no es válida: use el formato AAAA-MM-DD y un día que exista en el calendario.");
        }
        return $texto;
    }

    /** Número mayor o igual a cero (stock, stock mínimo, cantidades). Devuelve el valor como float. */
    public static function numeroNoNegativo($valor, string $etiqueta): float {
        if (!is_numeric($valor)) {
            throw new Exception("{$etiqueta} debe ser un número (ingresó '" . trim((string)$valor) . "').");
        }
        if ((float)$valor < 0) {
            throw new Exception("{$etiqueta} no puede ser negativo.");
        }
        return (float)$valor;
    }
}
