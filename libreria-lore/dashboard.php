<?php
/* =============================================
   FYLCAD — Dashboard v2 (Bento Layout)
============================================= */
session_start();
require_once 'config/db.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

$usuarioId     = $_SESSION['usuario_id'];
$usuarioNombre = $_SESSION['usuario_nombre'];
$usuarioPlan   = $_SESSION['usuario_plan'];

$db = getDB();

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: login.php');
    exit;
}

$stmt = $db->prepare("SELECT nombre, email, plan, creado_en FROM usuarios WHERE id = ?");
$stmt->execute([$usuarioId]);
$usuario = $stmt->fetch();

$db->prepare("UPDATE usuarios SET ultimo_acceso = NOW() WHERE id = ?")->execute([$usuarioId]);

$stats = $db->prepare("
    SELECT
        COUNT(*)                                  AS total_proyectos,
        COALESCE(SUM(total_puntos), 0)            AS total_puntos,
        COALESCE(SUM(total_triangulos), 0)        AS total_triangulos,
        COUNT(CASE WHEN MONTH(creado_en) = MONTH(NOW())
                    AND YEAR(creado_en)  = YEAR(NOW())
               THEN 1 END)                        AS proyectos_mes
    FROM proyectos WHERE usuario_id = ?
");
$stats->execute([$usuarioId]);
$st = $stats->fetch();

$cot = $db->prepare("SELECT COUNT(*) AS total FROM cotizaciones WHERE usuario_id = ?");
$cot->execute([$usuarioId]);
$totalCotizaciones = $cot->fetch()['total'];

$act = $db->prepare("
    SELECT a.tipo, a.descripcion, a.creado_en, p.nombre AS proyecto_nombre
    FROM actividad a
    LEFT JOIN proyectos p ON a.proyecto_id = p.id
    WHERE a.usuario_id = ?
    ORDER BY a.creado_en DESC
    LIMIT 6
");
$act->execute([$usuarioId]);
$actividades = $act->fetchAll();

$proy = $db->prepare("
    SELECT id, nombre, total_puntos, area_m2, estado, creado_en
    FROM proyectos
    WHERE usuario_id = ?
    ORDER BY creado_en DESC
    LIMIT 4
");
$proy->execute([$usuarioId]);
$proyectos = $proy->fetchAll();

$diasRegistrado = (int)((time() - strtotime($usuario['creado_en'])) / 86400);

function iconoActividad($tipo) {
    $map = [
        'proyecto_creado'      => '◈',
        'proyecto_actualizado' => '✏',
        'cotizacion_generada'  => '💰',
        'archivo_exportado'    => '↓',
        'login'                => '→',
    ];
    return $map[$tipo] ?? '·';
}

$primerNombre = htmlspecialchars(explode(' ', $usuario['nombre'])[0]);
$iniciales    = strtoupper(substr($usuario['nombre'], 0, 2));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>FYLCAD — Panel</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Sans:wght@300;400;500&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
/* =============================================
   FYLCAD — dashboard_v2.css  (Bento Layout)
============================================= */
:root {
    --bg:       #05080f;
    --surface:  #0c1120;
    --surface2: #0a0f1c;
    --border:   rgba(255,255,255,0.07);
    --border-h: rgba(255,255,255,0.13);
    --accent:   #00e5c0;
    --accent2:  #3b82f6;
    --accent3:  #8b5cf6;
    --accent4:  #f59e0b;
    --text:     #e8edf5;
    --muted:    #64748b;
    --topbar-h: 60px;
}

*, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }
html, body { height:100%; }

body {
    background: var(--bg);
    color: var(--text);
    font-family: "DM Sans", sans-serif;
    font-size: 14px;
    min-height: 100vh;
}

/* =============================================
   TOPBAR HORIZONTAL
============================================= */
.topbar {
    position: sticky;
    top: 0;
    z-index: 100;
    height: var(--topbar-h);
    background: rgba(5, 8, 15, 0.85);
    border-bottom: 1px solid var(--border);
    backdrop-filter: blur(16px);
    -webkit-backdrop-filter: blur(16px);
    display: flex;
    align-items: center;
    padding: 0 clamp(16px, 3vw, 40px);
    gap: 32px;
}

.topbar-logo {
    font-family: "Syne", sans-serif;
    font-weight: 800;
    font-size: 18px;
    letter-spacing: 3px;
    color: var(--accent);
    text-decoration: none;
    white-space: nowrap;
}
.topbar-logo span { color: var(--text); opacity: .4; }

.topbar-nav {
    display: flex;
    align-items: center;
    gap: 4px;
    flex: 1;
}

.tnav-item {
    padding: 6px 14px;
    border-radius: 7px;
    font-size: 13px;
    font-weight: 500;
    color: var(--muted);
    text-decoration: none;
    transition: background .2s, color .2s;
}
.tnav-item:hover { background: rgba(255,255,255,.04); color: var(--text); }
.tnav-item.active {
    background: rgba(0,229,192,.08);
    border: 1px solid rgba(0,229,192,.15);
    color: var(--accent);
}

.topbar-right {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-shrink: 0;
}

.topbar-plan {
    font-size: 11px;
    font-weight: 600;
    padding: 4px 10px;
    border-radius: 6px;
    letter-spacing: .5px;
}
.topbar-plan.free {
    background: rgba(100,116,139,.1);
    border: 1px solid rgba(100,116,139,.2);
    color: var(--muted);
}
.topbar-plan.premium {
    background: rgba(0,229,192,.1);
    border: 1px solid rgba(0,229,192,.25);
    color: var(--accent);
}

/* Avatar + dropdown */
.topbar-user { position: relative; cursor: pointer; }

.topbar-avatar {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    background: linear-gradient(135deg, var(--accent), var(--accent2));
    display: flex;
    align-items: center;
    justify-content: center;
    font-family: "Syne", sans-serif;
    font-weight: 800;
    font-size: 12px;
    color: #020617;
    user-select: none;
    transition: box-shadow .2s;
}
.topbar-user.open .topbar-avatar,
.topbar-avatar:hover {
    box-shadow: 0 0 0 2px rgba(0,229,192,.4);
}

.topbar-dropdown {
    display: none;
    position: absolute;
    top: calc(100% + 10px);
    right: 0;
    min-width: 200px;
    background: var(--surface);
    border: 1px solid var(--border-h);
    border-radius: 12px;
    padding: 8px;
    box-shadow: 0 20px 40px rgba(0,0,0,.5);
}
.topbar-user.open .topbar-dropdown { display: block; }

.td-name {
    font-size: 13px;
    font-weight: 600;
    color: var(--text);
    padding: 4px 8px 2px;
}
.td-email {
    font-size: 11px;
    color: var(--muted);
    padding: 0 8px 8px;
}
.td-divider {
    height: 1px;
    background: var(--border);
    margin: 4px 0;
}
.td-link {
    display: block;
    padding: 8px 8px;
    border-radius: 7px;
    font-size: 13px;
    color: var(--muted);
    text-decoration: none;
    transition: background .15s, color .15s;
}
.td-link:hover { background: rgba(255,255,255,.04); color: var(--text); }
.td-link.danger:hover { color: #ef4444; }

/* =============================================
   BENTO WRAP
============================================= */
.bento-wrap {
    max-width: 1320px;
    margin: 0 auto;
    padding: clamp(16px, 2.5vw, 32px) clamp(16px, 3vw, 40px);
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.bento-row {
    display: grid;
    gap: 16px;
}

/* =============================================
   ROW 1 — saludo ancho + 4 stats
============================================= */
.row-1 {
    grid-template-columns: 2fr 1fr 1fr 1fr 1fr;
}

/* =============================================
   ROW 2 — acciones + proyectos + actividad
============================================= */
.row-2 {
    grid-template-columns: 1.1fr 1.4fr 1fr;
}

/* =============================================
   ROW 3 — cuenta + upgrade + mapa deco
============================================= */
.row-3 {
    grid-template-columns: 1fr 1fr 1.2fr;
}

/* =============================================
   BENTO CARD BASE
============================================= */
.bento-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 18px;
    padding: 24px;
    position: relative;
    overflow: hidden;
    transition: border-color .25s, transform .25s;
}
.bento-card:hover {
    border-color: var(--border-h);
}

/* =============================================
   CARD: WELCOME
============================================= */
.card-welcome {
    background: linear-gradient(135deg, rgba(0,229,192,.07) 0%, rgba(59,130,246,.05) 100%);
    border-color: rgba(0,229,192,.12);
    display: flex;
    flex-direction: column;
    justify-content: flex-end;
    min-height: 180px;
}

.welcome-glow {
    position: absolute;
    top: -60px;
    right: -60px;
    width: 220px;
    height: 220px;
    background: radial-gradient(circle, rgba(0,229,192,.12) 0%, transparent 70%);
    pointer-events: none;
}

.welcome-label {
    font-size: 10px;
    font-weight: 600;
    letter-spacing: 2.5px;
    text-transform: uppercase;
    color: var(--accent);
    margin-bottom: 8px;
}

.welcome-name {
    font-family: "Syne", sans-serif;
    font-size: clamp(22px, 2.5vw, 30px);
    font-weight: 800;
    color: #fff;
    letter-spacing: -.5px;
    margin-bottom: 6px;
}

.welcome-sub {
    font-size: 13px;
    color: var(--muted);
    font-weight: 300;
    margin-bottom: 20px;
}

.welcome-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: var(--accent);
    color: #020617;
    font-size: 13px;
    font-weight: 700;
    padding: 10px 20px;
    border-radius: 9px;
    text-decoration: none;
    transition: all .2s;
    width: fit-content;
}
.welcome-btn:hover {
    background: #00ffda;
    box-shadow: 0 0 20px rgba(0,229,192,.35);
    transform: translateY(-1px);
}

.welcome-date {
    position: absolute;
    top: 24px;
    right: 24px;
    font-size: 11px;
    color: var(--muted);
    text-transform: capitalize;
    text-align: right;
}

/* =============================================
   CARD: STAT
============================================= */
.card-stat {
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    min-height: 180px;
}

.stat-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.stat-ico {
    width: 38px;
    height: 38px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 17px;
}

.card-stat.teal   .stat-ico { background: rgba(0,229,192,.1);  border: 1px solid rgba(0,229,192,.2); }
.card-stat.blue   .stat-ico { background: rgba(59,130,246,.1); border: 1px solid rgba(59,130,246,.2); }
.card-stat.purple .stat-ico { background: rgba(139,92,246,.1); border: 1px solid rgba(139,92,246,.2); }
.card-stat.orange .stat-ico { background: rgba(245,158,11,.1); border: 1px solid rgba(245,158,11,.2); }

.stat-badge {
    font-size: 10px;
    color: var(--muted);
    background: var(--surface2);
    border: 1px solid var(--border);
    padding: 2px 7px;
    border-radius: 4px;
    letter-spacing: .3px;
}

.stat-num {
    font-family: "Syne", sans-serif;
    font-size: clamp(28px, 3vw, 38px);
    font-weight: 800;
    color: #fff;
    line-height: 1;
}

.stat-lbl {
    font-size: 12px;
    color: var(--muted);
    line-height: 1.5;
}

/* Accent line top on hover */
.card-stat::before {
    content: "";
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 2px;
    border-radius: 18px 18px 0 0;
    opacity: 0;
    transition: opacity .3s;
}
.card-stat.teal::before   { background: var(--accent); }
.card-stat.blue::before   { background: var(--accent2); }
.card-stat.purple::before { background: var(--accent3); }
.card-stat.orange::before { background: var(--accent4); }
.card-stat:hover::before  { opacity: 1; }

/* =============================================
   CARD: ACTIONS
============================================= */
.card-actions {}

.card-title {
    font-family: "Syne", sans-serif;
    font-size: 13px;
    font-weight: 700;
    color: #fff;
    margin-bottom: 16px;
}

.card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 16px;
}
.card-link {
    font-size: 12px;
    color: var(--accent);
    text-decoration: none;
    transition: opacity .2s;
}
.card-link:hover { opacity: .7; }

.card-tag {
    font-size: 10px;
    color: var(--muted);
    background: var(--surface2);
    border: 1px solid var(--border);
    padding: 3px 8px;
    border-radius: 4px;
    letter-spacing: .5px;
    text-transform: uppercase;
}

.actions-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
}

