<?php
/**
 * usuario/dashboard.php
 * Vista principal del módulo de Gestión de Usuarios.
 *
 * - Datos mediante la clase Usuario (getAll, getRoles).
 * - Filtrado por texto/rol/estado y paginación en PHP sobre el listado de la clase.
 * - Interacción mediante fetch() sin recarga de página.
 */

require_once __DIR__ . '/../config/config.php';
if (!isAuthenticated()) {
    if (isset($_GET['ajax'])) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => 'Sesión no iniciada. Inicie sesión nuevamente.']);
        exit;
    }
    redirect('auth/login.php');
}

requireRole(['admin']);

$seccion = 'usuarios';
$css_modulo = 'usuarios';
$js_modulo = 'autocompletado'; // autocompletado de operario vinculado (se busca por AJAX)
$titulo_vista = 'Gestión de Usuarios';
$titulo_pagina = 'Gestión de Usuarios - Sistema de Control de Stock';

require_once __DIR__ . '/../clases/usuario.php';

$usuarioModel = new Usuario();

// =========================================================================
// FILTROS Y PAGINACIÓN (servidor)
// =========================================================================
$porPagina = 10;
$paginaActual = isset($_GET['pagina']) ? max(1, (int) $_GET['pagina']) : 1;
$busqueda = isset($_GET['busqueda']) ? trim((string) $_GET['busqueda']) : '';
$idRol = !empty($_GET['id_rol']) ? (int) $_GET['id_rol'] : null;
$estado = isset($_GET['estado']) ? trim((string) $_GET['estado']) : 'activo';

// getAll() no devuelve la columna "activo": se deduce comparando contra el listado de solo activos
$dnisActivos = array_column($usuarioModel->getAll(false), 'dni');
$todosUsuarios = array_map(function ($u) use ($dnisActivos) {
    $u['activo'] = in_array($u['dni'], $dnisActivos, true);
    return $u;
}, $usuarioModel->getAll(true));

$usuariosFiltrados = array_values(array_filter($todosUsuarios, function ($u) use ($busqueda, $idRol, $estado) {
    if ($estado === 'activo' && !$u['activo']) {
        return false;
    }
    if ($estado === 'inactivo' && $u['activo']) {
        return false;
    }
    if ($idRol !== null && (int) $u['id_rol'] !== $idRol) {
        return false;
    }
    $texto = $u['dni'] . ' ' . ($u['operario_nombre'] ?? '') . ' ' . ($u['operario_apellido'] ?? '') . ' ' . ($u['nombre_rol'] ?? '');
    if ($busqueda !== '' && mb_strpos(textoNormalizado($texto), textoNormalizado($busqueda)) === false) {
        return false;
    }
    return true;
}));

$totalRegistros = count($usuariosFiltrados);
$totalPaginas = max(1, (int) ceil($totalRegistros / $porPagina));
$paginaActual = min($paginaActual, $totalPaginas);
$usuarios = array_slice($usuariosFiltrados, ($paginaActual - 1) * $porPagina, $porPagina);

// =========================================================================
// RESPUESTA AJAX PARA BÚSQUEDA, FILTROS Y PAGINACIÓN DINÁMICA
// =========================================================================
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => true,
        'data' => $usuarios,
        'total_registros' => $totalRegistros,
        'total_paginas' => $totalPaginas,
        'pagina_actual' => $paginaActual
    ]);
    exit;
}

$roles = $usuarioModel->getRoles();

// Avisos de contraseña olvidada pendientes (con los datos del usuario para abrir su edición directamente)
require_once __DIR__ . '/../clases/solicitud_contrasena.php';
$solicitudesPendientes = SolicitudContrasena::pendientes();
$usuariosPorDni = [];
foreach ($todosUsuarios as $u) {
    $usuariosPorDni[$u['dni']] = $u;
}

include_once __DIR__ . '/../layout/header.php';
?>

<!-- Contenedor de Alertas Dinámicas -->
<div id="alerta-usuarios" class="alerta-mensaje"></div>

