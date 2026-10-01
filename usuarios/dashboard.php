<?php
/**
 * usuarios/dashboard.php
 * Vista principal del módulo de Gestión de Usuarios.
 * 
 * Directivas obligatorias:
 * - Sin iconos ni emojis en ninguna parte de la interfaz.
 * - Uso exclusivo de los métodos de Usuario: contarFiltrados, getFiltrados, crear, editar, alternarEstado, getRoles.
 * - Toda interacción mediante Fetch API sin recarga de página.
 * - Botones de acción textuales: "Nuevo Usuario", "Editar", "Deshabilitar", "Habilitar".
 */

$seccion = 'usuarios';
$css_modulo = 'usuarios';
$titulo_vista = 'Gestión de Usuarios';
$titulo_pagina = 'Gestión de Usuarios - DAM';

require_once __DIR__ . '/../clases/usuario.php';

$usuarioModel = new Usuario();

// =========================================================================
// RESPUESTA AJAX PARA BÚSQUEDA, FILTROS Y PAGINACIÓN DINÁMICA
// =========================================================================
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    header('Content-Type: application/json; charset=utf-8');

    $porPagina = 10;
    $paginaActual = isset($_GET['pagina']) ? max(1, (int) $_GET['pagina']) : 1;
    $busqueda = isset($_GET['busqueda']) ? trim((string) $_GET['busqueda']) : '';
    $idRol = !empty($_GET['id_rol']) ? (int) $_GET['id_rol'] : null;
    $estado = isset($_GET['estado']) ? trim((string) $_GET['estado']) : 'activo';

    $filtros = [
        'busqueda' => $busqueda,
        'id_rol' => $idRol,
        'estado' => $estado,
        'limite' => $porPagina,
        'offset' => ($paginaActual - 1) * $porPagina
    ];

    $totalRegistros = $usuarioModel->contarFiltrados($filtros);
    $totalPaginas = max(1, (int) ceil($totalRegistros / $porPagina));
    $usuarios = $usuarioModel->getFiltrados($filtros);

    echo json_encode([
        'success' => true,
        'data' => $usuarios,
        'total_registros' => $totalRegistros,
        'total_paginas' => $totalPaginas,
        'pagina_actual' => $paginaActual
    ]);
    exit;
}

// =========================================================================
// CARGA INICIAL (RENDERIZADO EN SERVIDOR)
// =========================================================================
$porPagina = 10;
$paginaActual = isset($_GET['pagina']) ? max(1, (int) $_GET['pagina']) : 1;
$busqueda = isset($_GET['busqueda']) ? trim((string) $_GET['busqueda']) : '';
$idRol = !empty($_GET['id_rol']) ? (int) $_GET['id_rol'] : null;
$estado = isset($_GET['estado']) ? trim((string) $_GET['estado']) : 'activo';

$filtros = [
    'busqueda' => $busqueda,
    'id_rol' => $idRol,
    'estado' => $estado,
    'limite' => $porPagina,
    'offset' => ($paginaActual - 1) * $porPagina
];

$totalRegistros = $usuarioModel->contarFiltrados($filtros);
$totalPaginas = max(1, (int) ceil($totalRegistros / $porPagina));
$usuarios = $usuarioModel->getFiltrados($filtros);
$roles = $usuarioModel->getRoles();

include_once __DIR__ . '/../layouts/header.php';
?>

