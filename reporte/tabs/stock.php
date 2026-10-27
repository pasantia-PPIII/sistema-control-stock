<?php
// reporte/tabs/stock.php
// Pestaña "Stock". Se incluye desde reporte/dashboard.php (usa $h, rp_num(), rp_fecha(), rp_dias(), rp_paginar()
// y los datos cargados allí).
$linkInsumo = fn($codigo) => '../insumo/detalles_insumo.php?codigo=' . rawurlencode((string) $codigo);

?>

<!-- STOCK CRÍTICO / COMPRAS -->
<section class="rp-card" id="sec-crit">
    <header class="rp-card__cab">
        <h2>Stock crítico</h2>
        <p class="rp-nota">Materiales con stock actual igual o menor al mínimo. Las herramientas no se cuentan: se miden por estado, no por cantidad.
            Los más faltantes primero.</p>
    </header>
    <div class="rp-tabla-wrap">
        <table class="rp-tabla">
            <thead>
                <tr>
                    <th>Código</th>
                    <th>Material</th>
                    <th>Rubro</th>
                    <th>Ubicación</th>
                    <th class="num">Stock</th>
                    <th class="num">Mínimo</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($pgCrit['filas'])): ?>
                    <tr><td colspan="6" class="rp-vacio">Ningún material está en nivel crítico. Existencias en estado óptimo.</td></tr>
                <?php else: ?>
                    <?php foreach ($pgCrit['filas'] as $it): ?>
                        <tr>
                            <td><a href="<?= $h($linkInsumo($it['codigo'])) ?>"><strong><?= $h($it['codigo']) ?></strong></a></td>
                            <td><?= $h($it['nombre']) ?></td>
                            <td><?= $h($it['rubro']) ?></td>
                            <td><?= $h($it['ubicacion']) ?></td>
                            <td class="num rp-txt-alerta"><strong><?= $h(rp_num($it['stock_actual'])) ?></strong> <?= $h($it['unidad']) ?></td>
                            <td class="num"><?= $h(rp_num($it['stock_minimo'])) ?> <?= $h($it['unidad']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php rp_paginador($pgCrit); ?>
</section>

<!-- VENCIMIENTOS -->
<section class="rp-card" id="sec-venc">
    <header class="rp-card__cab">
        <h2>Vencimientos</h2>
        <p class="rp-nota">Perecederos con stock que ya vencieron o vencen en los próximos 30 días. Un material vencido con stock debe retirarse o descartarse.</p>
    </header>
    <div class="rp-tabla-wrap">
        <table class="rp-tabla">
            <thead>
                <tr>
                    <th>Código</th>
                    <th>Material</th>
                    <th>Vencimiento</th>
                    <th>Situación</th>
                    <th class="num">Stock</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($pgVenc['filas'])): ?>
                    <tr><td colspan="5" class="rp-vacio">No hay materiales vencidos ni próximos a vencer.</td></tr>
                <?php else: ?>
                    <?php foreach ($pgVenc['filas'] as $v): $dias = (int) $v['dias_restantes']; ?>
                        <tr>
                            <td><a href="<?= $h($linkInsumo($v['codigo'])) ?>"><strong><?= $h($v['codigo']) ?></strong></a></td>
                            <td><?= $h($v['nombre']) ?></td>
                            <td><?= $h(rp_fecha($v['fecha_vencimiento'])) ?></td>
                            <td>
                                <?php if ($dias < 0): ?>
                                    <span class="rp-pastilla rp-pastilla--alerta">Vencido hace <?= $h(rp_dias(-$dias)) ?></span>
                                <?php elseif ($dias === 0): ?>
                                    <span class="rp-pastilla rp-pastilla--alerta">Vence hoy</span>
                                <?php else: ?>
                                    <span class="rp-pastilla rp-pastilla--aviso">Vence en <?= $h(rp_dias($dias)) ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="num"><?= $h(rp_num($v['stock_actual'])) ?> <?= $h($v['unidad']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php rp_paginador($pgVenc); ?>
</section>

<!-- STOCK INMÓVIL -->
<section class="rp-card" id="sec-inm">
    <header class="rp-card__cab">
        <h2>Stock inmóvil</h2>
        <p class="rp-nota">Materiales con stock que no salieron en los últimos 90 días (y fueron dados de alta hace más de 90 días).
            Es capital parado: conviene revisarlo, redistribuirlo o dejar de comprarlo. Los más antiguos primero.</p>
    </header>
    <div class="rp-tabla-wrap">
        <table class="rp-tabla">
            <thead>
                <tr>
                    <th>Código</th>
                    <th>Material</th>
                    <th class="num">Stock</th>
                    <th>Última entrega</th>
                    <th class="num">Sin salida hace</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($pgInm['filas'])): ?>
                    <tr><td colspan="5" class="rp-vacio">No hay materiales con stock inmóvil.</td></tr>
                <?php else: ?>
                    <?php foreach ($pgInm['filas'] as $m): ?>
                        <tr>
                            <td><a href="<?= $h($linkInsumo($m['codigo'])) ?>"><strong><?= $h($m['codigo']) ?></strong></a></td>
                            <td><?= $h($m['nombre']) ?></td>
                            <td class="num"><?= $h(rp_num($m['stock_actual'])) ?> <?= $h($m['unidad']) ?></td>
                            <td><?= $m['ultima_entrega'] ? $h(rp_fecha($m['ultima_entrega'])) : 'Nunca' ?></td>
                            <td class="num"><?= $h(rp_dias((int) $m['dias_sin_salida'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php rp_paginador($pgInm); ?>
</section>

<!-- CALIDAD DE DATOS -->
<section class="rp-card">
    <header class="rp-card__cab">
        <h2>Calidad de los datos del catálogo</h2>
        <p class="rp-nota">Faltantes de configuración que dejan ciegas a otras métricas. Por ejemplo, un material sin stock mínimo nunca aparece como crítico.
            Tocá un aviso para ver los insumos afectados y corregirlos.</p>
    </header>
    <div class="rp-higiene">
        <?php foreach ($higiene as $clave => [$etiqueta, $cantidad, $afectados]):
            $pgHig = rp_paginar($afectados, 'hig_' . $clave); ?>
            <?php if ($cantidad === 0): ?>
                <div class="rp-higiene__grupo rp-higiene--ok">
                    <div class="rp-higiene__cab">
                        <span class="rp-higiene__num">0</span>
                        <span class="rp-higiene__txt"><?= $h($etiqueta) ?></span>
                        <span class="rp-higiene__estado">&#10003; Todo en orden</span>
                    </div>
                </div>
            <?php else: ?>
                <details class="rp-higiene__grupo rp-higiene--pendiente" id="sec-hig_<?= $h($clave) ?>"
                         <?= isset($_GET['p_hig_' . $clave]) ? 'open' : '' ?>>
                    <summary class="rp-higiene__cab">
                        <span class="rp-higiene__num"><?= (int) $cantidad ?></span>
                        <span class="rp-higiene__txt"><?= $h($etiqueta) ?></span>
                        <span class="rp-higiene__estado">
                            <span class="rp-higiene__ver">Ver insumos</span>
                            <span class="rp-higiene__ocultar">Ocultar</span>
                            <svg class="rp-higiene__flecha" width="16" height="16" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                                <path d="M5 7.5l5 5 5-5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </span>
                    </summary>
                    <ul class="rp-higiene__lista">
                        <?php foreach ($pgHig['filas'] as $it): ?>
                            <li>
                                <span class="rp-higiene__insumo">
                                    <strong><?= $h($it['codigo']) ?></strong>
                                    <?= $h($it['nombre']) ?>
                                    <small class="rp-suave">· <?= $h($it['tipo']) ?></small>
                                </span>
                                <?php if (canEdit()): ?>
                                    <a class="rp-higiene__editar" href="<?= $h('../insumo/dashboard.php?editar=' . (int) $it['id_insumo']) ?>"
                                       title="Abre este insumo en Insumos para editarlo">
                                        Editar en Insumos
                                        <svg width="14" height="14" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                                            <path d="M4 10h12m0 0l-5-5m5 5l-5 5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                    </a>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php rp_paginador($pgHig); ?>
                </details>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
</section>
