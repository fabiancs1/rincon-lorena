<?php
/* =============================================
   FYLCAD — Planes y Precios
   Archivo: planes.php
============================================= */
session_start();
$logueado     = isset($_SESSION['usuario_id']);
$planActual   = $_SESSION['usuario_plan'] ?? null;
$nombreUsuario = $_SESSION['usuario_nombre'] ?? null;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>FYLCAD — Planes y Precios</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/planes.css">
</head>
<body>

<!-- ==================== HEADER ==================== -->
<header class="header" id="header">
    <a href="index.php" class="logo">FYL<span>CAD</span></a>

    <nav class="nav">
        <a href="index.php">Inicio</a>
        <a href="planes.php" class="active">Planes</a>
        <a href="#">Contacto</a>
    </nav>

    <div class="header-actions">
        <?php if ($logueado): ?>
            <a href="dashboard.php" class="btn btn-ghost">← Dashboard</a>
            <a href="proyecto.php" class="btn btn-accent">Ir al módulo 3D</a>
        <?php else: ?>
            <a href="login.php"    class="btn btn-ghost">Iniciar sesión</a>
            <a href="register.php" class="btn btn-accent">Crear cuenta <span>→</span></a>
        <?php endif; ?>
    </div>
</header>

<!-- ==================== HERO PLANES ==================== -->
<section class="plans-hero">
    <div class="plans-hero-inner">
        <span class="section-label">Precios</span>
        <h1>Simple, transparente,<br>sin sorpresas.</h1>
        <p>Empieza gratis y escala cuando lo necesites.<br>Sin contratos, cancela cuando quieras.</p>

        <!-- Toggle mensual / anual -->
        <div class="billing-toggle">
            <span class="billing-opt active" id="optMensual">Mensual</span>
            <div class="toggle-switch" id="billingSwitch">
                <div class="toggle-thumb"></div>
            </div>
            <span class="billing-opt" id="optAnual">
                Anual <span class="save-badge">-20%</span>
            </span>
        </div>
    </div>
</section>

<!-- ==================== PLANES ==================== -->
<section class="plans-section">
    <div class="plans-grid">

        <!-- Plan Free -->
        <div class="plan-card">
            <div class="plan-top">
                <div class="plan-icon">◈</div>
                <h2 class="plan-name">Free</h2>
                <p class="plan-tagline">Para explorar y aprender</p>
            </div>

            <div class="plan-price-wrap">
                <div class="plan-price">
                    <span class="price-currency">$</span>
                    <span class="price-amount">0</span>
                </div>
                <span class="price-period">para siempre</span>
            </div>

            <?php if ($logueado && $planActual === 'free'): ?>
                <div class="plan-current-badge">✓ Tu plan actual</div>
            <?php else: ?>
                <a href="register.php" class="plan-btn plan-btn-outline">
                    Empezar gratis →
                </a>
            <?php endif; ?>

            <ul class="plan-features">
                <li class="feat-yes">Módulo 3D completo</li>
                <li class="feat-yes">Hasta <strong>50 puntos</strong> por archivo</li>
                <li class="feat-yes">Triangulación Delaunay</li>
                <li class="feat-yes">Curvas de nivel</li>
                <li class="feat-yes">Métricas básicas (área, perímetro)</li>
                <li class="feat-yes">Cotización con tarifas editables</li>
                <li class="feat-yes">Vista 3D y 2D</li>
                <li class="feat-no">Exportar PNG / PDF</li>
                <li class="feat-no">Puntos ilimitados</li>
                <li class="feat-no">Historial de proyectos</li>
                <li class="feat-no">Comparación de capas</li>
                <li class="feat-no">Soporte prioritario</li>
            </ul>
        </div>

        <!-- Plan Pro (destacado) -->
        <div class="plan-card plan-card-featured">
            <div class="plan-badge-top">Más popular</div>

            <div class="plan-top">
                <div class="plan-icon">★</div>
                <h2 class="plan-name">Pro</h2>
                <p class="plan-tagline">Para profesionales activos</p>
            </div>

            <div class="plan-price-wrap">
                <div class="plan-price">
                    <span class="price-currency">$</span>
                    <span class="price-amount" id="proPrecio">9</span>
                    <span class="price-cents" id="proCents">.99</span>
                </div>
                <span class="price-period" id="proPeriodo">/ mes</span>
            </div>

            <?php if ($logueado && $planActual === 'premium'): ?>
                <div class="plan-current-badge featured">✓ Tu plan actual</div>
            <?php else: ?>
                <a href="#proximamente" class="plan-btn plan-btn-accent" id="btnProCTA">
                    Próximamente →
                </a>
            <?php endif; ?>

            <ul class="plan-features">
                <li class="feat-yes">Todo lo del plan Free</li>
                <li class="feat-yes"><strong>Puntos ilimitados</strong></li>
                <li class="feat-yes">Exportar plano como <strong>PNG y PDF</strong></li>
                <li class="feat-yes">Historial de <strong>proyectos guardados</strong></li>
                <li class="feat-yes">Comparación de 2 capas (corte/relleno)</li>
                <li class="feat-yes">Cálculo de volumen de corte y relleno</li>
                <li class="feat-yes">Exportar tabla de coordenadas</li>
                <li class="feat-yes">Cotización en PDF con membrete</li>
                <li class="feat-yes">Soporte por email prioritario</li>
                <li class="feat-no">API access</li>
                <li class="feat-no">Múltiples usuarios</li>
                <li class="feat-no">Marca blanca</li>
            </ul>
        </div>

        <!-- Plan Enterprise -->
        <div class="plan-card">
            <div class="plan-top">
                <div class="plan-icon">⬡</div>
                <h2 class="plan-name">Enterprise</h2>
                <p class="plan-tagline">Para equipos y empresas</p>
            </div>

            <div class="plan-price-wrap">
                <div class="plan-price plan-price-custom">
                    <span class="price-amount-custom">A medida</span>
                </div>
                <span class="price-period">según necesidades</span>
            </div>

            <a href="mailto:contacto@fylcad.com" class="plan-btn plan-btn-outline">
                Contactar ventas →
            </a>

            <ul class="plan-features">
                <li class="feat-yes">Todo lo del plan Pro</li>
                <li class="feat-yes"><strong>Múltiples usuarios</strong></li>
                <li class="feat-yes">Acceso a <strong>API REST</strong></li>
                <li class="feat-yes">Integración con AutoCAD / QGIS</li>
                <li class="feat-yes">Dashboard administrativo</li>
                <li class="feat-yes">Reportes personalizados</li>
                <li class="feat-yes">Marca blanca (white label)</li>
                <li class="feat-yes">SLA garantizado</li>
                <li class="feat-yes">Soporte dedicado 24/7</li>
                <li class="feat-yes">Onboarding personalizado</li>
                <li class="feat-yes">Facturación empresarial</li>
                <li class="feat-yes">Servidor dedicado opcional</li>
            </ul>
        </div>

    </div>
