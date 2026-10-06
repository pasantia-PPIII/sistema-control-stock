<?php
/**
 * auth/login.php
 * Inicio de sesión por DNI y contraseña (Usuario::login).
 */
// Validar el token CSRF también antes de autenticar
define('CSRF_REQUIRE', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../clases/usuario.php';

// Límite de intentos: 5 fallidos por DNI dentro de 15 minutos (tabla intento_login).
// Un login correcto reinicia la cuenta (solo se cuentan los fallos posteriores al último acierto).
// Si la tabla no existe (base sin migrar) se registra en el log y el login sigue sin límite.
const LOGIN_MAX_INTENTOS = 5;
const LOGIN_VENTANA_MINUTOS = 15;

/**
 * Minutos que faltan para poder reintentar (0 si el DNI no está bloqueado).
 */
function minutosBloqueoLogin($dni) {
    try {
        $dni = substr(trim((string)$dni), 0, 20);
        $fila = Database::fetch(
            "SELECT COUNT(*) AS fallos,
                    CEIL(EXTRACT(EPOCH FROM (MIN(fecha_hora) + INTERVAL '" . LOGIN_VENTANA_MINUTOS . " minutes' - CURRENT_TIMESTAMP)) / 60) AS minutos
             FROM intento_login
             WHERE dni = ? AND exitoso = FALSE
               AND fecha_hora > CURRENT_TIMESTAMP - INTERVAL '" . LOGIN_VENTANA_MINUTOS . " minutes'
               AND fecha_hora > COALESCE(
                    (SELECT MAX(fecha_hora) FROM intento_login WHERE dni = ? AND exitoso = TRUE),
                    TIMESTAMP '-infinity')",
            [$dni, $dni]
        );
        if ($fila && (int)$fila['fallos'] >= LOGIN_MAX_INTENTOS) {
            return max(1, (int)$fila['minutos']);
        }
    } catch (Throwable $e) {
        error_log('Login (límite de intentos): ' . $e->getMessage());
    }
    return 0;
}

/**
 * Registra un intento de login, fallido o exitoso.
 */
function registrarIntentoLogin($dni, $exitoso) {
    try {
        Database::execute(
            "INSERT INTO intento_login (dni, ip, exitoso) VALUES (?, ?, ?)",
            [substr(trim((string)$dni), 0, 20), substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45), (bool)$exitoso]
        );
    } catch (Throwable $e) {
        error_log('Login (límite de intentos): ' . $e->getMessage());
    }
}

// Si ya inició sesión, redirigir al Panel de Control
if (isAuthenticated()) {
    header("Location: ../panel/dashboard.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dni_ingresado = trim($_POST['dni'] ?? '');
    $password_ingresada = $_POST['password'] ?? '';

    if ($dni_ingresado !== '' && $password_ingresada !== '') {
        try {
            $usuarioModel = new Usuario();

            // Bloqueo por 5 intentos fallidos: se revisa antes de verificar la contraseña
            $minutosBloqueo = minutosBloqueoLogin($dni_ingresado);
            if ($minutosBloqueo > 0) {
                $error = 'Demasiados intentos fallidos. Intente nuevamente en ' . $minutosBloqueo
                    . ($minutosBloqueo === 1 ? ' minuto.' : ' minutos.');
            } elseif ($usuarioModel->login($dni_ingresado, $password_ingresada)) {
                registrarIntentoLogin($dni_ingresado, true);
                // Evita fijación de sesión: nuevo ID de sesión tras autenticar
                session_regenerate_id(true);
                header("Location: ../panel/dashboard.php");
                exit;
            } else {
                registrarIntentoLogin($dni_ingresado, false);
                $error = 'DNI o contraseña incorrectos, o el usuario se encuentra inactivo.';
            }
        } catch (Throwable $e) {
            error_log('Login: ' . $e->getMessage());
            $error = 'Error de conexión con la base de datos.';
        }
    } else {
        $error = 'Por favor, complete todos los campos obligatorios.';
    }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión - Dirección de Arquitectura y Mantenimiento</title>
    <link rel="stylesheet" href="../assets/css/global.css">
    <link rel="stylesheet" href="../assets/css/login.css">
</head>

<body class="login-page">

    <div class="login-card-dual">
        <!-- Panel Izquierdo Institucional -->
        <aside class="brand-sidebar">
            <div class="logo-container">
                <img src="../assets/img/logo.jpeg" alt="Logo Taller ONABE" class="brand-avatar-img">
            </div>
            <h2 class="brand-title">Dirección de Arquitectura<br>y Mantenimiento</h2>
            <p class="brand-subtitle">Poder Judicial de Corrientes</p>
        </aside>

        <!-- Panel Derecho: Formulario -->
        <main class="form-section">
            <h1 class="form-title">Iniciar Sesión</h1>

            <?php if (!empty($error)): ?>
                <div class="login-alert login-alert-error">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form action="login.php" method="POST">
                <?= csrfField() ?>
                <div class="input-field-group">
                    <label for="dni">DNI</label>
                    <input type="text" id="dni" name="dni" placeholder="Ingresar DNI" required
                        inputmode="numeric" autocomplete="username" autofocus
                        value="<?= htmlspecialchars($_POST['dni'] ?? '') ?>">
                </div>

                <div class="input-field-group">
                    <label for="password">Contraseña</label>
                    <div class="password-wrapper">
                        <input type="password" id="password" name="password" placeholder="••••••••" required
                            autocomplete="current-password">
                        <button type="button" class="btn-toggle-pass" onclick="alternarPassword()"
                            title="Mostrar / Ocultar">
                            <svg id="eye-icon" xmlns="http://www.w3.org/2000/svg" width="18" height="18"
                                viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                stroke-linecap="round" stroke-linejoin="round">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn-submit-pill">Iniciar Sesión</button>
            </form>
        </main>
    </div>

    <script>
        function alternarPassword() {
            const passInput = document.getElementById('password');
            const eyeIcon = document.getElementById('eye-icon');
            if (passInput.type === 'password') {
                passInput.type = 'text';
                eyeIcon.style.stroke = '#0b152d';
            } else {
                passInput.type = 'password';
                eyeIcon.style.stroke = '#94a3b8';
            }
        }
    </script>
</body>

</html>
