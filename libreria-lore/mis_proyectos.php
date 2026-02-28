<?php
/* =============================================
   FYLCAD — Mis Proyectos
   Archivo: mis_proyectos.php
============================================= */
session_start();
require_once 'config/db.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php'); exit;
}

$usuarioId   = $_SESSION['usuario_id'];
$usuarioPlan = $_SESSION['usuario_plan'] ?? 'free';
$db          = getDB();

/* ── Eliminar proyecto ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['eliminar_id'])) {
    $stmt = $db->prepare("DELETE FROM proyectos WHERE id = ? AND usuario_id = ?");
    $stmt->execute([(int)$_POST['eliminar_id'], $usuarioId]);
    header('Location: mis_proyectos.php?eliminado=1'); exit;
}

/* ── Filtros y búsqueda ── */
$busqueda  = trim($_GET['q']     ?? '');
$orden     = in_array($_GET['orden'] ?? '', ['creado_en','nombre','area_m2','total_puntos'])
             ? $_GET['orden'] : 'creado_en';
$dir       = ($_GET['dir'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';
$pagina    = max(1, (int)($_GET['p'] ?? 1));
$porPagina = 12;
$offset    = ($pagina - 1) * $porPagina;

$where  = "WHERE p.usuario_id = :uid";
$params = [':uid' => $usuarioId];
if ($busqueda) {
    $where .= " AND p.nombre LIKE :busqueda";
    $params[':busqueda'] = "%{$busqueda}%";
}

$total = $db->prepare("SELECT COUNT(*) FROM proyectos p $where");
$total->execute($params);
$totalProyectos = $total->fetchColumn();
$totalPaginas   = max(1, ceil($totalProyectos / $porPagina));

$stmt = $db->prepare("
    SELECT p.*,
           c.total AS cotizacion_total,
           IF(a.id IS NOT NULL, 1, 0) AS tiene_csv,
           a.tamano_kb AS csv_kb
    FROM proyectos p
    LEFT JOIN cotizaciones c ON c.proyecto_id = p.id
    LEFT JOIN archivos a     ON a.proyecto_id = p.id
    $where
    ORDER BY p.{$orden} {$dir}
    LIMIT :limit OFFSET :offset
");
$params[':limit']  = $porPagina;
$params[':offset'] = $offset;
$stmt->execute($params);
$proyectos = $stmt->fetchAll();

$usuarioData = $db->prepare("SELECT nombre, email FROM usuarios WHERE id = ?");
$usuarioData->execute([$usuarioId]);
$usuario = $usuarioData->fetch();

function fmtNum($n, $dec = 2) { return number_format((float)$n, $dec, ',', '.'); }
function fmtFecha($f) { return date('d/m/Y', strtotime($f)); }
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>FYLCAD — Mis Proyectos</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/dashboard.css">
    <style>
    .page-header { display:flex;align-items:center;justify-content:space-between;margin-bottom:24px;gap:16px;flex-wrap:wrap; }
    .page-header h2 { font-family:"Syne",sans-serif;font-size:22px;font-weight:800;color:#fff;letter-spacing:-.5px; }
    .search-bar { display:flex;gap:10px;align-items:center;flex-wrap:wrap; }
    .search-input-wrap { position:relative;display:flex;align-items:center; }
    .search-icon { position:absolute;left:11px;font-size:13px;color:#64748b;pointer-events:none; }
    .search-input { background:#0c1120;border:1px solid rgba(255,255,255,.07);border-radius:8px;padding:9px 14px 9px 34px;font-size:13px;color:#e8edf5;font-family:"DM Sans",sans-serif;outline:none;width:220px;transition:border-color .2s; }
    .search-input:focus { border-color:rgba(0,229,192,.35); }
    .search-input::placeholder { color:#64748b; }
    .select-orden { background:#0c1120;border:1px solid rgba(255,255,255,.07);color:#e8edf5;border-radius:8px;padding:9px 12px;font-size:13px;font-family:"DM Sans",sans-serif;outline:none;cursor:pointer; }
    .projects-grid { display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px; }
    .project-card { background:#0c1120;border:1px solid rgba(255,255,255,.07);border-radius:14px;padding:20px;display:flex;flex-direction:column;gap:12px;transition:all .2s;position:relative;overflow:hidden; }
    .project-card::before { content:"";position:absolute;top:0;left:0;right:0;height:2px;background:linear-gradient(90deg,#00e5c0,#3b82f6);opacity:0;transition:opacity .2s; }
    .project-card:hover { border-color:rgba(255,255,255,.13);transform:translateY(-2px); }
    .project-card:hover::before { opacity:1; }
    .project-name { font-family:"Syne",sans-serif;font-size:15px;font-weight:700;color:#fff;white-space:nowrap;overflow:hidden;text-overflow:ellipsis; }
    .project-date { font-size:11px;color:#64748b; }
    .project-metrics { display:grid;grid-template-columns:1fr 1fr;gap:8px; }
    .pm { background:#0a0f1c;border:1px solid rgba(255,255,255,.05);border-radius:6px;padding:8px 10px; }
    .pm-label { font-size:9px;color:#64748b;text-transform:uppercase;letter-spacing:.8px; }
    .pm-val { font-size:14px;font-weight:700;color:#e8edf5;font-family:"Syne",sans-serif; }
    .pm-unit { font-size:9px;color:#64748b; }
    .project-footer { display:flex;gap:8px;align-items:center;margin-top:4px; }
    .btn-open { flex:1;background:#00e5c0;color:#020617;border:none;border-radius:7px;padding:9px;font-size:12px;font-weight:700;font-family:"DM Sans",sans-serif;text-decoration:none;text-align:center;transition:all .2s;cursor:pointer;display:block; }
    .btn-open:hover { background:#00ffda;box-shadow:0 0 14px rgba(0,229,192,.3); }
    .btn-cot { flex:1;background:rgba(0,229,192,.1);color:#00e5c0;border:1px solid rgba(0,229,192,.25);border-radius:7px;padding:9px;font-size:12px;font-weight:700;font-family:"DM Sans",sans-serif;text-decoration:none;text-align:center;transition:all .2s;cursor:pointer;display:block; }
    .btn-cot:hover { background:rgba(0,229,192,.2); }
    .btn-del { background:transparent;border:1px solid rgba(239,68,68,.25);color:#ef4444;border-radius:7px;padding:9px 12px;font-size:12px;cursor:pointer;transition:all .2s;font-family:"DM Sans",sans-serif; }
    .btn-del:hover { background:rgba(239,68,68,.1);border-color:rgba(239,68,68,.5); }
    .btn-open-nocsv { background:#334155 !important;color:#94a3b8 !important; }
    .btn-open-nocsv:hover { background:#475569 !important;box-shadow:none !important; }
    .project-cotizacion { display:flex;align-items:center;justify-content:space-between;background:#0a0f1c;border:1px solid rgba(0,229,192,.1);border-radius:6px;padding:7px 10px; }
    .cot-label { font-size:10px;color:#64748b; }
    .cot-total { font-size:13px;font-weight:700;color:#00e5c0;font-family:"Syne",sans-serif; }
    .csv-badge { display:flex;align-items:center;gap:6px;font-size:10px;padding:5px 10px;border-radius:6px;border:1px solid;font-weight:500; }
    .csv-ok { background:rgba(0,229,192,.07);border-color:rgba(0,229,192,.2);color:#00e5c0; }
    .csv-missing { background:rgba(245,158,11,.07);border-color:rgba(245,158,11,.2);color:#f59e0b; }
    .empty-projects { text-align:center;padding:80px 20px;grid-column:1/-1; }
    .empty-projects .icon { font-size:48px;color:#1e293b;margin-bottom:16px; }
    .empty-projects h3 { font-family:"Syne",sans-serif;font-size:18px;color:#e8edf5;margin-bottom:8px; }
    .empty-projects p { font-size:13px;color:#64748b;margin-bottom:20px;line-height:1.7; }
    .empty-projects a { color:#00e5c0;font-weight:600;text-decoration:none; }
    .pagination { display:flex;justify-content:center;gap:8px;margin-top:28px;flex-wrap:wrap; }
    .page-btn { background:#0c1120;border:1px solid rgba(255,255,255,.07);color:#64748b;border-radius:7px;padding:8px 14px;font-size:13px;text-decoration:none;transition:all .2s;font-family:"DM Sans",sans-serif; }
    .page-btn:hover { border-color:rgba(0,229,192,.3);color:#00e5c0; }
    .page-btn.active { background:#00e5c0;color:#020617;border-color:#00e5c0;font-weight:700; }
    .page-btn.disabled { opacity:.3;pointer-events:none; }
    .alert-top { background:rgba(0,229,192,.08);border:1px solid rgba(0,229,192,.2);border-radius:10px;padding:12px 18px;margin-bottom:20px;font-size:13px;color:#00e5c0;display:flex;align-items:center;gap:10px; }
    .confirm-modal { position:fixed;inset:0;z-index:500;background:rgba(0,0,0,.75);backdrop-filter:blur(6px);display:flex;align-items:center;justify-content:center;opacity:0;pointer-events:none;transition:opacity .25s; }
    .confirm-modal.open { opacity:1;pointer-events:auto; }
    .confirm-box { background:#0c1120;border:1px solid rgba(255,255,255,.1);border-radius:16px;padding:32px;width:360px;max-width:90vw;text-align:center;transform:translateY(20px);transition:transform .25s; }
    .confirm-modal.open .confirm-box { transform:translateY(0); }
    .confirm-box h3 { font-family:"Syne",sans-serif;font-size:18px;color:#fff;margin-bottom:10px; }
    .confirm-box p { font-size:13px;color:#64748b;margin-bottom:24px;line-height:1.6; }
    .confirm-btns { display:flex;gap:10px; }
    .confirm-btn { flex:1;padding:11px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;border:none;font-family:"DM Sans",sans-serif;transition:all .2s; }
    .confirm-cancel { background:rgba(255,255,255,.06);color:#64748b;border:1px solid rgba(255,255,255,.08); }
    .confirm-cancel:hover { background:rgba(255,255,255,.1); }
    .confirm-delete { background:#ef4444;color:#fff; }
    .confirm-delete:hover { background:#dc2626;box-shadow:0 0 16px rgba(239,68,68,.3); }
    </style>
</head>
<body>

<aside class="sidebar" id="sidebar">
    <div class="sidebar-logo">
        <a href="index.php" class="logo">FYL<span>CAD</span></a>
        <button class="sidebar-close" id="sidebarClose">✕</button>
    </div>
    <div class="sidebar-user">
        <div class="user-avatar"><?= strtoupper(substr($usuario['nombre'], 0, 2)) ?></div>
        <div class="user-info">
            <div class="user-name"><?= htmlspecialchars($usuario['nombre']) ?></div>
            <div class="user-email"><?= htmlspecialchars($usuario['email']) ?></div>
        </div>
    </div>
    <div class="plan-badge <?= $usuarioPlan ?>">
        <?= $usuarioPlan === 'premium' ? '★ Plan Premium' : '◈ Plan Free' ?>
    </div>
    <nav class="sidebar-nav">
        <a href="dashboard.php"     class="nav-item"><span class="nav-icon">⊞</span> Dashboard</a>
        <a href="proyecto.php"      class="nav-item"><span class="nav-icon">◈</span> Módulo 3D</a>
        <a href="mis_proyectos.php" class="nav-item active"><span class="nav-icon">📁</span> Mis Proyectos</a>
        <a href="perfil.php"        class="nav-item"><span class="nav-icon">👤</span> Mi Perfil</a>
        <a href="planes.php"        class="nav-item"><span class="nav-icon">★</span> Planes</a>
    </nav>
    <?php if ($usuarioPlan === 'free'): ?>
    <div class="upgrade-card">
        <div class="upgrade-icon">★</div>
        <h3>Actualiza a Premium</h3>
        <p>Proyectos ilimitados y más.</p>
        <a href="planes.php" class="upgrade-btn">Ver planes →</a>
    </div>
    <?php endif; ?>
    <a href="dashboard.php?logout=1" class="sidebar-logout"><span>⏻</span> Cerrar sesión</a>
</aside>

<main class="main-content" id="mainContent">

    <header class="topbar">
        <button class="menu-btn" id="menuBtn">☰</button>
        <div class="topbar-title">
            <h1>Mis Proyectos</h1>
            <span class="topbar-date">
                <?= $totalProyectos ?> proyecto<?= $totalProyectos != 1 ? 's' : '' ?> guardado<?= $totalProyectos != 1 ? 's' : '' ?>
            </span>
        </div>
        <div class="topbar-right">
            <div class="topbar-plan <?= $usuarioPlan ?>">
                <?= $usuarioPlan === 'premium' ? '★ Premium' : '◈ Free' ?>
            </div>
            <a href="proyecto.php" class="btn-primary" style="font-size:12px;padding:8px 16px;text-decoration:none;">+ Nuevo</a>
        </div>
    </header>

    <div class="content">

        <?php if (isset($_GET['eliminado'])): ?>
        <div class="alert-top">✓ Proyecto eliminado correctamente.</div>
        <?php endif; ?>

        <div class="page-header">
            <h2>Todos los proyectos</h2>
            <form class="search-bar" method="GET" action="">
                <div class="search-input-wrap">
                    <span class="search-icon">🔍</span>
                    <input type="text" name="q" class="search-input"
                           placeholder="Buscar proyecto..."
                           value="<?= htmlspecialchars($busqueda) ?>">
                </div>
                <select name="orden" class="select-orden" onchange="this.form.submit()">
                    <option value="creado_en"    <?= $orden==='creado_en'    ? 'selected':'' ?>>Más reciente</option>
                    <option value="nombre"       <?= $orden==='nombre'       ? 'selected':'' ?>>Nombre A-Z</option>
                    <option value="area_m2"      <?= $orden==='area_m2'      ? 'selected':'' ?>>Mayor área</option>
                    <option value="total_puntos" <?= $orden==='total_puntos' ? 'selected':'' ?>>Más puntos</option>
                </select>
                <button type="submit" class="btn-open" style="width:auto;padding:9px 16px;border-radius:8px;">Buscar</button>
            </form>
        </div>

        <div class="projects-grid">
            <?php if (empty($proyectos)): ?>
            <div class="empty-projects">
                <div class="icon">📁</div>
                <h3><?= $busqueda ? 'Sin resultados' : 'Aún no tienes proyectos' ?></h3>
                <p><?= $busqueda
                    ? "No encontramos proyectos con «{$busqueda}»."
                    : "Carga tu primer archivo CSV en el módulo 3D<br>y guarda el proyecto para verlo aquí." ?></p>
                <a href="proyecto.php">→ Ir al módulo 3D</a>
            </div>

            <?php else: foreach ($proyectos as $p): ?>
            <div class="project-card">
                <div>
                    <div class="project-name" title="<?= htmlspecialchars($p['nombre']) ?>">
                        <?= htmlspecialchars($p['nombre']) ?>
                    </div>
                    <div class="project-date"><?= fmtFecha($p['creado_en']) ?></div>
                </div>

                <div class="csv-badge <?= $p['tiene_csv'] ? 'csv-ok' : 'csv-missing' ?>">
                    <?php if ($p['tiene_csv']): ?>
                        <span>💾</span> CSV guardado · <?= number_format($p['csv_kb'], 1) ?> KB
                    <?php else: ?>
                        <span>⚠️</span> Sin archivo CSV guardado
                    <?php endif; ?>
                </div>

                <div class="project-metrics">
                    <div class="pm">
                        <div class="pm-label">Puntos</div>
                        <div class="pm-val"><?= number_format($p['total_puntos']) ?></div>
                    </div>
                    <div class="pm">
                        <div class="pm-label">Triángulos</div>
                        <div class="pm-val"><?= number_format($p['total_triangulos']) ?></div>
                    </div>
                    <div class="pm">
                        <div class="pm-label">Área</div>
                        <div class="pm-val"><?= fmtNum($p['area_m2'], 0) ?></div>
                        <div class="pm-unit">m²</div>
                    </div>
                    <div class="pm">
                        <div class="pm-label">Desnivel</div>
                        <div class="pm-val"><?= fmtNum($p['desnivel'] ?? 0, 1) ?></div>
                        <div class="pm-unit">m</div>
                    </div>
                </div>

                <?php if ($p['cotizacion_total']): ?>
                <div class="project-cotizacion">
                    <span class="cot-label">Cotización total</span>
                    <span class="cot-total">$ <?= number_format($p['cotizacion_total'], 0, ',', '.') ?> COP</span>
                </div>
                <?php endif; ?>

                <div class="project-footer">
                    <?php if ($p['tiene_csv']): ?>
                    <a href="proyecto.php?cargar=<?= $p['id'] ?>" class="btn-open">🗺 Visor 3D</a>
                    <a href="cotizacion.php?proyecto=<?= $p['id'] ?>" class="btn-cot">💰 Cotizar</a>
                    <?php else: ?>
                    <a href="proyecto.php" class="btn-open btn-open-nocsv">↑ Subir CSV</a>
                    <?php endif; ?>
                    <button class="btn-del"
                            data-id="<?= $p['id'] ?>"
                            data-nombre="<?= htmlspecialchars($p['nombre']) ?>">🗑</button>
                </div>
            </div>
            <?php endforeach; endif; ?>
        </div>

        <?php if ($totalPaginas > 1): ?>
        <div class="pagination">
            <a href="?p=<?= $pagina-1 ?>&q=<?= urlencode($busqueda) ?>&orden=<?= $orden ?>"
               class="page-btn <?= $pagina <= 1 ? 'disabled' : '' ?>">← Anterior</a>
            <?php for ($i = max(1,$pagina-2); $i <= min($totalPaginas,$pagina+2); $i++): ?>
            <a href="?p=<?= $i ?>&q=<?= urlencode($busqueda) ?>&orden=<?= $orden ?>"
               class="page-btn <?= $i === $pagina ? 'active' : '' ?>"><?= $i ?></a>
            <?php endfor; ?>
            <a href="?p=<?= $pagina+1 ?>&q=<?= urlencode($busqueda) ?>&orden=<?= $orden ?>"
               class="page-btn <?= $pagina >= $totalPaginas ? 'disabled' : '' ?>">Siguiente →</a>
        </div>
        <?php endif; ?>

    </div>
</main>

<div class="confirm-modal" id="confirmModal">
    <div class="confirm-box">
        <h3>¿Eliminar proyecto?</h3>
        <p id="confirmMsg">Esta acción no se puede deshacer.</p>
        <form method="POST" action="">
            <input type="hidden" name="eliminar_id" id="eliminarId">
            <div class="confirm-btns">
                <button type="button" class="confirm-btn confirm-cancel" id="confirmCancelar">Cancelar</button>
                <button type="submit" class="confirm-btn confirm-delete">Sí, eliminar</button>
            </div>
        </form>
    </div>
</div>

<div class="sidebar-overlay" id="sidebarOverlay"></div>

<script>
const sidebar  = document.getElementById("sidebar");
const overlay  = document.getElementById("sidebarOverlay");
const menuBtn  = document.getElementById("menuBtn");
const closeBtn = document.getElementById("sidebarClose");
menuBtn?.addEventListener("click",  () => { sidebar.classList.add("open");    overlay.classList.add("show"); });
closeBtn?.addEventListener("click", () => { sidebar.classList.remove("open"); overlay.classList.remove("show"); });
overlay?.addEventListener("click",  () => { sidebar.classList.remove("open"); overlay.classList.remove("show"); });

const modal    = document.getElementById("confirmModal");
const msgEl    = document.getElementById("confirmMsg");
const inputId  = document.getElementById("eliminarId");
const cancelEl = document.getElementById("confirmCancelar");

document.querySelectorAll(".btn-del").forEach(btn => {
    btn.addEventListener("click", () => {
        inputId.value     = btn.dataset.id;
        msgEl.textContent = `¿Seguro que quieres eliminar "${btn.dataset.nombre}"? Esta acción no se puede deshacer.`;
        modal.classList.add("open");
    });
});
cancelEl?.addEventListener("click", () => modal.classList.remove("open"));
modal?.addEventListener("click", e => { if (e.target === modal) modal.classList.remove("open"); });
</script>


<script src="js/fylcad_ai_widget.js" data-pagina="mis_proyectos"></script>
</body>
</html>