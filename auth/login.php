<?php
session_start();
require_once __DIR__ . '/../config/database.php';

// Si ya inició sesión, redirigir al catálogo de insumos
if (isset($_SESSION['usuario_id'])) {
    header("Location: ../insumo/dashboard.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario_ingresado = trim($_POST['usuario'] ?? '');
    $password_ingresada = trim($_POST['password'] ?? '');

    if (!empty($usuario_ingresado) && !empty($password_ingresada)) {
        try {
            // Consulta compatible con PostgreSQL (tabla usuario y rol)
            $sql = "SELECT u.id_usuario, u.usuario, u.contrasena, u.nombre, u.apellido, u.activo,
                       r.rol AS nombre_rol
                FROM usuario u
                INNER JOIN rol r ON u.id_rol = r.id_rol
                WHERE u.usuario = :usr";

            $user = Database::fetch($sql, [':usr' => $usuario_ingresado]);

            if ($user) {
                if (!$user['activo']) {
                    $error = 'El usuario se encuentra inactivo.';
                } elseif (password_verify($password_ingresada, $user['contrasena']) || $password_ingresada === $user['contrasena']) {
                    $_SESSION['usuario_id'] = $user['id_usuario'];
                    $_SESSION['usuario'] = $user['usuario'];
                    $_SESSION['usuario_nombre'] = $user['nombre'] . ' ' . $user['apellido'];
                    $_SESSION['rol'] = strtolower($user['nombre_rol']);

                    header("Location: ../insumo/dashboard.php");
                    exit;
                } else {
                    $error = 'Usuario o contraseña incorrectos.';
                }
            } else {
                $error = 'Usuario o contraseña incorrectos.';
            }
        } catch (PDOException $e) {
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
                <img src="../assets/img/logo.jpeg" alt="Logo Taller ONABE" class="brand-avatar-img"
                    onerror="this.src='../assets/img/logo.png'">
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
                <div class="input-field-group">
                    <label for="usuario">Usuario / DNI</label>
                    <input type="text" id="usuario" name="usuario" placeholder="Ingresar usuario" required
                        autocomplete="username" autofocus value="<?= htmlspecialchars($_POST['usuario'] ?? '') ?>">
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