.action-tile {
    background: rgba(255,255,255,.03);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 16px;
    text-decoration: none;
    display: flex;
    flex-direction: column;
    gap: 4px;
    position: relative;
    transition: background .2s, border-color .2s, transform .2s;
    cursor: pointer;
}
.action-tile:hover:not(.locked) {
    background: rgba(0,229,192,.05);
    border-color: rgba(0,229,192,.2);
    transform: translateY(-2px);
}
.action-tile.locked { opacity: .45; cursor: not-allowed; }

.action-ico { font-size: 20px; margin-bottom: 4px; }
.action-lbl { font-size: 12px; font-weight: 600; color: var(--text); }
.action-desc { font-size: 11px; color: var(--muted); line-height: 1.4; }
.action-arr {
    position: absolute;
    bottom: 12px;
    right: 14px;
    font-size: 14px;
    color: var(--muted);
    transition: transform .2s, color .2s;
}
.action-tile:hover:not(.locked) .action-arr {
    transform: translateX(3px);
    color: var(--accent);
}

/* =============================================
   CARD: PROJECTS
============================================= */
.card-projects {}

.proj-list { display: flex; flex-direction: column; gap: 2px; }

.proj-row {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 0;
    border-bottom: 1px solid var(--border);
}
.proj-row:last-child { border-bottom: none; }

