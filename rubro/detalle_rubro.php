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

// MEJORA 2: Ordenar en el backend para que los insumos con stock 0 o crítico aparezcan primero
usort($insumos, function ($a, $b) {
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
$titulo_vista = 'Detalle de Rubro';
$titulo_pagina = 'Rubro ' . $rubro['nombre'] . ' - Sistema de Control de Stock';

include_once __DIR__ . '/../layout/header.php';

$h = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
?>

<div
    style="background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:1.75rem 2rem; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">

    <!-- ENCABEZADO DEL RUBRO -->
    <div
        style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:1.5rem; flex-wrap:wrap; gap:1rem;">
        <div>
            <div style="display:flex; align-items:center; gap:0.75rem;">
                <h2 style="margin:0; color:#0b1536; font-size:1.6rem; font-weight:800;"><?= $h($rubro['nombre']) ?></h2>
                <span class="badge <?= $activo ? 'badge-success' : 'badge-danger' ?>" style="font-size:0.8rem;">
                    <?= $activo ? 'Activo' : 'Inactivo' ?>
                </span>
            </div>
            <p style="margin:0.4rem 0 0; color:#64748b; font-size:0.9rem;">
                <?= !empty($rubro['descripcion']) ? $h($rubro['descripcion']) : 'Sin descripción registrada.' ?>
            </p>
        </div>

        <div style="display:flex; align-items:center; gap:0.75rem;">
            <?php if (isAdmin()): ?>
                <a href="../insumo/dashboard.php?rubro=<?= (int) $rubro['id_rubro'] ?>" class="btn-pill-action"
                    style="text-decoration:none; padding:0.55rem 1.25rem; font-size:0.85rem;">
                    Ver en Catálogo General
                </a>
            <?php endif; ?>
            <a href="dashboard.php" class="btn-action-text view"
                style="text-decoration:none; padding:0.55rem 1.1rem; font-size:0.85rem; font-weight:700;">
                Volver a Rubros
            </a>
        </div>
    </div>

    <!-- BARRA DE BÚSQUEDA Y FILTRO RÁPIDO DENTRO DE LA CATEGORÍA -->
    <div
        style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.2rem; flex-wrap:wrap; gap:0.75rem;">
        <h3 style="color:#0b1536; margin:0; font-size:1.15rem; font-weight:800;">
            Insumos del rubro (<span id="total-articulos-visibles"><?= count($insumos) ?></span>)
        </h3>

        <div style="display:flex; gap:0.6rem; align-items:center;">
            <input type="text" id="buscador-insumo-rubro" placeholder="Filtrar por código o nombre..."
                class="modal-input" style="width:260px; padding:0.45rem 0.8rem; font-size:0.85rem;">

            <select id="filtro-tipo-rubro" class="filter-select"
                style="padding:0.45rem 1.8rem 0.45rem 0.75rem; font-size:0.85rem; min-width:130px;">
                <option value="">Todos los tipos</option>
                <option value="material">Materiales</option>
                <option value="herramienta">Herramientas</option>
            </select>
        </div>
    </div>

    <!-- TABLA DE ARTÍCULOS CON ANCHOS FIJOS (table-layout: fixed) -->
    <div style="overflow-x:auto;">
        <table class="data-table" id="tabla-insumos-detalle"
            style="width:100%; border-collapse:collapse; font-size:0.88rem; table-layout:fixed;">
            <thead>
                <tr style="text-align:left; border-bottom:2px solid #e2e8f0; color:#475569;">
                    <th style="padding:0.65rem 0.5rem; width:85px;">Código</th>
                    <th style="padding:0.65rem 0.5rem; width:auto;">Nombre</th>
                    <th style="padding:0.65rem 0.5rem; width:110px;">Tipo</th>
                    <th style="padding:0.65rem 0.5rem; width:90px;">Unidad</th>
                    <th style="padding:0.65rem 0.5rem; width:85px; text-align:right;">Stock</th>
                    <th style="padding:0.65rem 0.5rem; width:100px; text-align:center;">Estado</th>
                    <th style="padding:0.65rem 0.5rem; width:110px; text-align:right;">Acción</th>
                </tr>
            </thead>
            <tbody id="cuerpo-tabla-insumos">
                <?php if (empty($insumos)): ?>
                    <tr>
                        <td colspan="7" style="text-align:center; color:#64748b; padding:2rem;">
                            Este rubro aún no tiene insumos asociados.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($insumos as $i):
                        $insActivo = !empty($i['activo']) && $i['activo'] !== 'f';
                        $stockValor = (float) ($i['stock_actual'] ?? 0);
                        $tipoLimpio = strtolower($i['tipo_nombre'] ?? 'material');
                        ?>
                        <tr class="fila-insumo" data-tipo="<?= $h($tipoLimpio) ?>" style="border-bottom:1px solid #f1f5f9;">
                            <td style="padding:0.65rem 0.5rem;">
                                <a href="../insumo/detalles_insumo.php?codigo=<?= rawurlencode($i['codigo']) ?>"
                                    style="text-decoration:none; color:#2563eb;">
                                    <strong><?= $h($i['codigo']) ?></strong>
                                </a>
                            </td>
                            <td class="col-nombre" style="padding:0.65rem 0.5rem;"><?= $h($i['nombre']) ?></td>
                            <td class="col-tipo" style="padding:0.65rem 0.5rem; color:#64748b;">
                                <?= $h($i['tipo_nombre'] ?? 'Material') ?></td>
                            <td style="padding:0.65rem 0.5rem; color:#64748b;"><?= $h($i['unidad_medida'] ?? '-') ?></td>
                            <td
                                style="padding:0.65rem 0.5rem; text-align:right; font-weight:800; color: <?= $stockValor <= 0 ? '#ef4444' : '#0f172a' ?>;">
                                <?= $stockValor ?>
                            </td>
                            <td style="padding:0.65rem 0.5rem; text-align:center;">
                                <span class="badge <?= $insActivo ? 'badge-success' : 'badge-danger' ?>"
                                    style="font-size:0.75rem;">
                                    <?= $insActivo ? 'Activo' : 'Deshabilitado' ?>
                                </span>
                            </td>
                            <td style="padding:0.65rem 0.5rem; text-align:right;">
                                <a href="../insumo/detalles_insumo.php?codigo=<?= rawurlencode($i['codigo']) ?>"
                                    class="btn-action-text view"
                                    style="text-decoration:none; font-size:0.75rem; padding:0.3rem 0.6rem;">
                                    Ver Ficha
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    <!-- MEJORA 1: Fila dinámica de sin resultados (se activa vía JS cuando no hay coincidencias) -->
                    <tr id="fila-sin-resultados" style="display:none;">
                        <td colspan="7" style="text-align:center; color:#64748b; padding:2rem; font-size:0.9rem;">
                            No se encontraron insumos que coincidan con los criterios de búsqueda.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const buscador = document.getElementById('buscador-insumo-rubro');
        const filtroTipo = document.getElementById('filtro-tipo-rubro');
        const totalContador = document.getElementById('total-articulos-visibles');
        const filas = document.querySelectorAll('.fila-insumo');
        const filaSinResultados = document.getElementById('fila-sin-resultados');

        function filtrarTabla() {
            const texto = buscador.value.toLowerCase().trim();
            const tipoSel = filtroTipo.value.toLowerCase().trim();
            let visibles = 0;

            filas.forEach(fila => {
                const codigo = fila.querySelector('td strong') ? fila.querySelector('td strong').innerText.toLowerCase() : '';
                const nombre = fila.querySelector('.col-nombre') ? fila.querySelector('.col-nombre').innerText.toLowerCase() : '';
                const tipoFila = fila.getAttribute('data-tipo') || '';

                const coincideTexto = codigo.includes(texto) || nombre.includes(texto);
                const coincideTipo = tipoSel === '' || tipoFila.includes(tipoSel);

                if (coincideTexto && coincideTipo) {
                    fila.style.display = '';
                    visibles++;
                } else {
                    fila.style.display = 'none';
                }
            });

            if (totalContador) {
                totalContador.innerText = visibles;
            }

            // Mostrar o esconder el aviso de sin resultados
            if (filaSinResultados) {
                filaSinResultados.style.display = (visibles === 0) ? '' : 'none';
            }
        }

        if (buscador) buscador.addEventListener('input', filtrarTabla);
        if (filtroTipo) filtroTipo.addEventListener('change', filtrarTabla);
    });
</script>

<?php
include_once __DIR__ . '/../layout/footer.php';
?>