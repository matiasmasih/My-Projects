<?php
session_start();
require_once 'connection.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

$stmt = $conn->prepare("SELECT rooli, etunimi, sukunimi FROM jasenet WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($rooli, $etunimi, $sukunimi);
$stmt->fetch();
$stmt->close();

if ($rooli !== 'manager' && $rooli !== 'admin') {
    header("Location: user_dashboard.php");
    exit();
}

$admin_name = $etunimi . ' ' . $sukunimi;

// Get all receipts
$receipts_query = "SELECT k.*, 
                    j.etunimi, j.sukunimi, j.jasennumero, j.email,
                    CASE 
                        WHEN k.sakko_id IS NOT NULL THEN 'Sakko'
                        WHEN k.laina_id IS NOT NULL THEN 'Kirjalaina'
                        WHEN k.laitelaina_id IS NOT NULL THEN 'Laitelaina'
                        ELSE 'Maksu'
                    END as tyyppi
                    FROM kuitit k
                    LEFT JOIN jasenet j ON k.jasen_id = j.id
                    ORDER BY k.maksupaiva DESC";

$receipts_result = $conn->query($receipts_query);

// Get statistics
$total_receipts = 0;
$total_amount = 0;

$stats_query = "SELECT COUNT(*) as count, COALESCE(SUM(summa), 0) as total FROM kuitit WHERE tila = 'maksettu'";
$stats_result = $conn->query($stats_query);
if ($stats = $stats_result->fetch_assoc()) {
    $total_receipts = $stats['count'];
    $total_amount = $stats['total'];
}
?>

<!DOCTYPE html>
<html lang="fi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kuitit - Manager | Kirjasto</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="50" cy="50" r="40" fill="none" stroke="rgba(255,255,255,0.05)" stroke-width="2"/></svg>') repeat;
            opacity: 0.5;
            pointer-events: none;
            z-index: 0;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
            position: relative;
            z-index: 1;
        }

        /* Modern Header */
        .manager-header {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(15px);
            border-radius: 25px;
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            color: white;
            margin-bottom: 30px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }

        .logo h1 {
            font-size: 24px;
            font-weight: 700;
            background: linear-gradient(135deg, #fff, #f0f0f0);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .logo h1 i {
            background: linear-gradient(135deg, #fff, #f0f0f0);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-right: 10px;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .user-badge {
            background: rgba(255, 255, 255, 0.2);
            padding: 8px 18px;
            border-radius: 40px;
            font-size: 14px;
            backdrop-filter: blur(5px);
        }

        .user-badge i {
            margin-right: 8px;
        }

        .back-btn {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            padding: 8px 18px;
            border-radius: 40px;
            text-decoration: none;
            font-size: 14px;
            transition: all 0.3s;
            backdrop-filter: blur(5px);
        }

        .back-btn:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateY(-2px);
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 25px;
            margin-bottom: 40px;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(15px);
            border-radius: 25px;
            padding: 30px;
            text-align: center;
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: all 0.3s;
            animation: fadeInUp 0.6s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            background: rgba(255, 255, 255, 0.15);
            border-color: rgba(255, 255, 255, 0.4);
        }

        .stat-card i {
            font-size: 50px;
            background: linear-gradient(135deg, #fff, #f0f0f0);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 15px;
            display: inline-block;
        }

        .stat-card h3 {
            font-size: 36px;
            font-weight: 800;
            color: white;
            margin-bottom: 5px;
        }

        .stat-card p {
            color: rgba(255, 255, 255, 0.8);
            font-size: 14px;
        }

        /* Table Container */
        .table-container {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(15px);
            border-radius: 25px;
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.2);
            animation: fadeInUp 0.8s ease;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            background: rgba(0, 0, 0, 0.2);
            padding: 18px 15px;
            text-align: left;
            color: #fff;
            font-weight: 600;
            font-size: 14px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        td {
            padding: 15px;
            color: rgba(255, 255, 255, 0.9);
            font-size: 14px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }

        tr {
            transition: all 0.3s;
        }

        tr:hover {
            background: rgba(255, 255, 255, 0.05);
        }

        /* Type Badges */
        .type-badge {
            display: inline-block;
            padding: 5px 14px;
            border-radius: 30px;
            font-size: 12px;
            font-weight: 600;
        }

        .type-sakko {
            background: linear-gradient(135deg, rgba(220, 38, 38, 0.2), rgba(220, 38, 38, 0.1));
            color: #fca5a5;
            border: 1px solid rgba(220, 38, 38, 0.3);
        }

        .type-kirjalaina {
            background: linear-gradient(135deg, rgba(37, 99, 235, 0.2), rgba(37, 99, 235, 0.1));
            color: #93c5fd;
            border: 1px solid rgba(37, 99, 235, 0.3);
        }

        .type-laitelaina {
            background: linear-gradient(135deg, rgba(22, 163, 74, 0.2), rgba(22, 163, 74, 0.1));
            color: #86efac;
            border: 1px solid rgba(22, 163, 74, 0.3);
        }

        .type-maksu {
            background: linear-gradient(135deg, rgba(217, 119, 6, 0.2), rgba(217, 119, 6, 0.1));
            color: #fcd34d;
            border: 1px solid rgba(217, 119, 6, 0.3);
        }

        .amount {
            font-weight: 700;
            color: #fff;
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 8px;
        }

        .btn-print, .btn-client {
            background: rgba(255, 255, 255, 0.1);
            border: none;
            cursor: pointer;
            font-size: 14px;
            padding: 8px 14px;
            border-radius: 30px;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-weight: 500;
        }

        .btn-print {
            color: #93c5fd;
            border: 1px solid rgba(59, 130, 246, 0.3);
        }

        .btn-print:hover {
            background: #3b82f6;
            color: white;
            transform: translateY(-2px);
        }

        .btn-client {
            color: #86efac;
            border: 1px solid rgba(34, 197, 94, 0.3);
        }

        .btn-client:hover {
            background: #22c55e;
            color: white;
            transform: translateY(-2px);
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px;
            color: rgba(255, 255, 255, 0.6);
        }

        .empty-state i {
            font-size: 60px;
            margin-bottom: 20px;
            opacity: 0.5;
        }

        /* Animations */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Responsive */
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            th, td {
                padding: 10px;
                font-size: 12px;
            }
            
            .action-buttons {
                flex-direction: column;
                gap: 5px;
            }
            
            .btn-print, .btn-client {
                padding: 5px 10px;
                font-size: 11px;
            }
            
            .manager-header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="manager-header">
            <div class="logo">
                <h1><i class="fas fa-receipt"></i> Kuitit - Manager</h1>
            </div>
            <div class="user-info">
                <div class="user-badge">
                    <i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($admin_name); ?>
                </div>
                <a href="manager_dashboard.php" class="back-btn">
                    <i class="fas fa-arrow-left"></i> Takaisin
                </a>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <i class="fas fa-receipt"></i>
                <h3><?php echo $total_receipts; ?></h3>
                <p>Kuitteja yhteensä</p>
            </div>
            <div class="stat-card">
                <i class="fas fa-euro-sign"></i>
                <h3><?php echo number_format($total_amount, 2); ?> €</h3>
                <p>Maksettu yhteensä</p>
            </div>
        </div>

        <!-- Receipts Table -->
        <div class="table-container">
            <?php if ($receipts_result && $receipts_result->num_rows > 0): ?>
            <table id="receiptsTable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Jäsen</th>
                        <th>Jäsennumero</th>
                        <th>Tyyppi</th>
                        <th>Summa</th>
                        <th>Kuvaus</th>
                        <th>Maksupäivä</th>
                        <th>Toiminnot</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($receipt = $receipts_result->fetch_assoc()): ?>
                    <tr id="receipt-row-<?php echo $receipt['id']; ?>">
                        <td><?php echo $receipt['id']; ?></td>
                        <td><?php echo htmlspecialchars($receipt['etunimi'] . ' ' . $receipt['sukunimi']); ?></td>
                        <td><?php echo $receipt['jasennumero']; ?></td>
                        <td>
                            <span class="type-badge type-<?php echo strtolower($receipt['tyyppi']); ?>">
                                <?php echo $receipt['tyyppi']; ?>
                            </span>
                        </td>
                        <td class="amount"><?php echo number_format($receipt['summa'], 2); ?> €</td>
                        <td><?php echo htmlspecialchars($receipt['kuvaus']); ?></td>
                        <td><?php echo date('d.m.Y H:i', strtotime($receipt['maksupaiva'])); ?></td>
                        <td class="action-buttons">
                            <button class="btn-print" onclick="printReceipt(<?php echo $receipt['id']; ?>)">
                                <i class="fas fa-print"></i> Tulosta
                            </button>
                            <button class="btn-client" onclick="clientCopy(<?php echo $receipt['id']; ?>)">
                                <i class="fas fa-copy"></i> Asiakas
                            </button>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-receipt"></i>
                <p>Ei kuitteja vielä</p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function printReceipt(id) {
            window.open('user_print_receipt.php?id=' + id + '&mode=print', '_blank', 'width=500,height=600');
        }
        
        function clientCopy(id) {
            window.open('user_print_receipt.php?id=' + id + '&mode=client', '_blank', 'width=500,height=600');
        }
    </script>
</body>
</html>
