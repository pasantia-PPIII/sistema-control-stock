<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/auditoria.php';

class Movimiento {
    private $db;

    public function __construct() {
        $this->db = new Database();
    }

    // =========================================================
    // 1. CONSULTAS (READ)
    // =========================================================

    /**
     * Obtiene todos los movimientos con sus relaciones legibles.
     * @param bool $incluir_anulados - Si es true, muestra los anulados.
     * @return array Lista de movimientos
     */
    public function getAll($incluir_anulados = false) {
        $sql = "SELECT m.id_movimiento, m.id_operario, m.fecha, m.hora, m.observaciones, m.activo,
                       tm.tipo as nombre_tipo_movimiento,
                       u.dni as dni_usuario,
                       o.apellido || ', ' || o.nombre as nombre_operario
                FROM movimiento m
                INNER JOIN tipo_movimiento tm ON m.id_tipo_mov = tm.id_tipo_mov
                INNER JOIN usuario u ON m.dni_usuario = u.dni
                LEFT JOIN operario o ON m.id_operario = o.id_operario";
        
        if (!$incluir_anulados) {
            $sql .= " WHERE m.activo = TRUE";
        }

        $sql .= " ORDER BY m.fecha DESC, m.hora DESC, m.id_movimiento DESC";

        return $this->db->fetchAll($sql);
    }

    /**
     * Obtiene un movimiento específico por su ID.
     * @param int $id_movimiento ID del movimiento
     * @return array|false Datos del movimiento
     */
    public function getById($id_movimiento) {
        $sql = "SELECT m.*, tm.tipo as nombre_tipo_movimiento,
                       u.nombre || ' ' || u.apellido as nombre_usuario,
                       o.apellido || ', ' || o.nombre as nombre_operario,
                       odt.numero_ot, mot.motivo AS nombre_motivo
                FROM movimiento m
                INNER JOIN tipo_movimiento tm ON m.id_tipo_mov = tm.id_tipo_mov
                INNER JOIN usuario u ON m.dni_usuario = u.dni
                LEFT JOIN operario o ON m.id_operario = o.id_operario
                LEFT JOIN orden_de_trabajo odt ON m.id_odt = odt.id_odt
                LEFT JOIN motivo_devolucion mot ON m.id_motivo = mot.id_motivo
                WHERE m.id_movimiento = :id_movimiento";

        return $this->db->fetchOne($sql, ['id_movimiento' => $id_movimiento]);
    }

    /**
     * Obtiene el DETALLE (los insumos) de un movimiento específico.
     * @param int $id_movimiento ID del movimiento
     * @return array Lista de insumos y cantidades de ese movimiento
     */
    public function getDetalles($id_movimiento) {
        $sql = "SELECT md.id_detalle, md.cantidad, md.id_estado_herramienta,
                       i.codigo, i.nombre as nombre_insumo,
                       t.tipo as tipo_insumo,
                       um.unidad_medida,
                       eh.estado as nombre_estado_herramienta
                FROM movimiento_detalle md
                INNER JOIN insumos i ON md.id_insumo = i.id_insumo
                LEFT JOIN tipo t ON i.id_tipo = t.id_tipo
                LEFT JOIN unidad_medida um ON i.id_unidad_medida = um.id_unidad_medida
                LEFT JOIN estado_herramienta eh ON md.id_estado_herramienta = eh.id_estado_herramienta
                WHERE md.id_movimiento = :id_movimiento
                ORDER BY i.nombre ASC";

        return $this->db->fetchAll($sql, ['id_movimiento' => $id_movimiento]);
    }

    /**
     * Obtiene movimientos de un operario específico.
     * @param int $id_operario ID del operario
     * @return array Lista de movimientos
     */
    public function getByOperario($id_operario) {
        $sql = "SELECT m.id_movimiento, m.fecha, m.hora, tm.tipo as nombre_tipo_movimiento, m.observaciones
                FROM movimiento m
                INNER JOIN tipo_movimiento tm ON m.id_tipo_mov = tm.id_tipo_mov
                WHERE m.id_operario = :id_operario AND m.activo = TRUE
                ORDER BY m.fecha DESC, m.hora DESC";
        
        return $this->db->fetchAll($sql, ['id_operario' => (int)$id_operario]);
    }

