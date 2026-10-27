<?php
// reporte/tabs/ordenes.php
// Pestaña "Órdenes de trabajo". Se incluye desde reporte/dashboard.php.
$linkInsumo = fn($codigo) => '../insumo/detalles_insumo.php?codigo=' . rawurlencode((string) $codigo);
$maxOrdenes = $ordenesPeriodo ? max($ordenesPeriodo) : 0;

$pgVencidas = rp_paginar($otVencidas, 'ovenc');
$pgSinDev = rp_paginar($htasSinDevolver, 'sdev');
?>

<!-- POR ESTADO -->
<section class="rp-card">
    <header class="rp-card__cab">
        <h2>Órdenes por estado</h2>
        <p class="rp-nota">Órdenes de trabajo activas (no deshabilitadas) agrupadas por su estado. El período filtra por fecha de inicio.</p>
    </header>
    <div class="rp-rango">
        <span class="rp-rango__titulo">Período:</span>
        <?php foreach (Reportes::RANGOS_ORDENES as $clave => [$etiqueta]): ?>
            <a href="<?= $h(rp_url(['rango_ot' => $clave === 'todo' ? null : $clave])) ?>" class="rp-chip <?= $rangoOt === $clave ? 'rp-chip--activo' : '' ?>"
                <?= $rangoOt === $clave ? 'aria-current="true"' : '' ?>><?= $h($etiqueta) ?></a>
        <?php endforeach; ?>
    </div>
    <?php if (empty($ordenesPeriodo)): ?>
        <p class="rp-vacio">No hay órdenes de trabajo en este período.</p>
    <?php else: ?>
        <ul class="rp-barras">
            <?php foreach ($ordenesPeriodo as $estado => $cantidad): ?>
                <li>
                    <span class="rp-barras__etq"><?= $h($estado) ?></span>
                    <span class="rp-barras__pista"><span class="rp-barras__rel" style="width: <?= rp_pct($cantidad, $maxOrdenes) ?>%"></span></span>
                    <span class="rp-barras__val"><?= (int) $cantidad ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>

<!-- VENCIDAS -->
<section class="rp-card" id="sec-ovenc">
    <header class="rp-card__cab">
        <h2>Órdenes vencidas</h2>
        <p class="rp-nota">Su fecha final ya pasó y todavía no están Finalizadas ni Anuladas. Las de mayor atraso primero.</p>
    </header>
    <div class="rp-tabla-wrap">
        <table class="rp-tabla">
            <thead>
                <tr>
                    <th>Orden</th>
                    <th>Estado</th>
                    <th>Localidad</th>
                    <th>Jurisdicción</th>
                    <th>Fecha final</th>
                    <th class="num">Atraso</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($pgVencidas['filas'])): ?>
                    <tr><td colspan="6" class="rp-vacio">No hay órdenes vencidas.</td></tr>
                <?php else: ?>
                    <?php foreach ($pgVencidas['filas'] as $o): ?>
                        <tr>
                            <td><strong><?= $h($o['numero_ot']) ?></strong></td>
                            <td><span class="rp-pastilla rp-pastilla--neutra"><?= $h($o['estado']) ?></span></td>
                            <td><?= $h($o['localidad']) ?></td>
                            <td><?= $h($o['jurisdiccion']) ?></td>
                            <td><?= $h(rp_fecha($o['fecha_final'])) ?></td>
                            <td class="num"><span class="rp-pastilla rp-pastilla--alerta"><?= $h(rp_dias((int) $o['dias_atraso'])) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php rp_paginador($pgVencidas); ?>
</section>

<!-- HERRAMIENTAS SIN DEVOLVER -->
<section class="rp-card" id="sec-sdev">
    <header class="rp-card__cab">
        <h2>Herramientas sin devolver de órdenes cerradas</h2>
        <p class="rp-nota">La orden ya está Finalizada o Anulada pero la herramienta que salió para ella sigue prestada. Es la principal fuente de herramientas perdidas.</p>
    </header>
    <div class="rp-tabla-wrap">
        <table class="rp-tabla">
            <thead>
                <tr>
                    <th>Orden</th>
                    <th>Estado</th>
                    <th>Herramienta</th>
                    <th>Operario</th>
                    <th class="num">Prestada hace</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($pgSinDev['filas'])): ?>
                    <tr><td colspan="5" class="rp-vacio">No hay herramientas pendientes de devolución en órdenes cerradas.</td></tr>
                <?php else: ?>
                    <?php foreach ($pgSinDev['filas'] as $t): ?>
                        <tr>
                            <td><strong><?= $h($t['numero_ot']) ?></strong></td>
                            <td><span class="rp-pastilla rp-pastilla--neutra"><?= $h($t['estado']) ?></span></td>
                            <td><a href="<?= $h($linkInsumo($t['codigo'])) ?>"><?= $h($t['herramienta']) ?></a></td>
                            <td><?= $h($t['operario']) ?></td>
                            <td class="num"><span class="rp-pastilla rp-pastilla--alerta"><?= $t['dias_prestada'] === null ? 'Sin dato' : $h(rp_dias((int) $t['dias_prestada'])) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php rp_paginador($pgSinDev); ?>
</section>
