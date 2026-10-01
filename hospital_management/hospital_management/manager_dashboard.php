<?php
session_start();
include 'config.php';

// Show errors for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Check if manager is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 2) {
    header("Location: login.php");
    exit;
}

// Tables for dashboard
$dashboardTables = [
    'patients' => 'Patients',
    'doctors' => 'Doctors',
    'appointments' => 'Appointments',
    'invoices' => 'Invoices',
    'payments' => 'Payments',
    'pharmacy_stock' => 'Pharmacy',
    'medicines' => 'Medicines',
    'wards' => 'Wards',
    'rooms' => 'Rooms',
    'messages' => 'Messages',
    'admissions' => 'Admissions'
];

// Fetch stats
$stats = [];
foreach($dashboardTables as $table => $label) {
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM $table");
    $stats[$label] = $stmt->fetch()['total'];
}

// Card classes for gradients
$cardClasses = [
    'Patients' => 'card-patients',
    'Doctors' => 'card-doctors',
    'Appointments' => 'card-appointments',
    'Invoices' => 'card-invoices',
    'Payments' => 'card-payments',
    'Pharmacy' => 'card-pharmacy',
    'Medicines' => 'card-medicines',
    'Wards' => 'card-wards',
    'Rooms' => 'card-rooms',
    'Messages' => 'card-messages',
    'Admissions' => 'card-admissions'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manager Dashboard</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
/* =============================
   GLOBAL STYLES
============================= */
:root {
    --manager-primary: #10b981;
    --manager-secondary: #059669;
    --manager-accent: #34d399;
    --manager-light: #ecfdf5;
    --manager-dark: #064e3b;
    --sidebar-bg: #1c2536;
    --sidebar-hover: #2d3748;
    --card-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    --transition: all 0.3s ease;
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Inter', sans-serif;
    background: #f8fafc;
    color: #334155;
    overflow-x: hidden;
    opacity: 0;
    animation: fadeIn 0.8s forwards;
}

@keyframes fadeIn {
    to { opacity: 1; }
}

/* =============================
   NAVBAR - Manager Theme
============================= */
.navbar {
    background: linear-gradient(135deg, var(--manager-primary), var(--manager-secondary));
    box-shadow: 0 2px 15px rgba(16, 185, 129, 0.2);
    padding: 14px 0;
    position: sticky;
    top: 0;
    z-index: 1000;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.navbar-brand {
    font-weight: 700;
    font-size: 1.5rem;
    display: flex;
    align-items: center;
    gap: 10px;
    color: white !important;
}

.navbar-brand i {
    font-size: 1.8rem;
}

.navbar .nav-link {
    font-weight: 500;
    transition: var(--transition);
    display: flex;
    align-items: center;
    gap: 5px;
    color: white !important;
    padding: 8px 16px !important;
    border-radius: 8px;
}

.navbar .nav-link:hover {
    background: rgba(255, 255, 255, 0.15);
    transform: translateY(-2px);
}

/* =============================
   LAYOUT
============================= */
.main-container {
    display: flex;
    min-height: calc(100vh - 62px);
}

/* =============================
   SIDEBAR - Manager Theme
============================= */
.sidebar {
    background: var(--sidebar-bg);
    color: #fff;
    width: 280px;
    padding: 25px 0;
    transition: var(--transition);
    box-shadow: 3px 0 15px rgba(0, 0, 0, 0.1);
    z-index: 999;
}

.sidebar-header {
    padding: 0 25px 25px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    margin-bottom: 20px;
}

.sidebar-header h4 {
    font-weight: 700;
    color: var(--manager-accent);
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 1.3rem;
}

.sidebar-menu {
    list-style: none;
    padding: 0;
}

.sidebar-menu li {
    margin-bottom: 8px;
}

.sidebar-menu a {
    display: flex;
    align-items: center;
    color: #cbd5e1;
    text-decoration: none;
    padding: 14px 25px;
    transition: var(--transition);
    border-left: 4px solid transparent;
    font-weight: 500;
    border-radius: 0 8px 8px 0;
}

.sidebar-menu a:hover {
    background: var(--sidebar-hover);
    color: white;
    border-left: 4px solid var(--manager-accent);
    transform: translateX(5px);
}

.sidebar-menu a.active {
    background: var(--sidebar-hover);
    color: white;
    border-left: 4px solid var(--manager-accent);
}

.sidebar-menu i {
    margin-right: 15px;
    font-size: 1.2rem;
    width: 20px;
    text-align: center;
    color: var(--manager-accent);
}

/* =============================
   MAIN CONTENT
============================= */
.main-content {
    flex: 1;
    padding: 35px;
    overflow-y: auto;
    background: #f8fafc;
}

.welcome-section {
    margin-bottom: 35px;
    padding: 25px;
    background: white;
    border-radius: 16px;
    box-shadow: var(--card-shadow);
    border-left: 5px solid var(--manager-primary);
}

.welcome-section h2 {
    font-weight: 700;
    color: var(--manager-dark);
    margin-bottom: 8px;
    font-size: 1.8rem;
}

.welcome-section p {
    color: #64748b;
    font-size: 1.1rem;
    margin: 0;
}

/* =============================
   DASHBOARD CARDS
============================= */
.dashboard-cards {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
    gap: 25px;
    margin-top: 10px;
}

.card {
    border: none;
    border-radius: 16px;
    box-shadow: var(--card-shadow);
    transition: var(--transition);
    overflow: hidden;
    position: relative;
    padding: 25px;
    color: white;
    min-height: 160px;
    display: flex;
    flex-direction: column;
    justify-content: center;
    background: linear-gradient(135deg, var(--card-color-1), var(--card-color-2));
}

.card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 6px;
    background: rgba(255, 255, 255, 0.3);
}

.card:hover {
    transform: translateY(-8px) scale(1.02);
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.15);
}