    // =========================================================
    // 2. CREACIÓN (CREATE) - CON TRANSACCIÓN
    // =========================================================

    /**
     * Crea un movimiento completo (Cabecera + Detalles) en una sola transacción.
     * Esto garantiza que si falla el detalle, no se crea la cabecera huérfana.
     * 
     * @param array $cabecera - Datos del movimiento (id_tipo_mov, id_operario, dni_usuario, fecha, hora, observaciones, id_odt opcional, id_motivo solo en devoluciones)
     * @param array $detalles - Array de arrays con los insumos (id_insumo, cantidad, id_estado_herramienta)
     * @return int - El ID del movimiento creado
     */
    public function create($cabecera, $detalles) {
        // Iniciamos una transacción de base de datos (ACID)
        $this->db->beginTransaction();

        try {
            // 1. Validar que haya detalles
            if (empty($detalles) || !is_array($detalles)) {
                throw new Exception("El movimiento debe tener al menos un insumo en el detalle.");
            }

            // 2. Preparar datos de la cabecera
            $cabecera['fecha'] = !empty($cabecera['fecha']) ? $cabecera['fecha'] : date('Y-m-d');
            $cabecera['hora'] = !empty($cabecera['hora']) ? $cabecera['hora'] : date('H:i:s');
            $cabecera['observaciones'] = !empty(trim($cabecera['observaciones'])) ? trim($cabecera['observaciones']) : null;
            $cabecera['id_operario'] = !empty($cabecera['id_operario']) ? $cabecera['id_operario'] : null;
            $cabecera['activo'] = true;

            // Tipo de movimiento (define el efecto sobre stock / herramientas)
            $tipoFila = $this->db->fetchOne("SELECT tipo FROM tipo_movimiento WHERE id_tipo_mov = :id", ['id' => (int)($cabecera['id_tipo_mov'] ?? 0)]);
            if (!$tipoFila) {
                throw new Exception("El tipo de movimiento indicado no existe.");
            }
            $tipoMov = $tipoFila['tipo'];

            // Motivo de la devolución (RF-34): obligatorio al devolver y exclusivo de las devoluciones
            $idMotivo = !empty($cabecera['id_motivo']) ? (int)$cabecera['id_motivo'] : null;
            $cabecera['id_motivo'] = $idMotivo;
            if ($tipoMov === self::TIPO_DEVOLUCION) {
                $motivo = $idMotivo !== null
                    ? $this->db->fetchOne("SELECT motivo FROM motivo_devolucion WHERE id_motivo = :id", ['id' => $idMotivo])
                    : null;
                if (!$motivo) {
                    throw new Exception("Debe indicar el motivo de la devolución.");
                }
                if ($motivo['motivo'] === 'Otro' && $cabecera['observaciones'] === null) {
                    throw new Exception("Si el motivo es 'Otro', detállelo en las observaciones.");
                }
            } elseif ($idMotivo !== null) {
                throw new Exception("Solo las devoluciones llevan motivo.");
            }

            // Vínculo opcional con una Orden de Trabajo (RF-27): solo entregas y devoluciones, y la orden debe estar habilitada.
            // Una misma orden puede tener varias entregas y devoluciones (RF-47).
            $idOdt = !empty($cabecera['id_odt']) ? (int)$cabecera['id_odt'] : null;
            $cabecera['id_odt'] = $idOdt;
            if ($idOdt !== null) {
                $this->validarOrdenVinculable($idOdt, $tipoMov);
            }

            // 3. Insertar la cabecera del movimiento
            $nuevoMovimiento = $this->db->insert('movimiento', $cabecera);
            $id_movimiento = $nuevoMovimiento['id_movimiento']; // Asumimos que tu clase Database devuelve el ID insertado

            // 4. Insertar cada línea del detalle y aplicar su efecto sobre el stock / estado de la herramienta
            $itemsAuditoria = [];
            foreach ($detalles as $detalle) {
                if (empty($detalle['id_insumo']) || $detalle['cantidad'] < 0 || ($detalle['cantidad'] == 0 && $tipoMov !== self::TIPO_AJUSTE)) {
                    throw new Exception("Cada detalle debe tener un insumo válido y una cantidad mayor a cero.");
                }

                $idEstado = !empty($detalle['id_estado_herramienta']) ? (int)$detalle['id_estado_herramienta'] : null;
                $cantidad = (float)$detalle['cantidad'];

                // AUDITORÍA: se pasa el id del movimiento para que el cambio de stock quede ligado a él
                $idEstado = $this->aplicarEfecto((int)$detalle['id_insumo'], $tipoMov, $cantidad, $idEstado, 1, (int)$id_movimiento);

                $this->db->insert('movimiento_detalle', [
                    'id_movimiento' => $id_movimiento,
                    'id_insumo' => (int)$detalle['id_insumo'],
                    'cantidad' => $cantidad,
                    'id_estado_herramienta' => $idEstado
                ]);
                $itemsAuditoria[] = ['id_insumo' => (int)$detalle['id_insumo'], 'cantidad' => $cantidad];
            }

            // AUDITORÍA: queda registrada la carga del movimiento (dentro de la misma transacción)
            Auditoria::registrarCambio(Auditoria::CREAR, 'movimiento', $id_movimiento,
                "{$tipoMov} #{$id_movimiento} registrada (" . count($itemsAuditoria) . " ítem/s)", null, [
                    'tipo'          => $tipoMov,
                    'id_operario'   => $cabecera['id_operario'],
                    'id_odt'        => $cabecera['id_odt'],
                    'id_motivo'     => $cabecera['id_motivo'],
                    'fecha'         => $cabecera['fecha'],
                    'hora'          => $cabecera['hora'],
                    'observaciones' => $cabecera['observaciones'],
                    'items'         => $itemsAuditoria,
                ]);

            // 5. Si todo salió bien, confirmamos la transacción
            $this->db->commit();
            return $id_movimiento;

        } catch (Throwable $e) {
            // Si algo falla, deshacemos TODO (ni la cabecera, ni los detalles, ni el stock se modifican)
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            if ($e instanceof PDOException) {
                error_log('Movimiento::create - ' . $e->getMessage());
                throw new Exception("Error al crear el movimiento. Intente nuevamente.");
            }
            throw new Exception($e->getMessage());
        }
    }

