<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/reportes.php';

class OrdenDeTrabajo {
    private $db;

    public function __construct() {
        $this->db = new Database();
    }

    // 
    // 1. CONSULTAS (READ)
    // 

    /**
     * Obtiene todas las Órdenes de Trabajo con sus datos relacionados
     * @param bool $incluir_anuladas - Si es true muestra las anuladas
     * @return array Lista de orden de trabajo
     */
    public function getAll($incluir_anuladas = false) {
        $sql = "SELECT o.*, 
                       l.localidad as nombre_localidad,
                       j.jurisdiccion as nombre_jurisdiccion
                FROM orden_de_trabajo o
                LEFT JOIN localidad l ON o.id_localidad = l.id_localidad
                LEFT JOIN jurisdiccion j ON o.id_jurisdiccion = j.id_jurisdiccion";
        
        if (!$incluir_anuladas) {
            $sql .= " WHERE o.activo = TRUE";
        }

        $sql .= " ORDER BY o.fecha_inicio DESC, o.id_odt DESC";

        return $this->db->fetchAll($sql);
    }

    /**
     * Órdenes de Trabajo filtradas y paginadas en la base (LIMIT/OFFSET), igual que reportes y auditoría.
     * @param string $busqueda Texto a buscar en id, número de OT, PA y trabajo a realizar
     * @param string $estado Estado del trabajo ('' = todos)
     * @param string $registro 'activo' (habilitadas), 'inactivo' (deshabilitadas) o 'todos'
     * @return array Estructura de Reportes::paginado(): filas, total, pagina, paginas, desde, hasta
     */
    public function getPaginado($busqueda, $estado, $registro, $pagina, $porPagina) {
        $sql = "SELECT o.*,
                       l.localidad as nombre_localidad,
                       j.jurisdiccion as nombre_jurisdiccion
                FROM orden_de_trabajo o
                LEFT JOIN localidad l ON o.id_localidad = l.id_localidad
                LEFT JOIN jurisdiccion j ON o.id_jurisdiccion = j.id_jurisdiccion
                WHERE 1 = 1";
        $params = [];

        if ($registro === 'activo') {
            $sql .= " AND o.activo = TRUE";
        } elseif ($registro === 'inactivo') {
            $sql .= " AND o.activo = FALSE";
        }
        if ($estado !== '') {
            $sql .= " AND COALESCE(o.estado, 'Pendiente') = :estado";
            $params['estado'] = $estado;
        }
        if ($busqueda !== '') {
            $sql .= " AND (CAST(o.id_odt AS TEXT) || ' ' || o.numero_ot || ' ' || COALESCE(o.pa, '') || ' ' || COALESCE(o.trabajo_a_realizar, '')) ILIKE :busqueda";
            $params['busqueda'] = '%' . addcslashes($busqueda, '%_\\') . '%';
        }

        $sql .= " ORDER BY o.fecha_inicio DESC, o.id_odt DESC";
        return Reportes::paginado($sql, $params, (int)$pagina, (int)$porPagina);
    }

    /**
     * Obtiene una Orden de Trabajo específica por su id
     * @param int $id_odt ID de la orden
     * @return array|false Datos de la Orden de trabajo
     */
    public function getById($id_odt) {
        $sql = "SELECT o.*, 
                       l.localidad as nombre_localidad,
                       j.jurisdiccion as nombre_jurisdiccion
                FROM orden_de_trabajo o
                LEFT JOIN localidad l ON o.id_localidad = l.id_localidad
                LEFT JOIN jurisdiccion j ON o.id_jurisdiccion = j.id_jurisdiccion
                WHERE o.id_odt = :id_odt AND o.activo = TRUE";

        return $this->db->fetchOne($sql, ['id_odt' => $id_odt]);
    }

    /**
     * Busca ODTs por número de PA, descripción del trabajo o id
     * @param string $termino Texto a buscar
     * @return array Resultados
     */
    public function buscar($termino) {
        $sql = "SELECT o.*, l.localidad as nombre_localidad
                FROM orden_de_trabajo o
                LEFT JOIN localidad l ON o.id_localidad = l.id_localidad
                WHERE (CAST(o.id_odt AS TEXT) ILIKE :termino 
                   OR o.pa ILIKE :termino 
                   OR o.trabajo_a_realizar ILIKE :termino)
                AND o.activo = TRUE
                ORDER BY o.fecha_inicio DESC";
        
        return $this->db->fetchAll($sql, ['termino' => '%' . $termino . '%']);
    }

