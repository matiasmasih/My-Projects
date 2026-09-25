<?php
session_start();
include 'config.php';
include_once 'access_control.php';

// Require login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Enforce access on Staff Dashboard
$access = require_access('staff_dashboard', 'login.php');
$is_view_only = ($access === 'view');

// ============================================
// FETCH STATS
// ============================================
$total_patients = 0;
$total_appointments = 0;
$total_invoices = 0;
$total_messages = 0;

try {
    $total_patients = $pdo->query("SELECT COUNT(*) as total FROM patients")->fetch()['total'];
    $total_appointments = $pdo->query("SELECT COUNT(*) as total FROM appointments")->fetch()['total'];
    $total_invoices = $pdo->query("SELECT COUNT(*) as total FROM invoices")->fetch()['total'];
    $total_messages = $pdo->query("SELECT COUNT(*) as total FROM messages")->fetch()['total'];
} catch (Exception $e) {}

// ============================================
// TODAY'S APPOINTMENTS
// ============================================
$today_appointments = [];
try {
    $stmt = $pdo->query("
        SELECT a.*, CONCAT(p.first_name, ' ', p.last_name) as patient_name,
               CONCAT(u.first_name, ' ', u.last_name) as doctor_name
        FROM appointments a
        LEFT JOIN patients p ON a.patient_id = p.id
        LEFT JOIN doctors d ON a.doctor_id = d.id
        LEFT JOIN users u ON d.user_id = u.id
        WHERE DATE(a.scheduled_at) = CURDATE()
        ORDER BY a.scheduled_at ASC
    ");
    $today_appointments = $stmt->fetchAll();
} catch (Exception $e) {}

// ============================================
// RECENT INVOICES
// ============================================
$recent_invoices = [];
try {
    $stmt = $pdo->query("
        SELECT i.*, CONCAT(p.first_name, ' ', p.last_name) as patient_name
        FROM invoices i
        LEFT JOIN patients p ON i.patient_id = p.id
        ORDER BY i.issued_at DESC
        LIMIT 5
    ");
    $recent_invoices = $stmt->fetchAll();
} catch (Exception $e) {}

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
// WEEKLY APPOINTMENTS
// ============================================
$week_labels = [];
$week_data = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $week_labels[] = date('D', strtotime($date));
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM appointments WHERE DATE(scheduled_at) = ?");
        $stmt->execute([$date]);
        $week_data[] = intval($stmt->fetch()['total']);
    } catch (Exception $e) {
        $week_data[] = 0;
    }
}

$page_title = 'Staff Dashboard';
include 'staff_header.php';
?>

<!-- WELCOME BANNER -->
<div class="welcome-banner">
    <h2>Welcome, <?php echo htmlspecialchars(($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['last_name'] ?? '')); ?> 👋</h2>
    <p>Staff Portal — Manage your daily tasks</p>

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

<!-- STATS -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon icon-patients"><i class="bi bi-person-fill"></i></div>
        <div class="stat-info">
            <h3 class="counter" data-target="<?php echo $total_patients; ?>">0</h3>
            <p>Total Patients</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon icon-appointments"><i class="bi bi-calendar-check"></i></div>
        <div class="stat-info">
            <h3 class="counter" data-target="<?php echo $total_appointments; ?>">0</h3>
            <p>Appointments</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon icon-invoices"><i class="bi bi-receipt"></i></div>
        <div class="stat-info">
            <h3 class="counter" data-target="<?php echo $total_invoices; ?>">0</h3>
            <p>Invoices</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon icon-messages"><i class="bi bi-chat-dots"></i></div>
        <div class="stat-info">
            <h3 class="counter" data-target="<?php echo $total_messages; ?>">0</h3>
            <p>Messages</p>
        </div>
    </div>
</div>

<!-- CHARTS -->
<div class="charts-grid">
    <div class="chart-card">
        <h5><i class="bi bi-bar-chart-fill"></i> Appointments (Last 7 Days)</h5>
        <div class="chart-wrapper"><canvas id="weekChart"></canvas></div>
    </div>
    <div class="chart-card">
        <h5><i class="bi bi-pie-chart-fill"></i> Appointments by Status</h5>
        <div class="chart-wrapper"><canvas id="statusChart"></canvas></div>
    </div>
</div>

<!-- QUICK ACTIONS -->
<h5 style="font-weight: 700; margin-bottom: 15px; color: var(--text);">
    <i class="bi bi-lightning-charge-fill" style="color: var(--primary);"></i> Quick Actions
