<?php
session_start();
include 'config.php';

include_once 'access_control.php';

// Require login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Enforce access on Manager Dashboard
$access = require_access('manager_dashboard', 'login.php');
$is_view_only = ($access === 'view');

// ============================================
// FETCH STATS
// ============================================
$stats = [];
$tables = [
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

foreach ($tables as $table => $label) {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM $table");
        $stats[$label] = $stmt->fetch()['total'];
    } catch (Exception $e) {
        $stats[$label] = 0;
    }
}

// ============================================
// REVENUE (LAST 7 DAYS)
// ============================================
$revenue_data = [];
$revenue_labels = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $revenue_labels[] = date('D', strtotime($date));
    try {
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(total_amount), 0) as total FROM invoices WHERE DATE(issued_at) = ?");
        $stmt->execute([$date]);
        $revenue_data[] = floatval($stmt->fetch()['total']);
    } catch (Exception $e) {
        $revenue_data[] = 0;
    }
}

// ============================================
// APPOINTMENTS BY STATUS
// ============================================
$appt_status = [];
try {
    $stmt = $pdo->query("SELECT status, COUNT(*) as total FROM appointments GROUP BY status");
    while ($row = $stmt->fetch()) {
        $appt_status[$row['status']] = $row['total'];
    }
} catch (Exception $e) {}

// ============================================
// RECENT APPOINTMENTS
// ============================================
$recent_appointments = [];
try {
    $stmt = $pdo->query("
        SELECT a.id, a.scheduled_at, a.status, a.reason,
               CONCAT(p.first_name, ' ', p.last_name) as patient_name,
               CONCAT(u.first_name, ' ', u.last_name) as doctor_name
        FROM appointments a
        LEFT JOIN patients p ON a.patient_id = p.id
        LEFT JOIN doctors d ON a.doctor_id = d.id
        LEFT JOIN users u ON d.user_id = u.id
        ORDER BY a.scheduled_at DESC
        LIMIT 5
    ");
    $recent_appointments = $stmt->fetchAll();
} catch (Exception $e) {}

// ============================================
// TODAY'S APPOINTMENTS COUNT
// ============================================
$today_appointments = 0;
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM appointments WHERE DATE(scheduled_at) = CURDATE()");
    $stmt->execute();
    $today_appointments = $stmt->fetch()['total'];
} catch (Exception $e) {}

// ============================================
// TOTAL REVENUE
// ============================================
$total_revenue = 0;
try {
    $stmt = $pdo->query("SELECT COALESCE(SUM(total_amount), 0) as total FROM invoices WHERE status = 'paid'");
    $total_revenue = floatval($stmt->fetch()['total']);
} catch (Exception $e) {}

// Page title + include header
$page_title = 'Manager Dashboard';
include 'manager_header.php';
?>

<!-- WELCOME BANNER -->
<div class="welcome-banner">
    <h2>Welcome back, <?php echo htmlspecialchars($_SESSION['first_name'] ?? 'Manager'); ?> 👋</h2>
    <p>Here's what's happening with your hospital today.</p>

    <?php if ($is_view_only): ?>
    <div style="background: rgba(255,255,255,0.2); padding: 10px 15px; border-radius: 8px; margin-top: 10px; display: inline-block; font-size: 0.9rem;">
        👁️ <strong>View Only Mode</strong> — You cannot make changes
    </div>
    <?php endif; ?>

    <div class="clock">
        <div class="time" id="clock">--:--:--</div>
        <div class="date" id="date">Loading...</div>
    </div>
</div>

<!-- STAT CARDS -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon icon-patients"><i class="bi bi-person-fill"></i></div>
        <div class="stat-info">
            <h3 class="counter" data-target="<?php echo $stats['Patients']; ?>">0</h3>
            <p>Patients</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon icon-doctors"><i class="bi bi-person-badge"></i></div>
        <div class="stat-info">
            <h3 class="counter" data-target="<?php echo $stats['Doctors']; ?>">0</h3>
            <p>Doctors</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon icon-appointments"><i class="bi bi-calendar-check"></i></div>
        <div class="stat-info">
            <h3 class="counter" data-target="<?php echo $stats['Appointments']; ?>">0</h3>
            <p>Appointments</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon icon-invoices"><i class="bi bi-receipt"></i></div>
        <div class="stat-info">
            <h3 class="counter" data-target="<?php echo $stats['Invoices']; ?>">0</h3>
            <p>Invoices</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon icon-pharmacy"><i class="bi bi-capsule"></i></div>
        <div class="stat-info">
            <h3 class="counter" data-target="<?php echo $stats['Pharmacy']; ?>">0</h3>
            <p>Pharmacy</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon icon-medicines"><i class="bi bi-heart-pulse"></i></div>
        <div class="stat-info">
            <h3 class="counter" data-target="<?php echo $stats['Medicines']; ?>">0</h3>
            <p>Medicines</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon icon-wards"><i class="bi bi-house-door"></i></div>
        <div class="stat-info">
            <h3 class="counter" data-target="<?php echo $stats['Wards']; ?>">0</h3>
            <p>Wards</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon icon-rooms"><i class="bi bi-door-closed"></i></div>
        <div class="stat-info">
            <h3 class="counter" data-target="<?php echo $stats['Rooms']; ?>">0</h3>
            <p>Rooms</p>
        </div>
    </div>
