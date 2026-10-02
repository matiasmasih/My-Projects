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
// MY APPOINTMENTS
// ============================================
$my_appointments = [];
try {
    $stmt = $pdo->prepare("
        SELECT a.*, CONCAT(u.first_name, ' ', u.last_name) as doctor_name
        FROM appointments a
        LEFT JOIN doctors d ON a.doctor_id = d.id
        LEFT JOIN users u ON d.user_id = u.id
        WHERE a.patient_id = ?
        ORDER BY a.scheduled_at DESC
        LIMIT 10
    ");
    $stmt->execute([$patient_id]);
    $my_appointments = $stmt->fetchAll();
} catch (Exception $e) {}

// ============================================
// MY INVOICES
// ============================================
$my_invoices = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM invoices WHERE patient_id = ? ORDER BY issued_at DESC LIMIT 5");
    $stmt->execute([$patient_id]);
    $my_invoices = $stmt->fetchAll();
} catch (Exception $e) {}

// ============================================
// STATS
// ============================================
$total_appointments = 0;
$total_invoices = 0;
$total_paid = 0;
$total_pending = 0;

try {
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM appointments WHERE patient_id = ?");
    $stmt->execute([$patient_id]);
    $total_appointments = $stmt->fetch()['total'];

    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM invoices WHERE patient_id = ?");
    $stmt->execute([$patient_id]);
    $total_invoices = $stmt->fetch()['total'];

    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM invoices WHERE patient_id = ? AND status = 'paid'");
    $stmt->execute([$patient_id]);
    $total_paid = $stmt->fetch()['total'];

    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM appointments WHERE patient_id = ? AND status IN ('scheduled', 'confirmed')");
    $stmt->execute([$patient_id]);
    $total_pending = $stmt->fetch()['total'];
} catch (Exception $e) {}

// Set page title
$page_title = 'Patient Dashboard';

// Include shared header
include 'patient_header.php';
?>

<!-- Welcome Banner -->
<div class="welcome-banner">
    <h2>Welcome, <?php echo htmlspecialchars($patient['first_name'] ?? $_SESSION['first_name']); ?> 👋</h2>
    <p>Your personal health portal</p>
    <div class="clock">
        <div class="time" id="clock">--:--:--</div>
        <div class="date" id="date">Loading...</div>
    </div>
</div>

<!-- Stats -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon icon-appointments"><i class="bi bi-calendar-check"></i></div>
        <div class="stat-info"><h3 class="counter" data-target="<?php echo $total_appointments; ?>">0</h3><p>My Appointments</p></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon icon-invoices"><i class="bi bi-receipt"></i></div>
        <div class="stat-info"><h3 class="counter" data-target="<?php echo $total_invoices; ?>">0</h3><p>My Invoices</p></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon icon-prescriptions"><i class="bi bi-check-circle-fill"></i></div>
        <div class="stat-info"><h3 class="counter" data-target="<?php echo $total_paid; ?>">0</h3><p>Paid</p></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon icon-doctors"><i class="bi bi-clock-fill"></i></div>
        <div class="stat-info"><h3 class="counter" data-target="<?php echo $total_pending; ?>">0</h3><p>Upcoming</p></div>
    </div>
</div>

<!-- My Appointments -->
<div class="table-card">
    <h5><i class="bi bi-calendar-check"></i> My Appointments</h5>
    <?php if (count($my_appointments) > 0): ?>
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
                <?php foreach ($my_appointments as $appt): ?>
                <tr>
                    <td><?php echo date('M d, Y', strtotime($appt['scheduled_at'])); ?></td>
                    <td><?php echo date('H:i', strtotime($appt['scheduled_at'])); ?></td>
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
        <p style="color: var(--text-muted); text-align: center; padding: 30px;">No appointments yet</p>
    <?php endif; ?>
</div>

<!-- My Invoices -->
<div class="table-card">
    <h5><i class="bi bi-receipt"></i> My Invoices</h5>
    <?php if (count($my_invoices) > 0): ?>
    <div style="overflow-x: auto;">
        <table>
            <thead>
                <tr>
                    <th>Invoice #</th>
                    <th>Amount</th>
                    <th>Status</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($my_invoices as $inv): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($inv['invoice_number'] ?? '-'); ?></strong></td>
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

<?php include 'patient_footer.php'; ?>
