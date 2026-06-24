<?php
session_start();
require_once 'config.php';

// Check if logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin_login.php');
    exit();
}

// Get site info
$info_sql = "SELECT * FROM personal_info LIMIT 1";
$info_result = $conn->query($info_sql);
$info = $info_result->fetch_assoc();

// Get admin info
$admin_name = $_SESSION['admin_name'] ?? 'Admin';
$admin_username = $_SESSION['admin_username'] ?? 'admin';

// Get counts for badges
$total_messages = $conn->query("SELECT COUNT(*) as count FROM contact_messages")->fetch_assoc()['count'];
$unread_messages = $conn->query("SELECT COUNT(*) as count FROM contact_messages WHERE status = 'unread'")->fetch_assoc()['count'];
$total_certifications = $conn->query("SELECT COUNT(*) as count FROM certifications")->fetch_assoc()['count'];
$total_testimonials = $conn->query("SELECT COUNT(*) as count FROM testimonials WHERE is_visible = 1")->fetch_assoc()['count'];

// Handle delete post with AJAX support
if (isset($_GET['delete'])) {
    $delete_id = (int)$_GET['delete'];
    $delete_sql = "DELETE FROM blog_posts WHERE id = ?";
    $delete_stmt = $conn->prepare($delete_sql);
    $delete_stmt->bind_param("i", $delete_id);
    $delete_stmt->execute();

    if (isset($_GET['ajax'])) {
        echo json_encode(['success' => true]);
        exit();
    }
    header('Location: admin_dashboard.php?msg=deleted');
    exit();
}

// Get all blog posts
$posts_sql = "SELECT id, title, category, status, published_at, views FROM blog_posts ORDER BY published_at DESC";
$posts_result = $conn->query($posts_sql);
$posts = [];
$categories_count = [];
while($row = $posts_result->fetch_assoc()) {
    $posts[] = $row;
    $cat = $row['category'];
    $categories_count[$cat] = ($categories_count[$cat] ?? 0) + 1;
}

// Get statistics
$total_posts = count($posts);
$published_count = 0;
$total_views = 0;
foreach($posts as $post) {
    if($post['status'] == 'published') $published_count++;
    $total_views += $post['views'];
}
$draft_count = $total_posts - $published_count;

// Prepare category data for chart
$category_names = array_keys($categories_count);
$category_values = array_values($categories_count);
$category_colors = ['rgba(0, 229, 255, 0.9)', 'rgba(138, 43, 226, 0.9)', 'rgba(255, 107, 107, 0.9)', 'rgba(16, 185, 129, 0.9)', 'rgba(245, 158, 11, 0.9)', 'rgba(52, 152, 219, 0.9)', 'rgba(231, 76, 60, 0.9)', 'rgba(155, 89, 182, 0.9)', 'rgba(26, 188, 156, 0.9)', 'rgba(230, 126, 34, 0.9)'];
$category_border_colors = ['#00e5ff', '#8a2be2', '#ff6b6b', '#10b981', '#f59e0b', '#3498db', '#e74c3c', '#9b59b6', '#1abc9c', '#e67e22'];
?>

