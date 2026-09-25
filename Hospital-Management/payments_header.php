<?php
// payments_header.php - Header for the shared Payments list page
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include_once 'access_control.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$access = my_access('payments');
if ($access === 'no') {
    header("Location: login.php");
    exit;
}

$viewer_name = trim(($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['last_name'] ?? ''));
$role_names = [
    'manager' => 'Manager',
    'admin'   => 'Admin',
    'doctor'  => 'Doctor',
    'staff'   => 'Staff',
    'patient' => 'Patient',
];
$viewer_role = $role_names[current_role()] ?? 'User';

$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title ?? 'Payments'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #14b8a6;
            --primary-dark: #0d9488;
            --primary-light: #5eead4;
            --bg: #f0fdfa;
            --card-bg: #ffffff;
            --text: #1e293b;
            --text-muted: #64748b;
            --border: #e2e8f0;
            --sidebar-bg: #0f172a;
            --sidebar-hover: #1e293b;
            --shadow: 0 4px 20px rgba(0,0,0,0.06);
            --shadow-lg: 0 10px 40px rgba(0,0,0,0.1);
            --radius: 16px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        [data-theme="dark"] {
            --bg: #0f172a;
            --card-bg: #1e293b;
            --text: #f1f5f9;
            --text-muted: #94a3b8;
            --border: #334155;
            --shadow: 0 4px 20px rgba(0,0,0,0.3);
            --shadow-lg: 0 10px 40px rgba(0,0,0,0.5);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg);
            color: var(--text);
            transition: var(--transition);
            overflow-x: hidden;
        }

        .navbar {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            padding: 14px 0;
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: 0 4px 20px rgba(20, 184, 166, 0.25);
        }

        .navbar-brand {
            font-weight: 700;
            font-size: 1.4rem;
            color: white !important;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .navbar .nav-link {
            color: rgba(255,255,255,0.9) !important;
            font-weight: 500;
            padding: 8px 16px !important;
            border-radius: 10px;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .navbar .nav-link:hover {
            background: rgba(255,255,255,0.15);
            color: white !important;
            transform: translateY(-2px);
        }

        .theme-toggle {
            background: rgba(255,255,255,0.15);
            border: none;
            color: white;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: var(--transition);
            font-size: 1.1rem;
        }

        .theme-toggle:hover {
            background: rgba(255,255,255,0.25);
            transform: rotate(20deg) scale(1.1);
        }

        .main-container { display: flex; min-height: calc(100vh - 68px); }

        .sidebar {
            background: var(--sidebar-bg);
            color: white;
            width: 240px;
            padding: 25px 0;
            transition: var(--transition);
            box-shadow: 4px 0 20px rgba(0,0,0,0.08);
            position: sticky;
            top: 68px;
            height: calc(100vh - 68px);
            overflow-y: auto;
        }

        .sidebar::-webkit-scrollbar { width: 8px; }
        .sidebar::-webkit-scrollbar-track { background: rgba(0,0,0,0.1); border-radius: 4px; }
        .sidebar::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.35); border-radius: 4px; }
        .sidebar::-webkit-scrollbar-thumb:hover { background: rgba(255,255,255,0.55); }

        .sidebar-header {
            padding: 0 25px 20px;
            border-bottom: 1px solid rgba(255,255,255,0.08);
            margin-bottom: 15px;
        }

        .sidebar-header h4 {
            font-weight: 700;
            color: var(--primary-light);
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 1.15rem;
        }

        .sidebar-menu { list-style: none; padding: 0 10px; }
        .sidebar-menu li { margin-bottom: 4px; }

        .sidebar-menu a {
            display: flex;
            align-items: center;
            color: #94a3b8;
            text-decoration: none;
            padding: 12px 18px;
            transition: var(--transition);
            border-radius: 10px;
            font-weight: 500;
            font-size: 0.92rem;
            position: relative;
            overflow: hidden;
        }

        .sidebar-menu a::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            height: 100%;
            width: 3px;
            background: var(--primary-light);
            transform: scaleY(0);
            transition: var(--transition);
            border-radius: 0 3px 3px 0;
        }

        .sidebar-menu a:hover {
            background: var(--sidebar-hover);
            color: white;
            transform: translateX(4px);
        }

        .sidebar-menu a:hover::before { transform: scaleY(1); }

        .sidebar-menu a.active {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            box-shadow: 0 4px 15px rgba(20, 184, 166, 0.3);
        }

        .sidebar-menu a.active::before { transform: scaleY(1); }

        .sidebar-menu i {
            margin-right: 12px;
            font-size: 1.05rem;
            width: 20px;
            text-align: center;
        }

        .sidebar-section {
            padding: 15px 25px 8px;
            color: #475569;
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .main-content {
            flex: 1;
            padding: 30px;
            overflow-y: auto;
            background: var(--bg);
            transition: var(--transition);
        }

        .welcome-banner {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border-radius: var(--radius);
            padding: 30px;
            margin-bottom: 25px;
            color: white;
            position: relative;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(20, 184, 166, 0.25);
        }

        .welcome-banner::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -10%;
            width: 300px;
            height: 300px;
            background: rgba(255,255,255,0.08);
            border-radius: 50%;
        }

        .welcome-banner h2 {
            font-weight: 800;
            font-size: 1.7rem;
            margin-bottom: 6px;
            position: relative;
            z-index: 1;
        }

        .welcome-banner p {
            opacity: 0.9;
            margin: 0;
            font-size: 1rem;
            position: relative;
            z-index: 1;
        }

        .welcome-banner .badge-role {
            display: inline-block;
            background: rgba(255,255,255,0.2);
            padding: 3px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            margin-left: 8px;
            vertical-align: middle;
        }

        .welcome-banner .clock {
            position: absolute;
            top: 30px;
            right: 30px;
            text-align: right;
            z-index: 1;
        }

        .welcome-banner .clock .time {
            font-size: 2rem;
            font-weight: 700;
            line-height: 1;
        }

        .welcome-banner .clock .date {
            font-size: 0.85rem;
            opacity: 0.85;
            margin-top: 4px;
        }

        .table-card {
            background: var(--card-bg);
            border-radius: var(--radius);
            padding: 22px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
            margin-bottom: 25px;
        }

        .table-card h5 {
            font-weight: 700;
            font-size: 1rem;
            margin-bottom: 18px;
            color: var(--text);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .table-card h5 i { color: var(--primary); }

        .table-card table { width: 100%; border-collapse: collapse; }

        .table-card th {
            text-align: left;
            padding: 10px 12px;
            color: var(--text-muted);
            font-weight: 600;
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid var(--border);
        }

        .table-card td {
            padding: 12px;
            color: var(--text);
            font-size: 0.9rem;
            border-bottom: 1px solid var(--border);
        }

        .table-card tr:hover td { background: rgba(20, 184, 166, 0.04); }

        .table-card td,
        .table-card th { vertical-align: middle; }

        .table-card td:last-child {
            white-space: nowrap;
            min-width: 120px;
        }

        .table-card td:last-child .btn-view,
        .table-card td:last-child .btn-delete,
        .table-card td:last-child .btn-receipt {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            padding: 0;
            margin: 0 2px;
            border-radius: 6px;
        }

        .btn-add, .btn-view, .btn-delete, .btn-receipt {
            border: none;
            border-radius: 6px;
            padding: 6px 12px;
            font-size: 0.85rem;
            color: white;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            transition: var(--transition);
            cursor: pointer;
        }

        .btn-add { background: var(--primary); }
        .btn-add:hover { background: var(--primary-dark); color: white; }

        .btn-view { background: #3b82f6; }
        .btn-view:hover { background: #2563eb; color: white; }

        .btn-delete { background: #ef4444; }
        .btn-delete:hover { background: #dc2626; color: white; }

        .btn-receipt { background: #22c55e; }
        .btn-receipt:hover { background: #16a34a; color: white; }

        .status-badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: capitalize;
            display: inline-block;
        }

        .status-paid { background: rgba(34, 197, 94, 0.15); color: #16a34a; }
        .status-pending { background: rgba(245, 158, 11, 0.15); color: #d97706; }
        .status-failed { background: rgba(239, 68, 68, 0.15); color: #dc2626; }

        .search-box { max-width: 320px; }

        .alert-soft {
            border-radius: 12px;
            padding: 12px 16px;
            margin-bottom: 15px;
            font-size: 0.9rem;
        }

        @media (max-width: 992px) {
            .sidebar { width: 200px; }
        }

        @media (max-width: 768px) {
            .main-container { flex-direction: column; }
            .sidebar { width: 100%; height: auto; position: relative; top: 0; padding: 15px 0; }
            .sidebar-menu { display: flex; overflow-x: auto; padding: 0 15px; gap: 5px; }
            .sidebar-menu li { flex: 0 0 auto; margin-bottom: 0; }
            .sidebar-menu a { padding: 10px 14px; white-space: nowrap; font-size: 0.85rem; }
            .sidebar-menu a::before { display: none; }
            .main-content { padding: 20px 15px; }
            .welcome-banner .clock { position: static; text-align: left; margin-top: 15px; }
            .sidebar-section { display: none; }
        }
    </style>
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar navbar-expand-lg">
    <div class="container-fluid px-4">
        <a class="navbar-brand" href="payments.php">
            <i class="bi bi-cash-stack"></i>
            <span>Payments</span>
        </a>
        <div class="ms-auto d-flex align-items-center gap-2">
            <button class="theme-toggle" onclick="toggleTheme()" title="Toggle Dark Mode">
                <i class="bi bi-moon-stars" id="themeIcon"></i>
            </button>
            <a class="nav-link" href="logout.php">
                <i class="bi bi-box-arrow-right"></i> Logout
            </a>
        </div>
    </div>
</nav>

<div class="main-container">

    <!-- SIDEBAR -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h4><i class="bi bi-cash-stack"></i> Payments Menu</h4>
        </div>

        <div class="sidebar-section">Payments</div>
        <ul class="sidebar-menu">
            <li>
                <a href="payments.php" class="<?php echo $current_page == 'payments.php' ? 'active' : ''; ?>">
                    <i class="bi bi-cash-coin"></i> All Payments
                </a>
            </li>
        </ul>

        <div class="sidebar-section">Related</div>
        <ul class="sidebar-menu">
            <?php if (my_access('invoices') !== 'no'): ?>
            <li>
                <a href="invoices.php" class="<?php echo $current_page == 'invoices.php' ? 'active' : ''; ?>">
                    <i class="bi bi-receipt"></i> Invoices
                </a>
            </li>
            <?php endif; ?>
            <?php if (my_access('patients') !== 'no'): ?>
            <li>
                <a href="patients.php" class="<?php echo $current_page == 'patients.php' ? 'active' : ''; ?>">
                    <i class="bi bi-person-fill"></i> Patients
                </a>
            </li>
            <?php endif; ?>
        </ul>

        <div class="sidebar-section">Back</div>
        <ul class="sidebar-menu">
            <?php if (current_role() === 'manager'): ?>
            <li><a href="manager_dashboard.php"><i class="bi bi-speedometer2"></i> Manager Dashboard</a></li>
            <?php elseif (current_role() === 'admin'): ?>
            <li><a href="admin_dashboard.php"><i class="bi bi-speedometer2"></i> Admin Dashboard</a></li>
            <?php elseif (current_role() === 'doctor'): ?>
            <li><a href="doctor_dashboard.php"><i class="bi bi-speedometer2"></i> Doctor Dashboard</a></li>
            <?php elseif (current_role() === 'staff'): ?>
            <li><a href="staff_dashboard.php"><i class="bi bi-speedometer2"></i> Staff Dashboard</a></li>
            <?php endif; ?>
        </ul>
    </div>

    <!-- MAIN CONTENT -->
    <div class="main-content">

        <!-- WELCOME BANNER -->
        <div class="welcome-banner">
            <h2>
                Welcome, <?php echo htmlspecialchars($viewer_name); ?>
                <span class="badge-role"><?php echo htmlspecialchars($viewer_role); ?></span> 👋
            </h2>
            <p>Payments Directory</p>
            <div class="clock">
                <div class="time" id="clock">--:--:--</div>
                <div class="date" id="date">Loading...</div>
            </div>
        </div>