    /**
     * Obtiene orden de trab filtradas por su estado
     * @param string $estado Estado a filtrar
     * @return array Lista de ODTs
     */
    public function getByEstado($estado) {
        $sql = "SELECT o.*, l.localidad as nombre_localidad
                FROM orden_de_trabajo o
                LEFT JOIN localidad l ON o.id_localidad = l.id_localidad
                WHERE o.estado = :estado AND o.activo = TRUE
                ORDER BY o.fecha_inicio DESC";
        
        return $this->db->fetchAll($sql, ['estado' => $estado]);
    }

    /**
     * Obtiene las orden de trab asignadas a un operario específico (a traves de la tabla comision)
     * @param int $id_operario ID del operario
     * @return array Lista de ODTs
     */
    public function getByOperario($id_operario) {
        $sql = "SELECT o.*, l.localidad as nombre_localidad
                FROM orden_de_trabajo o
                INNER JOIN comision c ON o.id_odt = c.id_odt
                LEFT JOIN localidad l ON o.id_localidad = l.id_localidad
                WHERE c.id_operario = :id_operario AND c.activo = TRUE AND o.activo = TRUE
                ORDER BY o.fecha_inicio DESC";
        
        return $this->db->fetchAll($sql, ['id_operario' => (int)$id_operario]);
    }

    /**
     * Obtiene los operarios asignados a una ODT específica (a traves de comision)
     * @param int $id_odt ID de la orden
     * @return array Lista de operarios
     */
    public function getOperariosAsignados($id_odt) {
        $sql = "SELECT o.id_operario, o.dni, o.nombre, o.apellido, r.nombre as nombre_rubro
                FROM operario o
                INNER JOIN comision c ON o.id_operario = c.id_operario AND c.activo = TRUE
                LEFT JOIN rubro r ON o.id_rubro = r.id_rubro
                WHERE c.id_odt = :id_odt
                ORDER BY o.apellido ASC";
        
        return $this->db->fetchAll($sql, ['id_odt' => $id_odt]);
    }

    // =========================================================
    // 2. CREAR Y ACTUALIZAR (CREATE / UPDATE)
    // =========================================================

    // Campos de la tabla orden_de_trabajo que se pueden cargar / editar desde el formulario
    private const CAMPOS_EDITABLES = [
        'numero_ot', 'pa', 'trabajo_a_realizar', 'id_localidad', 'id_jurisdiccion',
        'fecha_inicio', 'fecha_final', 'hora_inicio', 'hora_final',
        'estado', 'observaciones', 'archivo_pdf'
    ];

    /**
     * Normaliza los datos del formulario: solo campos permitidos, vacíos a NULL, claves foráneas a int.
     * Solo se devuelven las claves presentes en $data (así update() no pisa lo que no se envió).
     */
    private function limpiarDatos(array $data) {
        $out = array_intersect_key($data, array_flip(self::CAMPOS_EDITABLES));

        foreach (['pa', 'observaciones', 'trabajo_a_realizar', 'archivo_pdf', 'numero_ot'] as $campo) {
            if (array_key_exists($campo, $out)) {
                $v = trim((string)$out[$campo]);
                $out[$campo] = ($v !== '') ? $v : null;
            }
        }
        foreach (['fecha_inicio', 'fecha_final', 'hora_inicio', 'hora_final'] as $campo) {
            if (array_key_exists($campo, $out)) {
                $out[$campo] = !empty($out[$campo]) ? $out[$campo] : null;
            }
        }
        foreach (['id_localidad', 'id_jurisdiccion'] as $campo) {
            if (array_key_exists($campo, $out)) {
                $out[$campo] = !empty($out[$campo]) ? (int)$out[$campo] : null;
            }
        }
        if (array_key_exists('estado', $out)) {
            $v = trim((string)$out['estado']);
            $out['estado'] = ($v !== '') ? $v : 'Pendiente';
        }
        return $out;
    }