    // =========================================================
    // 2.1 EFECTO DE UN MOVIMIENTO SOBRE EL STOCK (PRIVADO)
    // =========================================================

    const TIPO_ENTREGA = 'Entrega';
    const TIPO_DEVOLUCION = 'Devolución';
    const TIPO_INGRESO = 'Ingreso';
    const TIPO_AJUSTE = 'Ajuste de Inventario';

    const ESTADO_DISPONIBLE = 1;
    const ESTADO_PRESTADA = 2;

    /**
     * Aplica (o revierte) el efecto de una línea de movimiento.
     * - Materiales: Entrega descuenta stock, Devolución e Ingreso lo suman (RF-24, RF-30, RF-33).
     *   No permite dejar el stock en negativo (RF-28).
     * - Herramientas: Entrega las marca "Prestada" y exige que estén disponibles (RF-26, RF-29);
     *   Devolución las pasa a "Disponible" o al estado indicado (RF-32). Cantidad siempre 1 (RF-15).
     * Debe llamarse dentro de una transacción: bloquea las filas involucradas.
     *
     * @param int $id_insumo ID del insumo
     * @param string $tipoMov Nombre del tipo de movimiento
     * @param float $cantidad Cantidad de la línea
     * @param int|null $idEstadoDev Estado con el que vuelve una herramienta (solo devoluciones)
     * - Ajuste de Inventario: la cantidad es el stock REAL contado; el stock del material pasa a valer exactamente eso.
     *   Solo materiales, y no se puede anular (se corrige registrando otro ajuste).
     * @param int $signo 1 = aplicar, -1 = revertir (anulación)
     * @return int|null Estado de herramienta a registrar en el detalle
     */
    private function aplicarEfecto($id_insumo, $tipoMov, $cantidad, $idEstadoDev, $signo, $idMovimiento = null) {
        // AUDITORÍA: origen de todo cambio de stock que se registre acá
        $origenStock = ($signo > 0) ? Auditoria::STOCK_MOVIMIENTO : Auditoria::STOCK_ANULACION;

        if (!in_array($tipoMov, [self::TIPO_ENTREGA, self::TIPO_DEVOLUCION, self::TIPO_INGRESO, self::TIPO_AJUSTE], true)) {
            throw new Exception("El tipo de movimiento '{$tipoMov}' todavía no admite registro con actualización de stock.");
        }

        $esAjuste = ($tipoMov === self::TIPO_AJUSTE);
        if ($esAjuste && $signo < 0) {
            throw new Exception("Un ajuste de inventario no se puede anular: registre otro ajuste con el valor correcto.");
        }

        $insumo = $this->db->fetchOne(
            "SELECT i.id_insumo, i.nombre, i.activo, i.id_stock_actual, i.id_estado_herramienta, t.tipo as nombre_tipo
             FROM insumos i INNER JOIN tipo t ON i.id_tipo = t.id_tipo
             WHERE i.id_insumo = :id FOR UPDATE OF i",
            ['id' => $id_insumo]
        );
        if (!$insumo) {
            throw new Exception("El insumo indicado no existe.");
        }
        if ($signo > 0 && !$insumo['activo']) {
            throw new Exception("El insumo '{$insumo['nombre']}' está deshabilitado.");
        }

        // ---- Herramientas: unidad entera. Cambian de estado y su stock acompaña al estado ----
        // Stock 1 = está en el pañol (Disponible). Stock 0 = no está (prestada, en reparación o dada de baja).
        if ($insumo['nombre_tipo'] === 'Herramienta') {
            if ($esAjuste) {
                throw new Exception("El ajuste de inventario aplica solo a materiales; '{$insumo['nombre']}' es una herramienta.");
            }
            if ($signo > 0 && abs($cantidad - 1.0) > 0.0001) {
                throw new Exception("La herramienta '{$insumo['nombre']}' se registra por unidad (cantidad 1).");
            }
            [$idStock, $stockActual] = $this->bloquearStock($insumo);
            $estadoActual = (int)$insumo['id_estado_herramienta'] ?: self::ESTADO_DISPONIBLE;

            $entrega = ($tipoMov === self::TIPO_ENTREGA);
            if ($signo < 0) {
                $entrega = !$entrega;
            }

            if ($entrega) {
                if ($signo > 0 && $estadoActual !== self::ESTADO_DISPONIBLE) {
                    throw new Exception("La herramienta '{$insumo['nombre']}' no está disponible para entregar (ya prestada, en reparación o dada de baja).");
                }
                if ($signo > 0 && $stockActual < 1) {
                    throw new Exception("La herramienta '{$insumo['nombre']}' no tiene stock en el pañol (stock {$stockActual}): no se puede entregar.");
                }
                $nuevoEstado = self::ESTADO_PRESTADA;
            } else {
                if ($signo > 0 && $estadoActual !== self::ESTADO_PRESTADA) {
                    throw new Exception("La herramienta '{$insumo['nombre']}' no figura como prestada, no se puede devolver.");
                }
                $nuevoEstado = ($signo > 0 && $idEstadoDev) ? $idEstadoDev : self::ESTADO_DISPONIBLE;
            }

            Database::execute(
                "UPDATE insumos SET id_estado_herramienta = :estado WHERE id_insumo = :id",
                ['estado' => $nuevoEstado, 'id' => $id_insumo]
            );
            // Vuelve al pañol solo si queda Disponible; en cualquier otro estado no hay unidad en el pañol
            $nuevoStockHerr = ($nuevoEstado === self::ESTADO_DISPONIBLE) ? 1 : 0;
            Database::execute(
                "UPDATE stock_actual SET stock_actual = :stock, fecha = CURRENT_DATE WHERE id_stock_actual = :id",
                ['stock' => $nuevoStockHerr, 'id' => $idStock]
            );
            // AUDITORÍA: cambio de stock de la herramienta (1 <-> 0)
            Auditoria::registrarStock((int)$id_insumo, $stockActual, (float)$nuevoStockHerr, $origenStock, $idMovimiento, $insumo['nombre']);
            return $signo > 0 ? $nuevoEstado : null;
        }

        // ---- Materiales: modifican stock_actual ----
        [$idStock, $stockActual] = $this->bloquearStock($insumo);

        if ($esAjuste) {
            Database::execute(
                "UPDATE stock_actual SET stock_actual = :nuevo, fecha = CURRENT_DATE WHERE id_stock_actual = :id",
                ['nuevo' => (string)$cantidad, 'id' => $idStock]
            );
            // AUDITORÍA: el ajuste fija el stock en la cantidad contada
            Auditoria::registrarStock((int)$id_insumo, $stockActual, (float)$cantidad, $origenStock, $idMovimiento, $insumo['nombre']);
            return null;
        }

        $descuenta = ($tipoMov === self::TIPO_ENTREGA);
        if ($signo < 0) {
            $descuenta = !$descuenta;
        }
        $delta = $descuenta ? -$cantidad : $cantidad;

        if ($stockActual + $delta < 0) {
            throw new Exception("Stock insuficiente para '{$insumo['nombre']}'. Disponible: {$stockActual}, requerido: {$cantidad}.");
        }

        Database::execute(
            "UPDATE stock_actual SET stock_actual = stock_actual + :delta, fecha = CURRENT_DATE WHERE id_stock_actual = :id",
            ['delta' => (string)$delta, 'id' => $idStock]
        );
        // AUDITORÍA: entrega (resta), devolución (suma) o anulación (revierte)
        Auditoria::registrarStock((int)$id_insumo, $stockActual, $stockActual + $delta, $origenStock, $idMovimiento, $insumo['nombre']);
        return null;
    }

