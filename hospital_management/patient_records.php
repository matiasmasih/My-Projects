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
// MY MEDICAL RECORDS
// ============================================
$records = [];
try {
    $stmt = $pdo->prepare("
        SELECT mr.*,
               CONCAT(u.first_name, ' ', u.last_name) AS doctor_name
        FROM medical_records mr
        LEFT JOIN doctors d ON mr.doctor_id = d.id
        LEFT JOIN users u ON d.user_id = u.id
        WHERE mr.patient_id = ?
        ORDER BY mr.created_at DESC
    ");
    $stmt->execute([$patient_id]);
    $records = $stmt->fetchAll();
} catch (Exception $e) {
    $records = [];
}

// ============================================
// STATS
// ============================================
$total_records = count($records);
$total_diagnoses = 0;
$total_allergies = 0;

foreach ($records as $r) {
    if (!empty($r['diagnosis'])) {
        $total_diagnoses++;
    }
    if (!empty($r['allergies'])) {
        $total_allergies++;
    }
}

// Set page title
$page_title = 'Medical Records';

// Include shared header
include 'patient_header.php';
?>

<!-- Welcome Banner -->
<div class="welcome-banner">
    <h2>Medical Records 📋</h2>
    <p>View all your medical history and diagnoses</p>
    <div class="clock">
        <div class="time" id="clock">--:--:--</div>
        <div class="date" id="date">Loading...</div>
    </div>
</div>

<!-- Stats -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon icon-records"><i class="bi bi-file-medical"></i></div>
        <div class="stat-info">
            <h3 class="counter" data-target="<?php echo $total_records; ?>">0</h3>
            <p>Total Records</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon icon-appointments"><i class="bi bi-clipboard-pulse"></i></div>
        <div class="stat-info">
            <h3 class="counter" data-target="<?php echo $total_diagnoses; ?>">0</h3>
            <p>Diagnoses</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon icon-invoices"><i class="bi bi-exclamation-triangle-fill"></i></div>
        <div class="stat-info">
            <h3 class="counter" data-target="<?php echo $total_allergies; ?>">0</h3>
            <p>Allergies</p>
        </div>
    </div>
</div>

<!-- Medical Records Table -->
<div class="table-card">
    <h5><i class="bi bi-file-medical"></i> All Medical Records</h5>
    <?php if (count($records) > 0): ?>
    <div style="overflow-x: auto;">
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Doctor</th>
                    <th>Diagnosis</th>
                    <th>Treatment</th>
                    <th>Allergies</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($records as $r): ?>
                <tr>
                    <td><?php echo !empty($r['created_at']) ? date('M d, Y', strtotime($r['created_at'])) : '-'; ?></td>
                    <td><?php echo htmlspecialchars($r['doctor_name'] ?? 'N/A'); ?></td>
                    <td><strong><?php echo htmlspecialchars($r['diagnosis'] ?? '-'); ?></strong></td>
                    <td><?php echo htmlspecialchars($r['treatment'] ?? '-'); ?></td>
                    <td><?php echo htmlspecialchars($r['allergies'] ?? '-'); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
        <p style="color: var(--text-muted); text-align: center; padding: 30px;">
            <i class="bi bi-file-medical" style="font-size: 2rem; display: block; margin-bottom: 10px;"></i>
            No medical records yet
        </p>
    <?php endif; ?>
</div>

<?php include 'patient_footer.php'; ?>