    /**
     * Crea una nueva Orden de Trabajo junto con su comisión de operarios, en una transacción.
     * Las entregas y devoluciones se vinculan después, al registrarlas en Movimientos (movimiento.id_odt).
     *
     * @param array $data Datos de la ODT
     * @param array $ids_operarios id_operario de los operarios asignados (comisión)
     * @return array El registro insertado con su ID generado
     */
    public function create($data, $ids_operarios = []) {
        $this->db->beginTransaction();
        try {
            $data = $this->limpiarDatos($data);

            if (empty($data['numero_ot'])) {
                throw new Exception("El número de Orden de Trabajo es obligatorio.");
            }
            if (empty($data['trabajo_a_realizar'])) {
                throw new Exception("La descripción del trabajo a realizar es obligatoria.");
            }
            $data['estado'] = $data['estado'] ?? 'Pendiente';
            $data['activo'] = true;

            $orden = $this->db->insert('orden_de_trabajo', $data);
            $id_odt = (int)$orden['id_odt'];

            $this->sincronizarOperarios($id_odt, $ids_operarios);

            $this->db->commit();
            return $orden;
        } catch (Throwable $e) {
            $this->cerrarConError($e, 'crear la Orden de Trabajo');
        }
    }

    /**
     * Actualiza una Orden de Trabajo. Solo se modifican los campos presentes en $data.
     *
     * @param int $id_odt ID de la orden a actualizar
     * @param array $data Nuevos datos
     * @param array|null $ids_operarios Comisión completa (null = no tocar la comisión)
     * @return bool true si se actualizó correctamente
     */
    public function update($id_odt, $data, $ids_operarios = null) {
        $this->db->beginTransaction();
        try {
            $data = $this->limpiarDatos($data);

            if (array_key_exists('trabajo_a_realizar', $data) && empty($data['trabajo_a_realizar'])
                && !empty($this->getById($id_odt)['trabajo_a_realizar'] ?? null)) {
                throw new Exception("La descripción del trabajo a realizar es obligatoria.");
            }
            if (array_key_exists('numero_ot', $data) && empty($data['numero_ot'])) {
                throw new Exception("El número de Orden de Trabajo es obligatorio.");
            }

            if (!empty($data)) {
                $this->db->update('orden_de_trabajo', $data, 'id_odt = :id_odt', ['id_odt' => (int)$id_odt]);
            }
            if (is_array($ids_operarios)) {
                $this->sincronizarOperarios((int)$id_odt, $ids_operarios);
            }

            $this->db->commit();
            return true;
        } catch (Throwable $e) {
            $this->cerrarConError($e, 'actualizar la Orden de Trabajo');
        }
    }

    /**
     * Deshace la transacción abierta y relanza el error (los de base de datos se registran en el log y se
     * reemplazan por un mensaje genérico; los de validación se conservan).
     */
    private function cerrarConError($e, $accion) {
        if ($this->db->inTransaction()) {
            $this->db->rollBack();
        }
        if ($e instanceof PDOException) {
            error_log("OrdenDeTrabajo - error al {$accion}: " . $e->getMessage());
            throw new Exception("Error al {$accion}. Intente nuevamente.");
        }
        throw new Exception($e->getMessage());
    }

    // =========================================================
    // 3. GESTIÓN DE COMISIONES (ASIGNAR/QUITAR OPERARIOS)
    //    Nunca se borran filas: quitar un operario lo marca como inactivo en la comisión.
    // =========================================================

    /**
     * Asigna un operario a una Orden de Trabajo (crea o reactiva su fila de comisión).
     * @param int $id_odt ID de la orden
     * @param int $id_operario ID del operario
     * @return bool true si se asignó correctamente
     */
    public function asignarOperario($id_odt, $id_operario) {
        try {
            $operario = $this->db->fetchOne(
                "SELECT activo FROM operario WHERE id_operario = :id",
                ['id' => (int)$id_operario]
            );
            if (!$operario || !$operario['activo']) {
                throw new Exception("El operario seleccionado no existe o está deshabilitado.");
            }

            $fila = $this->db->fetchOne(
                "SELECT id_comision, activo FROM comision WHERE id_odt = :id_odt AND id_operario = :id_operario",
                ['id_odt' => (int)$id_odt, 'id_operario' => (int)$id_operario]
            );

            if ($fila && $fila['activo']) {
                throw new Exception("Este operario ya está asignado a la orden de trabajo.");
            }
            if ($fila) {
                return Database::execute("UPDATE comision SET activo = TRUE WHERE id_comision = :id", ['id' => $fila['id_comision']]);
            }

            return (bool)$this->db->insert('comision', [
                'id_odt' => (int)$id_odt,
                'id_operario' => (int)$id_operario,
                'activo' => true
            ]);
        } catch (PDOException $e) {
            error_log('OrdenDeTrabajo::asignarOperario - ' . $e->getMessage());
            throw new Exception("Error al asignar el operario.");
        }
    }