<!DOCTYPE html>
<html lang="fi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | <?php echo htmlspecialchars($info['full_name']); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
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
            min-height: 100vh;
            position: relative;
        }

        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: radial-gradient(circle at 20% 50%, rgba(0, 229, 255, 0.08), transparent 50%),
                        radial-gradient(circle at 80% 80%, rgba(138, 43, 226, 0.08), transparent 50%);
            z-index: -2;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 20px;
        }

        /* Header - ONE LINE NAVBAR */
        header {
            position: relative;
            width: 95%;
            max-width: 1400px;
            margin: 20px auto 0;
            padding: 10px 20px;
            border-radius: 50px;
            background: rgba(10, 12, 21, 0.85);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(0, 229, 255, 0.3);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
        }

        header .container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            flex-wrap: nowrap;
        }

        .logo {
            flex-shrink: 0;
        }

        .logo h1 {
            font-size: 18px;
            font-weight: 800;
            background: linear-gradient(135deg, #00e5ff, #8a2be2, #ff6b6b);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
            white-space: nowrap;
        }

        /* Admin Info - ALL IN ONE LINE */
        .admin-info {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: nowrap;
            justify-content: flex-end;
        }

        .admin-badge {
            background: rgba(0, 229, 255, 0.15);
            padding: 5px 12px;
            border-radius: 40px;
            font-size: 11px;
            border: 1px solid rgba(0, 229, 255, 0.3);
            white-space: nowrap;
        }

        .admin-badge i {
            color: #00e5ff;
            margin-right: 5px;
        }

        .nav-btn {
            background: rgba(0, 229, 255, 0.15);
            color: #00e5ff;
            padding: 5px 12px;
            border-radius: 40px;
            text-decoration: none;
            font-size: 11px;
            font-weight: 500;
            border: 1px solid rgba(0, 229, 255, 0.3);
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            white-space: nowrap;
        }

        .nav-btn:hover {
            background: #00e5ff;
            color: #010714;
            transform: translateY(-2px);
        }

        .nav-btn .badge {
            background: #ef4444;
            color: white;
            padding: 2px 5px;
            border-radius: 30px;
            font-size: 9px;
        }

        .logout-btn, .home-btn {
            padding: 5px 12px;
            border-radius: 40px;
            text-decoration: none;
            font-size: 11px;
            font-weight: 500;
            transition: all 0.3s;
            white-space: nowrap;
        }

        .logout-btn {
            background: rgba(239, 68, 68, 0.15);
            color: #ef4444;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }

        .logout-btn:hover {
            background: #ef4444;
            color: white;
        }

        .home-btn {
            background: rgba(0, 229, 255, 0.15);
            color: #00e5ff;
            border: 1px solid rgba(0, 229, 255, 0.3);
        }

        .home-btn:hover {
            background: #00e5ff;
            color: #010714;
        }

        .main-content {
            padding: 40px 0 60px;
        }

        .welcome-card {
            background: linear-gradient(135deg, rgba(0, 229, 255, 0.1), rgba(138, 43, 226, 0.1));
            backdrop-filter: blur(15px);
            border-radius: 30px;
            padding: 25px;
            margin-bottom: 30px;
            border: 1px solid rgba(0, 229, 255, 0.2);
        }

        .welcome-card h2 {
            font-size: 24px;
            margin-bottom: 8px;
            background: linear-gradient(135deg, #fff, #00e5ff);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .welcome-card p {
            color: rgba(255,255,255,0.7);
            font-size: 13px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: rgba(3, 11, 39, 0.6);
            backdrop-filter: blur(12px);
            border-radius: 20px;
            padding: 18px;
            text-align: center;
            border: 1px solid rgba(0, 229, 255, 0.15);
            transition: all 0.4s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            border-color: rgba(0, 229, 255, 0.4);
        }

        .stat-card i {
            font-size: 35px;
            background: linear-gradient(135deg, #00e5ff, #8a2be2);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 10px;
            display: inline-block;
        }

        .stat-card h3 {
            font-size: 28px;
            font-weight: 800;
            margin-bottom: 5px;
        }

        .stat-card p {
            color: rgba(255,255,255,0.6);
            font-size: 12px;
        }

        .quick-stats {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }

        .quick-stat-card {
            background: rgba(3, 11, 39, 0.6);
            backdrop-filter: blur(12px);
            border-radius: 20px;
            padding: 18px;
            text-align: center;
            border: 1px solid rgba(0, 229, 255, 0.15);
            transition: all 0.4s;
            text-decoration: none;
            display: block;
        }

        .quick-stat-card:hover {
            transform: translateY(-5px);
            border-color: rgba(0, 229, 255, 0.4);
        }

        .quick-stat-card i {
            font-size: 35px;
            background: linear-gradient(135deg, #00e5ff, #8a2be2);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 10px;
            display: inline-block;
        }

        .quick-stat-card h3 {
            font-size: 28px;
            font-weight: 800;
            margin-bottom: 5px;
            color: #fff;
        }

        .quick-stat-card p {
            color: rgba(255,255,255,0.6);
            font-size: 12px;
        }

        .charts-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }

        .chart-card {
            background: rgba(3, 11, 39, 0.6);
            backdrop-filter: blur(12px);
            border-radius: 20px;
            padding: 20px;
            border: 1px solid rgba(0, 229, 255, 0.2);
            transition: all 0.3s;
        }

        .chart-card:hover {
            transform: translateY(-3px);
            border-color: rgba(0, 229, 255, 0.4);
        }

        .chart-card h3 {
            margin-bottom: 15px;
            font-size: 16px;
            color: #00e5ff;
            text-align: center;
        }

        .chart-container {
            position: relative;
            height: 250px;
            width: 100%;
        }

        canvas {
            max-height: 220px;
            width: 100% !important;
        }

        .action-btn {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: linear-gradient(135deg, #00e5ff, #8a2be2);
            color: white;
            padding: 10px 24px;
            border-radius: 40px;
            text-decoration: none;
            font-weight: 600;
            margin-bottom: 25px;
            transition: all 0.4s;
            font-size: 13px;
        }

        .action-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(0, 229, 255, 0.4);
            gap: 15px;
        }

        .table-container {
            background: rgba(3, 11, 39, 0.6);
            backdrop-filter: blur(12px);
            border-radius: 20px;
            overflow-x: auto;
            border: 1px solid rgba(0, 229, 255, 0.15);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 700px;
        }

        th, td {
            padding: 10px 12px;
            text-align: left;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            font-size: 12px;
        }

        th {
            background: rgba(0, 229, 255, 0.08);
            color: #00e5ff;
            font-weight: 600;
        }

        tr:hover {
            background: rgba(0, 229, 255, 0.05);
        }

        .status-badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 30px;
            font-size: 10px;
            font-weight: 500;
        }

        .status-published {
            background: rgba(16, 185, 129, 0.2);
            color: #10b981;
            border: 1px solid rgba(16, 185, 129, 0.3);
        }

        .status-draft {
            background: rgba(245, 158, 11, 0.2);
            color: #f59e0b;
            border: 1px solid rgba(245, 158, 11, 0.3);
        }

        .action-btns {
            display: flex;
            gap: 6px;
        }

        .edit-btn, .delete-btn {
            padding: 4px 10px;
            border-radius: 30px;
            font-size: 10px;
            text-decoration: none;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .edit-btn {
            background: rgba(52, 152, 219, 0.2);
            color: #3498db;
            border: 1px solid rgba(52, 152, 219, 0.3);
        }

        .edit-btn:hover {
            background: #3498db;
            color: white;
        }

        .delete-btn {
            background: rgba(231, 76, 60, 0.2);
            color: #e74c3c;
            border: 1px solid rgba(231, 76, 60, 0.3);
        }

        .delete-btn:hover {
            background: #e74c3c;
            color: white;
        }

        .msg {
            background: rgba(16, 185, 129, 0.2);
            border: 1px solid #10b981;
            color: #10b981;
            padding: 10px 18px;
            border-radius: 18px;
            margin-bottom: 20px;
            font-size: 12px;
        }

        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(1, 7, 20, 0.9);
            backdrop-filter: blur(10px);
            z-index: 9999;
            display: flex;
            justify-content: center;
            align-items: center;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s;
        }

        .loading-overlay.active {
            opacity: 1;
            visibility: visible;
        }

        .loader {
            width: 40px;
            height: 40px;
            border: 3px solid rgba(0, 229, 255, 0.3);
            border-top-color: #00e5ff;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        @media (max-width: 1100px) {
            .stats-grid, .quick-stats {
                grid-template-columns: repeat(2, 1fr);
            }
            .admin-info {
                gap: 5px;
            }
            .nav-btn, .logout-btn, .home-btn, .admin-badge {
                padding: 4px 8px;
                font-size: 10px;
            }
        }

        @media (max-width: 992px) {
            .charts-row {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 900px) {
            header .container {
                flex-direction: column;
                gap: 10px;
            }
            .admin-info {
                justify-content: center;
                flex-wrap: wrap;
            }
        }

        @media (max-width: 768px) {
            .stats-grid, .quick-stats {
                grid-template-columns: 1fr;
            }
            .welcome-card h2 {
                font-size: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loader"></div>
    </div>

    <header>
        <div class="container">
            <div class="logo">
                <h1><?php echo htmlspecialchars($info['full_name']); ?></h1>
            </div>
            <div class="admin-info">
                <div class="admin-badge">
                    <i class="fas fa-user-shield"></i> <?php echo htmlspecialchars($admin_name); ?>
                </div>
                <a href="admin_messages.php" class="nav-btn">
                    <i class="fas fa-envelope"></i> Viestit
                    <?php if ($unread_messages > 0): ?>
                        <span class="badge"><?php echo $unread_messages; ?></span>
                    <?php endif; ?>
                </a>
                <a href="admin_certifications.php" class="nav-btn">
                    <i class="fas fa-certificate"></i> Sertifikaatit
                    <?php if ($total_certifications > 0): ?>
                        <span class="badge" style="background: #10b981;"><?php echo $total_certifications; ?></span>
                    <?php endif; ?>
                </a>
                <a href="admin_testimonials.php" class="nav-btn">
                    <i class="fas fa-star"></i> Palautteet
                    <?php if ($total_testimonials > 0): ?>
                        <span class="badge" style="background: #f59e0b;"><?php echo $total_testimonials; ?></span>
                    <?php endif; ?>
                </a>
                <a href="admin_settings.php" class="nav-btn">
                    <i class="fas fa-cog"></i> Asetukset
                </a>
                <a href="admin_logout.php" class="logout-btn" id="logoutBtn">
                    <i class="fas fa-sign-out-alt"></i> Kirjaudu ulos
                </a>
                <a href="index.php" class="home-btn">
                    <i class="fas fa-home"></i> Etusivulle
                </a>
            </div>
        </div>
    </header>

    <div class="main-content">
        <div class="container">
            <div class="welcome-card">
                <h2>Tervetuloa takaisin, <?php echo htmlspecialchars($admin_name); ?>! <i class="fas fa-smile-wink"></i></h2>
                <p><i class="fas fa-tachometer-alt"></i> Hallinnoi blogipostauksia, sertifikaatteja, viestejä, palautteita ja sivuston asetuksia.</p>
            </div>

            <?php if (isset($_GET['msg']) && $_GET['msg'] == 'deleted'): ?>
                <div class="msg" id="successMsg">
                    <i class="fas fa-check-circle"></i> Blogipostaus poistettu onnistuneesti!
                </div>
            <?php endif; ?>

            <div class="stats-grid">
                <div class="stat-card">
                    <i class="fas fa-blog"></i>
                    <h3 id="totalPosts"><?php echo $total_posts; ?></h3>
                    <p>Blogipostausta</p>
                </div>
                <div class="stat-card">
                    <i class="fas fa-check-circle"></i>
                    <h3 id="publishedCount"><?php echo $published_count; ?></h3>
                    <p>Julkaistu</p>
                </div>
                <div class="stat-card">
                    <i class="fas fa-pencil-alt"></i>
                    <h3 id="draftCount"><?php echo $draft_count; ?></h3>
                    <p>Luonnoksia</p>
                </div>
                <div class="stat-card">
                    <i class="fas fa-eye"></i>
                    <h3 id="totalViews"><?php echo $total_views ?: 0; ?></h3>
                    <p>Katselukertaa</p>
                </div>
            </div>

            <div class="quick-stats">
                <a href="admin_messages.php" class="quick-stat-card">
                    <i class="fas fa-envelope"></i>
                    <h3><?php echo $total_messages ?: 0; ?></h3>
                    <p>Viestiä yhteensä</p>
                </a>
                <a href="admin_messages.php?filter=unread" class="quick-stat-card">
                    <i class="fas fa-envelope-open"></i>
                    <h3><?php echo $unread_messages ?: 0; ?></h3>
                    <p>Lukemattomia</p>
                </a>
                <a href="admin_certifications.php" class="quick-stat-card">
                    <i class="fas fa-certificate"></i>
                    <h3><?php echo $total_certifications ?: 0; ?></h3>
                    <p>Sertifikaattia</p>
                </a>
                <a href="admin_testimonials.php" class="quick-stat-card">
                    <i class="fas fa-star"></i>
                    <h3><?php echo $total_testimonials ?: 0; ?></h3>
                    <p>Asiakaspalautetta</p>
                </a>
            </div>

            <div class="charts-row">
                <div class="chart-card">
                    <h3><i class="fas fa-chart-line"></i> Blogipostausten kehitys</h3>
                    <div class="chart-container">
                        <canvas id="postsChart"></canvas>
                    </div>
                </div>
                <div class="chart-card">
                    <h3><i class="fas fa-chart-pie"></i> Kategoriajakauma</h3>
                    <div class="chart-container">
                        <canvas id="categoriesChart"></canvas>
                    </div>
                </div>
            </div>

            <a href="admin_add_post.php" class="action-btn" id="addPostBtn">
                <i class="fas fa-plus-circle"></i> Luo uusi blogipostaus
                <i class="fas fa-arrow-right"></i>
            </a>

            <div class="table-container">
                <table id="postsTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Otsikko</th>
                            <th>Kategoria</th>
                            <th>Tila</th>
                            <th>Julkaistu</th>
                            <th>Katselut</th>
                            <th>Toiminnot</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($posts as $post): ?>
                        <tr id="post-row-<?php echo $post['id']; ?>">
                            <td><?php echo $post['id']; ?></td>
                            <td><strong><?php echo htmlspecialchars($post['title']); ?></strong></td>
                            <td><?php echo htmlspecialchars($post['category']); ?></td>
                            <td>
                                <span class="status-badge status-<?php echo $post['status']; ?>">
                                    <?php echo $post['status'] == 'published' ? 'Julkaistu' : 'Luonnos'; ?>
                                </span>
                            </td>
                            <td><?php echo date('d.m.Y', strtotime($post['published_at'])); ?></td>
                            <td><i class="fas fa-eye"></i> <?php echo $post['views']; ?></td>
                            <td class="action-btns">
                                <a href="admin_edit_post.php?id=<?php echo $post['id']; ?>" class="edit-btn">
                                    <i class="fas fa-edit"></i> Muokkaa
                                </a>
                                <a href="#" class="delete-btn" data-id="<?php echo $post['id']; ?>">
                                    <i class="fas fa-trash"></i> Poista
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        $(document).ready(function() {
            setTimeout(function() {
                $('#successMsg').fadeOut(500);
            }, 3000);

            var categoryNames = <?php echo json_encode($category_names); ?>;
            var categoryValues = <?php echo json_encode($category_values); ?>;
            var categoryColors = <?php echo json_encode($category_colors); ?>;
            
            // Bar Chart
            var ctx1 = document.getElementById('postsChart').getContext('2d');
            var gradient = ctx1.createLinearGradient(0, 0, 0, 250);
            gradient.addColorStop(0, 'rgba(0, 229, 255, 0.8)');
            gradient.addColorStop(1, 'rgba(0, 229, 255, 0.1)');
            
            new Chart(ctx1, {
                type: 'bar',
                data: {
                    labels: ['Blogipostaukset'],
                    datasets: [{
                        label: 'Postaukset',
                        data: [<?php echo $total_posts; ?>],
                        backgroundColor: gradient,
                        borderColor: '#00e5ff',
                        borderWidth: 2,
                        borderRadius: 12
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: { labels: { color: '#fff' } },
                        tooltip: { callbacks: { label: function(context) { return context.raw + ' postausta'; } } }
                    },
                    scales: {
                        y: { beginAtZero: true, grid: { color: 'rgba(255,255,255,0.08)' }, ticks: { color: '#fff' } },
                        x: { grid: { display: false }, ticks: { color: '#fff' } }
                    }
                }
            });

            // Pie Chart
            if (categoryNames.length > 0) {
                var ctx2 = document.getElementById('categoriesChart').getContext('2d');
                new Chart(ctx2, {
                    type: 'doughnut',
                    data: {
                        labels: categoryNames,
                        datasets: [{
                            data: categoryValues,
                            backgroundColor: categoryColors,
                            borderColor: '#010714',
                            borderWidth: 3,
                            cutout: '60%'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: true,
                        plugins: {
                            legend: { position: 'bottom', labels: { color: '#fff', font: { size: 10 } } },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        var label = context.label || '';
                                        var value = context.raw || 0;
                                        var total = categoryValues.reduce((a,b) => a + b, 0);
                                        var percentage = Math.round((value / total) * 100);
                                        return label + ': ' + value + ' (' + percentage + '%)';
                                    }
                                }
                            }
                        }
                    }
                });
            }

            // Delete post with AJAX
            $('.delete-btn').click(function(e) {
                e.preventDefault();
                var postId = $(this).data('id');
                var $row = $('#post-row-' + postId);

                if (confirm('Haluatko varmasti poistaa tämän blogipostauksen?')) {
                    $('#loadingOverlay').addClass('active');
                    $.ajax({
                        url: 'admin_dashboard.php?delete=' + postId + '&ajax=1',
                        method: 'GET',
                        success: function() {
                            $row.fadeOut(300, function() {
                                $(this).remove();
                                location.reload();
                            });
                        },
                        complete: function() {
                            $('#loadingOverlay').removeClass('active');
                        }
                    });
                }
            });

            $('#logoutBtn, #addPostBtn').click(function(e) {
                e.preventDefault();
                var href = $(this).attr('href');
                $('#loadingOverlay').addClass('active');
                setTimeout(function() { window.location.href = href; }, 500);
            });
        });
    </script>
</body>
</html>