    /**
     * Devuelve el registro de stock del insumo bloqueado para la transacción (FOR UPDATE).
     * Si el insumo no tenía registro de stock, se crea en cero y se vincula.
     * @param array $insumo Fila del insumo (id_insumo, id_stock_actual)
     * @return array [id_stock_actual, stock actual como float]
     */
    private function bloquearStock($insumo) {
        $idStock = $insumo['id_stock_actual'];
        if (empty($idStock)) {
            $nuevo = $this->db->insert('stock_actual', ['stock_actual' => 0]);
            $idStock = $nuevo['id_stock_actual'];
            Database::execute("UPDATE insumos SET id_stock_actual = :s WHERE id_insumo = :id", ['s' => $idStock, 'id' => $insumo['id_insumo']]);
        }
        $stock = $this->db->fetchOne(
            "SELECT stock_actual FROM stock_actual WHERE id_stock_actual = :id FOR UPDATE",
            ['id' => $idStock]
        );
        return [$idStock, (float)$stock['stock_actual']];
    }

    /**
     * Valida que un movimiento de este tipo se pueda vincular a la Orden de Trabajo indicada (RF-27):
     * solo entregas y devoluciones, la orden debe existir y estar habilitada, y no admite entregas nuevas
     * si está Finalizada o Anulada.
     */
    private function validarOrdenVinculable($idOdt, $tipoMov) {
        if (!in_array($tipoMov, [self::TIPO_ENTREGA, self::TIPO_DEVOLUCION], true)) {
            throw new Exception("Solo las entregas y devoluciones se pueden vincular a una Orden de Trabajo.");
        }
        $odt = $this->db->fetchOne("SELECT numero_ot, estado, activo FROM orden_de_trabajo WHERE id_odt = :id", ['id' => (int)$idOdt]);
        if (!$odt || !$odt['activo']) {
            throw new Exception("La Orden de Trabajo indicada no existe o está deshabilitada.");
        }
        if ($tipoMov === self::TIPO_ENTREGA && in_array($odt['estado'], ['Finalizada', 'Anulada'], true)) {
            throw new Exception("La Orden de Trabajo '{$odt['numero_ot']}' está {$odt['estado']}: no admite nuevas entregas.");
        }
    }

