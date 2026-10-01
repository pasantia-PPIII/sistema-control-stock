<?php
/**
 * operario/detalle_operario.php
 * Ficha detallada del operario:
 * Datos de contacto, herramientas en custodia y registro histórico de retiros de materiales.
 * 
 * Directivas:
 * - Sin iconos ni emojis en ninguna parte de la interfaz.
 * - Migrado de practica/modules/operarios/detalle.php adaptado a PostgreSQL.
 * - Integrado con layouts/header.php y layouts/footer.php.
 */

$seccion = 'operario';
$css_modulo = 'operarios';

require_once __DIR__ . '/../clases/operario.php';

$operarioModel = new Operario();

// Identificar al operario por DNI, ID o Legajo
$dni = trim($_GET['dni'] ?? '');
$id = !empty($_GET['id']) ? (int)$_GET['id'] : (!empty($_GET['id_operario']) ? (int)$_GET['id_operario'] : null);
$legajo = trim($_GET['legajo'] ?? '');

$operario = null;

if ($dni !== '') {
    $operario = $operarioModel->getByDni($dni);
} elseif ($id !== null) {
    $operario = $operarioModel->getById($id);
} elseif ($legajo !== '') {
    $todos = $operarioModel->buscar($legajo);
    foreach ($todos as $o) {
        if (!empty($o['legajo']) && strcasecmp($o['legajo'], $legajo) === 0) {
            $operario = $o;
            break;
        }
    }
}

if (!$operario) {
    header("Location: dashboard.php");
    exit;
}

$titulo_vista = 'Ficha del Operario: ' . $operario['apellido'] . ', ' . $operario['nombre'];
$titulo_pagina = 'Ficha de ' . $operario['apellido'] . ' - Sistema DeControl';

// Obtener herramientas en custodia e historial de materiales
$herramientasCustodia = $operarioModel->getHerramientasCustodia($operario['id_operario']);
$historialMateriales = $operarioModel->getHistorialMateriales($operario['id_operario']);

include_once __DIR__ . '/../layouts/header.php';
?>

<div class="ficha-panel">
    
    <!-- Encabezado de la Ficha -->
    <div class="ficha-header">
        <div>
            <a href="dashboard.php" class="btn-action-text view" style="text-decoration: none; padding: 0.4rem 0.9rem; font-size: 0.82rem; font-weight: 700;">
                Volver a Operarios
            </a>
            <h2 class="ficha-title"><?= htmlspecialchars($operario['apellido'] . ', ' . $operario['nombre']) ?></h2>
        </div>
        <div>
            <?php if ($operario['activo']): ?>
                <span class="badge badge-success" style="font-size: 0.85rem; padding: 0.4rem 0.8rem;">Operario Activo</span>
            <?php else: ?>
                <span class="badge badge-danger" style="font-size: 0.85rem; padding: 0.4rem 0.8rem;">Operario Inactivo</span>
            <?php endif; ?>
        </div>
    </div>

    <!-- Tarjeta de Datos Personales -->
    <div class="ficha-grid-datos">
        <div>
            <span class="ficha-dato-label">DNI</span>
            <p class="ficha-dato-valor"><?= htmlspecialchars($operario['dni']) ?></p>
        </div>
        <div>
            <span class="ficha-dato-label">Legajo Interno</span>
            <p class="ficha-dato-valor"><?= !empty($operario['legajo']) ? htmlspecialchars($operario['legajo']) : '-' ?></p>
        </div>
        <div>
            <span class="ficha-dato-label">Rubro / Especialidad</span>
            <p class="ficha-dato-valor"><?= !empty($operario['rubro_nombre']) ? htmlspecialchars($operario['rubro_nombre']) : 'General' ?></p>
        </div>
        <div>
            <span class="ficha-dato-label">Teléfono de Contacto</span>
            <p class="ficha-dato-valor"><?= !empty($operario['telefono']) ? htmlspecialchars($operario['telefono']) : 'No registrado' ?></p>
        </div>
        <div>
            <span class="ficha-dato-label">Observaciones</span>
            <p class="ficha-dato-valor" style="font-size: 0.9rem;"><?= !empty($operario['observaciones']) ? htmlspecialchars($operario['observaciones']) : 'Ninguna' ?></p>
        </div>
    </div>

    <!-- Sección 1: Herramientas en Custodia (Prestadas) -->
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
                        <th>N° Inventario</th>
                        <th>Herramienta</th>
                        <th>Modelo / Serie</th>
                        <th>Ubicación Asignada</th>
                        <th>Fecha de Retiro</th>
                        <th>Orden de Trabajo</th>
                        <th>Observaciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($herramientasCustodia) > 0): ?>
                        <?php foreach ($herramientasCustodia as $hc): 
                            $odtTxt = !empty($hc['numero_ot']) ? htmlspecialchars($hc['numero_ot']) : (!empty($hc['pa']) ? 'PA: ' . htmlspecialchars($hc['pa']) : '-');
                            $fechaFormateada = date('d/m/Y', strtotime($hc['fecha'])) . ' ' . substr($hc['hora'], 0, 5);
                        ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($hc['codigo']) ?></strong></td>
                                <td><?= htmlspecialchars($hc['nombre']) ?></td>
                                <td><?= !empty($hc['serie_modelo']) ? htmlspecialchars($hc['serie_modelo']) : '-' ?></td>
                                <td><?= !empty($hc['ubicacion']) ? htmlspecialchars($hc['ubicacion']) : 'Pañol Central' ?></td>
                                <td><?= $fechaFormateada ?></td>
                                <td><strong><?= $odtTxt ?></strong></td>
                                <td><?= !empty($hc['observaciones']) ? htmlspecialchars($hc['observaciones']) : '-' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" style="text-align: center; color: #64748b; padding: 1.5rem;">
                                El operario no tiene herramientas en su poder actualmente.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Sección 2: Historial de Materiales Retirados -->
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
                            $odtMatTxt = !empty($hm['numero_ot']) ? htmlspecialchars($hm['numero_ot']) : (!empty($hm['pa']) ? 'PA: ' . htmlspecialchars($hm['pa']) : 'Taller General');
                            $fechaMatFormateada = date('d/m/Y', strtotime($hm['fecha'])) . ' ' . substr($hm['hora'], 0, 5);
                        ?>
                            <tr>
                                <td><?= $fechaMatFormateada ?></td>
                                <td><strong><?= htmlspecialchars($hm['codigo']) ?></strong></td>
                                <td><?= htmlspecialchars($hm['material_nombre']) ?></td>
                                <td><strong><?= (float)$hm['cantidad'] ?></strong> <?= htmlspecialchars($hm['unidad_medida'] ?? 'Unidad') ?></td>
                                <td><strong><?= $odtMatTxt ?></strong></td>
                                <td><?= !empty($hm['observaciones']) ? htmlspecialchars($hm['observaciones']) : '-' ?></td>
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
include_once __DIR__ . '/../layouts/footer.php';
?>
