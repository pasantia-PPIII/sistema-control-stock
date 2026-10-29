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
// También se consultan los insumos deshabilitados o dados de baja: su historial se conserva (RF-10)
$insumo = $codigo !== '' ? $insumoModel->getByCodigoAny($codigo) : null;

if (!$insumo) {
    if ($esAjax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => "No se encontró el insumo con código '{$codigo}'."]);
        exit;
    }
    setAlert('El insumo solicitado no existe.', 'warning');
    redirect('insumo/dashboard.php');
}

if ($esAjax) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => true, 'data' => $insumo, 'movimientos' => $insumoModel->getMovimientos($insumo['codigo'])]);
    exit;
}

// Kardex (todos los movimientos) e historial de asignaciones, paginados de a 10 (?pag_kardex=N y ?pag_hist=N)
$kardex = $insumoModel->getKardexPaginado($insumo['codigo'], (int)($_GET['pag_kardex'] ?? 1), 10);

$esHerramienta = (!empty($insumo['nombre_tipo']) && stripos($insumo['nombre_tipo'], 'herramienta') !== false);

// Herramienta: quién la tiene hoy (RF-19) y todas sus asignaciones (RF-20)
$asignacion = null;
$historial = null;
if ($esHerramienta) {
    $asignacion = (new Movimiento())->getAsignacionActual((int)$insumo['id_insumo']);
    $historial = $insumoModel->getHistorialAsignacionesPaginado($insumo['codigo'], (int)($_GET['pag_hist'] ?? 1), 10);
}
$stockActual = (float)($insumo['stock_actual'] ?? 0);
$stockMinimo = (float)($insumo['stock_minimo'] ?? 0);
$stockBajo = !$esHerramienta && $stockActual <= $stockMinimo; // mismo criterio que Reportes::stockCritico()
$perecedero = !empty($insumo['es_perecedero']) && $insumo['es_perecedero'] !== 'f';

$seccion = 'insumo';
$css_modulo = 'insumos';
$titulo_vista = 'Detalle de Insumo';
$titulo_pagina = 'Insumo ' . $insumo['codigo'] . ' - Sistema de Control de Stock';

include_once __DIR__ . '/../layout/header.php';

$h = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
?>

