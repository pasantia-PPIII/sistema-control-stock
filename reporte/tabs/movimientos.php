<?php
// reporte/tabs/movimientos.php
// Pestaña "Movimientos". Se incluye desde reporte/dashboard.php.
$meses = ['01' => 'ene', '02' => 'feb', '03' => 'mar', '04' => 'abr', '05' => 'may', '06' => 'jun',
    '07' => 'jul', '08' => 'ago', '09' => 'sep', '10' => 'oct', '11' => 'nov', '12' => 'dic'];
$etiquetaMes = fn($ym) => ($meses[substr($ym, 5, 2)] ?? $ym) . ' ' . substr($ym, 0, 4);

$maxEvolucion = 0;
foreach ($evolucion as $e) {
    $maxEvolucion = max($maxEvolucion, $e['ingresos'], $e['entregas'], $e['devoluciones']);
}

$maxDia = 0;
$totalDias = 0;
foreach ($actividadDiaria as $d) {
    $maxDia = max($maxDia, $d['movimientos']);
    $totalDias += $d['movimientos'];
}

$totalPeriodo = array_sum(array_column($movPorTipo, 'movimientos'));
$maxTipo = $movPorTipo ? max(array_column($movPorTipo, 'movimientos')) : 0;
$rotuloRango = Reportes::RANGOS[$rango][0];
$claseTipo = [
    'Ingreso' => 'rp-pastilla--ok',
    'Entrega' => 'rp-pastilla--info',
    'Devolución' => 'rp-pastilla--aviso',
    'Ajuste de Inventario' => 'rp-pastilla--neutra',
];
?>

<?php include __DIR__ . '/_rango.php'; ?>

<!-- POR TIPO -->
<section class="rp-card">
    <header class="rp-card__cab">
        <h2>Movimientos por tipo</h2>
        <p class="rp-nota">Movimientos registrados (sin los anulados) y cantidad de ítems movidos. Período: <?= $h($rotuloRango) ?>.
            Total: <strong><?= (int) $totalPeriodo ?></strong> movimientos.</p>
    </header>
    <?php if ($totalPeriodo === 0): ?>
        <p class="rp-vacio">No hubo movimientos en este período.</p>
    <?php else: ?>
        <ul class="rp-barras">
            <?php foreach ($movPorTipo as $t): ?>
                <li title="<?= $h($t['items'] . ' ítems') ?>">
                    <span class="rp-barras__etq"><?= $h($t['tipo']) ?></span>
                    <span class="rp-barras__pista"><span class="rp-barras__rel" style="width: <?= rp_pct($t['movimientos'], $maxTipo) ?>%"></span></span>
                    <span class="rp-barras__val"><?= (int) $t['movimientos'] ?> <small class="rp-suave">· <?= (int) $t['items'] ?> ítems</small></span>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>

<!-- ACTIVIDAD DIARIA -->
<section class="rp-card">
    <header class="rp-card__cab">
        <h2>Actividad de los últimos 30 días</h2>
        <p class="rp-nota">Movimientos por día. Promedio: <strong><?= $h(rp_num($totalDias / max(1, count($actividadDiaria)), 1)) ?></strong> por día.</p>
    </header>
    <?php if ($maxDia === 0): ?>
        <p class="rp-vacio">No hubo movimientos en los últimos 30 días.</p>
    <?php else: ?>
        <div class="rp-diario" role="img" aria-label="Movimientos por día en los últimos 30 días">
            <?php foreach ($actividadDiaria as $d): ?>
                <span class="rp-dia" title="<?= $h(rp_fecha($d['dia']) . ': ' . $d['movimientos'] . ' movimientos') ?>">
                    <span class="rp-dia__barra" style="height: <?= rp_pct($d['movimientos'], $maxDia) ?>%"></span>
                </span>
            <?php endforeach; ?>
        </div>
        <div class="rp-diario__ejes">
            <span><?= $h(rp_fecha($actividadDiaria[0]['dia'])) ?></span>
            <span>Máximo en un día: <?= (int) $maxDia ?></span>
            <span><?= $h(rp_fecha(end($actividadDiaria)['dia'])) ?></span>
        </div>
    <?php endif; ?>
</section>

<!-- EVOLUCIÓN MENSUAL -->
<section class="rp-card">
    <header class="rp-card__cab">
        <h2>Evolución mensual</h2>
        <p class="rp-nota">Cantidad de movimientos por mes en los últimos 6 meses: ingresos de stock, entregas y devoluciones.</p>
    </header>
    <?php if ($maxEvolucion === 0): ?>
        <p class="rp-vacio">No hay movimientos en los últimos 6 meses.</p>
    <?php else: ?>
        <div class="rp-leyenda">
            <span><i class="rp-dot rp-dot--ingreso"></i> Ingresos</span>
            <span><i class="rp-dot rp-dot--entrega"></i> Entregas</span>
            <span><i class="rp-dot rp-dot--devolucion"></i> Devoluciones</span>
        </div>
        <div class="rp-evolucion" role="img" aria-label="Movimientos por mes: ingresos, entregas y devoluciones">
            <?php foreach ($evolucion as $e): ?>
                <div class="rp-mes">
                    <div class="rp-mes__barras">
                        <?php foreach ([['ingresos', 'ingreso'], ['entregas', 'entrega'], ['devoluciones', 'devolucion']] as [$campo, $clase]): ?>
                            <div class="rp-col" title="<?= $h(ucfirst($campo) . ': ' . $e[$campo]) ?>">
                                <span class="rp-col__val"><?= (int) $e[$campo] ?></span>
                                <span class="rp-col__barra rp-col__barra--<?= $clase ?>" style="height: <?= rp_pct($e[$campo], $maxEvolucion) ?>%"></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <span class="rp-mes__etq"><?= $h($etiquetaMes($e['mes'])) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<!-- ÚLTIMOS MOVIMIENTOS -->
<section class="rp-card" id="sec-um">
    <header class="rp-card__cab">
        <h2>Últimos movimientos</h2>
        <p class="rp-nota">Todos los movimientos activos (sin anulados), del más nuevo al más antiguo.</p>
    </header>
    <div class="rp-tabla-wrap">
        <table class="rp-tabla">
            <thead>
                <tr>
                    <th>N°</th>
                    <th>Fecha y hora</th>
                    <th>Tipo</th>
                    <th>Operario</th>
                    <th>Orden</th>
                    <th class="num">Ítems</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($pgUlt['filas'])): ?>
                    <tr><td colspan="6" class="rp-vacio">Todavía no hay movimientos registrados.</td></tr>
                <?php else: ?>
                    <?php foreach ($pgUlt['filas'] as $m): ?>
                        <tr>
                            <td><strong>#<?= (int) $m['id_movimiento'] ?></strong></td>
                            <td><?= $h(rp_fecha($m['fecha'])) ?> <?= $h(substr((string) $m['hora'], 0, 5)) ?></td>
                            <td><span class="rp-pastilla <?= $claseTipo[$m['tipo']] ?? 'rp-pastilla--neutra' ?>"><?= $h($m['tipo']) ?></span></td>
                            <td><?= $h($m['operario']) ?></td>
                            <td><?= $m['numero_ot'] ? $h($m['numero_ot']) : '-' ?></td>
                            <td class="num"><?= (int) $m['items'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php rp_paginador($pgUlt); ?>
</section>
