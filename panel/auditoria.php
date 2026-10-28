<?php
/**
 * panel/auditoria.php
 * Módulo de Auditoría y Trazabilidad de Acciones.
 * Exclusivo para usuarios con rol ADMINISTRADOR.
 */

require_once __DIR__ . '/../config/config.php';

// Control de acceso estricto: solo administradores
if (!isAuthenticated()) {
    redirect('auth/login.php');
}
requireRole(['admin']);

require_once __DIR__ . '/../clases/auditoria.php';
$auditoriaModel = new Auditoria();

// =========================================================
// ENDPOINT AJAX: Obtener detalle completo de un registro
// =========================================================
if (isset($_GET['action']) && $_GET['action'] === 'detalle') {
    header('Content-Type: application/json; charset=utf-8');
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    
    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Identificador de auditoría inválido.']);
        exit;
    }

    $registro = $auditoriaModel->obtenerPorId($id);
    if (!$registro) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'No se encontró el registro de auditoría.']);
        exit;
    }

    echo json_encode(['success' => true, 'data' => $registro]);
    exit;
}

// =========================================================
// OBTENCIÓN Y SANITIZACIÓN DE FILTROS (GET)
// =========================================================
$filtros = [
    'fecha_desde' => isset($_GET['fecha_desde']) ? trim((string)$_GET['fecha_desde']) : '',
    'fecha_hasta' => isset($_GET['fecha_hasta']) ? trim((string)$_GET['fecha_hasta']) : '',
    'modulo'      => isset($_GET['modulo']) ? trim((string)$_GET['modulo']) : '',
    'accion'      => isset($_GET['accion']) ? trim((string)$_GET['accion']) : '',
    'dni_usuario' => isset($_GET['dni_usuario']) ? trim((string)$_GET['dni_usuario']) : '',
    'buscar'      => isset($_GET['buscar']) ? trim((string)$_GET['buscar']) : ''
];

$pagina = isset($_GET['pagina']) ? max(1, (int)$_GET['pagina']) : 1;
$porPagina = isset($_GET['por_pagina']) ? max(10, min(100, (int)$_GET['por_pagina'])) : 25;

// Consultas a base de datos
$totalRegistros = $auditoriaModel->contarConFiltros($filtros);
$totalPaginas = max(1, (int)ceil($totalRegistros / $porPagina));
if ($pagina > $totalPaginas) {
    $pagina = $totalPaginas;
}

$registros = $auditoriaModel->listarConFiltros($filtros, $pagina, $porPagina);
$modulos = $auditoriaModel->obtenerModulos();
$acciones = $auditoriaModel->obtenerAcciones();
$usuarios = $auditoriaModel->obtenerUsuarios();

// Configuración de vista
$seccion = 'auditoria';
$css_modulo = 'auditoria';
$titulo_vista = 'Auditoría y Trazabilidad';
$titulo_pagina = 'Auditoría - Sistema de Control de Stock';

include_once __DIR__ . '/../layout/header.php';

// Helper de escape
$h = fn($v) => htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
?>

