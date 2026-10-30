<?php
/**
 * panel/dashboard.php
 * Panel de Control: resumen de lo más importante según el rol del usuario.
 *
 * - admin:    todo el sistema (inventario, órdenes, movimientos, operarios, rubros y usuarios por rol).
 * - panolero: operación diaria del pañol (stock bajo, vencimientos, herramientas, movimientos y órdenes).
 * - vista:    resumen general de solo lectura.
 */

require_once __DIR__ . '/../config/config.php';
if (!isAuthenticated()) {
    redirect('auth/login.php');
}

require_once __DIR__ . '/../clases/panel.php';
require_once __DIR__ . '/../clases/insumo.php';
require_once __DIR__ . '/../clases/orden_de_trabajo.php';

$seccion = 'panel';
$css_modulo = 'panel';
$titulo_vista = 'Panel de Control';
$titulo_pagina = 'Panel de Control - Sistema de Control de Stock';

$panel = new Panel();
$rol = currentRole();
$esAdmin = isAdmin();
$esPanolero = ($rol === 'panolero');

$etiquetasRol = ['admin' => 'Administrador', 'panolero' => 'Pañolero', 'vista' => 'Vista (solo lectura)'];
$etiquetaRol = $etiquetasRol[$rol] ?? 'Usuario';

try {
    // Cada tarjeta lleva al listado con el filtro que reproduce su número (ver los filtros de cada dashboard)
    $herramientas = '../insumo/dashboard.php?tipo=' . Insumo::ID_TIPO_HERRAMIENTA . '&estado_herr=';
    $kpis = [
        ['Insumos activos', $panel->totalInsumosActivos(), '../insumo/dashboard.php?estado=activo', ''],
        ['Con stock bajo', $panel->totalStockBajo(), '../insumo/dashboard.php?stock=bajo', 'alerta'],
        ['Herramientas prestadas', $panel->totalHerramientasPrestadas(), $herramientas . Movimiento::ESTADO_PRESTADA, ''],
        ['Movimientos de hoy', $panel->totalMovimientosHoy(), '../movimiento/dashboard.php?fecha=hoy', ''],
    ];

    $ordenesPorEstado = $panel->ordenesPorEstado();
    $abiertas = 0;
    foreach ($ordenesPorEstado as $estado => $cantidad) {
        if (!in_array($estado, ['Finalizada', 'Anulada'], true)) {
            $abiertas += $cantidad;
        }
    }
    $kpis[] = ['Órdenes de trabajo abiertas', $abiertas, '../orden_de_trabajo/dashboard.php?estado=' . OrdenDeTrabajo::FILTRO_ABIERTAS, ''];

    if ($esAdmin) {
        $kpis[] = ['Operarios activos', $panel->totalOperariosActivos(), '../operario/dashboard.php?estado=activo', ''];
        $kpis[] = ['Rubros activos', $panel->totalRubrosActivos(), '../rubro/dashboard.php?estado=activo', ''];
        $kpis[] = ['Usuarios inactivos', $panel->totalUsuariosInactivos(), '../usuario/dashboard.php?estado=inactivo', ''];
    }
    if ($esPanolero) {
        $kpis[] = ['Herramientas en reparación', $panel->totalHerramientasEnReparacion(), $herramientas . Insumo::ESTADO_EN_REPARACION, ''];
    }

    $stockBajo = $panel->stockBajo(5);
    $ultimosMovimientos = $panel->ultimosMovimientos(5);
    $ordenesAbiertas = $panel->ordenesAbiertas(5);
    $vencimientos = ($esAdmin || $esPanolero) ? $panel->proximosVencimientos(30, 5) : [];
    $usuariosPorRol = $esAdmin ? $panel->usuariosPorRol() : [];
    $errorPanel = null;
} catch (Throwable $e) {
    error_log('Panel: ' . $e->getMessage());
    $kpis = $stockBajo = $ultimosMovimientos = $ordenesAbiertas = $vencimientos = $usuariosPorRol = [];
    $ordenesPorEstado = [];
    $errorPanel = 'No se pudo cargar el resumen. Intente nuevamente.';
}

include_once __DIR__ . '/../layout/header.php';

$h = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$fecha = fn($f) => !empty($f) ? date('d/m/Y', strtotime($f)) : '-';

