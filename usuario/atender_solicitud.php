<?php
/**
 * usuario/atender_solicitud.php
 * Endpoint AJAX: descarta el aviso de contraseña olvidada de un usuario (sin cambiarle la contraseña).
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
requireRole(['admin']);

require_once __DIR__ . '/../clases/solicitud_contrasena.php';

$dni = trim((string)($_POST['dni'] ?? ''));
if ($dni === '') {
    echo json_encode(['success' => false, 'error' => 'El DNI del usuario es obligatorio.']);
    exit;
}

SolicitudContrasena::atender($dni);
echo json_encode(['success' => true, 'pendientes' => SolicitudContrasena::contarPendientes()]);
exit;