<div class="ficha">
    <div class="ficha__encabezado">
        <div>
            <h2 class="ficha__titulo"><?= $h($insumo['nombre']) ?></h2>
            <p class="ficha__subtitulo">Código <strong><?= $h($insumo['codigo']) ?></strong> - <?= $h($insumo['nombre_tipo'] ?? 'Material') ?></p>
        </div>
        <a href="dashboard.php" class="btn-modal-back ficha__btn-volver">Volver a Insumos</a>
    </div>

    <?php if ($stockBajo): ?>
        <div class="ficha__alerta">
            Stock en o por debajo del mínimo configurado (<?= $stockActual ?> disponible, mínimo <?= $stockMinimo ?>).
        </div>
    <?php endif; ?>

    <div class="details-list">
        <?php if (!empty($insumo['descripcion'])): ?>
            <div class="detail-item"><strong>Descripción:</strong> <?= $h($insumo['descripcion']) ?></div>
        <?php endif; ?>
        <div class="detail-item"><strong>Rubro:</strong> <?= $h($insumo['nombre_rubro'] ?? '-') ?></div>
        <div class="detail-item"><strong>Ubicación:</strong> <?= $h($insumo['nombre_ubicacion'] ?? 'Sin ubicación asignada') ?></div>
        <?php if ($esHerramienta): ?>
            <div class="detail-item"><strong>Serie / Modelo:</strong> <?= !empty($insumo['serie_modelo']) ? $h($insumo['serie_modelo']) : '-' ?></div>
            <div class="detail-item"><strong>Estado de la herramienta:</strong> <?= $h($insumo['nombre_estado_herramienta'] ?? 'Sin estado') ?></div>
            <?php if ($asignacion): ?>
                <div class="detail-item"><strong>En poder de:</strong>
                    <?= enlaceOperario($asignacion['id_operario'], $asignacion['nombre_operario']) ?>
                    (desde el <?= $h(date('d/m/Y', strtotime($asignacion['fecha']))) ?>)
                </div>
            <?php elseif (($insumo['nombre_estado_herramienta'] ?? '') === 'Prestada'): ?>
                <div class="detail-item"><strong>En poder de:</strong> sin registro de la entrega (figura prestada)</div>
            <?php endif; ?>
            <?php if (!empty($insumo['motivo_baja'])): ?>
                <div class="detail-item"><strong>Motivo de la baja:</strong> <?= $h($insumo['motivo_baja']) ?><?= !empty($insumo['fecha_baja']) ? ' (' . $h(date('d/m/Y', strtotime($insumo['fecha_baja']))) . ')' : '' ?></div>
                <?php if (!empty($insumo['observacion_baja'])): ?>
                    <div class="detail-item"><strong>Observaciones de la baja:</strong> <?= $h($insumo['observacion_baja']) ?></div>
                <?php endif; ?>
            <?php endif; ?>
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

    <?php if ($esHerramienta): ?>
        <h3 class="ficha__subencabezado" id="historial-asignaciones">Historial de asignaciones (<?= (int)$historial['total'] ?>)</h3>
        <div class="ficha__tabla-wrap">
            <table class="ficha__tabla">
                <thead>
                    <tr>
                        <th>Operario</th>
                        <th>Entrega</th>
                        <th>Devolución</th>
                        <th>Orden de trabajo</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($historial['filas'])): ?>
                        <tr><td colspan="4" class="ficha__vacio">Esta herramienta todavía no fue entregada a ningún operario.</td></tr>
                    <?php else: ?>
                        <?php foreach ($historial['filas'] as $a): ?>
                            <tr>
                                <td><?= enlaceOperario($a['id_operario'] ?? null, $a['nombre_operario'] ?? null) ?></td>
                                <td>
                                    <a href="../movimiento/detalle_movimiento.php?id=<?= (int)$a['id_movimiento'] ?>"><?= $h(date('d/m/Y', strtotime($a['fecha_entrega']))) ?></a>
                                    <?= $h(substr($a['hora_entrega'], 0, 5)) ?>
                                </td>
                                <td>
                                    <?php if (!empty($a['fecha_devolucion'])): ?>
                                        <a href="../movimiento/detalle_movimiento.php?id=<?= (int)$a['id_mov_devolucion'] ?>"><?= $h(date('d/m/Y', strtotime($a['fecha_devolucion']))) ?></a>
                                        <?= $h(substr($a['hora_devolucion'], 0, 5)) ?>
                                    <?php else: ?>
                                        <span class="texto-tenue">Sin devolver</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($a['id_odt'])): ?>
                                        <a href="../orden_de_trabajo/detalle_orden_de_trabajo.php?id=<?= (int)$a['id_odt'] ?>"><?= $h($a['numero_ot']) ?></a>
                                    <?php else: ?>-<?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="pagination-container" data-total="<?= (int)$historial['paginas'] ?>" data-actual="<?= (int)$historial['pagina'] ?>"
            data-param="pag_hist" data-ancla="historial-asignaciones"></div>
    <?php endif; ?>

    <h3 class="ficha__subencabezado" id="kardex">Historial de movimientos - kardex (<?= (int)$kardex['total'] ?>)</h3>
    <div class="ficha__tabla-wrap">
        <table class="ficha__tabla">
            <thead>
                <tr>
                    <th>N°</th>
                    <th>Fecha y Hora</th>
                    <th>Tipo</th>
                    <th>Operario</th>
                    <th>Registró</th>
                    <th>OT</th>
                    <th class="col-der">Cantidad</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($kardex['filas'])): ?>
                    <tr><td colspan="7" class="ficha__vacio">Este insumo todavía no registra movimientos.</td></tr>
                <?php else: ?>
                    <?php foreach ($kardex['filas'] as $m): ?>
                        <tr>
                            <td><a href="../movimiento/detalle_movimiento.php?id=<?= (int)$m['id_movimiento'] ?>">#<?= (int)$m['id_movimiento'] ?></a></td>
                            <td><?= $h(date('d/m/Y', strtotime($m['fecha']))) ?> <?= $h(substr($m['hora'], 0, 5)) ?></td>
                            <td><?= $h($m['nombre_tipo_movimiento']) ?></td>
                            <td><?= enlaceOperario($m['id_operario'] ?? null, $m['nombre_operario'] ?? null) ?></td>
                            <td><?= $h($m['nombre_usuario'] ?? '-') ?></td>
                            <td>
                                <?php if (!empty($m['id_odt'])): ?>
                                    <a href="../orden_de_trabajo/detalle_orden_de_trabajo.php?id=<?= (int)$m['id_odt'] ?>"><?= $h($m['numero_ot']) ?></a>
                                <?php else: ?>-<?php endif; ?>
                            </td>
                            <td class="col-der col-cantidad"><?= (float)$m['cantidad'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="pagination-container" data-total="<?= (int)$kardex['paginas'] ?>" data-actual="<?= (int)$kardex['pagina'] ?>"
        data-param="pag_kardex" data-ancla="kardex"></div>
</div>

<?php
include_once __DIR__ . '/../layout/footer.php';
?>