    // =========================================================
    // 3. ACTUALIZACIÓN (UPDATE) - RESTRINGIDA
    // =========================================================

    /**
     * Actualiza SOLO las observaciones, el operario o la Orden de Trabajo vinculada de un movimiento.
     * IMPORTANTE: No se permite cambiar fecha, hora, tipo o detalles para preservar la auditoría.
     * 
     * @param int $id_movimiento ID del movimiento
     * @param array $data Datos a actualizar (solo 'observaciones', 'id_operario' o 'id_odt'; id_odt vacío = sin orden)
     * @return bool
     */
    public function update($id_movimiento, $data) {
        try {
            // Filtramos estrictamente lo que se puede cambiar
            $allowedFields = [];
            if (isset($data['observaciones'])) {
                $allowedFields['observaciones'] = trim($data['observaciones']) ?: null;
            }
            if (isset($data['id_operario'])) {
                $allowedFields['id_operario'] = !empty($data['id_operario']) ? $data['id_operario'] : null;
            }

            // Orden de Trabajo (RF-27): se valida solo si cambia. Un movimiento anulado no se puede vincular
            if (array_key_exists('id_odt', $data)) {
                $idOdt = !empty($data['id_odt']) ? (int)$data['id_odt'] : null;
                $actual = $this->db->fetchOne(
                    "SELECT m.activo, m.id_odt, tm.tipo FROM movimiento m
                     INNER JOIN tipo_movimiento tm ON m.id_tipo_mov = tm.id_tipo_mov
                     WHERE m.id_movimiento = :id",
                    ['id' => (int)$id_movimiento]
                );
                if (!$actual) {
                    throw new Exception("El movimiento indicado no existe.");
                }
                if ($idOdt !== null && $idOdt !== (int)$actual['id_odt']) {
                    if (!$actual['activo']) {
                        throw new Exception("Un movimiento anulado no se puede vincular a una Orden de Trabajo.");
                    }
                    $this->validarOrdenVinculable($idOdt, $actual['tipo']);
                }
                $allowedFields['id_odt'] = $idOdt;
            }

            if (empty($allowedFields)) {
                throw new Exception("No se proporcionaron datos válidos para actualizar.");
            }

            // AUDITORÍA: el cambio y su registro se guardan juntos (este método no tenía transacción propia)
            return Auditoria::transaccion(function () use ($id_movimiento, $allowedFields) {
                $antes = $this->db->fetchOne(
                    "SELECT observaciones, id_operario, id_odt FROM movimiento WHERE id_movimiento = :id FOR UPDATE",
                    ['id' => $id_movimiento]
                );
                $ok = $this->db->update('movimiento', $allowedFields, 'id_movimiento = :id', ['id' => $id_movimiento]);
                [$a, $d] = Auditoria::diferencias($antes ?? [], $allowedFields);
                if ($d) {
                    Auditoria::registrarCambio(Auditoria::EDITAR, 'movimiento', $id_movimiento,
                        "Movimiento #{$id_movimiento} editado: " . implode(', ', array_keys($d)), $a, $d);
                }
                return $ok;
            });
        } catch (PDOException $e) {
            throw errorAmigable('Error al actualizar el movimiento', $e);
        }
    }

