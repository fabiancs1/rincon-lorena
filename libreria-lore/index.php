<?php
session_start();
$logueado      = isset($_SESSION['usuario_id']);
$nombreUsuario = $_SESSION['usuario_nombre'] ?? null;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>FYLCAD — Topografía Digital</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:ital,wght@0,300;0,400;0,500;1,300&display=swap" rel="stylesheet">

    <!-- Estilos -->
    <link rel="stylesheet" href="css/styles.css?v=3">
</head>
<body>

<!-- ==================== HEADER ==================== -->
<header class="header" id="header">
    <a href="index.php" class="logo">FYL<span>CAD</span></a>

    <nav class="nav">
        <a href="index.php">Inicio</a>
        <a href="#caracteristicas">Características</a>
        <a href="#como-funciona">Cómo Funciona</a>
        <a href="planes.php">Planes</a>
        <a href="#" id="btn-contacto" onclick="abrirContacto(event)">Contacto</a>
    </nav>

    <div class="header-actions">
        <?php if ($logueado): ?>
            <a href="dashboard.php" class="btn btn-ghost">
                <?= htmlspecialchars(explode(' ', $nombreUsuario)[0]) ?>
            </a>
            <a href="proyecto.php" class="btn btn-accent">Módulo 3D <span class="arrow">→</span></a>
        <?php else: ?>
            <a href="login.php"    class="btn btn-ghost">Iniciar sesión</a>
            <a href="register.php" class="btn btn-accent">Crear cuenta <span class="arrow">→</span></a>
        <?php endif; ?>
    </div>
</header>


