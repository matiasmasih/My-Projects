<?php
echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aziz Rahman | Creative Developer</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: "Inter", sans-serif;
            color: #ffffff;
            overflow-x: hidden;
            position: relative;
            min-height: 100vh;
        }

        /* DIRECT EMBEDDED BACKGROUND - NO EXTERNAL IMAGES NEEDED */
        .fixed-bg {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -2;
            background: linear-gradient(135deg, #0f0c29, #302b63, #24243e);
        }

        /* TECH GRID PATTERN - BUILT WITH CSS */
        .grid-pattern {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            background-image: 
                linear-gradient(rgba(0, 255, 255, 0.03) 1px, transparent 1px),
                linear-gradient(90deg, rgba(0, 255, 255, 0.03) 1px, transparent 1px);
            background-size: 50px 50px;
        }

        /* GLOWING ORBS */
        .orb-1 {
            position: fixed;
            top: 20%;
            left: -10%;
            width: 500px;
            height: 500px;
            background: radial-gradient(circle, rgba(0, 229, 255, 0.15), transparent 70%);
            border-radius: 50%;
            z-index: -1;
            animation: float 20s ease-in-out infinite;
        }

        .orb-2 {
            position: fixed;
            bottom: 10%;
            right: -10%;
            width: 600px;
            height: 600px;
            background: radial-gradient(circle, rgba(138, 43, 226, 0.15), transparent 70%);
            border-radius: 50%;
            z-index: -1;
            animation: float 25s ease-in-out infinite reverse;
        }

        @keyframes float {
            0%, 100% { transform: translate(0, 0); }
            50% { transform: translate(50px, 50px); }
        }

        /* Container */
        .container {
            max-width: 1280px;
            margin: 0 auto;
            padding: 0 32px;
            position: relative;
            z-index: 2;
        }

        /* Navigation */
        .navbar {
            padding: 32px 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }

        .logo {
            font-size: 28px;
            font-weight: 800;
            background: linear-gradient(135deg, #00e5ff, #8a2be2);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .nav-links {
            display: flex;
            gap: 40px;
            list-style: none;
        }

        .nav-links a {
            color: white;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s;
            position: relative;
        }

        .nav-links a::after {
            content: "";
            position: absolute;
            bottom: -5px;
            left: 0;
            width: 0;
            height: 2px;
            background: #00e5ff;
            transition: width 0.3s;
        }

        .nav-links a:hover::after {
            width: 100%;
        }

        /* Hero Section */
        .hero {
            min-height: 80vh;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
        }

        .hero-content {
            max-width: 800px;
        }

        .hero-badge {
            display: inline-block;
            background: rgba(0, 229, 255, 0.15);
            backdrop-filter: blur(10px);
            padding: 8px 20px;
            border-radius: 100px;
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 30px;
            border: 1px solid rgba(0, 229, 255, 0.3);
            color: #00e5ff;
        }

        h1 {
            font-size: 72px;
            font-weight: 800;
            line-height: 1.1;
            margin-bottom: 24px;
            letter-spacing: -0.02em;
        }

        .highlight {
            background: linear-gradient(135deg, #00e5ff, #8a2be2);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .hero-description {
            font-size: 18px;
            color: rgba(255,255,255,0.8);
            line-height: 1.6;
            margin-bottom: 40px;
        }

        /* CTA Buttons */
        .cta-group {
            display: flex;
            gap: 20px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn-primary {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 14px 32px;
            background: linear-gradient(135deg, #00e5ff, #8a2be2);
            color: white;
            border-radius: 100px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
        }

        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(0, 229, 255, 0.3);
            gap: 15px;
        }

        .btn-secondary {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 14px 32px;
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(0, 229, 255, 0.3);
            color: white;
            border-radius: 100px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
        }

        .btn-secondary:hover {
            background: rgba(0, 229, 255, 0.1);
            transform: translateY(-3px);
            gap: 15px;
        }

        /* Projects Section */
        .projects-section {
            padding: 80px 0;
        }

        .section-header {
            text-align: center;
            margin-bottom: 60px;
        }

        .section-title {
            font-size: 48px;
            font-weight: 700;
            margin-bottom: 16px;
        }

        .section-subtitle {
            font-size: 18px;
            color: rgba(255,255,255,0.7);
        }

        .projects-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 30px;
        }

        .project-card {
            background: rgba(255,255,255,0.05);
            backdrop-filter: blur(10px);
            border-radius: 24px;
            padding: 32px;
            transition: all 0.3s;
            border: 1px solid rgba(0, 229, 255, 0.1);
            cursor: pointer;
        }

        .project-card:hover {
            transform: translateY(-10px);
            background: rgba(255,255,255,0.08);
            border-color: rgba(0, 229, 255, 0.3);
        }

        .project-icon {
            width: 60px;
            height: 60px;
            background: rgba(0, 229, 255, 0.1);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 24px;
        }

        .project-icon i {
            font-size: 28px;
            color: #00e5ff;
        }

        .project-card h3 {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 12px;
        }

        .project-card p {
            color: rgba(255,255,255,0.7);
            line-height: 1.6;
            margin-bottom: 20px;
        }

        .project-tech {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 24px;
        }

        .tech-tag {
            background: rgba(0, 229, 255, 0.1);
            padding: 4px 12px;
            border-radius: 100px;
            font-size: 12px;
            font-weight: 500;
            color: #00e5ff;
        }

        .project-link {
            color: #00e5ff;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-weight: 500;
            transition: gap 0.3s;
        }

        .project-link:hover {
            gap: 12px;
        }

        /* Stats Section */
        .stats-section {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 30px;
            padding: 60px 0;
            border-top: 1px solid rgba(255,255,255,0.1);
            border-bottom: 1px solid rgba(255,255,255,0.1);
            margin: 40px 0;
        }

        .stat-card {
            text-align: center;
            padding: 24px;
        }

        .stat-number {
            font-size: 48px;
            font-weight: 800;
            background: linear-gradient(135deg, #00e5ff, #8a2be2);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 8px;
        }

        .stat-label {
            color: rgba(255,255,255,0.6);
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        /* Footer */
        .footer {
            padding: 48px 0 32px;
            text-align: center;
        }

        .social-links {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-bottom: 24px;
        }

        .social-links a {
            width: 48px;
            height: 48px;
            background: rgba(255,255,255,0.05);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            text-decoration: none;
            font-size: 20px;
            transition: all 0.3s;
            border: 1px solid rgba(0, 229, 255, 0.2);
        }

        .social-links a:hover {
            background: rgba(0, 229, 255, 0.2);
            transform: translateY(-3px) rotate(360deg);
            border-color: #00e5ff;
        }

        .copyright {
            color: rgba(255,255,255,0.5);
            font-size: 14px;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .container {
                padding: 0 20px;
            }
            h1 {
                font-size: 40px;
            }
            .projects-grid {
                grid-template-columns: 1fr;
            }
            .stats-section {
                grid-template-columns: repeat(2, 1fr);
            }
            .nav-links {
                display: none;
            }
        }
    </style>
</head>
<body>
    <!-- DIRECT EMBEDDED BACKGROUND - 100% WORKING -->
    <div class="fixed-bg"></div>
    <div class="grid-pattern"></div>
    <div class="orb-1"></div>
    <div class="orb-2"></div>

    <div class="container">
        <!-- Navigation -->
        <nav class="navbar">
            <div class="logo">AZIZ<span style="color:#00e5ff">.</span></div>
            <ul class="nav-links">
                <li><a href="#">Home</a></li>
                <li><a href="#projects">Projects</a></li>
                <li><a href="#contact">Contact</a></li>
            </ul>
        </nav>

        <!-- Hero Section -->
        <section class="hero">
            <div class="hero-content">
                <span class="hero-badge">✦ FULL-STACK DEVELOPER</span>
                <h1>
                    Creating digital<br>
                    <span class="highlight">experiences</span> that matter
                </h1>
                <p class="hero-description">
                    Hi, I\'m Aziz Rahman. I build web applications and digital solutions with focus on performance, usability, and modern design.
                </p>
                <div class="cta-group">
                    <a href="#projects" class="btn-primary">
                        <span>View My Work</span>
                        <i class="fas fa-arrow-down"></i>
                    </a>
                    <a href="#contact" class="btn-secondary">
                        <span>Contact Me</span>
                        <i class="fas fa-envelope"></i>
                    </a>
                </div>
            </div>
        </section>

        <!-- Stats Section -->
        <div class="stats-section">
            <div class="stat-card">
                <div class="stat-number">5+</div>
                <div class="stat-label">Projects</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">3+</div>
                <div class="stat-label">Years</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">10+</div>
                <div class="stat-label">Certifications</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">24/7</div>
                <div class="stat-label">Support</div>
            </div>
        </div>

        <!-- Projects Section -->
        <section id="projects" class="projects-section">
            <div class="section-header">
                <h2 class="section-title">Featured Projects</h2>
                <p class="section-subtitle">A collection of my best work</p>
            </div>

            <div class="projects-grid">
                <div class="project-card" onclick="window.location.href=\'/aziz_portfolio/\'">
                    <div class="project-icon">
                        <i class="fas fa-user-astronaut"></i>
                    </div>
                    <h3>Custom Portfolio</h3>
                    <p>Dynamic portfolio website with blog system, admin dashboard, and contact management.</p>
                    <div class="project-tech">
                        <span class="tech-tag">PHP</span>
                        <span class="tech-tag">MySQL</span>
                        <span class="tech-tag">JavaScript</span>
                    </div>
                    <a href="/aziz_portfolio/" class="project-link">
                        <span>View Project</span>
                        <i class="fas fa-arrow-right"></i>
                    </a>
                </div>

                <div class="project-card" onclick="window.location.href=\'/hazara/\'">
                    <div class="project-icon">
                        <i class="fab fa-wordpress"></i>
                    </div>
                    <h3>Finland Hazara Community</h3>
                    <p>A WordPress-based content management system featuring a custom theme, advanced plugins, and optimized performance for scalability and speed.</p>
                    <div class="project-tech">
                        <span class="tech-tag">WordPress</span>
                        <span class="tech-tag">PHP</span>
                        <span class="tech-tag">MySQL</span>
                    </div>
                    <a href="/hazara/" class="project-link">
                        <span>View Project</span>
                        <i class="fas fa-arrow-right"></i>
                    </a>
                </div>

                <div class="project-card" onclick="window.location.href=\'/Kirjasto/\'">
                    <div class="project-icon">
                        <i class="fas fa-book"></i>
                    </div>
                    <h3>Kirjasto</h3>
                    <p>Library management system for tracking books, members, loans, and returns.</p>
                    <div class="project-tech">
                        <span class="tech-tag">PHP</span>
                        <span class="tech-tag">MySQL</span>
                        <span class="tech-tag">Bootstrap</span>
                    </div>
                    <a href="/Kirjasto/" class="project-link">
                        <span>View Project</span>
                        <i class="fas fa-arrow-right"></i>
                    </a>
                </div>

                <div class="project-card" onclick="window.location.href=\'/Warehouse/\'">
                    <div class="project-icon">
                        <i class="fas fa-warehouse"></i>
                    </div>
                    <h3>Warehouse</h3>
                    <p>Warehouse inventory management system for stock tracking and order processing.</p>
                    <div class="project-tech">
                        <span class="tech-tag">PHP</span>
                        <span class="tech-tag">MySQL</span>
                        <span class="tech-tag">JavaScript</span>
                    </div>
                    <a href="/Warehouse/" class="project-link">
                        <span>View Project</span>
                        <i class="fas fa-arrow-right"></i>
                    </a>
                </div>

                <div class="project-card" onclick="window.location.href=\'/hospital_management/\'">
                    <div class="project-icon">
                        <i class="fas fa-hospital"></i>
                    </div>
                    <h3>Hospital Management</h3>
                    <p>Complete hospital system for patient records, appointments, and billing.</p>
                    <div class="project-tech">
                        <span class="tech-tag">PHP</span>
                        <span class="tech-tag">MySQL</span>
                        <span class="tech-tag">Bootstrap</span>
                    </div>
                    <a href="/hospital_management/" class="project-link">
                        <span>View Project</span>
                        <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
            </div>
        </section>

        <!-- Footer -->
        <footer class="footer" id="contact">
            <div class="social-links">
                <a href="#"><i class="fab fa-github"></i></a>
                <a href="#"><i class="fab fa-linkedin-in"></i></a>
                <a href="#"><i class="fab fa-twitter"></i></a>
                <a href="#"><i class="fab fa-instagram"></i></a>
                <a href="#"><i class="fab fa-discord"></i></a>
            </div>
            <p class="copyright">© 2026 Aziz Rahman. Built with 💜</p>
        </footer>
    </div>

    <script>
        // Scroll reveal animation
        const observerOptions = { threshold: 0.1, rootMargin: "0px 0px -50px 0px" };
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = "1";
                    entry.target.style.transform = "translateY(0)";
                }
            });
        }, observerOptions);

        document.querySelectorAll(".project-card, .stat-card").forEach(el => {
            el.style.opacity = "0";
            el.style.transform = "translateY(30px)";
            el.style.transition = "all 0.6s ease";
            observer.observe(el);
        });
    </script>
</body>
</html>';
?>