    /**
     * Quita un operario de la comisión de una Orden de Trabajo (lo marca inactivo, no se borra).
     * @param int $id_odt ID de la orden
     * @param int $id_operario ID del operario
     * @return bool true si se quitó correctamente
     */
    public function quitarOperario($id_odt, $id_operario) {
        try {
            return Database::execute(
                "UPDATE comision SET activo = FALSE WHERE id_odt = :id_odt AND id_operario = :id_operario",
                ['id_odt' => (int)$id_odt, 'id_operario' => (int)$id_operario]
            );
        } catch (PDOException $e) {
            error_log('OrdenDeTrabajo::quitarOperario - ' . $e->getMessage());
            throw new Exception("Error al quitar el operario.");
        }
    }

    /**
     * Deja la comisión de la orden exactamente igual a la lista recibida:
     * activa los operarios indicados (creándolos si hace falta) y desactiva el resto.
     * Debe llamarse dentro de una transacción.
     * @param int $id_odt ID de la orden
     * @param array $ids_operarios id_operario que deben quedar asignados
     */
    private function sincronizarOperarios($id_odt, $ids_operarios) {
        $ids = [];
        foreach ((array)$ids_operarios as $id) {
            if ((int)$id > 0) {
                $ids[(int)$id] = true;
            }
        }
        $ids = array_keys($ids);

        $existentes = $this->db->fetchAll(
            "SELECT id_operario, activo FROM comision WHERE id_odt = :id_odt",
            ['id_odt' => (int)$id_odt]
        );
        $estado = [];
        foreach ($existentes as $c) {
            $estado[(int)$c['id_operario']] = (bool)$c['activo'];
        }

        foreach ($ids as $id) {
            if (!array_key_exists($id, $estado) || !$estado[$id]) {
                $this->asignarOperario($id_odt, $id);
            }
        }
        foreach ($estado as $id => $activo) {
            if ($activo && !in_array($id, $ids, true)) {
                $this->quitarOperario($id_odt, $id);
            }
        }
    }

    /**
     * Asigna múltiples operarios a una orden de trabajo de una sola vez.
     * @param int $id_odt ID de la orden
     * @param array $ids_operarios Array de id_operario
     * @return int Cantidad de operarios asignados
     */
    public function asignarMultiplesOperarios($id_odt, $ids_operarios) {
        $this->db->beginTransaction();
        try {
            $asignados = 0;
            foreach ($ids_operarios as $id_operario) {
                if (!$this->operarioYaAsignado($id_odt, $id_operario)) {
                    $this->asignarOperario($id_odt, $id_operario);
                    $asignados++;
                }
            }
            $this->db->commit();
            return $asignados;
        } catch (Throwable $e) {
            $this->cerrarConError($e, 'asignar operarios');
        }
    }

    /**
     * Verifica si un operario ya está activo en la comisión de una orden de trabajo.
     * @param int $id_odt ID de la orden
     * @param int $id_operario ID del operario
     * @return bool
     */
    private function operarioYaAsignado($id_odt, $id_operario) {
        $sql = "SELECT COUNT(*) as total FROM comision
                WHERE id_odt = :id_odt AND id_operario = :id_operario AND activo = TRUE";
        $result = $this->db->fetchOne($sql, [
            'id_odt' => (int)$id_odt,
            'id_operario' => (int)$id_operario
        ]);
        return $result['total'] > 0;
    }

    // =========================================================
    // 4. MOVIMIENTOS VINCULADOS
    //    Las entregas y devoluciones se vinculan al registrarlas (o editarlas) en el módulo Movimientos,
    //    con movimiento.id_odt. Una orden puede tener varias. Ver getMovimientosVinculados().
    // =========================================================

    // =========================================================
    // 5. DESHABILITACIÓN Y HABILITACIÓN (SOFT DELETE)
    // =========================================================

    /**
     * Anula una Orden de Trabajo.
     * @param int $id_odt ID de la orden
     * @return bool
     */
    public function anular($id_odt) {
        try {
            $data = ['activo' => false];
            return $this->db->update('orden_de_trabajo', $data, 'id_odt = :id_odt', ['id_odt' => $id_odt]);
        } catch (PDOException $e) {
            throw errorAmigable('Error al anular la Orden de Trabajo', $e);
        }
    }

    /**
     * Datos mínimos de una orden por id, esté activa o anulada.
     * (getById() solo devuelve las activas; esto sirve para reactivar una anulada.)
     * @param int $id_odt ID de la orden
     * @return array|null id_odt, numero_ot y activo; null si no existe
     */
    public function getEstadoById($id_odt) {
        return $this->db->fetchOne(
            "SELECT id_odt, numero_ot, activo FROM orden_de_trabajo WHERE id_odt = :id_odt",
            ['id_odt' => (int)$id_odt]
        );
    }