<!-- ==================== HERO ==================== -->
<section class="hero">

    <!-- Fondo topográfico SVG -->
    <div class="topo-bg" aria-hidden="true">
        <svg viewBox="0 0 1200 800" preserveAspectRatio="xMidYMid slice" xmlns="http://www.w3.org/2000/svg">
            <defs>
                <style>.tl { fill: none; stroke: #00e5c0; stroke-width: .6; }</style>
            </defs>

            <!-- Curvas de nivel -->
            <ellipse class="tl" cx="900" cy="400" rx="520" ry="260"/>
            <ellipse class="tl" cx="900" cy="400" rx="440" ry="210"/>
            <ellipse class="tl" cx="900" cy="400" rx="360" ry="160"/>
            <ellipse class="tl" cx="900" cy="400" rx="280" ry="115"/>
            <ellipse class="tl" cx="900" cy="400" rx="200" ry="75"/>
            <ellipse class="tl" cx="900" cy="400" rx="120" ry="40"/>

            <ellipse class="tl" cx="200" cy="650" rx="320" ry="160"/>
            <ellipse class="tl" cx="200" cy="650" rx="240" ry="115"/>
            <ellipse class="tl" cx="200" cy="650" rx="160" ry="74"/>
            <ellipse class="tl" cx="200" cy="650" rx="90"  ry="42"/>

            <!-- Grid de puntos -->
            <g fill="#00e5c0" opacity=".4">
                <?php
                for ($row = 0; $row < 10; $row++) {
                    for ($col = 0; $col < 16; $col++) {
                        $x = 60 + $col * 75;
                        $y = 60 + $row * 75;
                        echo "                <circle cx=\"$x\" cy=\"$y\" r=\"1.2\"/>\n";
                    }
                }
                ?>
            </g>
        </svg>
    </div>

    <div class="hero-container">

        <!-- Columna izquierda: Texto -->
        <div class="hero-text">

            <div class="hero-badge">
                <span class="badge-dot"></span>
                Plataforma SaaS en la nube
            </div>

            <h1>
                Del plano digital<br>
                a la cotización de obra<br>
                en <em>minutos.</em>
            </h1>

            <p>
                Procesa datos topográficos, automatiza cálculos complejos
                y genera presupuestos reales — sin instalaciones ni equipos costosos.
            </p>

            <div class="hero-buttons">
                <a href="register.php" class="btn btn-accent btn-large">
                    Empezar gratis <span class="arrow">→</span>
                </a>
                <a href="proyecto.php" class="btn btn-outline btn-large">
                    Ver demostración
                </a>
            </div>

            <div class="hero-stats">
                <div class="stat">
                    <span class="stat-number">10×</span>
                    <span class="stat-label">Más rápido</span>
                </div>
                <div class="stat">
                    <span class="stat-number">99%</span>
                    <span class="stat-label">Precisión</span>
                </div>
                <div class="stat">
                    <span class="stat-number">0</span>
                    <span class="stat-label">Instalaciones</span>
                </div>
            </div>

        </div>

        <!-- Columna derecha: Visual -->
        <div class="hero-visual">

            <div class="chip chip-1">
                <div class="chip-icon green">📐</div>
                <div>
                    <div style="font-size:12px; color:#64748b;">Superficie calculada</div>
                    <div style="font-size:14px; font-weight:600; color:#fff;">4,820 m²</div>
                </div>
            </div>

            <div class="hero-panel">
                <img src="imagenes/port.png" alt="Plataforma FYLCAD — Vista del módulo 3D">
            </div>

            <div class="chip chip-2">
                <div class="chip-icon blue">💰</div>
                <div>
                    <div style="font-size:12px; color:#64748b;">Cotización generada</div>
                    <div style="font-size:14px; font-weight:600; color:#fff;">$38,200 USD</div>
                </div>
            </div>

        </div>

    </div>
</section>


<!-- ==================== FEATURES ==================== -->
<section class="features" id="caracteristicas">

    <div class="features-header">
        <span class="section-label">Características</span>
        <h2 class="section-title">Todo lo que necesitas,<br>sin complicaciones.</h2>
        <p class="section-desc">
            FYLCAD centraliza el flujo completo: desde la lectura de coordenadas
            hasta la generación del presupuesto final.
        </p>
    </div>

    <div class="features-grid">

        <div class="feature-card fade-up">
            <div class="feature-icon">⚙️</div>
            <h3>Cálculos Automáticos</h3>
            <p>
                Procesa superficies, distancias y pendientes de forma instantánea.
                Cero cálculos manuales, cero errores de transcripción.
            </p>
            <span class="feature-tag">✔ Ahorra horas de trabajo</span>
        </div>

        <div class="feature-card fade-up">
            <div class="feature-icon">📊</div>
            <h3>Cotización Inteligente</h3>
            <p>
                Convierte métricas técnicas del terreno en presupuestos reales
                con un solo clic. Decisiones financieras basadas en datos.
            </p>
            <span class="feature-tag">✔ Presupuestos en minutos</span>
        </div>

        <div class="feature-card fade-up">
            <div class="feature-icon">🔗</div>
            <h3>Diseño → Obra</h3>
            <p>
                Del plano técnico a la ejecución real del proyecto sin reprocesos
                ni pérdida de información entre etapas.
            </p>
            <span class="feature-tag">✔ Flujo integral sin fricciones</span>
        </div>

    </div>

</section>


<!-- ==================== CÓMO FUNCIONA ==================== -->
<section class="features" id="como-funciona" style="background: var(--bg);">

    <div class="features-header">
        <span class="section-label">Proceso</span>
        <h2 class="section-title">¿Cómo funciona FYLCAD?</h2>
        <p class="section-desc">
            Tres pasos simples que transforman tus datos topográficos
            en un presupuesto completo, listo para presentar.
        </p>
    </div>

    <div class="features-grid">

        <div class="feature-card fade-up">
            <div class="feature-icon">📁</div>
            <h3>Carga tus coordenadas</h3>
            <p>
                Importa puntos topográficos en formato CSV, TXT o ingresa
                coordenadas X, Y, Z manualmente. Compatible con cualquier
                estación total o GPS.
            </p>
            <span class="feature-tag">✔ CSV, TXT y Excel soportados</span>
        </div>

        <div class="feature-card fade-up">
            <div class="feature-icon">⚡</div>
            <h3>Procesamiento automático</h3>
            <p>
                FYLCAD calcula superficies, volúmenes de corte y relleno,
                pendientes y curvas de nivel en segundos, con visualización
                3D interactiva incluida.
            </p>
            <span class="feature-tag">✔ Resultados en tiempo real</span>
        </div>

        <div class="feature-card fade-up">
            <div class="feature-icon">📋</div>
            <h3>Genera tu cotización</h3>
            <p>
                Convierte los cálculos técnicos en un presupuesto profesional
                con precios unitarios y memoria de cálculo lista para
                entregar al cliente.
            </p>
            <span class="feature-tag">✔ Exporta a PDF en un clic</span>
        </div>

    </div>

</section>


<!-- ==================== CTA ==================== -->
<section class="cta">
    <div class="cta-inner">
        <span class="section-label">Empieza hoy</span>
        <h2>Tu próximo proyecto<br>comienza aquí.</h2>
        <p>
            Crea tu cuenta gratuita en segundos y procesa tu primer
            archivo topográfico hoy mismo.
        </p>
        <div class="cta-buttons">
            <a href="register.php" class="btn btn-accent btn-large">
                Crear cuenta gratuita <span class="arrow">→</span>
            </a>
            <a href="proyecto.php" class="btn btn-outline btn-large">
                Ver módulo 3D
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
        <a href="#">Contacto</a>
    </nav>
</footer>


<!-- ==================== JS ==================== -->
<script>
    // Header scroll
    const header = document.getElementById("header");
    window.addEventListener("scroll", () => {
        header.classList.toggle("scrolled", window.scrollY > 20);
    });

    // Modal Contacto
    function abrirContacto(e) {
        if (e) e.preventDefault();
        const modal = document.getElementById("modal-contacto");
        modal.style.display = "flex";
        document.body.style.overflow = "hidden";
    }
    function cerrarContacto(e) {
        if (e && e.target !== document.getElementById("modal-contacto") && !e.target.classList.contains("modal-close")) return;
        const modal = document.getElementById("modal-contacto");
        modal.style.display = "none";
        document.body.style.overflow = "";
    }
    document.addEventListener("keydown", (e) => {
        if (e.key === "Escape") cerrarContacto({ target: document.getElementById("modal-contacto") });
    });

    // Footer contacto link
    document.querySelectorAll('a[href="#"]').forEach(a => {
        if (a.textContent.trim() === "Contacto") {
            a.addEventListener("click", abrirContacto);
        }
    });


</script>



<!-- ==================== MODAL CONTACTO ==================== -->
<div id="modal-contacto" class="modal-overlay" style="display:none;" onclick="cerrarContacto(event)">
    <div class="modal-box">
        <button class="modal-close" onclick="cerrarContacto()">✕</button>

        <div class="modal-header">
            <div class="modal-badge">
                <span class="badge-dot"></span> Respondemos en menos de 24h
            </div>
            <h2>Hablemos de<br>tu proyecto</h2>
            <p>¿Tienes dudas, necesitas una demo personalizada o quieres conocer un plan a medida? Escríbenos.</p>
        </div>

        <div class="modal-contacts">
            <a href="mailto:contacto@fylcad.com" class="contact-item" target="_blank">
                <div class="contact-icon">✉️</div>
                <div>
                    <div class="contact-label">Email</div>
                    <div class="contact-value">contacto@fylcad.com</div>
                </div>
                <div class="contact-arrow">→</div>
            </a>

            <a href="https://wa.me/5491100000000" class="contact-item" target="_blank">
                <div class="contact-icon">💬</div>
                <div>
                    <div class="contact-label">WhatsApp</div>
                    <div class="contact-value">+54 9 11 0000-0000</div>
                </div>
                <div class="contact-arrow">→</div>
            </a>

            <a href="https://linkedin.com/company/fylcad" class="contact-item" target="_blank">
                <div class="contact-icon">🔗</div>
                <div>
                    <div class="contact-label">LinkedIn</div>
                    <div class="contact-value">FYLCAD — Ingeniería Digital</div>
                </div>
                <div class="contact-arrow">→</div>
            </a>
        </div>

        <div class="modal-footer-note">
            También puedes <a href="planes.php">ver nuestros planes</a> o <a href="register.php">crear una cuenta gratuita</a> directamente.
        </div>
    </div>
</div>



<script src="js/fylcad_ai_widget.js" data-pagina="index"></script>
</body>
</html>