<?php
/**
 * movimiento/detalle_movimiento.php
 * Endpoint AJAX y vista de detalle de un movimiento específico.
 * Responde en JSON para modales vía fetch() y en HTML para acceso directo.
 */

require_once __DIR__ . '/../config/config.php';
if (!isAuthenticated()) {
    if ((isset($_GET['ajax']) && $_GET['ajax'] == '1')) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => 'Sesión no iniciada. Inicie sesión nuevamente.']);
        exit;
    }
    redirect('auth/login.php');
}

require_once __DIR__ . '/../clases/movimiento.php';

$idMovimiento = isset($_GET['id']) ? (int)$_GET['id'] : (isset($_GET['id_movimiento']) ? (int)$_GET['id_movimiento'] : (isset($_POST['id_movimiento']) ? (int)$_POST['id_movimiento'] : 0));

$movimientoModel = new Movimiento();

// Si se solicita vía AJAX o fetch con formato JSON
$esAjax = (isset($_GET['ajax']) && $_GET['ajax'] == '1') 
    || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)
    || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');

if ($idMovimiento <= 0) {
    if ($esAjax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => 'Identificador de movimiento inválido.']);
        exit;
    }
    header('Location: dashboard.php');
    exit;
}

$movimiento = $movimientoModel->getById($idMovimiento);
if (!$movimiento) {
    if ($esAjax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => "No se encontró el movimiento con ID #{$idMovimiento}."]);
        exit;
    }
    header('Location: dashboard.php');
    exit;
}

$detalles = $movimientoModel->getDetalles($idMovimiento);

if ($esAjax) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success'    => true,
        'movimiento' => $movimiento,
        'detalles'   => $detalles
    ]);
    exit;
}

// Vista HTML independiente si se accede directamente
$seccion = 'movimiento';
$css_modulo = 'movimientos';
$titulo_vista = 'Detalle de Movimiento #' . $idMovimiento;
$titulo_pagina = 'Movimiento #' . $idMovimiento . ' - Sistema DeControl';

// Orden de Trabajo del movimiento (RF-27): editable por admin/pañolero en entregas y devoluciones activas
$tipoVinculable = in_array($movimiento['nombre_tipo_movimiento'], [Movimiento::TIPO_ENTREGA, Movimiento::TIPO_DEVOLUCION], true);
$movActivo = !empty($movimiento['activo']) && $movimiento['activo'] !== 'f';
$puedeVincular = $tipoVinculable && $movActivo && canEdit();
$ordenesVinculables = [];
if ($puedeVincular) {
    require_once __DIR__ . '/../clases/orden_de_trabajo.php';
    $esEntrega = ($movimiento['nombre_tipo_movimiento'] === Movimiento::TIPO_ENTREGA);
    $ordenesVinculables = array_values(array_filter((new OrdenDeTrabajo())->getAll(), function ($o) use ($movimiento, $esEntrega) {
        $esActual = (int)$o['id_odt'] === (int)($movimiento['id_odt'] ?? 0);
        return $esActual || !$esEntrega || !in_array($o['estado'] ?? '', ['Finalizada', 'Anulada'], true);
    }));
}

include_once __DIR__ . '/../layout/header.php';
?>

