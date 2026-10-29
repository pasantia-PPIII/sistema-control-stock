<?php
// auditoria/dashboard.php
// Auditoría del sistema: cambios en registros, cambios de stock, accesos y movimientos.
// Exclusivo del rol Administrador. Las consultas están en clases/auditoria.php.
// config.php inicia la sesión con la cookie endurecida; la autenticación se valida antes de consultar la BD.
require_once __DIR__ . '/../config/config.php';

if (!isAuthenticated()) {
    redirect('auth/login.php');
}
requireRole(['admin']);

$seccion = 'auditoria';
$css_modulo = 'auditoria';
$js_modulo = 'autocompletado'; // filtro de usuario con búsqueda predictiva (assets/js/auditoria.js se carga al final de esta página)
$titulo_vista = 'Auditoría';
$titulo_pagina = 'Auditoría - Sistema de Control de Stock';

require_once __DIR__ . '/../clases/auditoria.php';

// ---------------------------------------------------------------------------------------------
// Pestañas
// ---------------------------------------------------------------------------------------------
$pestanias = [
    'cambios' => 'Cambios en registros',
    'stock' => 'Cambios de stock',
    'accesos' => 'Accesos al sistema',
    'movimientos' => 'Movimientos',
];
$tab = $_GET['tab'] ?? 'cambios';
if (!isset($pestanias[$tab])) {
    $tab = 'cambios';
}

// ---------------------------------------------------------------------------------------------
// Utilidades de presentación
// ---------------------------------------------------------------------------------------------
$h = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

const AU_POR_PAGINA = 10;   // igual que el resto de los listados del sistema

/** Número al estilo local (1.234,5), sin ceros decimales sobrantes. */
function au_num($valor, int $decimales = 2): string
{
    $txt = number_format((float) $valor, $decimales, ',', '.');
    if ($decimales > 0) {
        $txt = rtrim(rtrim($txt, '0'), ',');
    }
    return $txt;
}

/** Fecha y hora (d/m/Y H:i:s) de un timestamp de la base. */
function au_fecha_hora($valor): string
{
    return $valor ? date('d/m/Y H:i:s', strtotime((string) $valor)) : '-';
}

/** URL de esta página con los parámetros actuales y los $cambios indicados (null o '' los quita). */
function au_url(array $cambios): string
{
    $params = array_filter(array_merge($_GET, $cambios), fn($v) => $v !== null && $v !== '');
    return 'dashboard.php?' . http_build_query($params);
}

/** Página pedida en $_GET['p'] (por defecto la 1). */
function au_pagina(): int
{
    return max(1, (int) ($_GET['p'] ?? 1));
}

/** Navegación entre páginas de un listado. No muestra nada si todo entra en una sola página. */
function au_paginador(array $p): void
{
    if ($p['paginas'] <= 1) {
        return;
    }
    $e = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
    $enlace = fn(int $n) => $e(au_url(['p' => $n > 1 ? $n : null]));

    // Ventana de 10 botones numéricos, igual que la paginación de los demás listados del sistema
    $ventana = 10;
    $inicio = max(1, min($p['pagina'] - 4, $p['paginas'] - $ventana + 1));
    $fin = min($p['paginas'], $inicio + $ventana - 1);

    echo '<nav class="au-paginacion" aria-label="Paginación del listado">';
    echo '<span class="au-paginacion__info">Mostrando ' . $p['desde'] . '-' . $p['hasta'] . ' de ' . $p['total'] . '</span>';
    echo '<span class="au-paginacion__botones">';
    echo $p['pagina'] > 1
        ? '<a class="au-pag au-pag--texto" href="' . $enlace($p['pagina'] - 1) . '">Anterior</a>'
        : '<span class="au-pag au-pag--texto au-pag--off">Anterior</span>';
    for ($n = $inicio; $n <= $fin; $n++) {
        echo $n === $p['pagina']
            ? '<span class="au-pag au-pag--actual" aria-current="page">' . $n . '</span>'
            : '<a class="au-pag" href="' . $enlace($n) . '" aria-label="Página ' . $n . '">' . $n . '</a>';
    }
    echo $p['pagina'] < $p['paginas']
        ? '<a class="au-pag au-pag--texto" href="' . $enlace($p['pagina'] + 1) . '">Siguiente</a>'
        : '<span class="au-pag au-pag--texto au-pag--off">Siguiente</span>';
    echo '</span></nav>';
}

