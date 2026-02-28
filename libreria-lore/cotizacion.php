<?php
/* ============================================================
   FYLCAD — Módulo de Cotización v3
   cotizacion.php
   - Lee datos desde localStorage (si viene de proyecto.php)
   - O desde DB directamente si viene con ?proyecto=ID
   - Plano topográfico integrado con selección de zona
============================================================ */
session_start();
require_once 'config/db.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php'); exit;
}

$usuarioId     = $_SESSION['usuario_id'];
$usuarioNombre = $_SESSION['usuario_nombre'] ?? 'Usuario';
$usuarioPlan   = $_SESSION['usuario_plan']   ?? 'free';

/* ── Si viene ?proyecto=ID, cargar CSV desde DB ── */
$proyDB = null;
if (isset($_GET['proyecto']) && is_numeric($_GET['proyecto'])) {
    $db = getDB();
    /* Buscar el proyecto del usuario */
    $stmt = $db->prepare("
        SELECT p.*
        FROM proyectos p
        WHERE p.id = ? AND p.usuario_id = ?
        LIMIT 1
    ");
    $stmt->execute([(int)$_GET['proyecto'], $usuarioId]);
    $proy = $stmt->fetch();

    if ($proy) {
        /* Buscar el archivo CSV más reciente del proyecto */
        $stmtA = $db->prepare("
            SELECT contenido, nombre
            FROM archivos
            WHERE proyecto_id = ?
            ORDER BY id DESC
            LIMIT 1
        ");
        $stmtA->execute([(int)$_GET['proyecto']]);
        $arch = $stmtA->fetch();

        /* Siempre construir proyDB si existe el proyecto, con o sin CSV */
        $csvContent = $arch ? $arch['contenido'] : '';
        $proyDB = [
            'id'     => $proy['id'],
            'nombre' => $proy['nombre'],
            'csv'    => $csvContent,
            'tiene_csv' => !empty($csvContent),
            'meta'   => [
                'n'        => (int)$proy['total_puntos'],
                'area'     => (float)$proy['area_m2'],
                'perimetro'=> (float)$proy['perimetro_m'],
                'volumen'  => (float)$proy['volumen_m3'],
                'zMin'     => (float)$proy['cota_min'],
                'zMax'     => (float)$proy['cota_max'],
                'desnivel' => (float)$proy['desnivel'],
            ]
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>FYLCAD — Cotización<?= $proyDB ? ' · '.htmlspecialchars($proyDB['nombre']) : '' ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@600;700;800&family=DM+Sans:ital,wght@0,300;0,400;0,500;0,600;1,400&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
:root{
  --bg:#060A12; --bg2:#0a1120; --surf:#0f1a2e; --surf2:#111d35;
  --bord:rgba(255,255,255,.07); --bord2:rgba(255,255,255,.12);
  --acc:#00e5c0; --acc2:#00ffda; --acc-dim:rgba(0,229,192,.12);
  --txt:#e8edf5; --txt2:#cbd5e1; --mut:#64748b; --mut2:#475569;
  --amb:#f59e0b; --red:#ef4444; --blu:#3b82f6;
  --font-head:'Syne',sans-serif; --font:'DM Sans',sans-serif; --font-mono:'DM Mono',monospace;
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
html,body{height:100%;background:var(--bg);color:var(--txt);font-family:var(--font);overflow:hidden;}

/* ══ LAYOUT ══ */
.app{display:grid;grid-template-rows:52px 1fr;grid-template-columns:320px 1fr;height:100vh;overflow:hidden;}

/* ══ HEADER ══ */
header{
  grid-column:1/-1;background:var(--bg2);border-bottom:1px solid var(--bord);
  display:flex;align-items:center;gap:12px;padding:0 18px;z-index:100;
  position:relative;
}
header::after{
  content:'';position:absolute;bottom:0;left:0;right:0;height:1px;
  background:linear-gradient(90deg,transparent,rgba(0,229,192,.3),transparent);
}
.logo{font:800 16px var(--font-head);color:var(--txt);text-decoration:none;letter-spacing:-.5px;}
.logo span{color:var(--acc);}
.hbadge{
  font-size:10px;background:var(--acc-dim);color:var(--acc);
  border:1px solid rgba(0,229,192,.2);border-radius:20px;padding:2px 10px;
  font-family:var(--font-mono);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;
  max-width:280px;
}
.hright{margin-left:auto;display:flex;align-items:center;gap:8px;flex-shrink:0;}
.btn{padding:6px 13px;border-radius:6px;font:600 11px var(--font);cursor:pointer;border:none;transition:all .2s;white-space:nowrap;}
.btn-ghost{background:transparent;color:var(--mut);border:1px solid var(--bord);}
.btn-ghost:hover{color:var(--txt);border-color:var(--bord2);}
.btn-acc{background:var(--acc);color:#020617;}
.btn-acc:hover{background:var(--acc2);box-shadow:0 0 14px rgba(0,229,192,.3);}
.btn-out{background:transparent;color:var(--acc);border:1px solid rgba(0,229,192,.25);}
.btn-out:hover{background:var(--acc-dim);}
.mtog{display:flex;gap:2px;background:rgba(255,255,255,.04);border-radius:5px;padding:2px;border:1px solid var(--bord);}
.mbtn{padding:3px 9px;border-radius:3px;font:600 10px var(--font-mono);color:var(--mut);background:none;border:none;cursor:pointer;transition:all .2s;}
.mbtn.on{background:var(--acc-dim);color:var(--acc);}

/* ══ SIDEBAR ══ */
.sidebar{
  background:var(--bg2);border-right:1px solid var(--bord);
  display:flex;flex-direction:column;overflow:hidden;
}

/* ── Tabs sidebar ── */
.stabs{display:flex;border-bottom:1px solid var(--bord);flex-shrink:0;background:var(--bg);}
.stab{
  flex:1;padding:9px 4px;font:500 10px var(--font);color:var(--mut);background:none;
  border:none;border-bottom:2px solid transparent;cursor:pointer;transition:all .15s;
  letter-spacing:.02em;
}
.stab:hover{color:var(--txt2);}
.stab.on{color:var(--acc);border-bottom-color:var(--acc);background:rgba(0,229,192,.03);}
.spane{display:none;flex-direction:column;overflow-y:auto;flex:1;scrollbar-width:thin;scrollbar-color:var(--mut2) transparent;}
.spane.on{display:flex;}

/* ── Sin datos ── */
.nodata{padding:28px 18px;text-align:center;color:var(--mut);font-size:12px;line-height:1.9;}
.nodata-ico{font-size:42px;margin-bottom:12px;display:block;}
.nodata a{color:var(--acc);font-weight:600;text-decoration:none;}
.nodata a:hover{text-decoration:underline;}

/* ── Card info proyecto ── */
.proy-card{padding:12px 14px;border-bottom:1px solid var(--bord);background:linear-gradient(135deg,rgba(0,229,192,.03),transparent);}
.proy-nombre{font:700 13px var(--font-head);color:var(--txt);margin-bottom:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.proy-sub{font-size:10px;color:var(--mut);}
.kpis{display:grid;grid-template-columns:repeat(3,1fr);border-bottom:1px solid var(--bord);}
.kpi{padding:9px 10px;border-right:1px solid var(--bord);}
.kpi:last-child{border-right:none;}
.kpi-v{font:700 12px var(--font-mono);color:var(--acc);}
.kpi-l{font-size:9px;color:var(--mut);margin-top:2px;}

/* ── Zona ── */
.zona-sec{padding:11px 14px;border-bottom:1px solid var(--bord);}
.sec-title{font:600 10px var(--font-head);color:var(--txt2);margin-bottom:8px;display:flex;align-items:center;justify-content:space-between;letter-spacing:.03em;text-transform:uppercase;}
.sec-badge{font-size:9px;border-radius:10px;padding:2px 8px;background:rgba(245,158,11,.08);color:var(--amb);border:1px solid rgba(245,158,11,.2);}
.sec-badge.ok{background:var(--acc-dim);color:var(--acc);border-color:rgba(0,229,192,.2);}
.zona-btns{display:flex;gap:5px;margin-bottom:10px;flex-wrap:wrap;}
.zbtn{
  padding:5px 10px;border-radius:5px;font:500 10px var(--font);cursor:pointer;
  border:1px solid var(--bord);background:transparent;color:var(--mut);transition:all .18s;
}
.zbtn:hover{color:var(--txt);border-color:var(--bord2);}
.zbtn.on{border-color:rgba(0,229,192,.4);background:var(--acc-dim);color:var(--acc);}
.zona-stats{display:flex;flex-direction:column;gap:3px;}
.zrow{display:flex;justify-content:space-between;font-size:10px;padding:4px 8px;background:rgba(0,0,0,.18);border-radius:4px;}
.zrow-l{color:var(--mut);}
.zrow-v{font:500 10px var(--font-mono);color:var(--txt);}
.zrow-v.hi{color:var(--acc);}

/* ── ID proyecto ── */
.id-sec{padding:11px 14px;border-bottom:1px solid var(--bord);}
.lbl{font-size:9px;color:var(--mut);text-transform:uppercase;letter-spacing:.06em;display:block;margin-bottom:4px;margin-top:8px;}
.lbl:first-child{margin-top:0;}
.inp{width:100%;background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.08);color:var(--txt);border-radius:5px;padding:6px 8px;font:11px var(--font);transition:border-color .15s;}
.inp:focus{outline:none;border-color:rgba(0,229,192,.35);background:rgba(255,255,255,.06);}
select.inp{cursor:pointer;}
textarea.inp{resize:vertical;min-height:48px;font-size:10px;line-height:1.6;}

/* ── APU panel ── */
.apu-wrap{flex:1;overflow-y:auto;padding:12px 14px;scrollbar-width:thin;scrollbar-color:var(--mut2) transparent;}
.apu-sec{margin-bottom:14px;}
.apu-hdr{display:flex;align-items:center;gap:7px;margin-bottom:8px;padding-bottom:6px;border-bottom:1px solid var(--bord);}
.apu-num{width:20px;height:20px;border-radius:4px;background:var(--acc-dim);border:1px solid rgba(0,229,192,.2);display:flex;align-items:center;justify-content:center;font:700 9px var(--font-mono);color:var(--acc);flex-shrink:0;}
.apu-sec-name{font:700 11px var(--font-head);color:var(--txt);flex:1;letter-spacing:.02em;}
.apu-sec-sub{font:600 10px var(--font-mono);color:var(--acc);}
.apu-item{display:grid;grid-template-columns:1fr 90px 70px;align-items:center;gap:5px;padding:5px 0;border-bottom:1px solid rgba(255,255,255,.025);}
.apu-item:last-child{border:none;}
.ai-name{font:500 10px var(--font);color:var(--txt2);}
.ai-cant{font:9px var(--font-mono);color:var(--mut2);margin-top:2px;}
.ai-inp-wrap{display:flex;align-items:center;gap:2px;}
.ai-inp{width:64px;background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.08);color:var(--txt);border-radius:3px;padding:3px 5px;font:10px var(--font-mono);text-align:right;transition:border-color .15s;}
.ai-inp:focus{outline:none;border-color:rgba(0,229,192,.35);}
.ai-unit{font-size:8px;color:var(--mut2);}
.ai-sub{font:600 10px var(--font-mono);color:var(--txt);text-align:right;}

/* Parámetros colapsables */
.apu-params{padding:6px 0 4px;display:flex;flex-direction:column;gap:5px;}
.apu-params.hidden{display:none;}
.param-row{display:flex;align-items:center;justify-content:space-between;gap:6px;}
.param-label{font:400 9px var(--font);color:var(--mut2);}

/* KPI pequeños (indicadores presupuesto) */
.kpi-grid4{display:grid;grid-template-columns:1fr 1fr 1fr;gap:5px;}
.kpi2{background:var(--bg2);border-radius:5px;padding:6px 8px;border:1px solid var(--bord);}
.kpi2-v{font:700 11px var(--font-mono);color:var(--acc);}
.kpi2-l{font-size:8px;color:var(--mut);margin-top:1px;}

/* Factor detalle */
.fac-row{display:flex;gap:5px;margin-bottom:1px;}
.fac-ico{color:var(--acc);font-size:9px;}
.fac-badge{display:inline-block;padding:1px 5px;border-radius:10px;font:600 8px var(--font-mono);background:rgba(245,158,11,.12);color:var(--amb);margin-left:5px;border:1px solid rgba(245,158,11,.2);}

/* Factor */
.factor-row{display:flex;align-items:center;gap:9px;padding:9px 0 6px;border-bottom:1px solid var(--bord);margin-bottom:8px;}
.factor-ico{font-size:16px;}
.factor-info{flex:1;}
.factor-title{font:600 10px var(--font-head);color:var(--amb);letter-spacing:.02em;}
.factor-sub{font-size:9px;color:var(--mut);margin-top:2px;}
.factor-input{width:54px;background:rgba(245,158,11,.06);border:1px solid rgba(245,158,11,.25);color:var(--amb);border-radius:4px;padding:4px 6px;font:700 12px var(--font-mono);text-align:center;}
.factor-input:focus{outline:none;border-color:var(--amb);}

/* ── Barra total ── */
.totbar{
  background:var(--bg);border-top:1px solid var(--bord);padding:9px 14px;flex-shrink:0;
  background:linear-gradient(0deg,rgba(0,0,0,.3),var(--bg2));
}
.totbar-row{display:flex;align-items:center;gap:8px;flex-wrap:wrap;}
.ti{display:flex;flex-direction:column;gap:1px;}
.ti-l{font-size:8px;color:var(--mut);text-transform:uppercase;letter-spacing:.05em;}
.ti-v{font:700 11px var(--font-mono);color:var(--txt2);}
.ti-v.big{font-size:14px;color:var(--acc);}
.tsep{width:1px;height:28px;background:var(--bord);}

/* ══ MAIN ══ */
.main{display:flex;flex-direction:column;overflow:hidden;background:var(--bg);}
.mtabs{display:flex;padding:0 14px;background:var(--bg2);border-bottom:1px solid var(--bord);flex-shrink:0;}
.mtab{padding:10px 14px;font:500 11px var(--font);color:var(--mut);background:none;border:none;border-bottom:2px solid transparent;cursor:pointer;transition:all .18s;white-space:nowrap;}
.mtab:hover{color:var(--txt);}
.mtab.on{color:var(--acc);border-bottom-color:var(--acc);}
.mpanes{flex:1;position:relative;overflow:hidden;}
.mpane{display:none;position:absolute;inset:0;}
.mpane.on{display:flex;flex-direction:column;}

/* ══ PLANO ══ */
.plano-toolbar{
  display:flex;align-items:center;gap:5px;padding:6px 12px;
  background:var(--bg2);border-bottom:1px solid var(--bord);flex-shrink:0;
}
.ptool{padding:4px 9px;border-radius:4px;font:500 10px var(--font);cursor:pointer;border:1px solid var(--bord);background:transparent;color:var(--mut);transition:all .18s;}
.ptool:hover{color:var(--txt);border-color:var(--bord2);}
.ptool.on{background:var(--acc-dim);border-color:rgba(0,229,192,.35);color:var(--acc);}
.ptool.draw-active{background:rgba(0,229,192,.18);border-color:var(--acc);color:var(--acc);animation:pulseBtn 1.5s ease-in-out infinite;}
@keyframes pulseBtn{0%,100%{box-shadow:0 0 0 0 rgba(0,229,192,0)}50%{box-shadow:0 0 0 3px rgba(0,229,192,.2)}}
.ptool-sep{width:1px;height:18px;background:var(--bord);flex-shrink:0;}
.plano-wrap{flex:1;position:relative;overflow:hidden;}
#cvs{display:block;width:100%;height:100%;cursor:grab;}
#cvs.draw-mode{cursor:crosshair;}

/* Overlay zona info */
.zona-ov{
  position:absolute;top:10px;right:10px;
  background:rgba(6,10,18,.92);border:1px solid rgba(0,229,192,.3);
  border-radius:8px;padding:9px 12px;font-size:10px;min-width:152px;
  pointer-events:none;display:none;backdrop-filter:blur(8px);
}
.zona-ov-title{font:700 9px var(--font-head);color:var(--acc);text-transform:uppercase;letter-spacing:.07em;margin-bottom:5px;}
.zona-ov-row{display:flex;justify-content:space-between;gap:10px;margin-bottom:2px;}
.zona-ov-l{color:var(--mut);}
.zona-ov-v{font-family:var(--font-mono);color:var(--txt2);}

/* Hint dibujo */
.draw-hint{
  position:absolute;bottom:40px;left:50%;transform:translateX(-50%);
  background:rgba(0,229,192,.12);border:1px solid rgba(0,229,192,.3);
  border-radius:6px;padding:7px 14px;font-size:10px;color:var(--acc);
  pointer-events:none;display:none;white-space:nowrap;
  backdrop-filter:blur(8px);
}
.draw-hint.show{display:block;}

.plano-status{
  padding:5px 12px;background:var(--bg2);border-top:1px solid var(--bord);
  font:10px var(--font-mono);color:var(--mut);display:flex;gap:14px;flex-shrink:0;
  justify-content:space-between;
}

/* ══ RESUMEN ══ */
#mpane-res{overflow-y:auto;padding:20px 24px;scrollbar-width:thin;scrollbar-color:var(--mut2) transparent;}
.res-head{
  background:linear-gradient(135deg,#000060 0%,#001a80 60%,#002060 100%);
  border-radius:10px;padding:18px 22px;margin-bottom:16px;
  display:flex;justify-content:space-between;align-items:flex-start;
  border:1px solid rgba(0,229,192,.1);
}
.res-logo{font:800 20px var(--font-head);color:#fff;}
.res-logo span{color:var(--acc);}
.res-meta{text-align:right;}
.res-meta h2{font:700 14px var(--font-head);color:#fff;margin-bottom:3px;}
.res-meta p{font-size:10px;color:rgba(255,255,255,.38);margin-top:1px;}
.res-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px;}
.rcard{background:var(--surf);border:1px solid var(--bord);border-radius:8px;overflow:hidden;}
.rcard-title{padding:7px 12px;background:rgba(0,229,192,.04);border-bottom:1px solid var(--bord);font:600 9px var(--font-head);color:var(--acc);text-transform:uppercase;letter-spacing:.07em;}
.rrow{display:flex;justify-content:space-between;padding:5px 12px;border-bottom:1px solid rgba(255,255,255,.025);font-size:10px;}
.rrow:last-child{border:none;}
.rl{color:var(--mut);}
.rv{font-family:var(--font-mono);font-size:10px;font-weight:500;color:var(--txt2);}
.res-table{width:100%;border-collapse:collapse;font-size:10px;margin-bottom:16px;}
.res-table th{padding:7px 9px;background:rgba(0,0,100,.45);color:#fff;font:600 9px var(--font);text-transform:uppercase;letter-spacing:.04em;border:1px solid rgba(0,229,192,.12);}
.res-table td{padding:6px 9px;border:1px solid rgba(255,255,255,.04);}
.res-table tr:nth-child(even) td{background:rgba(255,255,255,.018);}
.res-table tr.rcap td{background:rgba(0,229,192,.04);font-weight:700;color:var(--acc);}
.res-table tr.rtot td{background:rgba(0,229,192,.1);font-weight:800;color:var(--acc);font-size:12px;}
.tr{text-align:right;}
.res-grand{
  background:linear-gradient(135deg,rgba(0,229,192,.07),transparent);
  border:1px solid rgba(0,229,192,.18);border-radius:8px;padding:14px 18px;
  display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;
}
.rg-lbl{font:700 10px var(--font-head);color:var(--mut);text-transform:uppercase;letter-spacing:.08em;}
.rg-alt{font-size:9px;color:var(--mut);margin-top:3px;}
.rg-val{font:800 24px var(--font-head);color:var(--acc);}
.notas{background:rgba(245,158,11,.03);border:1px solid rgba(245,158,11,.15);border-radius:6px;padding:12px 14px;}
.notas-t{font:700 9px var(--font-head);color:var(--amb);margin-bottom:6px;letter-spacing:.04em;}
.notas ul{padding-left:14px;}
.notas li{font-size:9px;color:var(--mut);line-height:2;}

/* ══ PRODUCTOS ══ */
#mpane-prod{overflow-y:auto;padding:16px 18px;scrollbar-width:thin;scrollbar-color:var(--mut2) transparent;}
.pg-title{font:700 12px var(--font-head);color:var(--txt);margin-bottom:10px;padding-bottom:7px;border-bottom:1px solid var(--bord);}
.prod-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(185px,1fr));gap:8px;margin-bottom:18px;}
.pcard{background:var(--surf);border:1px solid var(--bord);border-radius:8px;overflow:hidden;transition:all .18s;}
.pcard:hover{border-color:rgba(0,229,192,.2);}
.pcard.sel{border-color:rgba(0,229,192,.45);background:rgba(0,229,192,.03);}
.pcard-rec{background:var(--acc-dim);color:var(--acc);font:700 8px var(--font);padding:3px 8px;text-transform:uppercase;letter-spacing:.06em;}
.pcard-ico{font-size:28px;padding:11px;text-align:center;background:rgba(0,0,0,.15);}
.pcard-body{padding:9px;}
.pcard-cat{font-size:8px;color:var(--mut);text-transform:uppercase;letter-spacing:.06em;margin-bottom:2px;}
.pcard-name{font:700 11px var(--font-head);color:var(--txt);line-height:1.3;margin-bottom:3px;}
.pcard-desc{font-size:9px;color:var(--mut);line-height:1.6;margin-bottom:7px;}
.pcard-foot{display:flex;justify-content:space-between;align-items:center;}
.pcard-precio{font:700 10px var(--font-mono);color:var(--acc);}
.ptog{padding:3px 9px;border-radius:3px;font:600 9px var(--font);cursor:pointer;border:1px solid rgba(0,229,192,.25);background:transparent;color:var(--acc);transition:all .18s;}
.ptog:hover{background:var(--acc-dim);}
.ptog.on{background:var(--acc);color:#020617;border-color:var(--acc);}

/* ══ TOAST v2 ══ */
#toast{
  position:fixed;bottom:24px;left:50%;transform:translateX(-50%) translateY(16px) scale(.95);
  background:rgba(10,17,32,.97);border:1px solid rgba(0,229,192,.3);border-radius:10px;
  padding:10px 18px 10px 14px;font-size:12px;color:var(--txt);z-index:9999;
  opacity:0;transition:opacity .3s cubic-bezier(.34,1.56,.64,1),transform .3s cubic-bezier(.34,1.56,.64,1);
  pointer-events:none;display:flex;align-items:center;gap:10px;
  box-shadow:0 8px 32px rgba(0,0,0,.5),0 0 0 1px rgba(0,229,192,.08);
  backdrop-filter:blur(12px);min-width:200px;
}
#toast.on{opacity:1;transform:translateX(-50%) translateY(0) scale(1);}
#toast.err{border-color:rgba(239,68,68,.4);box-shadow:0 8px 32px rgba(0,0,0,.5),0 0 0 1px rgba(239,68,68,.1);}
#toast.warn{border-color:rgba(245,158,11,.4);}
#toast.succ{border-color:rgba(34,197,94,.4);}
.toast-ico{font-size:16px;flex-shrink:0;}
.toast-body{flex:1;}
.toast-title{font:600 12px var(--font);color:var(--txt);}
.toast-sub{font:400 10px var(--font);color:var(--mut);margin-top:1px;}
.toast-prog{height:2px;background:rgba(0,229,192,.15);border-radius:1px;margin-top:7px;overflow:hidden;}
.toast-prog-bar{height:100%;background:var(--acc);border-radius:1px;transition:width linear;}

/* ══ ANIMACIONES GLOBALES ══ */
@keyframes fadeInUp{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:translateY(0)}}
@keyframes fadeInScale{from{opacity:0;transform:scale(.96)}to{opacity:1;transform:scale(1)}}
@keyframes flipNum{0%{transform:rotateX(90deg);opacity:0}100%{transform:rotateX(0);opacity:1}}
@keyframes glowPulse{0%,100%{box-shadow:0 0 0 0 rgba(0,229,192,0)}50%{box-shadow:0 0 20px 4px rgba(0,229,192,.18)}}
@keyframes shimmer{0%{background-position:-400px 0}100%{background-position:400px 0}}
@keyframes countUp{from{opacity:0;transform:translateY(6px)}to{opacity:1;transform:translateY(0)}}
@keyframes borderGlow{0%,100%{border-color:rgba(0,229,192,.15)}50%{border-color:rgba(0,229,192,.45)}}
@keyframes spin{to{transform:rotate(360deg)}}
@keyframes barGrow{from{width:0}to{width:var(--w)}}
@keyframes slideInLeft{from{opacity:0;transform:translateX(-12px)}to{opacity:1;transform:translateX(0)}}
@keyframes popIn{0%{transform:scale(0.5);opacity:0}70%{transform:scale(1.08)}100%{transform:scale(1);opacity:1}}
@keyframes hbadgePulse{0%,100%{background:var(--acc-dim);border-color:rgba(0,229,192,.2)}
  50%{background:rgba(0,229,192,.22);border-color:rgba(0,229,192,.5);box-shadow:0 0 10px rgba(0,229,192,.2)}}

/* ── Badge header animado ── */
.hbadge.loaded{animation:hbadgePulse 2.5s ease-in-out 3;}

/* ── KPI valores con flip ── */
.kpi-v,.kpi2-v,.ti-v{transition:all .25s;}
.kpi-v.updating,.kpi2-v.updating,.ti-v.updating{
  animation:flipNum .35s cubic-bezier(.34,1.2,.64,1);
}

/* ── Skeleton shimmer para KPIs ── */
.kpi-skel{
  background:linear-gradient(90deg,rgba(255,255,255,.04) 25%,rgba(255,255,255,.09) 50%,rgba(255,255,255,.04) 75%);
  background-size:400px 100%;animation:shimmer 1.4s infinite;
  border-radius:4px;height:14px;width:60%;
}

/* ── Totbar mejorada ── */
.totbar{
  background:linear-gradient(0deg,rgba(0,0,0,.4),rgba(10,17,32,.97));
  border-top:1px solid rgba(0,229,192,.12);
  padding:10px 16px;flex-shrink:0;
  position:relative;
}
.totbar::before{
  content:'';position:absolute;top:0;left:0;right:0;height:1px;
  background:linear-gradient(90deg,transparent,rgba(0,229,192,.4),transparent);
}
.totbar.recalculating{animation:borderGlow 1s ease-in-out 2;}
.totbar-row{display:flex;align-items:center;gap:10px;flex-wrap:wrap;}
.ti{display:flex;flex-direction:column;gap:2px;min-width:60px;}
.ti-l{font-size:8px;color:var(--mut);text-transform:uppercase;letter-spacing:.06em;}
.ti-v{font:700 12px var(--font-mono);color:var(--txt2);transition:all .3s;}
.ti-v.big{font-size:16px;color:var(--acc);letter-spacing:-.5px;}
.ti-v.updated{animation:flipNum .4s cubic-bezier(.34,1.2,.64,1);}
.tsep{width:1px;height:30px;background:var(--bord);}

/* ── TOTAL grande animado ── */
.total-highlight{
  background:linear-gradient(135deg,rgba(0,229,192,.06),rgba(0,229,192,.02));
  border:1px solid rgba(0,229,192,.15);border-radius:8px;
  padding:6px 12px;margin-left:auto;
  transition:all .3s;
}
.total-highlight.glow{animation:glowPulse .8s ease-out 1;}
.total-lbl{font:600 8px var(--font);color:var(--mut);text-transform:uppercase;letter-spacing:.08em;}
.total-val{font:800 18px var(--font-head);color:var(--acc);letter-spacing:-.5px;transition:all .3s;}

/* ── Tabs mejorados con transición ── */
.stab,.mtab{position:relative;overflow:hidden;}
.stab::after,.mtab::after{
  content:'';position:absolute;bottom:0;left:50%;width:0;height:2px;
  background:var(--acc);transition:all .25s;transform:translateX(-50%);
}
.stab.on::after,.mtab.on::after{width:100%;}

/* ── Spane con transición ── */
.spane{transition:opacity .2s;}
.spane.on{display:flex;animation:fadeInUp .25s ease-out;}
.mpane.on{animation:fadeInScale .22s ease-out;}

/* ── Factor badge mejorado ── */
.fac-badge{
  display:inline-block;padding:2px 8px;border-radius:10px;
  font:700 9px var(--font-mono);margin-left:6px;
  transition:all .3s;
}
.fac-badge.low{background:rgba(34,197,94,.12);color:#4ade80;border:1px solid rgba(34,197,94,.25);}
.fac-badge.med{background:rgba(245,158,11,.12);color:var(--amb);border:1px solid rgba(245,158,11,.25);}
.fac-badge.high{background:rgba(239,68,68,.12);color:#f87171;border:1px solid rgba(239,68,68,.25);}
.fac-badge.extreme{background:rgba(239,68,68,.2);color:#ff4444;border:1px solid rgba(239,68,68,.4);animation:glowPulse 1.5s infinite;}

/* ── APU items con hover mejorado ── */
.apu-item{
  display:grid;grid-template-columns:1fr 90px 70px;align-items:center;gap:5px;
  padding:6px 4px;border-bottom:1px solid rgba(255,255,255,.025);
  transition:background .15s;border-radius:4px;
}
.apu-item:hover{background:rgba(255,255,255,.025);}
.apu-item:last-child{border:none;}
.ai-sub{font:600 10px var(--font-mono);color:var(--txt);text-align:right;transition:all .3s;}
.ai-sub.updated{animation:flipNum .35s ease-out;}
.ai-sub.zero{color:var(--mut);}
.ai-sub.high{color:var(--acc);}

/* ── CAP headers con total animado ── */
.apu-sec-sub{
  font:600 10px var(--font-mono);color:var(--acc);
  transition:all .3s;
}
.apu-sec-sub.updated{animation:flipNum .4s ease-out;}

/* ── Resumen mejorado ── */
.res-grand{
  background:linear-gradient(135deg,rgba(0,229,192,.08),rgba(0,229,192,.02),rgba(59,130,246,.04));
  border:1px solid rgba(0,229,192,.2);border-radius:12px;padding:18px 22px;
  display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;
  position:relative;overflow:hidden;
}
.res-grand::before{
  content:'';position:absolute;top:-50%;right:-30%;width:200px;height:200px;
  background:radial-gradient(circle,rgba(0,229,192,.06),transparent 70%);
  pointer-events:none;
}
.rg-val{font:800 28px var(--font-head);color:var(--acc);letter-spacing:-1px;}
.rg-val.loading{animation:shimmer 1.4s infinite;background:linear-gradient(90deg,rgba(0,229,192,.2) 25%,rgba(0,229,192,.4) 50%,rgba(0,229,192,.2) 75%);background-size:400px 100%;-webkit-background-clip:text;-webkit-text-fill-color:transparent;}

/* ── Gráfico de barras de capítulos ── */
.cap-chart{margin-bottom:16px;}
.cap-chart-title{font:700 9px var(--font-head);color:var(--mut);text-transform:uppercase;letter-spacing:.08em;margin-bottom:10px;}
.cap-bar-row{display:flex;align-items:center;gap:10px;margin-bottom:8px;}
.cap-bar-label{font:500 10px var(--font);color:var(--txt2);width:130px;flex-shrink:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.cap-bar-track{flex:1;height:6px;background:rgba(255,255,255,.06);border-radius:3px;overflow:hidden;}
.cap-bar-fill{height:100%;border-radius:3px;transition:width 1s cubic-bezier(.34,1,.64,1);}
.cap-bar-val{font:600 10px var(--font-mono);color:var(--txt2);width:90px;text-align:right;flex-shrink:0;}

/* ── Indicadores de progreso del cálculo ── */
.calc-spinner{
  display:inline-block;width:12px;height:12px;
  border:2px solid rgba(0,229,192,.2);border-top-color:var(--acc);
  border-radius:50%;animation:spin .7s linear infinite;margin-right:6px;vertical-align:middle;
}
.btn-calcular{
  width:100%;padding:11px;background:var(--acc);color:#020617;
  border:none;border-radius:8px;font:700 12px var(--font);cursor:pointer;
  transition:all .25s;position:relative;overflow:hidden;margin-top:8px;
}
.btn-calcular::after{
  content:'';position:absolute;inset:0;
  background:linear-gradient(90deg,transparent,rgba(255,255,255,.15),transparent);
  transform:translateX(-100%);transition:transform .5s;
}
.btn-calcular:hover::after{transform:translateX(100%);}
.btn-calcular:hover{background:var(--acc2);box-shadow:0 0 20px rgba(0,229,192,.35);}
.btn-calcular:active{transform:scale(.98);}
.btn-calcular.loading{background:rgba(0,229,192,.4);cursor:not-allowed;}

/* ── KPI cards mejoradas ── */
.kpi{
  padding:10px 12px;border-right:1px solid var(--bord);
  transition:background .2s;
}
.kpi:last-child{border-right:none;}
.kpi:hover{background:rgba(255,255,255,.02);}
.kpi-v{font:700 14px var(--font-mono);color:var(--acc);transition:all .3s;}
.kpi-l{font-size:9px;color:var(--mut);margin-top:3px;}

/* ── Input focus mejorado ── */
.ai-inp{
  background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.08);
  color:var(--txt);border-radius:4px;padding:4px 6px;font:10px var(--font-mono);
  text-align:right;transition:all .2s;
}
.ai-inp:focus{
  outline:none;border-color:rgba(0,229,192,.5);
  background:rgba(0,229,192,.06);
  box-shadow:0 0 0 3px rgba(0,229,192,.08);
}

/* ── Proy card con accent line ── */
.proy-card{
  padding:12px 14px;border-bottom:1px solid var(--bord);
  background:linear-gradient(135deg,rgba(0,229,192,.04),transparent);
  position:relative;overflow:hidden;
}
.proy-card::before{
  content:'';position:absolute;left:0;top:0;bottom:0;width:3px;
  background:linear-gradient(to bottom,var(--acc),rgba(0,229,192,.2));
}

/* ── Items de cotización con animación ── */
.apu-sec{
  margin-bottom:14px;
  animation:slideInLeft .3s ease-out;
}
.apu-hdr{
  display:flex;align-items:center;gap:7px;margin-bottom:8px;
  padding-bottom:7px;border-bottom:1px solid var(--bord);
}
.apu-num{
  width:22px;height:22px;border-radius:5px;background:var(--acc-dim);
  border:1px solid rgba(0,229,192,.2);display:flex;align-items:center;
  justify-content:center;font:700 9px var(--font-mono);color:var(--acc);flex-shrink:0;
  transition:all .2s;
}
.apu-sec:hover .apu-num{background:rgba(0,229,192,.2);transform:scale(1.05);}
.apu-sec-name{font:700 11px var(--font-head);color:var(--txt);flex:1;letter-spacing:.02em;}

/* ── Zona badge con estado ── */
.sec-badge{
  font-size:9px;border-radius:10px;padding:3px 10px;
  transition:all .3s;font-family:var(--font-mono);font-weight:600;
}
.sec-badge.default{background:rgba(245,158,11,.08);color:var(--amb);border:1px solid rgba(245,158,11,.2);}
.sec-badge.ok{background:var(--acc-dim);color:var(--acc);border:1px solid rgba(0,229,192,.3);animation:popIn .4s ease-out;}

/* ── Botón guardar cotización ── */
.btn-guardar-cot{
  display:flex;align-items:center;gap:7px;padding:7px 14px;
  background:rgba(0,229,192,.1);color:var(--acc);
  border:1px solid rgba(0,229,192,.3);border-radius:7px;
  font:600 11px var(--font);cursor:pointer;transition:all .2s;
  white-space:nowrap;
}
.btn-guardar-cot:hover{background:rgba(0,229,192,.18);box-shadow:0 0 14px rgba(0,229,192,.2);}
.btn-guardar-cot.saved{background:rgba(34,197,94,.1);border-color:rgba(34,197,94,.3);color:#4ade80;}
.btn-guardar-cot .btn-ico{transition:transform .3s;}
.btn-guardar-cot:hover .btn-ico{transform:translateY(-1px);}

/* ── Indicador de cambios no guardados ── */
.unsaved-dot{
  width:7px;height:7px;border-radius:50%;background:#f59e0b;
  display:inline-block;margin-left:5px;
  animation:glowPulse 1.5s infinite;
  vertical-align:middle;
}

/* ── Panel resumen chapítulos mejorado ── */
.rcard{
  background:var(--surf);border:1px solid var(--bord);border-radius:10px;
  overflow:hidden;transition:border-color .2s;
  animation:fadeInScale .3s ease-out;
}
.rcard:hover{border-color:rgba(0,229,192,.15);}
.rcard-title{
  padding:8px 14px;background:rgba(0,229,192,.04);
  border-bottom:1px solid var(--bord);
  font:600 9px var(--font-head);color:var(--acc);
  text-transform:uppercase;letter-spacing:.07em;
  display:flex;align-items:center;gap:6px;
}
.rrow{display:flex;justify-content:space-between;padding:6px 14px;border-bottom:1px solid rgba(255,255,255,.025);font-size:10px;transition:background .15s;}
.rrow:hover{background:rgba(255,255,255,.02);}
.rrow:last-child{border:none;}
.rl{color:var(--mut);}
.rv{font-family:var(--font-mono);font-size:10px;font-weight:600;color:var(--txt2);}

/* ══ PRINT ══ */
@media print{
  header,.stabs,.plano-toolbar,.totbar,.mtabs,.plano-status,
  .zona-sec,.id-sec,.factor-row,#mpane-plano,#mpane-prod,
  .btn,.mbtn{display:none!important;}
  .app{display:block;} .main{display:block;}
  #mpane-res{display:block!important;position:static;overflow:visible;padding:0;}
  body{background:#fff;color:#000;}
}

.sim-layer-legend{position:absolute;bottom:36px;right:12px;display:flex;flex-direction:column;gap:3px;}
.sim-leg-item{display:flex;align-items:center;gap:5px;font:400 9px var(--font-mono);color:var(--txt2);}
.sim-leg-dot{width:10px;height:10px;border-radius:2px;flex-shrink:0;}


/* ══ SIMULACIÓN DE RELLENO ══ */
.sim-layout{display:grid;grid-template-columns:260px 1fr;height:100%;overflow:hidden;}
.sim-sidebar{background:var(--bg2);border-right:1px solid var(--bord);display:flex;flex-direction:column;overflow-y:auto;scrollbar-width:thin;scrollbar-color:var(--mut2) transparent;}
.sim-canvas-wrap{position:relative;overflow:hidden;background:#080e1c;}
#simCanvas{width:100%;height:100%;display:block;}
.sim-sec{padding:12px 14px;border-bottom:1px solid var(--bord);}
.sim-sec-title{font:700 10px var(--font-head);color:var(--txt2);text-transform:uppercase;letter-spacing:.06em;margin-bottom:10px;display:flex;align-items:center;gap:6px;}
.sim-sec-title::before{content:'';width:3px;height:10px;background:var(--acc);border-radius:2px;flex-shrink:0;}
.mat-grid{display:grid;grid-template-columns:1fr 1fr;gap:5px;}
.mat-card{border:1.5px solid var(--bord);border-radius:8px;padding:9px 8px;cursor:pointer;transition:all .18s;background:rgba(255,255,255,.02);text-align:center;}
.mat-card:hover{border-color:rgba(255,255,255,.18);background:rgba(255,255,255,.05);transform:translateY(-1px);}
.mat-card.on{border-color:var(--acc);background:rgba(0,229,192,.08);box-shadow:0 0 12px rgba(0,229,192,.15);}
.mat-ico{font-size:20px;margin-bottom:4px;}
.mat-name{font:600 10px var(--font);color:var(--txt2);}
.mat-price{font:400 9px var(--font-mono);color:var(--mut);margin-top:2px;}
.mat-card.on .mat-name{color:var(--acc);}
.sim-field{display:flex;flex-direction:column;gap:3px;margin-bottom:8px;}
.sim-lbl{font-size:9px;color:var(--mut);text-transform:uppercase;letter-spacing:.06em;}
.sim-range{width:100%;accent-color:var(--acc);}
.sim-val-row{display:flex;justify-content:space-between;font:600 10px var(--font-mono);color:var(--txt);}
.sim-btn{width:100%;padding:10px;background:var(--acc);color:#020617;border:none;border-radius:7px;font:700 12px var(--font);cursor:pointer;transition:all .2s;margin-top:4px;}
.sim-btn:hover{background:var(--acc2);box-shadow:0 0 16px rgba(0,229,192,.35);}
.sim-btn:disabled{background:rgba(255,255,255,.08);color:var(--mut);cursor:not-allowed;}
.sim-btn-sec{width:100%;padding:7px;background:transparent;color:var(--acc);border:1px solid rgba(0,229,192,.3);border-radius:7px;font:600 11px var(--font);cursor:pointer;transition:all .2s;margin-top:5px;}
.sim-btn-sec:hover{background:var(--acc-dim);}
.sim-stat-grid{display:grid;grid-template-columns:1fr 1fr;gap:4px;}
.sim-stat{background:rgba(0,0,0,.25);border:1px solid var(--bord);border-radius:6px;padding:7px 9px;}
.sim-stat-v{font:700 12px var(--font-mono);color:var(--acc);}
.sim-stat-l{font-size:8px;color:var(--mut);margin-top:1px;}
.sim-progress{height:4px;background:rgba(255,255,255,.07);border-radius:2px;overflow:hidden;margin:8px 0 4px;}
.sim-progress-bar{height:100%;background:linear-gradient(90deg,var(--acc),var(--acc2));border-radius:2px;transition:width .1s linear;width:0%;}
.sim-anim-status{font:400 10px var(--font-mono);color:var(--mut);text-align:center;margin-bottom:8px;}
.sim-overlay{position:absolute;top:12px;right:12px;background:rgba(6,10,18,.88);border:1px solid var(--bord);border-radius:8px;padding:10px 14px;min-width:170px;backdrop-filter:blur(8px);}
.sim-ov-title{font:700 10px var(--font-head);color:var(--acc);text-transform:uppercase;letter-spacing:.05em;margin-bottom:7px;}
.sim-ov-row{display:flex;justify-content:space-between;font-size:10px;padding:2px 0;}
.sim-ov-l{color:var(--mut);}
.sim-ov-v{font:500 10px var(--font-mono);color:var(--txt);}
.sim-nozone{position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;color:var(--mut);gap:12px;pointer-events:none;}
.sim-nozone-ico{font-size:48px;opacity:.3;}
.sim-nozone-txt{font:400 13px var(--font);text-align:center;line-height:1.7;opacity:.6;}
.sim-hint-bar{position:absolute;bottom:0;left:0;right:0;background:rgba(6,10,18,.7);padding:6px 14px;font:400 10px var(--font-mono);color:var(--mut);display:flex;justify-content:space-between;backdrop-filter:blur(4px);}
</style>
</head>
<body>
<div class="app">

<!-- ══ HEADER ══ -->
<header>
  <a href="proyecto.php" class="logo">FYL<span>CAD</span></a>
  <span class="hbadge" id="hbadge">Cargando…</span>
  <div class="hright">
    <div class="mtog">
      <button class="mbtn on" id="bCOP" onclick="setMoneda('COP')">COP</button>
      <button class="mbtn"    id="bUSD" onclick="setMoneda('USD')">USD</button>
    </div>
    <button class="btn-guardar-cot" id="btnGuardarCot" onclick="guardarCotizacion()" style="display:none;">
      <span class="btn-ico">💾</span>
      <span id="btnGuardarTxt">Guardar cotización</span>
    </button>
    <button class="btn btn-out" onclick="goTab('res')">📄 Resumen</button>
    <button class="btn btn-ghost" onclick="window.print()" title="Imprimir">🖨</button>
    <a href="mis_proyectos.php" class="btn btn-ghost">← Volver</a>
  </div>
</header>

<!-- ══ SIDEBAR ══ -->
<aside class="sidebar">
  <div class="stabs">
    <button class="stab on" data-s="proy">📐 Terreno</button>
    <button class="stab"    data-s="apu" >💰 APU</button>
    <button class="stab"    data-s="mat" >🔩 Items</button>
  </div>

  <!-- ── PESTAÑA TERRENO ── -->
  <div class="spane on" id="sp-proy">
    <div id="nodata" class="nodata">
      <span class="nodata-ico">📡</span>
      No hay datos del levantamiento.<br><br>
      Ve a <a href="proyecto.php">proyecto.php</a>,<br>
      carga tu CSV y abre cotización desde ahí.<br><br>
      <small style="font-size:10px;color:var(--mut2)">O abre un proyecto guardado desde<br><a href="mis_proyectos.php">Mis Proyectos</a></small>
    </div>

    <div id="pdata" style="display:none;flex-direction:column;flex:1;">
      <div class="proy-card">
        <div class="proy-nombre" id="pNombre">—</div>
        <div class="proy-sub"    id="pSub"   >—</div>
      </div>
      <div class="kpis">
        <div class="kpi"><div class="kpi-v" id="k-area">—</div><div class="kpi-l">m² área</div></div>
        <div class="kpi"><div class="kpi-v" id="k-vol" >—</div><div class="kpi-l">m³ vol.</div></div>
        <div class="kpi"><div class="kpi-v" id="k-desn">—</div><div class="kpi-l">m desn.</div></div>
      </div>

      <!-- Zona a cotizar -->
      <div class="zona-sec">
        <div class="sec-title">
          Zona a cotizar
          <span class="sec-badge" id="zonaBadge">Terreno completo</span>
        </div>
        <div class="zona-btns">
          <button class="zbtn on"  data-z="all" >🗺 Todo</button>
          <button class="zbtn"     data-z="draw">✏️ Dibujar zona</button>
          <button class="zbtn"     data-z="clear" id="btnClearZona" style="display:none">✕ Limpiar</button>
        </div>
        <div class="zona-stats">
          <div class="zrow"><span class="zrow-l">Área cotizada</span><span class="zrow-v hi" id="zs-a">—</span></div>
          <div class="zrow"><span class="zrow-l">Volumen est.</span> <span class="zrow-v"    id="zs-v">—</span></div>
          <div class="zrow"><span class="zrow-l">Perímetro</span>    <span class="zrow-v"    id="zs-p">—</span></div>
          <div class="zrow"><span class="zrow-l">Puntos en zona</span><span class="zrow-v"   id="zs-n">—</span></div>
        </div>
      </div>

      <!-- Identificación -->
      <div class="id-sec" style="flex:1;">
        <label class="lbl">Nombre del presupuesto</label>
        <input class="inp" id="iNom" type="text" placeholder="Ej: Vía La Sanjuana — Tramo 1">
        <label class="lbl">Cliente</label>
        <input class="inp" id="iCli" type="text" placeholder="Nombre o razón social">
        <label class="lbl">Municipio</label>
        <input class="inp" id="iMun" type="text" placeholder="Ej: El Zulia, N. Santander">
        <label class="lbl">Tipo de obra</label>
        <select class="inp" id="iTipo">
          <option value="via">Vía / Carretera rural</option>
          <option value="urb">Urbanismo / Lote</option>
          <option value="can">Canal / Drenaje</option>
          <option value="edi">Edificio / Obra civil</option>
          <option value="mov">Solo movimiento de tierras</option>
        </select>
        <label class="lbl">Observaciones</label>
        <textarea class="inp" id="iObs" placeholder="Condiciones del terreno…"></textarea>
      </div>
    </div>
  </div><!-- /sp-proy -->

  <!-- ── PESTAÑA APU ── -->
  <div class="spane" id="sp-apu">
    <div class="apu-wrap">

      <!-- Factor terreno -->
      <div class="factor-row">
        <span class="factor-ico">⚠️</span>
        <div class="factor-info">
          <div class="factor-title">Factor de complejidad <span class="fac-badge" id="facBadge"></span></div>
          <div class="factor-sub" id="factorSub">Ajuste por pendiente y acceso</div>
        </div>
        <div style="display:flex;flex-direction:column;align-items:flex-end;gap:3px;">
          <select class="ai-inp" id="facModo" style="font-size:9px;width:70px;text-align:right;background:var(--bg2);color:var(--txt);">
            <option value="auto">Auto</option>
            <option value="manual">Manual</option>
          </select>
          <input class="factor-input" id="factor" type="number" value="1.00" min="0.5" max="3.0" step="0.05">
        </div>
      </div>
      <div id="facDetalle" style="padding:0 8px 8px 8px;font-size:9px;color:var(--mut);line-height:1.7;"></div>

      <!-- Parámetros volumétricos -->
      <div class="apu-sec" style="margin-bottom:6px;">
        <div class="apu-hdr" style="cursor:pointer;" onclick="this.nextElementSibling.classList.toggle('hidden')">
          <div class="apu-num" style="background:var(--acc-dim);color:var(--acc);">⚙</div>
          <span class="apu-sec-name">Parámetros volumétricos</span>
          <span class="apu-sec-sub" style="font-size:9px;">▾</span>
        </div>
        <div class="apu-params">
          <div class="param-row">
            <span class="param-label">% Roca del volumen</span>
            <div style="display:flex;align-items:center;gap:3px;">
              <input class="ai-inp" id="pct-roc" type="number" value="20" min="0" max="100" step="5" style="width:42px;text-align:center;">
              <span style="font-size:9px;color:var(--mut)">%</span>
            </div>
          </div>
          <div class="param-row">
            <span class="param-label">% Relleno (vs excav.)</span>
            <div style="display:flex;align-items:center;gap:3px;">
              <input class="ai-inp" id="pct-rel" type="number" value="35" min="0" max="100" step="5" style="width:42px;text-align:center;">
              <span style="font-size:9px;color:var(--mut)">%</span>
            </div>
          </div>
          <div class="param-row" style="margin-top:4px;padding-top:4px;border-top:1px solid rgba(255,255,255,.06);">
            <span class="param-label" style="color:var(--mut)">Transporte (complemento)</span>
            <span class="param-label" style="color:var(--acc);font-family:var(--font-mono);" id="pct-tra-lbl">65%</span>
          </div>
        </div>
      </div>

      <!-- AIU desglosado -->
      <div class="apu-sec" style="margin-bottom:6px;">
        <div class="apu-hdr" style="cursor:pointer;" onclick="this.nextElementSibling.classList.toggle('hidden')">
          <div class="apu-num" style="background:rgba(99,102,241,.15);color:#818cf8;">AIU</div>
          <span class="apu-sec-name">A + I + U</span>
          <span class="apu-sec-sub" id="bAIUpct" style="font-size:9px;color:#818cf8;">15+5+10%</span>
        </div>
        <div class="apu-params">
          <div class="param-row">
            <span class="param-label">A — Administración</span>
            <div style="display:flex;align-items:center;gap:3px;">
              <input class="ai-inp" id="pct-a" type="number" value="15" min="0" max="50" step="1" style="width:42px;text-align:center;">
              <span style="font-size:9px;color:var(--mut)">%</span>
            </div>
          </div>
          <div class="param-row">
            <span class="param-label">I — Imprevistos</span>
            <div style="display:flex;align-items:center;gap:3px;">
              <input class="ai-inp" id="pct-i" type="number" value="5" min="0" max="20" step="1" style="width:42px;text-align:center;">
              <span style="font-size:9px;color:var(--mut)">%</span>
            </div>
          </div>
          <div class="param-row">
            <span class="param-label">U — Utilidad</span>
            <div style="display:flex;align-items:center;gap:3px;">
              <input class="ai-inp" id="pct-u" type="number" value="10" min="0" max="30" step="1" style="width:42px;text-align:center;">
              <span style="font-size:9px;color:var(--mut)">%</span>
            </div>
          </div>
        </div>
      </div>

      <!-- CAP 01 -->
      <div class="apu-sec">
        <div class="apu-hdr">
          <div class="apu-num">01</div>
          <span class="apu-sec-name">Preliminares</span>
          <span class="apu-sec-sub" id="t1">—</span>
        </div>
        <div class="apu-item">
          <div><div class="ai-name">Localización y replanteo</div><div class="ai-cant" id="c-a1">—</div></div>
          <div class="ai-inp-wrap"><input class="ai-inp" id="t-rep" type="number" value="1850"><span class="ai-unit">/m²</span></div>
          <span class="ai-sub" id="s-rep">—</span>
        </div>
        <div class="apu-item">
          <div><div class="ai-name">Cerramiento provisional</div><div class="ai-cant" id="c-p1">—</div></div>
          <div class="ai-inp-wrap"><input class="ai-inp" id="t-cer" type="number" value="29370"><span class="ai-unit">/m</span></div>
          <span class="ai-sub" id="s-cer">—</span>
        </div>
        <div class="apu-item">
          <div><div class="ai-name">Señalización y seguridad</div><div class="ai-cant" id="c-a2">—</div></div>
          <div class="ai-inp-wrap"><input class="ai-inp" id="t-sen" type="number" value="480"><span class="ai-unit">/m²</span></div>
          <span class="ai-sub" id="s-sen">—</span>
        </div>
      </div>

      <!-- CAP 02 -->
      <div class="apu-sec">
        <div class="apu-hdr">
          <div class="apu-num">02</div>
          <span class="apu-sec-name">Movimiento de tierras</span>
          <span class="apu-sec-sub" id="t2">—</span>
        </div>
        <div class="apu-item">
          <div><div class="ai-name">Descapote e=25cm</div><div class="ai-cant" id="c-a3">—</div></div>
          <div class="ai-inp-wrap"><input class="ai-inp" id="t-des" type="number" value="3464"><span class="ai-unit">/m²</span></div>
          <span class="ai-sub" id="s-des">—</span>
        </div>
        <div class="apu-item">
          <div><div class="ai-name">Excavación mecánica tierra</div><div class="ai-cant" id="c-v1">—</div></div>
          <div class="ai-inp-wrap"><input class="ai-inp" id="t-tie" type="number" value="21800"><span class="ai-unit">/m³</span></div>
          <span class="ai-sub" id="s-tie">—</span>
        </div>
        <div class="apu-item">
          <div><div class="ai-name">Excavación roca <span id="pct-roc-lbl" style="color:var(--mut);font-size:9px;">(20%)</span></div><div class="ai-cant" id="c-v2">—</div></div>
          <div class="ai-inp-wrap"><input class="ai-inp" id="t-roc" type="number" value="68000"><span class="ai-unit">/m³</span></div>
          <span class="ai-sub" id="s-roc">—</span>
        </div>
        <div class="apu-item">
          <div><div class="ai-name">Relleno y compactación <span id="pct-rel-lbl" style="color:var(--mut);font-size:9px;">(35%)</span></div><div class="ai-cant" id="c-v3">—</div></div>
          <div class="ai-inp-wrap"><input class="ai-inp" id="t-rel" type="number" value="14912"><span class="ai-unit">/m³</span></div>
          <span class="ai-sub" id="s-rel">—</span>
        </div>
        <div class="apu-item">
          <div><div class="ai-name">Nivelación de rasante</div><div class="ai-cant" id="c-a4">—</div></div>
          <div class="ai-inp-wrap"><input class="ai-inp" id="t-niv" type="number" value="860"><span class="ai-unit">/m²</span></div>
          <span class="ai-sub" id="s-niv">—</span>
        </div>
        <div class="apu-item">
          <div><div class="ai-name">Transporte sobrante &lt;5km <span id="pct-tra-lbl2" style="color:var(--mut);font-size:9px;">(65%)</span></div><div class="ai-cant" id="c-v4">—</div></div>
          <div class="ai-inp-wrap"><input class="ai-inp" id="t-tra" type="number" value="8500"><span class="ai-unit">/m³</span></div>
          <span class="ai-sub" id="s-tra">—</span>
        </div>
      </div>

      <!-- CAP 03 -->
      <div class="apu-sec">
        <div class="apu-hdr">
          <div class="apu-num">03</div>
          <span class="apu-sec-name">Obras complementarias</span>
          <span class="apu-sec-sub" id="t3">—</span>
        </div>
        <div class="apu-item">
          <div><div class="ai-name">Cunetas drenaje (40% per.)</div><div class="ai-cant" id="c-p2">—</div></div>
          <div class="ai-inp-wrap"><input class="ai-inp" id="t-cun" type="number" value="185000"><span class="ai-unit">/m</span></div>
          <span class="ai-sub" id="s-cun">—</span>
        </div>
        <div class="apu-item">
          <div><div class="ai-name">Revegetalización (30% área)</div><div class="ai-cant" id="c-a5">—</div></div>
          <div class="ai-inp-wrap"><input class="ai-inp" id="t-rev" type="number" value="6200"><span class="ai-unit">/m²</span></div>
          <span class="ai-sub" id="s-rev">—</span>
        </div>
        <div class="apu-item">
          <div>
            <input class="ai-inp" id="xNom" type="text" value="Ítem adicional" style="width:100%;margin-bottom:3px;font-size:9px;">
            <div style="display:flex;gap:3px;">
              <input class="ai-inp" id="xCant" type="number" value="0" style="width:42px;">
              <input class="ai-inp" id="xUn"   type="text"   value="Gl" style="width:28px;text-align:center;">
            </div>
          </div>
          <div class="ai-inp-wrap"><input class="ai-inp" id="t-ext" type="number" value="0"><span class="ai-unit">COP</span></div>
          <span class="ai-sub" id="s-ext">—</span>
        </div>
      </div>

      <!-- KPIs de rendimiento y costo -->
      <div class="apu-sec" style="background:var(--bg);border:1px solid rgba(0,229,192,.12);">
        <div class="apu-hdr" style="border:none;">
          <div class="apu-num" style="background:var(--acc-dim);color:var(--acc);">📊</div>
          <span class="apu-sec-name">Indicadores del presupuesto</span>
        </div>
        <div class="kpi-grid4" style="padding:6px 8px 8px;">
          <div class="kpi2"><div class="kpi2-v" id="r-c-m2">—</div><div class="kpi2-l">Costo/m²</div></div>
          <div class="kpi2"><div class="kpi2-v" id="r-c-m3">—</div><div class="kpi2-l">Costo/m³</div></div>
          <div class="kpi2"><div class="kpi2-v" id="r-plazo">—</div><div class="kpi2-l">Plazo est.</div></div>
          <div class="kpi2"><div class="kpi2-v" id="r-pct-dir">—</div><div class="kpi2-l">% Directo</div></div>
          <div class="kpi2"><div class="kpi2-v" id="r-pct-aiu" style="color:#818cf8;">—</div><div class="kpi2-l">% AIU</div></div>
        </div>
        <div style="padding:0 8px 8px;font-size:9px;color:var(--mut);line-height:1.8;">
          <div style="display:flex;justify-content:space-between;"><span>Rendimiento excavación</span><span id="r-rend-exc" style="color:var(--txt);font-family:var(--font-mono);">—</span></div>
          <div style="display:flex;justify-content:space-between;"><span>Rendimiento nivelación</span><span id="r-rend-niv" style="color:var(--txt);font-family:var(--font-mono);">—</span></div>
        </div>
      </div>

    </div><!-- /apu-wrap -->
  </div><!-- /sp-apu -->

  <!-- ── PESTAÑA ITEMS ── -->
  <div class="spane" id="sp-mat" style="overflow-y:auto;padding:12px;scrollbar-width:thin;scrollbar-color:var(--mut2) transparent;">
    <div id="sbProd"></div>
  </div>

  <!-- BARRA TOTALES -->
  <div class="totbar" id="totbar">
    <div class="totbar-row">
      <div class="ti">
        <span class="ti-l">Cap.01 Prelim.</span>
        <span class="ti-v" id="b1">—</span>
      </div>
      <div class="tsep"></div>
      <div class="ti">
        <span class="ti-l">Cap.02 Tierras</span>
        <span class="ti-v" id="b2">—</span>
      </div>
      <div class="tsep"></div>
      <div class="ti">
        <span class="ti-l">Cap.03 Complem.</span>
        <span class="ti-v" id="b3">—</span>
      </div>
      <div class="tsep"></div>
      <div class="ti">
        <span class="ti-l">AIU <span id="bAIUpctBar" style="color:#818cf8;">30%</span></span>
        <span class="ti-v" id="bAIU">—</span>
      </div>
      <div class="tsep"></div>
      <div class="total-highlight" id="totalHighlight">
        <div class="total-lbl">TOTAL PRESUPUESTO</div>
        <div class="total-val" id="bTOT">—</div>
      </div>
    </div>
  </div>
</aside>

<!-- ══ MAIN ══ -->
<div class="main">
  <div class="mtabs">
    <button class="mtab on" data-m="plano">🗺 Plano topográfico</button>
    <button class="mtab"    data-m="sim"  >🏗️ Simulación Relleno</button>
    <button class="mtab"    data-m="res"  >📄 Resumen / Exportar</button>
    <button class="mtab"    data-m="prod" >🔩 Materiales y Maquinaria</button>
  </div>
  <div class="mpanes">

    <!-- PLANO -->
    <div class="mpane on" id="mpane-plano">
      <div class="plano-toolbar">
        <button class="ptool on" id="toolPan"  onclick="setTool('pan')">✋ Mover</button>
        <button class="ptool"    id="toolDraw" onclick="setTool('draw')">⬡ Dibujar zona</button>
        <button class="ptool"    id="toolUnd"  onclick="undoVert()" style="display:none">↩ Deshacer</button>
        <button class="ptool"    onclick="clearZona()">✕ Limpiar</button>
        <div class="ptool-sep"></div>
        <button class="ptool on" id="tHipso"  onclick="togOpt('hipso')">🎨 Color</button>
        <button class="ptool on" id="tCurvas" onclick="togOpt('curvas')">〰 Curvas</button>
        <button class="ptool"    id="tPts"    onclick="togOpt('puntos')">· Puntos</button>
        <div class="ptool-sep"></div>
        <button class="ptool" onclick="resetView()">⊕ Centrar</button>
        <span style="margin-left:auto;font:10px var(--font-mono);color:var(--mut);" id="zLbl">100%</span>
      </div>
      <div class="plano-wrap">
        <canvas id="cvs"></canvas>
        <div class="zona-ov" id="zonaOv">
          <div class="zona-ov-title">✏️ Zona seleccionada</div>
          <div class="zona-ov-row"><span class="zona-ov-l">Área</span>   <span class="zona-ov-v" id="ov-a">—</span></div>
          <div class="zona-ov-row"><span class="zona-ov-l">Puntos</span> <span class="zona-ov-v" id="ov-n">—</span></div>
          <div class="zona-ov-row"><span class="zona-ov-l">Vol.est.</span><span class="zona-ov-v" id="ov-v">—</span></div>
          <div class="zona-ov-row"><span class="zona-ov-l">Perímetro</span><span class="zona-ov-v" id="ov-p">—</span></div>
        </div>
        <div class="draw-hint" id="drawHint">
          Clic → colocar vértice &nbsp;·&nbsp; Doble clic → cerrar zona &nbsp;·&nbsp; ↩ deshacer último
        </div>
      </div>
      <div class="plano-status">
        <span id="psCursor">Posición: —</span>
        <span id="psInfo">Sin datos</span>
        <span id="psZona">—</span>
      </div>
    </div>

    <!-- SIMULACIÓN DE RELLENO -->
    <div class="mpane" id="mpane-sim">
      <div class="sim-layout">

        <!-- Panel lateral izquierdo -->
        <div class="sim-sidebar">

          <!-- Material -->
          <div class="sim-sec">
            <div class="sim-sec-title">Material de relleno</div>
            <div class="mat-grid" id="matGrid">
              <!-- generado por JS -->
            </div>
          </div>

          <!-- Zona -->
          <div class="sim-sec">
            <div class="sim-sec-title">Zona de análisis</div>
            <div id="simZonaInfo" style="font-size:11px;color:var(--mut);line-height:1.7;padding:4px 0;">
              Dibuja una zona en el <strong style="color:var(--acc)">Plano topográfico</strong> primero, o usa el terreno completo.
            </div>
            <button class="sim-btn-sec" id="btnSimUsarTodo">⬡ Usar terreno completo</button>
            <button class="sim-btn-sec" id="btnSimIrPlano" onclick="goTabSim()">← Ir al plano a dibujar zona</button>
          </div>

          <!-- Parámetros -->
          <div class="sim-sec">
            <div class="sim-sec-title">Parámetros</div>

            <div class="sim-field">
              <div class="sim-lbl">Espesor de capa (m)</div>
              <input type="range" class="sim-range" id="simEspesor" min="0.05" max="2.0" step="0.05" value="0.30">
              <div class="sim-val-row"><span>0.05 m</span><span id="simEspesorVal" style="color:var(--acc)">0.30 m</span><span>2.0 m</span></div>
            </div>

            <div class="sim-field">
              <div class="sim-lbl">Profundidad total (m)</div>
              <input type="range" class="sim-range" id="simProfundidad" min="0.1" max="5.0" step="0.1" value="1.0">
              <div class="sim-val-row"><span>0.1 m</span><span id="simProfVal" style="color:var(--acc)">1.0 m</span><span>5.0 m</span></div>
            </div>

            <div class="sim-field">
              <div class="sim-lbl">Velocidad animación</div>
              <input type="range" class="sim-range" id="simVelocidad" min="1" max="5" step="1" value="3">
              <div class="sim-val-row"><span>Lenta</span><span id="simVelVal" style="color:var(--acc)">Normal</span><span>Rápida</span></div>
            </div>
          </div>

          <!-- Estadísticas -->
          <div class="sim-sec">
            <div class="sim-sec-title">Volúmenes calculados</div>
            <div class="sim-stat-grid">
              <div class="sim-stat"><div class="sim-stat-v" id="sVol">—</div><div class="sim-stat-l">m³ relleno</div></div>
              <div class="sim-stat"><div class="sim-stat-v" id="sCapas">—</div><div class="sim-stat-l">capas</div></div>
              <div class="sim-stat"><div class="sim-stat-v" id="sArea">—</div><div class="sim-stat-l">m² área</div></div>
              <div class="sim-stat"><div class="sim-stat-v" id="sCosto">—</div><div class="sim-stat-l">costo est.</div></div>
            </div>
          </div>

          <!-- Controles animación -->
          <div class="sim-sec">
            <div class="sim-progress"><div class="sim-progress-bar" id="simProgressBar"></div></div>
            <div class="sim-anim-status" id="simAnimStatus">Listo para simular</div>
            <button class="sim-btn" id="btnSimPlay">▶ Iniciar simulación</button>
            <button class="sim-btn-sec" id="btnSimReset">↺ Reiniciar vista</button>
          </div>

        </div><!-- /sim-sidebar -->

        <!-- Canvas 3D -->
        <div class="sim-canvas-wrap">
          <canvas id="simCanvas"></canvas>

          <!-- Estado sin zona -->
          <div class="sim-nozone" id="simNoZone">
            <div class="sim-nozone-ico">🏗️</div>
            <div class="sim-nozone-txt">Selecciona un material y<br>presiona "Iniciar simulación"</div>
          </div>

          <!-- Info overlay -->
          <div class="sim-overlay" id="simOverlay" style="display:none;">
            <div class="sim-ov-title" id="simMatNombre">—</div>
            <div class="sim-ov-row"><span class="sim-ov-l">Espesor capa</span><span class="sim-ov-v" id="ovEspesor">—</span></div>
            <div class="sim-ov-row"><span class="sim-ov-l">Capas completadas</span><span class="sim-ov-v" id="ovCapas">—</span></div>
            <div class="sim-ov-row"><span class="sim-ov-l">Vol. compactado</span><span class="sim-ov-v" id="ovVol">—</span></div>
            <div class="sim-ov-row"><span class="sim-ov-l">Progreso</span><span class="sim-ov-v" id="ovPct">—</span></div>
          </div>

          <!-- Leyenda capas -->
          <div class="sim-layer-legend" id="simLegend"></div>

          <!-- Hint bar -->
          <div class="sim-hint-bar">
            <span>🖱 Arrastrar: rotar · Scroll: zoom · Shift+arrastrar: mover</span>
            <span id="simFpsLabel" style="color:var(--acc);">3D</span>
          </div>
        </div>

      </div>
    </div>

    <!-- RESUMEN -->
    <div class="mpane" id="mpane-res">
      <div class="res-head">
        <div>
          <div class="res-logo">FYL<span>CAD</span></div>
          <div style="font-size:9px;color:rgba(255,255,255,.3);margin-top:3px;">Topografía SaaS · fylcad.com</div>
        </div>
        <div class="res-meta">
          <h2 id="rNom">PRESUPUESTO DE OBRA</h2>
          <p id="rCli">—</p>
          <p id="rFec">—</p>
        </div>
      </div>
      <div class="res-grid">
        <div class="rcard">
          <div class="rcard-title">📡 Levantamiento topográfico</div>
          <div class="rrow"><span class="rl">Área cotizada</span> <span class="rv" id="rr-area">—</span></div>
          <div class="rrow"><span class="rl">Perímetro</span>     <span class="rv" id="rr-per" >—</span></div>
          <div class="rrow"><span class="rl">Volumen est.</span>  <span class="rv" id="rr-vol" >—</span></div>
          <div class="rrow"><span class="rl">Desnivel</span>      <span class="rv" id="rr-des" >—</span></div>
          <div class="rrow"><span class="rl">Clasificación INVIAS</span> <span class="rv" id="rr-cla" >—</span></div>
          <div class="rrow"><span class="rl">Pendiente media / máx.</span> <span class="rv" id="rr-pend">—</span></div>
          <div class="rrow"><span class="rl">Rugosidad superficial</span>  <span class="rv" id="rr-rug">—</span></div>
          <div class="rrow"><span class="rl">Puntos de control</span>     <span class="rv" id="rr-pts">—</span></div>
        </div>
        <div class="rcard">
          <div class="rcard-title">📋 Identificación del proyecto</div>
          <div class="rrow"><span class="rl">Proyecto</span>       <span class="rv" id="rr-nom">—</span></div>
          <div class="rrow"><span class="rl">Cliente</span>         <span class="rv" id="rr-cli">—</span></div>
          <div class="rrow"><span class="rl">Municipio</span>       <span class="rv" id="rr-mun">—</span></div>
          <div class="rrow"><span class="rl">Tipo obra</span>       <span class="rv" id="rr-tip">—</span></div>
          <div class="rrow"><span class="rl">Factor complejidad</span>  <span class="rv" id="rr-fac">—</span></div>
        </div>
      </div>
      <table class="res-table">
        <thead><tr><th>Cód.</th><th>Descripción</th><th>Un.</th><th class="tr">Cant.</th><th class="tr">Tarifa</th><th class="tr">Total</th></tr></thead>
        <tbody id="rBody"></tbody>
      </table>
      <!-- Gráfico capítulos -->
      <div class="cap-chart" id="capChart" style="display:none;">
        <div class="cap-chart-title">Distribución del presupuesto por capítulo</div>
        <div id="capChartRows"></div>
      </div>

      <div class="res-grand">
        <div>
          <div class="rg-lbl">Total estimado incl. AIU</div>
          <div class="rg-alt" id="rAlt">—</div>
        </div>
        <div class="rg-val" id="rTot">—</div>
      </div>
      <div id="resProd" style="margin-bottom:16px;display:none;"></div>
      <div class="notas">
        <div class="notas-t">⚖️ Alcances y notas legales</div>
        <ul>
          <li>Tarifas de referencia INVIAS / Gobernación Norte de Santander · vigencia 2024</li>
          <li>Estimación preliminar. No constituye oferta contractual ni propuesta comercial.</li>
          <li>No incluye IVA (19%). El AIU del contratista se estima entre 25% y 35% adicional.</li>
          <li>Volumen calculado por fórmula prismoide · Área por fórmula de Gauss-Shoelace.</li>
          <li>Factor de complejidad aplica sobre todos los ítems de excavación y cunetas.</li>
          <li id="rObs" style="display:none;"></li>
        </ul>
      </div>
    </div><!-- /mpane-res -->

    <!-- PRODUCTOS -->
    <div class="mpane" id="mpane-prod">
      <div style="padding:16px 18px;overflow-y:auto;height:100%;scrollbar-width:thin;scrollbar-color:var(--mut2) transparent;" id="mpProd"></div>
    </div>

  </div><!-- /mpanes -->
</div><!-- /main -->
</div><!-- /app -->
<div id="toast">
  <span class="toast-ico" id="toastIco">✓</span>
  <div class="toast-body">
    <div class="toast-title" id="toastTitle"></div>
    <div class="toast-sub"   id="toastSub"></div>
    <div class="toast-prog"  id="toastProg" style="display:none"><div class="toast-prog-bar" id="toastProgBar"></div></div>
  </div>
</div>

<?php if ($proyDB): ?>
<script>
/* Datos del proyecto cargado desde DB — disponibles antes del init */
window.__FYLCAD_DB__ = <?= json_encode([
    'id'     => $proyDB['id'],
    'nombre' => $proyDB['nombre'],
    'csv'    => $proyDB['csv'],
    'meta'   => $proyDB['meta'],
]) ?>;
</script>
<?php endif; ?>

<script>
'use strict';
/* ═══════════════════════════════════════════════════════════════
   FYLCAD — cotizacion.js v3
   Fuente de datos (prioridad):
   1. window.__FYLCAD_DB__  → viene de ?proyecto=ID (cargado desde DB)
   2. localStorage          → viene de proyecto.php abierto en otra tab
   3. Nada → mostrar pantalla "sin datos"
═══════════════════════════════════════════════════════════════ */

/* ── Estado global ── */
let M    = null;    // métricas del levantamiento
let PTS  = [];      // puntos [{x,y,z}]
let TRIS = [];      // triángulos Delaunay [{a,b,c}]
let NIV  = [];      // valores Z de curvas de nivel
let ISO  = {};      // segmentos de curvas {z: [[p0,p1]...]}
let ZONA = null;    // {area,perim,vol,n} zona seleccionada (null = todo el terreno)
let ZPOLY = [];     // vértices polígono en construcción (coords mundo)
let ZCLOSED = false;
let MONEDA = 'COP';
let FAC = 1.0;
const TRM = 4200;
let SEL = {};       // productos/maquinaria seleccionados

/* Canvas */
const CVS = document.getElementById('cvs');
const CTX = CVS.getContext('2d');
let ZOOM=1, PX=0, PY=0, CX=0, CY=0, ZMIN=0, ZMAX=1, SCL=1;
let DRAG = {on:false, x:0, y:0};
let TOOL = 'pan';
const OPT = {hipso:true, curvas:true, puntos:false};
let HOVER_PT = null;   // punto bajo el cursor al dibujar
let _APU = null;       // resultado último cálculo APU

/* ── Helpers ── */
const $ = id => document.getElementById(id);
const set = (id, v, anim=false) => {
  const e=$(id);
  if(!e) return;
  if(anim && e.textContent !== v) {
    e.classList.remove('updated');
    void e.offsetWidth;
    e.classList.add('updated');
  }
  e.textContent=v;
};
const g = id => parseFloat($(id)?.value) || 0;
const fN = v => {
  if(v==null||isNaN(v)) return '—';
  return v>=1e6 ? (v/1e6).toFixed(2)+'M' : v>=1e3 ? (v/1e3).toFixed(1)+'k' : Math.round(v).toLocaleString('es-CO');
};
const fM = v => {
  if(!v && v!==0) return '—';
  const c = Math.round(v);
  if(MONEDA==='USD') return '$'+(c/TRM).toFixed(0)+' USD';
  return c>=1e6 ? '$'+(c/1e6).toFixed(2)+'M' : '$'+c.toLocaleString('es-CO');
};
let _toastT = null;
function toast(msg, opts={}) {
  // opts: { sub, ico, type:'ok'|'err'|'warn'|'info', dur:ms, progress:bool }
  const t    = $('toast');
  const ico  = $('toastIco');
  const titl = $('toastTitle');
  const sub  = $('toastSub');
  const prog = $('toastProg');
  const bar  = $('toastProgBar');

  const type = opts.type || (opts.err ? 'err' : 'ok');
  const dur  = opts.dur  || 3200;
  const icons = {ok:'✓', err:'✕', warn:'⚠️', info:'ℹ️', save:'💾', calc:'⚡'};

  titl.textContent = msg;
  sub.textContent  = opts.sub  || '';
  sub.style.display = opts.sub ? 'block' : 'none';
  ico.textContent  = opts.ico  || icons[type] || '✓';

  if (opts.progress) {
    prog.style.display = 'block';
    bar.style.transition = 'none';
    bar.style.width = '0%';
    requestAnimationFrame(() => {
      bar.style.transition = `width ${dur}ms linear`;
      bar.style.width = '100%';
    });
  } else {
    prog.style.display = 'none';
  }

  t.className = 'on ' + type;
  clearTimeout(_toastT);
  _toastT = setTimeout(() => { t.className = t.className.replace('on','').trim(); }, dur);
}

/* ── Animador de valores numéricos ── */
function animateValue(el, start, end, duration=600, formatter=v=>v) {
  if (!el) return;
  const startTime = performance.now();
  const range = end - start;
  function step(now) {
    const progress = Math.min((now - startTime) / duration, 1);
    const ease = 1 - Math.pow(1 - progress, 3); // ease out cubic
    const current = start + range * ease;
    el.textContent = formatter(current);
    if (progress < 1) requestAnimationFrame(step);
    else el.textContent = formatter(end);
  }
  requestAnimationFrame(step);
}

/* ── Flip animado para un elemento ── */
function flipEl(el) {
  if (!el) return;
  el.classList.remove('updated');
  void el.offsetWidth;
  el.classList.add('updated');
}

/* ── Hipsométrico ── */
const HS=[[0,[195,230,170]],[.2,[220,242,155]],[.4,[250,230,100]],[.6,[230,175,55]],[.8,[190,125,45]],[1,[148,88,38]]];
function hc(t,a=1){
  let i=0; while(i<HS.length-1&&HS[i+1][0]<t) i++;
  const lo=HS[i], hi=HS[Math.min(i+1,HS.length-1)];
  const f=lo[0]===hi[0]?0:(t-lo[0])/(hi[0]-lo[0]);
  return `rgba(${~~(lo[1][0]+(hi[1][0]-lo[1][0])*f)},${~~(lo[1][1]+(hi[1][1]-lo[1][1])*f)},${~~(lo[1][2]+(hi[1][2]-lo[1][2])*f)},${a})`;
}
const tn = z => ZMAX>ZMIN ? (z-ZMIN)/(ZMAX-ZMIN) : 0;

/* ── Delaunay simple ── */
function inCircum(ax,ay,bx,by,cx,cy,px,py){
  const D=2*(ax*(by-cy)+bx*(cy-ay)+cx*(ay-by));
  if(Math.abs(D)<1e-10)return false;
  const ux=((ax*ax+ay*ay)*(by-cy)+(bx*bx+by*by)*(cy-ay)+(cx*cx+cy*cy)*(ay-by))/D;
  const uy=((ax*ax+ay*ay)*(cx-bx)+(bx*bx+by*by)*(ax-cx)+(cx*cx+cy*cy)*(bx-ax))/D;
  return Math.hypot(px-ux,py-uy)<Math.hypot(ax-ux,ay-uy)+1e-8;
}
function delaunay(pts){
  const n=pts.length; if(n<3)return[];
  let x0=Infinity,y0=Infinity,x1=-Infinity,y1=-Infinity;
  for(const p of pts){x0=Math.min(x0,p.x);y0=Math.min(y0,p.y);x1=Math.max(x1,p.x);y1=Math.max(y1,p.y);}
  const dm=Math.max(x1-x0,y1-y0)*3,mx=(x0+x1)/2,my=(y0+y1)/2;
  const sup=[{x:mx-dm*2,y:my-dm},{x:mx,y:my+dm*2},{x:mx+dm*2,y:my-dm}];
  const all=[...pts,...sup];
  let tris=[{a:n,b:n+1,c:n+2}];
  for(let i=0;i<n;i++){
    const p=all[i], bad=[];
    for(const t of tris){
      const a=all[t.a],b=all[t.b],c=all[t.c];
      if(inCircum(a.x,a.y,b.x,b.y,c.x,c.y,p.x,p.y)) bad.push(t);
    }
    const poly=[];
    for(const t of bad) for(const[e0,e1]of[[t.a,t.b],[t.b,t.c],[t.c,t.a]])
      if(!bad.some(u=>u!==t&&((u.a===e0||u.b===e0||u.c===e0)&&(u.a===e1||u.b===e1||u.c===e1)))) poly.push([e0,e1]);
    tris=tris.filter(t=>!bad.includes(t));
    for(const e of poly) tris.push({a:e[0],b:e[1],c:i});
  }
  return tris.filter(t=>t.a<n&&t.b<n&&t.c<n);
}

/* ── Curvas de nivel ── */
function buildIso(){
  ISO={};
  for(const z of NIV){
    const segs=[];
    for(const t of TRIS){
      const v=[PTS[t.a],PTS[t.b],PTS[t.c]], cr=[];
      for(const[i,j]of[[0,1],[1,2],[2,0]]){
        const a=v[i],b=v[j];
        if((a.z<z&&b.z>=z)||(b.z<z&&a.z>=z)){
          const f=(z-a.z)/(b.z-a.z);
          cr.push({x:a.x+(b.x-a.x)*f, y:a.y+(b.y-a.y)*f});
        }
      }
      if(cr.length===2) segs.push(cr);
    }
    ISO[z]=segs;
  }
}

/* ── Proyección canvas ── */
function proj(p){ return {sx:CVS.width/2+(p.x-CX)*SCL*ZOOM+PX, sy:CVS.height/2-(p.y-CY)*SCL*ZOOM+PY}; }
function unproj(sx,sy){ return {x:CX+(sx-CVS.width/2-PX)/(SCL*ZOOM), y:CY-(sy-CVS.height/2-PY)/(SCL*ZOOM)}; }

/* ── RENDER ── */
function draw(){
  if(!CVS.width||!CVS.height){
    const W=CVS.offsetWidth, H=CVS.offsetHeight;
    if(W>10&&H>10){ CVS.width=W; CVS.height=H; }
    else return;
  }
  CTX.clearRect(0,0,CVS.width,CVS.height);
  CTX.fillStyle='#F4F0E6'; CTX.fillRect(0,0,CVS.width,CVS.height);

  if(!PTS.length){
    CTX.fillStyle='#94a3b8'; CTX.font="13px 'DM Sans',sans-serif"; CTX.textAlign='center';
    CTX.fillText('Cargando datos del levantamiento...', CVS.width/2, CVS.height/2-10);
    CTX.font="11px 'DM Mono',monospace"; CTX.fillStyle='#64748b';
    CTX.fillText(window.__FYLCAD_DB__ ? 'Procesando CSV...' : 'Abre desde proyecto.php o mis_proyectos.php', CVS.width/2, CVS.height/2+14);
    CTX.textAlign='left'; return;
  }

  drawGrid();

  /* Hipsométrico TIN */
  if(OPT.hipso && TRIS.length){
    for(const t of TRIS){
      const a=PTS[t.a],b=PTS[t.b],c=PTS[t.c]; if(!a||!b||!c) continue;
      const pa=proj(a),pb=proj(b),pc=proj(c);
      CTX.beginPath(); CTX.moveTo(pa.sx,pa.sy); CTX.lineTo(pb.sx,pb.sy); CTX.lineTo(pc.sx,pc.sy); CTX.closePath();
      CTX.fillStyle=hc(tn((a.z+b.z+c.z)/3), .88); CTX.fill();
    }
    /* Sombreado */
    CTX.globalAlpha=.1;
    for(const t of TRIS){
      const a=PTS[t.a],b=PTS[t.b],c=PTS[t.c]; if(!a||!b||!c) continue;
      const ux=b.x-a.x,uy=b.y-a.y,uz=b.z-a.z, vx=c.x-a.x,vy=c.y-a.y,vz=c.z-a.z;
      const nx=uy*vz-uz*vy, ny=uz*vx-ux*vz, nz=ux*vy-uy*vx, nm=Math.hypot(nx,ny,nz)||1;
      const sh=Math.max(0,((-nx+ny)*.5+nz*.8)/nm);
      const pa=proj(a),pb=proj(b),pc=proj(c);
      CTX.beginPath(); CTX.moveTo(pa.sx,pa.sy); CTX.lineTo(pb.sx,pb.sy); CTX.lineTo(pc.sx,pc.sy); CTX.closePath();
      CTX.fillStyle=sh>.5?`rgba(255,255,230,${(sh-.4)*.45})`:`rgba(0,0,0,${(.5-sh)*.3})`; CTX.fill();
    }
    CTX.globalAlpha=1;
  }

  /* TIN sutil */
  CTX.strokeStyle='rgba(0,0,100,.045)'; CTX.lineWidth=.3;
  for(const t of TRIS){
    const a=PTS[t.a],b=PTS[t.b],c=PTS[t.c]; if(!a||!b||!c) continue;
    const pa=proj(a),pb=proj(b),pc=proj(c);
    CTX.beginPath(); CTX.moveTo(pa.sx,pa.sy); CTX.lineTo(pb.sx,pb.sy); CTX.lineTo(pc.sx,pc.sy); CTX.closePath(); CTX.stroke();
  }

  /* Curvas de nivel */
  if(OPT.curvas){
    for(const[zk,segs] of Object.entries(ISO)){
      const z=+zk, mae=Math.round(z*10)%50===0;
      CTX.strokeStyle=mae?'rgba(139,90,43,.65)':'rgba(139,90,43,.28)';
      CTX.lineWidth=mae?1.1:.5;
      for(const s of segs){
        const p0=proj(s[0]),p1=proj(s[1]);
        CTX.beginPath(); CTX.moveTo(p0.sx,p0.sy); CTX.lineTo(p1.sx,p1.sy); CTX.stroke();
      }
    }
  }

  /* Puntos */
  if(OPT.puntos){
    CTX.fillStyle='rgba(0,0,128,.5)';
    for(const p of PTS){ const pp=proj(p); CTX.beginPath(); CTX.arc(pp.sx,pp.sy,1.4,0,Math.PI*2); CTX.fill(); }
  }

  /* Zona seleccionada */
  if(ZPOLY.length){
    const sp=ZPOLY.map(p=>proj(p));
    CTX.beginPath(); CTX.moveTo(sp[0].sx,sp[0].sy);
    for(let i=1;i<sp.length;i++) CTX.lineTo(sp[i].sx,sp[i].sy);
    if(ZCLOSED){ CTX.closePath(); CTX.fillStyle='rgba(0,229,192,.13)'; CTX.fill(); }
    CTX.strokeStyle='#00e5c0'; CTX.lineWidth=2;
    CTX.setLineDash(ZCLOSED?[]:[7,4]); CTX.stroke(); CTX.setLineDash([]);
    for(const p of sp){
      CTX.beginPath(); CTX.arc(p.sx,p.sy,5,0,Math.PI*2);
      CTX.fillStyle='#00e5c0'; CTX.fill();
      CTX.strokeStyle='rgba(255,255,255,.8)'; CTX.lineWidth=1.5; CTX.stroke();
    }
    /* Línea al cursor mientras se dibuja */
    if(TOOL==='draw' && !ZCLOSED && HOVER_PT){
      const last=sp[sp.length-1];
      CTX.strokeStyle='rgba(0,229,192,.5)'; CTX.lineWidth=1.5; CTX.setLineDash([4,4]);
      CTX.beginPath(); CTX.moveTo(last.sx,last.sy); CTX.lineTo(HOVER_PT.sx,HOVER_PT.sy); CTX.stroke();
      CTX.setLineDash([]);
    }
  }

  drawGrid_labels();
  drawScaleBar();
  $('zLbl').textContent=Math.round(ZOOM*100)+'%';
}

function drawGrid(){
  const mp=SCL*ZOOM, steps=[1,2,5,10,20,25,50,100,200,500,1000];
  const paso=steps.find(s=>s*mp>=60)||1000, pp=paso*mp;
  const ox=CVS.width/2+PX, oy=CVS.height/2+PY;
  CTX.strokeStyle='rgba(100,130,180,.09)'; CTX.lineWidth=.4;
  for(let x=((ox%pp)+pp)%pp;x<CVS.width;x+=pp){ CTX.beginPath(); CTX.moveTo(x,0); CTX.lineTo(x,CVS.height); CTX.stroke(); }
  for(let y=((oy%pp)+pp)%pp;y<CVS.height;y+=pp){ CTX.beginPath(); CTX.moveTo(0,y); CTX.lineTo(CVS.width,y); CTX.stroke(); }
}

function drawGrid_labels(){
  /* coords en las líneas maestras — solo si ZOOM razonable */
  if(!PTS.length || ZOOM<.3) return;
  const mp=SCL*ZOOM, steps=[1,2,5,10,20,25,50,100,200,500,1000];
  const paso=steps.find(s=>s*mp>=60)||1000, pp=paso*mp;
  const ox=CVS.width/2+PX, oy=CVS.height/2+PY;
  CTX.fillStyle='rgba(100,130,180,.55)'; CTX.font="8px 'DM Mono',monospace";
  for(let x=((ox%pp)+pp)%pp;x<CVS.width;x+=pp){
    const wx=CX+(x-CVS.width/2-PX)/(SCL*ZOOM);
    CTX.fillText(wx.toFixed(0),x+2,10);
  }
  for(let y=((oy%pp)+pp)%pp;y<CVS.height;y+=pp){
    const wy=CY-(y-CVS.height/2-PY)/(SCL*ZOOM);
    CTX.fillText(wy.toFixed(0),2,y-2);
  }
}

function drawScaleBar(){
  const mp=SCL*ZOOM, steps=[1,2,5,10,20,50,100,200,500,1000];
  const bm=steps.find(s=>s*mp>=60)||1000, bp=bm*mp;
  const x=12, y=CVS.height-18;
  CTX.fillStyle='rgba(0,0,0,.45)'; CTX.fillRect(x-2,y-12,bp+4,15);
  CTX.fillStyle='rgba(255,255,255,.8)'; CTX.fillRect(x,y-10,bp,2);
  CTX.font="9px 'DM Mono',monospace"; CTX.textAlign='center'; CTX.fillStyle='#fff';
  CTX.fillText(bm+'m', x+bp/2, y); CTX.textAlign='left';
}

function resetView(){
  if(!PTS.length){ ZOOM=1; PX=0; PY=0; draw(); return; }
  const xs=PTS.map(p=>p.x), ys=PTS.map(p=>p.y);
  const x0=Math.min(...xs), x1=Math.max(...xs), y0=Math.min(...ys), y1=Math.max(...ys);
  SCL = Math.max(x1-x0,y1-y0)>0 ? Math.min(CVS.width,CVS.height)*.42/Math.max(x1-x0,y1-y0) : 1;
  ZOOM=1; PX=0; PY=0; draw();
}

function resizeCvs(){
  const p=CVS.parentElement;
  const W=p.clientWidth, H=p.clientHeight;
  if(W<10||H<10) return;
  CVS.width=W; CVS.height=H;
  draw();
}
new ResizeObserver(resizeCvs).observe(CVS.parentElement);

/* ── Eventos canvas ── */
CVS.addEventListener('mousedown', e => {
  const r=CVS.getBoundingClientRect(), sx=e.clientX-r.left, sy=e.clientY-r.top;
  if(TOOL==='draw'){
    if(ZCLOSED) return;
    const wp=unproj(sx,sy);
    /* Si el clic está cerca del primer punto → cerrar */
    if(ZPOLY.length>=3){
      const fp=proj(ZPOLY[0]);
      if(Math.hypot(sx-fp.sx,sy-fp.sy)<14){ ZCLOSED=true; calcZona(); draw(); return; }
    }
    ZPOLY.push(wp); ZCLOSED=false;
    updateZonaUI(); draw();
    $('toolUnd').style.display='';
  } else {
    DRAG={on:true, x:sx, y:sy};
  }
});

CVS.addEventListener('mousemove', e => {
  const r=CVS.getBoundingClientRect(), sx=e.clientX-r.left, sy=e.clientY-r.top;
  const wp=unproj(sx,sy);
  set('psCursor', `X: ${wp.x.toFixed(1)}  Y: ${wp.y.toFixed(1)}`);
  HOVER_PT = {sx,sy};
  if(DRAG.on){ PX+=sx-DRAG.x; PY+=sy-DRAG.y; DRAG.x=sx; DRAG.y=sy; draw(); }
  else if(TOOL==='draw' && ZPOLY.length && !ZCLOSED){ draw(); /* redraw con línea al cursor */ }
  /* Resaltar si estamos cerca del primer vértice */
  if(TOOL==='draw' && ZPOLY.length>=3 && !ZCLOSED){
    const fp=proj(ZPOLY[0]);
    CVS.style.cursor=Math.hypot(sx-fp.sx,sy-fp.sy)<14?'pointer':'crosshair';
  }
});

CVS.addEventListener('mouseup', () => { DRAG.on=false; });

CVS.addEventListener('dblclick', e => {
  if(TOOL==='draw' && ZPOLY.length>=3 && !ZCLOSED){
    /* Doble clic cierra la zona */
    ZCLOSED=true; calcZona(); draw();
    toast('Zona definida', {ico:'✏️', sub:'APU actualizado con zona seleccionada', type:'ok'});
  }
});

CVS.addEventListener('wheel', e => {
  e.preventDefault();
  ZOOM=Math.max(.04, Math.min(80, ZOOM*(e.deltaY<0?1.13:.88)));
  draw();
}, {passive:false});

/* touch básico (móvil) */
let _tc=null;
CVS.addEventListener('touchstart', e=>{ if(e.touches.length===1){ const t=e.touches[0]; _tc={x:t.clientX,y:t.clientY}; }});
CVS.addEventListener('touchmove', e=>{ e.preventDefault(); if(e.touches.length===1&&_tc){ const t=e.touches[0]; PX+=t.clientX-_tc.x; PY+=t.clientY-_tc.y; _tc={x:t.clientX,y:t.clientY}; draw(); }},{passive:false});

/* ── Herramienta ── */
function setTool(t){
  TOOL=t;
  $('toolPan').classList.toggle('on', t==='pan');
  $('toolPan').classList.toggle('draw-active', false);
  $('toolDraw').classList.toggle('on', t==='pan');
  $('toolDraw').classList.toggle('draw-active', t==='draw');
  CVS.className = t==='draw' ? 'draw-mode' : '';
  const hint=$('drawHint');
  if(hint) hint.classList.toggle('show', t==='draw');
  if(t==='draw') toast('Modo dibujo activo', {ico:'✏️', sub:'Clic para colocar vértices · Doble clic para cerrar', type:'info'});
}

function togOpt(k){
  OPT[k]=!OPT[k];
  const m={hipso:'tHipso', curvas:'tCurvas', puntos:'tPts'};
  $(m[k])?.classList.toggle('on', OPT[k]);
  draw();
}

function undoVert(){
  if(ZPOLY.length){ ZPOLY.pop(); ZCLOSED=false; ZONA=null; updateZonaUI(); draw(); recalc(); }
  if(!ZPOLY.length) $('toolUnd').style.display='none';
}

/* ── Zona / selección ── */
function pip(pt, poly){
  let ins=false;
  for(let i=0,j=poly.length-1;i<poly.length;j=i++){
    const xi=poly[i].x,yi=poly[i].y,xj=poly[j].x,yj=poly[j].y;
    if(((yi>pt.y)!==(yj>pt.y))&&(pt.x<(xj-xi)*(pt.y-yi)/(yj-yi)+xi)) ins=!ins;
  }
  return ins;
}

/* ══════════════════════════════════════════════════════════════
   ANÁLISIS MORFOLÓGICO PROFESIONAL
   ══════════════════════════════════════════════════════════════ */

/* Volumen bruto por TIN (suma de prismas sobre plano de referencia)
   V_bruto = Σ (z_a+z_b+z_c)/3 * área_triángulo
   Luego Vexcav = Σ (z_i - z_ref) * área (solo positivos)
         Vrellen = Σ (z_ref - z_i) * área (solo positivos)  */
function volumenTIN(tris, pts, ptsFiltro){
  if(!tris || tris.length===0 || !pts || pts.length===0) return {bruto:0,excav:0,rellen:0};
  const setIn = ptsFiltro ? new Set(ptsFiltro.map((_,i)=>i)) : null;
  // filtrar triángulos cuyos 3 vértices estén dentro de la zona
  const insideSet = ptsFiltro ? new Set(ptsFiltro) : null;
  let bruto=0, excav=0, rellen=0;
  for(const t of tris){
    const a=pts[t.a], b=pts[t.b], c=pts[t.c];
    if(!a||!b||!c) continue;
    // Si hay zona, verificar centroide dentro del polígono
    if(ZCLOSED && ZPOLY.length>=3){
      const cx=(a.x+b.x+c.x)/3, cy=(a.y+b.y+c.y)/3;
      if(!pip({x:cx,y:cy},ZPOLY)) continue;
    }
    // Área del triángulo (producto vectorial)
    const ax=b.x-a.x,ay=b.y-a.y,bx=c.x-a.x,by=c.y-a.y;
    const tArea=Math.abs(ax*by-ay*bx)/2;
    if(tArea<1e-10) continue;
    const zMed=(a.z+b.z+c.z)/3;
    bruto+=zMed*tArea;
    const zRef=Math.min(a.z,b.z,c.z);
    excav+=(zMed-zRef)*tArea;
  }
  return {bruto, excav, rellen:excav*0.35}; // relleno estimado 35% del excav
}

/* Pendiente media ponderada por área de cada triángulo */
function pendientesAnalisis(tris, pts, poly){
  if(!tris||!pts||tris.length===0) return {media:0,max:0,rugosidad:0};
  let sumPend=0, sumArea=0, maxPend=0;
  let sumAreaH=0; // área horizontal (2D)
  for(const t of tris){
    const a=pts[t.a],b=pts[t.b],c=pts[t.c];
    if(!a||!b||!c) continue;
    if(poly && poly.length>=3){
      const cx=(a.x+b.x+c.x)/3,cy=(a.y+b.y+c.y)/3;
      if(!pip({x:cx,y:cy},poly)) continue;
    }
    // Vectores del triángulo
    const u=[b.x-a.x,b.y-a.y,b.z-a.z];
    const v=[c.x-a.x,c.y-a.y,c.z-a.z];
    // Normal = u × v
    const nx=u[1]*v[2]-u[2]*v[1];
    const ny=u[2]*v[0]-u[0]*v[2];
    const nz=u[0]*v[1]-u[1]*v[0];
    const nMag=Math.sqrt(nx*nx+ny*ny+nz*nz);
    if(nMag<1e-10) continue;
    // Pendiente = arctan(mag_horizontal / nz)
    const hMag=Math.sqrt(nx*nx+ny*ny);
    const slope=nMag>0 ? hMag/Math.abs(nz) : 0;   // tangente
    const slopePct=slope*100;
    // Área 2D del triángulo
    const ax2=b.x-a.x,ay2=b.y-a.y,bx2=c.x-a.x,by2=c.y-a.y;
    const tArea2D=Math.abs(ax2*by2-ay2*bx2)/2;
    // Área 3D del triángulo
    const tArea3D=nMag/2;
    sumPend+=slopePct*tArea2D;
    sumArea+=tArea2D;
    sumAreaH+=tArea3D;
    if(slopePct>maxPend) maxPend=slopePct;
  }
  const media=sumArea>0?sumPend/sumArea:0;
  // Rugosidad = razón área 3D / área 2D (1.0 = plano, >1.2 = muy rugoso)
  const rugosidad=sumArea>0?sumAreaH/sumArea:1;
  return {media:Math.min(media,999), max:Math.min(maxPend,999), rugosidad};
}

/* Clasificación INVIAS por pendiente media */
function clasificPendiente(pct){
  if(pct<=3)   return {l:'Plano',         clase:'P', color:'#22c55e'};
  if(pct<=7)   return {l:'Levemente ondulado', clase:'LO', color:'#84cc16'};
  if(pct<=12)  return {l:'Ondulado',      clase:'O',  color:'#eab308'};
  if(pct<=25)  return {l:'Fuertemente ondulado', clase:'FO', color:'#f97316'};
  if(pct<=50)  return {l:'Quebrado',      clase:'Q',  color:'#ef4444'};
  if(pct<=75)  return {l:'Escarpado',     clase:'E',  color:'#dc2626'};
  return                {l:'Muy escarpado',  clase:'ME', color:'#991b1b'};
}

/* Factor de complejidad automático (multi-parámetro) */
function calcFactorAuto(morfo){
  if(!morfo) return {f:1.0, desc:'—', detalle:[]};
  const {media, max, rugosidad, desnivel, area} = morfo;
  let f=1.0;
  const detalle=[];

  // F1: pendiente media
  const fp = media<=3?0:media<=7?.05:media<=12?.15:media<=25?.30:media<=50?.55:.90;
  if(fp>0){f+=fp; detalle.push(`Pendiente media ${media.toFixed(1)}%: +${(fp*100).toFixed(0)}%`);}

  // F2: rugosidad superficial
  const fr = rugosidad<=1.01?0:rugosidad<=1.05?.03:rugosidad<=1.10?.08:rugosidad<=1.20?.15:.25;
  if(fr>0){f+=fr; detalle.push(`Rugosidad ${rugosidad.toFixed(3)}: +${(fr*100).toFixed(0)}%`);}

  // F3: desnivel total
  const dh=desnivel||0;
  const fd = dh<=5?0:dh<=20?.05:dh<=50?.10:dh<=100?.20:.35;
  if(fd>0){f+=fd; detalle.push(`Desnivel ${dh.toFixed(1)} m: +${(fd*100).toFixed(0)}%`);}

  // F4: área (grandes áreas → eficiencia)
  const a=area||0;
  const fa = a>=50000?-0.05:a>=20000?-0.03:a<=500?.10:0;
  if(fa!==0){f+=fa; detalle.push(`Área ${fN(a)} m²: ${fa>0?'+':''}${(fa*100).toFixed(0)}%`);}

  f=Math.min(Math.max(f,0.5),3.0);
  const clP=clasificPendiente(media);
  return {f:parseFloat(f.toFixed(2)), desc:`Auto "${clP.l}" · f=${f.toFixed(2)}`, detalle, clP};
}

function calcZona(){
  if(ZPOLY.length<3||!ZCLOSED) return;
  /* Área 2D (Shoelace / Gauss) */
  let area=0;
  for(let i=0;i<ZPOLY.length;i++){ const j=(i+1)%ZPOLY.length; area+=ZPOLY[i].x*ZPOLY[j].y-ZPOLY[j].x*ZPOLY[i].y; }
  area=Math.abs(area)/2;
  /* Perímetro */
  let perim=0;
  for(let i=0;i<ZPOLY.length;i++){ const j=(i+1)%ZPOLY.length; perim+=Math.hypot(ZPOLY[j].x-ZPOLY[i].x,ZPOLY[j].y-ZPOLY[i].y); }
  /* Puntos dentro */
  const inside=PTS.filter(p=>pip(p,ZPOLY));
  /* Volumen real por TIN (si hay triángulos) */
  const vtIN=volumenTIN(TRIS,PTS,inside);
  let vol=vtIN.excav>0 ? vtIN.excav : area*(Math.max(...(inside.map(p=>p.z)||[0]))-Math.min(...(inside.map(p=>p.z)||[0])))/3;
  if(inside.length===0) vol=0;
  /* Desnivel local */
  const zs=inside.map(p=>p.z);
  const desnivel=zs.length>1 ? Math.max(...zs)-Math.min(...zs) : (M.desnivel||0);
  /* Análisis morfológico */
  const pend=pendientesAnalisis(TRIS,PTS,ZPOLY);
  const morfo={media:pend.media, max:pend.max, rugosidad:pend.rugosidad, desnivel, area};
  /* Factor automático recalculado para esta zona */
  const facAuto=calcFactorAuto(morfo);
  ZONA={area,perim,vol,n:inside.length,desnivel,pend,facAuto,vtIN};
  /* Actualizar factor automáticamente si está en modo auto */
  if($('facModo')?.value==='auto'){
    FAC=facAuto.f;
    $('factor').value=FAC.toFixed(2);
    $('factorSub').textContent=facAuto.desc;
    renderFactorDetalle(facAuto);
  }
  updateZonaUI(); recalc();
}

function updateZonaUI(){
  const src=ZONA || (M?{area:M.area,perim:M.perimetro,vol:M.volumen,n:M.n}:null);
  if(!src){ ['zs-a','zs-v','zs-p','zs-n'].forEach(id=>set(id,'—')); return; }
  set('zs-a', fN(src.area)+' m²');
  set('zs-v', fN(src.vol??src.volumen)+' m³');
  set('zs-p', fN(src.perim??src.perimetro)+' m');
  set('zs-n', (src.n||M?.n||0).toLocaleString('es-CO'));
  /* Overlay canvas */
  const ov=$('zonaOv');
  if(ZONA&&ZCLOSED){
    ov.style.display='block';
    set('ov-a', fN(ZONA.area)+' m²');
    set('ov-n', ZONA.n.toLocaleString('es-CO'));
    set('ov-v', fN(ZONA.vol)+' m³');
    set('ov-p', fN(ZONA.perim)+' m');
    $('zonaBadge').textContent='Zona personalizada'; $('zonaBadge').className='sec-badge ok';
    $('btnClearZona').style.display='';
    set('psZona', `Zona: ${fN(ZONA.area)} m² · ${ZONA.n} pts`);
  } else {
    ov.style.display='none';
    $('zonaBadge').textContent='Terreno completo'; $('zonaBadge').className='sec-badge';
    $('btnClearZona').style.display='none';
    set('psZona','—');
  }
}

function clearZona(){
  ZPOLY=[]; ZCLOSED=false; ZONA=null;
  $('toolUnd').style.display='none';
  updateZonaUI(); draw(); recalc();
  toast('Zona eliminada', {ico:'✕', sub:'Usando terreno completo', type:'warn'});
}

/* ── APU: cálculo en tiempo real ── */
function recalc(){
  if(!M) return;
  FAC=parseFloat($('factor')?.value)||1;
  const area  = ZONA ? ZONA.area   : M.area;
  const perim = ZONA ? ZONA.perim  : M.perimetro;
  const volRaw= ZONA ? ZONA.vol    : M.volumen;

  // Desglose volumétrico (porcentajes editables o fijos)
  const pRoc  = (parseFloat($('pct-roc')?.value)||20)/100;   // % roca
  const pRel  = (parseFloat($('pct-rel')?.value)||35)/100;   // % relleno
  const pTra  = 1 - pRel;                                    // % transporte = 100% - relleno

  const volExc = volRaw;
  const volRoc = volExc * pRoc;
  const volTie = volExc * (1-pRoc);
  const volRel = volExc * pRel;
  const volTra = volExc * pTra;
  const aRev   = area   * 0.30;
  const pCun   = perim  * 0.40;

  // Mostrar cantidades en panel
  const setC = (id,v,un)=>{const e=$(id);if(e)e.textContent=`× ${fN(v)} ${un}`;};
  setC('c-a1',area,'m²'); setC('c-a2',area,'m²'); setC('c-a3',area,'m²');
  setC('c-a4',area,'m²'); setC('c-a5',aRev,'m²');
  setC('c-p1',perim,'m'); setC('c-p2',pCun,'m');
  setC('c-v1',volTie,'m³'); setC('c-v2',volRoc,'m³');
  setC('c-v3',volRel,'m³'); setC('c-v4',volTra,'m³');

  // ── Subtotales por ítem ──
  const sRep=area*g('t-rep'), sCer=perim*g('t-cer'), sSen=area*g('t-sen');
  const cap1=sRep+sCer+sSen;

  const sDes=area*g('t-des');
  const sTie=volTie*g('t-tie')*FAC;
  const sRoc=volRoc*g('t-roc')*FAC;
  const sRel=volRel*g('t-rel')*FAC;
  const sNiv=area*g('t-niv');
  const sTra=volTra*g('t-tra');
  const cap2=sDes+sTie+sRoc+sRel+sNiv+sTra;

  const sCun=pCun*g('t-cun')*FAC;
  const sRev=aRev*g('t-rev');
  const xCant=parseFloat($('xCant')?.value)||0;
  const sExt=xCant*g('t-ext');
  const cap3=sCun+sRev+sExt;

  const dir=cap1+cap2+cap3;

  // AIU desglosado (A+I+U)
  const pctA=parseFloat($('pct-a')?.value)||15;
  const pctI=parseFloat($('pct-i')?.value)||5;
  const pctU=parseFloat($('pct-u')?.value)||10;
  const pctAIU=(pctA+pctI+pctU)/100;
  const sA=dir*(pctA/100), sI=dir*(pctI/100), sU=dir*(pctU/100);
  const aiu=sA+sI+sU;
  const tot=dir+aiu;

  // Actualizar valores en DOM
  set('s-rep',fM(sRep)); set('s-cer',fM(sCer)); set('s-sen',fM(sSen));
  set('s-des',fM(sDes)); set('s-tie',fM(sTie)); set('s-roc',fM(sRoc));
  set('s-rel',fM(sRel)); set('s-niv',fM(sNiv)); set('s-tra',fM(sTra));
  set('s-cun',fM(sCun)); set('s-rev',fM(sRev)); set('s-ext',fM(sExt));
  set('t1',fM(cap1),true); set('t2',fM(cap2),true); set('t3',fM(cap3),true);

  // ── Totbar con animación flip ──
  ['b1','b2','b3','bAIU'].forEach(id => {
    const el = $(id);
    if(el) { el.classList.remove('updated'); void el.offsetWidth; el.classList.add('updated'); }
  });
  set('b1',fM(cap1)); set('b2',fM(cap2)); set('b3',fM(cap3));
  set('bAIU',fM(aiu));

  // Total con animación especial
  const totEl = $('bTOT');
  if(totEl) {
    totEl.classList.remove('updated'); void totEl.offsetWidth; totEl.classList.add('updated');
    totEl.textContent = fM(tot);
    const th = $('totalHighlight');
    if(th){ th.classList.remove('glow'); void th.offsetWidth; th.classList.add('glow'); }
  }

  set('bAIUpct',`${pctA}+${pctI}+${pctU}%`);
  const barPct=$('bAIUpctBar');
  if(barPct) barPct.textContent=`${pctA+pctI+pctU}%`;
  const rlbl=$('pct-roc-lbl'); if(rlbl) rlbl.textContent=`(${(pRoc*100).toFixed(0)}%)`;
  const rllbl=$('pct-rel-lbl'); if(rllbl) rllbl.textContent=`(${(pRel*100).toFixed(0)}%)`;
  const tlbl=$('pct-tra-lbl'); if(tlbl) tlbl.textContent=`${((1-pRel)*100).toFixed(0)}%`;
  const tlbl2=$('pct-tra-lbl2'); if(tlbl2) tlbl2.textContent=`(${((1-pRel)*100).toFixed(0)}%)`;

  // ── Flash totbar ──
  const totbar = $('totbar');
  if(totbar){ totbar.classList.remove('recalculating'); void totbar.offsetWidth; totbar.classList.add('recalculating'); }

  // ── Mostrar botón guardar ──
  const btnG = $('btnGuardarCot');
  if(btnG) { btnG.style.display='flex'; btnG.className='btn-guardar-cot'; }
  set('btnGuardarTxt','Guardar cotización');

  // ── ai-sub colores según valor ──
  ['s-rep','s-cer','s-sen','s-des','s-tie','s-roc','s-rel','s-niv','s-tra','s-cun','s-rev','s-ext'].forEach(id=>{
    const el=$(id);
    if(!el) return;
    el.classList.remove('updated','zero','high');
    void el.offsetWidth;
    el.classList.add('updated');
    const v = parseFloat(el.textContent.replace(/[^0-9.-]/g,''));
    if(isNaN(v)||v===0) el.classList.add('zero');
    else if(v>tot*0.25) el.classList.add('high');
  });

  // ── Rendimientos y plazo ──
  const rendExcav = 250/FAC;
  const rendNiv   = 8000/FAC;
  const rendRel   = 200/FAC;
  const dExcav  = volTie>0 ? Math.ceil(volTie/rendExcav) : 0;
  const dNiv    = area>0   ? Math.ceil(area/rendNiv)     : 0;
  const dRel    = volRel>0 ? Math.ceil(volRel/rendRel)   : 0;
  const diasMin = Math.max(dExcav, dNiv, dRel, 1);
  const diasMax = Math.ceil(diasMin*1.3);
  set('r-plazo', diasMin===diasMax?`~${diasMin} días`:`${diasMin}–${diasMax} días`);
  set('r-rend-exc',`${fN(rendExcav)} m³/día`);
  set('r-rend-niv',`${fN(rendNiv)} m²/día`);

  // ── KPI de costos ──
  set('r-c-m2', area>0?fM(tot/area)+'/m²':'—');
  set('r-c-m3', volRaw>0?fM(tot/volRaw)+'/m³':'—');
  set('r-pct-dir',`${(dir/tot*100).toFixed(1)}%`);
  set('r-pct-aiu',`${(aiu/tot*100).toFixed(1)}%`);

  // ── Gráfico de barras capítulos ──
  renderCapChart({cap1,cap2,cap3,aiu,tot});

  _APU={area,perim,vol:volRaw,volExc,volRoc,volTie,volRel,volTra,aRev,pCun,
    cap1,cap2,cap3,dir,aiu,tot,pctA,pctI,pctU,pctAIU,
    sRep,sCer,sSen,sDes,sTie,sRoc,sRel,sNiv,sTra,sCun,sRev,sExt,xCant,
    pRoc:pRoc*100,pRel:pRel*100,
    diasMin,diasMax};
}

/* ── Gráfico de barras de capítulos ── */
function renderCapChart({cap1,cap2,cap3,aiu,tot}) {
  const cont = $('capChartRows');
  if(!cont || tot<=0) return;
  $('capChart').style.display = 'block';

  const items = [
    {label:'Cap.01 Preliminares',     val:cap1, color:'#00e5c0'},
    {label:'Cap.02 Mov. de tierras',  val:cap2, color:'#3b82f6'},
    {label:'Cap.03 Complementarias',  val:cap3, color:'#a855f7'},
    {label:'AIU (Adm+Impr+Util)',     val:aiu,  color:'#818cf8'},
  ];

  cont.innerHTML = items.map(it => {
    const pct = (it.val/tot*100).toFixed(1);
    return `<div class="cap-bar-row">
      <span class="cap-bar-label">${it.label}</span>
      <div class="cap-bar-track">
        <div class="cap-bar-fill" style="background:${it.color};width:0%;--w:${pct}%"></div>
      </div>
      <span class="cap-bar-val">${fM(it.val)} <span style="color:var(--mut);font-size:9px">${pct}%</span></span>
    </div>`;
  }).join('');

  // Animar barras tras render
  requestAnimationFrame(()=>{
    cont.querySelectorAll('.cap-bar-fill').forEach((el,i)=>{
      setTimeout(()=>{ el.style.width = el.style.getPropertyValue('--w') || el.style['--w']; }, i*80);
    });
  });
}

/* ── Clasificación terreno (INVIAS 2013) ── */
function clasif(m){
  const pend = m.pendMedia || 0;
  const cl = clasificPendiente(pend);
  const factores={P:1.00,LO:1.08,O:1.20,FO:1.40,Q:1.65,E:2.00,ME:2.50};
  return{...cl, f: factores[cl.clase]||1.0};
}

/* ── Render detalle factor ── */
function renderFactorDetalle(facAuto){
  const el=$('facDetalle');
  if(!el||!facAuto?.detalle?.length){ if(el) el.innerHTML=''; return; }
  el.innerHTML=facAuto.detalle.map(d=>`<div class="fac-row"><span class="fac-ico">▸</span><span>${d}</span></div>`).join('');
}

/* ── Moneda ── */
function setMoneda(m){
  MONEDA=m;
  $('bCOP').classList.toggle('on',m==='COP');
  $('bUSD').classList.toggle('on',m==='USD');
  recalc();
}

/* ── Tabs main ── */
document.querySelectorAll('.mtab').forEach(t=>t.addEventListener('click',()=>{
  document.querySelectorAll('.mtab').forEach(x=>x.classList.remove('on'));
  document.querySelectorAll('.mpane').forEach(x=>x.classList.remove('on'));
  t.classList.add('on');
  $('mpane-'+t.dataset.m)?.classList.add('on');
  if(t.dataset.m==='plano') setTimeout(()=>{ resizeCvs(); draw(); },30);
  if(t.dataset.m==='res')   genResumen();
  if(t.dataset.m==='prod')  renderProd($('mpProd'));
}));
function goTab(id){ document.querySelector(`[data-m="${id}"]`)?.click(); }
function goTabSim(){ goTab('plano'); }

/* ── Tabs sidebar ── */
document.querySelectorAll('.stab').forEach(t=>t.addEventListener('click',()=>{
  document.querySelectorAll('.stab').forEach(x=>x.classList.remove('on'));
  document.querySelectorAll('.spane').forEach(x=>x.classList.remove('on'));
  t.classList.add('on'); $('sp-'+t.dataset.s)?.classList.add('on');
}));

/* ── Zona buttons ── */
document.querySelectorAll('.zbtn').forEach(b=>b.addEventListener('click',()=>{
  document.querySelectorAll('.zbtn').forEach(x=>x.classList.remove('on'));
  b.classList.add('on');
  const z=b.dataset.z;
  if(z==='all'){ clearZona(); setTool('pan'); }
  else if(z==='draw'){ clearZona(); setTool('draw'); goTab('plano'); toast('Modo dibujo', {ico:'✏️', sub:'Clic en el plano para colocar vértices', type:'info'}); }
  else if(z==='clear'){ clearZona(); document.querySelector('.zbtn[data-z="all"]')?.classList.add('on'); }
}));

/* ── Inputs APU ── */
document.querySelectorAll('.ai-inp,#factor,#xCant,#pct-roc,#pct-rel,#pct-a,#pct-i,#pct-u').forEach(e=>e.addEventListener('input',recalc));

/* ── Resumen PDF ── */
function genResumen(){
  if(!M||!_APU) return;
  const A=_APU;
  // Datos morfológicos de la zona activa
  const morfo = ZONA?.pend || {media:M.pendMedia||0, max:0, rugosidad:1};
  const desnivel = ZONA?.desnivel ?? M.desnivel ?? 0;
  const cl = clasificPendiente(morfo.media);
  const facInfo = ZONA?.facAuto || calcFactorAuto({...morfo, desnivel, area:A.area});
  const fecha=new Date().toLocaleDateString('es-CO',{day:'2-digit',month:'long',year:'numeric'});

  set('rNom', $('iNom').value||'PRESUPUESTO DE OBRA');
  set('rCli', $('iCli').value||'—');
  set('rFec','Fecha: '+fecha);

  // Panel levantamiento
  set('rr-area', fN(A.area)+' m²');
  set('rr-per',  fN(A.perim)+' m');
  set('rr-vol',  fN(A.vol)+' m³');
  set('rr-des',  desnivel.toFixed(2)+' m');
  set('rr-cla',  cl.l+' ('+cl.clase+')');
  set('rr-pend', morfo.media.toFixed(1)+'%'+' (max '+morfo.max.toFixed(0)+'%)');
  set('rr-rug',  morfo.rugosidad.toFixed(3)+' — '+(morfo.rugosidad<1.02?'Suave':morfo.rugosidad<1.08?'Moderada':'Rugosa'));

  // Panel identificación
  set('rr-nom',  $('iNom').value||'—');
  set('rr-cli',  $('iCli').value||'—');
  set('rr-mun',  $('iMun').value||'—');
  set('rr-tip',  $('iTipo').options[$('iTipo').selectedIndex]?.text||'—');
  set('rr-fac',  FAC.toFixed(2)+' — '+facInfo.clP?.l);
  set('rr-pts',  (ZONA?ZONA.n:M.n||0).toLocaleString('es-CO')+' puntos GPS');

  const obs=$('iObs').value, ro=$('rObs');
  if(obs){ ro.style.display='list-item'; ro.textContent='Obs: '+obs; } else ro.style.display='none';

  const rows=[
    ['01','OB-01-01','Localización y replanteo','m²',A.area,g('t-rep'),A.sRep],
    ['01','OB-01-02','Cerramiento provisional','m.l.',A.perim,g('t-cer'),A.sCer],
    ['01','OB-01-03','Señalización y seguridad','m²',A.area,g('t-sen'),A.sSen],
    ['02','MT-02-01','Descapote e=25cm','m²',A.area,g('t-des'),A.sDes],
    ['02','MT-02-02',`Excavación mecánica tierra (${(100-A.pRoc).toFixed(0)}%)` ,'m³',A.volTie,g('t-tie')*FAC,A.sTie],
    ['02','MT-02-03',`Excavación en roca (${A.pRoc.toFixed(0)}%)`, 'm³',A.volRoc,g('t-roc')*FAC,A.sRoc],
    ['02','MT-02-04',`Relleno y compactación (${A.pRel.toFixed(0)}%)`,'m³',A.volRel,g('t-rel')*FAC,A.sRel],
    ['02','MT-02-05','Nivelación de rasante','m²',A.area,g('t-niv'),A.sNiv],
    ['02','MT-02-06',`Transporte sobrante <5km (${(100-A.pRel).toFixed(0)}%)`,'m³',A.volTra,g('t-tra'),A.sTra],
    ['03','OC-03-01','Cunetas drenaje (40% per.)','m.l.',A.pCun,g('t-cun')*FAC,A.sCun],
    ['03','OC-03-02','Revegetalización taludes (30%)','m²',A.aRev,g('t-rev'),A.sRev],
  ];
  if(A.xCant>0&&g('t-ext')>0)
    rows.push(['03','OC-03-03',$('xNom')?.value||'Ítem adicional',$('xUn')?.value||'Gl',A.xCant,g('t-ext'),A.sExt]);

  const tb=$('rBody'); tb.innerHTML='';
  const cn={'01':'PRELIMINARES','02':'MOVIMIENTO DE TIERRAS','03':'OBRAS COMPLEMENTARIAS'};
  let curC='';
  rows.forEach(([cap,cod,desc,un,cant,tar,sub])=>{
    if(cap!==curC){ curC=cap; const tr=document.createElement('tr'); tr.className='rcap';
      tr.innerHTML=`<td colspan="6">CAP. ${cap} — ${cn[cap]}</td>`; tb.appendChild(tr); }
    const tr=document.createElement('tr');
    tr.innerHTML=`<td><code style="font-size:8px;color:var(--mut)">${cod}</code></td><td>${desc}</td><td>${un}</td><td class="tr">${fN(cant)}</td><td class="tr">${fM(tar)}</td><td class="tr">${fM(sub)}</td>`;
    tb.appendChild(tr);
  });

  // Subtotales
  [['CAP.01 — Preliminares',A.cap1],['CAP.02 — Mov. tierras',A.cap2],['CAP.03 — Complementarias',A.cap3]].forEach(([l,v])=>{
    const tr=document.createElement('tr'); tr.className='rcap';
    tr.innerHTML=`<td colspan="5" class="tr">${l}</td><td class="tr">${fM(v)}</td>`; tb.appendChild(tr);
  });

  // Subtotal directo
  const trD=document.createElement('tr'); trD.className='rcap';
  trD.innerHTML=`<td colspan="5" class="tr" style="color:var(--txt);font-weight:700;">COSTO DIRECTO</td><td class="tr" style="color:var(--txt);font-weight:700;">${fM(A.dir)}</td>`;
  tb.appendChild(trD);

  // AIU desglosado
  const aiuRows=[
    [`A — Administración (${A.pctA}%)`, A.dir*A.pctA/100],
    [`I — Imprevistos (${A.pctI}%)`,    A.dir*A.pctI/100],
    [`U — Utilidad (${A.pctU}%)`,        A.dir*A.pctU/100],
  ];
  aiuRows.forEach(([l,v])=>{
    const tr=document.createElement('tr');
    tr.innerHTML=`<td colspan="5" class="tr" style="color:#818cf8;font-size:10px;">${l}</td><td class="tr" style="color:#818cf8;">${fM(v)}</td>`;
    tb.appendChild(tr);
  });
  const trAIU=document.createElement('tr'); trAIU.className='rcap';
  trAIU.innerHTML=`<td colspan="5" class="tr" style="color:#818cf8;">SUBTOTAL AIU (${A.pctA+A.pctI+A.pctU}%)</td><td class="tr" style="color:#818cf8;">${fM(A.aiu)}</td>`;
  tb.appendChild(trAIU);

  const tr=document.createElement('tr'); tr.className='rtot';
  tr.innerHTML=`<td colspan="5" class="tr">TOTAL ESTIMADO</td><td class="tr">${fM(A.tot)}</td>`;
  tb.appendChild(tr);

  // Indicadores de costos
  const trInd=document.createElement('tr');
  trInd.innerHTML=`<td colspan="6" style="padding:6px 8px;background:var(--bg);border-top:1px solid var(--bord);">
    <div style="display:flex;gap:20px;font:10px var(--font-mono);color:var(--mut);">
      <span>Costo/m²: <b style="color:var(--acc)">${A.area>0?fM(A.tot/A.area)+'':'—'}</b></span>
      <span>Costo/m³: <b style="color:var(--acc)">${A.vol>0?fM(A.tot/A.vol)+'':'—'}</b></span>
      <span>Plazo: <b style="color:var(--acc)">${A.diasMin}–${A.diasMax} días</b></span>
      <span>Dir.: <b style="color:var(--txt)">${(A.dir/A.tot*100).toFixed(1)}%</b></span>
    </div>
  </td>`;
  tb.appendChild(trInd);

  set('rTot',fM(A.tot));
  set('rAlt', A.tot>0?(MONEDA==='COP'?`≈ $${(A.tot/TRM).toFixed(0)} USD`:`≈ $${Math.round(A.tot).toLocaleString('es-CO')} COP`):'');

  /* Productos seleccionados */
  const selArr=Object.values(SEL);
  const rp=$('resProd');
  if(selArr.length && rp){
    rp.style.display='';
    rp.innerHTML='<div class="rcard"><div class="rcard-title">🔩 Materiales y Maquinaria seleccionados</div>'+
      selArr.map(it=>`<div class="rrow"><span class="rl">${it.n}</span><span class="rv" style="color:var(--acc)">${it.p}</span></div>`).join('')+'</div>';
  } else if(rp) rp.style.display='none';
}

/* ── Catálogo ── */
const CAT=[
  {g:'🧱 Materiales',i:[
    {id:'m1',e:'🪨',n:'Piedra triturada ½"',c:'Agregados',d:'Base granular y relleno compactado.',p:'$85.000/m³',r:true},
    {id:'m2',e:'🏖️',n:'Arena de río lavada',c:'Agregados',d:'Morteros y camas de tuberías.',p:'$65.000/m³',r:false},
    {id:'m3',e:'🔩',n:'Malla eslabonada h=2m',c:'Cerramientos',d:'Cerramiento provisional Cal.12.',p:'$28.000/m.l.',r:true},
    {id:'m4',e:'🌱',n:'Semilla pasto nativo',c:'Revegetalización',d:'Kikuyo+trébol zona andina.',p:'$3.200/m²',r:false},
    {id:'m5',e:'🧱',n:'Concreto premezclado 21MPa',c:'Estructuras',d:'Cunetas y obras de arte.',p:'$480.000/m³',r:false},
  ]},
  {g:'🔧 Equipos y herramienta',i:[
    {id:'h1',e:'📏',n:'Nivel de ingeniero',c:'Topografía',d:'Nivel óptico alta precisión.',p:'$85.000/día',r:true},
    {id:'h2',e:'📡',n:'GPS diferencial RTK',c:'Topografía',d:'Sub-centimétrico para replanteo.',p:'$220.000/día',r:true},
    {id:'h3',e:'🔨',n:'Kit herramienta menor',c:'Obra civil',d:'Palas, picas, barretones.',p:'$1.200.000/mes',r:false},
    {id:'h4',e:'💧',n:'Motobomba 3"',c:'Drenaje',d:'Desagüe excavaciones.',p:'$95.000/día',r:false},
  ]},
  {g:'🚜 Maquinaria',i:[
    {id:'q1',e:'🟡',n:'Excavadora Cat 320',c:'Mov. tierra',d:'Orugas 20t. ≈250 m³/día.',p:'$1.850.000/día',r:true},
    {id:'q2',e:'🟢',n:'Motoniveladora 120K',c:'Nivelación',d:'Rasante final ≈8.000 m²/día.',p:'$1.650.000/día',r:true},
    {id:'q3',e:'🔵',n:'Compactador vibratorio 10t',c:'Compactación',d:'95% Proctor. Incl. operador.',p:'$980.000/día',r:false},
    {id:'q4',e:'🟣',n:'Retroexcavadora JCB 3CX',c:'Zanjas',d:'Versátil difícil acceso.',p:'$1.200.000/día',r:false},
    {id:'q5',e:'🟠',n:'Bulldozer D6/D7',c:'Conformación',d:'Empuje y conformación.',p:'$2.100.000/día',r:false},
  ]},
  {g:'🚛 Transporte',i:[
    {id:'t1',e:'🚛',n:'Volqueta 8 m³',c:'Transporte',d:'Retiro sobrante ≤15km.',p:'$580.000/día',r:true},
    {id:'t2',e:'🚚',n:'Tractomula + plataforma',c:'Maquinaria',d:'Movilización maquinaria.',p:'$1.800.000/viaje',r:false},
    {id:'t3',e:'🛻',n:'Camioneta 4×4',c:'Supervisión',d:'Desplazamiento en obra.',p:'$380.000/día',r:false},
  ]},
];

function renderProd(container){
  if(!container) return;
  container.innerHTML='';
  for(const grupo of CAT){
    const gt=document.createElement('div'); gt.className='pg-title'; gt.textContent=grupo.g; container.appendChild(gt);
    const grid=document.createElement('div'); grid.className='prod-grid';
    for(const item of grupo.i){
      const card=document.createElement('div');
      card.className='pcard'+(SEL[item.id]?' sel':'');
      card.innerHTML=(item.r?`<div class="pcard-rec">⭐ Recomendado</div>`:'')+
        `<div class="pcard-ico">${item.e}</div>`+
        `<div class="pcard-body">`+
          `<div class="pcard-cat">${item.c}</div>`+
          `<div class="pcard-name">${item.n}</div>`+
          `<div class="pcard-desc">${item.d}</div>`+
          `<div class="pcard-foot"><span class="pcard-precio">${item.p}</span>`+
          `<button class="ptog${SEL[item.id]?' on':''}" data-id="${item.id}">${SEL[item.id]?'✓ Incluido':'+​ Incluir'}</button>`+
        `</div></div>`;
      card.querySelector('.ptog').addEventListener('click',()=>{
        if(SEL[item.id]) delete SEL[item.id]; else SEL[item.id]=item;
        renderProd($('sbProd')); renderProd($('mpProd') || container.parentElement?.querySelector('#mpProd'));
      });
      grid.appendChild(card);
    }
    container.appendChild(grid);
  }
}

function parseCSV(csv){
  const lines=csv.trim().split('\n');
  const pts=[];
  let hasHeader=false;
  for(let i=0;i<lines.length;i++){
    const raw=lines[i].trim(); if(!raw) continue;
    const cols=raw.split(',').map(s=>s.trim());
    /* Detectar encabezado */
    if(i===0 && (isNaN(parseFloat(cols[0]))||cols[0].toLowerCase()==='n'||cols[0].toLowerCase()==='x')){ hasHeader=true; continue; }
    /* Intentar N,X,Y,Z,DESC o X,Y,Z */
    let x,y,z;
    if(cols.length>=4){
      /* N,X,Y,Z... */
      x=parseFloat(cols[1]); y=parseFloat(cols[2]); z=parseFloat(cols[3]);
    } else if(cols.length>=3){
      x=parseFloat(cols[0]); y=parseFloat(cols[1]); z=parseFloat(cols[2]);
    }
    if(!isNaN(x)&&!isNaN(y)&&!isNaN(z)) pts.push({x,y,z});
  }
  return pts;
}

/* ══ INIT ══════════════════════════════════════════════════════ */
(function init(){
  let srcLabel='';

  /* ── Prioridad 1: datos inyectados por PHP (?proyecto=ID) ── */
  if(window.__FYLCAD_DB__){
    const db=window.__FYLCAD_DB__;
    M = db.meta || {};
    M.n = M.n || 0;
    srcLabel = db.nombre + ' · desde DB';

    /* Parsear CSV */
    if(db.csv && db.csv.trim()){
      try{ PTS = parseCSV(db.csv); M.n = PTS.length; }
      catch(e){ console.warn('CSV parse err', e); }
    }

    /* Si las métricas son cero pero tenemos puntos, calcularlas en el cliente */
    if(PTS.length >= 3 && (!M.area || M.area === 0)){
      const xs = PTS.map(p=>p.x), ys = PTS.map(p=>p.y), zs = PTS.map(p=>p.z);
      M.zMin = Math.min(...zs);
      M.zMax = Math.max(...zs);
      M.desnivel = M.zMax - M.zMin;
      /* Área aproximada por bounding box / 2 como placeholder hasta triangular */
      const dx = Math.max(...xs) - Math.min(...xs);
      const dy = Math.max(...ys) - Math.min(...ys);
      M.area = dx * dy * 0.7; /* estimado, se recalcula tras triangular */
      M.volumen = M.area * M.desnivel * 0.3;
      M.perimetro = 2*(dx+dy);
    }

    /* Guardar en localStorage */
    try{
      localStorage.setItem('fylcad_metricas', JSON.stringify({...M, cx:0, cy:0, timestamp:Date.now()}));
      localStorage.setItem('fylcad_puntos', JSON.stringify(PTS.length>3000?PTS.filter((_,i)=>i%Math.ceil(PTS.length/3000)===0):PTS));
    }catch(e){}
  }
  /* ── Prioridad 2: localStorage (viene de proyecto.php sin guardar) ── */
  else {
    try{
      const rawM=localStorage.getItem('fylcad_metricas');
      if(!rawM){ showND(); return; }
      M=JSON.parse(rawM);
      if(!M?.area && !M?.n && !M?.zMin){ showND(); return; }
      const rawP=localStorage.getItem('fylcad_puntos');
      const rawT=localStorage.getItem('fylcad_tris');
      const rawN=localStorage.getItem('fylcad_niveles');
      if(rawP) PTS=JSON.parse(rawP);
      if(rawT) TRIS=JSON.parse(rawT);
      if(rawN) NIV=JSON.parse(rawN);
      const hace=M.timestamp?Math.round((Date.now()-M.timestamp)/60000):0;
      srcLabel=hace<2?'Esta sesión':hace<60?`Hace ${hace} min`:'Sesión reciente';
    }catch(e){ showND(); return; }
  }

  if(!PTS.length && !M?.area && !M?.n){ showND(); return; }

  /* ── Centroide y rangos Z ── */
  CX = M.cx || (PTS.length ? PTS.reduce((s,p)=>s+p.x,0)/PTS.length : 0);
  CY = M.cy || (PTS.length ? PTS.reduce((s,p)=>s+p.y,0)/PTS.length : 0);
  ZMIN = M.zMin||0; ZMAX = M.zMax||1;

  /* ── UI info ── */
  $('nodata').style.display='none';
  const pd=$('pdata'); if(pd) pd.style.display='flex';
  set('pNombre', (M.n||PTS.length).toLocaleString('es-CO')+' puntos · '+srcLabel);
  set('pSub',    PTS.length?`${PTS.length.toLocaleString('es-CO')} pts en memoria`:'Solo métricas');
  set('k-area',  fN(M.area)+' m²');
  set('k-vol',   fN(M.volumen)+' m³');
  set('k-desn',  (M.desnivel||0).toFixed(1)+' m');
  set('hbadge',  `${(M.n||PTS.length).toLocaleString('es-CO')||'?'} pts · ${fN(M.area)} m²`);
  const hb=$('hbadge');
  if(hb){
    hb.style.cssText='font-size:10px;background:var(--acc-dim);color:var(--acc);border:1px solid rgba(0,229,192,.2);border-radius:20px;padding:2px 10px;font-family:var(--font-mono);';
    // Animación de carga exitosa
    hb.classList.remove('loaded'); void hb.offsetWidth; hb.classList.add('loaded');
  }

  /* ── Triangular + curvas si faltan ── */
  function finalizarRender(){
    if(!TRIS.length && PTS.length>=3){
      TRIS=delaunay(PTS.map(p=>({x:p.x,y:p.y})));
    }
    if(!NIV.length && M.zMin!=null && M.zMax!=null){
      const eq=Math.max(1,Math.round((M.zMax-M.zMin)/20));
      for(let z=Math.ceil(M.zMin/eq)*eq; z<=M.zMax; z+=eq) NIV.push(z);
    }
    buildIso();

    /* Recalcular área real por TIN si las métricas eran 0 */
    if(TRIS.length && PTS.length && (!M.area || M.area<1)){
      let areaReal=0;
      for(const t of TRIS){
        const a=PTS[t.a],b=PTS[t.b],c=PTS[t.c];
        if(!a||!b||!c) continue;
        areaReal+=Math.abs((b.x-a.x)*(c.y-a.y)-(c.x-a.x)*(b.y-a.y))/2;
      }
      M.area=areaReal;
      M.volumen=M.volumen||areaReal*Math.max(M.desnivel||1,1)*0.3;
      set('k-area', fN(M.area)+' m²');
      set('k-vol',  fN(M.volumen)+' m³');
      set('hbadge', `${PTS.length.toLocaleString('es-CO')} pts · ${fN(M.area)} m²`);
    }

    function tryDraw(intentos){
      const W=CVS.offsetWidth, H=CVS.offsetHeight;
      if(W>10 && H>10){
        CVS.width=W; CVS.height=H;
        resetView();
        recalc();
        set('psInfo',`${PTS.length.toLocaleString('es-CO')} pts · ${TRIS.length.toLocaleString('es-CO')} △ · ${NIV.length} curvas`);
        if(window.__FYLCAD_DB__) toast(`✅ "${window.__FYLCAD_DB__.nombre}" — ${PTS.length.toLocaleString('es-CO')} puntos cargados`);
      } else if(intentos>0){
        setTimeout(()=>tryDraw(intentos-1), 100);
      }
    }
    tryDraw(20);
  }

  /* Factor automático */
  const applyFactor=()=>{
    const pend=pendientesAnalisis(TRIS,PTS,null);
    M.pendMedia=pend.media; M.rugosidad=pend.rugosidad;
    const morfo={media:pend.media,max:pend.max,rugosidad:pend.rugosidad,desnivel:M.desnivel||0,area:M.area};
    const facAuto=calcFactorAuto(morfo);
    FAC=facAuto.f;
    const fEl=$('factor'); if(fEl) fEl.value=FAC.toFixed(2);
    set('factorSub',facAuto.desc);
    renderFactorDetalle(facAuto);
    const fb=$('facBadge');
    if(fb){
      fb.textContent=facAuto.clP?.l||'';
      // Clase semántica según factor
      fb.className='fac-badge';
      if(FAC<=1.1) fb.classList.add('low');
      else if(FAC<=1.5) fb.classList.add('med');
      else if(FAC<=2.0) fb.classList.add('high');
      else fb.classList.add('extreme');
      // Animación de entrada
      fb.style.animation='none'; void fb.offsetWidth; fb.style.animation='';
    }
    recalc();
  };

  $('facModo')?.addEventListener('change',()=>{
    if($('facModo').value==='auto'){applyFactor();toast('Factor automático', {ico:'⚡', sub:'Calculado por pendiente y acceso', type:'ok'});}
    else toast('Factor manual', {ico:'✏️', sub:'Edita el valor directamente', type:'info'});
  });

  updateZonaUI();
  renderProd($('sbProd'));

  /* Iniciar render con pequeño delay para que el DOM esté listo */
  setTimeout(()=>{
    finalizarRender();
    setTimeout(applyFactor, 200);
  }, 50);

})();

function hexToRgb(hex){
  const r=/^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex);
  return r?`${parseInt(r[1],16)},${parseInt(r[2],16)},${parseInt(r[3],16)}`:'245,158,11';
}

/* ══ GUARDAR COTIZACIÓN EN DB ══ */
function guardarCotizacion() {
  if(!_APU || !M) {
    toast('Calcula el APU primero', {type:'warn', ico:'⚠️', sub:'No hay datos para guardar'});
    return;
  }
  const proyId = <?= isset($_GET['proyecto']) ? (int)$_GET['proyecto'] : 'null' ?>;
  if(!proyId) {
    toast('Abre la cotización desde un proyecto guardado', {type:'warn', ico:'⚠️', sub:'Sin ID de proyecto'});
    return;
  }

  const btn = $('btnGuardarCot');
  if(btn) { btn.className='btn-guardar-cot'; btn.innerHTML='<span class="calc-spinner"></span><span>Guardando…</span>'; }

  const payload = {
    proyecto_id: proyId,
    cotizacion: {
      tarifaTierra:      parseFloat($('t-tie')?.value)||0,
      tarifaNivelacion:  parseFloat($('t-niv')?.value)||0,
      tarifaCerramiento: parseFloat($('t-cer')?.value)||0,
      costoTierra:       _APU.sTie||0,
      costoNivelacion:   _APU.sNiv||0,
      costoCerramiento:  _APU.sCer||0,
      total:             _APU.tot||0,
      cap1:              _APU.cap1||0,
      cap2:              _APU.cap2||0,
      cap3:              _APU.cap3||0,
      aiu:               _APU.aiu||0,
      area_m2:           _APU.area||0,
      vol_m3:            _APU.vol||0,
      pctA:              _APU.pctA||0,
      pctI:              _APU.pctI||0,
      pctU:              _APU.pctU||0,
      factor:            FAC,
      cliente:           $('iCli')?.value||'',
      municipio:         $('iMun')?.value||'',
      tipo_obra:         $('iTipo')?.value||'',
      nombre_rev:        $('iNom')?.value||'Cotización',
    }
  };

  fetch('guardar_proyecto.php', {
    method:'POST',
    headers:{'Content-Type':'application/json'},
    body: JSON.stringify(payload)
  })
  .then(r=>r.json())
  .then(res=>{
    if(res.ok || res.cotizacion_ok) {
      if(btn){ btn.className='btn-guardar-cot saved'; btn.innerHTML='<span>✓</span><span>Guardado</span>'; }
      toast('Cotización guardada', {type:'ok', ico:'💾', sub:`Total: ${fM(_APU.tot)}`, dur:4000, progress:true});
      setTimeout(()=>{
        if(btn){ btn.className='btn-guardar-cot'; btn.innerHTML='<span class="btn-ico">💾</span><span id="btnGuardarTxt">Guardar cotización</span>'; }
      }, 3000);
    } else {
      if(btn){ btn.className='btn-guardar-cot'; btn.innerHTML='<span class="btn-ico">💾</span><span>Guardar cotización</span>'; }
      toast(res.error||'Error al guardar', {type:'err', ico:'✕'});
    }
  })
  .catch(()=>{
    if(btn){ btn.className='btn-guardar-cot'; btn.innerHTML='<span class="btn-ico">💾</span><span>Guardar cotización</span>'; }
    toast('Error de conexión', {type:'err', ico:'✕', sub:'Revisa la conexión'});
  });
}

function showND(){
  $('nodata').style.display='block'; $('pdata').style.display='none';
  $('hbadge').textContent='Sin datos';
  $('hbadge').style.cssText='font-size:10px;background:rgba(239,68,68,.1);color:#f87171;border:1px solid rgba(239,68,68,.2);border-radius:20px;padding:2px 10px;';
}

/* ═══════════════════════════════════════════════════════════════
   SIMULACIÓN 3D DE RELLENO — Motor Canvas 2D con perspectiva
═══════════════════════════════════════════════════════════════ */
(function(){
'use strict';

const MATERIALES=[
  {id:'piedra',e:'🪨',n:'Piedra triturada',precio:85000,unit:'m³',
   capas:[{color:'#3d2e1e',lbl:'Base seleccionada'},{color:'#5a4230',lbl:'Afirmado piedra'},{color:'#7a6248',lbl:'Base compactada'}],
   particle:'#c4a882',desc:'Relleno granular compactado · Base granular INVIAS',compactacion:1.25},
  {id:'arena',e:'🏖️',n:'Arena río lavada',precio:65000,unit:'m³',
   capas:[{color:'#b8a060',lbl:'Cama de arena'},{color:'#ceba80',lbl:'Relleno arena'}],
   particle:'#e8d8a0',desc:'Cama de tuberías y rellenos finos',compactacion:1.15},
  {id:'concreto',e:'🧱',n:'Concreto 21MPa',precio:480000,unit:'m³',
   capas:[{color:'#666',lbl:'Subbase'},{color:'#888',lbl:'Placa concreto'},{color:'#aaa',lbl:'Acabado'}],
   particle:'#c0c0c0',desc:'Estructuras, cunetas y obras de arte',compactacion:1.0},
  {id:'tierra',e:'🌱',n:'Material selecto',precio:18000,unit:'m³',
   capas:[{color:'#3a2510',lbl:'Subrasante'},{color:'#5a3a18',lbl:'Material selecto'},{color:'#2d5010',lbl:'Cobertura vegetal'}],
   particle:'#7a5a30',desc:'Terraplén y conformación de taludes',compactacion:1.20},
  {id:'grava',e:'⬛',n:'Grava base',precio:55000,unit:'m³',
   capas:[{color:'#3a3a3a',lbl:'Sub-base granular'},{color:'#505050',lbl:'Base granular'}],
   particle:'#707070',desc:'Sub-base y base granular para vías',compactacion:1.30},
  {id:'geotex',e:'🔶',n:'Geotextil+piedra',precio:120000,unit:'m²',
   capas:[{color:'#333',lbl:'Terreno natural'},{color:'#d46a00',lbl:'Geotextil'},{color:'#5a4a38',lbl:'Relleno piedra'}],
   particle:'#ff8c00',desc:'Refuerzo geotécnico + drenaje',compactacion:1.10},
];

let MAT=null, SIM_PTS=[], SIM_TRIS=[], SIM_ZONA=null;
let animReq=null, animRunning=false, animPct=0;
let capasOK=0, totalCaps=0;
let parts=[];
let cam={rx:0.55,ry:0.52,z:1.0,tx:0,ty:0};
let drag={on:false,sh:false,lx:0,ly:0};
let lastPinch=0;

const SC=document.getElementById('simCanvas');
const SX=SC?.getContext('2d');
if(!SC||!SX) return;

function rsz(){ SC.width=SC.offsetWidth*devicePixelRatio; SC.height=SC.offsetHeight*devicePixelRatio; }

/* Proyección 3D → pantalla */
function p3(wx,wy,wz){
  if(!SIM_PTS.length) return {x:SC.width/2,y:SC.height/2,d:0};
  let x0=Infinity,x1=-Infinity,y0=Infinity,y1=-Infinity,z0=Infinity,z1=-Infinity;
  for(const p of SIM_PTS){x0=Math.min(x0,p.x);x1=Math.max(x1,p.x);y0=Math.min(y0,p.y);y1=Math.max(y1,p.y);z0=Math.min(z0,p.z);z1=Math.max(z1,p.z);}
  const sp=Math.max(x1-x0,y1-y0,0.01);
  let x=(wx-(x0+x1)/2)/sp, y=(wy-(y0+y1)/2)/sp, z=(wz-(z0+z1)/2)/sp*1.6;
  const cy=Math.cos(cam.ry),sy=Math.sin(cam.ry);
  let rx=x*cy-y*sy, ry2=x*sy+y*cy;
  const cx2=Math.cos(cam.rx),sx2=Math.sin(cam.rx);
  let rz=ry2*sx2+z*cx2, ry3=ry2*cx2-z*sx2;
  const W=SC.width,H=SC.height,sc=Math.min(W,H)*0.36*cam.z;
  return{x:W/2+(rx+cam.tx)*sc, y:H/2+(ry3+cam.ty)*sc, d:rz};
}

/* Dibuja terreno base */
function drawT(ctx){
  if(!SIM_PTS.length||!SIM_TRIS.length) return;
  const z0=Math.min(...SIM_PTS.map(p=>p.z)), z1=Math.max(...SIM_PTS.map(p=>p.z)), zr=Math.max(z1-z0,0.01);
  const sd=[...SIM_TRIS].map(t=>{
    const a=SIM_PTS[t.a],b=SIM_PTS[t.b],c=SIM_PTS[t.c];
    const pa=p3(a.x,a.y,a.z),pb=p3(b.x,b.y,b.z),pc=p3(c.x,c.y,c.z);
    return{pa,pb,pc,d:(pa.d+pb.d+pc.d)/3,zm:(a.z+b.z+c.z)/3};
  }).sort((a,b)=>a.d-b.d);
  for(const {pa,pb,pc,zm} of sd){
    const t=(zm-z0)/zr;
    const r=~~(80+t*70),g=~~(100+t*90),b=~~(40+t*50);
    ctx.beginPath(); ctx.moveTo(pa.x,pa.y); ctx.lineTo(pb.x,pb.y); ctx.lineTo(pc.x,pc.y); ctx.closePath();
    ctx.fillStyle=`rgb(${r},${g},${b})`; ctx.fill();
    ctx.strokeStyle='rgba(0,0,0,.15)'; ctx.lineWidth=.5; ctx.stroke();
  }
}

/* Dibuja capas de relleno animadas */
function drawF(ctx,pct){
  if(!MAT||!SIM_PTS.length||!SIM_TRIS.length) return;
  const esp=parseFloat(document.getElementById('simEspesor')?.value||0.3);
  const prof=parseFloat(document.getElementById('simProfundidad')?.value||1.0);
  const nCaps=Math.max(1,Math.round(prof/esp));
  totalCaps=nCaps; capasOK=Math.floor(pct*nCaps);
  const zBase=Math.min(...SIM_PTS.map(p=>p.z));

  for(let ci=0;ci<nCaps;ci++){
    const fr=(ci+1)/nCaps;
    if(fr>pct+0.001) break;
    const capaDef=MAT.capas[Math.min(ci,MAT.capas.length-1)];
    const zA=zBase+ci*esp, zB=zBase+(ci+1)*esp;
    const cpct=Math.min(1,(pct*nCaps-ci));
    const zTop=zA+(zB-zA)*cpct;

    const sd=[...SIM_TRIS].map(t=>{
      const a=SIM_PTS[t.a],b=SIM_PTS[t.b],c=SIM_PTS[t.c];
      const pa=p3(a.x,a.y,zTop),pb=p3(b.x,b.y,zTop),pc=p3(c.x,c.y,zTop);
      return{pa,pb,pc,d:(pa.d+pb.d+pc.d)/3};
    }).sort((a,b)=>a.d-b.d);

    const alpha=ci===capasOK?0.75*cpct:0.82;
    for(const {pa,pb,pc} of sd){
      ctx.beginPath(); ctx.moveTo(pa.x,pa.y); ctx.lineTo(pb.x,pb.y); ctx.lineTo(pc.x,pc.y); ctx.closePath();
      ctx.fillStyle=capaDef.color+Math.round(alpha*255).toString(16).padStart(2,'0');
      ctx.fill();
      if(cpct>0.4){ctx.strokeStyle=`rgba(255,255,255,${alpha*.18})`; ctx.lineWidth=.4; ctx.stroke();}
    }

    /* Efecto borde brillante en capa activa */
    if(ci===capasOK&&cpct>0.1){
      const edgeAlpha=cpct*0.6;
      for(const {pa,pb,pc} of sd){
        ctx.beginPath(); ctx.moveTo(pa.x,pa.y); ctx.lineTo(pb.x,pb.y); ctx.lineTo(pc.x,pc.y); ctx.closePath();
        ctx.strokeStyle=`rgba(255,220,100,${edgeAlpha})`; ctx.lineWidth=1; ctx.stroke();
      }
    }
  }
}

/* Partículas */
function spawnP(){
  if(!SIM_PTS.length||!MAT) return;
  const prof=parseFloat(document.getElementById('simProfundidad')?.value||1.0);
  const zMax=Math.max(...SIM_PTS.map(p=>p.z));
  for(let i=0;i<4+~~(Math.random()*3);i++){
    const bp=SIM_PTS[~~(Math.random()*SIM_PTS.length)];
    parts.push({wx:bp.x+(Math.random()-.5)*3,wy:bp.y+(Math.random()-.5)*3,
      wz:zMax+prof*(1.2+Math.random()*.8),vz:-.06-Math.random()*.1,
      color:MAT.particle,r:1.5+Math.random()*2,life:1,land:bp.z+prof*(1-animPct)});
  }
}
function updP(){
  parts=parts.filter(p=>{
    p.wz+=p.vz; p.life-=.018;
    if(p.wz<=p.land) p.life=0;
    return p.life>0;
  });
}
function drawP(ctx){
  for(const p of parts){
    const sp=p3(p.wx,p.wy,p.wz);
    ctx.beginPath(); ctx.arc(sp.x,sp.y,p.r,0,Math.PI*2);
    ctx.fillStyle=p.color+Math.round(p.life*200).toString(16).padStart(2,'0');
    ctx.fill();
  }
}

/* Render frame */
function frame(){
  const W=SC.width,H=SC.height;
  SX.clearRect(0,0,W,H);
  const bg=SX.createLinearGradient(0,0,0,H);
  bg.addColorStop(0,'#0a1428'); bg.addColorStop(1,'#050c18');
  SX.fillStyle=bg; SX.fillRect(0,0,W,H);
  /* Grid sutil */
  SX.strokeStyle='rgba(0,229,192,.055)'; SX.lineWidth=.7;
  for(let i=0;i<=10;i++){const t=i/10; SX.beginPath();SX.moveTo(t*W,0);SX.lineTo(t*W,H);SX.stroke();SX.beginPath();SX.moveTo(0,t*H);SX.lineTo(W,t*H);SX.stroke();}
  drawT(SX);
  drawF(SX,animPct);
  drawP(SX);
}

/* Loop */
let fpsC=0,fpsTm=0,fps=0;
function loop(ts){
  if(!animRunning){animReq=null;return;}
  const vel=parseInt(document.getElementById('simVelocidad')?.value||3);
  const spd=[.0015,.0030,.0060,.011,.018][vel-1];
  animPct=Math.min(1,animPct+spd);
  fpsC++; if(ts-fpsTm>1000){fps=fpsC;fpsC=0;fpsTm=ts;}
  const fl=document.getElementById('simFpsLabel'); if(fl) fl.textContent=fps+'fps';
  if(animPct<1&&Math.random()<.4) spawnP();
  updP();
  frame();
  /* Actualizar UI overlay */
  const esp=parseFloat(document.getElementById('simEspesor')?.value||.3);
  const prof=parseFloat(document.getElementById('simProfundidad')?.value||1);
  const area=SIM_ZONA?.area||M?.area||0;
  document.getElementById('ovCapas').textContent=capasOK+'/'+totalCaps;
  document.getElementById('ovVol').textContent=fN2(area*prof*animPct)+' m³';
  document.getElementById('ovPct').textContent=~~(animPct*100)+'%';
  const pb=document.getElementById('simProgressBar'); if(pb) pb.style.width=(animPct*100)+'%';
  const as=document.getElementById('simAnimStatus');
  if(as) as.textContent=animPct>=1?'✅ Relleno completado':`⏳ Compactando capa ${Math.min(capasOK+1,totalCaps)} de ${totalCaps}…`;
  if(animPct>=1){animRunning=false;animReq=null;parts=[];const btn=document.getElementById('btnSimPlay');if(btn)btn.textContent='▶ Reiniciar';}
  else animReq=requestAnimationFrame(loop);
}

function fN2(n){return isNaN(n)?'—':n.toLocaleString('es-CO',{maximumFractionDigits:1});}

function renderMats(){
  const g=document.getElementById('matGrid');if(!g)return;
  g.innerHTML=MATERIALES.map(m=>`<div class="mat-card${MAT?.id===m.id?' on':''}" data-mid="${m.id}" title="${m.desc}">
    <div class="mat-ico">${m.e}</div><div class="mat-name">${m.n}</div>
    <div class="mat-price">$${m.precio.toLocaleString('es-CO')}/${m.unit}</div></div>`).join('');
  g.querySelectorAll('.mat-card').forEach(c=>c.addEventListener('click',()=>{
    MAT=MATERIALES.find(m=>m.id===c.dataset.mid); renderMats(); updateStats();
    document.getElementById('btnSimPlay').disabled=false;
  }));
}

function updateStats(){
  const prof=parseFloat(document.getElementById('simProfundidad')?.value||1);
  const area=SIM_ZONA?.area||M?.area||0;
  const vol=area*prof, caps=Math.max(1,Math.round(prof/(parseFloat(document.getElementById('simEspesor')?.value)||.3)));
  const costo=MAT?vol*MAT.precio*(MAT.compactacion||1):0;
  const sv=document.getElementById('sVol');if(sv)sv.textContent=fN2(vol)+' m³';
  const sc=document.getElementById('sCapas');if(sc)sc.textContent=caps;
  const sa=document.getElementById('sArea');if(sa)sa.textContent=fN2(area)+' m²';
  const sk=document.getElementById('sCosto');if(sk)sk.textContent=costo>0?'$'+(costo/1e6).toFixed(2)+'M':'—';
}

function loadData(){
  SIM_PTS=[...PTS]; SIM_TRIS=TRIS.length?[...TRIS]:[];
  if(ZONA&&ZPOLY.length>=3){
    SIM_ZONA=ZONA;
    SIM_PTS=PTS.filter(p=>{
      let inside=false; const n=ZPOLY.length;
      for(let i=0,j=n-1;i<n;j=i++){
        const xi=ZPOLY[i].x,yi=ZPOLY[i].y,xj=ZPOLY[j].x,yj=ZPOLY[j].y;
        if(((yi>p.y)!==(yj>p.y))&&(p.x<(xj-xi)*(p.y-yi)/(yj-yi)+xi)) inside=!inside;
      }
      return inside;
    });
    if(SIM_PTS.length<3){SIM_PTS=[...PTS];SIM_ZONA={area:M?.area||0};}
  } else { SIM_ZONA={area:M?.area||0}; }
  if(!SIM_TRIS.length&&SIM_PTS.length>=3)
    SIM_TRIS=delaunay(SIM_PTS.map(p=>({x:p.x,y:p.y})));
  const zi=document.getElementById('simZonaInfo');
  if(zi) zi.innerHTML=SIM_ZONA?.area?`<strong style="color:var(--acc)">${fN2(SIM_ZONA.area)} m²</strong> · <strong style="color:var(--txt)">${SIM_PTS.length}</strong> puntos`:'Sin zona definida';
  updateStats(); rsz(); frame();
}

function updateLegend(){
  const leg=document.getElementById('simLegend');if(!leg||!MAT)return;
  leg.innerHTML=MAT.capas.map(c=>`<div class="sim-leg-item"><div class="sim-leg-dot" style="background:${c.color}"></div>${c.lbl}</div>`).join('');
}

/* Controles mouse/touch */
SC.addEventListener('mousedown',e=>{drag.on=true;drag.sh=e.shiftKey;drag.lx=e.clientX;drag.ly=e.clientY;});
window.addEventListener('mousemove',e=>{
  if(!drag.on)return;
  const dx=(e.clientX-drag.lx)*.011,dy=(e.clientY-drag.ly)*.011;
  if(drag.sh){cam.tx+=dx;cam.ty+=dy;}else{cam.ry+=dx;cam.rx+=dy;cam.rx=Math.max(-1.5,Math.min(1.5,cam.rx));}
  drag.lx=e.clientX;drag.ly=e.clientY;
  if(!animRunning)frame();
});
window.addEventListener('mouseup',()=>{drag.on=false;});
SC.addEventListener('wheel',e=>{cam.z*=e.deltaY>0?.91:1.10;cam.z=Math.max(.15,Math.min(6,cam.z));e.preventDefault();if(!animRunning)frame();},{passive:false});
SC.addEventListener('touchstart',e=>{if(e.touches.length===1){drag.on=true;drag.lx=e.touches[0].clientX;drag.ly=e.touches[0].clientY;}});
SC.addEventListener('touchmove',e=>{
  if(e.touches.length===2){
    const d=Math.hypot(e.touches[0].clientX-e.touches[1].clientX,e.touches[0].clientY-e.touches[1].clientY);
    if(lastPinch){cam.z*=d/lastPinch;cam.z=Math.max(.15,Math.min(6,cam.z));}
    lastPinch=d;if(!animRunning)frame();
  }else if(drag.on&&e.touches.length===1){
    const dx=(e.touches[0].clientX-drag.lx)*.011,dy=(e.touches[0].clientY-drag.ly)*.011;
    cam.ry+=dx;cam.rx+=dy;drag.lx=e.touches[0].clientX;drag.ly=e.touches[0].clientY;
    if(!animRunning)frame();
  }
  e.preventDefault();
},{passive:false});
SC.addEventListener('touchend',()=>{drag.on=false;lastPinch=0;});

/* Sliders */
['simEspesor','simProfundidad'].forEach(id=>{
  document.getElementById(id)?.addEventListener('input',function(){
    const v={simEspesor:'simEspesorVal',simProfundidad:'simProfVal'}[id];
    const el=document.getElementById(v); if(el) el.textContent=parseFloat(this.value).toFixed(id==='simEspesor'?2:1)+' m';
    updateStats();
  });
});
document.getElementById('simVelocidad')?.addEventListener('input',function(){
  const v=document.getElementById('simVelVal');
  if(v) v.textContent=['Lenta','Pausada','Normal','Rápida','Turbo'][parseInt(this.value)-1];
});

/* Botones */
document.getElementById('btnSimPlay')?.addEventListener('click',()=>{
  if(!MAT){alert('Selecciona un material primero.');return;}
  if(!SIM_PTS.length){alert('Sin puntos de terreno. Vuelve al plano.');return;}
  if(animRunning){animRunning=false;document.getElementById('btnSimPlay').textContent='▶ Continuar';return;}
  animPct=0;parts=[];animRunning=true;
  document.getElementById('btnSimPlay').textContent='⏸ Pausar';
  document.getElementById('simNoZone').style.display='none';
  document.getElementById('simOverlay').style.display='';
  document.getElementById('simMatNombre').textContent=MAT.e+' '+MAT.n;
  document.getElementById('ovEspesor').textContent=parseFloat(document.getElementById('simEspesor').value).toFixed(2)+' m';
  updateLegend();
  if(animReq) cancelAnimationFrame(animReq);
  animReq=requestAnimationFrame(loop);
});
document.getElementById('btnSimReset')?.addEventListener('click',()=>{
  animRunning=false;if(animReq)cancelAnimationFrame(animReq);
  animPct=0;parts=[];cam={rx:.55,ry:.52,z:1.0,tx:0,ty:0};
  document.getElementById('btnSimPlay').textContent='▶ Iniciar simulación';
  const pb=document.getElementById('simProgressBar');if(pb)pb.style.width='0%';
  const as=document.getElementById('simAnimStatus');if(as)as.textContent='Listo para simular';
  frame();
});
document.getElementById('btnSimUsarTodo')?.addEventListener('click',()=>{
  SIM_PTS=[...PTS];SIM_TRIS=TRIS.length?[...TRIS]:[];
  SIM_ZONA={area:M?.area||0};
  if(!SIM_TRIS.length&&SIM_PTS.length>=3)SIM_TRIS=delaunay(SIM_PTS.map(p=>({x:p.x,y:p.y})));
  const zi=document.getElementById('simZonaInfo');
  if(zi)zi.innerHTML=`<strong style="color:var(--acc)">${fN2(M?.area||0)} m²</strong> — terreno completo (${SIM_PTS.length} pts)`;
  updateStats();rsz();frame();
});

/* Activar cuando se selecciona el tab */
document.querySelectorAll('.mtab').forEach(btn=>{
  btn.addEventListener('click',()=>{
    if(btn.dataset.m==='sim'){loadData();setTimeout(()=>{rsz();frame();},80);}
  });
});
window.addEventListener('resize',()=>{
  if(document.getElementById('mpane-sim')?.classList.contains('on')){rsz();if(!animRunning)frame();}
});

renderMats();
})();
</script>
</body>
</html>