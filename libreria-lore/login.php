<?php
/* =============================================
   FYLCAD — Login de usuario
   Archivo: login.php
============================================= */
session_start();
require_once 'config/db.php';

// Si ya está logueado, redirigir al dashboard
if (isset($_SESSION['usuario_id'])) {
    header('Location: dashboard.php');
    exit;
}

$errores = [];
$email   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email']    ?? '');
    $password = trim($_POST['password'] ?? '');

    // Validaciones básicas
    if (empty($email) || empty($password)) {
        $errores[] = "Completa todos los campos.";
    } else {
        $db   = getDB();
        $stmt = $db->prepare("SELECT id, nombre, password, plan, activo FROM usuarios WHERE email = ?");
        $stmt->execute([$email]);
        $usuario = $stmt->fetch();

        if (!$usuario || !password_verify($password, $usuario['password'])) {
            $errores[] = "Email o contraseña incorrectos.";
        } elseif (!$usuario['activo']) {
            $errores[] = "Tu cuenta está desactivada. Contacta soporte.";
        } else {
            // Login exitoso — crear sesión
            session_regenerate_id(true);
            $_SESSION['usuario_id']     = $usuario['id'];
            $_SESSION['usuario_nombre'] = $usuario['nombre'];
            $_SESSION['usuario_plan']   = $usuario['plan'];

            // Redirigir a donde intentaba ir, o al dashboard
            $destino = $_SESSION['redirect_after_login'] ?? 'dashboard.php';
            unset($_SESSION['redirect_after_login']);
            header('Location: ' . $destino);
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>FYLCAD — Iniciar sesión</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/auth.css">
</head>
<body>

<div class="auth-bg">
    <svg class="topo-svg" viewBox="0 0 800 800" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
        <ellipse cx="600" cy="400" rx="480" ry="240" fill="none" stroke="#00e5c0" stroke-width="0.6" opacity="0.08"/>
        <ellipse cx="600" cy="400" rx="380" ry="180" fill="none" stroke="#00e5c0" stroke-width="0.6" opacity="0.08"/>
        <ellipse cx="600" cy="400" rx="280" ry="125" fill="none" stroke="#00e5c0" stroke-width="0.6" opacity="0.08"/>
        <ellipse cx="600" cy="400" rx="180" ry="78"  fill="none" stroke="#00e5c0" stroke-width="0.6" opacity="0.08"/>
        <ellipse cx="600" cy="400" rx="90"  ry="38"  fill="none" stroke="#00e5c0" stroke-width="0.6" opacity="0.08"/>
        <ellipse cx="100" cy="700" rx="300" ry="150" fill="none" stroke="#00e5c0" stroke-width="0.6" opacity="0.05"/>
        <ellipse cx="100" cy="700" rx="200" ry="100" fill="none" stroke="#00e5c0" stroke-width="0.6" opacity="0.05"/>
    </svg>
    <div class="bg-glow"></div>
</div>

<div class="auth-wrapper">

    <!-- Logo -->
    <a href="index.php" class="auth-logo">FYL<span>CAD</span></a>

    <!-- Card -->
    <div class="auth-card">

        <div class="auth-card-header">
            <h1>Bienvenido de vuelta</h1>
            <p>Ingresa a tu cuenta para continuar</p>
        </div>

        <!-- Alerta de éxito desde registro -->
        <?php if (isset($_GET['registered'])): ?>
        <div class="alert alert-success">
            <span class="alert-icon">✓</span>
            <div><strong>¡Cuenta creada!</strong> Ya puedes iniciar sesión.</div>
        </div>
        <?php endif; ?>

        <!-- Errores -->
        <?php if (!empty($errores)): ?>
        <div class="alert alert-error">
            <span class="alert-icon">!</span>
            <div>
                <?php foreach ($errores as $e): ?>
                    <p><?= htmlspecialchars($e) ?></p>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Formulario -->
        <form method="POST" action="login.php" class="auth-form">

            <div class="form-group">
                <label for="email">Correo electrónico</label>
                <div class="input-wrap">
                    <span class="input-icon">✉</span>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        placeholder="correo@ejemplo.com"
                        value="<?= htmlspecialchars($email) ?>"
                        autocomplete="email"
                        required>
                </div>
            </div>

            <div class="form-group">
                <div class="label-row">
                    <label for="password">Contraseña</label>
                    <a href="forgot_password.php" class="forgot-link">¿Olvidaste tu contraseña?</a>
                </div>
                <div class="input-wrap">
                    <span class="input-icon">🔒</span>
                    <input
                        type="password"
                        id="password"
                        name="password"
                        placeholder="Tu contraseña"
                        autocomplete="current-password"
                        required>
                    <button type="button" class="toggle-pass" data-target="password">👁</button>
                </div>
            </div>

            <!-- Recordarme -->
            <div class="check-row">
                <label class="checkbox-label">
                    <input type="checkbox" name="recordar" id="recordar">
                    <span class="checkbox-custom"></span>
                    Mantener sesión iniciada
                </label>
            </div>

            <button type="submit" class="btn-submit">
                <span class="btn-text">Iniciar sesión</span>
                <span class="btn-arrow">→</span>
            </button>

        </form>

        <div class="auth-divider"><span>o</span></div>

        <div class="auth-footer">
            ¿No tienes cuenta? <a href="register.php">Crear cuenta gratis</a>
        </div>

    </div>

</div>

<script>
// Toggle mostrar/ocultar contraseña
document.querySelectorAll(".toggle-pass").forEach(btn => {
    btn.addEventListener("click", () => {
        const input = document.getElementById(btn.dataset.target);
        input.type  = input.type === "password" ? "text" : "password";
        btn.textContent = input.type === "password" ? "👁" : "🙈";
    });
});
</script>

</body>
</html>