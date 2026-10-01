<?php
// blog.php - Blog page
require_once 'config.php';

// Get personal information for header
$info_sql = "SELECT * FROM personal_info LIMIT 1";
$info_result = $conn->query($info_sql);
$info = $info_result->fetch_assoc();

// Get social links for footer
$social_sql = "SELECT * FROM social_links ORDER BY display_order";
$social_result = $conn->query($social_sql);

// Get blog posts from database
$blog_sql = "SELECT * FROM blog_posts WHERE status = 'published' ORDER BY published_at DESC";
$blog_result = $conn->query($blog_sql);
?>

<!DOCTYPE html>
<html lang="fi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Blogi - <?php echo htmlspecialchars($info['full_name']); ?>">
    <title>Blogi | <?php echo htmlspecialchars($info['full_name']); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        body {
            background: #010714;
            color: #fff;
            line-height: 1.6;
            background-image: url('https://images.unsplash.com/photo-1519681393784-d120267933ba?ixlib=rb-4.0.3&auto=format&fit=crop&w=2070&q=80');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            position: relative;
        }

        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(1, 7, 20, 0.85);
            z-index: -1;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 15px;
        }

        /* Header */
        header {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            padding: 20px 0;
            z-index: 1000;
            transition: all 0.3s ease;
        }

        header.sticky {
            background: rgba(1, 7, 20, 0.95);
            padding: 15px 0;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.5);
        }

        header .container {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo h1 {
            font-size: 28px;
            font-weight: 700;
            background: linear-gradient(45deg, #00e5ff, #8a2be2);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        nav ul {
            display: flex;
            list-style: none;
        }

        nav ul li {
            margin-left: 30px;
        }

        nav ul li a {
            color: #fff;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s;
        }

        nav ul li a:hover,
        nav ul li a.active {
            color: #00e5ff;
        }

        .menu-toggle {
            display: none;
            font-size: 24px;
            cursor: pointer;
        }

        /* Blog Section */
        .blog-section {
            padding: 120px 0 80px;
        }

        .section-title {
            text-align: center;
            margin-bottom: 50px;
        }

        .section-title h2 {
            font-size: 42px;
            font-weight: 700;
            background: linear-gradient(45deg, #fff, #00e5ff);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .blog-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 30px;
        }

        .blog-card {
            background: rgba(3, 11, 39, 0.8);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            overflow: hidden;
            transition: all 0.3s;
            border: 1px solid rgba(0, 229, 255, 0.1);
        }

        .blog-card:hover {
            transform: translateY(-10px);
            border-color: #00e5ff;
            box-shadow: 0 15px 35px rgba(0, 229, 255, 0.2);
        }

        .blog-image {
            height: 220px;
            overflow: hidden;
        }

        .blog-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s;
        }

        .blog-card:hover .blog-image img {
            transform: scale(1.1);
        }

        .blog-content {
            padding: 25px;
        }

        .blog-date {
            font-size: 12px;
            color: #00e5ff;
            margin-bottom: 10px;
            display: inline-block;
            background: rgba(0, 229, 255, 0.1);
            padding: 4px 12px;
            border-radius: 20px;
        }

        .blog-title {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 15px;
            line-height: 1.4;
        }

        .blog-title a {
            color: #fff;
            text-decoration: none;
            transition: color 0.3s;
        }

        .blog-title a:hover {
            color: #00e5ff;
        }

        .blog-excerpt {
            font-size: 14px;
            opacity: 0.8;
            margin-bottom: 20px;
            line-height: 1.6;
        }

        .blog-category {
            display: inline-block;
            font-size: 12px;
            color: #8a2be2;
            margin-bottom: 15px;
        }

        .read-more {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #00e5ff;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: gap 0.3s;
        }

        .read-more:hover {
            gap: 12px;
        }

        .no-posts {
            text-align: center;
            padding: 60px;
            background: rgba(3, 11, 39, 0.8);
            border-radius: 20px;
        }

        .no-posts i {
            font-size: 48px;
            color: #00e5ff;
            margin-bottom: 20px;
        }

        /* Footer */
        footer {
            background: rgba(1, 7, 20, 0.9);
            padding: 30px 0;
            text-align: center;
        }

        .footer-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }

        .footer-social {
            display: flex;
            gap: 15px;
        }

        .footer-social a {
            display: inline-flex;
            justify-content: center;
            align-items: center;
            width: 40px;
            height: 40px;
            background: transparent;
            border: 2px solid #00e5ff;
            border-radius: 50%;
            color: #00e5ff;
            transition: all 0.3s;
            text-decoration: none;
        }

        .footer-social a:hover {
            background: #00e5ff;
            color: #010714;
            transform: translateY(-3px);
        }

        /* Scroll Top */
        .scroll-top {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 40px;
            height: 40px;
            background: linear-gradient(45deg, #00e5ff, #8a2be2);
            display: flex;
            justify-content: center;
            align-items: center;
            border-radius: 50%;
            color: #fff;
            opacity: 0;
            pointer-events: none;
            transition: all 0.3s;
            text-decoration: none;
        }

        .scroll-top.active {
            opacity: 1;
            pointer-events: auto;
        }

        /* Responsive */
        @media (max-width: 991px) {
            .menu-toggle {
                display: block;
            }
            nav {
                position: fixed;
                top: 80px;
                left: -100%;
                width: 100%;
                background: rgba(1, 7, 20, 0.95);
                padding: 20px;
                transition: all 0.5s;
            }
            nav.active {
                left: 0;
            }
            nav ul {
                flex-direction: column;
                align-items: center;
            }
            nav ul li {
                margin: 15px 0;
            }
            .blog-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .footer-content {
                flex-direction: column;
                text-align: center;
            }
            .section-title h2 {
                font-size: 32px;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header>
        <div class="container">
            <div class="logo">
                <h1><?php echo htmlspecialchars($info['full_name']); ?></h1>
            </div>
             <nav>
                <ul>
                  <li><a href="index.php">Koti</a></li>
                  <li><a href="index.php#about">Tietoa minusta</a></li>
                  <li><a href="index.php#education">Koulutus</a></li>
                  <li><a href="index.php#experience">Työkokemus</a></li>
                  <li><a href="index.php#skills">Taidot</a></li>
                  <li><a href="index.php#projects">Projektit</a></li>
                  <li><a href="blog.php" class="active">Blogi</a></li>
                  <li><a href="index.php#contact">Ota yhteyttä</a></li>
               </ul>
            </nav>
            <div class="menu-toggle">
                <i class="fas fa-bars"></i>
            </div>
        </div>
    </header>

    <!-- Blog Section -->
    <section class="blog-section">
        <div class="container">
            <div class="section-title">
                <h2>Blogi</h2>
                <p style="color: rgba(255,255,255,0.7); margin-top: 10px;">Ajatuksia, oppeja ja teknologiaa</p>
            </div>

            <div class="blog-grid">
                <?php if ($blog_result && $blog_result->num_rows > 0): ?>
                    <?php while($post = $blog_result->fetch_assoc()): ?>
                        <div class="blog-card">
                            <?php if(!empty($post['featured_image'])): ?>
                                <div class="blog-image">
                                    <img src="<?php echo htmlspecialchars($post['featured_image']); ?>" alt="<?php echo htmlspecialchars($post['title']); ?>">
                                </div>
                            <?php endif; ?>
                            <div class="blog-content">
                                <div class="blog-date">
                                    <i class="far fa-calendar-alt"></i>
                                    <?php echo date('d.m.Y H:i', strtotime($post['published_at'])); ?>
                                </div>
                                <?php if(!empty($post['category'])): ?>
                                    <div class="blog-category">
                                        <i class="fas fa-tag"></i> <?php echo htmlspecialchars($post['category']); ?>
                                    </div>
                                <?php endif; ?>
                                <h3 class="blog-title">
                                    <a href="blog_post.php?id=<?php echo $post['id']; ?>"><?php echo htmlspecialchars($post['title']); ?></a>
                                </h3>
                                <p class="blog-excerpt"><?php echo htmlspecialchars(substr($post['excerpt'], 0, 120)) . '...'; ?></p>
                                <a href="blog_post.php?id=<?php echo $post['id']; ?>" class="read-more">
                                    Lue lisää <i class="fas fa-arrow-right"></i>
                                </a>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="no-posts">
                        <i class="fas fa-blog"></i>
                        <h3>Ei blogipostauksia vielä</h3>
                        <p style="margin-top: 10px;">Tule pian takaisin, ensimmäinen blogipostaus on tulossa!</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer>
        <div class="container">
            <div class="footer-content">
                <p>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($info['full_name']); ?>. Kaikki oikeudet pidätetään.</p>
                <div class="footer-social">
                    <?php
                    $social_result = $conn->query("SELECT * FROM social_links ORDER BY display_order");
                    while($social = $social_result->fetch_assoc()):
                    ?>
                        <a href="<?php echo htmlspecialchars($social['url']); ?>" target="_blank">
                            <?php if($social['platform'] == 'Teams'): ?>
                                <i class="fab fa-microsoft"></i>
                            <?php elseif($social['platform'] == 'WhatsApp'): ?>
                                <i class="fab fa-whatsapp"></i>
                            <?php elseif($social['platform'] == 'GitHub'): ?>
                                <i class="fab fa-github"></i>
                            <?php elseif($social['platform'] == 'LinkedIn'): ?>
                                <i class="fab fa-linkedin-in"></i>
                            <?php else: ?>
                                <i class="<?php echo htmlspecialchars($social['icon_class']); ?>"></i>
                            <?php endif; ?>
                        </a>
                    <?php endwhile; ?>
                </div>
            </div>
        </div>
    </footer>

    <a href="#" class="scroll-top">
        <i class="fas fa-arrow-up"></i>
    </a>

    <script>
        $(document).ready(function() {
            // Sticky Header
            $(window).on('scroll', function() {
                if ($(this).scrollTop() > 100) {
                    $('header').addClass('sticky');
                    $('.scroll-top').addClass('active');
                } else {
                    $('header').removeClass('sticky');
                    $('.scroll-top').removeClass('active');
                }
            });

            // Scroll to top
            $('.scroll-top').on('click', function(e) {
                e.preventDefault();
                $('html, body').animate({scrollTop: 0}, 800);
            });

            // Mobile menu toggle
            $('.menu-toggle').click(function() {
                $('nav').toggleClass('active');
                $(this).find('i').toggleClass('fa-bars fa-times');
            });

            // Close menu on link click
            $('nav ul li a').click(function() {
                $('nav').removeClass('active');
                $('.menu-toggle i').removeClass('fa-times').addClass('fa-bars');
            });
        });
    </script>
</body>
</html>