    // =========================================================
    // 4. ANULACIÓN (SOFT DELETE)
    // =========================================================

    /**
     * Anula un movimiento (lo marca como inactivo) y REVIERTE su efecto sobre el stock
     * y el estado de las herramientas, todo dentro de una transacción.
     * Falla si la reversión dejaría el stock en negativo.
     *
     * @param int $id_movimiento ID del movimiento
     * @return bool
     */
    public function anular($id_movimiento) {
        $this->db->beginTransaction();
        try {
            $mov = $this->db->fetchOne(
                "SELECT m.id_movimiento, m.activo, tm.tipo
                 FROM movimiento m INNER JOIN tipo_movimiento tm ON m.id_tipo_mov = tm.id_tipo_mov
                 WHERE m.id_movimiento = :id FOR UPDATE OF m",
                ['id' => (int)$id_movimiento]
            );
            if (!$mov) {
                throw new Exception("El movimiento indicado no existe.");
            }
            if (!$mov['activo']) {
                throw new Exception("El movimiento ya se encuentra anulado.");
            }

            $lineas = $this->db->fetchAll(
                "SELECT id_insumo, cantidad FROM movimiento_detalle WHERE id_movimiento = :id ORDER BY id_detalle",
                ['id' => (int)$id_movimiento]
            );
            foreach ($lineas as $l) {
                // AUDITORÍA: cada línea revertida queda como ANULACION_MOVIMIENTO ligada al movimiento
                $this->aplicarEfecto((int)$l['id_insumo'], $mov['tipo'], (float)$l['cantidad'], null, -1, (int)$id_movimiento);
            }

            Database::execute("UPDATE movimiento SET activo = FALSE WHERE id_movimiento = :id", ['id' => (int)$id_movimiento]);

            // AUDITORÍA: queda registrada la anulación
            Auditoria::registrarCambio(Auditoria::ANULAR, 'movimiento', (int)$id_movimiento,
                "{$mov['tipo']} #{$id_movimiento} anulada (stock revertido)", ['activo' => true], ['activo' => false]);

            $this->db->commit();
            return true;
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            if ($e instanceof PDOException) {
                error_log('Movimiento::anular - ' . $e->getMessage());
                throw new Exception("Error al anular el movimiento. Intente nuevamente.");
            }
            throw new Exception($e->getMessage());
        }
    }

