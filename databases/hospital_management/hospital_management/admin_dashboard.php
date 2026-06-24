<?php
session_start();
include 'config.php';

// Show errors for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Check if admin is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) { // 1 = admin
    header("Location: login.php");
    exit;
}

// Tables and labels for admin dashboard cards
$dashboardTables = [
    'users' => 'Users',
    'roles' => 'Roles',
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

// Card gradient classes
$cardClasses = [
    'Users' => 'card-users',
    'Roles' => 'card-roles',
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
<title>Admin Dashboard</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
/* =============================
   GLOBAL STYLES
============================= */
:root {
    --primary: #4361ee;
    --secondary: #3f37c9;
    --success: #4cc9f0;
    --info: #4895ef;
    --warning: #f72585;
    --danger: #e63946;
    --light: #f8f9fa;
    --dark: #212529;
    --sidebar-bg: #1a1d29;
    --sidebar-hover: #2d3040;
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
    background: #f5f7fb;
    color: #333;
    overflow-x: hidden;
    opacity: 0;
    animation: fadeIn 0.8s forwards;
}

@keyframes fadeIn {
    to { opacity: 1; }
}

/* =============================
   NAVBAR
============================= */
.navbar {
    background: linear-gradient(135deg, #93f3f1, #b388fe);
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    padding: 12px 0;
    position: sticky;
    top: 0;
    z-index: 1000;
}

.navbar-brand {
    font-weight: 700;
    font-size: 1.5rem;
    display: flex;
    align-items: center;
    gap: 10px;
    color: #333 !important;
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
    color: #333 !important;
}

.navbar .nav-link:hover {
    color: #0d47a1 !important;
    transform: translateY(-2px);
}

/* =============================
   LAYOUT
============================= */
.main-container {
    display: flex;
    min-height: calc(100vh - 56px);
}

/* =============================
   SIDEBAR
============================= */
.sidebar {
    background: var(--sidebar-bg);
    color: #fff;
    width: 260px;
    padding: 25px 0;
    transition: var(--transition);
    box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
    z-index: 999;
}

.sidebar-header {
    padding: 0 25px 20px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    margin-bottom: 15px;
}

.sidebar-header h4 {
    font-weight: 700;
    color: #fff;
    display: flex;
    align-items: center;
    gap: 10px;
}

.sidebar-menu {
    list-style: none;
    padding: 0;
}

.sidebar-menu li {
    margin-bottom: 5px;
}

.sidebar-menu a {
    display: flex;
    align-items: center;
    color: #b0b3c1;
    text-decoration: none;
    padding: 12px 25px;
    transition: var(--transition);
    border-left: 3px solid transparent;
    font-weight: 500;
}

.sidebar-menu a:hover {
    background: var(--sidebar-hover);
    color: #fff;
    border-left: 3px solid var(--primary);
}

.sidebar-menu a.active {
    background: var(--sidebar-hover);
    color: #fff;
    border-left: 3px solid var(--primary);
}

.sidebar-menu i {
    margin-right: 12px;
    font-size: 1.1rem;
    width: 20px;
    text-align: center;
}

/* =============================
   MAIN CONTENT
============================= */
.main-content {
    flex: 1;
    padding: 30px;
    overflow-y: auto;
}

.welcome-section {
    margin-bottom: 30px;
}

.welcome-section h2 {
    font-weight: 700;
    color: var(--dark);
    margin-bottom: 5px;
}

.welcome-section p {
    color: #6c757d;
    font-size: 1.1rem;
}

/* =============================
   DASHBOARD CARDS
============================= */
.dashboard-cards {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
    gap: 25px;
    margin-top: 20px;
}

.card {
    border: none;
    border-radius: 16px;
    box-shadow: var(--card-shadow);
    transition: var(--transition);
    overflow: hidden;
    position: relative;
    padding: 20px;
    color: #fff;
    min-height: 140px;
    display: flex;
    flex-direction: column;
    justify-content: center;
}

.card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 5px;
    background: rgba(255, 255, 255, 0.3);
}

.card:hover {
    transform: translateY(-8px);
    box-shadow: 0 12px 30px rgba(0, 0, 0, 0.15);
}

.card-body {
    padding: 0;
    position: relative;
    z-index: 1;
}

.card-body h5 {
    font-size: 14px;
    font-weight: 500;
    margin-bottom: 10px;
    opacity: 0.9;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.card-body p {
    font-size: 32px;
    font-weight: 700;
    margin: 0;
}

.card-icon {
    position: absolute;
    bottom: 15px;
    right: 15px;
    font-size: 2.5rem;
    opacity: 0.2;
    transition: var(--transition);
}

.card:hover .card-icon {
    opacity: 0.3;
    transform: scale(1.1);
}

/* =============================
   GRADIENT CARD COLORS
============================= */
.card-users { background: linear-gradient(135deg, #36d1dc, #5b86e5); }
.card-roles { background: linear-gradient(135deg, #ff7e5f, #feb47b); }
.card-patients { background: linear-gradient(135deg, #ff6a00, #ee0979); }
.card-doctors { background: linear-gradient(135deg, #56ab2f, #a8e063); }
.card-appointments { background: linear-gradient(135deg, #36d1dc, #5b86e5); }
.card-invoices { background: linear-gradient(135deg, #ff7e5f, #feb47b); }
.card-payments { background: linear-gradient(135deg, #c33764, #1d2671); }
.card-pharmacy { background: linear-gradient(135deg, #fc4a1a, #f7b733); }
.card-medicines { background: linear-gradient(135deg, #11998e, #38ef7d); }
.card-wards { background: linear-gradient(135deg, #ee9ca7, #ffdde1); color: #333 !important; }
.card-rooms { background: linear-gradient(135deg, #42275a, #734b6d); }
.card-messages { background: linear-gradient(135deg, #00c6ff, #0072ff); }
.card-admissions { background: linear-gradient(135deg, #f7971e, #ffd200); color: #333 !important; }

.card-wards h5, .card-admissions h5 { color: #333 !important; }

/* =============================
   RESPONSIVE
============================= */
@media (max-width: 992px) {
    .dashboard-cards {
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
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
    }

    .sidebar-menu li {
        flex: 0 0 auto;
        margin-bottom: 0;
        margin-right: 5px;
    }

    .sidebar-menu a {
        padding: 10px 15px;
        white-space: nowrap;
        border-left: none;
        border-bottom: 3px solid transparent;
    }

    .sidebar-menu a:hover,
    .sidebar-menu a.active {
        border-left: none;
        border-bottom: 3px solid var(--primary);
    }

    .main-content {
        padding: 20px;
    }

    .dashboard-cards {
        grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
        gap: 15px;
    }

    .card-body p {
        font-size: 26px;
    }
}

@media (max-width: 576px) {
    .dashboard-cards {
        grid-template-columns: 1fr 1fr;
    }

.navbar-brand span {
  display: none;
    }
}
</style>
</head>
<body>
<nav class="navbar navbar-expand-lg">
    <div class="container-fluid">
        <a class="navbar-brand" href="#">
            <i class="bi bi-hospital"></i>
            <span>MediCare Admin</span>
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
            <h4><i class="bi bi-layout-sidebar"></i> Admin Menu</h4>
        </div>
        <ul class="sidebar-menu">
            <li><a href="admin_dashboard.php" class="active"><i class="bi bi-speedometer2"></i> Dashboard</a></li>
            <li><a href="users.php"><i class="bi bi-people-fill"></i> Users</a></li>
            <li><a href="roles.php"><i class="bi bi-shield-lock"></i> Roles</a></li>
            <li><a href="patients.php"><i class="bi bi-person-fill"></i> Patients</a></li>
            <li><a href="doctors.php"><i class="bi bi-person-badge"></i> Doctors</a></li>
            <li><a href="appointments.php"><i class="bi bi-calendar-check"></i> Appointments</a></li>
            <li><a href="invoices.php"><i class="bi bi-receipt"></i> Invoices</a></li>
            <li><a href="payments.php"><i class="bi bi-cash-stack"></i> Payments</a></li>
            <li><a href="pharmacy_stock.php"><i class="bi bi-capsule"></i> Pharmacy</a></li>
            <li><a href="medicines.php"><i class="bi bi-heart-pulse"></i> Medicines</a></li>
            <li><a href="wards.php"><i class="bi bi-house-door"></i> Wards</a></li>
            <li><a href="rooms.php"><i class="bi bi-door-closed"></i> Rooms</a></li>
            <li><a href="admin_messages.php"><i class="bi bi-chat-dots"></i> Messages</a></li>
            <li><a href="admissions.php"><i class="bi bi-journal-plus"></i> Admissions</a></li>
        </ul>
    </div>

    <div class="main-content">
        <div class="welcome-section">
            <h2>Welcome, Admin</h2>
            <p>Here's what's happening with your hospital today.</p>
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
                            'Users' => 'bi-people',
                            'Roles' => 'bi-shield-check',
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
// Counter animation
document.addEventListener("DOMContentLoaded", function() {
    const counters = document.querySelectorAll('.counter');

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const counter = entry.target;
                const target = +counter.getAttribute('data-target');
                const duration = 1500; // Animation duration in ms
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
    }, { threshold: 0.5 });

    counters.forEach(counter => {
        observer.observe(counter);
    });
});
</script>

</body>
</html>