<div class="auditoria-container">
    
    <!-- Encabezado y Estadísticas -->
    <div class="auditoria-header">
        <div class="auditoria-header-title">
            <h2>Registro de Auditoría del Sistema</h2>
            <p>Trazabilidad completa e inmutable de operaciones, autenticaciones y modificaciones de registros.</p>
        </div>
        <div class="auditoria-stats">
            <span class="stat-pill">
                <span>Resultados:</span>
                <strong><?= number_format($totalRegistros, 0, ',', '.') ?></strong>
            </span>
            <span class="stat-pill">
                <span>Página:</span>
                <strong><?= $pagina ?> de <?= $totalPaginas ?></strong>
            </span>
        </div>
    </div>

    <!-- Formulario de Filtros y Búsqueda -->
    <div class="filtro-card">
        <form method="GET" action="auditoria.php" class="filtro-grid">
            
            <div class="filtro-group">
                <label for="fecha_desde">Fecha Desde</label>
                <input type="date" id="fecha_desde" name="fecha_desde" class="filtro-control" value="<?= $h($filtros['fecha_desde']) ?>">
            </div>

            <div class="filtro-group">
                <label for="fecha_hasta">Fecha Hasta</label>
                <input type="date" id="fecha_hasta" name="fecha_hasta" class="filtro-control" value="<?= $h($filtros['fecha_hasta']) ?>">
            </div>

            <div class="filtro-group">
                <label for="modulo">Módulo</label>
                <select id="modulo" name="modulo" class="filtro-control">
                    <option value="">-- Todos los módulos --</option>
                    <?php foreach ($modulos as $mod): ?>
                        <option value="<?= $h($mod) ?>" <?= ($filtros['modulo'] === $mod) ? 'selected' : '' ?>>
                            <?= $h($mod) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filtro-group">
                <label for="accion">Acción</label>
                <select id="accion" name="accion" class="filtro-control">
                    <option value="">-- Todas las acciones --</option>
                    <?php foreach ($acciones as $acc): ?>
                        <option value="<?= $h($acc) ?>" <?= ($filtros['accion'] === $acc) ? 'selected' : '' ?>>
                            <?= $h($acc) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filtro-group">
                <label for="dni_usuario">Usuario</label>
                <select id="dni_usuario" name="dni_usuario" class="filtro-control">
                    <option value="">-- Todos los usuarios --</option>
                    <?php foreach ($usuarios as $usr): ?>
                        <option value="<?= $h($usr['dni_usuario']) ?>" <?= ($filtros['dni_usuario'] === $usr['dni_usuario']) ? 'selected' : '' ?>>
                            <?= $h($usr['nombre_usuario'] ?? $usr['dni_usuario']) ?> (DNI <?= $h($usr['dni_usuario']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filtro-group" style="grid-column: span 2;">
                <label for="buscar">Búsqueda General</label>
                <input type="text" id="buscar" name="buscar" class="filtro-control" placeholder="Buscar por descripción, usuario, DNI o IP..." value="<?= $h($filtros['buscar']) ?>">
            </div>

            <div class="filtro-acciones">
                <button type="submit" class="btn-filtro btn-filtro-submit">
                    Filtrar
                </button>
                <a href="auditoria.php" class="btn-filtro btn-filtro-reset">
                    Limpiar
                </a>
            </div>

            <input type="hidden" name="por_pagina" value="<?= (int)$porPagina ?>">
        </form>
    </div>

    <!-- Tabla de Registros -->
    <div class="tabla-card">
        <div class="tabla-responsive">
            <table class="tabla-auditoria">
                <thead>
                    <tr>
                        <th>Fecha / Hora</th>
                        <th>Usuario</th>
                        <th>Rol</th>
                        <th>Módulo</th>
                        <th>Acción</th>
                        <th>Descripción</th>
                        <th>IP</th>
                        <th style="text-align: center;">Detalle</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($registros)): ?>
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 2.5rem; color: #64748b;">
                                No se encontraron registros de auditoría para los filtros aplicados.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($registros as $reg): ?>
                            <?php
                                $fechaHora = strtotime($reg['fecha_hora']);
                                $fechaFormato = date('d/m/Y', $fechaHora);
                                $horaFormato = date('H:i:s', $fechaHora);

                                $accionLower = strtolower($reg['accion']);
                                $claseAccion = 'badge-accion-' . preg_replace('/[^a-z0-9_]/', '_', $accionLower);

                                $tieneJson = (!empty($reg['datos_anteriores']) && $reg['datos_anteriores'] !== '{}' && $reg['datos_anteriores'] !== 'null')
                                          || (!empty($reg['datos_nuevos']) && $reg['datos_nuevos'] !== '{}' && $reg['datos_nuevos'] !== 'null');
                            ?>
                            <tr>
                                <td class="td-fecha">
                                    <?= $fechaFormato ?>
                                    <small><?= $horaFormato ?></small>
                                </td>
                                <td class="td-usuario">
                                    <?= $h($reg['nombre_usuario'] ?? 'Sistema / Anónimo') ?>
                                    <?php if (!empty($reg['dni_usuario'])): ?>
                                        <small>DNI: <?= $h($reg['dni_usuario']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($reg['rol_usuario'])): ?>
                                        <span class="badge badge-rol"><?= $h($reg['rol_usuario']) ?></span>
                                    <?php else: ?>
                                        <span style="color: #94a3b8;">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge badge-modulo"><?= $h($reg['modulo']) ?></span>
                                </td>
                                <td>
                                    <span class="badge <?= $claseAccion ?>"><?= $h($reg['accion']) ?></span>
                                </td>
                                <td>
                                    <?= $h($reg['descripcion']) ?>
                                </td>
                                <td class="td-ip">
                                    <?= $h($reg['ip'] ?? '-') ?>
                                </td>
                                <td style="text-align: center;">
                                    <?php if ($tieneJson): ?>
                                        <button type="button" class="btn-detalle" onclick="abrirModalDetalle(<?= (int)$reg['id_auditoria'] ?>)">
                                            Ver Detalle
                                        </button>
                                    <?php else: ?>
                                        <span style="font-size: 0.75rem; color: #94a3b8;">Sin cambios</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Paginación -->
        <?php if ($totalPaginas > 1): ?>
            <?php
                // Preservar query params
                $paramsQuery = $filtros;
                $paramsQuery['por_pagina'] = $porPagina;
                $urlPagina = function($num) use ($paramsQuery) {
                    $paramsQuery['pagina'] = $num;
                    return 'auditoria.php?' . http_build_query($paramsQuery);
                };
            ?>
            <div class="paginacion-bar">
                <div class="paginacion-info">
                    Mostrando <?= count($registros) ?> de <?= number_format($totalRegistros, 0, ',', '.') ?> registros
                </div>
                <div class="paginacion-links">
                    <a href="<?= $urlPagina(1) ?>" class="pag-link <?= ($pagina <= 1) ? 'disabled' : '' ?>" title="Primera página">&laquo;</a>
                    <a href="<?= $urlPagina(max(1, $pagina - 1)) ?>" class="pag-link <?= ($pagina <= 1) ? 'disabled' : '' ?>" title="Anterior">&lsaquo;</a>

                    <?php
                        $inicio = max(1, $pagina - 2);
                        $fin = min($totalPaginas, $pagina + 2);
                        for ($i = $inicio; $i <= $fin; $i++):
                    ?>
                        <a href="<?= $urlPagina($i) ?>" class="pag-link <?= ($pagina === $i) ? 'active' : '' ?>">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>

                    <a href="<?= $urlPagina(min($totalPaginas, $pagina + 1)) ?>" class="pag-link <?= ($pagina >= $totalPaginas) ? 'disabled' : '' ?>" title="Siguiente">&rsaquo;</a>
                    <a href="<?= $urlPagina($totalPaginas) ?>" class="pag-link <?= ($pagina >= $totalPaginas) ? 'disabled' : '' ?>" title="Última página">&raquo;</a>
                </div>
            </div>
        <?php endif; ?>

    </div>

</div>

<!-- Modal para Visualización de JSON (Antes / Después) -->
<div id="modalAuditoria" class="modal-auditoria-backdrop">
    <div class="modal-auditoria-card">
        <div class="modal-auditoria-header">
            <h3>Detalle de Cambios y Trazabilidad</h3>
            <button type="button" class="modal-btn-close" onclick="cerrarModalDetalle()">&times;</button>
        </div>
        <div class="modal-auditoria-body">
            
            <div class="modal-meta-grid">
                <div class="meta-item">
                    <strong>Fecha y Hora</strong>
                    <span id="modalMetaFecha">-</span>
                </div>
                <div class="meta-item">
                    <strong>Usuario</strong>
                    <span id="modalMetaUsuario">-</span>
                </div>
                <div class="meta-item">
                    <strong>Acción / Módulo</strong>
                    <span id="modalMetaAccion">-</span>
                </div>
                <div class="meta-item">
                    <strong>Dirección IP</strong>
                    <span id="modalMetaIp">-</span>
                </div>
                <div class="meta-item" style="grid-column: span 2;">
                    <strong>User Agent (Navegador)</strong>
                    <span id="modalMetaUserAgent" style="font-weight: 400; font-size: 0.78rem; word-break: break-all;">-</span>
                </div>
            </div>

            <div class="diff-container">
                <div class="diff-box diff-box--antes">
                    <div class="diff-box-title">
                        <span>Estado Anterior (ANTES)</span>
                    </div>
                    <pre id="modalJsonAntes" class="diff-pre">Sin datos anteriores</pre>
                </div>
                <div class="diff-box diff-box--despues">
                    <div class="diff-box-title">
                        <span>Estado Nuevo (DESPUÉS)</span>
                    </div>
                    <pre id="modalJsonDespues" class="diff-pre">Sin datos nuevos</pre>
                </div>
            </div>

        </div>
        <div class="modal-auditoria-footer">
            <button type="button" class="btn-filtro btn-filtro-reset" onclick="cerrarModalDetalle()">Cerrar</button>
        </div>
    </div>
</div>

<script>
function abrirModalDetalle(id) {
    const modal = document.getElementById('modalAuditoria');
    
    // Indicador de carga
    document.getElementById('modalMetaFecha').innerText = 'Cargando...';
    document.getElementById('modalMetaUsuario').innerText = 'Cargando...';
    document.getElementById('modalMetaAccion').innerText = 'Cargando...';
    document.getElementById('modalMetaIp').innerText = 'Cargando...';
    document.getElementById('modalMetaUserAgent').innerText = 'Cargando...';
    document.getElementById('modalJsonAntes').innerText = 'Cargando...';
    document.getElementById('modalJsonDespues').innerText = 'Cargando...';
    modal.classList.add('active');

    fetch(`auditoria.php?action=detalle&id=${encodeURIComponent(id)}`)
        .then(res => res.json())
        .then(res => {
            if (!res.success) {
                alert(res.error || 'Error al obtener el detalle de auditoría.');
                cerrarModalDetalle();
                return;
            }

            const d = res.data;
            document.getElementById('modalMetaFecha').innerText = d.fecha_hora || '-';
            document.getElementById('modalMetaUsuario').innerText = (d.nombre_usuario || 'Anónimo') + (d.dni_usuario ? ` (DNI: ${d.dni_usuario})` : '');
            document.getElementById('modalMetaAccion').innerText = `${d.accion} en [${d.modulo}]`;
            document.getElementById('modalMetaIp').innerText = d.ip || 'No registrada';
            document.getElementById('modalMetaUserAgent').innerText = d.user_agent || 'No registrado';

            // Formatear JSON anterior
            if (d.datos_anteriores) {
                try {
                    const parsedAntes = typeof d.datos_anteriores === 'string' ? JSON.parse(d.datos_anteriores) : d.datos_anteriores;
                    document.getElementById('modalJsonAntes').innerText = JSON.stringify(parsedAntes, null, 2);
                } catch (e) {
                    document.getElementById('modalJsonAntes').innerText = d.datos_anteriores;
                }
            } else {
                document.getElementById('modalJsonAntes').innerText = '{\n  // Sin datos previos registrados\n}';
            }

            // Formatear JSON nuevo
            if (d.datos_nuevos) {
                try {
                    const parsedDespues = typeof d.datos_nuevos === 'string' ? JSON.parse(d.datos_nuevos) : d.datos_nuevos;
                    document.getElementById('modalJsonDespues').innerText = JSON.stringify(parsedDespues, null, 2);
                } catch (e) {
                    document.getElementById('modalJsonDespues').innerText = d.datos_nuevos;
                }
            } else {
                document.getElementById('modalJsonDespues').innerText = '{\n  // Sin datos nuevos registrados\n}';
            }
        })
        .catch(err => {
            console.error('Error fetching auditoria detail:', err);
            alert('Error de conexión al cargar el detalle.');
            cerrarModalDetalle();
        });
}

function cerrarModalDetalle() {
    document.getElementById('modalAuditoria').classList.remove('active');
}

// Cerrar al hacer clic fuera de la tarjeta
document.getElementById('modalAuditoria').addEventListener('click', function(e) {
    if (e.target === this) {
        cerrarModalDetalle();
    }
});

// Cerrar con Escape
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        cerrarModalDetalle();
    }
});
</script>

<?php
include_once __DIR__ . '/../layout/footer.php';
