<?php
/**
 * operario/detalle_operario.php
 * Ficha detallada del operario: datos personales, herramientas en custodia
 * e historial de materiales retirados.
 *
 * - Parámetros: ?id=N (id_operario) o ?dni=XXXX.
 * - Datos mediante la clase Operario (getById, getByDni, getHerramientasCustodia, getHistorialMateriales).
 */

require_once __DIR__ . '/../config/config.php';
if (!isAuthenticated()) {
    redirect('auth/login.php');
}

require_once __DIR__ . '/../clases/operario.php';

$operarioModel = new Operario();

$operario = null;
if (!empty($_GET['id'])) {
    $operario = $operarioModel->getById((int)$_GET['id']);
} elseif (!empty($_GET['dni'])) {
    $operario = $operarioModel->getByDni(trim((string)$_GET['dni']));
}

if (!$operario) {
    setAlert('El operario solicitado no existe.', 'warning');
    redirect('operario/dashboard.php');
}

$activo = !empty($operario['activo']) && $operario['activo'] !== 'f';
$herramientasCustodia = $operarioModel->getHerramientasCustodia($operario['id_operario']);
$historialMateriales = $operarioModel->getHistorialMateriales($operario['id_operario']);

$seccion = 'operario';
$css_modulo = 'operarios';
$titulo_vista = 'Ficha del Operario: ' . $operario['apellido'] . ', ' . $operario['nombre'];
$titulo_pagina = 'Ficha de ' . $operario['apellido'] . ' - Sistema de Control de Stock';

include_once __DIR__ . '/../layout/header.php';

$h = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
?>

<div class="ficha-panel">

    <div class="ficha-header">
        <div>
            <a href="dashboard.php" class="btn-action-text view" style="text-decoration: none; padding: 0.4rem 0.9rem; font-size: 0.82rem; font-weight: 700;">
                Volver a Operarios
            </a>
            <h2 class="ficha-title"><?= $h($operario['apellido'] . ', ' . $operario['nombre']) ?></h2>
        </div>
        <div>
            <?php if ($activo): ?>
                <span class="badge badge-success" style="font-size: 0.85rem; padding: 0.4rem 0.8rem;">Operario Activo</span>
            <?php else: ?>
                <span class="badge badge-danger" style="font-size: 0.85rem; padding: 0.4rem 0.8rem;">Operario Inactivo</span>
            <?php endif; ?>
        </div>
    </div>

    <div class="ficha-grid-datos">
        <div>
            <span class="ficha-dato-label">DNI</span>
            <p class="ficha-dato-valor"><?= $h($operario['dni']) ?></p>
        </div>
        <div>
            <span class="ficha-dato-label">Legajo Interno</span>
            <p class="ficha-dato-valor"><?= !empty($operario['legajo']) ? $h($operario['legajo']) : '-' ?></p>
        </div>
        <div>
            <span class="ficha-dato-label">Rubro / Especialidad</span>
            <p class="ficha-dato-valor"><?= !empty($operario['nombre_rubro']) ? $h($operario['nombre_rubro']) : 'General' ?></p>
        </div>
    </div>

    <!-- Herramientas en custodia -->
    <div class="ficha-section">
        <div class="ficha-section-header">
            <h3 class="ficha-section-title">Herramientas en Custodia (Prestadas)</h3>
            <span style="font-size: 0.85rem; color: #64748b; font-weight: 600;">
                Total en poder: <?= count($herramientasCustodia) ?>
            </span>
        </div>

        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Herramienta</th>
                        <th>Modelo / Serie</th>
                        <th>Ubicación Habitual</th>
                        <th>Cantidad</th>
                        <th>Última Entrega</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($herramientasCustodia) > 0): ?>
                        <?php foreach ($herramientasCustodia as $hc): ?>
                            <tr>
                                <td><strong><?= $h($hc['codigo']) ?></strong></td>
                                <td><?= $h($hc['nombre']) ?></td>
                                <td><?= !empty($hc['serie_modelo']) ? $h($hc['serie_modelo']) : '-' ?></td>
                                <td><?= !empty($hc['ubicacion']) ? $h($hc['ubicacion']) : 'Pañol Central' ?></td>
                                <td><strong><?= (float)$hc['en_poder'] ?></strong></td>
                                <td><?= !empty($hc['fecha']) ? $h(date('d/m/Y', strtotime($hc['fecha']))) : '-' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" style="text-align: center; color: #64748b; padding: 1.5rem;">
                                El operario no tiene herramientas en su poder actualmente.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Historial de materiales -->
    <div class="ficha-section" style="margin-bottom: 0;">
        <div class="ficha-section-header">
            <h3 class="ficha-section-title">Historial de Materiales Retirados</h3>
            <span style="font-size: 0.85rem; color: #64748b; font-weight: 600;">
                Total registros: <?= count($historialMateriales) ?>
            </span>
        </div>

        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Fecha y Hora</th>
                        <th>Código</th>
                        <th>Material / Insumo</th>
                        <th>Cantidad</th>
                        <th>Orden de Trabajo</th>
                        <th>Observaciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($historialMateriales) > 0): ?>
                        <?php foreach ($historialMateriales as $hm):
                            $odtTxt = !empty($hm['numero_ot']) ? $h($hm['numero_ot']) : (!empty($hm['pa']) ? 'PA: ' . $h($hm['pa']) : 'Taller General');
                        ?>
                            <tr>
                                <td><?= $h(date('d/m/Y', strtotime($hm['fecha']))) ?> <?= $h(substr($hm['hora'], 0, 5)) ?></td>
                                <td><strong><?= $h($hm['codigo']) ?></strong></td>
                                <td><?= $h($hm['material_nombre']) ?></td>
                                <td><strong><?= (float)$hm['cantidad'] ?></strong> <?= $h($hm['unidad_medida'] ?? 'Unidad') ?></td>
                                <td><strong><?= $odtTxt ?></strong></td>
                                <td><?= !empty($hm['observaciones']) ? $h($hm['observaciones']) : '-' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" style="text-align: center; color: #64748b; padding: 1.5rem;">
                                No se registran retiros de materiales para este operario.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<?php
include_once __DIR__ . '/../layout/footer.php';
?>
