<?php
// patient_header.php - Shared header for all patient pages
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Allow ONLY Patient (6)
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 6) {
    header("Location: login.php");
    exit;
}

$current_page = basename($_SERVER['PHP_SELF']);
$patient_name = ($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['last_name'] ?? '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title ?? 'Patient Portal'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <style>
        :root {
            --primary: #0ea5e9;
            --primary-dark: #0284c7;
            --primary-light: #38bdf8;
            --bg: #f0f9ff;
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

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg);
            color: var(--text);
            transition: var(--transition);
            overflow-x: hidden;
        }

        /* NAVBAR */
        .navbar {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            padding: 14px 0;
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: 0 4px 20px rgba(14, 165, 233, 0.25);
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

        /* LAYOUT */
        .main-container {
            display: flex;
            min-height: calc(100vh - 68px);
        }

        /* SIDEBAR */
        .sidebar {
            background: var(--sidebar-bg);
            color: white;
            width: 280px;
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

        .sidebar-menu {
            list-style: none;
            padding: 0 10px;
        }

        .sidebar-menu li {
            margin-bottom: 4px;
        }

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

        .sidebar-menu a:hover::before {
            transform: scaleY(1);
        }

        .sidebar-menu a.active {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            box-shadow: 0 4px 15px rgba(14, 165, 233, 0.3);
        }

        .sidebar-menu a.active::before {
            transform: scaleY(1);
        }

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

        /* MAIN CONTENT */
        .main-content {
            flex: 1;
            padding: 30px;
            overflow-y: auto;
            background: var(--bg);
            transition: var(--transition);
        }

        /* WELCOME BANNER */
        .welcome-banner {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border-radius: var(--radius);
            padding: 30px;
            margin-bottom: 25px;
            color: white;
            position: relative;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(14, 165, 233, 0.25);
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

        /* STAT CARDS */
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

        /* ICON COLORS */
        .icon-appointments {
            background: linear-gradient(135deg, #8b5cf6, #7c3aed);
        }

        .icon-invoices {
            background: linear-gradient(135deg, #ef4444, #dc2626);
        }

        .icon-prescriptions {
            background: linear-gradient(135deg, #10b981, #059669);
        }

        .icon-records {
            background: linear-gradient(135deg, #06b6d4, #0891b2);
        }

        .icon-doctors {
            background: linear-gradient(135deg, #f59e0b, #d97706);
        }

        .icon-messages {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
        }

        /* TABLE CARDS */
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

        .table-card h5 i {
            color: var(--primary);
        }

        .table-card table {
            width: 100%;
            border-collapse: collapse;
        }

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

        .table-card tr:hover td {
            background: rgba(14, 165, 233, 0.04);
        }

        /* STATUS BADGES */
        .status-badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: capitalize;
            display: inline-block;
        }

        .status-scheduled {
            background: rgba(139, 92, 246, 0.15);
            color: #7c3aed;
        }

        .status-confirmed {
            background: rgba(16, 185, 129, 0.15);
            color: #059669;
        }

        .status-completed {
            background: rgba(34, 197, 94, 0.15);
            color: #16a34a;
        }

        .status-cancelled {
            background: rgba(239, 68, 68, 0.15);
            color: #dc2626;
        }

        .status-paid {
            background: rgba(34, 197, 94, 0.15);
            color: #16a34a;
        }

        .status-issued {
            background: rgba(59, 130, 246, 0.15);
            color: #2563eb;
        }

        .status-draft {
            background: rgba(107, 114, 128, 0.15);
            color: #4b5563;
        }

        /* RESPONSIVE */
        @media (max-width: 992px) {
            .sidebar {
                width: 240px;
            }
            .stats-grid {
                grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            }
        }

        @media (max-width: 768px) {
            .main-container {
                flex-direction: column;
            }
            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
                top: 0;
                padding: 15px 0;
            }
            .sidebar-menu {
                display: flex;
                overflow-x: auto;
                padding: 0 15px;
                gap: 5px;
            }
            .sidebar-menu li {
                flex: 0 0 auto;
                margin-bottom: 0;
            }
            .sidebar-menu a {
                padding: 10px 14px;
                white-space: nowrap;
                font-size: 0.85rem;
            }
            .sidebar-menu a::before {
                display: none;
            }
            .main-content {
                padding: 20px 15px;
            }
            .welcome-banner .clock {
                position: static;
                text-align: left;
                margin-top: 15px;
            }
            .stats-grid {
                grid-template-columns: 1fr 1fr;
            }
            .sidebar-section {
                display: none;
            }
        }

        @media (max-width: 576px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            .welcome-banner h2 {
                font-size: 1.3rem;
            }
        }
    </style>
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar navbar-expand-lg">
    <div class="container-fluid px-4">
        <a class="navbar-brand" href="patient_dashboard.php">
            <i class="bi bi-person-heart"></i>
            <span>Patient Portal</span>
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
            <h4><i class="bi bi-person-heart"></i> Patient Menu</h4>
        </div>

        <div class="sidebar-section">My Health</div>
        <ul class="sidebar-menu">
            <li>
                <a href="patient_dashboard.php" class="<?php echo $current_page == 'patient_dashboard.php' ? 'active' : ''; ?>">
                    <i class="bi bi-speedometer2"></i> Dashboard
                </a>
            </li>
            <li>
                <a href="patient_appointments.php" class="<?php echo $current_page == 'patient_appointments.php' ? 'active' : ''; ?>">
                    <i class="bi bi-calendar-check"></i> My Appointments
                </a>
            </li>
            <li>
               <a href="patient_book_appointment.php" class="<?php echo $current_page == 'patient_book_appointment.php' ? 'active' : ''; ?>">
                    <i class="bi bi-calendar-plus"></i> Book Appointment
               </a>
            </li>
            <li>
                <a href="patient_prescriptions.php" class="<?php echo $current_page == 'patient_prescriptions.php' ? 'active' : ''; ?>">
                    <i class="bi bi-capsule"></i> My Prescriptions
                </a>
            </li>
            <li>
                <a href="patient_records.php" class="<?php echo $current_page == 'patient_records.php' ? 'active' : ''; ?>">
                    <i class="bi bi-file-medical"></i> Medical Records
                </a>
            </li>
            <li>
                <a href="patient_doctors.php" class="<?php echo $current_page == 'patient_doctors.php' ? 'active' : ''; ?>">
                    <i class="bi bi-person-badge"></i> My Doctors
                </a>
            </li>
        </ul>

        <div class="sidebar-section">Account</div>
        <ul class="sidebar-menu">
            <li>
                <a href="patient_invoices.php" class="<?php echo $current_page == 'patient_invoices.php' ? 'active' : ''; ?>">
                    <i class="bi bi-receipt"></i> My Invoices
                </a>
            </li>
            <li>
                <a href="patient_messages.php" class="<?php echo $current_page == 'patient_messages.php' ? 'active' : ''; ?>">
                    <i class="bi bi-chat-dots"></i> Messages
                </a>
            </li>
           <li>
               <a href="patient_send_message.php" class="<?php echo $current_page == 'patient_send_message.php' ? 'active' : ''; ?>">
                   <i class="bi bi-send"></i> Send Message
               </a>
            </li>
            <li>
                <a href="patient_profile.php" class="<?php echo $current_page == 'patient_profile.php' ? 'active' : ''; ?>">
                    <i class="bi bi-person"></i> My Profile
                </a>
            </li>
        </ul>
    </div>

    <!-- MAIN CONTENT STARTS -->
    <div class="main-content">