.card-body {
    padding: 0;
    position: relative;
    z-index: 1;
}

.card-body h5 {
    font-size: 14px;
    font-weight: 600;
    margin-bottom: 12px;
    opacity: 0.95;
    text-transform: uppercase;
    letter-spacing: 0.8px;
}

.card-body p {
    font-size: 34px;
    font-weight: 700;
    margin: 0;
}

.card-icon {
    position: absolute;
    bottom: 20px;
    right: 20px;
    font-size: 2.8rem;
    opacity: 0.2;
    transition: var(--transition);
}

.card:hover .card-icon {
    opacity: 0.3;
    transform: scale(1.1) rotate(5deg);
}

/* =============================
   GRADIENT CARD COLORS - Manager Theme
============================= */
.card-patients { 
    --card-color-1: #f59e0b; 
    --card-color-2: #d97706; 
}
.card-doctors { 
    --card-color-1: #10b981; 
    --card-color-2: #059669; 
}
.card-appointments { 
    --card-color-1: #8b5cf6; 
    --card-color-2: #7c3aed; 
}
.card-invoices { 
    --card-color-1: #ef4444; 
    --card-color-2: #dc2626; 
}
.card-payments { 
    --card-color-1: #06b6d4; 
    --card-color-2: #0891b2; 
}
.card-pharmacy { 
    --card-color-1: #84cc16; 
    --card-color-2: #65a30d; 
}
.card-medicines { 
    --card-color-1: #f97316; 
    --card-color-2: #ea580c; 
}
.card-wards { 
    --card-color-1: #ec4899; 
    --card-color-2: #db2777; 
}
.card-rooms { 
    --card-color-1: #6366f1; 
    --card-color-2: #4f46e5; 
}
.card-messages { 
    --card-color-1: #14b8a6; 
    --card-color-2: #0d9488; 
}
.card-admissions { 
    --card-color-1: #f59e0b; 
    --card-color-2: #d97706; 
}

/* =============================
   RESPONSIVE
============================= */
@media (max-width: 1200px) {
    .dashboard-cards {
        grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    }
}

@media (max-width: 992px) {
    .dashboard-cards {
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    }
    
    .sidebar {
        width: 240px;
    }
}

@media (max-width: 768px) {
    .main-container {
        flex-direction: column;
    }
    
    .sidebar {
        width: 100%;
        height: auto;
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
        padding: 12px 15px;
        white-space: nowrap;
        border-left: none;
        border-bottom: 3px solid transparent;
        border-radius: 8px;
    }
    
    .sidebar-menu a:hover,
    .sidebar-menu a.active {
        border-left: none;
        border-bottom: 3px solid var(--manager-accent);
        transform: translateY(-3px);
    }
    
    .main-content {
        padding: 20px;
    }
    
    .dashboard-cards {
        grid-template-columns: 1fr 1fr;
        gap: 15px;
    }
    
    .card-body p {
        font-size: 28px;
    }
    
    .welcome-section {
        padding: 20px;
    }
}