// Clases de color (definidas en panel.css) según el título del KPI, el estado de la OT y el tipo de movimiento.
$colorKpi = [
    'Insumos activos'             => 'blue',
    'Con stock bajo'              => 'red',
    'Herramientas prestadas'      => 'amber',
    'Movimientos de hoy'          => 'indigo',
    'Órdenes de trabajo abiertas' => 'green',
    'Operarios activos'           => 'blue',
    'Rubros activos'              => 'green',
    'Usuarios inactivos'          => 'red',
    'Herramientas en reparación'  => 'amber',
];
$claseEstadoOt = [
    'Pendiente'   => 'pendiente',
    'En Proceso'  => 'proceso',
    'En Progreso' => 'proceso',
    'Pausada'     => 'pausada',
    'Finalizada'  => 'finalizada',
    'Anulada'     => 'anulada',
];
$claseMovimiento = [
    'Entrega'              => 'entrega',
    'Devolución'           => 'devolucion',
    'Ingreso'              => 'ingreso',
    'Ajuste de Inventario' => 'ajuste',
];
?>

<p class="panel-rol">Resumen para el perfil <strong><?= $h($etiquetaRol) ?></strong></p>

<?php if ($esAdmin || $esPanolero): ?>
<div class="panel-accesos">
    <a href="../movimiento/crear_movimiento.php?tipo=entrega" class="panel-acceso panel-acceso--blue">
        <span class="panel-acceso-icono"><?= iconoAccion('dispatch', 16) ?></span>
        <span>Nueva entrega</span>
    </a>
    <a href="../movimiento/crear_movimiento.php?tipo=devolucion" class="panel-acceso panel-acceso--green">
        <span class="panel-acceso-icono"><?= iconoAccion('return', 16) ?></span>
        <span>Nueva devolución</span>
    </a>
    <a href="../insumo/dashboard.php" class="panel-acceso panel-acceso--amber">
        <span class="panel-acceso-icono"><?= iconoAccion('box', 16) ?></span>
        <span>Gestionar insumos</span>
    </a>
    <a href="../orden_de_trabajo/dashboard.php" class="panel-acceso panel-acceso--indigo">
        <span class="panel-acceso-icono"><?= iconoAccion('clipboard', 16) ?></span>
        <span>Órdenes de trabajo</span>
    </a>
</div>
<?php endif; ?>

<?php if ($errorPanel): ?>
    <div class="panel-error"><?= $h($errorPanel) ?></div>
<?php endif; ?>

<div class="panel-kpis">
    <?php foreach ($kpis as [$titulo, $valor, $enlace, $clase]): ?>
        <a class="panel-kpi panel-kpi--<?= $h($colorKpi[$titulo] ?? 'blue') ?> <?= $h($clase) ?><?= ($clase === 'alerta' && $valor > 0) ? ' activo' : '' ?>" href="<?= $h($enlace) ?>">
            <span class="panel-kpi-valor"><?= (int)$valor ?></span>
            <span class="panel-kpi-titulo"><?= $h($titulo) ?></span>
        </a>
    <?php endforeach; ?>
</div>

