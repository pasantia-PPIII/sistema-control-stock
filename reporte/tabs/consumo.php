<?php
// reporte/tabs/consumo.php
// Pestaña "Consumo". Se incluye desde reporte/dashboard.php.
// Consumo de un material = Entregas - Devoluciones (movimientos activos). Las herramientas no cuentan.
$linkInsumo = fn($codigo) => '../insumo/detalles_insumo.php?codigo=' . rawurlencode((string) $codigo);
/** Barra horizontal proporcional al máximo de una lista (campo $campo). */
$maximo = function (array $filas, string $campo): float {
    $m = 0.0;
    foreach ($filas as $f) {
        $m = max($m, (float) $f[$campo]);
    }
    return $m;
};
$rotuloRango = Reportes::RANGOS[$rango][0];

// El máximo de cada barra se calcula sobre la lista completa (en la base, ver *Paginado()), para que las proporciones no cambien al paginar
$mxRubro = $maximo($porRubro, 'neto');
$mxJur = $maximo($porJurisdiccion, 'neto');
$mxOper = $pgOpe['maximo'];
$mxOrden = $pgOrd['maximo'];
$mxTop = $pgTop['maximo'];

$pgRub = rp_paginar($porRubro, 'rub');
$pgJur = rp_paginar($porJurisdiccion, 'jur');
?>

<?php include __DIR__ . '/_rango.php'; ?>

<!-- MÁS CONSUMIDOS -->
<section class="rp-card" id="sec-top">
    <header class="rp-card__cab">
        <h2>Materiales más consumidos</h2>
        <p class="rp-nota">Ordenados por consumo neto (entregado menos devuelto). Período: <?= $h($rotuloRango) ?>.</p>
    </header>
    <div class="rp-tabla-wrap">
        <table class="rp-tabla">
            <thead>
                <tr>
                    <th>Código</th>
                    <th>Material</th>
                    <th class="num">Entregado</th>
                    <th class="num">Devuelto</th>
                    <th class="rp-col-barra">Consumo neto</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($pgTop['filas'])): ?>
                    <tr><td colspan="5" class="rp-vacio">No hubo consumo en este período.</td></tr>
                <?php else: ?>
                    <?php foreach ($pgTop['filas'] as $t): ?>
                        <tr>
                            <td><a href="<?= $h($linkInsumo($t['codigo'])) ?>"><strong><?= $h($t['codigo']) ?></strong></a></td>
                            <td><?= $h($t['nombre']) ?></td>
                            <td class="num"><?= $h(rp_num($t['entregado'])) ?></td>
                            <td class="num"><?= $h(rp_num($t['devuelto'])) ?></td>
                            <td class="rp-col-barra">
                                <span class="rp-celda-barra">
                                    <span class="rp-barras__pista"><span class="rp-barras__rel" style="width: <?= rp_pct($t['neto'], $mxTop) ?>%"></span></span>
                                    <strong><?= $h(rp_num($t['neto'])) ?></strong> <small><?= $h($t['unidad']) ?></small>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php rp_paginador($pgTop); ?>
</section>

<div class="rp-dos-col">
    <!-- POR RUBRO -->
    <section class="rp-card" id="sec-rub">
        <header class="rp-card__cab">
            <h2>Consumo por rubro</h2>
            <p class="rp-nota">Unidades netas entregadas por rubro del material. Período: <?= $h($rotuloRango) ?>.</p>
        </header>
        <?php if (empty($pgRub['filas'])): ?>
            <p class="rp-vacio">No hubo consumo en este período.</p>
        <?php else: ?>
            <ul class="rp-barras">
                <?php foreach ($pgRub['filas'] as $r): ?>
                    <li title="<?= $h($r['movimientos'] . ' movimientos · entregado ' . rp_num($r['entregado']) . ' · devuelto ' . rp_num($r['devuelto'])) ?>">
                        <span class="rp-barras__etq"><?= $h($r['rubro']) ?></span>
                        <span class="rp-barras__pista"><span class="rp-barras__rel" style="width: <?= rp_pct($r['neto'], $mxRubro) ?>%"></span></span>
                        <span class="rp-barras__val"><?= $h(rp_num($r['neto'])) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    <?php rp_paginador($pgRub); ?>
    </section>

    <!-- POR JURISDICCIÓN -->
    <section class="rp-card" id="sec-jur">
        <header class="rp-card__cab">
            <h2>Consumo por jurisdicción</h2>
            <p class="rp-nota">Según la jurisdicción de la orden de trabajo a la que se entregó el material. Período: <?= $h($rotuloRango) ?>.</p>
        </header>
        <?php if (empty($pgJur['filas'])): ?>
            <p class="rp-vacio">No hubo consumo en este período.</p>
        <?php else: ?>
            <ul class="rp-barras">
                <?php foreach ($pgJur['filas'] as $j): ?>
                    <li title="<?= $h($j['movimientos'] . ' movimientos') ?>">
                        <span class="rp-barras__etq"><?= $h($j['jurisdiccion']) ?></span>
                        <span class="rp-barras__pista"><span class="rp-barras__rel" style="width: <?= rp_pct($j['neto'], $mxJur) ?>%"></span></span>
                        <span class="rp-barras__val"><?= $h(rp_num($j['neto'])) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    <?php rp_paginador($pgJur); ?>
    </section>
