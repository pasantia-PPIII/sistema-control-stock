<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/reportes.php';
require_once __DIR__ . '/auditoria.php';

class Insumo {
    private $db;

    // Constructor: inicializa la conexión a la base de datos
    public function __construct() {
        $this->db = new Database();
    }

    // =========================================================
    // 1. CONSULTAS (READ)
    // =========================================================

    /**
     * Obtiene todos los insumos con sus relaciones.
     * @param bool $incluir_inactivos - Si es true, muestra también los deshabilitados.
     * @return array Lista de insumos
     */
    public function getAll($incluir_inactivos = false) {
        $sql = "SELECT i.*, 
                       um.unidad_medida, 
                       t.tipo as nombre_tipo, 
                       r.nombre as nombre_rubro, 
                       u.ubicacion as nombre_ubicacion,
                       s.stock_actual,
                       eh.estado as nombre_estado_herramienta
                FROM insumos i
                LEFT JOIN unidad_medida um ON i.id_unidad_medida = um.id_unidad_medida
                LEFT JOIN tipo t ON i.id_tipo = t.id_tipo
                LEFT JOIN rubro r ON i.id_rubro = r.id_rubro
                LEFT JOIN ubicacion u ON i.id_ubicacion = u.id_ubicacion
                LEFT JOIN stock_actual s ON i.id_stock_actual = s.id_stock_actual
                LEFT JOIN estado_herramienta eh ON i.id_estado_herramienta = eh.id_estado_herramienta";

        if (!$incluir_inactivos) {
            $sql .= " WHERE i.activo = TRUE";
        }

        $sql .= " ORDER BY i.nombre ASC";

        return $this->db->fetchAll($sql);
    }

    /**
     * Obtiene un insumo específico por su código.
     * @param string $codigo Código del insumo
     * @return array|false Datos del insumo
     */
    public function getByCodigo($codigo) {
        $sql = "SELECT i.*, 
                       um.unidad_medida, 
                       t.tipo as nombre_tipo, 
                       r.nombre as nombre_rubro, 
                       u.ubicacion as nombre_ubicacion,
                       s.stock_actual,
                       eh.estado as nombre_estado_herramienta
                FROM insumos i
                LEFT JOIN unidad_medida um ON i.id_unidad_medida = um.id_unidad_medida
                LEFT JOIN tipo t ON i.id_tipo = t.id_tipo
                LEFT JOIN rubro r ON i.id_rubro = r.id_rubro
                LEFT JOIN ubicacion u ON i.id_ubicacion = u.id_ubicacion
                LEFT JOIN stock_actual s ON i.id_stock_actual = s.id_stock_actual
                LEFT JOIN estado_herramienta eh ON i.id_estado_herramienta = eh.id_estado_herramienta
                WHERE i.codigo = :codigo AND i.activo = TRUE";

        return $this->db->fetchOne($sql, ['codigo' => $codigo]);
    }

    /**
     * Datos mínimos de un insumo por código, esté activo o deshabilitado.
     * (getByCodigo() solo devuelve los activos; esto sirve para habilitar uno deshabilitado.)
     * @param string $codigo Código del insumo
     * @return array|null codigo, nombre y activo; null si no existe
     */
    public function getEstadoByCodigo($codigo) {
        return $this->db->fetchOne(
            "SELECT codigo, nombre, activo FROM insumos WHERE codigo = :codigo",
            ['codigo' => $codigo]
        );
    }

    /**
     * Últimos movimientos que involucran a un insumo (pantalla de detalle).
     * @param string $codigo Código del insumo
     * @param int $limite Cantidad máxima de filas
     * @return array Movimientos con cantidad, tipo y operario
     */
    public function getMovimientos($codigo, $limite = 20) {
        $sql = "SELECT m.id_movimiento, m.id_operario, m.fecha, m.hora, md.cantidad,
                       tm.tipo as nombre_tipo_movimiento,
                       o.apellido || ', ' || o.nombre as nombre_operario
                FROM movimiento_detalle md
                INNER JOIN insumos i ON md.id_insumo = i.id_insumo
                INNER JOIN movimiento m ON md.id_movimiento = m.id_movimiento
                INNER JOIN tipo_movimiento tm ON m.id_tipo_mov = tm.id_tipo_mov
                LEFT JOIN operario o ON m.id_operario = o.id_operario
                WHERE i.codigo = :codigo AND m.activo = TRUE
                ORDER BY m.fecha DESC, m.hora DESC, m.id_movimiento DESC
                LIMIT " . (int)$limite;

        return $this->db->fetchAll($sql, ['codigo' => $codigo]);
    }

