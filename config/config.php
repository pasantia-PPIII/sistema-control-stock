<?php
/**
 * config/config.php
 * Configuración general del sistema Sistema DeControl.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

date_default_timezone_set('America/Argentina/Buenos_Aires');

// Definición de BASE_URL dinámica si no está definida
if (!defined('BASE_URL')) {
    $docRoot = isset($_SERVER['DOCUMENT_ROOT']) ? str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'])) : '';
    $projectRoot = str_replace('\\', '/', realpath(__DIR__ . '/..'));
    if ($docRoot && strpos($projectRoot, $docRoot) === 0) {
        $relative = substr($projectRoot, strlen($docRoot));
        define('BASE_URL', rtrim($relative, '/'));
    } else {
        define('BASE_URL', '/sistema-control-stock');
    }
}