.proj-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: var(--accent);
    flex-shrink: 0;
    opacity: .6;
}

.proj-info { flex: 1; min-width: 0; }
.proj-name {
    font-size: 13px;
    font-weight: 600;
    color: var(--text);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    margin-bottom: 2px;
}
.proj-meta { font-size: 11px; color: var(--muted); }

.proj-btn {
    font-size: 11px;
    color: var(--accent);
    background: rgba(0,229,192,.06);
    border: 1px solid rgba(0,229,192,.15);
    padding: 3px 9px;
    border-radius: 5px;
    text-decoration: none;
    white-space: nowrap;
    transition: background .2s;
    flex-shrink: 0;
}
.proj-btn:hover { background: rgba(0,229,192,.12); }

/* =============================================
   CARD: ACTIVITY
============================================= */
.act-list { display: flex; flex-direction: column; gap: 4px; }

.act-row {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 0;
    border-bottom: 1px solid var(--border);
}
.act-row:last-child { border-bottom: none; }

.act-ico-wrap {
    width: 30px;
    height: 30px;
    border-radius: 8px;
    background: rgba(0,229,192,.07);
    border: 1px solid rgba(0,229,192,.13);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 13px;
    flex-shrink: 0;
}

.act-desc {
    font-size: 12px;
    color: var(--text);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    margin-bottom: 2px;
}
.act-time { font-size: 10px; color: var(--muted); }

