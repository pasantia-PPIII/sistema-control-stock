<?php
require_once __DIR__ . '/../config/database.php';

/**
 * Avisos de contraseña olvidada: el usuario los genera desde auth/forgot_password.php
 * y el administrador los atiende desde la sección Usuarios.
 * Todos los métodos fallan en silencio (log) si la tabla no existe, para no romper pantallas en bases sin migrar.
 */
class SolicitudContrasena {

    /**
     * Registra el aviso de un DNI activo. Si ya tiene uno pendiente no se duplica.
     * @return bool true si el DNI existe y está activo (haya creado un aviso nuevo o no)
     */
    public static function crear(string $dni): bool {
        $dni = substr(trim($dni), 0, 20);
        try {
            $usuario = Database::fetch("SELECT dni FROM usuario WHERE dni = ? AND activo = TRUE", [$dni]);
            if (!$usuario) {
                return false;
            }
            $pendiente = Database::fetch("SELECT 1 AS existe FROM solicitud_contrasena WHERE dni = ? AND atendida = FALSE", [$dni]);
            if (!$pendiente) {
                Database::execute(
                    "INSERT INTO solicitud_contrasena (dni, ip) VALUES (?, ?)",
                    [$dni, substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45)]
                );
            }
            return true;
        } catch (Throwable $e) {
            error_log('Solicitud de contraseña (crear): ' . $e->getMessage());
            return false;
        }
    }

    /** Avisos pendientes, del más antiguo al más reciente, con el nombre del usuario. */
    public static function pendientes(): array {
        try {
            return Database::fetchAll(
                "SELECT s.id_solicitud, s.dni, s.fecha_hora, u.nombre, u.apellido
                 FROM solicitud_contrasena s
                 INNER JOIN usuario u ON u.dni = s.dni
                 WHERE s.atendida = FALSE
                 ORDER BY s.fecha_hora ASC"
            );
        } catch (Throwable $e) {
            error_log('Solicitud de contraseña (pendientes): ' . $e->getMessage());
            return [];
        }
    }

    public static function contarPendientes(): int {
        try {
            $fila = Database::fetch("SELECT COUNT(*) AS total FROM solicitud_contrasena WHERE atendida = FALSE");
            return (int)($fila['total'] ?? 0);
        } catch (Throwable $e) {
            error_log('Solicitud de contraseña (contar): ' . $e->getMessage());
            return 0;
        }
    }

    /** Marca como atendidos todos los avisos pendientes de un DNI. */
    public static function atender(string $dni): void {
        try {
            Database::execute(
                "UPDATE solicitud_contrasena SET atendida = TRUE, fecha_atencion = CURRENT_TIMESTAMP
                 WHERE dni = ? AND atendida = FALSE",
                [$dni]
            );
        } catch (Throwable $e) {
            error_log('Solicitud de contraseña (atender): ' . $e->getMessage());
        }
    }
}
