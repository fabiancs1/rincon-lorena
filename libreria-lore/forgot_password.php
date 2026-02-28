<?php
/* =============================================
   FYLCAD — Recuperar contraseña
   Archivo: forgot_password.php
============================================= */
session_start();
require_once 'config/db.php';

if (isset($_SESSION['usuario_id'])) { header('Location: dashboard.php'); exit; }

// Agregar columna reset_token si no existe (primera vez)
try {
    getDB()->exec("
        ALTER TABLE usuarios
        ADD COLUMN IF NOT EXISTS reset_token     VARCHAR(255) NULL,
        ADD COLUMN IF NOT EXISTS reset_expira    DATETIME     NULL
    ");
} catch (Exception $e) {}

$mensaje = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Ingresa un email válido.";
    } else {
        $db   = getDB();
        $stmt = $db->prepare("SELECT id FROM usuarios WHERE email = ? AND activo = 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        // Siempre mostrar el mismo mensaje (por seguridad)
        $mensaje = "Si ese email está registrado, recibirás las instrucciones en breve.";

        if ($user) {
            $token   = bin2hex(random_bytes(32));
            $expira  = date('Y-m-d H:i:s', strtotime('+1 hour'));

            $db->prepare("UPDATE usuarios SET reset_token = ?, reset_expira = ? WHERE id = ?")
               ->execute([$token, $expira, $user['id']]);

            // En producción aquí enviarías el email.
            // Por ahora mostramos el link para que lo pruebes en local.
            $link = "http://localhost/fylcad/reset_password.php?token={$token}";
            // Para desarrollo — guardar en sesión y mostrar el link
            $_SESSION['dev_reset_link'] = $link;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>FYLCAD — Recuperar contraseña</title>
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
        <ellipse cx="600" cy="400" rx="320" ry="150" fill="none" stroke="#00e5c0" stroke-width="0.6" opacity="0.08"/>
        <ellipse cx="600" cy="400" rx="160" ry="70"  fill="none" stroke="#00e5c0" stroke-width="0.6" opacity="0.08"/>
    </svg>
    <div class="bg-glow"></div>
</div>

<div class="auth-wrapper">
    <a href="index.php" class="auth-logo">FYL<span>CAD</span></a>

    <div class="auth-card">
        <div class="auth-card-header">
            <h1>Recuperar contraseña</h1>
            <p>Te enviaremos un enlace para resetearla.</p>
        </div>

        <?php if ($mensaje): ?>
        <div class="alert alert-success">
            <span class="alert-icon">✓</span>
            <div><?= htmlspecialchars($mensaje) ?></div>
        </div>
        <?php if (isset($_SESSION['dev_reset_link'])): ?>
        <div style="background:rgba(245,158,11,.08);border:1px solid rgba(245,158,11,.2);border-radius:8px;padding:12px;margin-bottom:16px;">
            <p style="font-size:11px;color:#f59e0b;margin-bottom:6px;">⚠ MODO DESARROLLO — en producción esto llegaría por email:</p>
            <a href="<?= htmlspecialchars($_SESSION['dev_reset_link']) ?>"
               style="font-size:11px;color:#00e5c0;word-break:break-all;">
               <?= htmlspecialchars($_SESSION['dev_reset_link']) ?>
            </a>
        </div>
        <?php unset($_SESSION['dev_reset_link']); endif; ?>
        <?php endif; ?>

        <?php if ($error): ?>
        <div class="alert alert-error">
            <span class="alert-icon">!</span>
            <div><?= htmlspecialchars($error) ?></div>
        </div>
        <?php endif; ?>

        <?php if (!$mensaje): ?>
        <form method="POST" class="auth-form">
            <div class="form-group">
                <label for="email">Correo electrónico</label>
                <div class="input-wrap">
                    <span class="input-icon">✉</span>
                    <input type="email" id="email" name="email"
                           placeholder="correo@ejemplo.com" required autocomplete="email">
                </div>
            </div>
            <button type="submit" class="btn-submit">
                <span class="btn-text">Enviar instrucciones</span>
                <span class="btn-arrow">→</span>
            </button>
        </form>
        <?php endif; ?>

        <div class="auth-footer">
            <a href="login.php">← Volver al login</a>
        </div>
    </div>
</div>
</body>
</html>