    // =========================================================
    // 5. UTILIDADES
    // =========================================================

    /**
     * Obtiene los tipos de movimiento para el <select> del formulario.
     */
    public function getTiposMovimiento() {
        return $this->db->fetchAll("SELECT id_tipo_mov, tipo FROM tipo_movimiento ORDER BY tipo");
    }

    /**
     * Obtiene los motivos de devolución para el <select> del formulario (RF-34).
     */
    public function getMotivosDevolucion() {
        return $this->db->fetchAll("SELECT id_motivo, motivo FROM motivo_devolucion ORDER BY id_motivo");
    }

    /**
     * Obtiene los estados de herramienta para el <select> del detalle.
     */
    public function getEstadosHerramienta() {
        return $this->db->fetchAll("SELECT id_estado_herramienta, estado FROM estado_herramienta ORDER BY estado");
    }

    /**
     * Busca movimientos por observaciones, tipo de movimiento o nombre de operario.
     * @param string $termino Texto a buscar
     * @return array Resultados
     */
    public function buscar($termino) {
        $sql = "SELECT m.id_movimiento, m.id_operario, m.fecha, m.hora, m.observaciones, m.activo,
                       tm.tipo as nombre_tipo_movimiento,
                       u.dni as dni_usuario,
                       o.apellido || ', ' || o.nombre as nombre_operario
                FROM movimiento m
                INNER JOIN tipo_movimiento tm ON m.id_tipo_mov = tm.id_tipo_mov
                INNER JOIN usuario u ON m.dni_usuario = u.dni
                LEFT JOIN operario o ON m.id_operario = o.id_operario
                WHERE (m.observaciones ILIKE :termino
                   OR tm.tipo ILIKE :termino
                   OR o.apellido ILIKE :termino
                   OR o.nombre ILIKE :termino
                   OR CAST(m.id_movimiento AS TEXT) = :termino_exacto)
                AND m.activo = TRUE
                ORDER BY m.fecha DESC, m.hora DESC";

        return $this->db->fetchAll($sql, [
            'termino'       => '%' . $termino . '%',
            'termino_exacto' => $termino
        ]);
    }

    /**
     * Obtiene movimientos dentro de un rango de fechas.
     * @param string $fecha_desde Fecha inicio (formato Y-m-d)
     * @param string $fecha_hasta Fecha fin (formato Y-m-d)
     * @param bool $incluir_anulados Si es true, incluye los anulados
     * @return array Lista de movimientos en el rango
     */
    public function getByFecha($fecha_desde, $fecha_hasta, $incluir_anulados = false) {
        $sql = "SELECT m.id_movimiento, m.id_operario, m.fecha, m.hora, m.observaciones, m.activo,
                       tm.tipo as nombre_tipo_movimiento,
                       u.dni as dni_usuario,
                       o.apellido || ', ' || o.nombre as nombre_operario
                FROM movimiento m
                INNER JOIN tipo_movimiento tm ON m.id_tipo_mov = tm.id_tipo_mov
                INNER JOIN usuario u ON m.dni_usuario = u.dni
                LEFT JOIN operario o ON m.id_operario = o.id_operario
                WHERE m.fecha BETWEEN :fecha_desde AND :fecha_hasta";

        if (!$incluir_anulados) {
            $sql .= " AND m.activo = TRUE";
        }

        $sql .= " ORDER BY m.fecha DESC, m.hora DESC";

        return $this->db->fetchAll($sql, [
            'fecha_desde' => $fecha_desde,
            'fecha_hasta' => $fecha_hasta
        ]);
    }
}
?>