/** Pastilla de color según el evento de acceso. */
function au_clase_evento(string $evento): string
{
    return match ($evento) {
        Auditoria::LOGIN_OK => 'ok',
        Auditoria::LOGIN_FALLIDO => 'alerta',
        Auditoria::LOGIN_BLOQUEADO => 'aviso',
        default => 'neutra',
    };
}

/** Pastilla de color según la acción sobre un registro. */
function au_clase_accion(string $accion): string
{
    return match ($accion) {
        Auditoria::CREAR, Auditoria::HABILITAR, Auditoria::REACTIVAR => 'ok',
        Auditoria::EDITAR => 'info',
        Auditoria::DESHABILITAR, Auditoria::ANULAR => 'alerta',
        default => 'aviso',
    };
}

// ---------------------------------------------------------------------------------------------
// Filtros (GET)
// ---------------------------------------------------------------------------------------------
$texto = fn(string $k) => trim((string) ($_GET[$k] ?? ''));
$desde = $texto('desde');
$hasta = $texto('hasta');
$buscar = $texto('buscar');

// Una fecha que no existe (p. ej. 2026-13-45) se descarta y se avisa; la clase también la ignora.
if ($desde !== '' && !Auditoria::fechaValida($desde)) {
    $desde = '';
    setAlert('La fecha "desde" no es válida; se ignoró ese filtro.', 'warning');
}
if ($hasta !== '' && !Auditoria::fechaValida($hasta)) {
    $hasta = '';
    setAlert('La fecha "hasta" no es válida; se ignoró ese filtro.', 'warning');
}

$filtros = [
    'desde' => $desde,
    'hasta' => $hasta,
    'busqueda' => $buscar,
    'evento' => $texto('evento'),
    'accion' => $texto('accion'),
    'entidad' => $texto('entidad'),
    'origen' => $texto('origen'),
    'usuario' => $texto('usuario'),
];

// Texto del usuario elegido en el filtro (si el DNI no es de un usuario registrado, se muestra el DNI)
$usuarioTexto = '';
if ($filtros['usuario'] !== '') {
    require_once __DIR__ . '/../clases/usuario.php';
    $u = (new Usuario())->getByDni($filtros['usuario'], true);
    $usuarioTexto = $u ? $u['apellido'] . ', ' . $u['nombre'] . ' - DNI: ' . $u['dni'] : $filtros['usuario'];
}

// ---------------------------------------------------------------------------------------------
// Datos: las tarjetas se calculan siempre; el detalle, solo de la pestaña activa
// ---------------------------------------------------------------------------------------------
$hace30 = date('Y-m-d', strtotime('-30 days'));
$resumen = Auditoria::resumen($hace30, date('Y-m-d'));

switch ($tab) {
    case 'cambios':
        $pg = Auditoria::cambiosPaginado($filtros, au_pagina(), AU_POR_PAGINA);
        break;
    case 'stock':
        $pg = Auditoria::stockPaginado($filtros, au_pagina(), AU_POR_PAGINA);
        break;
    case 'accesos':
        $pg = Auditoria::accesosPaginado($filtros, au_pagina(), AU_POR_PAGINA);
        break;
    case 'movimientos':
        $periodo = $_GET['periodo'] ?? 'hoy';
        $tipo_mov = $texto('tipo_mov');
        $pg = Auditoria::movimientosPaginado(au_pagina(), AU_POR_PAGINA, $periodo, $desde ?: null, $hasta ?: null, $buscar ?: null, $tipo_mov ?: null, $filtros['usuario'] ?: null);
        break;
}

require_once __DIR__ . '/../layout/header.php';
?>