<div class="form-card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
        <div>
            <h2 class="form-card-title">Movimiento #<?= htmlspecialchars($movimiento['id_movimiento']) ?></h2>
            <p class="form-card-subtitle"><?= htmlspecialchars($movimiento['nombre_tipo_movimiento']) ?> - Registrado el <?= htmlspecialchars($movimiento['fecha']) ?> a las <?= htmlspecialchars(substr($movimiento['hora'], 0, 5)) ?></p>
        </div>
        <a href="dashboard.php" class="btn-cancel-form">Volver al Historial</a>
    </div>

    <div class="form-grid-3">
        <div class="form-group">
            <label>Tipo de Movimiento</label>
            <input type="text" class="form-control" value="<?= htmlspecialchars($movimiento['nombre_tipo_movimiento']) ?>" readonly>
        </div>
        <div class="form-group">
            <label>Operario Responsable</label>
            <input type="text" class="form-control" value="<?= !empty($movimiento['nombre_operario']) ? htmlspecialchars($movimiento['nombre_operario']) : 'No aplica' ?>" readonly>
        </div>
        <div class="form-group">
            <label>Registrado Por (Usuario)</label>
            <input type="text" class="form-control" value="<?= htmlspecialchars($movimiento['nombre_usuario']) ?> (DNI: <?= htmlspecialchars($movimiento['dni_usuario']) ?>)" readonly>
        </div>
    </div>

    <?php if ($puedeVincular): ?>
    <div class="form-group" style="margin-bottom: 1.25rem;">
        <label for="id_odt">Orden de Trabajo (opcional)</label>
        <div style="display: flex; gap: 0.75rem; align-items: center;">
            <select id="id_odt" class="form-control" style="flex: 1;">
                <option value="">Sin OT / Uso general</option>
                <?php foreach ($ordenesVinculables as $ot):
                    $trabajoOt = trim((string)($ot['trabajo_a_realizar'] ?? ''));
                    if (mb_strlen($trabajoOt) > 60) {
                        $trabajoOt = mb_substr($trabajoOt, 0, 60) . '...';
                    }
                    $textoOt = $ot['numero_ot'] . ($trabajoOt !== '' ? ' - ' . $trabajoOt : '') . ' [' . ($ot['estado'] ?? 'Pendiente') . ']';
                ?>
                    <option value="<?= (int)$ot['id_odt'] ?>" <?= ((int)($movimiento['id_odt'] ?? 0) === (int)$ot['id_odt']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($textoOt) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="button" class="btn-submit-form" id="btn-guardar-odt" onclick="guardarOrdenMovimiento(<?= (int)$idMovimiento ?>)">Guardar</button>
        </div>
        <div id="msg-odt" style="font-size: 0.85rem; margin-top: 0.4rem; font-weight: 600;"></div>
    </div>
    <?php elseif (!empty($movimiento['numero_ot'])): ?>
    <div class="form-group" style="margin-bottom: 1.25rem;">
        <label>Orden de Trabajo</label>
        <input type="text" class="form-control" value="<?= htmlspecialchars($movimiento['numero_ot']) ?>" readonly>
    </div>
    <?php endif; ?>

    <?php if (!empty($movimiento['nombre_motivo'])): ?>
    <div class="form-group" style="margin-bottom: 1.25rem;">
        <label>Motivo de la Devolución</label>
        <input type="text" class="form-control" value="<?= htmlspecialchars($movimiento['nombre_motivo']) ?>" readonly>
    </div>
    <?php endif; ?>

    <div class="form-group" style="margin-bottom: 2rem;">
        <label>Observaciones</label>
        <textarea class="form-control" style="min-height: 70px; resize: vertical;" readonly><?= !empty($movimiento['observaciones']) ? htmlspecialchars($movimiento['observaciones']) : 'Sin observaciones registradas.' ?></textarea>
    </div>

    <div class="items-section-header">
        <h3 class="items-section-title">Ítems Involucrados en el Movimiento (<?= count($detalles) ?>)</h3>
    </div>

    <table class="items-table">
        <thead>
            <tr>
                <th>Código</th>
                <th>Insumo / Herramienta</th>
                <th>Tipo</th>
                <th style="text-align: right;">Cantidad</th>
                <th>Unidad</th>
                <th>Estado Herramienta</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($detalles)): ?>
                <tr>
                    <td colspan="6" style="text-align: center; color: #64748b; padding: 2rem;">No hay detalles registrados para este movimiento.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($detalles as $d): 
                    $estadoHerramienta = !empty($d['nombre_estado_herramienta']) ? htmlspecialchars($d['nombre_estado_herramienta']) : 'No aplica';
                ?>
                <tr>
                    <td style="font-weight: 700;"><?= htmlspecialchars($d['codigo']) ?></td>
                    <td><?= htmlspecialchars($d['nombre_insumo']) ?></td>
                    <td><?= htmlspecialchars($d['tipo_insumo'] ?? 'Material') ?></td>
                    <td style="text-align: right; font-weight: 700;"><?= (float)$d['cantidad'] ?></td>
                    <td><?= htmlspecialchars($d['unidad_medida'] ?? 'unidades') ?></td>
                    <td><span class="badge badge-neutral"><?= $estadoHerramienta ?></span></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php if ($puedeVincular): ?>
<script>
function guardarOrdenMovimiento(idMovimiento) {
    const btn = document.getElementById('btn-guardar-odt');
    const msg = document.getElementById('msg-odt');
    const formData = new FormData();
    formData.append('id_movimiento', idMovimiento);
    formData.append('id_odt', document.getElementById('id_odt').value);
    btn.disabled = true;

    fetch('editar_movimiento.php', { method: 'POST', body: formData, credentials: 'same-origin' })
        .then(response => response.json())
        .then(res => {
            if (!res.success) throw new Error(res.error || 'No se pudo guardar la Orden de Trabajo.');
            msg.style.color = '#166534';
            msg.innerText = res.message;
        })
        .catch(err => {
            msg.style.color = '#991b1b';
            msg.innerText = err.message;
        })
        .finally(() => { btn.disabled = false; });
}
</script>
<?php endif; ?>

<?php
include_once __DIR__ . '/../layout/footer.php';
?>

