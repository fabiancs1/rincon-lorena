<?php
/* =============================================
   FYLCAD — Resetear contraseña
   Archivo: reset_password.php
============================================= */
session_start();
require_once 'config/db.php';

if (isset($_SESSION['usuario_id'])) { header('Location: dashboard.php'); exit; }

$token  = trim($_GET['token'] ?? '');
$error  = '';
$exito  = false;
$usuario = null;

if (empty($token)) { header('Location: login.php'); exit; }

// Buscar token válido y no expirado
$db   = getDB();
$stmt = $db->prepare("
    SELECT id, nombre FROM usuarios
    WHERE reset_token = ? AND reset_expira > NOW() AND activo = 1
");
$stmt->execute([$token]);
$usuario = $stmt->fetch();

if (!$usuario) {
    $error = "El enlace es inválido o ha expirado. Solicita uno nuevo.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $usuario) {
    $nueva     = $_POST['password_nueva']    ?? '';
    $confirmar = $_POST['password_confirmar']?? '';

    if (strlen($nueva) < 8) {
        $error = "La contraseña debe tener al menos 8 caracteres.";
    } elseif ($nueva !== $confirmar) {
        $error = "Las contraseñas no coinciden.";
    } else {
        $hash = password_hash($nueva, PASSWORD_BCRYPT);
        $db->prepare("
            UPDATE usuarios
            SET password = ?, reset_token = NULL, reset_expira = NULL
            WHERE id = ?
        ")->execute([$hash, $usuario['id']]);
        $exito = true;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>FYLCAD — Nueva contraseña</title>
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
        <ellipse cx="600" cy="400" rx="300" ry="140" fill="none" stroke="#00e5c0" stroke-width="0.6" opacity="0.08"/>
        <ellipse cx="600" cy="400" rx="140" ry="60"  fill="none" stroke="#00e5c0" stroke-width="0.6" opacity="0.08"/>
    </svg>
    <div class="bg-glow"></div>
</div>

<div class="auth-wrapper">
    <a href="index.php" class="auth-logo">FYL<span>CAD</span></a>

    <div class="auth-card">
        <div class="auth-card-header">
            <h1>Nueva contraseña</h1>
            <p><?= $usuario ? "Hola, {$usuario['nombre']}. Elige una contraseña segura." : "Verificando enlace..." ?></p>
        </div>

        <?php if ($exito): ?>
        <div class="alert alert-success">
            <span class="alert-icon">✓</span>
            <div><strong>¡Contraseña actualizada!</strong> Ya puedes <a href="login.php">iniciar sesión</a>.</div>
        </div>

        <?php elseif ($error): ?>
        <div class="alert alert-error">
            <span class="alert-icon">!</span>
            <div><?= htmlspecialchars($error) ?></div>
        </div>
        <?php if (!$usuario): ?>
        <div class="auth-footer">
            <a href="forgot_password.php">Solicitar nuevo enlace →</a>
        </div>
        <?php endif; ?>

        <?php elseif ($usuario): ?>
        <form method="POST" class="auth-form">
            <div class="form-group">
                <label for="password_nueva">Nueva contraseña</label>
                <div class="input-wrap">
                    <span class="input-icon">🔒</span>
                    <input type="password" id="password_nueva" name="password_nueva"
                           placeholder="Mínimo 8 caracteres" required minlength="8"
                           autocomplete="new-password">
                    <button type="button" class="toggle-pass" data-target="password_nueva">👁</button>
                </div>
                <div class="pass-strength"><div class="pass-bar" id="passBar"></div></div>
                <span class="pass-label" id="passLabel"></span>
            </div>
            <div class="form-group">
                <label for="password_confirmar">Confirmar contraseña</label>
                <div class="input-wrap">
                    <span class="input-icon">🔒</span>
                    <input type="password" id="password_confirmar" name="password_confirmar"
                           placeholder="Repite la contraseña" required
                           autocomplete="new-password">
                    <button type="button" class="toggle-pass" data-target="password_confirmar">👁</button>
                </div>
            </div>
            <button type="submit" class="btn-submit">
                <span class="btn-text">Guardar nueva contraseña</span>
                <span class="btn-arrow">→</span>
            </button>
        </form>
        <?php endif; ?>

        <div class="auth-footer"><a href="login.php">← Volver al login</a></div>
    </div>
</div>

<script>
document.querySelectorAll(".toggle-pass").forEach(btn => {
    btn.addEventListener("click", () => {
        const input = document.getElementById(btn.dataset.target);
        input.type  = input.type === "password" ? "text" : "password";
        btn.textContent = input.type === "password" ? "👁" : "🙈";
    });
});

const passInput = document.getElementById("password_nueva");
const passBar   = document.getElementById("passBar");
const passLabel = document.getElementById("passLabel");

passInput?.addEventListener("input", () => {
    const v = passInput.value;
    let score = 0;
    if (v.length >= 8) score++;
    if (/[A-Z]/.test(v)) score++;
    if (/[0-9]/.test(v)) score++;
    if (/[^A-Za-z0-9]/.test(v)) score++;
    const niveles = [
        { label:"", color:"transparent", w:"0%" },
        { label:"Débil",     color:"#ef4444", w:"25%" },
        { label:"Regular",   color:"#f59e0b", w:"50%" },
        { label:"Buena",     color:"#3b82f6", w:"75%" },
        { label:"Excelente", color:"#00e5c0", w:"100%" }
    ];
    const n = niveles[score];
    if (passBar)  { passBar.style.width=n.w; passBar.style.background=n.color; }
    if (passLabel){ passLabel.textContent=n.label; passLabel.style.color=n.color; }
});
</script>
</body>
</html>