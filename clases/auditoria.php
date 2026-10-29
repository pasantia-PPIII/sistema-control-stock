<?php
/**
 * clases/auditoria.php
 * Registro de auditoría del sistema: accesos, cambios en registros y cambios de stock, más el historial de
 * movimientos (que antes vivía en la pestaña "Historial" de Reportes).
 *
 * - Solo se INSERTA y se CONSULTA: nunca se actualiza ni se borra (lo refuerzan los triggers de
 *   bd/db_control_stock.sql, sección 7).
 * - Escribe con la misma conexión que el resto del sistema (Database), por lo que cuando se llama dentro
 *   de una transacción el registro de auditoría se confirma o se deshace junto con el cambio.
 * - Los métodos registrarCambio() y registrarStock() LANZAN excepción si fallan (un cambio sin auditoría
 *   no debe quedar guardado). registrarAcceso() nunca lanza: un fallo de auditoría no impide ingresar.
 * - La pantalla (auditoria/dashboard.php) es solo para el rol Administrador.
 */
require_once __DIR__ . '/../config/database.php';

class Auditoria
{
    // Eventos de acceso
    const LOGIN_OK        = 'LOGIN_OK';
    const LOGIN_FALLIDO   = 'LOGIN_FALLIDO';
    const LOGIN_BLOQUEADO = 'LOGIN_BLOQUEADO';
    const LOGOUT          = 'LOGOUT';

    // Acciones sobre registros
    const CREAR              = 'CREAR';
    const EDITAR             = 'EDITAR';
    const DESHABILITAR       = 'DESHABILITAR';
    const HABILITAR          = 'HABILITAR';
    const ANULAR             = 'ANULAR';
    const REACTIVAR          = 'REACTIVAR';
    const CAMBIAR_CONTRASENA = 'CAMBIAR_CONTRASENA';

    // Origen de un cambio de stock
    const STOCK_ALTA       = 'ALTA_INSUMO';
    const STOCK_EDICION    = 'EDICION_INSUMO';
    const STOCK_MOVIMIENTO = 'MOVIMIENTO';
    const STOCK_ANULACION  = 'ANULACION_MOVIMIENTO';

    // Listas para los filtros de la pantalla (valor => etiqueta)
    const EVENTOS = [
        self::LOGIN_OK        => 'Ingreso correcto',
        self::LOGIN_FALLIDO   => 'Ingreso fallido',
        self::LOGIN_BLOQUEADO => 'Ingreso bloqueado',
        self::LOGOUT          => 'Cierre de sesión',
    ];
    const ACCIONES = [
        self::CREAR              => 'Carga',
        self::EDITAR             => 'Edición',
        self::DESHABILITAR       => 'Deshabilitación',
        self::HABILITAR          => 'Habilitación',
        self::ANULAR             => 'Anulación',
        self::REACTIVAR          => 'Reactivación',
        self::CAMBIAR_CONTRASENA => 'Cambio de contraseña',
    ];
    const ENTIDADES = [
        'insumo'           => 'Insumos',
        'movimiento'       => 'Movimientos',
        'orden_de_trabajo' => 'Órdenes de trabajo',
        'operario'         => 'Operarios',
        'rubro'            => 'Rubros',
        'usuario'          => 'Usuarios',
    ];
    const ORIGENES = [
        self::STOCK_ALTA       => 'Alta de insumo',
        self::STOCK_EDICION    => 'Edición de insumo',
        self::STOCK_MOVIMIENTO => 'Movimiento',
        self::STOCK_ANULACION  => 'Anulación de movimiento',
    ];

    // Motivos de un ingreso fallido (se guardan en auditoria_acceso.detalle)
    const MOTIVO_DNI_NO_REGISTRADO  = 'DNI no registrado en el sistema';
    const MOTIVO_USUARIO_INACTIVO   = 'Usuario inactivo o deshabilitado';
    const MOTIVO_PASSWORD_INCORRECTA = 'Contraseña incorrecta';

    // Campos que nunca se guardan en datos_anteriores / datos_nuevos
    private const CAMPOS_SECRETOS = ['contrasena', 'password', 'csrf_token'];

    // =========================================================
    // 1. ESCRITURA
    // =========================================================

