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
// MY PRESCRIPTIONS
// ============================================
$prescriptions = [];
try {
    $stmt = $pdo->prepare("
        SELECT p.*,
               m.name AS medicine_name,
               m.unit_price,
               CONCAT(u.first_name, ' ', u.last_name) AS doctor_name
        FROM prescriptions p
        LEFT JOIN medicines m ON p.medicine_id = m.id
        LEFT JOIN doctors d ON p.doctor_id = d.id
        LEFT JOIN users u ON d.user_id = u.id
        WHERE p.patient_id = ?
        ORDER BY p.created_at DESC
    ");
    $stmt->execute([$patient_id]);
    $prescriptions = $stmt->fetchAll();
} catch (Exception $e) {
    $prescriptions = [];
}

// ============================================
// STATS
// ============================================
$total_prescriptions = count($prescriptions);
$active_prescriptions = 0;
$completed_prescriptions = 0;

foreach ($prescriptions as $p) {
    if (($p['status'] ?? '') === 'active') {
        $active_prescriptions++;
    } else {
        $completed_prescriptions++;
    }
}

// Set page title
$page_title = 'My Prescriptions';

// Include shared header
include 'patient_header.php';
?>

<!-- Welcome Banner -->
<div class="welcome-banner">
    <h2>My Prescriptions 💊</h2>
    <p>View all your prescribed medications</p>
    <div class="clock">
        <div class="time" id="clock">--:--:--</div>
        <div class="date" id="date">Loading...</div>
    </div>
</div>

<!-- Stats -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon icon-prescriptions"><i class="bi bi-capsule"></i></div>
        <div class="stat-info">
            <h3 class="counter" data-target="<?php echo $total_prescriptions; ?>">0</h3>
            <p>Total Prescriptions</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon icon-records"><i class="bi bi-check-circle-fill"></i></div>
        <div class="stat-info">
            <h3 class="counter" data-target="<?php echo $active_prescriptions; ?>">0</h3>
            <p>Active</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon icon-doctors"><i class="bi bi-clock-history"></i></div>
        <div class="stat-info">
            <h3 class="counter" data-target="<?php echo $completed_prescriptions; ?>">0</h3>
            <p>Completed</p>
        </div>
    </div>
</div>

<!-- Prescriptions Table -->
<div class="table-card">
    <h5><i class="bi bi-capsule"></i> All Prescriptions</h5>
    <?php if (count($prescriptions) > 0): ?>
    <div style="overflow-x: auto;">
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Medicine</th>
                    <th>Doctor</th>
                    <th>Dosage</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($prescriptions as $p): ?>
                <tr>
                    <td><?php echo !empty($p['created_at']) ? date('M d, Y', strtotime($p['created_at'])) : '-'; ?></td>
                    <td><strong><?php echo htmlspecialchars($p['medicine_name'] ?? 'N/A'); ?></strong></td>
                    <td><?php echo htmlspecialchars($p['doctor_name'] ?? 'N/A'); ?></td>
                    <td><?php echo htmlspecialchars($p['dosage'] ?? '-'); ?></td>
                    <td>
                        <span class="status-badge status-<?php echo htmlspecialchars($p['status'] ?? 'draft'); ?>">
                            <?php echo htmlspecialchars($p['status'] ?? '-'); ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
        <p style="color: var(--text-muted); text-align: center; padding: 30px;">
            <i class="bi bi-capsule" style="font-size: 2rem; display: block; margin-bottom: 10px;"></i>
            No prescriptions yet
        </p>
    <?php endif; ?>
</div>

<?php include 'patient_footer.php'; ?>