</section>

<!-- ==================== COMPARACIÓN ==================== -->
<section class="compare-section">
    <div class="compare-inner">
        <span class="section-label">Comparación detallada</span>
        <h2>¿Qué incluye cada plan?</h2>

        <div class="compare-table-wrap">
            <table class="compare-table">
                <thead>
                    <tr>
                        <th>Característica</th>
                        <th>Free</th>
                        <th class="th-featured">Pro</th>
                        <th>Enterprise</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="compare-group"><td colspan="4">Módulo 3D</td></tr>
                    <tr>
                        <td>Visualización 3D / 2D</td>
                        <td>✓</td><td class="td-featured">✓</td><td>✓</td>
                    </tr>
                    <tr>
                        <td>Triangulación Delaunay</td>
                        <td>✓</td><td class="td-featured">✓</td><td>✓</td>
                    </tr>
                    <tr>
                        <td>Curvas de nivel</td>
                        <td>✓</td><td class="td-featured">✓</td><td>✓</td>
                    </tr>
                    <tr>
                        <td>Puntos por archivo</td>
                        <td>50</td><td class="td-featured">∞</td><td>∞</td>
                    </tr>
                    <tr class="compare-group"><td colspan="4">Cálculos y métricas</td></tr>
                    <tr>
                        <td>Área y perímetro</td>
                        <td>✓</td><td class="td-featured">✓</td><td>✓</td>
                    </tr>
                    <tr>
                        <td>Volumen estimado</td>
                        <td>✓</td><td class="td-featured">✓</td><td>✓</td>
                    </tr>
                    <tr>
                        <td>Corte y relleno</td>
                        <td>—</td><td class="td-featured">✓</td><td>✓</td>
                    </tr>
                    <tr>
                        <td>Cotización editable</td>
                        <td>✓</td><td class="td-featured">✓</td><td>✓</td>
                    </tr>
                    <tr class="compare-group"><td colspan="4">Exportación</td></tr>
                    <tr>
                        <td>Exportar CSV</td>
                        <td>✓</td><td class="td-featured">✓</td><td>✓</td>
                    </tr>
                    <tr>
                        <td>Exportar PNG</td>
                        <td>—</td><td class="td-featured">✓</td><td>✓</td>
                    </tr>
                    <tr>
                        <td>Exportar PDF con membrete</td>
                        <td>—</td><td class="td-featured">✓</td><td>✓</td>
                    </tr>
                    <tr class="compare-group"><td colspan="4">Proyectos y cuenta</td></tr>
                    <tr>
                        <td>Proyectos guardados</td>
                        <td>—</td><td class="td-featured">✓</td><td>✓</td>
                    </tr>
                    <tr>
                        <td>Historial</td>
                        <td>—</td><td class="td-featured">✓</td><td>✓</td>
                    </tr>
                    <tr>
                        <td>Múltiples usuarios</td>
                        <td>—</td><td class="td-featured">—</td><td>✓</td>
                    </tr>
                    <tr>
                        <td>API REST</td>
                        <td>—</td><td class="td-featured">—</td><td>✓</td>
                    </tr>
                    <tr class="compare-group"><td colspan="4">Soporte</td></tr>
                    <tr>
                        <td>Soporte por email</td>
                        <td>—</td><td class="td-featured">✓</td><td>✓</td>
                    </tr>
                    <tr>
                        <td>Soporte 24/7</td>
                        <td>—</td><td class="td-featured">—</td><td>✓</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</section>

