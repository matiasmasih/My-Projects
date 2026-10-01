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
$admin_initials = strtoupper(substr($etunimi, 0, 1) . substr($sukunimi, 0, 1));

// Get all fines for the library
$fines_query = "SELECT s.*, 
                j.etunimi, j.sukunimi, j.email, j.jasennumero,
                'Kirjalaina' as laina_tyyppi
                FROM sakot s
                LEFT JOIN jasenet j ON s.jasen_id = j.id
                ORDER BY s.sakko_paiva DESC";

$fines_result = $conn->query($fines_query);

// Get statistics
$stats = [
    'total_fines' => 0,
    'unpaid_fines' => 0,
    'paid_fines' => 0,
    'total_amount' => 0,
    'unpaid_amount' => 0
];

$unpaid_query = "SELECT COUNT(*) as count, COALESCE(SUM(sakko_maara - maksettu_maara), 0) as total 
                 FROM sakot WHERE tila IN ('maksettava', 'osittain')";
$unpaid_result = $conn->query($unpaid_query);
if ($unpaid = $unpaid_result->fetch_assoc()) {
    $stats['unpaid_fines'] = $unpaid['count'];
    $stats['unpaid_amount'] = $unpaid['total'];
}

$paid_query = "SELECT COUNT(*) as count, COALESCE(SUM(maksettu_maara), 0) as total 
               FROM sakot WHERE tila = 'maksettu'";
$paid_result = $conn->query($paid_query);
if ($paid = $paid_result->fetch_assoc()) {
    $stats['paid_fines'] = $paid['count'];
}

$total_query = "SELECT COUNT(*) as count, COALESCE(SUM(sakko_maara), 0) as total FROM sakot";
$total_result = $conn->query($total_query);
if ($total = $total_result->fetch_assoc()) {
    $stats['total_fines'] = $total['count'];
    $stats['total_amount'] = $total['total'];
}
?>

<!DOCTYPE html>
<html lang="fi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sakkojen hallinta | Manager</title>
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
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
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

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 30px;
            margin-top: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            text-align: center;
        }

        .stat-card i {
            font-size: 40px;
            color: #3498db;
            margin-bottom: 10px;
        }

        .stat-card h3 {
            font-size: 28px;
            color: #2c3e50;
        }

        .stat-card p {
            color: #7f8c8d;
            font-size: 14px;
        }

        /* Table */
        .table-container {
            background: white;
            border-radius: 15px;
            overflow-x: auto;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #ecf0f1;
        }

        th {
            background: #f8f9fa;
            color: #2c3e50;
            font-weight: 600;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }

        .status-maksettava {
            background: #fef3c7;
            color: #d97706;
        }

        .status-maksettu {
            background: #d1fae5;
            color: #10b981;
        }

        .status-osittain {
            background: #fed7aa;
            color: #ea580c;
        }

        .btn-mark-paid {
            background: #10b981;
            color: white;
            border: none;
            padding: 5px 12px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 12px;
        }

        .btn-mark-paid:hover {
            background: #059669;
        }

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>
<body>
    <div class="manager-header">
        <div class="logo">
            <h1><i class="fas fa-euro-sign"></i> Sakkojen hallinta - Manager</h1>
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
        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <i class="fas fa-receipt"></i>
                <h3><?php echo $stats['total_fines']; ?></h3>
                <p>Sakkoja yhteensä</p>
            </div>
            <div class="stat-card">
                <i class="fas fa-clock"></i>
                <h3><?php echo $stats['unpaid_fines']; ?></h3>
                <p>Maksamattomia</p>
            </div>
            <div class="stat-card">
                <i class="fas fa-check-circle"></i>
                <h3><?php echo $stats['paid_fines']; ?></h3>
                <p>Maksettuja</p>
            </div>
            <div class="stat-card">
                <i class="fas fa-coins"></i>
                <h3><?php echo number_format($stats['unpaid_amount'], 2); ?> €</h3>
                <p>Maksamatta yhteensä</p>
            </div>
        </div>

        <!-- Fines Table -->
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Jäsen</th>
                        <th>Jäsennumero</th>
                        <th>Laina tyyppi</th>
                        <th>Sakko (€)</th>
                        <th>Maksettu (€)</th>
                        <th>Päivämäärä</th>
                        <th>Tila</th>
                        <th>Toiminnot</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($fine = $fines_result->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo $fine['id']; ?></td>
                        <td><?php echo htmlspecialchars($fine['etunimi'] . ' ' . $fine['sukunimi']); ?></td>
                        <td><?php echo $fine['jasennumero']; ?></td>
                        <td><?php echo $fine['laina_tyyppi']; ?></td>
                        <td><?php echo number_format($fine['sakko_maara'], 2); ?></td>
                        <td><?php echo number_format($fine['maksettu_maara'], 2); ?></td>
                        <td><?php echo date('d.m.Y', strtotime($fine['sakko_paiva'])); ?></td>
                        <td>
                            <span class="status-badge status-<?php echo $fine['tila']; ?>">
                                <?php 
                                    if($fine['tila'] == 'maksettava') echo 'Maksamaton';
                                    elseif($fine['tila'] == 'maksettu') echo 'Maksettu';
                                    else echo 'Osittain maksettu';
                                ?>
                            </span>
                        </td>
                        <td>
                            <?php if($fine['tila'] != 'maksettu'): ?>
                            <form method="POST" action="manager_mark_paid.php" style="display:inline;">
                                <input type="hidden" name="fine_id" value="<?php echo $fine['id']; ?>">
                                <button type="submit" class="btn-mark-paid" onclick="return confirm('Merkitse maksetuksi?')">
                                    <i class="fas fa-check"></i> Merkitse maksetuksi
                                </button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
