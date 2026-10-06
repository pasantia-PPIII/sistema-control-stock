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
    $kpis = [
        ['Insumos activos', $panel->totalInsumosActivos(), '../insumo/dashboard.php', ''],
        ['Con stock bajo', $panel->totalStockBajo(), '../insumo/dashboard.php', 'alerta'],
        ['Herramientas prestadas', $panel->totalHerramientasPrestadas(), '../insumo/dashboard.php', ''],
        ['Movimientos de hoy', $panel->totalMovimientosHoy(), '../movimiento/dashboard.php', ''],
    ];

    $ordenesPorEstado = $panel->ordenesPorEstado();
    $abiertas = 0;
    foreach ($ordenesPorEstado as $estado => $cantidad) {
        if (!in_array($estado, ['Finalizada', 'Anulada'], true)) {
            $abiertas += $cantidad;
        }
    }
    $kpis[] = ['Órdenes de trabajo abiertas', $abiertas, '../orden_de_trabajo/dashboard.php', ''];

    if ($esAdmin) {
        $kpis[] = ['Operarios activos', $panel->totalOperariosActivos(), '../operario/dashboard.php', ''];
        $kpis[] = ['Rubros activos', $panel->totalRubrosActivos(), '../rubro/dashboard.php', ''];
        $kpis[] = ['Usuarios inactivos', $panel->totalUsuariosInactivos(), '../usuario/dashboard.php', ''];
    }
    if ($esPanolero) {
        $kpis[] = ['Herramientas en reparación', $panel->totalHerramientasEnReparacion(), '../insumo/dashboard.php', ''];
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
?>

<p class="panel-rol">Resumen para el perfil <strong><?= $h($etiquetaRol) ?></strong></p>

<?php if ($errorPanel): ?>
    <div class="panel-error"><?= $h($errorPanel) ?></div>
<?php endif; ?>

<div class="panel-kpis">
    <?php foreach ($kpis as [$titulo, $valor, $enlace, $clase]): ?>
        <a class="panel-kpi <?= $h($clase) ?><?= ($clase === 'alerta' && $valor > 0) ? ' activo' : '' ?>" href="<?= $h($enlace) ?>">
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
            <div class="panel-chips">
                <?php foreach ($ordenesPorEstado as $estado => $cantidad): ?>
                    <span class="panel-chip"><?= $h($estado) ?>: <strong><?= (int)$cantidad ?></strong></span>
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
                        <td><?= $h($m['nombre_tipo_movimiento']) ?></td>
                        <td><?= !empty($m['nombre_operario']) ? $h($m['nombre_operario']) : '-' ?></td>
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
                    <tr>
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
