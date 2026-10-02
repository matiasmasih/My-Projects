<?php
// doctor_header.php - Shared header for doctor pages
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include_once 'access_control.php';

// Access: doctor_dashboard
$access = require_access('doctor_dashboard');
$is_view_only = ($access === 'view');

$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title ?? 'Doctor Dashboard'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        :root {
            --primary: #10b981;
            --primary-dark: #059669;
            --primary-light: #34d399;
            --bg: #f0fdf4;
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
            box-shadow: 0 4px 20px rgba(16, 185, 129, 0.25);
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

.sidebar::-webkit-scrollbar {
    width: 8px;
}

.sidebar::-webkit-scrollbar-track {
    background: rgba(0,0,0,0.1);
    border-radius: 4px;
}

.sidebar::-webkit-scrollbar-thumb {
    background: rgba(255,255,255,0.35);
    border-radius: 4px;
}

.sidebar::-webkit-scrollbar-thumb:hover {
    background: rgba(255,255,255,0.55);
}

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
            left: 0; top: 0;
            height: 100%; width: 3px;
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
            box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3);
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
            box-shadow: 0 10px 30px rgba(16, 185, 129, 0.25);
        }

        .welcome-banner::before {
            content: '';
            position: absolute;
            top: -50%; right: -10%;
            width: 300px; height: 300px;
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
            top: 30px; right: 30px;
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

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 18px;
            margin-bottom: 25px;
        }

        .stat-card {
            background: var(--card-bg);
            border-radius: var(--radius);
            padding: 22px;
            box-shadow: var(--shadow);
            transition: var(--transition);
            position: relative;
            overflow: hidden;
            border: 1px solid var(--border);
            display: flex;
            align-items: center;
            gap: 18px;
        }

        .stat-card:hover {
            transform: translateY(-6px);
            box-shadow: var(--shadow-lg);
            border-color: var(--primary-light);
        }

        .stat-icon {
            width: 56px;
            height: 56px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            flex-shrink: 0;
            color: white;
        }

        .stat-info h3 {
            font-size: 1.7rem;
            font-weight: 800;
            margin: 0;
            color: var(--text);
            line-height: 1.1;
        }

        .stat-info p {
            margin: 4px 0 0;
            color: var(--text-muted);
            font-size: 0.85rem;
            font-weight: 500;
        }

        .icon-appointments { background: linear-gradient(135deg, #8b5cf6, #7c3aed); }
        .icon-patients { background: linear-gradient(135deg, #f59e0b, #d97706); }
        .icon-completed { background: linear-gradient(135deg, #10b981, #059669); }
        .icon-pending { background: linear-gradient(135deg, #06b6d4, #0891b2); }
        .icon-doctors { background: linear-gradient(135deg, #10b981, #059669); }
        .icon-invoices { background: linear-gradient(135deg, #ef4444, #dc2626); }
        .icon-pharmacy { background: linear-gradient(135deg, #84cc16, #65a30d); }
        .icon-medicines { background: linear-gradient(135deg, #f97316, #ea580c); }
        .icon-wards { background: linear-gradient(135deg, #ec4899, #db2777); }
        .icon-rooms { background: linear-gradient(135deg, #6366f1, #4f46e5); }
        .icon-messages { background: linear-gradient(135deg, #14b8a6, #0d9488); }
        .icon-admissions { background: linear-gradient(135deg, #f7971e, #ffd200); }

        .charts-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
            margin-bottom: 25px;
        }

        .chart-card {
            background: var(--card-bg);
            border-radius: var(--radius);
            padding: 22px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
            transition: var(--transition);
            height: 350px;
            display: flex;
            flex-direction: column;
            position: relative;
        }

        .chart-card:hover { box-shadow: var(--shadow-lg); }

        .chart-card h5 {
            font-weight: 700;
            font-size: 1rem;
            margin-bottom: 18px;
            color: var(--text);
            display: flex;
            align-items: center;
            gap: 8px;
            flex-shrink: 0;
        }

        .chart-card h5 i { color: var(--primary); }

        .chart-wrapper {
            position: relative;
            flex: 1;
            min-height: 0;
            width: 100%;
        }

        .chart-wrapper canvas {
            position: absolute !important;
            top: 0; left: 0;
            width: 100% !important;
            height: 100% !important;
        }

        /* ============ TABLE ============ */
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

        .table-card table { width: 100%; border-collapse: collapse; white-space: nowrap; }

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

        .table-card td, .table-card th { vertical-align: middle; }

        .table-card td:last-child {
            white-space: nowrap;
            min-width: 90px;
        }

        .table-card td:last-child .btn-edit,
        .table-card td:last-child .btn-delete {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            padding: 0;
            margin: 0 2px;
            border-radius: 6px;
        }

        .table-card tr:hover td { background: rgba(16, 185, 129, 0.04); }

        /* ============ BUTTONS ============ */
        .btn-add, .btn-edit, .btn-delete, .btn-restock {
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

        .btn-edit { background: #22c55e; }
        .btn-edit:hover { background: #16a34a; color: white; }

        .btn-delete { background: #ef4444; }
        .btn-delete:hover { background: #dc2626; color: white; }

        .btn-restock { background: #f59e0b; }
        .btn-restock:hover { background: #d97706; color: white; }

        .btn-secondary-custom {
            background: #64748b;
            color: white;
            border: none;
            border-radius: 6px;
            padding: 6px 12px;
            font-size: 0.85rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            transition: var(--transition);
        }
        .btn-secondary-custom:hover { background: #475569; color: white; }

        /* ============ STATUS BADGES ============ */
        .status-badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: capitalize;
            display: inline-block;
        }

        .status-scheduled { background: rgba(139, 92, 246, 0.15); color: #7c3aed; }
        .status-confirmed { background: rgba(16, 185, 129, 0.15); color: #059669; }
        .status-completed { background: rgba(34, 197, 94, 0.15); color: #16a34a; }
        .status-cancelled { background: rgba(239, 68, 68, 0.15); color: #dc2626; }
        .status-checked_in { background: rgba(6, 182, 212, 0.15); color: #0891b2; }
        .status-in_progress { background: rgba(245, 158, 11, 0.15); color: #d97706; }
        .status-no_show { background: rgba(107, 114, 128, 0.15); color: #4b5563; }

        /* ============ SEARCH + ALERTS ============ */
        .search-box { max-width: 320px; }

        .alert-soft {
            border-radius: 12px;
            padding: 12px 16px;
            margin-bottom: 15px;
            font-size: 0.9rem;
        }

        /* ============ QUICK ACTIONS ============ */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }

        .action-btn {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 18px;
            text-decoration: none;
            color: var(--text);
            display: flex;
            align-items: center;
            gap: 12px;
            transition: var(--transition);
            box-shadow: var(--shadow);
            font-weight: 600;
            font-size: 0.9rem;
        }

        .action-btn:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
            border-color: var(--primary);
            color: var(--primary-dark);
        }

        .action-btn i {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            flex-shrink: 0;
        }

        /* ============ RESPONSIVE ============ */
        @media (max-width: 1200px) {
            .charts-grid { grid-template-columns: 1fr; }
            .chart-card { height: 320px; }
        }

        @media (max-width: 992px) {
            .sidebar { width: 200px; }
            .stats-grid { grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); }
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
            .stats-grid { grid-template-columns: 1fr 1fr; }
            .chart-card { height: 280px; }
            .sidebar-section { display: none; }
        }

        @media (max-width: 576px) {
            .stats-grid { grid-template-columns: 1fr; }
            .welcome-banner h2 { font-size: 1.3rem; }
        }
    </style>

</head>
<body>

<!-- NAVBAR -->
<nav class="navbar navbar-expand-lg">
    <div class="container-fluid px-4">
        <a class="navbar-brand" href="doctor_dashboard.php">
            <i class="bi bi-heart-pulse"></i>
            <span>Doctor Dashboard</span>
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
            <h4><i class="bi bi-heart-pulse-fill"></i> Doctor Menu</h4>
        </div>

        <div class="sidebar-section">Main</div>
        <ul class="sidebar-menu">
            <li>
                <a href="doctor_dashboard.php" class="<?php echo $current_page == 'doctor_dashboard.php' ? 'active' : ''; ?>">
                    <i class="bi bi-speedometer2"></i> Dashboard
                </a>
            </li>
        </ul>

        <div class="sidebar-section">Switch View</div>
        <ul class="sidebar-menu">
            <?php if (my_access('manager_dashboard') !== 'no'): ?>
            <li>
                <a href="manager_dashboard.php" class="<?php echo $current_page == 'manager_dashboard.php' ? 'active' : ''; ?>">
                    <i class="bi bi-clipboard2-pulse"></i> Manager View
                    <?php if (my_access('manager_dashboard') === 'view'): ?><span style="margin-left:auto; font-size:0.7rem; opacity:0.6;">👁️</span><?php endif; ?>
                </a>
            </li>
            <?php endif; ?>
            <?php if (my_access('admin_dashboard') !== 'no'): ?>
            <li>
                <a href="admin_dashboard.php" class="<?php echo $current_page == 'admin_dashboard.php' ? 'active' : ''; ?>">
                    <i class="bi bi-shield-check"></i> Admin View
                    <?php if (my_access('admin_dashboard') === 'view'): ?><span style="margin-left:auto; font-size:0.7rem; opacity:0.6;">👁️</span><?php endif; ?>
                </a>
            </li>
            <?php endif; ?>
            <?php if (my_access('staff_dashboard') !== 'no'): ?>
            <li>
                <a href="staff_dashboard.php" class="<?php echo $current_page == 'staff_dashboard.php' ? 'active' : ''; ?>">
                    <i class="bi bi-person-badge"></i> Staff View
                    <?php if (my_access('staff_dashboard') === 'view'): ?><span style="margin-left:auto; font-size:0.7rem; opacity:0.6;">👁️</span><?php endif; ?>
                </a>
            </li>
            <?php endif; ?>
            <?php if (my_access('patient_dashboard') !== 'no'): ?>
            <li>
                <a href="patient_dashboard.php" class="<?php echo $current_page == 'patient_dashboard.php' ? 'active' : ''; ?>">
                    <i class="bi bi-person-heart"></i> Patient View
                    <?php if (my_access('patient_dashboard') === 'view'): ?><span style="margin-left:auto; font-size:0.7rem; opacity:0.6;">👁️</span><?php endif; ?>
                </a>
            </li>
            <?php endif; ?>
        </ul>

        <div class="sidebar-section">Medical</div>
        <ul class="sidebar-menu">
            <?php if (my_access('patients') !== 'no'): ?>
            <li><a href="patients.php" class="<?php echo $current_page == 'patients.php' ? 'active' : ''; ?>"><i class="bi bi-person-fill"></i> Patients</a></li>
            <?php endif; ?>
            <?php if (my_access('doctors') !== 'no'): ?>
            <li><a href="doctors.php" class="<?php echo $current_page == 'doctors.php' ? 'active' : ''; ?>"><i class="bi bi-person-badge"></i> Doctors</a></li>
            <?php endif; ?>
            <?php if (my_access('appointments') !== 'no'): ?>
            <li><a href="appointments.php" class="<?php echo $current_page == 'appointments.php' ? 'active' : ''; ?>"><i class="bi bi-calendar-check"></i> Appointments</a></li>
            <?php endif; ?>
            <?php if (my_access('prescriptions') !== 'no'): ?>
            <li><a href="prescriptions.php" class="<?php echo $current_page == 'prescriptions.php' ? 'active' : ''; ?>"><i class="bi bi-capsule"></i> Prescriptions</a></li>
            <?php endif; ?>
            <?php if (my_access('medical_records') !== 'no'): ?>
            <li><a href="medical_records.php" class="<?php echo $current_page == 'medical_records.php' ? 'active' : ''; ?>"><i class="bi bi-file-medical"></i> Medical Records</a></li>
            <?php endif; ?>
            <?php if (my_access('medicines') !== 'no'): ?>
            <li><a href="medicines.php" class="<?php echo $current_page == 'medicines.php' ? 'active' : ''; ?>"><i class="bi bi-heart-pulse"></i> Medicines</a></li>
            <?php endif; ?>
            <?php if (my_access('medical_records') === 'full'): ?>
            <li>
                <a href="add_record.php" class="<?php echo $current_page == 'add_record.php' ? 'active' : ''; ?>">
                    <i class="bi bi-plus-circle"></i> New Record
                </a>
            </li>
            <?php endif; ?>
            <?php if (my_access('prescriptions') === 'full'): ?>
            <li>
                <a href="add_prescription.php" class="<?php echo $current_page == 'add_prescription.php' ? 'active' : ''; ?>">
                    <i class="bi bi-plus-circle"></i> New Prescription
                </a>
            </li>
            <?php endif; ?>
          </ul>
    </div>

    <!-- MAIN CONTENT -->
    <div class="main-content">
        <!-- WELCOME BANNER (skipped on dashboard — it has its own) -->
        <?php if (($current_page ?? '') !== 'doctor_dashboard.php'): ?>
        <div class="welcome-banner">
            <h2>
                Welcome, Dr. <?php echo htmlspecialchars($viewer_name ?? ($_SESSION['first_name'] ?? 'Doctor') . ' ' . ($_SESSION['last_name'] ?? '')); ?>
                <span class="badge-role">Doctor</span> 👋
            </h2>
            <p><?php echo isset($page_title) ? htmlspecialchars($page_title) : 'Doctor Dashboard'; ?></p>
            <div class="clock">
                <div class="time" id="clock">--:--:--</div>
                <div class="date" id="date">Loading...</div>
            </div>
        </div>
        <?php endif; ?>