</div>

<div class="rp-dos-col">
    <!-- POR OPERARIO -->
    <section class="rp-card" id="sec-ope">
        <header class="rp-card__cab">
            <h2>Consumo por operario</h2>
            <p class="rp-nota">Operarios que recibieron material, de mayor a menor (neto). Período: <?= $h($rotuloRango) ?>.</p>
        </header>
        <?php if (empty($pgOpe['filas'])): ?>
            <p class="rp-vacio">No hubo consumo en este período.</p>
        <?php else: ?>
            <ul class="rp-barras">
                <?php foreach ($pgOpe['filas'] as $o): ?>
                    <li title="<?= $h($o['movimientos'] . ' movimientos') ?>">
                        <span class="rp-barras__etq"><?= $h($o['operario']) ?></span>
                        <span class="rp-barras__pista"><span class="rp-barras__rel" style="width: <?= rp_pct($o['neto'], $mxOper) ?>%"></span></span>
                        <span class="rp-barras__val"><?= $h(rp_num($o['neto'])) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    <?php rp_paginador($pgOpe); ?>
    </section>

    <!-- POR ORDEN -->
    <section class="rp-card" id="sec-ord">
        <header class="rp-card__cab">
            <h2>Consumo por orden de trabajo</h2>
            <p class="rp-nota">Órdenes que más material consumieron. Solo movimientos asociados a una orden. Período: <?= $h($rotuloRango) ?>.</p>
        </header>
        <?php if (empty($pgOrd['filas'])): ?>
            <p class="rp-vacio">No hubo consumo asociado a órdenes en este período.</p>
        <?php else: ?>
            <ul class="rp-barras">
                <?php foreach ($pgOrd['filas'] as $o): ?>
                    <li title="<?= $h($o['estado'] . ' · ' . $o['movimientos'] . ' movimientos') ?>">
                        <span class="rp-barras__etq"><?= $h($o['numero_ot']) ?></span>
                        <span class="rp-barras__pista"><span class="rp-barras__rel" style="width: <?= rp_pct($o['neto'], $mxOrden) ?>%"></span></span>
                        <span class="rp-barras__val"><?= $h(rp_num($o['neto'])) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    <?php rp_paginador($pgOrd); ?>
    </section>
</div>

<!-- AJUSTES DE INVENTARIO -->
<section class="rp-card" id="sec-aju">
    <header class="rp-card__cab">
        <h2>Ajustes de inventario</h2>
        <p class="rp-nota">Materiales con más ajustes en el período (<?= $h($rotuloRango) ?>). Un ajuste corrige el stock registrado al valor contado, así que repetirse sobre
            un mismo material indica diferencias frecuentes entre el stock real y el del sistema (mermas, errores de carga o faltantes).</p>
    </header>
    <div class="rp-tabla-wrap">
        <table class="rp-tabla">
            <thead>
                <tr>
                    <th>Código</th>
                    <th>Material</th>
                    <th class="num">Ajustes</th>
                    <th>Último ajuste</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($pgAju['filas'])): ?>
                    <tr><td colspan="4" class="rp-vacio">No hubo ajustes de inventario en este período.</td></tr>
                <?php else: ?>
                    <?php foreach ($pgAju['filas'] as $a): ?>
                        <tr>
                            <td><a href="<?= $h($linkInsumo($a['codigo'])) ?>"><strong><?= $h($a['codigo']) ?></strong></a></td>
                            <td><?= $h($a['nombre']) ?></td>
                            <td class="num"><?= (int) $a['ajustes'] ?></td>
                            <td><?= $h(rp_fecha($a['ultimo_ajuste'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php rp_paginador($pgAju); ?>
</section>
