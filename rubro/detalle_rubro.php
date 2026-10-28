<?php
/**
 * rubro/detalle_rubro.php
 * Detalle de un rubro: datos generales e insumos que lo componen
 * con buscador dinámico, fila de 'sin resultados', ordenamiento de stock y filtros.
 */

require_once __DIR__ . '/../config/config.php';

$esAjax = (isset($_GET['ajax']) && $_GET['ajax'] == '1')
    || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');

if (!isAuthenticated()) {
    if ($esAjax) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => 'Sesión no iniciada. Inicie sesión nuevamente.']);
        exit;
    }
    redirect('auth/login.php');
}

require_once __DIR__ . '/../clases/rubro.php';

$idRubro = isset($_GET['id']) ? (int) $_GET['id'] : 0;

$rubroModel = new Rubro();
$rubro = $idRubro > 0 ? $rubroModel->getById($idRubro) : false;

if (!$rubro) {
    if ($esAjax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => "No se encontró el rubro #{$idRubro}."]);
        exit;
    }
    redirect('rubro/dashboard.php');
}

$insumos = $rubroModel->getInsumosByRubro($idRubro);

// Orden en el backend: primero los activos con stock 0 o crítico
usort($insumos, function ($a, $b) {
    // Los insumos deshabilitados van al final: no requieren reposición
    $activoA = !empty($a['activo']) && $a['activo'] !== 'f';
    $activoB = !empty($b['activo']) && $b['activo'] !== 'f';
    if ($activoA !== $activoB) {
        return $activoA ? -1 : 1;
    }

    $stockA = (float) ($a['stock_actual'] ?? 0);
    $stockB = (float) ($b['stock_actual'] ?? 0);

    // Si uno no tiene stock y el otro sí, va primero el de stock 0
    if ($stockA <= 0 && $stockB > 0)
        return -1;
    if ($stockA > 0 && $stockB <= 0)
        return 1;

    // Si ambos están en la misma condición, ordenar por menor stock primero
    if ($stockA !== $stockB) {
        return ($stockA < $stockB) ? -1 : 1;
    }

    // Si empatan en stock, ordenar alfabéticamente por nombre
    return strcasecmp($a['nombre'], $b['nombre']);
});

if ($esAjax) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => true, 'data' => $rubro, 'insumos' => $insumos]);
    exit;
}

$activo = !empty($rubro['activo']) && $rubro['activo'] !== 'f';

$seccion = 'rubro';
$css_modulo = 'rubros';
$js_modulo = 'rubro_detalle';
$titulo_vista = 'Detalle de Rubro';
$titulo_pagina = 'Rubro ' . $rubro['nombre'] . ' - Sistema de Control de Stock';

include_once __DIR__ . '/../layout/header.php';

$h = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
?>

