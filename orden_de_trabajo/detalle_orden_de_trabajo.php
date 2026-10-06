<?php
/**
 * orden_de_trabajo/detalle_orden_de_trabajo.php
 * Detalle de una Orden de Trabajo: vista HTML y respuesta JSON (?ajax=1) para modales.
 *
 * - Parámetro: ?id=N
 * - Datos mediante la clase OrdenDeTrabajo (getById, getOperariosAsignados, getMovimientosVinculados).
 */

require_once __DIR__ . '/../config/config.php';

$esAjax = (isset($_GET['ajax']) && $_GET['ajax'] == '1')
    || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)
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

require_once __DIR__ . '/../clases/orden_de_trabajo.php';

$idOdt = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$odtModel = new OrdenDeTrabajo();
$orden = $idOdt > 0 ? $odtModel->getById($idOdt) : false;

if (!$orden) {
    if ($esAjax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => "No se encontró la Orden de Trabajo #{$idOdt}."]);
        exit;
    }
    setAlert('La Orden de Trabajo solicitada no existe o fue anulada.', 'warning');
    redirect('orden_de_trabajo/dashboard.php');
}

$operarios = $odtModel->getOperariosAsignados($idOdt);
$movimientos = $odtModel->getMovimientosVinculados($idOdt);

if ($esAjax) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success'     => true,
        'data'        => $orden,
        'operarios'   => $operarios,
        'movimientos' => $movimientos
    ]);
    exit;
}

$seccion = 'orden_de_trabajo';
$css_modulo = 'orden_de_trabajo';
$titulo_vista = 'Detalle de Orden de Trabajo #' . $idOdt;
$titulo_pagina = 'OT ' . $orden['numero_ot'] . ' - Sistema de Control de Stock';

include_once __DIR__ . '/../layout/header.php';

$h = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$estado = $orden['estado'] ?? 'Pendiente';
$badgeClass = 'badge-' . strtolower(str_replace(' ', '-', trim($estado)));
$fecha = fn($f) => !empty($f) ? date('d/m/Y', strtotime($f)) : '-';
?>

<div class="detail-card">
    <div class="detail-header">
        <div>
            <h2 class="detail-title">Orden de Trabajo: <?= $h($orden['numero_ot']) ?></h2>
            <div class="detail-subtitle">
                ID Sistema: #<?= (int)$orden['id_odt'] ?> | Estado actual:
                <span class="badge <?= $badgeClass ?>"><?= $h($estado) ?></span>
            </div>
        </div>
        <a href="dashboard.php" class="btn-modal-cancel" style="text-decoration:none;">Volver al Listado</a>
    </div>

    <div class="detail-grid">
        <div class="detail-item">
            <strong>Número de PA (Pedido de Abastecimiento)</strong>
            <span><?= !empty($orden['pa']) ? $h($orden['pa']) : 'No especificado' ?></span>
        </div>
        <div class="detail-item">
            <strong>Localidad</strong>
            <span><?= !empty($orden['nombre_localidad']) ? $h($orden['nombre_localidad']) : 'No asignada' ?></span>
        </div>
        <div class="detail-item">
            <strong>Jurisdicción</strong>
            <span><?= !empty($orden['nombre_jurisdiccion']) ? $h($orden['nombre_jurisdiccion']) : 'No asignada' ?></span>
        </div>
        <div class="detail-item">
            <strong>Fecha de Inicio</strong>
            <span><?= $h($fecha($orden['fecha_inicio'] ?? null)) ?></span>
        </div>
        <div class="detail-item">
            <strong>Fecha de Finalización</strong>
            <span><?= $h($fecha($orden['fecha_final'] ?? null)) ?></span>
        </div>
        <div class="detail-item">
            <strong>Horario Programado</strong>
            <span>
                <?= !empty($orden['hora_inicio']) ? $h(substr($orden['hora_inicio'], 0, 5)) : '--:--' ?>
                a
                <?= !empty($orden['hora_final']) ? $h(substr($orden['hora_final'], 0, 5)) : '--:--' ?>
            </span>
        </div>
    </div>

    <div style="margin-bottom: 1.5rem;">
        <label class="modal-label">Trabajo a Realizar / Descripción</label>
        <div style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 1rem; color: #334155; line-height: 1.6;">
            <?= !empty($orden['trabajo_a_realizar']) ? nl2br($h($orden['trabajo_a_realizar'])) : 'Sin descripción detallada.' ?>
        </div>
    </div>

    <div style="margin-bottom: 1.5rem;">
        <label class="modal-label">Observaciones</label>
        <div style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 1rem; color: #334155; line-height: 1.6;">
            <?= !empty($orden['observaciones']) ? nl2br($h($orden['observaciones'])) : 'Sin observaciones registradas.' ?>
        </div>
    </div>

    <div style="margin-bottom: 1.5rem;">
        <label class="modal-label">Operarios Asignados (<?= count($operarios) ?>)</label>
        <?php if (empty($operarios)): ?>
            <div style="color: #64748b;">No hay operarios asignados a esta orden.</div>
        <?php else: ?>
            <ul style="margin: 0.3rem 0 0 1.2rem; color: #334155; line-height: 1.7;">
                <?php foreach ($operarios as $op): ?>
                    <li>
                        <?= $h($op['apellido'] . ', ' . $op['nombre']) ?> (DNI <?= $h($op['dni']) ?>)
                        <?= !empty($op['nombre_rubro']) ? ' - ' . $h($op['nombre_rubro']) : '' ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>

    <div style="margin-bottom: 1.5rem;">
        <label class="modal-label">Movimientos Vinculados</label>
        <?php if (empty($movimientos)): ?>
            <div style="color: #64748b;">No hay movimientos de egreso ni devolución vinculados.</div>
        <?php else: ?>
            <ul style="margin: 0.3rem 0 0 1.2rem; color: #334155; line-height: 1.7;">
                <?php foreach ($movimientos as $mv): ?>
                    <li>
                        <?= $mv['rol_en_odt'] === 'egreso' ? 'Egreso' : 'Devolución' ?>:
                        <a href="../movimiento/detalle_movimiento.php?id=<?= (int)$mv['id_movimiento'] ?>">Movimiento #<?= (int)$mv['id_movimiento'] ?></a>
                        - <?= $h($fecha($mv['fecha'])) ?> <?= $h(substr($mv['hora'], 0, 5)) ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>

    <div>
        <label class="modal-label">Documento Oficial PDF</label>
        <?php if (!empty($orden['archivo_pdf'])): ?>
            <a href="<?= rtrim(BASE_URL, '/') ?>/uploads/pa_pdfs/<?= rawurlencode($orden['archivo_pdf']) ?>" target="_blank" rel="noopener" class="btn-pdf-view">
                Ver PDF Adjunto
            </a>
            <span style="font-size: 0.8rem; color: #64748b; margin-left: 0.5rem;">(<?= $h($orden['archivo_pdf']) ?>)</span>
        <?php else: ?>
            <span class="btn-pdf-none">No se ha adjuntado ningún documento PDF a esta orden.</span>
        <?php endif; ?>
    </div>
</div>

<?php
include_once __DIR__ . '/../layout/footer.php';
?>