<?php if (!empty($solicitudesPendientes)): ?>
<!-- Solicitudes de cambio de contraseña (desde "¿Olvidaste tu contraseña?") -->
<div class="solicitudes-panel" id="panel-solicitudes">
    <div class="solicitudes-titulo">
        Solicitudes de cambio de contraseña
        <span class="menu-badge"><?= count($solicitudesPendientes) ?></span>
    </div>
    <?php foreach ($solicitudesPendientes as $s):
        $dniSol = htmlspecialchars($s['dni'], ENT_QUOTES, 'UTF-8');
        $nombreSol = htmlspecialchars(trim($s['apellido'] . ', ' . $s['nombre']), ENT_QUOTES, 'UTF-8');
        $fechaSol = date('d/m/Y H:i', strtotime($s['fecha_hora']));
        $jsonSol = isset($usuariosPorDni[$s['dni']])
            ? json_encode($usuariosPorDni[$s['dni']], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP)
            : null;
    ?>
        <div class="solicitud-item">
            <div class="solicitud-info">
                <strong><?= $nombreSol ?></strong> (DNI <?= $dniSol ?>)
                <span class="solicitud-fecha">olvidó su contraseña · <?= $fechaSol ?></span>
            </div>
            <div class="solicitud-acciones">
                <?php if ($jsonSol): ?>
                    <button type="button" class="btn-pill-action" onclick='abrirModalEditar(<?= $jsonSol ?>)'>Asignar nueva contraseña</button>
                <?php endif; ?>
                <button type="button" class="btn-pill-secondary" onclick="descartarSolicitud('<?= $dniSol ?>', this)">Descartar</button>
            </div>
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Barra de Filtros, Búsqueda y Botón Nuevo Usuario -->
<div class="action-bar-filters">
    <div class="search-input-wrapper">
        <input type="text" id="buscador-usuario" class="search-input" placeholder="Buscar por DNI, operario o rol..."
            value="<?= htmlspecialchars($busqueda, ENT_QUOTES, 'UTF-8') ?>">
    </div>

    <select class="filter-select" id="filtro-rol">
        <option value="">Todos los Roles</option>
        <?php foreach ($roles as $r): ?>
            <option value="<?= (int) $r['id_rol'] ?>" <?= ($idRol === (int) $r['id_rol']) ? 'selected' : '' ?>>
                <?= htmlspecialchars($r['rol'], ENT_QUOTES, 'UTF-8') ?>
            </option>
        <?php endforeach; ?>
    </select>

    <select class="filter-select" id="filtro-estado">
        <option value="activo" <?= ($estado === 'activo') ? 'selected' : '' ?>>Solo Activos</option>
        <option value="inactivo" <?= ($estado === 'inactivo') ? 'selected' : '' ?>>Solo Inactivos</option>
        <option value="todos" <?= ($estado === 'todos') ? 'selected' : '' ?>>Todos</option>
    </select>

    <?php if (isAdmin()): ?>
    <button type="button" class="btn-pill-action" onclick="abrirModalCrear()">Nuevo Usuario</button>
    <?php endif; ?>
</div>