    /**
     * Obtiene un insumo activo por su clave primaria (id_insumo), con sus relaciones.
     * Equivale a getByCodigo() pero buscando por id (p. ej. enlaces ?editar=ID desde Reportes).
     * @param int $id_insumo ID del insumo
     * @return array|false Datos del insumo, o false si no existe o está deshabilitado
     */
    public function getById($id_insumo) {
        $sql = "SELECT i.*, 
                       um.unidad_medida, 
                       t.tipo as nombre_tipo, 
                       r.nombre as nombre_rubro, 
                       u.ubicacion as nombre_ubicacion,
                       s.stock_actual,
                       eh.estado as nombre_estado_herramienta
                FROM insumos i
                LEFT JOIN unidad_medida um ON i.id_unidad_medida = um.id_unidad_medida
                LEFT JOIN tipo t ON i.id_tipo = t.id_tipo
                LEFT JOIN rubro r ON i.id_rubro = r.id_rubro
                LEFT JOIN ubicacion u ON i.id_ubicacion = u.id_ubicacion
                LEFT JOIN stock_actual s ON i.id_stock_actual = s.id_stock_actual
                LEFT JOIN estado_herramienta eh ON i.id_estado_herramienta = eh.id_estado_herramienta
                WHERE i.id_insumo = :id_insumo AND i.activo = TRUE";

        return $this->db->fetchOne($sql, ['id_insumo' => (int)$id_insumo]);
    }

    /**
     * Busca insumos por nombre o código (para el buscador del dashboard).
     * @param string $termino Texto a buscar
     * @return array Resultados
     */
    public function buscar($termino) {
        $sql = "SELECT i.*, r.nombre as nombre_rubro, s.stock_actual
                FROM insumos i
                LEFT JOIN rubro r ON i.id_rubro = r.id_rubro
                LEFT JOIN stock_actual s ON i.id_stock_actual = s.id_stock_actual
                WHERE (" . sqlBusquedaSinTildes('i.nombre') . " OR " . sqlBusquedaSinTildes('i.codigo') . ")
                AND i.activo = TRUE";
        
        return $this->db->fetchAll($sql, ['termino' => '%' . $termino . '%']);
    }

    /**
     * Insumos activos para el autocompletado de los formularios, paginados en la base (LIMIT/OFFSET).
     * Busca por código o nombre. Con $termino vacío devuelve todos.
     * @return array Hasta $limite insumos desde $offset, con unidad, stock y estado de herramienta
     */
    public function buscarParaSelect(string $termino = '', int $limite = 10, int $offset = 0): array {
        $sql = "SELECT i.id_insumo, i.codigo, i.nombre, i.id_tipo, i.id_estado_herramienta,
                       um.unidad_medida,
                       s.stock_actual
                FROM insumos i
                LEFT JOIN unidad_medida um ON i.id_unidad_medida = um.id_unidad_medida
                LEFT JOIN stock_actual s ON i.id_stock_actual = s.id_stock_actual
                WHERE i.activo = TRUE";
        $params = [];
        if ($termino !== '') {
            $sql .= " AND (" . sqlBusquedaSinTildes('i.codigo') . " OR " . sqlBusquedaSinTildes('i.nombre') . ")";
            $params['termino'] = '%' . addcslashes($termino, '%_\\') . '%';
        }
        $sql .= " ORDER BY i.nombre ASC, i.id_insumo ASC
                  LIMIT " . max(1, $limite) . " OFFSET " . max(0, $offset);
        return $this->db->fetchAll($sql, $params);
    }

    /**
     * Obtiene materiales en stock crítico (stock actual <= stock mínimo).
     * Delega en Reportes::stockCritico(), la definición única que usan también el Panel y los Reportes.
     * Las herramientas no se incluyen: su stock es 1 (en el pañol) o 0 (prestada), no una cantidad a reponer.
     * @return array Insumos con stock crítico
     */
    public function getStockBajo() {
        return Reportes::stockCritico();
    }