<!-- Contenedor de Alertas Dinámicas -->
<div id="alerta-usuarios" class="alerta-mensaje"></div>

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

    <button type="button" class="btn-pill-action" onclick="abrirModalCrear()">Nuevo Usuario</button>
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
                <div class="user-card-row">
                    <div><strong><?= $filaNum++ ?></strong></div>
                    <div><?= $dniEscapado ?></div>
                    <div><?= $opNombre ?></div>
                    <div><?= $dniOpEscapado ?></div>
                    <div><span class="badge-role"><?= $rolNombre ?></span></div>
                    <div><?= $badgeEstado ?></div>
                    <div class="actions-cell">
                        <button type="button" class="btn-action-text edit" title="Editar Usuario"
                            onclick='abrirModalEditar(<?= $jsonUsuario ?>)'>
                            Editar
                        </button>
                        <?php if ($esActivo): ?>
                            <button type="button" class="btn-action-text disable" title="Deshabilitar Usuario"
                                onclick="abrirModalEstado('<?= $dniEscapado ?>', true)">
                                Deshabilitar
                            </button>
                        <?php else: ?>
                            <button type="button" class="btn-action-text view" title="Habilitar Usuario"
                                onclick="abrirModalEstado('<?= $dniEscapado ?>', false)">
                                Habilitar
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Contenedor de Paginación Dinámica -->
<div class="pagination-container" id="contenedor-paginacion">
    <?php if ($totalPaginas > 1): ?>
        <button type="button" class="pagination-btn text-label" onclick="cambiarPagina(<?= max(1, $paginaActual - 1) ?>)"
            <?= ($paginaActual <= 1) ? 'disabled' : '' ?>>
            Anterior
        </button>
        <?php for ($p = 1; $p <= $totalPaginas; $p++): ?>
            <button type="button" class="pagination-btn <?= ($p === $paginaActual) ? 'active' : '' ?>"
                onclick="cambiarPagina(<?= $p ?>)">
                <?= $p ?>
            </button>
        <?php endfor; ?>
        <button type="button" class="pagination-btn text-label"
            onclick="cambiarPagina(<?= min($totalPaginas, $paginaActual + 1) ?>)" <?= ($paginaActual >= $totalPaginas) ? 'disabled' : '' ?>>
            Siguiente
        </button>
    <?php endif; ?>
</div>

<!-- ========================================================================= -->
<!-- MODALES -->
<!-- ========================================================================= -->

<!-- Modal 1: Nuevo Usuario -->
<div id="modal-nuevo-usuario" class="modal-overlay">
    <div class="modal-card">
        <button type="button" class="modal-close-btn" onclick="cerrarModal('modal-nuevo-usuario')"
            title="Cerrar">Cerrar</button>
        <h2 class="modal-title">Nuevo Usuario</h2>
        <p class="modal-subtitle">Completar los campos obligatorios para registrar el acceso</p>

        <form id="form-crear-usuario" onsubmit="guardarNuevoUsuario(event)">
            <div class="form-grid-2">
                <div>
                    <label class="modal-label" for="crear-dni">DNI de Usuario *</label>
                    <input type="text" id="crear-dni" name="dni" class="modal-input" required
                        placeholder="Número de documento">
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
                        placeholder="Clave de acceso">
                </div>
                <div>
                    <label class="modal-label" for="crear-dni-operario">DNI Operario (Opcional)</label>
                    <input type="text" id="crear-dni-operario" name="dni_operario" class="modal-input"
                        placeholder="DNI del operario vinculado">
                </div>
            </div>

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
        <button type="button" class="modal-close-btn" onclick="cerrarModal('modal-editar-usuario')"
            title="Cerrar">Cerrar</button>
        <h2 class="modal-title">Editar Usuario</h2>
        <p class="modal-subtitle">Actualizar el rol, la vinculación u opcionalmente la contraseña</p>

        <form id="form-editar-usuario" onsubmit="guardarEdicionUsuario(event)">
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
                        placeholder="Dejar en blanco para conservar actual">
                </div>
                <div>
                    <label class="modal-label" for="edit-dni-operario">DNI Operario (Opcional)</label>
                    <input type="text" id="edit-dni-operario" name="dni_operario" class="modal-input"
                        placeholder="DNI del operario vinculado">
                </div>
            </div>

            <div class="modal-actions-row">
                <button type="button" class="btn-modal-cancel"
                    onclick="cerrarModal('modal-editar-usuario')">Cancelar</button>
                <button type="submit" class="btn-modal-submit" id="btn-submit-editar">Guardar Cambios</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal 3: Confirmación de Deshabilitar / Habilitar Usuario -->
