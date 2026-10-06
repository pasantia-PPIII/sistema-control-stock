<?php
/**
 * insumo/detalles_insumo.php
 * Ficha de detalle de un insumo (vista HTML y respuesta JSON con ?ajax=1).
 *
 * - Parámetro: ?codigo=XXXX
 * - Datos mediante la clase Insumo (getByCodigo, getMovimientos).
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

require_once __DIR__ . '/../clases/insumo.php';

$codigo = isset($_GET['codigo']) ? trim((string)$_GET['codigo']) : '';

$insumoModel = new Insumo();
$insumo = $codigo !== '' ? $insumoModel->getByCodigo($codigo) : null;

if (!$insumo) {
    if ($esAjax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => "No se encontró el insumo con código '{$codigo}'."]);
        exit;
    }
    setAlert('El insumo solicitado no existe o se encuentra deshabilitado.', 'warning');
    redirect('insumo/dashboard.php');
}

$movimientos = $insumoModel->getMovimientos($insumo['codigo']);

if ($esAjax) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => true, 'data' => $insumo, 'movimientos' => $movimientos]);
    exit;
}

$esHerramienta = (!empty($insumo['nombre_tipo']) && stripos($insumo['nombre_tipo'], 'herramienta') !== false);
$stockActual = (float)($insumo['stock_actual'] ?? 0);
$stockMinimo = (float)($insumo['stock_minimo'] ?? 0);
$stockBajo = !$esHerramienta && $stockActual < $stockMinimo;
$perecedero = !empty($insumo['es_perecedero']) && $insumo['es_perecedero'] !== 'f';

$seccion = 'insumo';
$css_modulo = 'insumos';
$titulo_vista = 'Detalle de Insumo';
$titulo_pagina = 'Insumo ' . $insumo['codigo'] . ' - Sistema de Control de Stock';

include_once __DIR__ . '/../layout/header.php';

$h = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
?>

<div style="background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:1.5rem 2rem;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
        <div>
            <h2 style="margin:0; color:#0b1536;"><?= $h($insumo['nombre']) ?></h2>
            <p style="margin:0.3rem 0 0; color:#64748b;">Código <strong><?= $h($insumo['codigo']) ?></strong> - <?= $h($insumo['nombre_tipo'] ?? 'Material') ?></p>
        </div>
        <a href="dashboard.php" class="btn-modal-back" style="text-decoration:none; width:auto; padding:0.6rem 1.2rem;">Volver a Insumos</a>
    </div>

    <?php if ($stockBajo): ?>
        <div style="background:#fef2f2; border:1px solid #fecaca; color:#991b1b; border-radius:8px; padding:0.7rem 1rem; margin-bottom:1rem; font-size:0.9rem;">
            Stock por debajo del mínimo configurado (<?= $stockActual ?> disponible, mínimo <?= $stockMinimo ?>).
        </div>
    <?php endif; ?>

    <div class="details-list">
        <div class="detail-item"><strong>Rubro:</strong> <?= $h($insumo['nombre_rubro'] ?? '-') ?></div>
        <div class="detail-item"><strong>Ubicación:</strong> <?= $h($insumo['nombre_ubicacion'] ?? 'Sin ubicación asignada') ?></div>
        <?php if ($esHerramienta): ?>
            <div class="detail-item"><strong>Serie / Modelo:</strong> <?= !empty($insumo['serie_modelo']) ? $h($insumo['serie_modelo']) : '-' ?></div>
            <div class="detail-item"><strong>Estado de la herramienta:</strong> <?= $h($insumo['nombre_estado_herramienta'] ?? 'Sin estado') ?></div>
        <?php else: ?>
            <div class="detail-item"><strong>Unidad de Medida:</strong> <?= $h($insumo['unidad_medida'] ?? 'Unidad') ?></div>
            <div class="detail-item"><strong>Stock Actual:</strong> <?= $stockActual ?></div>
            <div class="detail-item"><strong>Stock Mínimo:</strong> <?= $stockMinimo ?></div>
            <div class="detail-item"><strong>¿Es Perecedero?:</strong> <?= $perecedero ? 'Sí' : 'No' ?></div>
            <div class="detail-item"><strong>Fecha de Vencimiento:</strong> <?= ($perecedero && !empty($insumo['fecha_vencimiento'])) ? $h(date('d/m/Y', strtotime($insumo['fecha_vencimiento']))) : '-' ?></div>
        <?php endif; ?>
        <div class="detail-item"><strong>Fecha de Registro:</strong> <?= !empty($insumo['fecha_registro']) ? $h(date('d/m/Y', strtotime($insumo['fecha_registro']))) : '-' ?></div>
        <div class="detail-item"><strong>Estado del Registro:</strong> <?= !empty($insumo['activo']) && $insumo['activo'] !== 'f' ? 'Activo' : 'Deshabilitado' ?></div>
    </div>

    <h3 style="color:#0b1536; margin:1.5rem 0 0.7rem;">Últimos movimientos (<?= count($movimientos) ?>)</h3>
    <div style="overflow-x:auto;">
        <table style="width:100%; border-collapse:collapse; font-size:0.9rem;">
            <thead>
                <tr style="text-align:left; border-bottom:2px solid #e2e8f0; color:#64748b;">
                    <th style="padding:0.5rem;">N°</th>
                    <th style="padding:0.5rem;">Fecha y Hora</th>
                    <th style="padding:0.5rem;">Tipo</th>
                    <th style="padding:0.5rem;">Operario</th>
                    <th style="padding:0.5rem; text-align:right;">Cantidad</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($movimientos)): ?>
                    <tr><td colspan="5" style="text-align:center; color:#64748b; padding:1.5rem;">Este insumo todavía no registra movimientos.</td></tr>
                <?php else: ?>
                    <?php foreach ($movimientos as $m): ?>
                        <tr style="border-bottom:1px solid #f1f5f9;">
                            <td style="padding:0.5rem;"><a href="../movimiento/detalle_movimiento.php?id=<?= (int)$m['id_movimiento'] ?>">#<?= (int)$m['id_movimiento'] ?></a></td>
                            <td style="padding:0.5rem;"><?= $h(date('d/m/Y', strtotime($m['fecha']))) ?> <?= $h(substr($m['hora'], 0, 5)) ?></td>
                            <td style="padding:0.5rem;"><?= $h($m['nombre_tipo_movimiento']) ?></td>
                            <td style="padding:0.5rem;"><?= !empty($m['nombre_operario']) ? $h($m['nombre_operario']) : '-' ?></td>
                            <td style="padding:0.5rem; text-align:right; font-weight:700;"><?= (float)$m['cantidad'] ?></td>
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
