<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/reportes.php';

/**
 * Consultas de resumen para el Panel de Control (solo lectura).
 */
class Panel {
    private $db;

    public function __construct() {
        $this->db = new Database();
    }

    private function contar($sql, $params = []) {
        $fila = $this->db->fetchOne($sql, $params);
        return (int)($fila['total'] ?? 0);
    }

    // ---------------- Inventario ----------------

    public function totalInsumosActivos() {
        return $this->contar("SELECT COUNT(*) as total FROM insumos WHERE activo = TRUE");
    }

    /**
     * Materiales en stock crítico (stock actual <= stock mínimo, sin herramientas).
     * Misma definición que Reportes: ver Reportes::stockCritico().
     */
    public function totalStockBajo() {
        return Reportes::totalStockCritico();
    }

    /**
     * Materiales en stock crítico, los más críticos primero.
     */
    public function stockBajo($limite = 5) {
        return Reportes::stockCritico((int)$limite);
    }

    /**
     * Materiales perecederos con stock que vencen dentro de los próximos días (o ya vencidos).
     */
    public function proximosVencimientos($dias = 30, $limite = 5) {
        return Reportes::vencimientos((int)$dias, (int)$limite);
    }

    // ---------------- Herramientas ----------------

    public function totalHerramientasPrestadas() {
        return Reportes::totalHerramientasPrestadas();
    }

    public function totalHerramientasEnReparacion() {
        return Reportes::totalHerramientasEnReparacion();
    }

    // ---------------- Movimientos ----------------

    public function totalMovimientosHoy() {
        return $this->contar("SELECT COUNT(*) as total FROM movimiento WHERE activo = TRUE AND fecha = CURRENT_DATE");
    }

    public function ultimosMovimientos($limite = 5) {
        return $this->db->fetchAll(
            "SELECT m.id_movimiento, m.id_operario, m.fecha, m.hora, tm.tipo as nombre_tipo_movimiento,
                    o.apellido || ', ' || o.nombre as nombre_operario,
                    (SELECT COUNT(*) FROM movimiento_detalle md WHERE md.id_movimiento = m.id_movimiento) as total_items
             FROM movimiento m
             INNER JOIN tipo_movimiento tm ON m.id_tipo_mov = tm.id_tipo_mov
             LEFT JOIN operario o ON m.id_operario = o.id_operario
             WHERE m.activo = TRUE
             ORDER BY m.fecha DESC, m.hora DESC, m.id_movimiento DESC
             LIMIT " . (int)$limite
        );
    }

    // ---------------- Órdenes de trabajo ----------------

    /**
     * Cantidad de órdenes activas agrupadas por estado: ['Pendiente' => 3, ...]
     */
    public function ordenesPorEstado() {
        return Reportes::ordenesPorEstado();
    }

    /**
     * Órdenes que todavía no terminaron, las que vencen primero arriba.
     */
    public function ordenesAbiertas($limite = 5) {
        return $this->db->fetchAll(
            "SELECT o.id_odt, o.numero_ot, o.estado, o.fecha_final, l.localidad as nombre_localidad
             FROM orden_de_trabajo o
             LEFT JOIN localidad l ON o.id_localidad = l.id_localidad
             WHERE o.activo = TRUE AND COALESCE(o.estado, 'Pendiente') NOT IN ('Finalizada', 'Anulada')
             ORDER BY o.fecha_final ASC NULLS LAST, o.id_odt DESC
             LIMIT " . (int)$limite
        );
    }

    // ---------------- Personas y accesos ----------------

    public function totalOperariosActivos() {
        return $this->contar("SELECT COUNT(*) as total FROM operario WHERE activo = TRUE");
    }

    public function totalRubrosActivos() {
        return $this->contar("SELECT COUNT(*) as total FROM rubro WHERE activo = TRUE");
    }

    /**
     * Usuarios activos agrupados por rol: [['rol' => 'admin', 'total' => 1], ...]
     */
    public function usuariosPorRol() {
        return $this->db->fetchAll(
            "SELECT r.rol, COUNT(u.dni) as total
             FROM rol r LEFT JOIN usuario u ON u.id_rol = r.id_rol AND u.activo = TRUE
             GROUP BY r.id_rol, r.rol ORDER BY r.id_rol"
        );
    }

    public function totalUsuariosInactivos() {
        return $this->contar("SELECT COUNT(*) as total FROM usuario WHERE activo = FALSE");
    }
}
