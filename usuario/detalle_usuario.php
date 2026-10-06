<?php
/**
 * usuario/detalle_usuario.php
 * Ficha de un usuario del sistema (vista HTML y respuesta JSON con ?ajax=1).
 *
 * - Parámetro: ?dni=XXXX
 * - Visible para cualquier usuario autenticado (solo lectura); nunca expone la contraseña.
 * - Nunca expone el hash de la contraseña.
 */

require_once __DIR__ . '/../config/config.php';

$esAjax = (isset($_GET['ajax']) && $_GET['ajax'] == '1')
    || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');

if (!isAuthenticated()) {
    if ($esAjax) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => 'Sesión no iniciada. Inicie sesión nuevamente.']);
        exit;
    }
    redirect('auth/login.php');
}

require_once __DIR__ . '/../clases/usuario.php';

$dni = isset($_GET['dni']) ? trim((string)$_GET['dni']) : '';

$usuarioModel = new Usuario();
$usuario = $dni !== '' ? $usuarioModel->getByDni($dni, true) : null;

if (!$usuario) {
    if ($esAjax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => "No se encontró el usuario con DNI '{$dni}'."]);
        exit;
    }
    setAlert('El usuario solicitado no existe.', 'warning');
    redirect('usuario/dashboard.php');
}

if ($esAjax) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => true, 'data' => $usuario]);
    exit;
}

$activo = !empty($usuario['activo']) && $usuario['activo'] !== 'f';
$nombreCompleto = trim(($usuario['apellido'] ?? '') . ', ' . ($usuario['nombre'] ?? ''), ' ,');

$seccion = 'usuario';
$css_modulo = 'usuarios';
$titulo_vista = 'Detalle de Usuario';
$titulo_pagina = 'Usuario ' . $usuario['dni'] . ' - Sistema de Control de Stock';

include_once __DIR__ . '/../layout/header.php';

$h = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
?>

<div style="background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:1.5rem 2rem; max-width:720px;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
        <div>
            <h2 style="margin:0; color:#0b1536;"><?= $h($nombreCompleto !== '' ? $nombreCompleto : $usuario['dni']) ?></h2>
            <p style="margin:0.3rem 0 0; color:#64748b;">DNI <strong><?= $h($usuario['dni']) ?></strong></p>
        </div>
        <div style="display:flex; align-items:center; gap:1rem;">
            <span class="<?= $activo ? 'badge-status-activo' : 'badge-status-inactivo' ?>"><?= $activo ? 'Activo' : 'Inactivo' ?></span>
            <a href="dashboard.php" class="btn-action-text view" style="text-decoration:none; padding:0.5rem 1rem;">Volver a Usuarios</a>
        </div>
    </div>

    <div style="display:flex; flex-direction:column; gap:0.65rem; margin-top:1.5rem; font-size:0.95rem; color:#1e293b;">
        <div><strong>Rol:</strong> <span class="badge-role"><?= $h($usuario['nombre_rol'] ?? 'Sin Rol') ?></span></div>
        <div>
            <strong>Operario vinculado:</strong>
            <?php if (!empty($usuario['id_operario'])): ?>
                <a href="../operario/detalle_operario.php?id=<?= (int)$usuario['id_operario'] ?>">
                    <?= $h(($usuario['operario_apellido'] ?? '') . ', ' . ($usuario['operario_nombre'] ?? '')) ?>
                </a>
                (DNI <?= $h($usuario['dni_operario'] ?? '-') ?>)
            <?php else: ?>
                Ninguno
            <?php endif; ?>
        </div>
        <div><strong>Estado de la cuenta:</strong> <?= $activo ? 'Habilitada' : 'Deshabilitada' ?></div>
    </div>
</div>

<?php
include_once __DIR__ . '/../layout/footer.php';
?>