<!-- RESUMEN DE LOS ÚLTIMOS 30 DÍAS -->
<div class="au-kpis">
    <a class="au-kpi au-kpi--ok" href="<?= $h('dashboard.php?' . http_build_query(['tab' => 'accesos', 'evento' => Auditoria::LOGIN_OK, 'desde' => $hace30])) ?>">
        <span class="au-kpi__titulo">Ingresos correctos</span>
        <span class="au-kpi__valor"><?= $resumen['accesos_ok'] ?></span>
        <span class="au-kpi__detalle">últimos 30 días</span>
    </a>
    <a class="au-kpi <?= $resumen['accesos_fallidos'] > 0 ? 'au-kpi--alerta' : 'au-kpi--ok' ?>"
        href="<?= $h('dashboard.php?' . http_build_query(['tab' => 'accesos', 'evento' => Auditoria::LOGIN_FALLIDO, 'desde' => $hace30])) ?>">
        <span class="au-kpi__titulo">Ingresos fallidos</span>
        <span class="au-kpi__valor"><?= $resumen['accesos_fallidos'] ?></span>
        <span class="au-kpi__detalle">últimos 30 días</span>
    </a>
    <a class="au-kpi" href="<?= $h('dashboard.php?' . http_build_query(['tab' => 'cambios', 'desde' => $hace30])) ?>">
        <span class="au-kpi__titulo">Cambios en registros</span>
        <span class="au-kpi__valor"><?= $resumen['cambios'] ?></span>
        <span class="au-kpi__detalle">últimos 30 días</span>
    </a>
    <a class="au-kpi" href="<?= $h('dashboard.php?' . http_build_query(['tab' => 'stock', 'desde' => $hace30])) ?>">
        <span class="au-kpi__titulo">Cambios de stock</span>
        <span class="au-kpi__valor"><?= $resumen['cambios_stock'] ?></span>
        <span class="au-kpi__detalle">últimos 30 días</span>
    </a>
</div>

<!-- PESTAÑAS -->
<nav class="au-tabs" aria-label="Secciones de auditoría">
    <?php foreach ($pestanias as $clave => $titulo): ?>
        <a href="dashboard.php?tab=<?= $h($clave) ?>" class="au-tab <?= $tab === $clave ? 'au-tab--activa' : '' ?>"
            <?= $tab === $clave ? 'aria-current="page"' : '' ?>><?= $h($titulo) ?></a>
    <?php endforeach; ?>
</nav>

<div class="au-panel">