    /**
     * Verifica si ya existe un insumo con el mismo código.
     * @param string $codigo Código a verificar
     * @param string|null $codigo_original Código actual (para excluirlo al editar)
     * @return bool true si ya existe
     */
    public function existeCodigo($codigo, $codigo_original = null) {
        $sql = "SELECT COUNT(*) as total FROM insumos WHERE codigo = :codigo";
        $params = ['codigo' => $codigo];

        if ($codigo_original !== null) {
            $sql .= " AND codigo != :codigo_original";
            $params['codigo_original'] = $codigo_original;
        }

        $result = $this->db->fetchOne($sql, $params);
        return $result['total'] > 0;
    }

    // =========================================================
    // 2. CREAR, ACTUALIZAR Y DESHABILITAR (CREATE / UPDATE / SOFT DELETE)
    // =========================================================

    /**
     * Crea un nuevo insumo en la base de datos.
     * El stock inicial es OBLIGATORIO para los materiales y viaja en $data['stock_inicial'] (no es una
     * columna de insumos; 0 es válido). Una herramienta es una unidad entera: siempre nace con stock 1
     * (en el pañol) y se ignora el valor enviado.
     * @param array $data Datos del insumo
     * @return array El registro insertado completo
     */
    public function create($data) {
        try {
            $esHerramienta = ((int)($data['id_tipo'] ?? 0) === 2);

            $stockInicial = null;
            if (isset($data['stock_inicial']) && $data['stock_inicial'] !== '') {
                if (!is_numeric($data['stock_inicial']) || (float)$data['stock_inicial'] < 0) {
                    throw new Exception("El stock inicial debe ser un número igual o mayor a cero.");
                }
                $stockInicial = (float)$data['stock_inicial'];
            }
            if (!$esHerramienta && $stockInicial === null) {
                throw new Exception("El stock inicial es obligatorio (si no hay existencias, cargue 0).");
            }

            // Whitelist de campos permitidos para el INSERT
            // Evita que campos extra del formulario (ej: csrf_token) rompan el query
            $camposPermitidos = [
                'codigo', 'nombre', 'id_unidad_medida', 'id_tipo', 'id_rubro',
                'id_ubicacion', 'stock_minimo', 'es_perecedero',
                'fecha_vencimiento', 'serie_modelo', 'fecha_registro', 'activo'
            ];
            $data = array_intersect_key($data, array_flip($camposPermitidos));

            // OJO: el endpoint envía false cuando no es perecedero, por eso se usa !empty y no isset
            $data['es_perecedero'] = !empty($data['es_perecedero']);

            if (empty($data['fecha_vencimiento']) || !$data['es_perecedero']) {
                $data['fecha_vencimiento'] = null;
            }

            // Aseguramos que al crearse, el insumo nazca activo
            $data['activo'] = true;

            // Las herramientas nacen "Disponible" y con 1 unidad en el pañol (necesario para controlar préstamos)
            if ($esHerramienta) {
                $data['id_estado_herramienta'] = 1;
            }

            // El stock vive en su propia tabla: se crea el registro con el stock inicial y se vincula al insumo
            $this->db->beginTransaction();
            try {
                $stock = $this->db->insert('stock_actual', ['stock_actual' => $esHerramienta ? 1 : $stockInicial]);
                $data['id_stock_actual'] = $stock['id_stock_actual'];

                $insumo = $this->db->insert('insumos', $data);

                // AUDITORÍA: carga del insumo y de su stock inicial (herramienta: 1 unidad)
                $stockFinal = $esHerramienta ? 1.0 : $stockInicial;
                Auditoria::registrarCambio(Auditoria::CREAR, 'insumo', $insumo['codigo'],
                    "Insumo {$insumo['codigo']} '{$insumo['nombre']}' cargado", null, $insumo + ['stock_actual' => $stockFinal]);
                Auditoria::registrarStock((int)$insumo['id_insumo'], 0.0, $stockFinal, Auditoria::STOCK_ALTA, null, 'Stock inicial');

                $this->db->commit();
                return $insumo;
            } catch (Throwable $e) {
                if ($this->db->inTransaction()) {
                    $this->db->rollBack();
                }
                throw $e;
            }
        } catch (PDOException $e) {
            error_log('Insumo::create - ' . $e->getMessage());
            if ($e->getCode() == '23505') {
                throw new Exception("Ya existe un insumo con ese código.");
            }
            throw new Exception("Error al crear el insumo. Verifique los datos ingresados.");
        }
    }