<!-- ==================== FAQ ==================== -->
<section class="faq-section">
    <div class="faq-inner">
        <span class="section-label">FAQ</span>
        <h2>Preguntas frecuentes</h2>

        <div class="faq-grid">
            <?php
            $faqs = [
                ["¿Puedo cambiar de plan en cualquier momento?",
                 "Sí. Puedes actualizar o bajar tu plan cuando quieras. Los cambios se aplican inmediatamente."],
                ["¿Qué pasa si supero el límite de 50 puntos en Free?",
                 "El módulo procesará hasta 50 puntos y mostrará una advertencia. Para archivos más grandes necesitas el plan Pro."],
                ["¿Necesito tarjeta de crédito para el plan Free?",
                 "No. El plan Free es completamente gratuito y no requiere ningún método de pago."],
                ["¿Cómo funciona la facturación anual?",
                 "Al elegir facturación anual obtienes un 20% de descuento. Se cobra un solo pago por adelantado por 12 meses."],
                ["¿Mis datos están seguros?",
                 "Sí. Los archivos CSV se procesan en memoria y no se almacenan en nuestros servidores. Solo guardamos las métricas calculadas."],
                ["¿Puedo cancelar en cualquier momento?",
                 "Sí. Sin contratos ni cargos por cancelación. Al cancelar conservas acceso hasta el fin del período pagado."],
            ];
            foreach ($faqs as [$q, $a]):
            ?>
            <div class="faq-item">
                <button class="faq-q">
                    <span><?= $q ?></span>
                    <span class="faq-arrow">↓</span>
                </button>
                <div class="faq-a">
                    <p><?= $a ?></p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ==================== CTA FINAL ==================== -->
<section class="cta-section">
    <div class="cta-inner">
        <h2>Empieza hoy, gratis.</h2>
        <p>Sin tarjeta de crédito. Sin límite de tiempo en el plan Free.</p>
        <div class="cta-buttons">
            <a href="register.php" class="btn btn-accent btn-lg">
                Crear cuenta gratuita →
            </a>
            <a href="proyecto.php" class="btn btn-outline btn-lg">
                Ver demostración
            </a>
        </div>
    </div>
</section>

<!-- ==================== FOOTER ==================== -->
<footer class="footer">
    <div class="footer-logo">FYLCAD</div>
    <p>© 2026 FYLCAD — Ingeniería Digital</p>
    <nav class="footer-links">
        <a href="#">Privacidad</a>
        <a href="#">Términos</a>
        <a href="mailto:contacto@fylcad.com">Contacto</a>
    </nav>
</footer>

<script>
/* ── Header scroll ── */
const header = document.getElementById("header");
window.addEventListener("scroll", () => {
    header.classList.toggle("scrolled", window.scrollY > 20);
});

/* ── Toggle facturación mensual / anual ── */
const switchEl   = document.getElementById("billingSwitch");
const optMensual = document.getElementById("optMensual");
const optAnual   = document.getElementById("optAnual");
const proPrecio  = document.getElementById("proPrecio");
const proCents   = document.getElementById("proCents");
const proPeriodo = document.getElementById("proPeriodo");
let esAnual = false;

switchEl.addEventListener("click", () => {
    esAnual = !esAnual;
    switchEl.classList.toggle("active", esAnual);
    optMensual.classList.toggle("active", !esAnual);
    optAnual.classList.toggle("active",   esAnual);

    if (esAnual) {
        proPrecio.textContent  = "7";
        proCents.textContent   = ".99";
        proPeriodo.textContent = "/ mes · facturado anualmente";
    } else {
        proPrecio.textContent  = "9";
        proCents.textContent   = ".99";
        proPeriodo.textContent = "/ mes";
    }
});

/* ── FAQ accordion ── */
document.querySelectorAll(".faq-q").forEach(btn => {
    btn.addEventListener("click", () => {
        const item   = btn.parentElement;
        const answer = item.querySelector(".faq-a");
        const arrow  = btn.querySelector(".faq-arrow");
        const open   = item.classList.contains("open");

        // Cerrar todos
        document.querySelectorAll(".faq-item").forEach(i => {
            i.classList.remove("open");
            i.querySelector(".faq-a").style.maxHeight  = "0";
            i.querySelector(".faq-arrow").style.transform = "rotate(0deg)";
        });

        // Abrir el clickeado si estaba cerrado
        if (!open) {
            item.classList.add("open");
            answer.style.maxHeight     = answer.scrollHeight + "px";
            arrow.style.transform      = "rotate(180deg)";
        }
    });
});
</script>


<script src="js/fylcad_ai_widget.js" data-pagina="planes"></script>
</body>
</html>