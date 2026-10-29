<?php
/**
 * operario/detalle_operario.php
 * Ficha detallada del operario: datos personales, herramientas en custodia
 * e historial de materiales retirados.
 *
 * - Parámetros: ?id=N (id_operario) o ?dni=XXXX.
 * - Datos mediante la clase Operario (getById, getByDni, getHerramientasCustodia, getHistorialMaterialesPaginado).
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
// Historial de materiales paginado en la base, de a 10 retiros (?pagina_mat=N)
$historial = $operarioModel->getHistorialMaterialesPaginado($operario['id_operario'], (int)($_GET['pagina_mat'] ?? 1), 10);
$historialMateriales = $historial['filas'];
$totalPaginasMat = max(1, (int)ceil($historial['total'] / $historial['porPagina']));

$seccion = 'operario';
$css_modulo = 'operarios';
$titulo_vista = 'Ficha del Operario: ' . $operario['apellido'] . ', ' . $operario['nombre'];
$titulo_pagina = 'Ficha de ' . $operario['apellido'] . ' - Sistema de Control de Stock';

include_once __DIR__ . '/../layout/header.php';

$h = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
?>

<div class="ficha-card">
    <div class="ficha-header">
        <div>
            <h2 class="ficha-title">Operario: <?= $h($operario['apellido'] . ', ' . $operario['nombre']) ?></h2>
            <div class="ficha-subtitle">
                ID Sistema: #<?= (int)$operario['id_operario'] ?> | Estado actual:
                <span class="badge <?= $activo ? 'badge-success' : 'badge-danger' ?>"><?= $activo ? 'Activo' : 'Inactivo' ?></span>
            </div>
        </div>
        <a href="dashboard.php" class="btn-volver-ficha">Volver al Listado</a>
    </div>

    <div class="ficha-grid">
        <div class="ficha-dato">
            <strong>DNI</strong>
            <span><?= $h($operario['dni']) ?></span>
        </div>
        <div class="ficha-dato">
            <strong>Legajo Interno</strong>
            <span><?= !empty($operario['legajo']) ? $h($operario['legajo']) : '-' ?></span>
        </div>
        <div class="ficha-dato">
            <strong>Rubro / Especialidad</strong>
            <span><?= !empty($operario['nombre_rubro']) ? $h($operario['nombre_rubro']) : 'General' ?></span>
        </div>
        <div class="ficha-dato">
            <strong>Herramientas en su poder</strong>
            <span><?= count($herramientasCustodia) ?></span>
        </div>
        <div class="ficha-dato">
            <strong>Retiros de materiales</strong>
            <span><?= (int)$historial['total'] ?></span>
        </div>
    </div>

    <!-- Herramientas en custodia -->
    <div class="ficha-seccion">
        <h3 class="ficha-seccion__titulo">Herramientas en Custodia (<?= count($herramientasCustodia) ?>)</h3>

        <div class="ficha-tabla-wrap">
            <table class="ficha-tabla">
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
                                <td><a href="../insumo/detalles_insumo.php?codigo=<?= rawurlencode($hc['codigo']) ?>"><strong><?= $h($hc['codigo']) ?></strong></a></td>
                                <td><?= $h($hc['nombre']) ?></td>
                                <td><?= !empty($hc['serie_modelo']) ? $h($hc['serie_modelo']) : '-' ?></td>
                                <td><?= !empty($hc['ubicacion']) ? $h($hc['ubicacion']) : 'Pañol Central' ?></td>
                                <td><strong><?= (float)$hc['en_poder'] ?></strong></td>
                                <td><?= !empty($hc['fecha']) ? $h(date('d/m/Y', strtotime($hc['fecha']))) : '-' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="estado-vacio estado-vacio--compacto">
                                El operario no tiene herramientas en su poder actualmente.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Historial de materiales -->
    <div class="ficha-seccion ficha-seccion--ultima" id="historial-materiales">
        <h3 class="ficha-seccion__titulo">Historial de Materiales Retirados (<?= (int)$historial['total'] ?>)</h3>

        <div class="ficha-tabla-wrap">
            <table class="ficha-tabla">
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
                                <td><a href="../insumo/detalles_insumo.php?codigo=<?= rawurlencode($hm['codigo']) ?>"><strong><?= $h($hm['codigo']) ?></strong></a></td>
                                <td><?= $h($hm['material_nombre']) ?></td>
                                <td><strong><?= (float)$hm['cantidad'] ?></strong> <?= $h($hm['unidad_medida'] ?? 'Unidad') ?></td>
                                <td><strong><?= $odtTxt ?></strong></td>
                                <td><?= !empty($hm['observaciones']) ? $h($hm['observaciones']) : '-' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="estado-vacio estado-vacio--compacto">
                                No se registran retiros de materiales para este operario.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="pagination-container" data-total="<?= $totalPaginasMat ?>" data-actual="<?= (int)$historial['pagina'] ?>"
            data-param="pagina_mat" data-ancla="historial-materiales"></div>
    </div>
</div>

<?php
include_once __DIR__ . '/../layout/footer.php';
?>