/* =============================================
   CARD: ACCOUNT
============================================= */
.account-rows { display: flex; flex-direction: column; gap: 0; }

.acc-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 11px 0;
    border-bottom: 1px solid var(--border);
}
.acc-row:last-child { border-bottom: none; }

.acc-lbl {
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: .7px;
    color: var(--muted);
    font-weight: 500;
}
.acc-val { font-size: 13px; color: var(--text); font-weight: 500; }
.plan-free    { color: var(--muted); }
.plan-premium { color: var(--accent); }

/* =============================================
   CARD: UPGRADE
============================================= */
.card-upgrade {
    background: linear-gradient(135deg, rgba(139,92,246,.1), rgba(59,130,246,.08));
    border-color: rgba(139,92,246,.2);
    display: flex;
    flex-direction: column;
    justify-content: center;
    text-align: center;
    align-items: center;
    gap: 12px;
}

.card-upgrade.premium-active {
    background: linear-gradient(135deg, rgba(0,229,192,.08), rgba(59,130,246,.06));
    border-color: rgba(0,229,192,.2);
}

.upgrade-glow {
    position: absolute;
    inset: 0;
    background: radial-gradient(circle at 50% 0%, rgba(139,92,246,.15), transparent 60%);
    pointer-events: none;
}
.upgrade-glow.teal {
    background: radial-gradient(circle at 50% 0%, rgba(0,229,192,.12), transparent 60%);
}

