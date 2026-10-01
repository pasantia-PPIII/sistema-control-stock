<?php
// clases/orden_de_trabajo.php

require_once __DIR__ . '/../config/database.php';

class OrdenDeTrabajo {
    private PDO $pdo;

    public function __construct(?PDO $pdo = null) {
        $this->pdo = $pdo ?? Database::getConnection();
    }

    /**
     * Cuenta total de OTs para paginación
     */
    public function contarFiltrados($busqueda = null, $estado = null): int {
        if (is_array($busqueda)) {
            $estado   = $busqueda['estado'] ?? null;
            $busqueda = $busqueda['busqueda'] ?? null;
        }

        $sql = "SELECT COUNT(*) FROM orden_de_trabajo ot WHERE ot.activo = TRUE";
        $params = [];

        if (!empty($busqueda)) {
            $sql .= " AND (ot.numero_ot ILIKE :b OR ot.pa ILIKE :b OR ot.trabajo_a_realizar ILIKE :b)";
            $params[':b'] = "%{$busqueda}%";
        }
        if (!empty($estado)) {
            $sql .= " AND ot.estado = :est";
            $params[':est'] = $estado;
        }

        $res = Database::fetch($sql, $params);
        return (int)($res['count'] ?? 0);
    }

    /**
     * Listado paginado de órdenes de trabajo
     */
    public function getFiltrados($busqueda = null, $estado = null, int $limite = 15, int $offset = 0): array {
        if (is_array($busqueda)) {
            $filtros  = $busqueda;
            $busqueda = $filtros['busqueda'] ?? null;
            $estado   = $filtros['estado'] ?? null;
            $limite   = isset($filtros['limite']) ? (int)$filtros['limite'] : $limite;
            $offset   = isset($filtros['offset']) ? (int)$filtros['offset'] : $offset;
        }

        $sql = "SELECT ot.*, 
                       loc.localidad AS localidad_nombre, 
                       jur.jurisdiccion AS jurisdiccion_nombre
                FROM orden_de_trabajo ot
                LEFT JOIN localidad loc ON ot.id_localidad = loc.id_localidad
                LEFT JOIN jurisdiccion jur ON ot.id_jurisdiccion = jur.id_jurisdiccion
                WHERE ot.activo = TRUE";
        $params = [];

        if (!empty($busqueda)) {
            $sql .= " AND (ot.numero_ot ILIKE :b OR ot.pa ILIKE :b OR ot.trabajo_a_realizar ILIKE :b)";
            $params[':b'] = "%{$busqueda}%";
        }
        if (!empty($estado)) {
            $sql .= " AND ot.estado = :est";
            $params[':est'] = $estado;
        }

        $sql .= " ORDER BY ot.id_odt DESC LIMIT :limite OFFSET :offset";

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerPorId(int $id): ?array {
        $sql = "SELECT ot.*, 
                       loc.localidad AS localidad_nombre, 
                       jur.jurisdiccion AS jurisdiccion_nombre
                FROM orden_de_trabajo ot
                LEFT JOIN localidad loc ON ot.id_localidad = loc.id_localidad
                LEFT JOIN jurisdiccion jur ON ot.id_jurisdiccion = jur.id_jurisdiccion
                WHERE ot.id_odt = :id";
        return Database::fetch($sql, [':id' => $id]);
    }

    public function crear(array $d): bool {
        $sql = "INSERT INTO orden_de_trabajo (
                    numero_ot, pa, trabajo_a_realizar, id_localidad, id_jurisdiccion,
                    fecha_inicio, fecha_final, hora_inicio, hora_final, estado,
                    archivo_pdf, observaciones, activo
                ) VALUES (
                    :ot, :pa, :trabajo, :loc, :jur,
                    :f_ini, :f_fin, :h_ini, :h_fin, :estado,
                    :pdf, :obs, TRUE
                )";

        return Database::execute($sql, [
            ':ot'      => $d['numero_ot'],
            ':pa'      => $d['pa'] ?? null,
            ':trabajo' => $d['trabajo_a_realizar'] ?? null,
            ':loc'     => !empty($d['id_localidad']) ? (int)$d['id_localidad'] : null,
            ':jur'     => !empty($d['id_jurisdiccion']) ? (int)$d['id_jurisdiccion'] : null,
            ':f_ini'   => !empty($d['fecha_inicio']) ? $d['fecha_inicio'] : null,
            ':f_fin'   => !empty($d['fecha_final']) ? $d['fecha_final'] : null,
            ':h_ini'   => !empty($d['hora_inicio']) ? $d['hora_inicio'] : null,
            ':h_fin'   => !empty($d['hora_final']) ? $d['hora_final'] : null,
            ':estado'  => $d['estado'] ?? 'Pendiente',
            ':pdf'     => $d['archivo_pdf'] ?? null,
            ':obs'     => $d['observaciones'] ?? null
        ]);
    }

    public function actualizarEstado(int $id, string $nuevoEstado): bool {
        return Database::execute("UPDATE orden_de_trabajo SET estado = :est WHERE id_odt = :id", [
            ':est' => $nuevoEstado,
            ':id'  => $id
        ]);
    }

    public function alternarEstado(int $id): bool {
        return Database::execute("UPDATE orden_de_trabajo SET activo = NOT activo WHERE id_odt = :id", [':id' => $id]);
    }

    public function getLocalidades(): array {
        return Database::fetchAll("SELECT id_localidad, localidad FROM localidad ORDER BY localidad ASC");
    }

    public function getJurisdicciones(): array {
        return Database::fetchAll("SELECT id_jurisdiccion, jurisdiccion FROM jurisdiccion ORDER BY jurisdiccion ASC");
    }
}