</div>

<!-- CHARTS -->
<div class="charts-grid">
    <div class="chart-card">
        <h5><i class="bi bi-graph-up-arrow"></i> Revenue (Last 7 Days)</h5>
        <div class="chart-wrapper">
            <canvas id="revenueChart"></canvas>
        </div>
    </div>
    <div class="chart-card">
        <h5><i class="bi bi-pie-chart-fill"></i> Appointments Status</h5>
        <div class="chart-wrapper">
            <canvas id="apptChart"></canvas>
        </div>
    </div>
</div>

<!-- QUICK ACTIONS -->
<h5 style="font-weight: 700; margin-bottom: 15px; color: var(--text);">
    <i class="bi bi-lightning-charge-fill" style="color: var(--primary);"></i> Quick Actions
</h5>
<div class="quick-actions">
    <?php if (!$is_view_only): ?>
    <a href="add_patients.php" class="action-btn"><i class="bi bi-person-plus"></i> Add Patient</a>
    <a href="add_doctor.php" class="action-btn"><i class="bi bi-person-badge"></i> Add Doctor</a>
    <a href="appointments.php" class="action-btn"><i class="bi bi-calendar-plus"></i> New Appointment</a>
    <a href="invoices.php" class="action-btn"><i class="bi bi-receipt"></i> Create Invoice</a>
    <a href="restock.php" class="action-btn"><i class="bi bi-box-seam"></i> Restock Pharmacy</a>
    <?php endif; ?>
    <a href="manager_messages.php" class="action-btn"><i class="bi bi-chat-dots"></i> View Messages</a>
</div>

<!-- RECENT APPOINTMENTS -->
<div class="table-card">
    <h5><i class="bi bi-clock-history"></i> Recent Appointments</h5>
    <?php if (count($recent_appointments) > 0): ?>
    <div style="overflow-x: auto;">
        <table>
            <thead>
                <tr>
                    <th>Patient</th>
                    <th>Doctor</th>
                    <th>Scheduled</th>
                    <th>Reason</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recent_appointments as $appt): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($appt['patient_name'] ?? 'N/A'); ?></strong></td>
                    <td><?php echo htmlspecialchars($appt['doctor_name'] ?? 'N/A'); ?></td>
                    <td><?php echo $appt['scheduled_at'] ? date('M d, Y H:i', strtotime($appt['scheduled_at'])) : 'N/A'; ?></td>
                    <td><?php echo htmlspecialchars($appt['reason'] ?? '-'); ?></td>
                    <td>
                        <span class="status-badge status-<?php echo $appt['status']; ?>">
                            <?php echo str_replace('_', ' ', $appt['status']); ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
        <p style="color: var(--text-muted); text-align: center; padding: 30px;">No appointments yet.</p>
    <?php endif; ?>
</div>

<!-- PAGE-SPECIFIC CHARTS SCRIPT -->
<script>
// REVENUE CHART
const revenueCtx = document.getElementById('revenueChart').getContext('2d');
const gradient = revenueCtx.createLinearGradient(0, 0, 0, 300);
gradient.addColorStop(0, 'rgba(16, 185, 129, 0.3)');
gradient.addColorStop(1, 'rgba(16, 185, 129, 0.01)');

new Chart(revenueCtx, {
    type: 'line',
    data: {
        labels: <?php echo json_encode($revenue_labels); ?>,
        datasets: [{
            label: 'Revenue (€)',
            data: <?php echo json_encode($revenue_data); ?>,
            borderColor: '#10b981',
            backgroundColor: gradient,
            borderWidth: 3,
            fill: true,
            tension: 0.4,
            pointBackgroundColor: '#10b981',
            pointBorderColor: '#fff',
            pointBorderWidth: 2,
            pointRadius: 5,
            pointHoverRadius: 7
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
            y: {
                beginAtZero: true,
                grid: { color: 'rgba(0,0,0,0.05)' },
                ticks: { color: '#64748b', font: { size: 11 } }
            },
            x: {
                grid: { display: false },
                ticks: { color: '#64748b', font: { size: 11 } }
            }
        }
    }
});

// APPOINTMENTS STATUS CHART
const apptCtx = document.getElementById('apptChart').getContext('2d');
const statusData = <?php echo json_encode($appt_status); ?>;
const statusLabels = Object.keys(statusData).map(s => s.replace('_', ' '));
const statusValues = Object.values(statusData);

new Chart(apptCtx, {
    type: 'doughnut',
    data: {
        labels: statusLabels.length ? statusLabels : ['No Data'],
        datasets: [{
            data: statusValues.length ? statusValues : [1],
            backgroundColor: ['#8b5cf6', '#10b981', '#22c55e', '#ef4444', '#06b6d4', '#f59e0b', '#6b7280'],
            borderWidth: 0,
            hoverOffset: 10
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom',
                labels: {
                    color: '#64748b',
                    font: { size: 11 },
                    padding: 12,
                    usePointStyle: true,
                    pointStyle: 'circle'
                }
            }
        },
        cutout: '65%'
    }
});
</script>

<?php include 'manager_footer.php'; ?>