.upgrade-star {
    font-size: 32px;
    line-height: 1;
}

.card-upgrade h3 {
    font-family: "Syne", sans-serif;
    font-size: 16px;
    font-weight: 800;
    color: #fff;
}

.card-upgrade p {
    font-size: 12px;
    color: var(--muted);
    line-height: 1.6;
    max-width: 220px;
}

.upgrade-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: var(--accent);
    color: #020617;
    text-decoration: none;
    font-size: 13px;
    font-weight: 700;
    padding: 10px 22px;
    border-radius: 9px;
    transition: all .2s;
}
.upgrade-btn:hover {
    background: #00ffda;
    box-shadow: 0 0 20px rgba(0,229,192,.3);
    transform: translateY(-1px);
}

/* =============================================
   CARD: TOPO DECO
============================================= */
.card-topo {
    background: var(--bg);
    border-color: rgba(0,229,192,.08);
    padding: 20px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
}

.topo-label {
    font-size: 10px;
    font-weight: 600;
    letter-spacing: 2px;
    text-transform: uppercase;
    color: rgba(0,229,192,.5);
}

.topo-svg {
    width: 100%;
    height: auto;
    opacity: .7;
}

.topo-version {
    font-family: "DM Mono", monospace;
    font-size: 10px;
    color: rgba(0,229,192,.3);
    text-align: right;
}

/* =============================================
   EMPTY STATE
============================================= */
.empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 32px 20px;
    text-align: center;
    gap: 12px;
}
.empty-ico {
    font-size: 30px;
    color: var(--muted);
    opacity: .4;
    animation: pulse 3s ease-in-out infinite;
}
@keyframes pulse {
    0%,100% { opacity: .2; }
    50%      { opacity: .5; }
}
.empty-state p { font-size: 13px; color: var(--muted); line-height: 1.8; }
.empty-state a { color: var(--accent); text-decoration: none; font-weight: 500; }

/* =============================================
   RESPONSIVE
============================================= */
@media (max-width: 1200px) {
    .row-1 { grid-template-columns: 2fr 1fr 1fr; }
    .row-1 .card-stat:nth-child(4),
    .row-1 .card-stat:nth-child(5) { display: none; }
    .row-2 { grid-template-columns: 1fr 1fr; }
    .row-2 .card-activity { display: none; }
    .row-3 { grid-template-columns: 1fr 1fr; }
    .row-3 .card-topo { display: none; }
}

@media (max-width: 800px) {
    .topbar-nav { display: none; }
    .row-1 { grid-template-columns: 1fr 1fr; }
    .row-1 .card-welcome { grid-column: span 2; }
    .row-1 .card-stat:nth-child(4),
    .row-1 .card-stat:nth-child(5) { display: flex; }
    .row-2 { grid-template-columns: 1fr; }
    .row-3 { grid-template-columns: 1fr; }
}

@media (max-width: 480px) {
    .row-1 { grid-template-columns: 1fr 1fr; }
    .row-1 .card-stat:nth-child(5) { display: none; }
    .bento-wrap { padding: 12px; gap: 12px; }
    .bento-row { gap: 12px; }
}

    </style>