@media (max-width: 576px) {
    .dashboard-cards {
        grid-template-columns: 1fr;
    }
    
    .navbar-brand span {
        font-size: 1.3rem;
    }
    
    .welcome-section h2 {
        font-size: 1.5rem;
    }
}
</style>
</head>
<body>
<nav class="navbar navbar-expand-lg">
    <div class="container-fluid">
        <a class="navbar-brand" href="#">
            <i class="bi bi-clipboard2-pulse"></i>
            <span>Hospital Manager</span>
        </a>
        <div class="collapse navbar-collapse">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <a class="nav-link" href="logout.php">
                        <i class="bi bi-box-arrow-right"></i> Logout
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<div class="main-container">
    <div class="sidebar">
        <div class="sidebar-header">
            <h4><i class="bi bi-clipboard-data"></i> Manager Menu</h4>
        </div>
        <ul class="sidebar-menu">
            <li><a href="manager_dashboard.php" class="active"><i class="bi bi-speedometer2"></i> Dashboard</a></li>
            <li><a href="admin_dashboard.php"><i class="bi bi-shield-check"></i> Admin Dashboard</a></li>
            <li><a href="patients.php"><i class="bi bi-person-fill"></i> Patients</a></li>
            <li><a href="doctors.php"><i class="bi bi-person-badge"></i> Doctors</a></li>
            <li><a href="appointments.php"><i class="bi bi-calendar-check"></i> Appointments</a></li>
            <li><a href="invoices.php"><i class="bi bi-receipt"></i> Invoices</a></li>
            <li><a href="payments.php"><i class="bi bi-cash-stack"></i> Payments</a></li>
            <li><a href="pharmacy_stock.php"><i class="bi bi-capsule"></i> Pharmacy</a></li>
            <li><a href="medicines.php"><i class="bi bi-heart-pulse"></i> Medicines</a></li>
            <li><a href="wards.php"><i class="bi bi-house-door"></i> Wards</a></li>
            <li><a href="rooms.php"><i class="bi bi-door-closed"></i> Rooms</a></li>
            <li><a href="manager_messages.php"><i class="bi bi-chat-dots"></i> Messages</a></li>
            <li><a href="admissions.php"><i class="bi bi-journal-plus"></i> Admissions</a></li>
        </ul>
    </div>

    <div class="main-content">
        <div class="welcome-section">
            <h2>Welcome, Manager</h2>
            <p>Manage your hospital operations efficiently</p>
        </div>
        
        <div class="dashboard-cards">
            <?php foreach($stats as $label=>$value): ?>
            <div class="card <?php echo $cardClasses[$label]; ?>">
                <div class="card-body">
                    <h5><?php echo $label; ?></h5>
                    <p class="counter" data-target="<?php echo $value; ?>">0</p>
                    <div class="card-icon">
                        <?php 
                        // Set appropriate icons for each card
                        $icons = [
                            'Patients' => 'bi-person',
                            'Doctors' => 'bi-person-badge',
                            'Appointments' => 'bi-calendar-event',
                            'Invoices' => 'bi-receipt',
                            'Payments' => 'bi-currency-dollar',
                            'Pharmacy' => 'bi-capsule',
                            'Medicines' => 'bi-capsule-pill',
                            'Wards' => 'bi-house',
                            'Rooms' => 'bi-door-closed',
                            'Messages' => 'bi-chat',
                            'Admissions' => 'bi-journal-plus'
                        ];
                        echo '<i class="bi ' . $icons[$label] . '"></i>';
                        ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<script>
// Enhanced Counter animation with Intersection Observer
document.addEventListener("DOMContentLoaded", function() {
    const counters = document.querySelectorAll('.counter');
    
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const counter = entry.target;
                const target = +counter.getAttribute('data-target');
                const duration = 1800; // Animation duration in ms
                const step = target / (duration / 16); // 60fps
                let current = 0;
                
                const updateCounter = () => {
                    current += step;
                    if (current < target) {
                        counter.innerText = Math.ceil(current);
                        requestAnimationFrame(updateCounter);
                    } else {
                        counter.innerText = target;
                    }
                };
                
                updateCounter();
                observer.unobserve(counter);
            }
        });
    }, { threshold: 0.3 });
    
    counters.forEach(counter => {
        observer.observe(counter);
    });
});
</script>

</body>
</html>