</h5>
<div class="quick-actions">
    <a href="patients.php" class="action-btn"><i class="bi bi-person-plus"></i> Patients</a>
    <a href="appointments.php" class="action-btn"><i class="bi bi-calendar-plus"></i> Appointments</a>
    <a href="invoices.php" class="action-btn"><i class="bi bi-receipt"></i> Invoices</a>
    <a href="payments.php" class="action-btn"><i class="bi bi-cash-stack"></i> Payments</a>
    <a href="messages.php" class="action-btn"><i class="bi bi-chat-dots"></i> Messages</a>
    <a href="rooms.php" class="action-btn"><i class="bi bi-door-closed"></i> Rooms</a>
</div>

<!-- TODAY'S APPOINTMENTS -->
<div class="table-card">
    <h5><i class="bi bi-calendar-day"></i> Today's Appointments (<?php echo date('M d, Y'); ?>)</h5>
    <?php if (count($today_appointments) > 0): ?>
    <div style="overflow-x: auto;">
        <table>
            <thead>
                <tr>
                    <th>Time</th>
                    <th>Patient</th>
                    <th>Doctor</th>
                    <th>Reason</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($today_appointments as $appt): ?>
                <tr>
                    <td><strong><?php echo date('H:i', strtotime($appt['scheduled_at'])); ?></strong></td>
                    <td><?php echo htmlspecialchars($appt['patient_name'] ?? 'N/A'); ?></td>
                    <td><?php echo htmlspecialchars($appt['doctor_name'] ?? 'N/A'); ?></td>
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
        <p style="color: var(--text-muted); text-align: center; padding: 30px;">
            <i class="bi bi-calendar-x" style="font-size: 2rem; display: block; margin-bottom: 10px;"></i>
            No appointments scheduled for today
        </p>
    <?php endif; ?>
</div>

<!-- RECENT INVOICES -->
<div class="table-card">
    <h5><i class="bi bi-receipt"></i> Recent Invoices</h5>
    <?php if (count($recent_invoices) > 0): ?>
    <div style="overflow-x: auto;">
        <table>
            <thead>
                <tr>
                    <th>Invoice #</th>
                    <th>Patient</th>
                    <th>Amount</th>
                    <th>Status</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recent_invoices as $inv): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($inv['invoice_number'] ?? '-'); ?></strong></td>
                    <td><?php echo htmlspecialchars($inv['patient_name'] ?? 'N/A'); ?></td>
                    <td>€<?php echo number_format($inv['total_amount'] ?? 0, 2); ?></td>
                    <td>
                        <span class="status-badge status-<?php echo $inv['status']; ?>">
                            <?php echo str_replace('_', ' ', $inv['status']); ?>
                        </span>
                    </td>
                    <td><?php echo $inv['issued_at'] ? date('M d, Y', strtotime($inv['issued_at'])) : '-'; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
        <p style="color: var(--text-muted); text-align: center; padding: 30px;">No invoices yet</p>
    <?php endif; ?>
</div>

<!-- PAGE CHARTS -->
<script>
// WEEK CHART
const weekCtx = document.getElementById('weekChart').getContext('2d');
const weekGradient = weekCtx.createLinearGradient(0, 0, 0, 300);
weekGradient.addColorStop(0, 'rgba(59, 130, 246, 0.3)');
weekGradient.addColorStop(1, 'rgba(59, 130, 246, 0.01)');

new Chart(weekCtx, {
    type: 'bar',
    data: {
        labels: <?php echo json_encode($week_labels); ?>,
        datasets: [{
            label: 'Appointments',
            data: <?php echo json_encode($week_data); ?>,
            backgroundColor: weekGradient,
            borderColor: '#3b82f6',
            borderWidth: 2,
            borderRadius: 8
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
            y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.05)' }, ticks: { color: '#64748b', font: { size: 11 }, stepSize: 1 } },
            x: { grid: { display: false }, ticks: { color: '#64748b', font: { size: 11 } } }
        }
    }
});

// STATUS CHART
const statusCtx = document.getElementById('statusChart').getContext('2d');
const statusData = <?php echo json_encode($appt_status); ?>;
const statusLabels = Object.keys(statusData).map(s => s.replace('_', ' '));
const statusValues = Object.values(statusData);

new Chart(statusCtx, {
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
                labels: { color: '#64748b', font: { size: 11 }, padding: 10, usePointStyle: true, pointStyle: 'circle' }
            }
        },
        cutout: '65%'
    }
});
</script>

<?php include 'staff_footer.php'; ?>
