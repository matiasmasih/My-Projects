<?php
session_start();
include 'config.php';

// Allow ONLY Patient (6)
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 6) {
    header("Location: login.php");
    exit;
}

// Get patient info by email
$patient = null;
$patient_id = 0;

try {
    $stmt = $pdo->prepare("SELECT * FROM patients WHERE email = ?");
    $stmt->execute([$_SESSION['email'] ?? '']);
    $patient = $stmt->fetch();
    $patient_id = $patient ? $patient['id'] : 0;
} catch (Exception $e) {}

// ============================================
// MY APPOINTMENTS (all)
// ============================================
$appointments = [];
try {
    $stmt = $pdo->prepare("
        SELECT a.*,
               CONCAT(u.first_name, ' ', u.last_name) as doctor_name,
               d.consultation_fee
        FROM appointments a
        LEFT JOIN doctors d ON a.doctor_id = d.id
        LEFT JOIN users u ON d.user_id = u.id
        WHERE a.patient_id = ?
        ORDER BY a.scheduled_at DESC
    ");
    $stmt->execute([$patient_id]);
    $appointments = $stmt->fetchAll();
} catch (Exception $e) {}

// ============================================
// UPCOMING APPOINTMENTS
// ============================================
$upcoming = array_filter($appointments, function($a) {
    return in_array($a['status'], ['scheduled', 'confirmed', 'checked_in', 'in_progress']);
});

// ============================================
// PAST APPOINTMENTS
// ============================================
$past = array_filter($appointments, function($a) {
    return in_array($a['status'], ['completed', 'cancelled', 'no_show']);
});

// Set page title
$page_title = 'My Appointments';

// Include shared header
include 'patient_header.php';
?>

<!-- Welcome Banner -->
<div class="welcome-banner">
    <h2>My Appointments 📅</h2>
    <p>View all your appointments with doctors</p>
    <div class="clock">
        <div class="time" id="clock">--:--:--</div>
        <div class="date" id="date">Loading...</div>
    </div>
</div>

<!-- Stats -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon icon-appointments"><i class="bi bi-calendar-check"></i></div>
        <div class="stat-info"><h3 class="counter" data-target="<?php echo count($appointments); ?>">0</h3><p>Total Appointments</p></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon icon-doctors"><i class="bi bi-clock-fill"></i></div>
        <div class="stat-info"><h3 class="counter" data-target="<?php echo count($upcoming); ?>">0</h3><p>Upcoming</p></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon icon-prescriptions"><i class="bi bi-check-circle-fill"></i></div>
        <div class="stat-info"><h3 class="counter" data-target="<?php echo count($past); ?>">0</h3><p>Past</p></div>
    </div>
</div>

<!-- Upcoming Appointments -->
<div class="table-card">
    <h5><i class="bi bi-calendar-week"></i> Upcoming Appointments</h5>
    <?php if (count($upcoming) > 0): ?>
    <div style="overflow-x: auto;">
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Time</th>
                    <th>Doctor</th>
                    <th>Reason</th>
                    <th>Duration</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($upcoming as $appt): ?>
                <tr>
                    <td><strong><?php echo date('M d, Y', strtotime($appt['scheduled_at'])); ?></strong></td>
                    <td><?php echo date('h:i A', strtotime($appt['scheduled_at'])); ?></td>
                    <td><?php echo htmlspecialchars($appt['doctor_name'] ?? 'N/A'); ?></td>
                    <td><?php echo htmlspecialchars($appt['reason'] ?? '-'); ?></td>
                    <td><?php echo $appt['duration_minutes'] ?? 30; ?> min</td>
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
            No upcoming appointments
        </p>
    <?php endif; ?>
</div>

<!-- Past Appointments -->
<div class="table-card">
    <h5><i class="bi bi-clock-history"></i> Past Appointments</h5>
    <?php if (count($past) > 0): ?>
    <div style="overflow-x: auto;">
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Time</th>
                    <th>Doctor</th>
                    <th>Reason</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($past as $appt): ?>
                <tr>
                    <td><?php echo date('M d, Y', strtotime($appt['scheduled_at'])); ?></td>
                    <td><?php echo date('h:i A', strtotime($appt['scheduled_at'])); ?></td>
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
            <i class="bi bi-clock-history" style="font-size: 2rem; display: block; margin-bottom: 10px;"></i>
            No past appointments
        </p>
    <?php endif; ?>
</div>

<?php include 'patient_footer.php'; ?>
