<?php
session_start();
include 'config.php';
include_once 'access_control.php';

// Require login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Enforce access on Doctor Dashboard
$access = require_access('doctor_dashboard', 'login.php');
$is_view_only = ($access === 'view');

$doctor_user_id = $_SESSION['user_id'];

// ============================================
// GET DOCTOR ID
// ============================================
$doctor_id = 0;
try {
    $stmt = $pdo->prepare("SELECT id FROM doctors WHERE user_id = ?");
    $stmt->execute([$doctor_user_id]);
    $doctor_row = $stmt->fetch();
    $doctor_id = $doctor_row ? $doctor_row['id'] : 0;
} catch (Exception $e) {}

// ============================================
// TODAY'S APPOINTMENTS
// ============================================
$today_appointments = [];
try {
    $stmt = $pdo->prepare("
        SELECT a.*, CONCAT(p.first_name, ' ', p.last_name) as patient_name
        FROM appointments a
        LEFT JOIN patients p ON a.patient_id = p.id
        WHERE a.doctor_id = ? AND DATE(a.scheduled_at) = CURDATE()
        ORDER BY a.scheduled_at ASC
    ");
    $stmt->execute([$doctor_id]);
    $today_appointments = $stmt->fetchAll();
} catch (Exception $e) {}

// ============================================
// UPCOMING APPOINTMENTS
// ============================================
$upcoming_appointments = [];
try {
    $stmt = $pdo->prepare("
        SELECT a.*, CONCAT(p.first_name, ' ', p.last_name) as patient_name
        FROM appointments a
        LEFT JOIN patients p ON a.patient_id = p.id
        WHERE a.doctor_id = ? AND DATE(a.scheduled_at) > CURDATE()
        ORDER BY a.scheduled_at ASC
        LIMIT 5
    ");
    $stmt->execute([$doctor_id]);
    $upcoming_appointments = $stmt->fetchAll();
} catch (Exception $e) {}

// ============================================
// STATS
// ============================================
$total_appointments = 0;
$total_patients = 0;
$total_completed = 0;
$total_pending = 0;

try {
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM appointments WHERE doctor_id = ?");
    $stmt->execute([$doctor_id]);
    $total_appointments = $stmt->fetch()['total'];

    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT patient_id) as total FROM appointments WHERE doctor_id = ?");
    $stmt->execute([$doctor_id]);
    $total_patients = $stmt->fetch()['total'];

    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM appointments WHERE doctor_id = ? AND status = 'completed'");
    $stmt->execute([$doctor_id]);
    $total_completed = $stmt->fetch()['total'];

    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM appointments WHERE doctor_id = ? AND status IN ('scheduled', 'confirmed', 'in_progress')");
    $stmt->execute([$doctor_id]);
    $total_pending = $stmt->fetch()['total'];
} catch (Exception $e) {}

// ============================================
// APPOINTMENTS BY STATUS
// ============================================
$appt_status = [];
try {
    $stmt = $pdo->prepare("SELECT status, COUNT(*) as total FROM appointments WHERE doctor_id = ? GROUP BY status");
    $stmt->execute([$doctor_id]);
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
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM appointments WHERE doctor_id = ? AND DATE(scheduled_at) = ?");
        $stmt->execute([$doctor_id, $date]);
        $week_data[] = intval($stmt->fetch()['total']);
    } catch (Exception $e) {
        $week_data[] = 0;
    }
}

$page_title = 'Doctor Dashboard';
include 'doctor_header.php';
?>

<!-- WELCOME BANNER -->
<div class="welcome-banner">
    <h2>Welcome, Dr. <?php echo htmlspecialchars(($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['last_name'] ?? '')); ?> 👋</h2>
    <p>Here's your daily overview</p>

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
        <div class="stat-icon icon-appointments"><i class="bi bi-calendar-check"></i></div>
        <div class="stat-info">
            <h3 class="counter" data-target="<?php echo $total_appointments; ?>">0</h3>
            <p>Total Appointments</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon icon-patients"><i class="bi bi-people-fill"></i></div>
        <div class="stat-info">
            <h3 class="counter" data-target="<?php echo $total_patients; ?>">0</h3>
            <p>My Patients</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon icon-completed"><i class="bi bi-check-circle-fill"></i></div>
        <div class="stat-info">
            <h3 class="counter" data-target="<?php echo $total_completed; ?>">0</h3>
            <p>Completed</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon icon-pending"><i class="bi bi-clock-fill"></i></div>
        <div class="stat-info">
            <h3 class="counter" data-target="<?php echo $total_pending; ?>">0</h3>
            <p>Pending</p>
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
    <a href="patients.php" class="action-btn"><i class="bi bi-person-plus"></i> My Patients</a>
    <a href="appointments.php" class="action-btn"><i class="bi bi-calendar-plus"></i> Appointments</a>
    <a href="medical_records.php" class="action-btn"><i class="bi bi-file-medical"></i> Medical Records</a>
    <a href="prescriptions.php" class="action-btn"><i class="bi bi-capsule"></i> Prescriptions</a>
    <a href="medicines.php" class="action-btn"><i class="bi bi-heart-pulse"></i> Medicines</a>
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
                    <th>Reason</th>
                    <th>Duration</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($today_appointments as $appt): ?>
                <tr>
                    <td><strong><?php echo date('H:i', strtotime($appt['scheduled_at'])); ?></strong></td>
                    <td><?php echo htmlspecialchars($appt['patient_name'] ?? 'N/A'); ?></td>
                    <td><?php echo htmlspecialchars($appt['reason'] ?? '-'); ?></td>
                    <td><?php echo $appt['duration_minutes']; ?> min</td>
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

<!-- UPCOMING APPOINTMENTS -->
<div class="table-card">
    <h5><i class="bi bi-calendar-week"></i> Upcoming Appointments</h5>
    <?php if (count($upcoming_appointments) > 0): ?>
    <div style="overflow-x: auto;">
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Time</th>
                    <th>Patient</th>
                    <th>Reason</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($upcoming_appointments as $appt): ?>
                <tr>
                    <td><?php echo date('M d, Y', strtotime($appt['scheduled_at'])); ?></td>
                    <td><?php echo date('H:i', strtotime($appt['scheduled_at'])); ?></td>
                    <td><strong><?php echo htmlspecialchars($appt['patient_name'] ?? 'N/A'); ?></strong></td>
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
        <p style="color: var(--text-muted); text-align: center; padding: 30px;">No upcoming appointments</p>
    <?php endif; ?>
</div>

<!-- PAGE CHARTS -->
<script>
// WEEK CHART
const weekCtx = document.getElementById('weekChart').getContext('2d');
const weekGradient = weekCtx.createLinearGradient(0, 0, 0, 300);
weekGradient.addColorStop(0, 'rgba(16, 185, 129, 0.3)');
weekGradient.addColorStop(1, 'rgba(16, 185, 129, 0.01)');

new Chart(weekCtx, {
    type: 'bar',
    data: {
        labels: <?php echo json_encode($week_labels); ?>,
        datasets: [{
            label: 'Appointments',
            data: <?php echo json_encode($week_data); ?>,
            backgroundColor: weekGradient,
            borderColor: '#10b981',
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

<?php include 'doctor_footer.php'; ?>
