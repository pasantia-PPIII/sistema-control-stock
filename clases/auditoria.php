<?php
/**
 * clases/auditoria.php
 * Modelo y servicios de Auditoría y Trazabilidad de Acciones.
 */

require_once __DIR__ . '/../config/database.php';

class Auditoria
{
    private Database $db;

    public function __construct()
    {
        $this->db = new Database();
    }

    /**
     * Registra un evento en la tabla de auditoría.
     *
     * @param array $datos Claves: dni_usuario, nombre_usuario, rol_usuario, accion, modulo, descripcion, ip, user_agent, datos_anteriores, datos_nuevos
     * @return int|false ID de auditoría generado o false si falló
     */
    public function registrar(array $datos): int|false
    {
        try {
            $sql = "INSERT INTO auditoria (
                        dni_usuario, nombre_usuario, rol_usuario, accion, modulo,
                        descripcion, ip, user_agent, datos_anteriores, datos_nuevos
                    ) VALUES (
                        :dni_usuario, :nombre_usuario, :rol_usuario, :accion, :modulo,
                        :descripcion, :ip, :user_agent, :datos_anteriores, :datos_nuevos
                    ) RETURNING id_auditoria";

            $datosAnterioresJson = null;
            if (isset($datos['datos_anteriores']) && $datos['datos_anteriores'] !== null) {
                $datosAnterioresJson = is_string($datos['datos_anteriores'])
                    ? $datos['datos_anteriores']
                    : json_encode($datos['datos_anteriores'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            }

            $datosNuevosJson = null;
            if (isset($datos['datos_nuevos']) && $datos['datos_nuevos'] !== null) {
                $datosNuevosJson = is_string($datos['datos_nuevos'])
                    ? $datos['datos_nuevos']
                    : json_encode($datos['datos_nuevos'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            }

            $params = [
                'dni_usuario'      => !empty($datos['dni_usuario']) ? substr((string)$datos['dni_usuario'], 0, 20) : null,
                'nombre_usuario'   => !empty($datos['nombre_usuario']) ? substr((string)$datos['nombre_usuario'], 0, 150) : null,
                'rol_usuario'      => !empty($datos['rol_usuario']) ? substr((string)$datos['rol_usuario'], 0, 50) : null,
                'accion'           => strtoupper(trim((string)($datos['accion'] ?? 'GENERAL'))),
                'modulo'           => strtoupper(trim((string)($datos['modulo'] ?? 'SISTEMA'))),
                'descripcion'      => trim((string)($datos['descripcion'] ?? '')),
                'ip'               => !empty($datos['ip']) ? substr((string)$datos['ip'], 0, 45) : null,
                'user_agent'       => !empty($datos['user_agent']) ? substr((string)$datos['user_agent'], 0, 500) : null,
                'datos_anteriores' => $datosAnterioresJson,
                'datos_nuevos'     => $datosNuevosJson
            ];

            $res = Database::fetch($sql, $params);
            return $res ? (int)$res['id_auditoria'] : true;
        } catch (Throwable $e) {
            error_log('[Auditoria] Fallo al registrar evento: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Construye las cláusulas WHERE y parámetros a partir de los filtros.
     */
    private function buildWhere(array $filtros): array
    {
        $conds = [];
        $params = [];

        if (!empty($filtros['fecha_desde'])) {
            $conds[] = "fecha_hora >= :fecha_desde";
            $params['fecha_desde'] = $filtros['fecha_desde'] . ' 00:00:00';
        }

        if (!empty($filtros['fecha_hasta'])) {
            $conds[] = "fecha_hora <= :fecha_hasta";
            $params['fecha_hasta'] = $filtros['fecha_hasta'] . ' 23:59:59';
        }

        if (!empty($filtros['modulo'])) {
            $conds[] = "modulo = :modulo";
            $params['modulo'] = strtoupper(trim($filtros['modulo']));
        }

        if (!empty($filtros['accion'])) {
            $conds[] = "accion = :accion";
            $params['accion'] = strtoupper(trim($filtros['accion']));
        }

        if (!empty($filtros['dni_usuario'])) {
            $conds[] = "dni_usuario = :dni_usuario";
            $params['dni_usuario'] = trim($filtros['dni_usuario']);
        }

        if (!empty($filtros['buscar'])) {
            $conds[] = "(descripcion ILIKE :buscar OR nombre_usuario ILIKE :buscar OR dni_usuario ILIKE :buscar OR ip ILIKE :buscar)";
            $params['buscar'] = '%' . trim($filtros['buscar']) . '%';
        }

        $whereSql = !empty($conds) ? 'WHERE ' . implode(' AND ', $conds) : '';
        return [$whereSql, $params];
    }

    /**
     * Cuenta total de registros filtrados para la paginación.
     */
    public function contarConFiltros(array $filtros): int
    {
        [$whereSql, $params] = $this->buildWhere($filtros);
        $sql = "SELECT COUNT(*) AS total FROM auditoria {$whereSql}";
        $row = Database::fetch($sql, $params);
        return $row ? (int)$row['total'] : 0;
    }

    /**
     * Lista los registros de auditoría aplicando filtros, paginación y orden descendente.
     */
    public function listarConFiltros(array $filtros, int $pagina = 1, int $porPagina = 25): array
    {
        $pagina = max(1, $pagina);
        $porPagina = max(5, min(100, $porPagina));
        $offset = ($pagina - 1) * $porPagina;

        [$whereSql, $params] = $this->buildWhere($filtros);

        $sql = "SELECT id_auditoria, fecha_hora, dni_usuario, nombre_usuario, rol_usuario,
                       accion, modulo, descripcion, ip, user_agent,
                       datos_anteriores, datos_nuevos
                FROM auditoria
                {$whereSql}
                ORDER BY fecha_hora DESC, id_auditoria DESC
                LIMIT {$porPagina} OFFSET {$offset}";

        return Database::fetchAll($sql, $params);
    }

    /**
     * Obtiene el detalle completo de un registro por su ID.
     */
    public function obtenerPorId(int $id): ?array
    {
        $sql = "SELECT * FROM auditoria WHERE id_auditoria = :id";
        return Database::fetch($sql, ['id' => $id]);
    }

    /**
     * Retorna la lista de módulos únicos registrados para los selectores de filtro.
     */
    public function obtenerModulos(): array
    {
        $sql = "SELECT DISTINCT modulo FROM auditoria ORDER BY modulo ASC";
        $filas = Database::fetchAll($sql);
        return array_column($filas, 'modulo');
    }

    /**
     * Retorna la lista de acciones únicas registradas para los selectores de filtro.
     */
    public function obtenerAcciones(): array
    {
        $sql = "SELECT DISTINCT accion FROM auditoria ORDER BY accion ASC";
        $filas = Database::fetchAll($sql);
        return array_column($filas, 'accion');
    }

    /**
     * Retorna la lista de usuarios únicos registrados para los filtros.
     */
    public function obtenerUsuarios(): array
    {
        $sql = "SELECT DISTINCT dni_usuario, nombre_usuario 
                FROM auditoria 
                WHERE dni_usuario IS NOT NULL 
                ORDER BY nombre_usuario ASC";
        return Database::fetchAll($sql);
    }

    /**
     * Utilidad: calcula las diferencias clave entre un estado anterior y uno nuevo.
     * Genera la estructura ANTES / DESPUES requerida:
     * [
     *    'antes' => ['campo' => 'valor anterior'],
     *    'despues' => ['campo' => 'valor nuevo']
     * ]
     */
    public static function calcularDiferencias(array $anterior, array $nuevo, array $ignorar = ['contrasena', 'password', 'fecha_actualizacion']): array
    {
        $antes = [];
        $despues = [];

        foreach ($nuevo as $clave => $valorNuevo) {
            if (in_array($clave, $ignorar, true)) {
                continue;
            }

            $valorAnterior = $anterior[$clave] ?? null;

            // Comparar convirtiendo a string si ambos no son nulos para evitar discrepancias int vs string
            $cambio = false;
            if ($valorAnterior === null && $valorNuevo !== null) {
                $cambio = true;
            } elseif ($valorAnterior !== null && $valorNuevo === null) {
                $cambio = true;
            } elseif ((string)$valorAnterior !== (string)$valorNuevo) {
                $cambio = true;
            }

            if ($cambio) {
                $antes[$clave] = $valorAnterior;
                $despues[$clave] = $valorNuevo;
            }
        }

        return [
            'antes'   => $antes,
            'despues' => $despues
        ];
    }
}
