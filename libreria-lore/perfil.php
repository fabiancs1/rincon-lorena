<?php
/* =============================================
   FYLCAD — Mi Perfil (v2)
============================================= */
session_start();
require_once 'config/db.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php'); exit;
}

$usuarioId   = $_SESSION['usuario_id'];
$usuarioPlan = $_SESSION['usuario_plan'] ?? 'free';
$db          = getDB();

$stmt = $db->prepare("SELECT * FROM usuarios WHERE id = ?");
$stmt->execute([$usuarioId]);
$usuario = $stmt->fetch();

$errores = [];
$exitos  = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {

    if ($_POST['accion'] === 'nombre') {
        $nombre = trim($_POST['nombre'] ?? '');
        if (strlen($nombre) < 2) {
            $errores[] = "El nombre debe tener al menos 2 caracteres.";
        } else {
            $db->prepare("UPDATE usuarios SET nombre = ? WHERE id = ?")->execute([$nombre, $usuarioId]);
            $_SESSION['usuario_nombre'] = $nombre;
            $usuario['nombre']          = $nombre;
            $exitos[] = "Nombre actualizado correctamente.";
        }
    }

    if ($_POST['accion'] === 'password') {
        $actual    = $_POST['password_actual']   ?? '';
        $nueva     = $_POST['password_nueva']    ?? '';
        $confirmar = $_POST['password_confirmar']?? '';

        if (!password_verify($actual, $usuario['password'])) {
            $errores[] = "La contraseña actual no es correcta.";
        } elseif (strlen($nueva) < 8) {
            $errores[] = "La nueva contraseña debe tener al menos 8 caracteres.";
        } elseif ($nueva !== $confirmar) {
            $errores[] = "Las contraseñas nuevas no coinciden.";
        } else {
            $hash = password_hash($nueva, PASSWORD_BCRYPT);
            $db->prepare("UPDATE usuarios SET password = ? WHERE id = ?")->execute([$hash, $usuarioId]);
            $exitos[] = "Contraseña actualizada correctamente.";
        }
    }
}

// Subir foto de perfil
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'foto') {
    if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
        $file     = $_FILES['foto'];
        $maxSize  = 3 * 1024 * 1024; // 3MB
        $allowed  = ['image/jpeg','image/png','image/webp','image/gif'];
        $ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $extMap   = ['jpg'=>'jpeg','jpeg'=>'jpeg','png'=>'png','webp'=>'webp','gif'=>'gif'];

        if ($file['size'] > $maxSize) {
            $errores[] = "La imagen no puede superar 3MB.";
        } elseif (!in_array($file['type'], $allowed)) {
            $errores[] = "Formato no permitido. Usa JPG, PNG, WEBP o GIF.";
        } else {
            $uploadDir = __DIR__ . '/uploads/avatares/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

            // Borrar foto anterior si existe
            if (!empty($usuario['foto_perfil'])) {
                $oldFile = $uploadDir . basename($usuario['foto_perfil']);
                if (file_exists($oldFile)) unlink($oldFile);
            }

            $newName  = 'avatar_' . $usuarioId . '_' . time() . '.' . ($extMap[$ext] ?? $ext);
            $destPath = $uploadDir . $newName;

            if (move_uploaded_file($file['tmp_name'], $destPath)) {
                $fotoUrl = 'uploads/avatares/' . $newName;
                $db->prepare("UPDATE usuarios SET foto_perfil = ? WHERE id = ?")->execute([$fotoUrl, $usuarioId]);
                $usuario['foto_perfil'] = $fotoUrl;
                $exitos[] = "Foto de perfil actualizada.";
            } else {
                $errores[] = "No se pudo guardar la imagen. Verifica permisos de la carpeta uploads/.";
            }
        }
    } else {
        $errores[] = "No se recibió ninguna imagen.";
    }
}

