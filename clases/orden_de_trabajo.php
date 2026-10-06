<?php
require_once __DIR__ . '/../config/database.php';

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
     * Crea una nueva Orden de Trabajo junto con su comisión de operarios y,
     * opcionalmente, sus movimientos de entrega y de devolución. Todo en una transacción.
     *
     * @param array $data Datos de la ODT
     * @param array $ids_operarios id_operario de los operarios asignados (comisión)
     * @param int|null $id_mov_egreso Movimiento de tipo Entrega (opcional)
     * @param int|null $id_mov_devolucion Movimiento de tipo Devolución (opcional)
     * @return array El registro insertado con su ID generado
     */
    public function create($data, $ids_operarios = [], $id_mov_egreso = null, $id_mov_devolucion = null) {
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

            $this->aplicarVinculo($id_odt, 'id_mov_egreso', 'Entrega', $id_mov_egreso);
            $this->aplicarVinculo($id_odt, 'id_mov_devolucion', 'Devolución', $id_mov_devolucion);
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
     * @param array|null $vinculos ['id_mov_egreso' => ?int, 'id_mov_devolucion' => ?int]; solo se aplican las claves presentes
     * @return bool true si se actualizó correctamente
     */
    public function update($id_odt, $data, $ids_operarios = null, $vinculos = null) {
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
            if (is_array($vinculos)) {
                if (array_key_exists('id_mov_egreso', $vinculos)) {
                    $this->aplicarVinculo((int)$id_odt, 'id_mov_egreso', 'Entrega', $vinculos['id_mov_egreso']);
                }
                if (array_key_exists('id_mov_devolucion', $vinculos)) {
                    $this->aplicarVinculo((int)$id_odt, 'id_mov_devolucion', 'Devolución', $vinculos['id_mov_devolucion']);
                }
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
    // 4. VINCULACIÓN CON MOVIMIENTOS (ambos opcionales)
    //    id_mov_egreso -> movimiento de tipo Entrega; id_mov_devolucion -> movimiento de tipo Devolución.
    // =========================================================

    /**
     * Vincula (o desvincula, con null) el movimiento de entrega de la orden.
     * @param int $id_odt ID de la orden
     * @param int|null $id_mov_egreso ID del movimiento de tipo Entrega
     * @return bool
     */
    public function vincularMovimientoEgreso($id_odt, $id_mov_egreso) {
        return $this->vincularEnTransaccion($id_odt, 'id_mov_egreso', 'Entrega', $id_mov_egreso);
    }

    /**
     * Vincula (o desvincula, con null) el movimiento de devolución de la orden.
     * @param int $id_odt ID de la orden
     * @param int|null $id_mov_devolucion ID del movimiento de tipo Devolución
     * @return bool
     */
    public function vincularMovimientoDevolucion($id_odt, $id_mov_devolucion) {
        return $this->vincularEnTransaccion($id_odt, 'id_mov_devolucion', 'Devolución', $id_mov_devolucion);
    }

    private function vincularEnTransaccion($id_odt, $columna, $tipo, $id_mov) {
        $this->db->beginTransaction();
        try {
            $this->aplicarVinculo((int)$id_odt, $columna, $tipo, $id_mov);
            $this->db->commit();
            return true;
        } catch (Throwable $e) {
            $this->cerrarConError($e, 'vincular el movimiento');
        }
    }

    /**
     * Deja el movimiento indicado como el vinculado en la columna dada (null = sin movimiento).
     * Valida que el movimiento exista, esté activo, sea del tipo correcto y no esté vinculado a otra orden.
     * Mantiene sincronizado movimiento.id_odt. Debe llamarse dentro de una transacción.
     * @param int $id_odt ID de la orden
     * @param string $columna 'id_mov_egreso' o 'id_mov_devolucion' (valor interno, nunca viene del usuario)
     * @param string $tipoEsperado 'Entrega' o 'Devolución'
     * @param int|null $idNuevo ID del movimiento a vincular
     */
    private function aplicarVinculo($id_odt, $columna, $tipoEsperado, $idNuevo) {
        if (!in_array($columna, ['id_mov_egreso', 'id_mov_devolucion'], true)) {
            throw new Exception("Vínculo de movimiento inválido.");
        }

        $actual = $this->db->fetchOne("SELECT {$columna} AS id FROM orden_de_trabajo WHERE id_odt = :id", ['id' => $id_odt]);
        $idActual = ($actual && !empty($actual['id'])) ? (int)$actual['id'] : null;
        $idNuevo = !empty($idNuevo) ? (int)$idNuevo : null;

        if ($idActual === $idNuevo) {
            return;
        }

        if ($idNuevo !== null) {
            $mov = $this->db->fetchOne(
                "SELECT m.activo, tm.tipo FROM movimiento m
                 INNER JOIN tipo_movimiento tm ON m.id_tipo_mov = tm.id_tipo_mov
                 WHERE m.id_movimiento = :id",
                ['id' => $idNuevo]
            );
            if (!$mov || !$mov['activo']) {
                throw new Exception("El movimiento #{$idNuevo} no existe o está anulado.");
            }
            if ($mov['tipo'] !== $tipoEsperado) {
                throw new Exception("El movimiento #{$idNuevo} es de tipo '{$mov['tipo']}' y debe ser de tipo '{$tipoEsperado}'.");
            }
            $usado = $this->db->fetchOne(
                "SELECT id_odt FROM orden_de_trabajo
                 WHERE activo = TRUE AND id_odt <> :odt AND (id_mov_egreso = :m1 OR id_mov_devolucion = :m2)",
                ['odt' => $id_odt, 'm1' => $idNuevo, 'm2' => $idNuevo]
            );
            if ($usado) {
                throw new Exception("El movimiento #{$idNuevo} ya está vinculado a otra Orden de Trabajo.");
            }
        }

        if ($idActual !== null) {
            Database::execute(
                "UPDATE movimiento SET id_odt = NULL WHERE id_movimiento = :m AND id_odt = :odt",
                ['m' => $idActual, 'odt' => $id_odt]
            );
        }
        Database::execute("UPDATE orden_de_trabajo SET {$columna} = :m WHERE id_odt = :odt", ['m' => $idNuevo, 'odt' => $id_odt]);
        if ($idNuevo !== null) {
            Database::execute("UPDATE movimiento SET id_odt = :odt WHERE id_movimiento = :m", ['m' => $idNuevo, 'odt' => $id_odt]);
        }
    }

    /**
     * Movimientos que se pueden elegir al vincular: activos, del tipo pedido y no vinculados a otra orden.
     * Incluye el que ya tiene vinculado la orden indicada (id_odt = 0 al crear una nueva).
     * @param string $tipo 'Entrega' o 'Devolución'
     * @param int $id_odt Orden que se está editando (0 si es nueva)
     * @return array Movimientos con fecha, hora y operario
     */
    public function getMovimientosDisponibles($tipo, $id_odt = 0) {
        $sql = "SELECT m.id_movimiento, m.fecha, m.hora,
                       o.apellido || ', ' || o.nombre as nombre_operario
                FROM movimiento m
                INNER JOIN tipo_movimiento tm ON m.id_tipo_mov = tm.id_tipo_mov
                LEFT JOIN operario o ON m.id_operario = o.id_operario
                WHERE m.activo = TRUE AND tm.tipo = ?
                  AND NOT EXISTS (
                      SELECT 1 FROM orden_de_trabajo x
                      WHERE x.activo = TRUE AND x.id_odt <> ?
                        AND (x.id_mov_egreso = m.id_movimiento OR x.id_mov_devolucion = m.id_movimiento)
                  )
                ORDER BY m.fecha DESC, m.hora DESC, m.id_movimiento DESC";

        return $this->db->fetchAll($sql, [$tipo, (int)$id_odt]);
    }
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
        $sql = "SELECT 
                    (CASE WHEN id_mov_egreso IS NOT NULL THEN 1 ELSE 0 END) +
                    (CASE WHEN id_mov_devolucion IS NOT NULL THEN 1 ELSE 0 END) as total
                FROM orden_de_trabajo 
                WHERE id_odt = :id_odt";
        
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
     * Obtiene los movimientos vinculados a una ODT (egreso y devolución).
     * Devuelve los datos completos de cada movimiento para mostrar en el frontend.
     * @param int $id_odt ID de la orden de trabajo
     * @return array Movimientos vinculados con tipo y fecha
     */
    public function getMovimientosVinculados($id_odt) {
        $sql = "SELECT 
                    'egreso' as rol_en_odt,
                    m.id_movimiento, m.fecha, m.hora, m.observaciones,
                    tm.tipo as nombre_tipo_movimiento
                FROM orden_de_trabajo o
                INNER JOIN movimiento m ON o.id_mov_egreso = m.id_movimiento
                INNER JOIN tipo_movimiento tm ON m.id_tipo_mov = tm.id_tipo_mov
                WHERE o.id_odt = :id_odt AND o.id_mov_egreso IS NOT NULL
                UNION ALL
                SELECT 
                    'devolucion' as rol_en_odt,
                    m.id_movimiento, m.fecha, m.hora, m.observaciones,
                    tm.tipo as nombre_tipo_movimiento
                FROM orden_de_trabajo o
                INNER JOIN movimiento m ON o.id_mov_devolucion = m.id_movimiento
                INNER JOIN tipo_movimiento tm ON m.id_tipo_mov = tm.id_tipo_mov
                WHERE o.id_odt = :id_odt2 AND o.id_mov_devolucion IS NOT NULL
                ORDER BY fecha ASC";

        return $this->db->fetchAll($sql, ['id_odt' => $id_odt, 'id_odt2' => $id_odt]);
    }
}
?>