    /**
     * Registra un acceso (ingreso correcto, ingreso fallido, bloqueo o cierre de sesión).
     * Nunca lanza excepción.
     * @param string $evento Una de las constantes LOGIN_* / LOGOUT
     * @param string $dni DNI tipeado o de la sesión (puede no existir como usuario)
     * @param string|null $detalle Motivo, por ejemplo por qué falló un ingreso
     */
    public static function registrarAcceso(string $evento, string $dni, ?string $detalle = null): void
    {
        try {
            Database::execute(
                "INSERT INTO auditoria_acceso (dni, evento, detalle, ip, user_agent) VALUES (:dni, :evento, :detalle, :ip, :ua)",
                [
                    'dni'     => substr(trim($dni), 0, 20),
                    'evento'  => $evento,
                    'detalle' => self::texto($detalle ?? '', 255),
                    'ip'      => self::ip(),
                    'ua'      => self::texto($_SERVER['HTTP_USER_AGENT'] ?? '', 255),
                ]
            );
        } catch (Throwable $e) {
            error_log('Auditoría (acceso): ' . $e->getMessage());
        }
    }

    /**
     * Registra un cambio sobre un registro (carga, edición, deshabilitación, anulación...).
     * Usa el usuario de la sesión. Lanza excepción si no hay sesión o si falla el INSERT.
     *
     * @param string $accion Una de las constantes de acción (CREAR, EDITAR, ...)
     * @param string $entidad insumo | movimiento | orden_de_trabajo | operario | rubro | usuario
     * @param int|string $idRegistro Id, código o DNI del registro afectado
     * @param string $descripcion Frase legible para la pantalla
     * @param array|null $antes Valores anteriores (solo los campos que cambiaron)
     * @param array|null $despues Valores nuevos (en una carga, el registro completo)
     */
    public static function registrarCambio(string $accion, string $entidad, $idRegistro, string $descripcion,
                                           ?array $antes = null, ?array $despues = null): void
    {
        Database::execute(
            "INSERT INTO auditoria_cambio (dni_usuario, accion, entidad, id_registro, descripcion, datos_anteriores, datos_nuevos, ip)
             VALUES (:dni, :accion, :entidad, :id, :descripcion, CAST(:antes AS jsonb), CAST(:despues AS jsonb), :ip)",
            [
                'dni'         => self::dniSesion(),
                'accion'      => $accion,
                'entidad'     => $entidad,
                'id'          => (string)$idRegistro,
                'descripcion' => self::texto($descripcion, 500) ?? '',
                'antes'       => self::aJson($antes),
                'despues'     => self::aJson($despues),
                'ip'          => self::ip(),
            ]
        );
    }

    /**
     * Registra un cambio de stock de un insumo (valor anterior -> valor nuevo).
     * Si el stock no cambió no se registra nada, salvo en el alta del insumo (siempre queda el stock inicial).
     *
     * @param int $idInsumo Insumo afectado
     * @param float $anterior Stock antes del cambio
     * @param float $nuevo Stock después del cambio
     * @param string $origen Una de las constantes STOCK_*
     * @param int|null $idMovimiento Movimiento que lo originó (entrega, devolución o su anulación)
     * @param string|null $detalle Texto opcional
     */
    public static function registrarStock(int $idInsumo, float $anterior, float $nuevo, string $origen,
                                          ?int $idMovimiento = null, ?string $detalle = null): void
    {
        if ($origen !== self::STOCK_ALTA && abs($nuevo - $anterior) < 0.0001) {
            return;
        }
        Database::execute(
            "INSERT INTO auditoria_stock (dni_usuario, id_insumo, stock_anterior, stock_nuevo, origen, id_movimiento, detalle)
             VALUES (:dni, :insumo, :anterior, :nuevo, :origen, :mov, :detalle)",
            [
                'dni'      => self::dniSesion(),
                'insumo'   => $idInsumo,
                'anterior' => (string)$anterior,
                'nuevo'    => (string)$nuevo,
                'origen'   => $origen,
                'mov'      => $idMovimiento,
                'detalle'  => self::texto($detalle ?? '', 255),
            ]
        );
    }

