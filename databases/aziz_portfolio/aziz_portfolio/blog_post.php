<?php
// blog_post.php - Single blog post view
require_once 'config.php';

// Get blog post ID from URL
$post_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Get personal information for header
$info_sql = "SELECT * FROM personal_info LIMIT 1";
$info_result = $conn->query($info_sql);
$info = $info_result->fetch_assoc();

// Get social links for footer
$social_sql = "SELECT * FROM social_links ORDER BY display_order";
$social_result = $conn->query($social_sql);

// Get single blog post
$post_sql = "SELECT * FROM blog_posts WHERE id = ? AND status = 'published'";
$post_stmt = $conn->prepare($post_sql);
$post_stmt->bind_param("i", $post_id);
$post_stmt->execute();
$post_result = $post_stmt->get_result();
$post = $post_result->fetch_assoc();

// If post not found, redirect to blog page
if (!$post) {
    header('Location: blog.php');
    exit();
}

// Update view count
$update_sql = "UPDATE blog_posts SET views = views + 1 WHERE id = ?";
$update_stmt = $conn->prepare($update_sql);
$update_stmt->bind_param("i", $post_id);
$update_stmt->execute();
?>

<!DOCTYPE html>
<html lang="fi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?php echo htmlspecialchars($post['title']); ?>">
    <title><?php echo htmlspecialchars($post['title']); ?> | <?php echo htmlspecialchars($info['full_name']); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
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
            padding: 0 20px;
        }

        /* ========================================
           STYLISH HEADER BOX
        ========================================= */
        header {
            position: relative;
            width: 90%;
            max-width: 1300px;
            margin: 20px auto 0;
            padding: 12px 25px;
            z-index: 1000;
            transition: all 0.4s ease;
            border-radius: 60px;
            background: rgba(10, 12, 21, 0.85);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(0, 229, 255, 0.3);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
        }

        header .container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            width: 100%;
        }

        /* Logo Styling */
        .logo {
            position: relative;
            flex-shrink: 0;
        }

        .logo h1 {
            font-size: 24px;
            font-weight: 800;
            background: linear-gradient(135deg, #00e5ff, #8a2be2, #ff6b6b);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
            transition: all 0.3s;
            white-space: nowrap;
        }

        .logo::after {
            content: '';
            position: absolute;
            bottom: -5px;
            left: 0;
            width: 100%;
            height: 2px;
            background: linear-gradient(90deg, #00e5ff, #8a2be2);
            transform: scaleX(0);
            transition: transform 0.3s;
            border-radius: 2px;
        }

        .logo:hover::after {
            transform: scaleX(1);
        }

        /* Navigation - ALL ITEMS IN ONE LINE */
        nav {
            display: flex;
            align-items: center;
            flex: 1;
            justify-content: flex-end;
        }

        nav ul {
            display: flex;
            list-style: none;
            gap: 5px;
            margin: 0;
            padding: 0;
            flex-wrap: nowrap;
        }

        nav ul li {
            margin: 0;
            white-space: nowrap;
        }

        nav ul li a {
            color: #fff;
            text-decoration: none;
            font-weight: 500;
            padding: 8px 16px;
            border-radius: 40px;
            transition: all 0.3s;
            font-size: 13px;
            letter-spacing: 0.3px;
            position: relative;
            white-space: nowrap;
            display: inline-block;
        }

        nav ul li a::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, rgba(0, 229, 255, 0.2), rgba(138, 43, 226, 0.2));
            border-radius: 40px;
            opacity: 0;
            transition: opacity 0.3s;
            z-index: -1;
        }

        nav ul li a:hover::before,
        nav ul li a.active::before {
            opacity: 1;
        }

        nav ul li a:hover,
        nav ul li a.active {
            color: #00e5ff;
            transform: translateY(-2px);
        }

        /* Menu Toggle Button */
        .menu-toggle {
            display: none;
            font-size: 24px;
            cursor: pointer;
            color: #00e5ff;
            background: rgba(0, 229, 255, 0.1);
            width: 42px;
            height: 42px;
            border-radius: 50%;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
            border: 1px solid rgba(0, 229, 255, 0.3);
            flex-shrink: 0;
        }

        .menu-toggle:hover {
            background: rgba(0, 229, 255, 0.2);
            transform: scale(1.05);
        }

        /* Blog Post */
        .blog-post {
            padding: 60px 0 80px;
        }

        .post-header {
            text-align: center;
            margin-bottom: 40px;
        }

        .post-category {
            display: inline-block;
            background: rgba(0, 229, 255, 0.15);
            color: #00e5ff;
            padding: 5px 15px;
            border-radius: 30px;
            font-size: 12px;
            margin-bottom: 20px;
        }

        .post-title {
            font-size: 42px;
            font-weight: 700;
            margin-bottom: 20px;
            background: linear-gradient(45deg, #fff, #00e5ff);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .post-meta {
            display: flex;
            justify-content: center;
            gap: 20px;
            color: rgba(255, 255, 255, 0.6);
            font-size: 14px;
            flex-wrap: wrap;
        }

        .post-meta i {
            color: #00e5ff;
            margin-right: 5px;
        }

        .post-content {
            background: rgba(3, 11, 39, 0.7);
            backdrop-filter: blur(10px);
            padding: 40px;
            border-radius: 20px;
            border: 1px solid rgba(0, 229, 255, 0.1);
        }

        .post-content p {
            margin-bottom: 20px;
            line-height: 1.8;
        }

        .post-content h2 {
            margin: 30px 0 15px;
            color: #00e5ff;
        }

        .back-to-blog {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            margin-top: 30px;
            color: #00e5ff;
            text-decoration: none;
            font-weight: 500;
            transition: gap 0.3s;
        }

        .back-to-blog:hover {
            gap: 15px;
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

        /* ========================================
           RESPONSIVE DESIGN
        ========================================= */
        @media (max-width: 1100px) {
            nav ul {
                gap: 3px;
            }
            nav ul li a {
                padding: 8px 12px;
                font-size: 12px;
            }
        }

        @media (max-width: 991px) {
            header {
                width: 95%;
                padding: 10px 20px;
                margin: 15px auto 0;
            }

            .menu-toggle {
                display: flex;
            }

            nav {
                position: fixed;
                top: 80px;
                left: -100%;
                width: 85%;
                max-width: 300px;
                background: rgba(10, 12, 21, 0.98);
                backdrop-filter: blur(25px);
                padding: 20px;
                transition: all 0.5s ease;
                border-radius: 25px;
                border: 1px solid rgba(0, 229, 255, 0.3);
                box-shadow: 0 15px 35px rgba(0, 0, 0, 0.3);
                flex: none;
            }

            nav.active {
                left: 10px;
            }

            nav ul {
                flex-direction: column;
                align-items: center;
                gap: 12px;
                width: 100%;
            }

            nav ul li {
                width: 100%;
                text-align: center;
            }

            nav ul li a {
                display: block;
                width: 100%;
                text-align: center;
                padding: 12px 20px;
                white-space: normal;
                font-size: 14px;
            }

            .post-title {
                font-size: 32px;
            }

            .post-content {
                padding: 25px;
            }
            
            .logo h1 {
                font-size: 20px;
            }
        }

        @media (max-width: 768px) {
            .footer-content {
                flex-direction: column;
                text-align: center;
            }
            
            .post-title {
                font-size: 28px;
            }
            
            .logo h1 {
                font-size: 18px;
            }
            
            .menu-toggle {
                width: 38px;
                height: 38px;
                font-size: 18px;
            }
        }

        @media (max-width: 480px) {
            header {
                padding: 8px 15px;
            }
            
            .logo h1 {
                font-size: 16px;
            }
            
            .menu-toggle {
                width: 35px;
                height: 35px;
                font-size: 16px;
            }
            
            .post-title {
                font-size: 24px;
            }
            
            .post-content {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
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

    <section class="blog-post">
        <div class="container">
            <div class="post-header">
                <div class="post-category">
                    <i class="fas fa-tag"></i> <?php echo htmlspecialchars($post['category']); ?>
                </div>
                <h1 class="post-title"><?php echo htmlspecialchars($post['title']); ?></h1>
                <div class="post-meta">
                    <span><i class="far fa-calendar-alt"></i> <?php echo date('d.m.Y', strtotime($post['published_at'])); ?></span>
                    <span><i class="far fa-eye"></i> <?php echo $post['views']; ?> katselukertaa</span>
                </div>
            </div>
            <div class="post-content">
                <?php echo $post['content']; ?>
            </div>
            <a href="blog.php" class="back-to-blog">
                <i class="fas fa-arrow-left"></i> Takaisin blogiin
            </a>
        </div>
    </section>

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
    // Simple mobile menu toggle
    document.querySelector('.menu-toggle').addEventListener('click', function() {
        document.querySelector('nav').classList.toggle('active');
        this.querySelector('i').classList.toggle('fa-bars');
        this.querySelector('i').classList.toggle('fa-times');
    });

    // Scroll to top
    document.querySelector('.scroll-top').addEventListener('click', function(e) {
        e.preventDefault();
        window.scrollTo({top: 0, behavior: 'smooth'});
    });

    // Sticky header on scroll
    window.addEventListener('scroll', function() {
        if (window.scrollY > 100) {
            document.querySelector('header').classList.add('sticky');
            document.querySelector('.scroll-top').classList.add('active');
        } else {
            document.querySelector('header').classList.remove('sticky');
            document.querySelector('.scroll-top').classList.remove('active');
        }
    });
</script>
</body>
</html>
