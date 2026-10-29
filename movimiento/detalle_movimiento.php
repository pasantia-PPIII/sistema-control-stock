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
$odtActual = '';
$tipoBusquedaOdt = 'devolucion';
if ($puedeVincular) {
    // La OT se busca por AJAX (ajax/buscar_autocompletado.php). Una entrega no puede vincularse a una orden Finalizada o Anulada
    $js_modulo = 'autocompletado';
    require_once __DIR__ . '/../clases/orden_de_trabajo.php';
    $tipoBusquedaOdt = ($movimiento['nombre_tipo_movimiento'] === Movimiento::TIPO_ENTREGA) ? 'entrega' : 'devolucion';
    if (!empty($movimiento['id_odt'])) {
        $otActual = (new OrdenDeTrabajo())->getById((int)$movimiento['id_odt']);
        $odtActual = $otActual ? OrdenDeTrabajo::textoParaSelect($otActual) : (string)($movimiento['numero_ot'] ?? '');
    }
}

include_once __DIR__ . '/../layout/header.php';
?>

<div class="form-card">
    <div class="detalle-cabecera">
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
            <div class="form-control form-control--texto"><?= enlaceOperario($movimiento['id_operario'] ?? null, $movimiento['nombre_operario'] ?? null, 'No aplica') ?></div>
        </div>
        <div class="form-group">
            <label>Registrado Por (Usuario)</label>
            <input type="text" class="form-control" value="<?= htmlspecialchars($movimiento['nombre_usuario']) ?> (DNI: <?= htmlspecialchars($movimiento['dni_usuario']) ?>)" readonly>
        </div>
    </div>

    <?php if ($puedeVincular): ?>
    <div class="form-group mb-20">
        <label for="buscar-odt">Orden de Trabajo (opcional)</label>
        <div class="fila-odt">
            <div class="autocomplete-wrapper form-control--fluido">
                <input type="text" id="buscar-odt" class="form-control" placeholder="Escriba el N° de OT, PA o trabajo... (vacío = sin OT / uso general)" autocomplete="off"
                       value="<?= htmlspecialchars($odtActual) ?>">
                <input type="hidden" id="id_odt" value="<?= (int)($movimiento['id_odt'] ?? 0) ?: '' ?>">
                <div id="lista-odt" class="autocomplete-list"></div>
            </div>
            <button type="button" class="btn-submit-form" id="btn-guardar-odt" onclick="guardarOrdenMovimiento(<?= (int)$idMovimiento ?>)">Guardar</button>
        </div>
        <div id="msg-odt" class="msg-odt"></div>
    </div>
    <?php elseif (!empty($movimiento['numero_ot'])): ?>
    <div class="form-group mb-20">
        <label>Orden de Trabajo</label>
        <input type="text" class="form-control" value="<?= htmlspecialchars($movimiento['numero_ot']) ?>" readonly>
    </div>
    <?php endif; ?>

    <?php if (!empty($movimiento['nombre_motivo'])): ?>
    <div class="form-group mb-20">
        <label>Motivo de la Devolución</label>
        <input type="text" class="form-control" value="<?= htmlspecialchars($movimiento['nombre_motivo']) ?>" readonly>
    </div>
    <?php endif; ?>

    <div class="form-group mb-32">
        <label>Observaciones</label>
        <textarea class="form-control form-control--area" readonly><?= !empty($movimiento['observaciones']) ? htmlspecialchars($movimiento['observaciones']) : 'Sin observaciones registradas.' ?></textarea>
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
                <th class="celda-derecha">Cantidad</th>
                <th>Unidad</th>
                <th>Estado Herramienta</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($detalles)): ?>
                <tr>
                    <td colspan="6" class="estado-vacio">No hay detalles registrados para este movimiento.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($detalles as $d): 
                    $estadoHerramienta = !empty($d['nombre_estado_herramienta']) ? htmlspecialchars($d['nombre_estado_herramienta']) : 'No aplica';
                ?>
                <tr>
                    <td class="celda-fuerte"><?= htmlspecialchars($d['codigo']) ?></td>
                    <td><?= htmlspecialchars($d['nombre_insumo']) ?></td>
                    <td><?= htmlspecialchars($d['tipo_insumo'] ?? 'Material') ?></td>
                    <td class="celda-derecha celda-fuerte"><?= (float)$d['cantidad'] ?></td>
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
// Autocompletado de la OT (assets/js/autocompletado.js, se carga al final de la página)
document.addEventListener('DOMContentLoaded', function () {
    crearAutocompletado({
        input: document.getElementById('buscar-odt'),
        lista: document.getElementById('lista-odt'),
        url: '../ajax/buscar_autocompletado.php',
        parametros: { recurso: 'odt', tipo: '<?= $tipoBusquedaOdt ?>' },
        alSeleccionar: function (ot) { document.getElementById('id_odt').value = ot.id; },
        alLimpiar: function () { document.getElementById('id_odt').value = ''; }
    });
});

function guardarOrdenMovimiento(idMovimiento) {
    const btn = document.getElementById('btn-guardar-odt');
    const msg = document.getElementById('msg-odt');

    // Texto escrito sin elegir una opción: se avisa en lugar de quitar la OT sin querer
    if (document.getElementById('buscar-odt').value.trim() !== '' && !document.getElementById('id_odt').value) {
        msg.style.color = '#991b1b';
        msg.innerText = 'Elija una Orden de Trabajo de la lista o deje el campo vacío para dejar el movimiento sin OT.';
        return;
    }

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

