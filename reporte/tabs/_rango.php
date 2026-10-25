<?php
// reporte/tabs/_rango.php
// Selector de período compartido por las pestañas Movimientos y Consumo.
// Espera: $tab_reporte (pestaña actual), $rango (clave activa), $h.
?>
<div class="rp-rango">
    <span class="rp-rango__titulo">Período:</span>
    <?php foreach (Reportes::RANGOS as $clave => [$etiqueta]): ?>
        <a href="dashboard.php?tab=<?= $h($tab_reporte) ?>&rango=<?= $h($clave) ?>" class="rp-chip <?= $rango === $clave ? 'rp-chip--activo' : '' ?>"
            <?= $rango === $clave ? 'aria-current="true"' : '' ?>><?= $h($etiqueta) ?></a>
    <?php endforeach; ?>
</div>
