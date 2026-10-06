<?php
/**
 * rubro/detalle_rubro.php
 * Detalle de un rubro: datos generales e insumos que lo componen
 * (vista HTML y respuesta JSON con ?ajax=1).
 *
 * - Parámetro: ?id=N
 * - Datos mediante la clase Rubro (getById, getInsumosByRubro).
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

require_once __DIR__ . '/../clases/rubro.php';

$idRubro = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$rubroModel = new Rubro();
$rubro = $idRubro > 0 ? $rubroModel->getById($idRubro) : false;

if (!$rubro) {
    if ($esAjax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => "No se encontró el rubro #{$idRubro}."]);
        exit;
    }
    setAlert('El rubro solicitado no existe.', 'warning');
    redirect('rubro/dashboard.php');
}

$insumos = $rubroModel->getInsumosByRubro($idRubro);

if ($esAjax) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => true, 'data' => $rubro, 'insumos' => $insumos]);
    exit;
}

$activo = !empty($rubro['activo']) && $rubro['activo'] !== 'f';

$seccion = 'rubro';
$css_modulo = 'rubros';
$titulo_vista = 'Detalle de Rubro';
$titulo_pagina = 'Rubro ' . $rubro['nombre'] . ' - Sistema de Control de Stock';

include_once __DIR__ . '/../layout/header.php';

$h = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
?>

<div style="background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:1.5rem 2rem;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
        <div>
            <h2 style="margin:0; color:#0b1536;"><?= $h($rubro['nombre']) ?></h2>
            <p style="margin:0.3rem 0 0; color:#64748b;"><?= !empty($rubro['descripcion']) ? $h($rubro['descripcion']) : 'Sin descripción.' ?></p>
        </div>
        <div style="display:flex; align-items:center; gap:1rem;">
            <span class="badge <?= $activo ? 'badge-success' : 'badge-danger' ?>"><?= $activo ? 'Activo' : 'Inactivo' ?></span>
            <a href="dashboard.php" class="btn-action-text view" style="text-decoration:none; padding:0.5rem 1rem;">Volver a Rubros</a>
        </div>
    </div>

    <h3 style="color:#0b1536; margin:1.5rem 0 0.7rem;">Insumos del rubro (<?= count($insumos) ?>)</h3>
    <div style="overflow-x:auto;">
        <table style="width:100%; border-collapse:collapse; font-size:0.9rem;">
            <thead>
                <tr style="text-align:left; border-bottom:2px solid #e2e8f0; color:#64748b;">
                    <th style="padding:0.5rem;">Código</th>
                    <th style="padding:0.5rem;">Nombre</th>
                    <th style="padding:0.5rem;">Tipo</th>
                    <th style="padding:0.5rem;">Unidad</th>
                    <th style="padding:0.5rem; text-align:right;">Stock</th>
                    <th style="padding:0.5rem;">Estado</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($insumos)): ?>
                    <tr><td colspan="6" style="text-align:center; color:#64748b; padding:1.5rem;">Este rubro no tiene insumos asociados.</td></tr>
                <?php else: ?>
                    <?php foreach ($insumos as $i):
                        $insActivo = !empty($i['activo']) && $i['activo'] !== 'f';
                    ?>
                        <tr style="border-bottom:1px solid #f1f5f9;">
                            <td style="padding:0.5rem;"><a href="../insumo/detalles_insumo.php?codigo=<?= rawurlencode($i['codigo']) ?>"><strong><?= $h($i['codigo']) ?></strong></a></td>
                            <td style="padding:0.5rem;"><?= $h($i['nombre']) ?></td>
                            <td style="padding:0.5rem;"><?= $h($i['tipo_nombre'] ?? '-') ?></td>
                            <td style="padding:0.5rem;"><?= $h($i['unidad_medida'] ?? '-') ?></td>
                            <td style="padding:0.5rem; text-align:right; font-weight:700;"><?= (float)($i['stock_actual'] ?? 0) ?></td>
                            <td style="padding:0.5rem;"><?= $insActivo ? 'Activo' : 'Deshabilitado' ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
include_once __DIR__ . '/../layout/footer.php';
?>