</head>
<body>

<!-- ==================== TOPBAR ==================== -->
<header class="topbar">
    <a href="index.php" class="topbar-logo">FYL<span>CAD</span></a>

    <nav class="topbar-nav">
        <a href="dashboard.php" class="tnav-item active">Panel</a>
        <a href="proyecto.php"     class="tnav-item">Módulo 3D</a>
        <a href="mis_proyectos.php" class="tnav-item">Proyectos</a>
        <a href="perfil.php"       class="tnav-item">Perfil</a>
        <a href="planes.php"       class="tnav-item">Planes</a>
    </nav>

    <div class="topbar-right">
        <span class="topbar-plan <?= $usuario['plan'] ?>">
            <?= $usuario['plan'] === 'premium' ? '★ Premium' : '◈ Free' ?>
        </span>
        <div class="topbar-user">
            <div class="topbar-avatar"><?= $iniciales ?></div>
            <div class="topbar-dropdown">
                <div class="td-name"><?= htmlspecialchars($usuario['nombre']) ?></div>
                <div class="td-email"><?= htmlspecialchars($usuario['email']) ?></div>
                <div class="td-divider"></div>
                <a href="perfil.php" class="td-link">Mi perfil</a>
                <a href="?logout=1" class="td-link danger">Cerrar sesión</a>
            </div>
        </div>
    </div>
</header>