    /**
     * Reactiva una Orden de Trabajo anulada.
     * @param int $id_odt ID de la orden
     * @return bool
     */
    public function reactivar($id_odt) {
        try {
            $data = ['activo' => true];
            return $this->db->update('orden_de_trabajo', $data, 'id_odt = :id_odt', ['id_odt' => $id_odt]);
        } catch (PDOException $e) {
            throw errorAmigable('Error al reactivar la Orden de Trabajo', $e);
        }
    }

    // =========================================================
    // 6. VALIDACIONES Y UTILIDADES
    // =========================================================

    /**
     * Verifica si la ODT tiene movimientos asociado
     * @param int $id_odt ID de la orden
     * @return bool
     */
    public function tieneMovimientosAsociados($id_odt) {
        $sql = "SELECT COUNT(*) as total FROM movimiento WHERE id_odt = :id_odt AND activo = TRUE";

        $result = $this->db->fetchOne($sql, ['id_odt' => $id_odt]);
        return $result['total'] > 0;
    }

    /**
     * Verifica si la oden de trab tiene operarios asignado
     * @param int $id_odt ID de la orden
     * @return bool
     */
    public function tieneOperariosAsignados($id_odt) {
        $sql = "SELECT COUNT(*) as total FROM comision WHERE id_odt = :id_odt AND activo = TRUE";
        $result = $this->db->fetchOne($sql, ['id_odt' => $id_odt]);
        return $result['total'] > 0;
    }

    /**
     * Obtiene el catálogo de localidade
     */
    public function getLocalidades() {
        return $this->db->fetchAll("SELECT id_localidad, localidad FROM localidad ORDER BY localidad");
    }

    /**
     * Obtiene el catálogo de jurisdiccion
     */
    public function getJurisdicciones() {
        return $this->db->fetchAll("SELECT id_jurisdiccion, jurisdiccion FROM jurisdiccion ORDER BY jurisdiccion");
    }

    /**
     * Obtiene los estados posibles para una orden de Trab
     */
    public function getEstadosPosibles() {
        return [
            'Pendiente' => 'Pendiente',
            'En Progreso' => 'En Progreso',
            'Pausada' => 'Pausada',
            'Finalizada' => 'Finalizada',
            'Anulada' => 'Anulada'
        ];
    }

    /**
     * Obtiene el total de orden de trab activa
     * @return int
     */
    public function getTotal() {
        $sql = "SELECT COUNT(*) as total FROM orden_de_trabajo WHERE activo = TRUE";
        $result = $this->db->fetchOne($sql);
        return (int)$result['total'];
    }

    /**
     * Obtiene el total de orden de trab por estado
     * @return array
     */
    public function getTotalPorEstado() {
        $sql = "SELECT estado, COUNT(*) as total 
                FROM orden_de_trabajo 
                WHERE activo = TRUE 
                GROUP BY estado 
                ORDER BY total DESC";
        return $this->db->fetchAll($sql);
    }

    /**
     * Obtiene todos los movimientos (entregas y devoluciones) de una ODT, en orden cronológico.
     * Se toman por movimiento.id_odt, así una orden puede tener varias entregas y devoluciones (RF-47, RF-49).
     * @param int $id_odt ID de la orden de trabajo
     * @return array Movimientos con rol ('egreso' / 'devolucion'), tipo, fecha, hora y operario
     */
    public function getMovimientosVinculados($id_odt) {
        $sql = "SELECT CASE WHEN tm.tipo = 'Devolución' THEN 'devolucion' ELSE 'egreso' END AS rol_en_odt,
                       m.id_movimiento, m.fecha, m.hora, m.observaciones,
                       tm.tipo AS nombre_tipo_movimiento,
                       op.apellido || ', ' || op.nombre AS nombre_operario
                FROM movimiento m
                INNER JOIN tipo_movimiento tm ON m.id_tipo_mov = tm.id_tipo_mov
                LEFT JOIN operario op ON m.id_operario = op.id_operario
                WHERE m.activo = TRUE AND m.id_odt = :id_odt
                ORDER BY m.fecha ASC, m.hora ASC, m.id_movimiento ASC";

        return $this->db->fetchAll($sql, ['id_odt' => $id_odt]);
    }
}
?>