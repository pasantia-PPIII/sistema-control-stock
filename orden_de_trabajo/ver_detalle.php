<?php
/**
 * orden_de_trabajo/ver_detalle.php
 * Endpoint AJAX y vista detallada de una Orden de Trabajo.
 * Responde en JSON para modales vía Fetch API y en HTML para acceso directo.
 */

require_once __DIR__ . '/../clases/orden_de_trabajo.php';

$idOdt = isset($_GET['id']) ? (int)$_GET['id'] : (isset($_GET['id_odt']) ? (int)$_GET['id_odt'] : (isset($_POST['id_odt']) ? (int)$_POST['id_odt'] : 0));

$odtModel = new OrdenDeTrabajo();

$esAjax = (isset($_GET['ajax']) && $_GET['ajax'] == '1')
    || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)
    || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');

if ($idOdt <= 0) {
    if ($esAjax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => 'Identificador de Orden de Trabajo inválido.']);
        exit;
    }
    header('Location: dashboard.php');
    exit;
}

$orden = $odtModel->obtenerPorId($idOdt);

if (!$orden) {
    if ($esAjax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => "No se encontró la Orden de Trabajo #{$idOdt}."]);
        exit;
    }
    header('Location: dashboard.php');
    exit;
}

if ($esAjax) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => true,
        'data'    => $orden
    ]);
    exit;
}

// Vista HTML para acceso directo por URL
$seccion = 'orden_de_trabajo';
$css_modulo = 'orden_de_trabajo';
$titulo_vista = 'Detalle de Orden de Trabajo #' . $idOdt;
$titulo_pagina = 'OT #' . $idOdt . ' - Sistema DeControl';

include_once __DIR__ . '/../layouts/header.php';

$estadoLower = strtolower(str_replace(' ', '-', trim($orden['estado'] ?? 'pendiente')));
$badgeClass = 'badge-' . $estadoLower;
?>

<div class="detail-card">
    <div class="detail-header">
        <div>
            <h2 class="detail-title">Orden de Trabajo: <?= htmlspecialchars($orden['numero_ot'], ENT_QUOTES, 'UTF-8') ?></h2>
            <div class="detail-subtitle">
                ID Sistema: #<?= (int)$orden['id_odt'] ?> | Estado actual: 
                <span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($orden['estado'] ?? 'Pendiente', ENT_QUOTES, 'UTF-8') ?></span>
            </div>
        </div>
        <a href="dashboard.php" class="btn-modal-cancel" style="text-decoration:none;">Volver al Listado</a>
    </div>

    <div class="detail-grid">
        <div class="detail-item">
            <strong>Número de PA (Pedido de Abastecimiento)</strong>
            <span><?= !empty($orden['pa']) ? htmlspecialchars($orden['pa'], ENT_QUOTES, 'UTF-8') : 'No especificado' ?></span>
        </div>
        <div class="detail-item">
            <strong>Localidad</strong>
            <span><?= !empty($orden['localidad_nombre']) ? htmlspecialchars($orden['localidad_nombre'], ENT_QUOTES, 'UTF-8') : 'No asignada' ?></span>
        </div>
        <div class="detail-item">
            <strong>Jurisdicción</strong>
            <span><?= !empty($orden['jurisdiccion_nombre']) ? htmlspecialchars($orden['jurisdiccion_nombre'], ENT_QUOTES, 'UTF-8') : 'No asignada' ?></span>
        </div>
        <div class="detail-item">
            <strong>Fecha de Inicio</strong>
            <span><?= !empty($orden['fecha_inicio']) ? htmlspecialchars($orden['fecha_inicio'], ENT_QUOTES, 'UTF-8') : '-' ?></span>
        </div>
        <div class="detail-item">
            <strong>Fecha de Finalización</strong>
            <span><?= !empty($orden['fecha_final']) ? htmlspecialchars($orden['fecha_final'], ENT_QUOTES, 'UTF-8') : '-' ?></span>
        </div>
        <div class="detail-item">
            <strong>Horario Programado</strong>
            <span>
                <?= !empty($orden['hora_inicio']) ? htmlspecialchars(substr($orden['hora_inicio'], 0, 5), ENT_QUOTES, 'UTF-8') : '--:--' ?> 
                a 
                <?= !empty($orden['hora_final']) ? htmlspecialchars(substr($orden['hora_final'], 0, 5), ENT_QUOTES, 'UTF-8') : '--:--' ?>
            </span>
        </div>
    </div>

    <div style="margin-bottom: 1.5rem;">
        <label class="modal-label">Trabajo a Realizar / Descripción</label>
        <div style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 1rem; color: #334155; line-height: 1.6;">
            <?= !empty($orden['trabajo_a_realizar']) ? nl2br(htmlspecialchars($orden['trabajo_a_realizar'], ENT_QUOTES, 'UTF-8')) : 'Sin descripción detallada.' ?>
        </div>
    </div>

    <div style="margin-bottom: 1.5rem;">
        <label class="modal-label">Observaciones</label>
        <div style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 1rem; color: #334155; line-height: 1.6;">
            <?= !empty($orden['observaciones']) ? nl2br(htmlspecialchars($orden['observaciones'], ENT_QUOTES, 'UTF-8')) : 'Sin observaciones registradas.' ?>
        </div>
    </div>

    <div>
        <label class="modal-label">Documento Oficial PDF</label>
        <?php if (!empty($orden['archivo_pdf'])): ?>
            <a href="<?= BASE_URL ?>/uploads/pa_pdfs/<?= htmlspecialchars($orden['archivo_pdf'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" class="btn-pdf-view">
                Ver PDF Adjunto
            </a>
            <span style="font-size: 0.8rem; color: #64748b; margin-left: 0.5rem;">(<?= htmlspecialchars($orden['archivo_pdf'], ENT_QUOTES, 'UTF-8') ?>)</span>
        <?php else: ?>
            <span class="btn-pdf-none">No se ha adjuntado ningún documento PDF a esta orden.</span>
        <?php endif; ?>
    </div>
</div>

<?php
include_once __DIR__ . '/../layouts/footer.php';
?>