<!-- Grilla de Usuarios -->
<div class="users-table-container">
    <div class="table-header-row">
        <div>#</div>
        <div>Usuario (DNI)</div>
        <div>Operario Vinculado</div>
        <div>DNI Operario</div>
        <div>Rol</div>
        <div>Estado</div>
        <div>Acciones</div>
    </div>

    <div id="tabla-usuarios-body">
        <?php if (empty($usuarios)): ?>
            <div class="empty-state">No se encontraron usuarios registrados con los criterios seleccionados.</div>
        <?php else: ?>
            <?php
            $filaNum = ($paginaActual - 1) * $porPagina + 1;
            foreach ($usuarios as $u):
                $dniEscapado = htmlspecialchars($u['dni'], ENT_QUOTES, 'UTF-8');
                $dniOpEscapado = !empty($u['dni_operario']) ? htmlspecialchars($u['dni_operario'], ENT_QUOTES, 'UTF-8') : '-';

                $tieneOp = !empty($u['operario_nombre']) || !empty($u['operario_apellido']);
                $opNombre = $tieneOp
                    ? htmlspecialchars(trim(($u['operario_apellido'] ?? '') . ', ' . ($u['operario_nombre'] ?? '')), ENT_QUOTES, 'UTF-8')
                    : 'Sin asociar';

                $rolNombre = htmlspecialchars($u['nombre_rol'] ?? $u['rol_nombre'] ?? 'Sin Rol', ENT_QUOTES, 'UTF-8');

                $esActivo = (bool) $u['activo'];
                $badgeEstado = $esActivo
                    ? "<span class='badge-status-activo'>Activo</span>"
                    : "<span class='badge-status-inactivo'>Inactivo</span>";

                $jsonUsuario = json_encode($u, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
                ?>
                <div class="user-card-row fila-clic">
                    <div><strong><?= $filaNum++ ?></strong></div>
                    <div class="celda-usuario"><img class="foto-mini" src="<?= htmlspecialchars($u['foto_url'], ENT_QUOTES, 'UTF-8') ?>" alt="" loading="lazy"><span><?= $dniEscapado ?></span></div>
                    <div><?= $opNombre ?></div>
                    <div><?= $dniOpEscapado ?></div>
                    <div><span class="badge-role"><?= $rolNombre ?></span></div>
                    <div><?= $badgeEstado ?></div>
                    <div class="actions-cell">
                        <button type="button" class="btn-action-icon view" title="Ver Detalle" onclick='abrirModalDetalle(<?= $jsonUsuario ?>)'><?= iconoAccion('view') ?></button>
                        <?php if (isAdmin()): ?>
                        <button type="button" class="btn-action-icon edit" title="Editar Usuario"
                            onclick='abrirModalEditar(<?= $jsonUsuario ?>)'>
                            <?= iconoAccion('edit') ?>
                        </button>
                        <?php if ($esActivo && (string)$u['dni'] === (string)($_SESSION['dni_usuario'] ?? '')): ?>
                            <?php /* Un administrador no puede deshabilitar su propio usuario */ ?>
                        <?php elseif ($esActivo): ?>
                            <button type="button" class="btn-action-icon disable" title="Deshabilitar Usuario"
                                onclick="abrirModalEstado('<?= $dniEscapado ?>', true)">
                                <?= iconoAccion('disable') ?>
                            </button>
                        <?php else: ?>
                            <button type="button" class="btn-action-icon enable" title="Habilitar Usuario"
                                onclick="abrirModalEstado('<?= $dniEscapado ?>', false)">
                                <?= iconoAccion('enable') ?>
                            </button>
                        <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Contenedor de Paginación Dinámica -->
<div class="pagination-container" id="contenedor-paginacion" data-total="<?= (int)$totalPaginas ?>" data-actual="<?= (int)$paginaActual ?>"></div>

<!-- ========================================================================= -->
<!-- MODALES -->
<!-- ========================================================================= -->

<!-- Modal 1: Nuevo Usuario -->
<div id="modal-nuevo-usuario" class="modal-overlay">
    <div class="modal-card">
        <button type="button" class="modal-close-btn" onclick="cerrarModal('modal-nuevo-usuario')" title="Cerrar" aria-label="Cerrar"><?= iconoAccion('close', 18) ?></button>
        <h2 class="modal-title">Nuevo Usuario</h2>
        <p class="modal-subtitle">Completar los campos obligatorios para registrar el acceso</p>

        <form id="form-crear-usuario" onsubmit="guardarNuevoUsuario(event)">
            <div class="form-grid-2">
                <div>
                    <label class="modal-label" for="crear-nombre">Nombre *</label>
                    <input type="text" id="crear-nombre" name="nombre" class="modal-input" required>
                </div>
                <div>
                    <label class="modal-label" for="crear-apellido">Apellido *</label>
                    <input type="text" id="crear-apellido" name="apellido" class="modal-input" required>
                </div>
            </div>

            <div class="form-grid-2">
                <div>
                    <label class="modal-label" for="crear-dni">DNI de Usuario *</label>
                    <input type="text" id="crear-dni" name="dni" class="modal-input" required
                        inputmode="numeric" pattern="\d{7,8}" minlength="7" maxlength="8"
                        title="El DNI debe tener 7 u 8 dígitos, solo números"
                        oninput="this.value = this.value.replace(/[^0-9]/g, '')"
                        placeholder="7 u 8 dígitos, sin puntos">
                </div>
                <div>
                    <label class="modal-label" for="crear-id-rol">Rol en el Sistema *</label>
                    <select id="crear-id-rol" name="id_rol" class="modal-select" required>
                        <option value="">Seleccione un rol...</option>
                        <?php foreach ($roles as $r): ?>
                            <option value="<?= (int) $r['id_rol'] ?>">
                                <?= htmlspecialchars($r['rol'], ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-grid-2">
                <div>
                    <label class="modal-label" for="crear-password">Contraseña *</label>
                    <input type="password" id="crear-password" name="password" class="modal-input" required
                        minlength="8" maxlength="72" autocomplete="new-password"
                        title="Mínimo 8 caracteres, con letras y números"
                        placeholder="Mín. 8 caracteres, letras y números">
                </div>
                <div>
                    <label class="modal-label" for="crear-buscar-operario">Operario Vinculado (Opcional)</label>
                    <div class="autocomplete-wrapper">
                        <input type="text" id="crear-buscar-operario" class="modal-input" placeholder="Escriba DNI, apellido o nombre... (vacío = sin operario)" autocomplete="off">
                        <input type="hidden" id="crear-id-operario" name="id_operario" value="">
                        <div id="crear-lista-operario" class="autocomplete-list"></div>
                    </div>
                </div>
            </div>

            <div class="foto-campo">
                <img id="crear-foto-vista" class="foto-vista" src="<?= htmlspecialchars(FotoPerfil::url(null), ENT_QUOTES, 'UTF-8') ?>" alt="Vista previa de la foto">
                <div>
                    <label class="modal-label" for="crear-foto">Foto de perfil (opcional)</label>
                    <input type="file" id="crear-foto" name="foto_perfil" class="modal-input" accept="image/jpeg,image/png,image/webp"
                        onchange="previsualizarFoto(this, 'crear-foto-vista')">
                    <small class="foto-ayuda">JPG, PNG o WEBP, hasta 2 MB. Sin foto se muestra una imagen genérica.</small>
                </div>
            </div>

            <div id="msg-error-crear" class="msg-error-modal" role="alert"></div>

            <div class="modal-actions-row">
                <button type="button" class="btn-modal-cancel"
                    onclick="cerrarModal('modal-nuevo-usuario')">Cancelar</button>
                <button type="submit" class="btn-modal-submit" id="btn-submit-crear">Guardar Usuario</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal 2: Editar Usuario -->
<div id="modal-editar-usuario" class="modal-overlay">
    <div class="modal-card">
        <button type="button" class="modal-close-btn" onclick="cerrarModal('modal-editar-usuario')" title="Cerrar" aria-label="Cerrar"><?= iconoAccion('close', 18) ?></button>
        <h2 class="modal-title">Editar Usuario</h2>
        <p class="modal-subtitle">Actualizar el rol, la vinculación u opcionalmente la contraseña</p>

        <form id="form-editar-usuario" onsubmit="guardarEdicionUsuario(event)">
            <div class="form-grid-2">
                <div>
                    <label class="modal-label" for="edit-nombre">Nombre</label>
                    <input type="text" id="edit-nombre" name="nombre" class="modal-input">
                </div>
                <div>
                    <label class="modal-label" for="edit-apellido">Apellido</label>
                    <input type="text" id="edit-apellido" name="apellido" class="modal-input">
                </div>
            </div>

            <div class="form-grid-2">
                <div>
                    <label class="modal-label" for="edit-dni">DNI de Usuario</label>
                    <input type="text" id="edit-dni" name="dni" class="modal-input" readonly>
                </div>
                <div>
                    <label class="modal-label" for="edit-id-rol">Rol en el Sistema *</label>
                    <select id="edit-id-rol" name="id_rol" class="modal-select" required>
                        <option value="">Seleccione un rol...</option>
                        <?php foreach ($roles as $r): ?>
                            <option value="<?= (int) $r['id_rol'] ?>">
                                <?= htmlspecialchars($r['rol'], ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-grid-2">
                <div>
                    <label class="modal-label" for="edit-password">Nueva Contraseña</label>
                    <input type="password" id="edit-password" name="password" class="modal-input"
                        minlength="8" maxlength="72" autocomplete="new-password"
                        title="Mínimo 8 caracteres, con letras y números"
                        placeholder="Dejar en blanco para conservar actual">
                </div>
                <div>
                    <label class="modal-label" for="edit-buscar-operario">Operario Vinculado (Opcional)</label>
                    <div class="autocomplete-wrapper">
                        <input type="text" id="edit-buscar-operario" class="modal-input" placeholder="Escriba DNI, apellido o nombre... (vacío = sin operario)" autocomplete="off">
                        <input type="hidden" id="edit-id-operario" name="id_operario" value="">
                        <div id="edit-lista-operario" class="autocomplete-list"></div>
                    </div>
                </div>
            </div>

            <div class="foto-campo">
                <img id="edit-foto-vista" class="foto-vista" src="<?= htmlspecialchars(FotoPerfil::url(null), ENT_QUOTES, 'UTF-8') ?>" alt="Foto de perfil actual">
                <div>
                    <label class="modal-label" for="edit-foto">Foto de perfil (opcional)</label>
                    <input type="file" id="edit-foto" name="foto_perfil" class="modal-input" accept="image/jpeg,image/png,image/webp"
                        onchange="previsualizarFoto(this, 'edit-foto-vista')">
                    <small class="foto-ayuda">JPG, PNG o WEBP, hasta 2 MB. Si elige una nueva, reemplaza a la actual.</small>
                    <label class="check-etiqueta foto-quitar">
                        <input type="checkbox" id="edit-quitar-foto" name="quitar_foto" value="1" onchange="alternarQuitarFoto(this.checked)">
                        <span>Quitar la foto (usar la imagen genérica)</span>
                    </label>
                </div>
            </div>

            <div id="msg-error-editar" class="msg-error-modal" role="alert"></div>

            <div class="modal-actions-row">
                <button type="button" class="btn-modal-cancel"
                    onclick="cerrarModal('modal-editar-usuario')">Cancelar</button>
                <button type="submit" class="btn-modal-submit" id="btn-submit-editar">Guardar Cambios</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Ver detalle del usuario (solo lectura; los datos vienen de la misma fila del listado) -->
<div id="modal-detalle-usuario" class="modal-overlay">
    <div class="modal-card">
        <button type="button" class="modal-close-btn" onclick="cerrarModal('modal-detalle-usuario')" title="Cerrar" aria-label="Cerrar"><?= iconoAccion('close', 18) ?></button>
        <h2 class="modal-title" id="detalle-usuario-titulo">Detalle del Usuario</h2>
        <p class="modal-subtitle" id="detalle-usuario-subtitulo"></p>

        <img id="detalle-usuario-foto" class="foto-detalle" src="" alt="Foto de perfil">

        <div class="detalle-usuario__datos">
            <div><strong>Rol:</strong> <span class="badge-role" id="detalle-usuario-rol"></span></div>
            <div><strong>Operario vinculado:</strong> <span id="detalle-usuario-operario"></span></div>
            <div><strong>Estado de la cuenta:</strong> <span id="detalle-usuario-estado"></span></div>
        </div>
    </div>
</div>

<!-- Modal 3: Confirmación de Deshabilitar / Habilitar Usuario -->
<div id="modal-estado-usuario" class="modal-overlay">
    <div class="modal-card">
        <button type="button" class="modal-close-btn" onclick="cerrarModal('modal-estado-usuario')" title="Cerrar" aria-label="Cerrar"><?= iconoAccion('close', 18) ?></button>
        <h2 class="modal-title" id="estado-titulo">Cambiar Estado</h2>
        <p class="modal-subtitle" id="estado-subtitulo">Confirmación de acción</p>

        <p id="estado-mensaje" class="modal-mensaje-estado"></p>

        <form id="form-estado-usuario" onsubmit="confirmarCambioEstado(event)">
            <input type="hidden" id="estado-dni" name="dni">
            <div class="modal-actions-row">
                <button type="button" class="btn-modal-cancel"
                    onclick="cerrarModal('modal-estado-usuario')">Cancelar</button>
                <button type="submit" class="btn-modal-submit" id="btn-submit-estado">Confirmar</button>
            </div>
        </form>
    </div>
</div>

<!-- ========================================================================= -->
<!-- LÓGICA JAVASCRIPT / AJAX (FETCH API) -->
<!-- ========================================================================= -->
<script>
    let paginaActual = <?= (int) $paginaActual ?>;
    let debounceTimeout = null;

    function escaparHtml(texto) {
        if (texto === null || texto === undefined) return '';
        return String(texto)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function mostrarAlerta(mensaje, esExito = true) {
        // Con un modal abierto, el error se muestra dentro de él (la alerta de página queda detrás)
        if (!esExito && mostrarErrorEnModal(mensaje)) return;
        const alerta = document.getElementById('alerta-usuarios');
        if (!alerta) return;
        alerta.className = 'alerta-mensaje ' + (esExito ? 'alerta-exito' : 'alerta-error');
        alerta.innerText = mensaje;
        alerta.style.display = 'block';
        window.scrollTo({ top: 0, behavior: 'smooth' });
        setTimeout(() => {
            alerta.style.display = 'none';
        }, 4500);
    }

    function cargarUsuarios(pagina = 1) {
        paginaActual = pagina;
        const busqueda = encodeURIComponent(document.getElementById('buscador-usuario').value.trim());
        const idRol = encodeURIComponent(document.getElementById('filtro-rol').value);
        const estado = encodeURIComponent(document.getElementById('filtro-estado').value);

        const url = `dashboard.php?ajax=1&pagina=${pagina}&busqueda=${busqueda}&id_rol=${idRol}&estado=${estado}`;

        fetch(url, { credentials: 'same-origin' })
            .then(response => {
                if (!response.ok) throw new Error('Error al conectar con el servidor.');
                return response.json();
            })
            .then(res => {
                if (!res.success) throw new Error(res.error || 'No se pudieron obtener los datos.');
                renderizarTabla(res.data, (res.pagina_actual - 1) * 10 + 1);
                renderizarPaginacion(res.total_paginas, res.pagina_actual);
            })
            .catch(err => {
                mostrarAlerta(err.message, false);
            });
    }

    function cambiarPagina(p) {
        cargarUsuarios(p);
    }

    function renderizarTabla(usuarios, numeroInicio) {
        const contenedor = document.getElementById('tabla-usuarios-body');
        if (!contenedor) return;

        if (!usuarios || usuarios.length === 0) {
            contenedor.innerHTML = '<div class="empty-state">No se encontraron usuarios registrados con los criterios seleccionados.</div>';
            return;
        }

        let html = '';
        let fila = numeroInicio;

        usuarios.forEach(u => {
            const dniEscapado = escaparHtml(u.dni);
            const dniOpEscapado = u.dni_operario ? escaparHtml(u.dni_operario) : '-';

            const tieneOp = Boolean(u.operario_nombre || u.operario_apellido);
            const opNombre = tieneOp
                ? escaparHtml(((u.operario_apellido || '') + ', ' + (u.operario_nombre || '')).trim())
                : 'Sin asociar';

            const rolNombre = escaparHtml(u.nombre_rol || '-');

            const esActivo = Boolean(u.activo == true || u.activo == 1 || u.activo === 't');
            const badgeEstado = esActivo
                ? '<span class="badge-status-activo">Activo</span>'
                : '<span class="badge-status-inactivo">Inactivo</span>';

            const jsonStr = JSON.stringify(u).replace(/"/g, '&quot;');

            const esMismoUsuario = String(u.dni) === String(APP_PERMISOS.dni);
            const btnEstado = !APP_PERMISOS.admin || (esActivo && esMismoUsuario)
                ? ''
                : esActivo
                ? `<button type="button" class="btn-action-icon disable" title="Deshabilitar Usuario" onclick="abrirModalEstado('${dniEscapado}', true)">${window.APP_ICONS.disable}</button>`
                : `<button type="button" class="btn-action-icon enable" title="Habilitar Usuario" onclick="abrirModalEstado('${dniEscapado}', false)">${window.APP_ICONS.enable}</button>`;

            html += `
        <div class="user-card-row fila-clic">
            <div><strong>${fila++}</strong></div>
            <div class="celda-usuario"><img class="foto-mini" src="${escaparHtml(u.foto_url || '')}" alt="" loading="lazy"><span>${dniEscapado}</span></div>
            <div>${opNombre}</div>
            <div>${dniOpEscapado}</div>
            <div><span class="badge-role">${rolNombre}</span></div>
            <div>${badgeEstado}</div>
            <div class="actions-cell">
                <button type="button" class="btn-action-icon view" title="Ver Detalle" onclick="abrirModalDetalle(${jsonStr})">${window.APP_ICONS.view}</button>
                ${APP_PERMISOS.admin ? `
                <button type="button" class="btn-action-icon edit" title="Editar Usuario" onclick="abrirModalEditar(${jsonStr})">
                    ${window.APP_ICONS.edit}
                </button>` : ''}
                ${btnEstado}
            </div>
        </div>`;
        });

        contenedor.innerHTML = html;
    }


    // Búsqueda y Filtros con Debounce
    document.getElementById('buscador-usuario').addEventListener('input', function () {
        clearTimeout(debounceTimeout);
        debounceTimeout = setTimeout(() => {
            cargarUsuarios(1);
        }, 350);
    });

    document.getElementById('filtro-rol').addEventListener('change', function () {
        cargarUsuarios(1);
    });

    document.getElementById('filtro-estado').addEventListener('change', function () {
        cargarUsuarios(1);
    });

    // Modal: Crear Usuario
    // Error de validación dentro del modal (la alerta del dashboard queda detrás); texto vacío = ocultarlo
    function mostrarErrorModal(id, mensaje) {
        const caja = document.getElementById(id);
        if (!caja) return;
        caja.innerText = mensaje || '';
        caja.style.display = mensaje ? 'block' : 'none';
    }

    // Foto de perfil: imagen genérica por defecto y vista previa de la que se elija
    const FOTO_GENERICA = <?= json_encode(FotoPerfil::url(null)) ?>;

    function previsualizarFoto(input, idImagen) {
        const img = document.getElementById(idImagen);
        const archivo = input.files && input.files[0];
        if (!archivo) {
            img.src = img.dataset.original || FOTO_GENERICA;
            return;
        }
        if (archivo.size > 2 * 1024 * 1024) {
            input.value = '';
            img.src = img.dataset.original || FOTO_GENERICA;
            mostrarAlerta('La foto de perfil supera el tamaño máximo permitido (2 MB).', false);
            return;
        }
        img.src = URL.createObjectURL(archivo);
        // Elegir una foto nueva anula "quitar la foto"
        const quitar = document.getElementById('edit-quitar-foto');
        if (quitar && idImagen === 'edit-foto-vista') quitar.checked = false;
    }

    function alternarQuitarFoto(quitar) {
        const img = document.getElementById('edit-foto-vista');
        if (quitar) {
            document.getElementById('edit-foto').value = '';
            img.src = FOTO_GENERICA;
        } else {
            img.src = img.dataset.original || FOTO_GENERICA;
        }
    }

    function abrirModalCrear() {
        document.getElementById('form-crear-usuario').reset();
        document.getElementById('crear-foto-vista').src = FOTO_GENERICA;
        mostrarErrorModal('msg-error-crear', '');
        abrirModal('modal-nuevo-usuario');
    }

    function guardarNuevoUsuario(e) {
        e.preventDefault();
        mostrarErrorModal('msg-error-crear', '');
        const btnSubmit = document.getElementById('btn-submit-crear');
        btnSubmit.disabled = true;
        btnSubmit.innerText = 'Guardando...';

        const form = document.getElementById('form-crear-usuario');
        const formData = new FormData(form);

        fetch('crear_usuario.php', {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        })
            .then(response => {
                if (!response.ok) throw new Error('Error al conectar con el servidor.');
                return response.json();
            })
            .then(res => {
                if (!res.success) throw new Error(res.error || 'No se pudo crear el usuario.');
                cerrarModal('modal-nuevo-usuario');
                form.reset();
                mostrarAlerta(res.message, true);
                cargarUsuarios(1);
            })
            .catch(err => {
                mostrarErrorModal('msg-error-crear', err.message);
            })
            .finally(() => {
                btnSubmit.disabled = false;
                btnSubmit.innerText = 'Guardar Usuario';
            });
    }

    // Modal: Ver detalle del usuario (los datos salen de la fila, no hace falta otra consulta)
    function abrirModalDetalle(usuario) {
        const nombre = ((usuario.apellido || '') + ', ' + (usuario.nombre || '')).replace(/^[ ,]+|[ ,]+$/g, '');
        document.getElementById('detalle-usuario-titulo').innerText = nombre !== '' ? nombre : usuario.dni;
        document.getElementById('detalle-usuario-subtitulo').innerText = 'DNI ' + usuario.dni;
        document.getElementById('detalle-usuario-rol').innerText = usuario.nombre_rol || 'Sin Rol';
        document.getElementById('detalle-usuario-foto').src = usuario.foto_url || '';

        const tieneOperario = Boolean(usuario.id_operario);
        document.getElementById('detalle-usuario-operario').innerHTML = tieneOperario
            ? `<a href="../operario/detalle_operario.php?id=${parseInt(usuario.id_operario, 10)}">${escaparHtml(((usuario.operario_apellido || '') + ', ' + (usuario.operario_nombre || '')).trim())}</a> (DNI ${escaparHtml(usuario.dni_operario || '-')})`
            : 'Ninguno';

        const activo = Boolean(usuario.activo == true || usuario.activo == 1 || usuario.activo === 't');
        document.getElementById('detalle-usuario-estado').innerText = activo ? 'Habilitada' : 'Deshabilitada';
        abrirModal('modal-detalle-usuario');
    }

    // Modal: Editar Usuario
    function descartarSolicitud(dni, boton) {
        boton.disabled = true;
        const formData = new FormData();
        formData.append('dni', dni);
        fetch('atender_solicitud.php', { method: 'POST', body: formData, credentials: 'same-origin' })
            .then(response => response.json())
            .then(res => {
                if (!res.success) throw new Error(res.error || 'No se pudo descartar la solicitud.');
                // Se recarga para actualizar el panel y el contador del menú
                window.location.reload();
            })
            .catch(err => {
                boton.disabled = false;
                mostrarAlerta(err.message, false);
            });
    }

    function abrirModalEditar(usuario) {
        document.getElementById('edit-dni').value = usuario.dni || '';
        document.getElementById('edit-id-rol').value = usuario.id_rol || '';
        document.getElementById('edit-password').value = '';
        document.getElementById('edit-id-operario').value = usuario.id_operario || '';
        document.getElementById('edit-buscar-operario').value = usuario.id_operario
            ? `${usuario.operario_apellido || ''}, ${usuario.operario_nombre || ''} - DNI: ${usuario.dni_operario || '-'}`
            : '';
        document.getElementById('edit-nombre').value = usuario.nombre || '';
        document.getElementById('edit-apellido').value = usuario.apellido || '';
        mostrarErrorModal('msg-error-editar', '');
        const fotoVista = document.getElementById('edit-foto-vista');
        fotoVista.dataset.original = usuario.foto_url || FOTO_GENERICA;
        fotoVista.src = fotoVista.dataset.original;
        document.getElementById('edit-foto').value = '';
        document.getElementById('edit-quitar-foto').checked = false;
        abrirModal('modal-editar-usuario');
    }

    function guardarEdicionUsuario(e) {
        e.preventDefault();
        mostrarErrorModal('msg-error-editar', '');
        const btnSubmit = document.getElementById('btn-submit-editar');
        btnSubmit.disabled = true;
        btnSubmit.innerText = 'Guardando...';

        const form = document.getElementById('form-editar-usuario');
        const formData = new FormData(form);

        fetch('editar_usuario.php', {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        })
            .then(response => {
                if (!response.ok) throw new Error('Error al conectar con el servidor.');
                return response.json();
            })
            .then(res => {
                if (!res.success) throw new Error(res.error || 'No se pudo actualizar el usuario.');
                cerrarModal('modal-editar-usuario');
                mostrarAlerta(res.message, true);
                cargarUsuarios(paginaActual);
            })
            .catch(err => {
                mostrarErrorModal('msg-error-editar', err.message);
            })
            .finally(() => {
                btnSubmit.disabled = false;
                btnSubmit.innerText = 'Guardar Cambios';
            });
    }

    // Modal: Deshabilitar / Habilitar Usuario
    function abrirModalEstado(dni, esActivo) {
        document.getElementById('estado-dni').value = dni;
        const titulo = document.getElementById('estado-titulo');
        const subtitulo = document.getElementById('estado-subtitulo');
        const mensaje = document.getElementById('estado-mensaje');
        const btnSubmit = document.getElementById('btn-submit-estado');

        if (esActivo) {
            titulo.innerText = 'Deshabilitar Usuario';
            subtitulo.innerText = 'Baja lógica del acceso';
            mensaje.innerHTML = `¿Está seguro de que desea deshabilitar el acceso para el usuario con DNI <strong>"${escaparHtml(dni)}"</strong>?`;
            btnSubmit.innerText = 'Deshabilitar';
        } else {
            titulo.innerText = 'Habilitar Usuario';
            subtitulo.innerText = 'Reactivación del acceso';
            mensaje.innerHTML = `¿Desea reactivar y habilitar el acceso para el usuario con DNI <strong>"${escaparHtml(dni)}"</strong>?`;
            btnSubmit.innerText = 'Habilitar';
        }

        abrirModal('modal-estado-usuario');
    }

    function confirmarCambioEstado(e) {
        e.preventDefault();
        const btnSubmit = document.getElementById('btn-submit-estado');
        const dni = document.getElementById('estado-dni').value;
        btnSubmit.disabled = true;
        btnSubmit.innerText = 'Procesando...';

        const formData = new FormData();
        formData.append('dni', dni);

        fetch('deshabilitar_usuario.php', {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        })
            .then(response => {
                if (!response.ok) throw new Error('Error al conectar con el servidor.');
                return response.json();
            })
            .then(res => {
                if (!res.success) throw new Error(res.error || 'No se pudo procesar la solicitud.');
                cerrarModal('modal-estado-usuario');
                mostrarAlerta(res.message, true);
                cargarUsuarios(paginaActual);
            })
            .catch(err => {
                mostrarAlerta(err.message, false);
            })
            .finally(() => {
                btnSubmit.disabled = false;
                btnSubmit.innerText = 'Confirmar';
            });
    }

    // Cerrar modales con tecla ESC
    // Autocompletado del operario vinculado (assets/js/autocompletado.js, se carga al final de la página)
    document.addEventListener('DOMContentLoaded', function () {
        ['crear', 'edit'].forEach(function (prefijo) {
            crearAutocompletado({
                input: document.getElementById(prefijo + '-buscar-operario'),
                lista: document.getElementById(prefijo + '-lista-operario'),
                url: '../ajax/buscar_autocompletado.php',
                parametros: { recurso: 'operario' },
                alSeleccionar: function (op) { document.getElementById(prefijo + '-id-operario').value = op.id; },
                alLimpiar: function () { document.getElementById(prefijo + '-id-operario').value = ''; }
            });
        });
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            cerrarModal('modal-nuevo-usuario');
            cerrarModal('modal-editar-usuario');
            cerrarModal('modal-estado-usuario');
            cerrarModal('modal-detalle-usuario');
        }
    });
</script>

<?php
include_once __DIR__ . '/../layout/footer.php';
?>