    /**
     * Compara dos versiones de un registro y devuelve solo los campos que cambiaron.
     * Se recorren los campos de $despues (lo que realmente se está guardando).
     * @return array [antes, despues]; ambos vacíos si no hubo cambios
     */
    public static function diferencias(array $antes, array $despues): array
    {
        $a = [];
        $d = [];
        foreach ($despues as $campo => $valorNuevo) {
            if (in_array($campo, self::CAMPOS_SECRETOS, true)) {
                continue;
            }
            $valorViejo = $antes[$campo] ?? null;
            if (self::normalizar($valorViejo) !== self::normalizar($valorNuevo)) {
                $a[$campo] = $valorViejo;
                $d[$campo] = $valorNuevo;
            }
        }
        return [$a, $d];
    }

    /**
     * Ejecuta $fn dentro de una transacción (la abre solo si no hay una abierta) para que el cambio y su
     * registro de auditoría se guarden juntos o no se guarde ninguno.
     * NO usar en métodos que ya abren su propia transacción (Movimiento::create/anular, Insumo::create/update,
     * OrdenDeTrabajo::create/update...): ahí se llama a Auditoria::registrar...() dentro de la existente.
     */
    public static function transaccion(callable $fn)
    {
        $pdo = Database::getConnection();
        $propia = !$pdo->inTransaction();
        if ($propia) {
            $pdo->beginTransaction();
        }
        try {
            $resultado = $fn();
            if ($propia) {
                $pdo->commit();
            }
            return $resultado;
        } catch (Throwable $e) {
            if ($propia && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    // =========================================================
    // 2. CONSULTAS (pantalla de Auditoría)
    // Cada listado devuelve la forma de Reportes::paginado(): filas, pagina, paginas, total, desde, hasta.
    // Filtros (todos opcionales): desde, hasta (Y-m-d), busqueda, y según el listado: evento | accion, entidad | origen
    // =========================================================

    public static function accesosPaginado(array $f, int $pagina, int $porPagina): array
    {
        [$where, $params] = self::filtrosAcceso($f);
        return self::paginar(
            "SELECT a.id_acceso, a.fecha_hora, a.dni, a.evento, a.detalle, a.ip, a.user_agent,
                    u.nombre || ' ' || u.apellido AS nombre_usuario
             FROM auditoria_acceso a
             LEFT JOIN usuario u ON u.dni = a.dni" . $where . "
             ORDER BY a.fecha_hora DESC, a.id_acceso DESC",
            $params, $pagina, $porPagina
        );
    }

    public static function cambiosPaginado(array $f, int $pagina, int $porPagina): array
    {
        [$where, $params] = self::filtrosCambio($f);
        $res = self::paginar(
            "SELECT c.id_cambio, c.fecha_hora, c.dni_usuario, c.accion, c.entidad, c.id_registro, c.descripcion,
                    c.datos_anteriores, c.datos_nuevos, c.ip,
                    u.nombre || ' ' || u.apellido AS nombre_usuario
             FROM auditoria_cambio c
             INNER JOIN usuario u ON u.dni = c.dni_usuario" . $where . "
             ORDER BY c.fecha_hora DESC, c.id_cambio DESC",
            $params, $pagina, $porPagina
        );
        // JSONB llega como texto: se decodifica para que la vista reciba arreglos
        foreach ($res['filas'] as &$r) {
            $r['datos_anteriores'] = $r['datos_anteriores'] !== null ? json_decode($r['datos_anteriores'], true) : null;
            $r['datos_nuevos']     = $r['datos_nuevos'] !== null ? json_decode($r['datos_nuevos'], true) : null;
        }
        unset($r);
        return $res;
    }

    public static function stockPaginado(array $f, int $pagina, int $porPagina): array
    {
        [$where, $params] = self::filtrosStock($f);
        return self::paginar(
            "SELECT s.id_auditoria_stock, s.fecha_hora, s.dni_usuario, s.id_insumo, i.codigo, i.nombre AS nombre_insumo,
                    s.stock_anterior, s.stock_nuevo, (s.stock_nuevo - s.stock_anterior) AS diferencia,
                    s.origen, s.id_movimiento, tm.tipo AS tipo_movimiento, s.detalle,
                    u.nombre || ' ' || u.apellido AS nombre_usuario
             FROM auditoria_stock s
             INNER JOIN insumos i ON i.id_insumo = s.id_insumo
             INNER JOIN usuario u ON u.dni = s.dni_usuario
             LEFT JOIN movimiento m ON m.id_movimiento = s.id_movimiento
             LEFT JOIN tipo_movimiento tm ON tm.id_tipo_mov = m.id_tipo_mov" . $where . "
             ORDER BY s.fecha_hora DESC, s.id_auditoria_stock DESC",
            $params, $pagina, $porPagina
        );
    }

    /**
     * Historial de movimientos (entregas, devoluciones, ingresos y ajustes) paginado en la base.
     * Antes era la pestaña "Historial" de Reportes.
     * @param string|null $periodo hoy | semana | mes | anio (solo si no hay rango de fechas manual)
     */
    public static function movimientosPaginado(int $pagina, int $porPagina, ?string $periodo = 'hoy', ?string $desde = null,
                                               ?string $hasta = null, ?string $buscar = null, ?string $tipoMov = null, ?string $usuario = null): array
    {
        $sql = "
            SELECT
                m.id_movimiento,
                m.fecha,
                m.hora,
                tm.tipo AS tipo_movimiento,
                m.id_operario,
                COALESCE(o.nombre || ' ' || o.apellido, 'Sin operario') AS operario,
                COALESCE(u.nombre || ' ' || u.apellido, u.dni) AS usuario_responsable,
                m.observaciones
            FROM movimiento m
            INNER JOIN tipo_movimiento tm ON m.id_tipo_mov = tm.id_tipo_mov
            LEFT JOIN operario o ON m.id_operario = o.id_operario
            LEFT JOIN usuario u ON m.dni_usuario = u.dni
            WHERE m.activo = TRUE
        ";
        $params = [];

        // Fechas inválidas se ignoran; un período desconocido equivale a no filtrar por período.
        if (!empty($desde) && !self::fechaValida($desde)) {
            $desde = null;
        }
        if (!empty($hasta) && !self::fechaValida($hasta)) {
            $hasta = null;
        }

        if (!empty($periodo) && empty($desde) && empty($hasta)) {
            if ($periodo === 'hoy') {
                $sql .= " AND m.fecha = CURRENT_DATE";
            } elseif ($periodo === 'semana') {
                $sql .= " AND m.fecha >= (CURRENT_DATE - INTERVAL '7 days')";
            } elseif ($periodo === 'mes') {
                $sql .= " AND DATE_TRUNC('month', m.fecha) = DATE_TRUNC('month', CURRENT_DATE)";
            } elseif ($periodo === 'anio') {
                $sql .= " AND DATE_TRUNC('year', m.fecha) = DATE_TRUNC('year', CURRENT_DATE)";
            }
        }
        if (!empty($desde)) {
            $sql .= " AND m.fecha >= :fdesde";
            $params[':fdesde'] = $desde;
        }
        if (!empty($hasta)) {
            $sql .= " AND m.fecha <= :fhasta";
            $params[':fhasta'] = $hasta;
        }
        if (!empty($usuario)) {
            $sql .= " AND m.dni_usuario = :usuario";
            $params[':usuario'] = $usuario;
        }
        if (!empty($tipoMov)) {
            $sql .= " AND tm.tipo = :tipo_mov";
            $params[':tipo_mov'] = $tipoMov;
        }
        if (!empty($buscar)) {
            $sql .= " AND (" . sqlBusquedaSinTildes('u.nombre', ':b1') . " OR " . sqlBusquedaSinTildes('u.apellido', ':b2')
                . " OR " . sqlBusquedaSinTildes('o.nombre', ':b3') . " OR " . sqlBusquedaSinTildes('o.apellido', ':b4')
                . " OR " . sqlBusquedaSinTildes("o.nombre || ' ' || o.apellido", ':b5')
                . " OR " . sqlBusquedaSinTildes("u.nombre || ' ' || u.apellido", ':b6')
                . " OR " . sqlBusquedaSinTildes('m.observaciones', ':b7') . ")";
            $like = '%' . $buscar . '%';
            $params += [':b1' => $like, ':b2' => $like, ':b3' => $like, ':b4' => $like, ':b5' => $like, ':b6' => $like, ':b7' => $like];
        }

        $sql .= " ORDER BY m.fecha DESC, m.hora DESC, m.id_movimiento DESC";
        return self::paginar($sql, $params, $pagina, $porPagina);
    }

    /**
     * Líneas detalladas de un movimiento (para el modal del historial de movimientos).
     */
    public static function detalleMovimiento(int $idMovimiento): array
    {
        return Database::fetchAll(
            "SELECT
                COALESCE(i.codigo, '-') AS codigo,
                COALESCE(i.nombre, 'Artículo no identificado') AS item_nombre,
                COALESCE(md.cantidad, 1) AS cantidad,
                COALESCE(um.unidad_medida, 'Unid.') AS unidad,
                COALESCE(eh.estado, 'N/A') AS estado_herramienta
             FROM movimiento_detalle md
             LEFT JOIN insumos i ON md.id_insumo = i.id_insumo
             LEFT JOIN unidad_medida um ON i.id_unidad_medida = um.id_unidad_medida
             LEFT JOIN estado_herramienta eh ON md.id_estado_herramienta = eh.id_estado_herramienta
             WHERE md.id_movimiento = :id",
            ['id' => $idMovimiento]
        );
    }

    /**
     * Totales de un rango para las tarjetas de resumen de la pantalla.
     * @return array accesos_ok, accesos_fallidos, cambios, cambios_stock
     */
    public static function resumen(?string $desde = null, ?string $hasta = null): array
    {
        $f = ['desde' => $desde, 'hasta' => $hasta];
        return [
            'accesos_ok'       => self::accesosPaginado($f + ['evento' => self::LOGIN_OK], 1, 1)['total'],
            'accesos_fallidos' => self::accesosPaginado($f + ['evento' => self::LOGIN_FALLIDO], 1, 1)['total'],
            'cambios'          => self::cambiosPaginado($f, 1, 1)['total'],
            'cambios_stock'    => self::stockPaginado($f, 1, 1)['total'],
        ];
    }

    // =========================================================
    // 3. AUXILIARES PRIVADOS
    // =========================================================

    /** Pagina con Reportes::paginado() (se carga recién acá para no crear un ciclo de require con movimiento.php). */
    private static function paginar(string $sql, array $params, int $pagina, int $porPagina): array
    {
        require_once __DIR__ . '/reportes.php';
        return Reportes::paginado($sql, $params, $pagina, $porPagina);
    }

    private static function filtrosAcceso(array $f): array
    {
        $where = [];
        $params = [];
        self::rangoFechas($f, 'a.fecha_hora', $where, $params);
        if (!empty($f['usuario'])) {
            $where[] = 'a.dni = :usuario';
            $params['usuario'] = $f['usuario'];
        }
        if (!empty($f['evento']) && isset(self::EVENTOS[$f['evento']])) {
            $where[] = 'a.evento = :evento';
            $params['evento'] = $f['evento'];
        }
        if (!empty($f['busqueda'])) {
            $where[] = "(" . sqlBusquedaSinTildes('a.dni', ':b1') . " OR " . sqlBusquedaSinTildes('a.ip', ':b2')
                . " OR " . sqlBusquedaSinTildes('u.nombre', ':b3') . " OR " . sqlBusquedaSinTildes('u.apellido', ':b4')
                . " OR " . sqlBusquedaSinTildes('a.detalle', ':b5') . ")";
            $like = '%' . $f['busqueda'] . '%';
            $params += ['b1' => $like, 'b2' => $like, 'b3' => $like, 'b4' => $like, 'b5' => $like];
        }
        return [self::armarWhere($where), $params];
    }

    private static function filtrosCambio(array $f): array
    {
        $where = [];
        $params = [];
        self::rangoFechas($f, 'c.fecha_hora', $where, $params);
        if (!empty($f['usuario'])) {
            $where[] = 'c.dni_usuario = :usuario';
            $params['usuario'] = $f['usuario'];
        }
        if (!empty($f['accion']) && isset(self::ACCIONES[$f['accion']])) {
            $where[] = 'c.accion = :accion';
            $params['accion'] = $f['accion'];
        }
        if (!empty($f['entidad']) && isset(self::ENTIDADES[$f['entidad']])) {
            $where[] = 'c.entidad = :entidad';
            $params['entidad'] = $f['entidad'];
        }
        if (!empty($f['busqueda'])) {
            $where[] = "(" . sqlBusquedaSinTildes('c.dni_usuario', ':b1') . " OR " . sqlBusquedaSinTildes('c.id_registro', ':b2')
                . " OR " . sqlBusquedaSinTildes('c.descripcion', ':b3')
                . " OR " . sqlBusquedaSinTildes('u.nombre', ':b4') . " OR " . sqlBusquedaSinTildes('u.apellido', ':b5') . ")";
            $like = '%' . $f['busqueda'] . '%';
            $params += ['b1' => $like, 'b2' => $like, 'b3' => $like, 'b4' => $like, 'b5' => $like];
        }
        return [self::armarWhere($where), $params];
    }

    private static function filtrosStock(array $f): array
    {
        $where = [];
        $params = [];
        self::rangoFechas($f, 's.fecha_hora', $where, $params);
        if (!empty($f['usuario'])) {
            $where[] = 's.dni_usuario = :usuario';
            $params['usuario'] = $f['usuario'];
        }
        if (!empty($f['origen']) && isset(self::ORIGENES[$f['origen']])) {
            $where[] = 's.origen = :origen';
            $params['origen'] = $f['origen'];
        }
        if (!empty($f['busqueda'])) {
            $where[] = "(" . sqlBusquedaSinTildes('i.codigo', ':b1') . " OR " . sqlBusquedaSinTildes('i.nombre', ':b2')
                . " OR " . sqlBusquedaSinTildes('s.dni_usuario', ':b3')
                . " OR " . sqlBusquedaSinTildes('u.nombre', ':b4') . " OR " . sqlBusquedaSinTildes('u.apellido', ':b5') . ")";
            $like = '%' . $f['busqueda'] . '%';
            $params += ['b1' => $like, 'b2' => $like, 'b3' => $like, 'b4' => $like, 'b5' => $like];
        }
        return [self::armarWhere($where), $params];
    }

    /** Rango de fechas inclusivo (el día "hasta" entra completo). Las fechas con formato inválido se ignoran. */
    private static function rangoFechas(array $f, string $columna, array &$where, array &$params): void
    {
        if (!empty($f['desde']) && self::fechaValida($f['desde'])) {
            $where[] = "$columna >= CAST(:desde AS date)";
            $params['desde'] = $f['desde'];
        }
        if (!empty($f['hasta']) && self::fechaValida($f['hasta'])) {
            $where[] = "$columna < CAST(:hasta AS date) + 1";
            $params['hasta'] = $f['hasta'];
        }
    }

    /**
     * Indica si el texto es una fecha real en formato AAAA-MM-DD (rechaza, por ejemplo, 2026-13-45).
     * Los filtros de fecha de toda la auditoría pasan por aquí para que una fecha inválida
     * nunca llegue a la base de datos.
     */
    public static function fechaValida(string $fecha): bool
    {
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $fecha, $m)) {
            return false;
        }
        return checkdate((int) $m[2], (int) $m[3], (int) $m[1]);
    }

    private static function armarWhere(array $where): string
    {
        return $where ? ' WHERE ' . implode(' AND ', $where) : '';
    }

    private static function dniSesion(): string
    {
        $dni = (string)($_SESSION['dni_usuario'] ?? '');
        if ($dni === '') {
            throw new RuntimeException('Auditoría: no hay un usuario con sesión iniciada.');
        }
        return $dni;
    }

    private static function ip(): ?string
    {
        return self::texto($_SERVER['REMOTE_ADDR'] ?? '', 45);
    }

    private static function texto(string $valor, int $max): ?string
    {
        $valor = mb_substr($valor, 0, $max);
        return $valor !== '' ? $valor : null;
    }

    private static function aJson(?array $datos): ?string
    {
        if ($datos === null) {
            return null;
        }
        foreach (self::CAMPOS_SECRETOS as $campo) {
            unset($datos[$campo]);
        }
        return json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
    }

    private static function normalizar($valor): string
    {
        if ($valor === null) {
            return '';
        }
        if (is_bool($valor)) {
            return $valor ? '1' : '0';
        }
        if (is_numeric($valor)) {
            return (string)(float)$valor;
        }
        return trim((string)$valor);
    }
}