<div id="modal-estado-usuario" class="modal-overlay">
    <div class="modal-card">
        <button type="button" class="modal-close-btn" onclick="cerrarModal('modal-estado-usuario')"
            title="Cerrar">Cerrar</button>
        <h2 class="modal-title" id="estado-titulo">Cambiar Estado</h2>
        <p class="modal-subtitle" id="estado-subtitulo">Confirmación de acción</p>

        <p id="estado-mensaje"
            style="font-size: 0.95rem; color: #334155; margin: 1.5rem 0; text-align: center; line-height: 1.5;"></p>

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

            const btnEstado = esActivo
                ? `<button type="button" class="btn-action-text disable" title="Deshabilitar Usuario" onclick="abrirModalEstado('${dniEscapado}', true)">Deshabilitar</button>`
                : `<button type="button" class="btn-action-text view" title="Habilitar Usuario" onclick="abrirModalEstado('${dniEscapado}', false)">Habilitar</button>`;

            html += `
        <div class="user-card-row">
            <div><strong>${fila++}</strong></div>
            <div>${dniEscapado}</div>
            <div>${opNombre}</div>
            <div>${dniOpEscapado}</div>
            <div><span class="badge-role">${rolNombre}</span></div>
            <div>${badgeEstado}</div>
            <div class="actions-cell">
                <button type="button" class="btn-action-text edit" title="Editar Usuario" onclick="abrirModalEditar(${jsonStr})">
                    Editar
                </button>
                ${btnEstado}
            </div>
        </div>`;
        });

        contenedor.innerHTML = html;
    }

    function renderizarPaginacion(totalPaginas, pagina) {
        const contenedor = document.getElementById('contenedor-paginacion');
        if (!contenedor) return;

        if (totalPaginas <= 1) {
            contenedor.innerHTML = '';
            return;
        }

        let html = '';
        html += `<button type="button" class="pagination-btn text-label" onclick="cambiarPagina(${Math.max(1, pagina - 1)})" ${pagina <= 1 ? 'disabled' : ''}>Anterior</button>`;

        for (let p = 1; p <= totalPaginas; p++) {
            html += `<button type="button" class="pagination-btn ${p === pagina ? 'active' : ''}" onclick="cambiarPagina(${p})">${p}</button>`;
        }

        html += `<button type="button" class="pagination-btn text-label" onclick="cambiarPagina(${Math.min(totalPaginas, pagina + 1)})" ${pagina >= totalPaginas ? 'disabled' : ''}>Siguiente</button>`;

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
    function abrirModalCrear() {
        document.getElementById('form-crear-usuario').reset();
        abrirModal('modal-nuevo-usuario');
    }

    function guardarNuevoUsuario(e) {
        e.preventDefault();
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
                mostrarAlerta(err.message, false);
            })
            .finally(() => {
                btnSubmit.disabled = false;
                btnSubmit.innerText = 'Guardar Usuario';
            });
    }

    // Modal: Editar Usuario
    function abrirModalEditar(usuario) {
        document.getElementById('edit-dni').value = usuario.dni || '';
        document.getElementById('edit-id-rol').value = usuario.id_rol || '';
        document.getElementById('edit-password').value = '';
        document.getElementById('edit-dni-operario').value = usuario.dni_operario || '';
        abrirModal('modal-editar-usuario');
    }

    function guardarEdicionUsuario(e) {
        e.preventDefault();
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
                mostrarAlerta(err.message, false);
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
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            cerrarModal('modal-nuevo-usuario');
            cerrarModal('modal-editar-usuario');
            cerrarModal('modal-estado-usuario');
        }
    });
</script>

<?php
include_once __DIR__ . '/../layouts/footer.php';
?>