<?php if ($tab === 'movimientos'): ?>
    <?php
    // Botón de período activo (solo si no hay rango de fechas manual)
    $periodoActivo = fn(string $p) => ($periodo === $p && !$desde && !$hasta) ? ' au-periodo--activo' : '';
    $periodos = ['hoy' => 'Diario (Hoy)', 'semana' => 'Semanal', 'mes' => 'Mensual', 'anio' => 'Anual'];
    ?>
    <div class="au-periodos">
        <span class="au-periodos__titulo">Período:</span>
        <?php foreach ($periodos as $clave => $titulo): ?>
            <a class="au-periodo<?= $periodoActivo($clave) ?>"
                href="<?= $h(au_url(['periodo' => $clave, 'desde' => null, 'hasta' => null, 'p' => null])) ?>"><?= $h($titulo) ?></a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

    <form method="GET" action="dashboard.php" class="au-filtros">
        <input type="hidden" name="tab" value="<?= $h($tab) ?>">
        <?php if ($tab === 'movimientos'): ?>
            <input type="hidden" name="periodo" value="<?= $h($periodo) ?>">
        <?php endif; ?>

        <div class="au-campo au-campo--buscar">
            <label class="au-campo__etiqueta" for="au-buscar">Buscar</label>
            <input type="text" id="au-buscar" name="buscar" value="<?= $h($buscar) ?>" class="au-input"
                placeholder="<?= $tab === 'movimientos' ? 'Responsable, operario o nota...' : 'Usuario, DNI, descripción...' ?>">
        </div>

        <?php if ($tab === 'cambios'): ?>
            <div class="au-campo">
                <label class="au-campo__etiqueta" for="au-entidad">Registro</label>
                <select id="au-entidad" name="entidad" class="au-input">
                    <option value="">Todos</option>
                    <?php foreach (Auditoria::ENTIDADES as $valor => $etiqueta): ?>
                        <option value="<?= $h($valor) ?>" <?= $filtros['entidad'] === $valor ? 'selected' : '' ?>><?= $h($etiqueta) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="au-campo">
                <label class="au-campo__etiqueta" for="au-accion">Acción</label>
                <select id="au-accion" name="accion" class="au-input">
                    <option value="">Todas</option>
                    <?php foreach (Auditoria::ACCIONES as $valor => $etiqueta): ?>
                        <option value="<?= $h($valor) ?>" <?= $filtros['accion'] === $valor ? 'selected' : '' ?>><?= $h($etiqueta) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php elseif ($tab === 'stock'): ?>
            <div class="au-campo">
                <label class="au-campo__etiqueta" for="au-origen">Origen</label>
                <select id="au-origen" name="origen" class="au-input">
                    <option value="">Todos</option>
                    <?php foreach (Auditoria::ORIGENES as $valor => $etiqueta): ?>
                        <option value="<?= $h($valor) ?>" <?= $filtros['origen'] === $valor ? 'selected' : '' ?>><?= $h($etiqueta) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php elseif ($tab === 'accesos'): ?>
            <div class="au-campo">
                <label class="au-campo__etiqueta" for="au-evento">Evento</label>
                <select id="au-evento" name="evento" class="au-input">
                    <option value="">Todos</option>
                    <?php foreach (Auditoria::EVENTOS as $valor => $etiqueta): ?>
                        <option value="<?= $h($valor) ?>" <?= $filtros['evento'] === $valor ? 'selected' : '' ?>><?= $h($etiqueta) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php else: ?>
            <div class="au-campo">
                <label class="au-campo__etiqueta" for="au-tipo">Tipo</label>
                <select id="au-tipo" name="tipo_mov" class="au-input">
                    <option value="">Todos</option>
                    <?php foreach (['Entrega' => 'Entrega', 'Devolución' => 'Devolución', 'Ingreso' => 'Ingreso', 'Ajuste de Inventario' => 'Ajuste'] as $valor => $etiqueta): ?>
                        <option value="<?= $h($valor) ?>" <?= $tipo_mov === $valor ? 'selected' : '' ?>><?= $h($etiqueta) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>

        <div class="au-campo au-campo--usuario">
            <label class="au-campo__etiqueta" for="au-buscar-usuario">Usuario</label>
            <div class="autocomplete-wrapper">
                <input type="text" id="au-buscar-usuario" class="au-input" value="<?= $h($usuarioTexto) ?>"
                    placeholder="DNI, apellido o nombre... (vacío = todos)" autocomplete="off">
                <input type="hidden" id="au-usuario" name="usuario" value="<?= $h($filtros['usuario']) ?>">
                <div id="au-lista-usuario" class="autocomplete-list"></div>
            </div>
        </div>

        <div class="au-campo">
            <label class="au-campo__etiqueta" for="au-desde">Desde</label>
            <input type="date" id="au-desde" name="desde" value="<?= $h($desde) ?>" class="au-input">
        </div>
        <div class="au-campo">
            <label class="au-campo__etiqueta" for="au-hasta">Hasta</label>
            <input type="date" id="au-hasta" name="hasta" value="<?= $h($hasta) ?>" class="au-input">
        </div>

        <button type="submit" class="au-btn">Filtrar</button>
        <a href="dashboard.php?tab=<?= $h($tab) ?>" class="au-btn au-btn--sec">Limpiar</a>
    </form>

    <div class="au-tabla-wrap">
        <table class="au-tabla">
            <?php if ($tab === 'cambios'): ?>
                <thead>
                    <tr>
                        <th>Fecha y hora</th>
                        <th>Usuario</th>
                        <th>Acción</th>
                        <th>Registro</th>
                        <th>Descripción</th>
                        <th class="der">Detalle</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($pg['filas'])): ?>
                        <tr><td colspan="6" class="au-vacio">No se encontraron cambios para los filtros aplicados.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($pg['filas'] as $c): ?>
                        <?php
                        $entidadTxt = Auditoria::ENTIDADES[$c['entidad']] ?? $c['entidad'];
                        $accionTxt = Auditoria::ACCIONES[$c['accion']] ?? $c['accion'];
                        $meta = au_fecha_hora($c['fecha_hora']) . ' · ' . $c['nombre_usuario'] . ' (DNI ' . $c['dni_usuario'] . ')'
                            . ($c['ip'] ? ' · IP ' . $c['ip'] : '');
                        ?>
                        <tr>
                            <td class="au-fecha"><?= $h(au_fecha_hora($c['fecha_hora'])) ?></td>
                            <td><strong><?= $h($c['nombre_usuario']) ?></strong><br><span class="au-suave">DNI <?= $h($c['dni_usuario']) ?></span></td>
                            <td><span class="au-pastilla au-pastilla--<?= au_clase_accion($c['accion']) ?>"><?= $h($accionTxt) ?></span></td>
                            <td><?= $h($entidadTxt) ?><br><span class="au-suave"><?= $h($c['id_registro']) ?></span></td>
                            <td class="au-desc"><?= $h($c['descripcion']) ?></td>
                            <td class="der">
                                <?php if ($c['datos_anteriores'] !== null || $c['datos_nuevos'] !== null): ?>
                                    <button type="button" class="au-btn au-btn--sec au-btn--chico" onclick="abrirDetalleCambio(this)"
                                        data-titulo="<?= $h($accionTxt . ' · ' . $entidadTxt . ' ' . $c['id_registro']) ?>"
                                        data-meta="<?= $h($meta) ?>"
                                        data-antes="<?= $h(json_encode($c['datos_anteriores'], JSON_UNESCAPED_UNICODE)) ?>"
                                        data-despues="<?= $h(json_encode($c['datos_nuevos'], JSON_UNESCAPED_UNICODE)) ?>">Ver</button>
                                <?php else: ?>
                                    <span class="au-suave">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>

            <?php elseif ($tab === 'stock'): ?>
                <thead>
                    <tr>
                        <th>Fecha y hora</th>
                        <th>Usuario</th>
                        <th>Insumo</th>
                        <th class="num">Anterior</th>
                        <th class="num">Nuevo</th>
                        <th class="num">Diferencia</th>
                        <th>Origen</th>
                        <th class="der">Movimiento</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($pg['filas'])): ?>
                        <tr><td colspan="8" class="au-vacio">No se encontraron cambios de stock para los filtros aplicados.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($pg['filas'] as $s): ?>
                        <?php $dif = (float) $s['diferencia']; ?>
                        <tr>
                            <td class="au-fecha"><?= $h(au_fecha_hora($s['fecha_hora'])) ?></td>
                            <td><strong><?= $h($s['nombre_usuario']) ?></strong><br><span class="au-suave">DNI <?= $h($s['dni_usuario']) ?></span></td>
                            <td><strong><?= $h($s['codigo']) ?></strong><br><?= $h($s['nombre_insumo']) ?></td>
                            <td class="num"><?= $h(au_num($s['stock_anterior'])) ?></td>
                            <td class="num"><?= $h(au_num($s['stock_nuevo'])) ?></td>
                            <td class="num <?= $dif > 0 ? 'au-mas' : ($dif < 0 ? 'au-menos' : '') ?>"><?= ($dif > 0 ? '+' : '') . $h(au_num($dif)) ?></td>
                            <td><span class="au-pastilla au-pastilla--neutra"><?= $h(Auditoria::ORIGENES[$s['origen']] ?? $s['origen']) ?></span></td>
                            <td class="der">
                                <?php if (!empty($s['id_movimiento'])): ?>
                                    <button type="button" class="au-btn au-btn--sec au-btn--chico"
                                        onclick="abrirDetallesMovimiento(<?= (int) $s['id_movimiento'] ?>)">
                                        <?= $h(($s['tipo_movimiento'] ?? 'Movimiento') . ' #' . $s['id_movimiento']) ?></button>
                                <?php else: ?>
                                    <span class="au-suave">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>

            <?php elseif ($tab === 'accesos'): ?>
                <thead>
                    <tr>
                        <th>Fecha y hora</th>
                        <th>DNI</th>
                        <th>Usuario</th>
                        <th>Evento</th>
                        <th>Detalle</th>
                        <th>IP</th>
                        <th>Navegador</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($pg['filas'])): ?>
                        <tr><td colspan="7" class="au-vacio">No se encontraron accesos para los filtros aplicados.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($pg['filas'] as $a): ?>
                        <tr>
                            <td class="au-fecha"><?= $h(au_fecha_hora($a['fecha_hora'])) ?></td>
                            <td><?= $h($a['dni']) ?></td>
                            <td><?= $a['nombre_usuario'] ? '<strong>' . $h($a['nombre_usuario']) . '</strong>' : '<span class="au-suave">No registrado</span>' ?></td>
                            <td><span class="au-pastilla au-pastilla--<?= au_clase_evento($a['evento']) ?>"><?= $h(Auditoria::EVENTOS[$a['evento']] ?? $a['evento']) ?></span></td>
                            <td class="au-desc"><?= $a['detalle'] ? $h($a['detalle']) : '<span class="au-suave">-</span>' ?></td>
                            <td><?= $h($a['ip'] ?: '-') ?></td>
                            <td class="au-agente" title="<?= $h($a['user_agent']) ?>"><?= $h($a['user_agent'] ?: '-') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>

            <?php else: ?>
                <thead>
                    <tr>
                        <th>Fecha y hora</th>
                        <th>Operación</th>
                        <th>Operario destinatario</th>
                        <th>Usuario responsable</th>
                        <th>Observaciones</th>
                        <th class="der">Acción</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($pg['filas'])): ?>
                        <tr><td colspan="6" class="au-vacio">No se encontraron movimientos para los filtros aplicados.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($pg['filas'] as $m): ?>
                        <tr>
                            <td class="au-fecha"><?= date('d/m/Y', strtotime($m['fecha'])) ?> <?= $h($m['hora']) ?></td>
                            <td><span class="au-pastilla au-pastilla--info"><?= $h($m['tipo_movimiento']) ?></span></td>
                            <td><strong><?= enlaceOperario($m['id_operario'] ?? null, $m['operario'] ?? null) ?></strong></td>
                            <td><?= $h($m['usuario_responsable']) ?></td>
                            <td class="au-suave"><?= $h($m['observaciones'] ?: '-') ?></td>
                            <td class="der">
                                <button type="button" class="au-btn au-btn--sec au-btn--chico"
                                    onclick="abrirDetallesMovimiento(<?= (int) $m['id_movimiento'] ?>)">Ver ítems</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            <?php endif; ?>
        </table>
    </div>

    <?php au_paginador($pg); ?>