    /**
     * Actualiza los datos de un insumo existente.
     * Si $data trae 'stock_actual', el stock del material pasa a valer exactamente ese número
     * (carga de más stock o corrección). No aplica a herramientas: su stock lo manejan las entregas y devoluciones.
     * @param string $codigo Código del insumo a actualizar
     * @param array $data Nuevos datos
     * @return bool true si se actualizó correctamente
     */
    public function update($codigo, $data) {
        try {
            $nuevoStock = null;
            if (isset($data['stock_actual']) && $data['stock_actual'] !== '') {
                $nuevoStock = (float)$data['stock_actual'];
                if ($nuevoStock < 0) {
                    throw new Exception("El stock no puede ser negativo.");
                }
            }

            // Whitelist de campos permitidos para el UPDATE
            // Excluye 'activo' (solo por deshabilitar/habilitar) y 'codigo' (PK inmutable)
            $camposPermitidos = [
                'nombre', 'id_unidad_medida', 'id_tipo', 'id_rubro',
                'id_ubicacion', 'stock_minimo', 'es_perecedero',
                'fecha_vencimiento', 'serie_modelo'
            ];
            $data = array_intersect_key($data, array_flip($camposPermitidos));

            // El tipo no se puede cambiar: convertir un material en herramienta (o al revés)
            // dejaría stock y estado inconsistentes. Se deshabilita el insumo y se crea otro.
            if (array_key_exists('id_tipo', $data)) {
                $actual = $this->db->fetchOne(
                    "SELECT id_tipo FROM insumos WHERE codigo = :codigo",
                    ['codigo' => $codigo]
                );
                if ($actual && (int) $actual['id_tipo'] !== (int) $data['id_tipo']) {
                    throw new Exception("El tipo de insumo no se puede cambiar después de crearlo. Deshabilite este insumo y cree uno nuevo del tipo correcto.");
                }
            }

            // Solo normalizar los campos que realmente vienen en la petición: así una edición
            // parcial no pisa es_perecedero / fecha_vencimiento con valores vacíos.
            if (array_key_exists('es_perecedero', $data)) {
                $data['es_perecedero'] = !empty($data['es_perecedero']);
            }
            if (array_key_exists('fecha_vencimiento', $data) && empty($data['fecha_vencimiento'])) {
                $data['fecha_vencimiento'] = null;
            }

            // 'activo' se maneja exclusivamente con deshabilitar() y habilitar()
            // Datos y stock se guardan juntos: si uno falla, no se aplica ninguno
            $this->db->beginTransaction();
            try {
                // AUDITORÍA: se guarda cómo estaba antes de tocar nada
                $antes = $this->db->fetchOne("SELECT * FROM insumos WHERE codigo = :codigo FOR UPDATE", ['codigo' => $codigo]);

                $resultado = $this->db->update('insumos', $data, 'codigo = :codigo', ['codigo' => $codigo]);
                $stockAnterior = null;
                if ($nuevoStock !== null) {
                    $stockAnterior = $this->fijarStock($codigo, $nuevoStock);   // registra el cambio en auditoria_stock
                }

                // AUDITORÍA: solo los campos que cambiaron (el stock también, si cambió)
                [$a, $d] = Auditoria::diferencias($antes ?? [], $data);
                if ($stockAnterior !== null && abs($stockAnterior - $nuevoStock) > 0.0001) {
                    $a['stock_actual'] = $stockAnterior;
                    $d['stock_actual'] = $nuevoStock;
                }
                if ($d) {
                    Auditoria::registrarCambio(Auditoria::EDITAR, 'insumo', $codigo,
                        "Insumo {$codigo} '" . ($antes['nombre'] ?? '') . "' editado: " . implode(', ', array_keys($d)), $a, $d);
                }

                $this->db->commit();
                return $resultado;
            } catch (Throwable $e) {
                if ($this->db->inTransaction()) {
                    $this->db->rollBack();
                }
                throw $e;
            }
        } catch (PDOException $e) {
            throw errorAmigable('Error al actualizar el insumo', $e);
        }
    }