<div class="rubro-det">

    <!-- ENCABEZADO DEL RUBRO -->
    <div class="rubro-det__encabezado">
        <div>
            <div class="rubro-det__titulo-fila">
                <h2 class="rubro-det__titulo"><?= $h($rubro['nombre']) ?></h2>
                <span class="badge rubro-det__badge <?= $activo ? 'badge-success' : 'badge-danger' ?>">
                    <?= $activo ? 'Activo' : 'Inactivo' ?>
                </span>
            </div>
            <p class="rubro-det__descripcion">
                <?= !empty($rubro['descripcion']) ? $h($rubro['descripcion']) : 'Sin descripción registrada.' ?>
            </p>
        </div>

        <div class="rubro-det__acciones">
            <?php if (isAdmin()): ?>
                <a href="../insumo/dashboard.php?rubro=<?= (int) $rubro['id_rubro'] ?>"
                    class="btn-pill-action rubro-det__btn-catalogo">
                    Ver en Catálogo General
                </a>
            <?php endif; ?>
            <a href="dashboard.php" class="btn-action-text view rubro-det__btn-volver">
                Volver a Rubros
            </a>
        </div>
    </div>

    <!-- BARRA DE BÚSQUEDA Y FILTRO RÁPIDO DENTRO DE LA CATEGORÍA -->
    <div class="rubro-det__barra">
        <h3 class="rubro-det__subtitulo">
            Insumos del rubro (<span id="total-articulos-visibles"><?= count($insumos) ?></span>)
        </h3>

        <div class="rubro-det__filtros">
            <input type="text" id="buscador-insumo-rubro" placeholder="Filtrar por código o nombre..."
                class="modal-input rubro-det__buscador">

            <select id="filtro-tipo-rubro" class="filter-select rubro-det__filtro-tipo">
                <option value="">Todos los tipos</option>
                <option value="material">Materiales</option>
                <option value="herramienta">Herramientas</option>
            </select>
        </div>
    </div>

    <!-- TABLA DE ARTÍCULOS CON ANCHOS FIJOS (table-layout: fixed) -->
    <div class="rubro-det__tabla-wrap">
        <table class="data-table rubro-det__tabla" id="tabla-insumos-detalle">
            <thead>
                <tr>
                    <th class="col-codigo">Código</th>
                    <th class="col-nombre-th">Nombre</th>
                    <th class="col-tipo-th">Tipo</th>
                    <th class="col-unidad">Unidad</th>
                    <th class="col-stock">Stock</th>
                    <th class="col-estado">Estado</th>
                    <th class="col-accion">Acción</th>
                </tr>
            </thead>
            <tbody id="cuerpo-tabla-insumos">
                <?php if (empty($insumos)): ?>
                    <tr>
                        <td colspan="7" class="rubro-det__vacio">
                            Este rubro aún no tiene insumos asociados.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($insumos as $i):
                        $insActivo = !empty($i['activo']) && $i['activo'] !== 'f';
                        $stockValor = (float) ($i['stock_actual'] ?? 0);
                        $tipoLimpio = strtolower($i['tipo_nombre'] ?? 'material');
                        ?>
                        <tr class="fila-insumo<?= $insActivo ? '' : ' fila-insumo--inactiva' ?>" data-tipo="<?= $h($tipoLimpio) ?>"
                            <?= $insActivo ? '' : 'title="Insumo deshabilitado"' ?>>
                            <td>
                                <a href="../insumo/detalles_insumo.php?codigo=<?= rawurlencode($i['codigo']) ?>"
                                    class="rubro-det__link-codigo">
                                    <strong><?= $h($i['codigo']) ?></strong>
                                </a>
                            </td>
                            <td class="col-nombre"><?= $h($i['nombre']) ?></td>
                            <td class="col-tipo rubro-det__celda-tenue"><?= $h($i['tipo_nombre'] ?? 'Material') ?></td>
                            <td class="rubro-det__celda-tenue"><?= $h($i['unidad_medida'] ?? '-') ?></td>
                            <td class="rubro-det__stock <?= $stockValor <= 0 ? 'rubro-det__stock--cero' : '' ?>">
                                <?= $stockValor ?>
                            </td>
                            <td class="rubro-det__celda-centro">
                                <span class="badge rubro-det__badge-insumo <?= $insActivo ? 'badge-success' : 'badge-danger' ?>">
                                    <?= $insActivo ? 'Activo' : 'Deshabilitado' ?>
                                </span>
                            </td>
                            <td class="rubro-det__celda-der">
                                <a href="../insumo/detalles_insumo.php?codigo=<?= rawurlencode($i['codigo']) ?>"
                                    class="btn-action-icon view" title="Ver Ficha de Insumo"><?= iconoAccion('view') ?></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    <!-- Fila dinámica de sin resultados (se activa vía JS cuando no hay coincidencias) -->
                    <tr id="fila-sin-resultados" class="oculto">
                        <td colspan="7" class="rubro-det__vacio rubro-det__vacio--filtro">
                            No se encontraron insumos que coincidan con los criterios de búsqueda.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
include_once __DIR__ . '/../layout/footer.php';
?>