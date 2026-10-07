<?php
session_start();
require_once 'connection.php';

// Check if logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Get user info
$stmt = $conn->prepare("SELECT rooli, etunimi, sukunimi, email, profile_image FROM jasenet WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($rooli, $profile_image, $etunimi, $sukunimi, $email);
$stmt->fetch();
$stmt->close();

// ONLY MANAGER can access this page
if ($rooli !== 'manager') {
    if ($rooli === 'admin') {
        header("Location: admin_dashboard.php");
    } else {
        header("Location: user_dashboard.php");
    }
    exit();
}

$admin_name = $etunimi . ' ' . $sukunimi;

// ============================================
// REPORTS DATA
// ============================================

// 1. BOOK LOANS STATISTICS
$total_book_loans = 0;
$active_book_loans = 0;
$returned_book_loans = 0;
$overdue_book_loans = 0;

$result = $conn->query("SELECT COUNT(*) as count FROM lainat");
if ($result) { $total_book_loans = $result->fetch_assoc()['count']; }

$result = $conn->query("SELECT COUNT(*) as count FROM lainat WHERE tila = 'aktiivinen'");
if ($result) { $active_book_loans = $result->fetch_assoc()['count']; }

$result = $conn->query("SELECT COUNT(*) as count FROM lainat WHERE tila = 'palautettu'");
if ($result) { $returned_book_loans = $result->fetch_assoc()['count']; }

$result = $conn->query("SELECT COUNT(*) as count FROM lainat WHERE tila = 'myohassa'");
if ($result) { $overdue_book_loans = $result->fetch_assoc()['count']; }

// 2. DEVICE LOANS STATISTICS
$total_device_loans = 0;
$active_device_loans = 0;
$returned_device_loans = 0;
$overdue_device_loans = 0;
$device_fines_total = 0;

$result = $conn->query("SELECT COUNT(*) as count FROM Laitelainat");
if ($result) { $total_device_loans = $result->fetch_assoc()['count']; }

$result = $conn->query("SELECT COUNT(*) as count FROM Laitelainat WHERE palautus_pvm IS NULL");
if ($result) { $active_device_loans = $result->fetch_assoc()['count']; }

$result = $conn->query("SELECT COUNT(*) as count FROM Laitelainat WHERE palautus_pvm IS NOT NULL");
if ($result) { $returned_device_loans = $result->fetch_assoc()['count']; }

$result = $conn->query("SELECT COUNT(*) as count FROM Laitelainat WHERE palautus_pvm IS NULL AND erapaiva < NOW()");
if ($result) { $overdue_device_loans = $result->fetch_assoc()['count']; }

$result = $conn->query("SELECT COALESCE(SUM(myohastyymismaksu), 0) as total FROM Laitelainat");
if ($result) { $device_fines_total = $result->fetch_assoc()['total']; }

// 3. BOOK STATISTICS
$total_books = 0;
$total_book_copies = 0;
$available_copies = 0;

$result = $conn->query("SELECT COUNT(*) as count FROM kirjat");
if ($result) { $total_books = $result->fetch_assoc()['count']; }

$result = $conn->query("SELECT COUNT(*) as count FROM Kirjakopiot");
if ($result) { $total_book_copies = $result->fetch_assoc()['count']; }

$result = $conn->query("SELECT COUNT(*) as count FROM Kirjakopiot WHERE tila = 'saatavilla'");
if ($result) { $available_copies = $result->fetch_assoc()['count']; }

// 4. DEVICE STATISTICS
$total_devices = 0;
$available_devices = 0;
$maintenance_devices = 0;

$result = $conn->query("SELECT COUNT(*) as count FROM Laitteet");
if ($result) { $total_devices = $result->fetch_assoc()['count']; }

$result = $conn->query("SELECT COUNT(*) as count FROM Laitteet WHERE tila = 'saatavilla'");
if ($result) { $available_devices = $result->fetch_assoc()['count']; }

$result = $conn->query("SELECT COUNT(*) as count FROM Laitteet WHERE tila = 'huoltotila'");
if ($result) { $maintenance_devices = $result->fetch_assoc()['count']; }

// 5. USER STATISTICS
$total_users = 0;
$active_users = 0;
$admin_users = 0;
$manager_users = 0;
$regular_users = 0;

$result = $conn->query("SELECT COUNT(*) as count FROM jasenet");
if ($result) { $total_users = $result->fetch_assoc()['count']; }

$result = $conn->query("SELECT COUNT(*) as count FROM jasenet WHERE tila = 'aktiivinen'");
if ($result) { $active_users = $result->fetch_assoc()['count']; }

$result = $conn->query("SELECT COUNT(*) as count FROM jasenet WHERE rooli = 'admin'");
if ($result) { $admin_users = $result->fetch_assoc()['count']; }

$result = $conn->query("SELECT COUNT(*) as count FROM jasenet WHERE rooli = 'manager'");
if ($result) { $manager_users = $result->fetch_assoc()['count']; }

$result = $conn->query("SELECT COUNT(*) as count FROM jasenet WHERE rooli = 'user'");
if ($result) { $regular_users = $result->fetch_assoc()['count']; }

// 6. FINE STATISTICS
$total_fines = 0;
$unpaid_fines = 0;
$total_fine_amount = 0;
$unpaid_fine_amount = 0;

$result = $conn->query("SELECT COUNT(*) as count, COALESCE(SUM(sakko_maara), 0) as total FROM sakot");
if ($result) { 
    $data = $result->fetch_assoc();
    $total_fines = $data['count'];
    $total_fine_amount = $data['total'];
}

$result = $conn->query("SELECT COUNT(*) as count, COALESCE(SUM(sakko_maara - maksettu_maara), 0) as total FROM sakot WHERE tila IN ('maksettava', 'osittain')");
if ($result) { 
    $data = $result->fetch_assoc();
    $unpaid_fines = $data['count'];
    $unpaid_fine_amount = $data['total'];
}

// 7. TODAY'S ACTIVITY
$today_loans = 0;
$today_returns = 0;

$today = date('Y-m-d');
$result = $conn->query("SELECT COUNT(*) as count FROM lainat WHERE DATE(lainauspaiva) = '$today'");
if ($result) { $today_loans += $result->fetch_assoc()['count']; }
$result = $conn->query("SELECT COUNT(*) as count FROM Laitelainat WHERE DATE(lainaus_pvm) = '$today'");
if ($result) { $today_loans += $result->fetch_assoc()['count']; }

$result = $conn->query("SELECT COUNT(*) as count FROM lainat WHERE DATE(palautuspaiva) = '$today'");
if ($result) { $today_returns += $result->fetch_assoc()['count']; }
$result = $conn->query("SELECT COUNT(*) as count FROM Laitelainat WHERE DATE(palautus_pvm) = '$today'");
if ($result) { $today_returns += $result->fetch_assoc()['count']; }
?>

<!DOCTYPE html>
<html lang="fi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Raportit - Manager | Kirjasto</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        body {
            background: #f0f2f5;
            min-height: 100vh;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }

        /* Header */
        .manager-header {
            background: linear-gradient(135deg, #2c3e50, #1a2632);
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            color: white;
        }

        .logo h1 {
            font-size: 24px;
        }

        .logo h1 i {
            color: #3498db;
            margin-right: 10px;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .user-badge {
            background: rgba(255,255,255,0.2);
            padding: 8px 16px;
            border-radius: 30px;
            font-size: 14px;
        }

        .back-btn {
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 8px 16px;
            border-radius: 8px;
            text-decoration: none;
            transition: all 0.3s;
        }

        .back-btn:hover {
            background: #3498db;
        }

        /* Section Titles */
        .section-title {
            margin: 30px 0 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #3498db;
        }

        .section-title h2 {
            color: #2c3e50;
            font-size: 22px;
        }

        .section-title h2 i {
            color: #3498db;
            margin-right: 10px;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            text-align: center;
            transition: transform 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }

        .stat-card i {
            font-size: 40px;
            color: #3498db;
            margin-bottom: 10px;
        }

        .stat-card h3 {
            font-size: 32px;
            color: #2c3e50;
        }

        .stat-card p {
            color: #7f8c8d;
            font-size: 14px;
        }

        /* Two Column Layout */
        .two-columns {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
            margin-bottom: 30px;
        }

        .report-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .report-card h3 {
            color: #2c3e50;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #ecf0f1;
        }

        .report-card h3 i {
            color: #3498db;
            margin-right: 8px;
        }

        .stat-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #f0f0f0;
        }

        .stat-label {
            color: #7f8c8d;
        }

        .stat-value {
            font-weight: 600;
            color: #2c3e50;
        }

        .stat-value.highlight {
            color: #3498db;
            font-size: 18px;
        }

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            .two-columns {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="manager-header">
        <div class="logo">
            <h1><i class="fas fa-chart-bar"></i> Raportit - Manager</h1>
        </div>
        <div class="user-info">
            <div class="user-badge">
                <i class="fas fa-user"></i> <?php echo htmlspecialchars($admin_name); ?>
            </div>
            <a href="manager_dashboard.php" class="back-btn">
                <i class="fas fa-arrow-left"></i> Takaisin
            </a>
        </div>
    </div>

    <div class="container">
        <!-- Quick Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <i class="fas fa-book"></i>
                <h3><?php echo $total_books; ?></h3>
                <p>Kirjoja</p>
            </div>
            <div class="stat-card">
                <i class="fas fa-laptop"></i>
                <h3><?php echo $total_devices; ?></h3>
                <p>Laitteita</p>
            </div>
            <div class="stat-card">
                <i class="fas fa-users"></i>
                <h3><?php echo $active_users; ?>/<?php echo $total_users; ?></h3>
                <p>Aktiivisia jäseniä</p>
            </div>
            <div class="stat-card">
                <i class="fas fa-euro-sign"></i>
                <h3><?php echo number_format($unpaid_fine_amount, 2); ?> €</h3>
                <p>Maksamattomia sakkoja</p>
            </div>
        </div>

        <!-- Loan Statistics -->
        <div class="section-title">
            <h2><i class="fas fa-chart-line"></i> Lainatilastot</h2>
        </div>
        <div class="two-columns">
            <div class="report-card">
                <h3><i class="fas fa-book"></i> Kirjalainat</h3>
                <div class="stat-row">
                    <span class="stat-label">Lainoja yhteensä:</span>
                    <span class="stat-value"><?php echo $total_book_loans; ?></span>
                </div>
                <div class="stat-row">
                    <span class="stat-label">Aktiivisia lainoja:</span>
                    <span class="stat-value"><?php echo $active_book_loans; ?></span>
                </div>
                <div class="stat-row">
                    <span class="stat-label">Palautettuja:</span>
                    <span class="stat-value"><?php echo $returned_book_loans; ?></span>
                </div>
                <div class="stat-row">
                    <span class="stat-label">Myöhässä:</span>
                    <span class="stat-value"><?php echo $overdue_book_loans; ?></span>
                </div>
            </div>
            <div class="report-card">
                <h3><i class="fas fa-mobile-alt"></i> Laitelainat</h3>
                <div class="stat-row">
                    <span class="stat-label">Lainoja yhteensä:</span>
                    <span class="stat-value"><?php echo $total_device_loans; ?></span>
                </div>
                <div class="stat-row">
                    <span class="stat-label">Aktiivisia lainoja:</span>
                    <span class="stat-value"><?php echo $active_device_loans; ?></span>
                </div>
                <div class="stat-row">
                    <span class="stat-label">Palautettuja:</span>
                    <span class="stat-value"><?php echo $returned_device_loans; ?></span>
                </div>
                <div class="stat-row">
                    <span class="stat-label">Myöhässä:</span>
                    <span class="stat-value"><?php echo $overdue_device_loans; ?></span>
                </div>
                <div class="stat-row">
                    <span class="stat-label">Myöhästymismaksut:</span>
                    <span class="stat-value highlight"><?php echo number_format($device_fines_total, 2); ?> €</span>
                </div>
            </div>
        </div>

        <!-- Collection Statistics -->
        <div class="section-title">
            <h2><i class="fas fa-database"></i> Kokoelmatilastot</h2>
        </div>
        <div class="two-columns">
            <div class="report-card">
                <h3><i class="fas fa-book"></i> Kirjat</h3>
                <div class="stat-row">
                    <span class="stat-label">Erilaisia kirjoja:</span>
                    <span class="stat-value"><?php echo $total_books; ?></span>
                </div>
                <div class="stat-row">
                    <span class="stat-label">Kirjakappaleita yhteensä:</span>
                    <span class="stat-value"><?php echo $total_book_copies; ?></span>
                </div>
                <div class="stat-row">
                    <span class="stat-label">Saatavilla olevia kappaleita:</span>
                    <span class="stat-value"><?php echo $available_copies; ?></span>
                </div>
                <div class="stat-row">
                    <span class="stat-label">Lainassa olevia kappaleita:</span>
                    <span class="stat-value"><?php echo $total_book_copies - $available_copies; ?></span>
                </div>
            </div>
            <div class="report-card">
                <h3><i class="fas fa-laptop"></i> Laitteet</h3>
                <div class="stat-row">
                    <span class="stat-label">Laitteita yhteensä:</span>
                    <span class="stat-value"><?php echo $total_devices; ?></span>
                </div>
                <div class="stat-row">
                    <span class="stat-label">Saatavilla:</span>
                    <span class="stat-value"><?php echo $available_devices; ?></span>
                </div>
                <div class="stat-row">
                    <span class="stat-label">Lainassa:</span>
                    <span class="stat-value"><?php echo $total_devices - $available_devices - $maintenance_devices; ?></span>
                </div>
                <div class="stat-row">
                    <span class="stat-label">Huollossa:</span>
                    <span class="stat-value"><?php echo $maintenance_devices; ?></span>
                </div>
            </div>
        </div>

        <!-- User Statistics -->
        <div class="section-title">
            <h2><i class="fas fa-users"></i> Käyttäjätilastot</h2>
        </div>
        <div class="two-columns">
            <div class="report-card">
                <h3><i class="fas fa-user-friends"></i> Jäsenet</h3>
                <div class="stat-row">
                    <span class="stat-label">Jäseniä yhteensä:</span>
                    <span class="stat-value"><?php echo $total_users; ?></span>
                </div>
                <div class="stat-row">
                    <span class="stat-label">Aktiivisia jäseniä:</span>
                    <span class="stat-value"><?php echo $active_users; ?></span>
                </div>
            </div>
            <div class="report-card">
                <h3><i class="fas fa-user-tag"></i> Roolijakauma</h3>
                <div class="stat-row">
                    <span class="stat-label">Ylläpitäjät:</span>
                    <span class="stat-value"><?php echo $admin_users; ?></span>
                </div>
                <div class="stat-row">
                    <span class="stat-label">Managerit:</span>
                    <span class="stat-value"><?php echo $manager_users; ?></span>
                </div>
                <div class="stat-row">
                    <span class="stat-label">Tavalliset käyttäjät:</span>
                    <span class="stat-value"><?php echo $regular_users; ?></span>
                </div>
            </div>
        </div>

        <!-- Fine Statistics -->
        <div class="section-title">
            <h2><i class="fas fa-euro-sign"></i> Sakkotilastot</h2>
        </div>
        <div class="two-columns">
            <div class="report-card">
                <h3><i class="fas fa-receipt"></i> Sakot</h3>
                <div class="stat-row">
                    <span class="stat-label">Sakkoja yhteensä:</span>
                    <span class="stat-value"><?php echo $total_fines; ?></span>
                </div>
                <div class="stat-row">
                    <span class="stat-label">Maksamattomia sakkoja:</span>
                    <span class="stat-value"><?php echo $unpaid_fines; ?></span>
                </div>
                <div class="stat-row">
                    <span class="stat-label">Sakkojen kokonaismäärä:</span>
                    <span class="stat-value highlight"><?php echo number_format($total_fine_amount, 2); ?> €</span>
                </div>
                <div class="stat-row">
                    <span class="stat-label">Maksamatta yhteensä:</span>
                    <span class="stat-value highlight"><?php echo number_format($unpaid_fine_amount, 2); ?> €</span>
                </div>
            </div>
            <div class="report-card">
                <h3><i class="fas fa-calendar-day"></i> Tämän päivän aktiviteetit</h3>
                <div class="stat-row">
                    <span class="stat-label">Uusia lainoja tänään:</span>
                    <span class="stat-value"><?php echo $today_loans; ?></span>
                </div>
                <div class="stat-row">
                    <span class="stat-label">Palautuksia tänään:</span>
                    <span class="stat-value"><?php echo $today_returns; ?></span>
                </div>
                <div class="stat-row">
                    <span class="stat-label">Päivämäärä:</span>
                    <span class="stat-value"><?php echo date('d.m.Y'); ?></span>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
