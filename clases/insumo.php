<?php
require_once __DIR__ . '/../config/database.php';

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
                       s.stock_actual
                FROM insumos i
                LEFT JOIN unidad_medida um ON i.id_unidad_medida = um.id_unidad_medida
                LEFT JOIN tipo t ON i.id_tipo = t.id_tipo
                LEFT JOIN rubro r ON i.id_rubro = r.id_rubro
                LEFT JOIN ubicacion u ON i.id_ubicacion = u.id_ubicacion
                LEFT JOIN stock_actual s ON i.id_stock_actual = s.id_stock_actual";
        
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
     * Últimos movimientos que involucran a un insumo (pantalla de detalle).
     * @param string $codigo Código del insumo
     * @param int $limite Cantidad máxima de filas
     * @return array Movimientos con cantidad, tipo y operario
     */
    public function getMovimientos($codigo, $limite = 20) {
        $sql = "SELECT m.id_movimiento, m.fecha, m.hora, md.cantidad,
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
     * Alias de getByCodigo() para consistencia de nomenclatura con las demás clases.
     * @param string $codigo Código del insumo
     * @return array|false Datos del insumo
     */
    public function getById($codigo) {
        return $this->getByCodigo($codigo);
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
                WHERE (i.nombre ILIKE :termino OR i.codigo ILIKE :termino)
                AND i.activo = TRUE";
        
        return $this->db->fetchAll($sql, ['termino' => '%' . $termino . '%']);
    }

    /**
     * Obtiene insumos con stock bajo (menor al stock_minimo).
     * @return array Insumos con stock crítico
     */
    public function getStockBajo() {
        $sql = "SELECT i.codigo, i.nombre, i.stock_minimo, s.stock_actual
                FROM insumos i
                LEFT JOIN stock_actual s ON i.id_stock_actual = s.id_stock_actual
                WHERE s.stock_actual < i.stock_minimo 
                AND i.activo = TRUE";
        
        return $this->db->fetchAll($sql);
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
     * @param array $data Datos del insumo
     * @return array El registro insertado completo
     */
    public function create($data) {
        try {
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

            // El stock vive en su propia tabla: se crea el registro en cero y se vincula al insumo
            $this->db->beginTransaction();
            try {
                $stock = $this->db->insert('stock_actual', ['stock_actual' => 0]);
                $data['id_stock_actual'] = $stock['id_stock_actual'];

                // Las herramientas nacen "Disponible" (necesario para controlar préstamos)
                if ((int)($data['id_tipo'] ?? 0) === 2) {
                    $data['id_estado_herramienta'] = 1;
                }

                $insumo = $this->db->insert('insumos', $data);
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
     * @param string $codigo Código del insumo a actualizar
     * @param array $data Nuevos datos
     * @return bool true si se actualizó correctamente
     */
    public function update($codigo, $data) {
        try {
            // Whitelist de campos permitidos para el UPDATE
            // Excluye 'activo' (solo por deshabilitar/habilitar) y 'codigo' (PK inmutable)
            $camposPermitidos = [
                'nombre', 'id_unidad_medida', 'id_tipo', 'id_rubro',
                'id_ubicacion', 'stock_minimo', 'es_perecedero',
                'fecha_vencimiento', 'serie_modelo'
            ];
            $data = array_intersect_key($data, array_flip($camposPermitidos));

            $data['es_perecedero'] = !empty($data['es_perecedero']);
            
            if (empty($data['fecha_vencimiento'])) {
                $data['fecha_vencimiento'] = null;
            }

            // 'activo' se maneja exclusivamente con deshabilitar() y habilitar()
            return $this->db->update('insumos', $data, 'codigo = :codigo', ['codigo' => $codigo]);
        } catch (PDOException $e) {
            throw errorAmigable('Error al actualizar el insumo', $e);
        }
    }

    /**
     * DESHABILITA (Soft Delete) un insumo del sistema.
     * No borra el registro, solo cambia su estado a inactivo para auditoría.
     * @param string $codigo Código del insumo a deshabilitar
     * @return bool true si se deshabilitó correctamente
     */
    public function deshabilitar($codigo) {
        try {
            $data = ['activo' => false];
            return $this->db->update('insumos', $data, 'codigo = :codigo', ['codigo' => $codigo]);
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
            $data = ['activo' => true];
            return $this->db->update('insumos', $data, 'codigo = :codigo', ['codigo' => $codigo]);
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