<div class="panel-grid">

    <section class="panel-card">
        <h2>Materiales con stock bajo</h2>
        <?php if (empty($stockBajo)): ?>
            <p class="panel-vacio">No hay materiales por debajo del stock mínimo.</p>
        <?php else: ?>
            <table class="panel-tabla">
                <thead><tr><th>Código</th><th>Material</th><th class="num">Stock</th><th class="num">Mínimo</th></tr></thead>
                <tbody>
                <?php foreach ($stockBajo as $i): ?>
                    <tr>
                        <td><a href="../insumo/detalles_insumo.php?codigo=<?= rawurlencode($i['codigo']) ?>"><?= $h($i['codigo']) ?></a></td>
                        <td><?= $h($i['nombre']) ?></td>
                        <td class="num"><strong><?= (float)$i['stock_actual'] ?></strong> <?= $h($i['unidad_medida'] ?? '') ?></td>
                        <td class="num"><?= (float)$i['stock_minimo'] ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>

    <section class="panel-card">
        <h2>Órdenes de trabajo</h2>
        <?php if (!empty($ordenesPorEstado)): ?>
            <?php $totalOrdenes = array_sum($ordenesPorEstado); ?>
            <div class="panel-barras">
                <?php foreach ($ordenesPorEstado as $estado => $cantidad):
                    $porc = $totalOrdenes > 0 ? round(($cantidad / $totalOrdenes) * 100) : 0;
                ?>
                    <div class="panel-barra-fila">
                        <span class="panel-barra-label"><?= $h($estado) ?></span>
                        <div class="panel-barra-track">
                            <div class="panel-barra-fill panel-barra-fill--<?= $h($claseEstadoOt[$estado] ?? 'pausada') ?>" style="--ancho: <?= (int)$porc ?>%;"></div>
                        </div>
                        <span class="panel-barra-valor"><?= (int)$cantidad ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="panel-chips">
                <?php foreach ($ordenesPorEstado as $estado => $cantidad): ?>
                    <span class="panel-chip panel-chip--<?= $h($claseEstadoOt[$estado] ?? 'pausada') ?>"><?= $h($estado) ?>: <strong><?= (int)$cantidad ?></strong></span>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <?php if (empty($ordenesAbiertas)): ?>
            <p class="panel-vacio">No hay órdenes de trabajo abiertas.</p>
        <?php else: ?>
            <table class="panel-tabla">
                <thead><tr><th>OT</th><th>Estado</th><th>Localidad</th><th>Finaliza</th></tr></thead>
                <tbody>
                <?php foreach ($ordenesAbiertas as $o): ?>
                    <tr>
                        <td><a href="../orden_de_trabajo/detalle_orden_de_trabajo.php?id=<?= (int)$o['id_odt'] ?>"><?= $h($o['numero_ot']) ?></a></td>
                        <td><?= $h($o['estado'] ?? 'Pendiente') ?></td>
                        <td><?= $h($o['nombre_localidad'] ?? '-') ?></td>
                        <td><?= $h($fecha($o['fecha_final'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>

    <section class="panel-card">
        <h2>Últimos movimientos</h2>
        <?php if (empty($ultimosMovimientos)): ?>
            <p class="panel-vacio">Todavía no hay movimientos registrados.</p>
        <?php else: ?>
            <table class="panel-tabla">
                <thead><tr><th>N°</th><th>Fecha</th><th>Tipo</th><th>Operario</th><th class="num">Ítems</th></tr></thead>
                <tbody>
                <?php foreach ($ultimosMovimientos as $m): ?>
                    <tr>
                        <td><a href="../movimiento/detalle_movimiento.php?id=<?= (int)$m['id_movimiento'] ?>">#<?= (int)$m['id_movimiento'] ?></a></td>
                        <td><?= $h($fecha($m['fecha'])) ?> <?= $h(substr($m['hora'], 0, 5)) ?></td>
                        <td><span class="panel-badge-mov panel-badge-mov--<?= $h($claseMovimiento[$m['nombre_tipo_movimiento']] ?? 'otro') ?>"><?= $h($m['nombre_tipo_movimiento']) ?></span></td>
                        <td><?= enlaceOperario($m['id_operario'] ?? null, $m['nombre_operario'] ?? null) ?></td>
                        <td class="num"><?= (int)$m['total_items'] ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>

    <?php if ($esAdmin || $esPanolero): ?>
    <section class="panel-card">
        <h2>Vencimientos próximos (30 días)</h2>
        <?php if (empty($vencimientos)): ?>
            <p class="panel-vacio">No hay materiales por vencer.</p>
        <?php else: ?>
            <table class="panel-tabla">
                <thead><tr><th>Código</th><th>Material</th><th>Vence</th></tr></thead>
                <tbody>
                <?php foreach ($vencimientos as $v):
                    $vencido = strtotime($v['fecha_vencimiento']) < strtotime('today');
                ?>
                    <tr class="<?= $vencido ? 'panel-fila-vencida' : '' ?>">
                        <td><a href="../insumo/detalles_insumo.php?codigo=<?= rawurlencode($v['codigo']) ?>"><?= $h($v['codigo']) ?></a></td>
                        <td><?= $h($v['nombre']) ?></td>
                        <td><?= $h($fecha($v['fecha_vencimiento'])) ?><?= $vencido ? ' <span class="panel-vencido">vencido</span>' : '' ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>
    <?php endif; ?>

    <?php if ($esAdmin): ?>
    <section class="panel-card">
        <h2>Usuarios activos por rol</h2>
        <table class="panel-tabla">
            <thead><tr><th>Rol</th><th class="num">Usuarios</th></tr></thead>
            <tbody>
            <?php foreach ($usuariosPorRol as $r): ?>
                <tr>
                    <td><?= $h($r['rol']) ?></td>
                    <td class="num"><strong><?= (int)$r['total'] ?></strong></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </section>
    <?php endif; ?>

</div>

<?php
include_once __DIR__ . '/../layout/footer.php';
?>
