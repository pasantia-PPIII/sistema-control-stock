<?php
// reporte/tabs/herramientas.php
// Pestaña "Herramientas". Se incluye desde reporte/dashboard.php.
$linkInsumo = fn($codigo) => '../insumo/detalles_insumo.php?codigo=' . rawurlencode((string) $codigo);
$totalHtas = array_sum($htasPorEstado);
$claseEstado = [
    Reportes::HTA_DISPONIBLE => 'rp-pastilla--ok',
    Reportes::HTA_PRESTADA => 'rp-pastilla--aviso',
    Reportes::HTA_REPARACION => 'rp-pastilla--aviso',
    Reportes::HTA_BAJA => 'rp-pastilla--neutra',
];

$pgOper = rp_paginar($htasPorOperario, 'hop');
?>

<!-- ESTADO DEL PARQUE -->
<section class="rp-card">
    <header class="rp-card__cab">
        <h2>Estado de las herramientas</h2>
        <p class="rp-nota">Herramientas activas según su estado actual. Cambia solo con los movimientos de Entrega y Devolución.</p>
    </header>
    <div class="rp-estados">
        <div class="rp-estado rp-estado--total">
            <span class="rp-estado__num"><?= (int) $totalHtas ?></span>
            <span class="rp-estado__txt">Total</span>
        </div>
        <?php foreach ($htasPorEstado as $estado => $cantidad): ?>
            <div class="rp-estado">
                <span class="rp-estado__num"><?= (int) $cantidad ?></span>
                <span class="rp-pastilla <?= $claseEstado[$estado] ?? 'rp-pastilla--neutra' ?>"><?= $h($estado) ?></span>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<!-- PRESTADAS -->
<section class="rp-card" id="sec-prest">
    <header class="rp-card__cab">
        <h2>Herramientas prestadas</h2>
        <p class="rp-nota">Quién tiene cada herramienta y desde cuándo (última entrega vigente). Las de mayor antigüedad primero.</p>
    </header>
    <div class="rp-tabla-wrap">
        <table class="rp-tabla">
            <thead>
                <tr>
                    <th>Código</th>
                    <th>Herramienta</th>
                    <th>Operario</th>
                    <th>Entregada</th>
                    <th class="num">Antigüedad</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($pgPrest['filas'])): ?>
                    <tr><td colspan="5" class="rp-vacio">No hay herramientas prestadas. Todas están en el pañol o fuera de servicio.</td></tr>
                <?php else: ?>
                    <?php foreach ($pgPrest['filas'] as $t):
                        $dias = $t['dias_prestada'] === null ? null : (int) $t['dias_prestada'];
                        $clase = $dias === null ? 'rp-pastilla--neutra' : ($dias > 15 ? 'rp-pastilla--alerta' : ($dias > 7 ? 'rp-pastilla--aviso' : 'rp-pastilla--ok'));
                        ?>
                        <tr>
                            <td><a href="<?= $h($linkInsumo($t['codigo'])) ?>"><strong><?= $h($t['codigo']) ?></strong></a></td>
                            <td><?= $h($t['nombre']) ?></td>
                            <td><?= $h($t['operario']) ?></td>
                            <td><?= $h(rp_fecha($t['fecha_entrega'])) ?></td>
                            <td class="num">
                                <span class="rp-pastilla <?= $clase ?>"><?= $dias === null ? 'Sin dato' : $h(rp_dias($dias)) ?></span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php rp_paginador($pgPrest); ?>
</section>

<!-- POR OPERARIO -->
<section class="rp-card" id="sec-hop">
    <header class="rp-card__cab">
        <h2>Herramientas por operario</h2>
        <p class="rp-nota">Cuántas herramientas tiene prestadas cada operario y el préstamo más antiguo.</p>
    </header>
    <div class="rp-tabla-wrap">
        <table class="rp-tabla">
            <thead>
                <tr>
                    <th>Operario</th>
                    <th class="num">Herramientas</th>
                    <th class="num">Préstamo más antiguo</th>
                    <th>Detalle</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($pgOper['filas'])): ?>
                    <tr><td colspan="4" class="rp-vacio">Ningún operario tiene herramientas prestadas.</td></tr>
                <?php else: ?>
                    <?php foreach ($pgOper['filas'] as $o): ?>
                        <tr>
                            <td><strong><?= $h($o['operario']) ?></strong></td>
                            <td class="num"><?= (int) $o['cantidad'] ?></td>
                            <td class="num"><?= $o['max_dias'] === null ? '-' : $h(rp_dias((int) $o['max_dias'])) ?></td>
                            <td class="rp-suave"><?= $h(implode(', ', $o['herramientas'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php rp_paginador($pgOper); ?>
</section>