<!-- ==================== BENTO GRID ==================== -->
<main class="bento-wrap">

    <!-- ── ROW 1: Saludo + Stats grandes ── -->
    <div class="bento-row row-1">

        <!-- Card saludo -->
        <div class="bento-card card-welcome">
            <div class="welcome-glow"></div>
            <div class="welcome-label">Panel de control</div>
            <h1 class="welcome-name">Hola, <?= $primerNombre ?>.</h1>
            <p class="welcome-sub">
                <?= $st['total_proyectos'] > 0
                    ? "Tienes {$st['total_proyectos']} proyecto" . ($st['total_proyectos'] > 1 ? 's' : '') . " procesado" . ($st['total_proyectos'] > 1 ? 's' : '') . "."
                    : "Aún no tienes proyectos. Empieza hoy." ?>
            </p>
            <a href="proyecto.php" class="welcome-btn">
                Abrir Módulo 3D <span>→</span>
            </a>
            <div class="welcome-date" id="welcomeDate"></div>
        </div>

        <!-- Stat: Proyectos -->
        <div class="bento-card card-stat blue">
            <div class="stat-top">
                <div class="stat-ico">◈</div>
                <span class="stat-badge">+<?= $st['proyectos_mes'] ?> mes</span>
            </div>
            <div class="stat-num"><?= number_format($st['total_proyectos']) ?></div>
            <div class="stat-lbl">Proyectos<br>procesados</div>
        </div>

        <!-- Stat: Puntos -->
        <div class="bento-card card-stat teal">
            <div class="stat-top">
                <div class="stat-ico">📐</div>
                <span class="stat-badge"><?= number_format($st['total_triangulos']) ?> tri</span>
            </div>
            <div class="stat-num"><?= number_format($st['total_puntos']) ?></div>
            <div class="stat-lbl">Puntos<br>procesados</div>
        </div>

        <!-- Stat: Cotizaciones -->
        <div class="bento-card card-stat purple">
            <div class="stat-top">
                <div class="stat-ico">💰</div>
                <span class="stat-badge">total</span>
            </div>
            <div class="stat-num"><?= $totalCotizaciones ?></div>
            <div class="stat-lbl">Cotizaciones<br>generadas</div>
        </div>

        <!-- Stat: Días -->
        <div class="bento-card card-stat orange">
            <div class="stat-top">
                <div class="stat-ico">📅</div>
            </div>
            <div class="stat-num"><?= $diasRegistrado ?></div>
            <div class="stat-lbl">Días en<br>FYLCAD</div>
        </div>

    </div>

    <!-- ── ROW 2: Accesos rápidos + Proyectos + Actividad ── -->
    <div class="bento-row row-2">

        <!-- Acciones rápidas — 2x2 -->
        <div class="bento-card card-actions">
            <div class="card-title">Acceso rápido</div>
            <div class="actions-grid">
                <a href="proyecto.php" class="action-tile">
                    <div class="action-ico">◈</div>
                    <div class="action-lbl">Módulo 3D</div>
                    <div class="action-desc">Procesar coordenadas</div>
                    <span class="action-arr">→</span>
                </a>
                <a href="mis_proyectos.php" class="action-tile">
                    <div class="action-ico">📁</div>
                    <div class="action-lbl">Proyectos</div>
                    <div class="action-desc">Ver todos tus proyectos</div>
                    <span class="action-arr">→</span>
                </a>
                <a href="mis_proyectos.php" class="action-tile">
                    <div class="action-ico">💰</div>
                    <div class="action-lbl">Cotización</div>
                    <div class="action-desc">Generar presupuesto</div>
                    <span class="action-arr">→</span>
                </a>
                <a href="planes.php" class="action-tile <?= $usuario['plan'] === 'free' ? 'locked' : '' ?>">
                    <div class="action-ico">📄</div>
                    <div class="action-lbl">Exportar PDF</div>
                    <div class="action-desc"><?= $usuario['plan'] === 'free' ? '🔒 Solo Premium' : 'Descargar plano' ?></div>
                    <span class="action-arr">→</span>
                </a>
            </div>
        </div>

        <!-- Proyectos recientes -->
        <div class="bento-card card-projects">
            <div class="card-header">
                <div class="card-title">Proyectos recientes</div>
                <a href="mis_proyectos.php" class="card-link">Ver todos →</a>
            </div>
            <?php if (empty($proyectos)): ?>
            <div class="empty-state">
                <div class="empty-ico">◈</div>
                <p>Sin proyectos aún.<br>
                <a href="proyecto.php">Procesa tu primer archivo</a></p>
            </div>
            <?php else: ?>
            <div class="proj-list">
                <?php foreach ($proyectos as $p): ?>
                <div class="proj-row">
                    <div class="proj-dot"></div>
                    <div class="proj-info">
                        <div class="proj-name"><?= htmlspecialchars($p['nombre']) ?></div>
                        <div class="proj-meta">
                            <?= number_format($p['total_puntos']) ?> pts
                            · <?= number_format($p['area_m2'], 1) ?> m²
                            · <?= date('d/m/Y', strtotime($p['creado_en'])) ?>
                        </div>
                    </div>
                    <a href="mis_proyectos.php" class="proj-btn">Ver →</a>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Actividad reciente -->
        <div class="bento-card card-activity">
            <div class="card-header">
                <div class="card-title">Actividad</div>
                <span class="card-tag">Reciente</span>
            </div>
            <?php if (empty($actividades)): ?>
            <div class="empty-state">
                <div class="empty-ico">·</div>
                <p>Sin actividad registrada.</p>
            </div>
            <?php else: ?>
            <div class="act-list">
                <?php foreach ($actividades as $a): ?>
                <div class="act-row">
                    <div class="act-ico-wrap"><?= iconoActividad($a['tipo']) ?></div>
                    <div class="act-info">
                        <div class="act-desc"><?= htmlspecialchars($a['descripcion'] ?? $a['tipo']) ?></div>
                        <div class="act-time"><?= date('d/m H:i', strtotime($a['creado_en'])) ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

    </div>

    <!-- ── ROW 3: Cuenta + Upgrade ── -->
    <div class="bento-row row-3">

        <!-- Info cuenta -->
        <div class="bento-card card-account">
            <div class="card-title">Tu cuenta</div>
            <div class="account-rows">
                <div class="acc-row">
                    <span class="acc-lbl">Nombre</span>
                    <span class="acc-val"><?= htmlspecialchars($usuario['nombre']) ?></span>
                </div>
                <div class="acc-row">
                    <span class="acc-lbl">Email</span>
                    <span class="acc-val"><?= htmlspecialchars($usuario['email']) ?></span>
                </div>
                <div class="acc-row">
                    <span class="acc-lbl">Plan</span>
                    <span class="acc-val plan-<?= $usuario['plan'] ?>">
                        <?= $usuario['plan'] === 'premium' ? '★ Premium' : '◈ Free' ?>
                    </span>
                </div>
                <div class="acc-row">
                    <span class="acc-lbl">Miembro desde</span>
                    <span class="acc-val"><?= date('d/m/Y', strtotime($usuario['creado_en'])) ?></span>
                </div>
            </div>
        </div>

        <!-- Upgrade o banner premium -->
        <?php if ($usuario['plan'] === 'free'): ?>
        <div class="bento-card card-upgrade">
            <div class="upgrade-glow"></div>
            <div class="upgrade-star">★</div>
            <h3>Actualiza a Premium</h3>
            <p>Desbloquea puntos ilimitados, exportación PDF, soporte prioritario y mucho más.</p>
            <a href="planes.php" class="upgrade-btn">Ver planes →</a>
        </div>
        <?php else: ?>
        <div class="bento-card card-upgrade premium-active">
            <div class="upgrade-glow teal"></div>
            <div class="upgrade-star">★</div>
            <h3>Plan Premium activo</h3>
            <p>Tienes acceso completo a todas las funciones de FYLCAD.</p>
            <a href="proyecto.php" class="upgrade-btn">Ir al Módulo 3D →</a>
        </div>
        <?php endif; ?>

        <!-- Mini mapa topográfico decorativo -->
        <div class="bento-card card-topo">
            <div class="topo-label">FYLCAD · Topografía Digital</div>
            <svg class="topo-svg" viewBox="0 0 400 220" xmlns="http://www.w3.org/2000/svg">
                <defs>
                    <style>.tl{fill:none;stroke:#00e5c0;stroke-width:.8;opacity:.5}</style>
                </defs>
                <ellipse class="tl" cx="200" cy="110" rx="190" ry="95"/>
                <ellipse class="tl" cx="200" cy="110" rx="155" ry="76"/>
                <ellipse class="tl" cx="200" cy="110" rx="120" ry="58"/>
                <ellipse class="tl" cx="200" cy="110" rx="88"  ry="42"/>
                <ellipse class="tl" cx="200" cy="110" rx="57"  ry="27"/>
                <ellipse class="tl" cx="200" cy="110" rx="28"  ry="13"/>
                <ellipse class="tl" cx="80"  cy="190" rx="90"  ry="45"/>
                <ellipse class="tl" cx="80"  cy="190" rx="60"  ry="30"/>
                <ellipse class="tl" cx="80"  cy="190" rx="30"  ry="15"/>
                <ellipse class="tl" cx="340" cy="40"  rx="70"  ry="35"/>
                <ellipse class="tl" cx="340" cy="40"  rx="42"  ry="21"/>
                <g fill="#00e5c0" opacity=".3">
                    <?php for($r=0;$r<6;$r++) for($c=0;$c<10;$c++) echo "<circle cx='".($c*40+20)."' cy='".($r*36+18)."' r='1.2'/>"; ?>
                </g>
            </svg>
            <div class="topo-version">v2.0 · <?= date('Y') ?></div>
        </div>

    </div>

</main>

<script>
    const d = new Date();
    const opts = { weekday:'long', day:'numeric', month:'long' };
    document.getElementById("welcomeDate").textContent =
        d.toLocaleDateString('es-ES', opts);

    // Dropdown usuario
    const avatar = document.querySelector('.topbar-user');
    avatar?.addEventListener('click', () => avatar.classList.toggle('open'));
    document.addEventListener('click', e => {
        if (!avatar?.contains(e.target)) avatar?.classList.remove('open');
    });
</script>


<script src="js/fylcad_ai_widget.js" data-pagina="dashboard"></script>
</body>
</html>