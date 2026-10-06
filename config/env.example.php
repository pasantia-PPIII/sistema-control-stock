<?php
// Copiar como config/env.php (no se versiona) y completar con los datos reales del entorno.
define('DB_HOST', 'localhost');
define('DB_PORT', '5432');
define('DB_NAME', 'db_control_stock');
define('DB_USER', 'postgres');
define('DB_PASS', '');

// true solo en desarrollo (muestra errores en pantalla); en producción false o eliminar la línea
define('APP_DEBUG', false);

// Opcional: forzar la URL base (con "/" final). Si se omite se deduce de la petición.
// define('BASE_URL', 'https://stock.ejemplo.gob.ar/');