    /**
     * Deja el stock de un material exactamente en $nuevoStock. Debe llamarse dentro de una transacción.
     * Registra el cambio en auditoria_stock y devuelve el stock que había antes.
     * @param string $codigo Código del insumo
     * @param float $nuevoStock Stock real (0 o más)
     * @return float Stock anterior
     */
    private function fijarStock($codigo, $nuevoStock) {
        $insumo = $this->db->fetchOne(
            "SELECT i.id_insumo, i.id_stock_actual, t.tipo
             FROM insumos i INNER JOIN tipo t ON i.id_tipo = t.id_tipo
             WHERE i.codigo = :codigo FOR UPDATE OF i",
            ['codigo' => $codigo]
        );
        if (!$insumo) {
            throw new Exception("No se encontró el insumo '{$codigo}'.");
        }
        if ($insumo['tipo'] === 'Herramienta') {
            throw new Exception("El stock de una herramienta lo controlan sus entregas y devoluciones, no se edita a mano.");
        }

        $stockAnterior = 0.0;
        if (empty($insumo['id_stock_actual'])) {
            $nuevo = $this->db->insert('stock_actual', ['stock_actual' => $nuevoStock]);
            Database::execute(
                "UPDATE insumos SET id_stock_actual = :s WHERE id_insumo = :id",
                ['s' => $nuevo['id_stock_actual'], 'id' => $insumo['id_insumo']]
            );
        } else {
            // AUDITORÍA: se lee (y bloquea) el stock vigente antes de pisarlo
            $fila = $this->db->fetchOne(
                "SELECT stock_actual FROM stock_actual WHERE id_stock_actual = :id FOR UPDATE",
                ['id' => $insumo['id_stock_actual']]
            );
            $stockAnterior = (float)$fila['stock_actual'];
            Database::execute(
                "UPDATE stock_actual SET stock_actual = :stock, fecha = CURRENT_DATE WHERE id_stock_actual = :id",
                ['stock' => (string)$nuevoStock, 'id' => $insumo['id_stock_actual']]
            );
        }
        Auditoria::registrarStock((int)$insumo['id_insumo'], $stockAnterior, (float)$nuevoStock,
            Auditoria::STOCK_EDICION, null, 'Edición manual desde Insumos');
        return $stockAnterior;
    }

    /**
     * DESHABILITA (Soft Delete) un insumo del sistema.
     * No borra el registro, solo cambia su estado a inactivo para auditoría.
     * @param string $codigo Código del insumo a deshabilitar
     * @return bool true si se deshabilitó correctamente
     */
    public function deshabilitar($codigo) {
        try {
            // AUDITORÍA: el cambio y su registro se guardan juntos
            return Auditoria::transaccion(function () use ($codigo) {
                $ok = $this->db->update('insumos', ['activo' => false], 'codigo = :codigo', ['codigo' => $codigo]);
                Auditoria::registrarCambio(Auditoria::DESHABILITAR, 'insumo', $codigo,
                    "Insumo {$codigo} deshabilitado", ['activo' => true], ['activo' => false]);
                return $ok;
            });
        } catch (PDOException $e) {
            throw errorAmigable('Error al deshabilitar el insumo', $e);
        }
    }

    /**
     * HABILITA (Reactiva) un insumo que fue deshabilitado previamente.
     * @param string $codigo Código del insumo a habilitar
     * @return bool true si se habilitó correctamente
     */
    public function habilitar($codigo) {
        try {
            return Auditoria::transaccion(function () use ($codigo) {
                $ok = $this->db->update('insumos', ['activo' => true], 'codigo = :codigo', ['codigo' => $codigo]);
                Auditoria::registrarCambio(Auditoria::HABILITAR, 'insumo', $codigo,
                    "Insumo {$codigo} habilitado", ['activo' => false], ['activo' => true]);
                return $ok;
            });
        } catch (PDOException $e) {
            throw errorAmigable('Error al habilitar el insumo', $e);
        }
    }

    // =========================================================
    // 3. CATÁLOGOS (Para los <select> de los formularios)
    // =========================================================

    public function getUnidadesMedida() {
        return $this->db->fetchAll("SELECT * FROM unidad_medida ORDER BY unidad_medida");
    }

    public function getTipos() {
        return $this->db->fetchAll("SELECT * FROM tipo ORDER BY tipo");
    }

    public function getRubros() {
        // Solo muestra rubros activos en los selectores de formularios
        return $this->db->fetchAll("SELECT * FROM rubro WHERE activo = TRUE ORDER BY nombre");
    }

    public function getUbicaciones() {
        return $this->db->fetchAll("SELECT * FROM ubicacion ORDER BY ubicacion");
    }
}
?>