$statsStmt = $db->prepare("SELECT COUNT(*) AS proyectos, COALESCE(SUM(total_puntos),0) AS puntos FROM proyectos WHERE usuario_id = ?");
$statsStmt->execute([$usuarioId]);
$stats = $statsStmt->fetch();
$diasRegistrado = (int)((time() - strtotime($usuario['creado_en'])) / 86400);
$iniciales = strtoupper(substr($usuario['nombre'], 0, 2));
$primerNombre = htmlspecialchars(explode(' ', $usuario['nombre'])[0]);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>FYLCAD — Mi Perfil</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Sans:ital,wght@0,300;0,400;0,500&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
    /* ── Reset & vars ── */
    :root {
        --bg:      #05080f;
        --surface: #0c1120;
        --surface2:#0a0f1c;
        --border:  rgba(255,255,255,0.07);
        --border-h:rgba(255,255,255,0.13);
        --accent:  #00e5c0;
        --accent2: #3b82f6;
        --text:    #e8edf5;
        --muted:   #64748b;
        --red:     #ef4444;
    }
    *, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }
    html, body { min-height:100%; }
    body {
        background: var(--bg);
        color: var(--text);
        font-family: "DM Sans", sans-serif;
        font-size: 14px;
    }

    /* ── Topbar ── */
    .topbar {
        position: sticky; top: 0; z-index: 100;
        height: 60px;
        background: rgba(5,8,15,0.85);
        border-bottom: 1px solid var(--border);
        backdrop-filter: blur(16px);
        display: flex; align-items: center;
        padding: 0 clamp(16px,3vw,40px);
        gap: 32px;
    }
    .topbar-logo {
        font-family: "Syne", sans-serif;
        font-weight: 800; font-size: 18px;
        letter-spacing: 3px; color: var(--accent);
        text-decoration: none; white-space: nowrap;
    }
    .topbar-logo span { color: var(--text); opacity:.4; }
    .topbar-nav { display:flex; align-items:center; gap:4px; flex:1; }
    .tnav-item {
        padding: 6px 14px; border-radius: 7px;
        font-size: 13px; font-weight: 500;
        color: var(--muted); text-decoration: none;
        transition: background .2s, color .2s;
    }
    .tnav-item:hover { background:rgba(255,255,255,.04); color:var(--text); }
    .tnav-item.active {
        background: rgba(0,229,192,.08);
        border: 1px solid rgba(0,229,192,.15);
        color: var(--accent);
    }
    .topbar-right { display:flex; align-items:center; gap:12px; flex-shrink:0; }
    .topbar-plan {
        font-size:11px; font-weight:600;
        padding:4px 10px; border-radius:6px; letter-spacing:.5px;
    }
    .topbar-plan.free { background:rgba(100,116,139,.1); border:1px solid rgba(100,116,139,.2); color:var(--muted); }
    .topbar-plan.premium { background:rgba(0,229,192,.1); border:1px solid rgba(0,229,192,.25); color:var(--accent); }
    .topbar-user { position:relative; cursor:pointer; }
    .topbar-avatar {
        width:36px; height:36px; border-radius:10px;
        background:linear-gradient(135deg,var(--accent),var(--accent2));
        display:flex; align-items:center; justify-content:center;
        font-family:"Syne",sans-serif; font-weight:800; font-size:12px;
        color:#020617; user-select:none; transition:box-shadow .2s;
        overflow: hidden;
    }
    .topbar-avatar img {
        width:100%; height:100%; object-fit:cover; display:block;
    }
    .topbar-user.open .topbar-avatar { box-shadow:0 0 0 2px rgba(0,229,192,.4); }
    .topbar-dropdown {
        display:none; position:absolute; top:calc(100% + 10px); right:0;
        min-width:200px; background:var(--surface);
        border:1px solid var(--border-h); border-radius:12px;
        padding:8px; box-shadow:0 20px 40px rgba(0,0,0,.5);
    }
    .topbar-user.open .topbar-dropdown { display:block; }
    .td-name { font-size:13px; font-weight:600; color:var(--text); padding:4px 8px 2px; }
    .td-email { font-size:11px; color:var(--muted); padding:0 8px 8px; }
    .td-divider { height:1px; background:var(--border); margin:4px 0; }
    .td-link {
        display:block; padding:8px; border-radius:7px;
        font-size:13px; color:var(--muted); text-decoration:none;
        transition:background .15s, color .15s;
    }
    .td-link:hover { background:rgba(255,255,255,.04); color:var(--text); }
    .td-link.danger:hover { color:var(--red); }

    /* ── Wrap ── */
    .page-wrap {
        max-width: 1100px;
        margin: 0 auto;
        padding: clamp(20px,3vw,40px) clamp(16px,3vw,40px);
        display: flex;
        flex-direction: column;
        gap: 16px;
    }

    /* ── HERO card ── */
    .hero-card {
        background: linear-gradient(135deg,rgba(0,229,192,.07),rgba(59,130,246,.06));
        border: 1px solid rgba(0,229,192,.12);
        border-radius: 20px;
        padding: 32px 36px;
        display: grid;
        grid-template-columns: auto 1fr auto;
        align-items: center;
        gap: 24px;
        position: relative;
        overflow: hidden;
    }
    .hero-glow {
        position:absolute; top:-80px; right:-80px;
        width:280px; height:280px;
        background:radial-gradient(circle,rgba(0,229,192,.1) 0%,transparent 70%);
        pointer-events:none;
    }
    .avatar-wrap {
        position: relative;
        flex-shrink: 0;
        width: 80px;
        height: 80px;
    }
    .hero-avatar {
        width: 80px; height: 80px; border-radius: 20px;
        background: linear-gradient(135deg,var(--accent),var(--accent2));
        display: flex; align-items: center; justify-content: center;
        font-family: "Syne", sans-serif; font-weight: 800;
        font-size: 30px; color: #020617;
        overflow: hidden;
        box-shadow: 0 8px 24px rgba(0,229,192,.2);
        transition: box-shadow .2s;
    }
    .hero-avatar img {
        width: 100%; height: 100%;
        object-fit: cover;
        display: block;
    }
    .avatar-edit-btn {
        position: absolute;
        bottom: -6px; right: -6px;
        width: 26px; height: 26px;
        background: var(--surface);
        border: 2px solid var(--bg);
        border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        font-size: 12px;
        cursor: pointer;
        transition: background .2s, transform .2s;
        line-height: 1;
    }
    .avatar-edit-btn:hover {
        background: var(--accent);
        transform: scale(1.1);
    }
    .avatar-wrap:hover .hero-avatar {
        box-shadow: 0 8px 28px rgba(0,229,192,.35);
    }
    .hero-info { min-width:0; }
    .hero-label {
        font-size:10px; font-weight:600; letter-spacing:2.5px;
        text-transform:uppercase; color:var(--accent); margin-bottom:6px;
    }
    .hero-name {
        font-family:"Syne",sans-serif; font-size:clamp(20px,2.5vw,28px);
        font-weight:800; color:#fff; letter-spacing:-.5px; margin-bottom:5px;
    }
    .hero-email { font-size:13px; color:var(--muted); }
    .hero-stats {
        display: flex; gap: 28px;
        border-left: 1px solid var(--border);
        padding-left: 28px;
        flex-shrink: 0;
    }
    .hstat { text-align:center; }
    .hstat-val {
        display:block;
        font-family:"Syne",sans-serif; font-size:26px;
        font-weight:800; color:#fff; line-height:1;
        margin-bottom:4px;
    }
    .hstat-lbl { font-size:11px; color:var(--muted); }

    /* ── Alertas ── */
    .alert {
        padding:13px 18px; border-radius:10px;
        font-size:13px; font-weight:500;
    }
    .alert-ok  { background:rgba(0,229,192,.08); border:1px solid rgba(0,229,192,.2); color:var(--accent); }
    .alert-err { background:rgba(239,68,68,.08); border:1px solid rgba(239,68,68,.2); color:#fca5a5; }

    /* ── Plan card ── */
    .plan-card {
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: 16px;
        padding: 22px 28px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 20px;
    }
    .plan-card-left { }
    .plan-card-label {
        font-size:10px; font-weight:600; letter-spacing:2px;
        text-transform:uppercase; color:var(--muted); margin-bottom:6px;
    }
    .plan-card-name {
        font-family:"Syne",sans-serif; font-size:18px;
        font-weight:800; color:#fff; margin-bottom:4px;
    }
    .plan-card-desc { font-size:13px; color:var(--muted); font-weight:300; }
    .plan-badge-pill {
        padding:8px 20px; border-radius:100px;
        font-size:13px; font-weight:700; white-space:nowrap;
    }
    .plan-badge-pill.free { background:rgba(100,116,139,.1); border:1px solid rgba(100,116,139,.2); color:var(--muted); }
    .plan-badge-pill.premium { background:rgba(0,229,192,.1); border:1px solid rgba(0,229,192,.3); color:var(--accent); }
    .plan-card-actions { display:flex; align-items:center; gap:12px; flex-shrink:0; }
    .btn-upgrade-sm {
        display:inline-flex; align-items:center; gap:6px;
        background:var(--accent); color:#020617;
        font-size:13px; font-weight:700;
        padding:10px 20px; border-radius:9px;
        text-decoration:none; transition:all .2s; white-space:nowrap;
    }
    .btn-upgrade-sm:hover { background:#00ffda; box-shadow:0 0 16px rgba(0,229,192,.3); transform:translateY(-1px); }

    /* ── Main grid ── */
    .main-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 16px;
    }

    /* ── Panel ── */
    .panel {
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: 16px;
        overflow: hidden;
        transition: border-color .25s;
    }
    .panel:hover { border-color: var(--border-h); }
    .panel-header {
        padding: 18px 24px;
        border-bottom: 1px solid var(--border);
        display: flex; align-items: center; gap: 10px;
    }
    .panel-icon {
        width:32px; height:32px; border-radius:8px;
        background:rgba(0,229,192,.08); border:1px solid rgba(0,229,192,.15);
        display:flex; align-items:center; justify-content:center; font-size:15px;
    }
    .panel-header h3 {
        font-family:"Syne",sans-serif; font-size:14px;
        font-weight:700; color:#fff;
    }
    .panel-body { padding: 24px; }

    /* ── Form fields ── */
    .form-field { display:flex; flex-direction:column; gap:7px; margin-bottom:16px; }
    .form-field:last-of-type { margin-bottom:20px; }
    .form-field label {
        font-size:11px; color:var(--muted);
        text-transform:uppercase; letter-spacing:.8px; font-weight:500;
    }
    .form-field input {
        background: var(--surface2);
        border: 1px solid var(--border);
        border-radius: 10px; padding: 11px 14px;
        font-size: 14px; color: var(--text);
        font-family: "DM Sans", sans-serif;
        outline: none; transition: border-color .2s, box-shadow .2s;
    }
    .form-field input:focus {
        border-color: rgba(0,229,192,.4);
        box-shadow: 0 0 0 3px rgba(0,229,192,.06);
    }
    .form-field input:disabled { opacity:.4; cursor:not-allowed; }
    .form-field small { font-size:11px; color:var(--muted); }

    .btn-save {
        display:inline-flex; align-items:center; gap:8px;
        background:var(--accent); color:#020617;
        border:none; border-radius:10px; padding:11px 24px;
        font-size:13px; font-weight:700;
        font-family:"DM Sans",sans-serif; cursor:pointer;
        transition:all .2s;
    }
    .btn-save:hover { background:#00ffda; box-shadow:0 0 16px rgba(0,229,192,.3); transform:translateY(-1px); }

    /* ── Danger zone ── */
    .danger-panel {
        background: rgba(239,68,68,.04);
        border: 1px solid rgba(239,68,68,.12);
        border-radius: 16px;
        padding: 22px 24px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 20px;
    }
    .danger-left h3 {
        font-family:"Syne",sans-serif; font-size:14px;
        color:var(--red); font-weight:700; margin-bottom:5px;
    }
    .danger-left p { font-size:13px; color:var(--muted); font-weight:300; line-height:1.6; }
    .btn-danger {
        background:transparent; border:1px solid rgba(239,68,68,.35);
        color:var(--red); border-radius:9px; padding:10px 20px;
        font-size:13px; font-weight:600; cursor:pointer;
        font-family:"DM Sans",sans-serif; transition:all .2s;
        white-space:nowrap; flex-shrink:0;
    }
    .btn-danger:hover { background:rgba(239,68,68,.1); border-color:rgba(239,68,68,.6); }

    /* ── Responsive ── */
    @media (max-width:860px) {
        .topbar-nav { display:none; }
        .hero-card { grid-template-columns:auto 1fr; }
        .hero-stats { display:none; }
        .main-grid { grid-template-columns:1fr; }
        .danger-panel { flex-direction:column; align-items:flex-start; }
    }
    </style>
</head>
<body>

<!-- TOPBAR -->
<header class="topbar">
    <a href="index.php" class="topbar-logo">FYL<span>CAD</span></a>
    <nav class="topbar-nav">
        <a href="dashboard.php"     class="tnav-item">Panel</a>
        <a href="proyecto.php"      class="tnav-item">Módulo 3D</a>
        <a href="mis_proyectos.php" class="tnav-item">Proyectos</a>
        <a href="perfil.php"        class="tnav-item active">Perfil</a>
        <a href="planes.php"        class="tnav-item">Planes</a>
    </nav>
    <div class="topbar-right">
        <span class="topbar-plan <?= $usuarioPlan ?>">
            <?= $usuarioPlan === 'premium' ? '★ Premium' : '◈ Free' ?>
        </span>
        <div class="topbar-user">
            <div class="topbar-avatar">
                <?php if (!empty($usuario['foto_perfil']) && file_exists($usuario['foto_perfil'])): ?>
                    <img src="<?= htmlspecialchars($usuario['foto_perfil']) ?>?v=<?= time() ?>" alt="">
                <?php else: ?>
                    <?= $iniciales ?>
                <?php endif; ?>
            </div>
            <div class="topbar-dropdown">
                <div class="td-name"><?= htmlspecialchars($usuario['nombre']) ?></div>
                <div class="td-email"><?= htmlspecialchars($usuario['email']) ?></div>
                <div class="td-divider"></div>
                <a href="perfil.php" class="td-link">Mi perfil</a>
                <a href="dashboard.php?logout=1" class="td-link danger">Cerrar sesión</a>
            </div>
        </div>
    </div>
</header>

<div class="page-wrap">

    <!-- HERO -->
    <div class="hero-card">
        <div class="hero-glow"></div>
        <!-- Avatar con foto o iniciales -->
        <div class="avatar-wrap">
            <div class="hero-avatar">
                <?php if (!empty($usuario['foto_perfil']) && file_exists($usuario['foto_perfil'])): ?>
                    <img src="<?= htmlspecialchars($usuario['foto_perfil']) ?>?v=<?= time() ?>"
                         alt="Foto de perfil">
                <?php else: ?>
                    <?= $iniciales ?>
                <?php endif; ?>
            </div>
            <label class="avatar-edit-btn" for="fotoInput" title="Cambiar foto">
                📷
            </label>
            <form method="POST" enctype="multipart/form-data" id="fotoForm">
                <input type="hidden" name="accion" value="foto">
                <input type="file" id="fotoInput" name="foto"
                       accept="image/jpeg,image/png,image/webp,image/gif"
                       style="display:none;"
                       onchange="document.getElementById('fotoForm').submit()">
            </form>
        </div>
        <div class="hero-info">
            <div class="hero-label">Mi Perfil</div>
            <div class="hero-name"><?= htmlspecialchars($usuario['nombre']) ?></div>
            <div class="hero-email">
                <?= htmlspecialchars($usuario['email']) ?>
                · Miembro desde <?= date('F Y', strtotime($usuario['creado_en'])) ?>
            </div>
        </div>
        <div class="hero-stats">
            <div class="hstat">
                <span class="hstat-val"><?= $stats['proyectos'] ?></span>
                <span class="hstat-lbl">Proyectos</span>
            </div>
            <div class="hstat">
                <span class="hstat-val"><?= number_format($stats['puntos']) ?></span>
                <span class="hstat-lbl">Puntos</span>
            </div>
            <div class="hstat">
                <span class="hstat-val"><?= $diasRegistrado ?></span>
                <span class="hstat-lbl">Días</span>
            </div>
        </div>
    </div>

    <!-- ALERTAS -->
    <?php foreach ($exitos  as $m): ?>
        <div class="alert alert-ok">✓ <?= htmlspecialchars($m) ?></div>
    <?php endforeach; ?>
    <?php foreach ($errores as $m): ?>
        <div class="alert alert-err">✗ <?= htmlspecialchars($m) ?></div>
    <?php endforeach; ?>

    <!-- PLAN ACTUAL -->
    <div class="plan-card">
        <div class="plan-card-left">
            <div class="plan-card-label">Plan actual</div>
            <div class="plan-card-name"><?= $usuarioPlan === 'premium' ? '★ Plan Premium' : '◈ Plan Free' ?></div>
            <div class="plan-card-desc">
                <?= $usuarioPlan === 'premium'
                    ? 'Tienes acceso completo a todas las funciones de FYLCAD.'
                    : 'Actualiza a Premium para desbloquear puntos ilimitados y exportar PDF.' ?>
            </div>
        </div>
        <div class="plan-card-actions">
            <span class="plan-badge-pill <?= $usuarioPlan ?>">
                <?= $usuarioPlan === 'premium' ? '★ Premium' : '◈ Free' ?>
            </span>
            <?php if ($usuarioPlan === 'free'): ?>
            <a href="planes.php" class="btn-upgrade-sm">Mejorar plan →</a>
            <?php endif; ?>
        </div>
    </div>

    <!-- FORMULARIOS -->
    <div class="main-grid">

        <!-- Información personal -->
        <div class="panel">
            <div class="panel-header">
                <div class="panel-icon">👤</div>
                <h3>Información personal</h3>
            </div>
            <div class="panel-body">
                <form method="POST">
                    <input type="hidden" name="accion" value="nombre">
                    <div class="form-field">
                        <label>Nombre completo</label>
                        <input type="text" name="nombre"
                               value="<?= htmlspecialchars($usuario['nombre']) ?>"
                               required minlength="2" maxlength="100">
                    </div>
                    <div class="form-field">
                        <label>Correo electrónico</label>
                        <input type="email" value="<?= htmlspecialchars($usuario['email']) ?>" disabled>
                        <small>El email no se puede modificar.</small>
                    </div>
                    <div class="form-field">
                        <label>Miembro desde</label>
                        <input type="text" value="<?= date('d \d\e F \d\e Y', strtotime($usuario['creado_en'])) ?>" disabled>
                    </div>
                    <button type="submit" class="btn-save">Guardar cambios</button>
                </form>
            </div>
        </div>

        <!-- Cambiar contraseña -->
        <div class="panel">
            <div class="panel-header">
                <div class="panel-icon">🔒</div>
                <h3>Cambiar contraseña</h3>
            </div>
            <div class="panel-body">
                <form method="POST">
                    <input type="hidden" name="accion" value="password">
                    <div class="form-field">
                        <label>Contraseña actual</label>
                        <input type="password" name="password_actual" required>
                    </div>
                    <div class="form-field">
                        <label>Nueva contraseña</label>
                        <input type="password" name="password_nueva" required minlength="8" placeholder="Mínimo 8 caracteres">
                    </div>
                    <div class="form-field">
                        <label>Confirmar nueva contraseña</label>
                        <input type="password" name="password_confirmar" required>
                    </div>
                    <button type="submit" class="btn-save">Actualizar contraseña</button>
                </form>
            </div>
        </div>

    </div>

    <!-- ZONA PELIGROSA -->
    <div class="danger-panel">
        <div class="danger-left">
            <h3>⚠ Zona peligrosa</h3>
            <p>Eliminar tu cuenta borrará todos tus proyectos y datos de forma permanente.<br>Esta acción no se puede deshacer.</p>
        </div>
        <button class="btn-danger" onclick="alert('Para eliminar tu cuenta escríbenos a contacto@fylcad.com')">
            Eliminar mi cuenta
        </button>
    </div>

</div>

<script>
    const avatar = document.querySelector('.topbar-user');
    avatar?.addEventListener('click', () => avatar.classList.toggle('open'));
    document.addEventListener('click', e => {
        if (!avatar?.contains(e.target)) avatar?.classList.remove('open');
    });
</script>


<script src="js/fylcad_ai_widget.js" data-pagina="perfil"></script>
</body>
</html>