</div>

<!-- MODAL: ÍTEMS DE UN MOVIMIENTO -->
<div id="au-modal-movimiento" class="au-modal">
    <div class="au-modal__caja">
        <div class="au-modal__cabecera">
            <h3 class="au-modal__titulo">Desglose de ítems (Movimiento #<span id="au-mov-id"></span>)</h3>
            <button type="button" class="modal-close-btn au-modal__cerrar" onclick="auditoriaCerrarModal('au-modal-movimiento')" title="Cerrar" aria-label="Cerrar"><?= iconoAccion('close', 18) ?></button>
        </div>
        <div id="au-mov-estado" class="au-modal__estado">Cargando...</div>
        <div id="au-mov-tabla" style="display: none;">
            <table class="au-modal__tabla">
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Artículo / Insumo</th>
                        <th>Cantidad</th>
                        <th>Estado Hta.</th>
                    </tr>
                </thead>
                <tbody id="au-mov-filas"></tbody>
            </table>
        </div>
    </div>
</div>

<!-- MODAL: ANTES / DESPUÉS DE UN CAMBIO -->
<div id="au-modal-cambio" class="au-modal">
    <div class="au-modal__caja">
        <div class="au-modal__cabecera">
            <h3 class="au-modal__titulo" id="au-cambio-titulo"></h3>
            <button type="button" class="modal-close-btn au-modal__cerrar" onclick="auditoriaCerrarModal('au-modal-cambio')" title="Cerrar" aria-label="Cerrar"><?= iconoAccion('close', 18) ?></button>
        </div>
        <p class="au-modal__meta" id="au-cambio-meta"></p>
        <table class="au-modal__tabla">
            <thead>
                <tr>
                    <th>Campo</th>
                    <th>Antes</th>
                    <th>Después</th>
                </tr>
            </thead>
            <tbody id="au-cambio-filas"></tbody>
        </table>
    </div>
</div>

<script src="<?= $h(rtrim(BASE_URL, '/')) ?>/assets/js/auditoria.js"></script>
<script>
// Filtro de usuario con búsqueda predictiva (assets/js/autocompletado.js, se carga al final de la página)
document.addEventListener('DOMContentLoaded', function () {
    crearAutocompletado({
        input: document.getElementById('au-buscar-usuario'),
        lista: document.getElementById('au-lista-usuario'),
        url: '../ajax/buscar_autocompletado.php',
        parametros: { recurso: 'usuario' },
        alSeleccionar: function (u) { document.getElementById('au-usuario').value = u.id; },
        alLimpiar: function () { document.getElementById('au-usuario').value = ''; }
    });
});
</script>

<?php
require_once __DIR__ . '/../layout/footer.php';
?>
