<?php
/**
 * auth/forgot_password.php
 * El usuario avisa que olvidó su contraseña; el administrador lo ve en Usuarios y le asigna una nueva.
 */
define('CSRF_REQUIRE', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../clases/solicitud_contrasena.php';

if (isAuthenticated()) {
    header("Location: ../panel/dashboard.php");
    exit;
}

$enviado = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dni = trim($_POST['dni'] ?? '');
    if ($dni === '') {
        $error = 'Por favor, ingrese su DNI.';
    } else {
        // Se registra solo si el DNI existe y está activo, pero el mensaje es siempre el mismo
        // para no revelar qué DNI están registrados.
        SolicitudContrasena::crear($dni);
        $enviado = true;
    }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar Contraseña - Dirección de Arquitectura y Mantenimiento</title>
    <link rel="stylesheet" href="../assets/css/global.css">
    <link rel="stylesheet" href="../assets/css/login.css">
</head>

<body class="login-page">

    <div class="login-card-dual">
        <aside class="brand-sidebar">
            <div class="logo-container">
                <img src="../assets/img/logo.jpeg" alt="Logo Taller ONABE" class="brand-avatar-img">
            </div>
            <h2 class="brand-title">Dirección de Arquitectura<br>y Mantenimiento</h2>
            <p class="brand-subtitle">Poder Judicial de Corrientes</p>
        </aside>

        <main class="form-section">
            <h1 class="form-title">Recuperar Contraseña</h1>

            <?php if ($enviado): ?>
                <div class="login-alert login-alert-success">
                    Si el DNI corresponde a un usuario activo, el aviso fue enviado al administrador.
                </div>
            <?php elseif ($error !== ''): ?>
                <div class="login-alert login-alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <p class="form-instructions">
                Ingrese su DNI para avisar que olvidó su contraseña.
                <strong>Comuníquese con el administrador del sistema</strong> para que le asigne una contraseña nueva.
            </p>

            <?php if (!$enviado): ?>
            <form action="forgot_password.php" method="POST">
                <?= csrfField() ?>
                <div class="input-field-group">
                    <label for="dni">DNI</label>
                    <input type="text" id="dni" name="dni" placeholder="Ingresar DNI" required
                        inputmode="numeric" autocomplete="username" autofocus
                        value="<?= htmlspecialchars($_POST['dni'] ?? '') ?>">
                </div>
                <button type="submit" class="btn-submit-pill btn-recovery">Avisar al administrador</button>
            </form>
            <?php endif; ?>

            <hr class="form-divider">
            <div class="forgot-link-wrapper">
                <a href="login.php" class="back-to-login">Volver a Iniciar Sesión</a>
            </div>
        </main>
    </div>
</body>

</html>
