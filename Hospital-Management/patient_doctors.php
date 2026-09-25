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
// ALL DOCTORS
// ============================================
$all_doctors = [];
try {
    $stmt = $pdo->query("
        SELECT d.*,
               u.first_name,
               u.last_name,
               u.email,
               u.phone
        FROM doctors d
        LEFT JOIN users u ON d.user_id = u.id
        WHERE d.status = 'active'
        ORDER BY u.first_name ASC
    ");
    $all_doctors = $stmt->fetchAll();
} catch (Exception $e) {}

// ============================================
// MY DOCTORS (doctors I have appointments with)
// ============================================
$my_doctors = [];
try {
    $stmt = $pdo->prepare("
        SELECT DISTINCT d.*,
               u.first_name,
               u.last_name,
               u.email,
               u.phone,
               COUNT(a.id) AS appointment_count
        FROM appointments a
        LEFT JOIN doctors d ON a.doctor_id = d.id
        LEFT JOIN users u ON d.user_id = u.id
        WHERE a.patient_id = ?
        GROUP BY d.id
        ORDER BY appointment_count DESC
    ");
    $stmt->execute([$patient_id]);
    $my_doctors = $stmt->fetchAll();
} catch (Exception $e) {}

// Set page title
$page_title = 'My Doctors';

// Include shared header
include 'patient_header.php';
?>

<!-- Welcome Banner -->
<div class="welcome-banner">
    <h2>My Doctors 👨‍⚕️</h2>
    <p>View all doctors and your personal doctors</p>
    <div class="clock">
        <div class="time" id="clock">--:--:--</div>
        <div class="date" id="date">Loading...</div>
    </div>
</div>

<!-- Stats -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon icon-doctors"><i class="bi bi-person-badge"></i></div>
        <div class="stat-info">
            <h3 class="counter" data-target="<?php echo count($all_doctors); ?>">0</h3>
            <p>All Doctors</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon icon-appointments"><i class="bi bi-heart-pulse"></i></div>
        <div class="stat-info">
            <h3 class="counter" data-target="<?php echo count($my_doctors); ?>">0</h3>
            <p>My Doctors</p>
        </div>
    </div>
</div>

<!-- Tabs -->
<div class="table-card">
    <div style="display: flex; gap: 10px; margin-bottom: 20px; border-bottom: 2px solid var(--border); padding-bottom: 10px;">
        <button onclick="showTab('all')" id="tab-all" class="tab-btn active-tab">
            <i class="bi bi-people-fill"></i> All Doctors
        </button>
        <button onclick="showTab('mine')" id="tab-mine" class="tab-btn">
            <i class="bi bi-heart-fill"></i> My Doctors
        </button>
    </div>

    <!-- All Doctors Tab -->
    <div id="content-all">
        <h5><i class="bi bi-person-badge"></i> All Doctors</h5>
        <?php if (count($all_doctors) > 0): ?>
        <div style="overflow-x: auto;">
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>License</th>
                        <th>Fee</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($all_doctors as $doc): ?>
                    <tr>
                        <td><strong>Dr. <?php echo htmlspecialchars(($doc['first_name'] ?? '') . ' ' . ($doc['last_name'] ?? '')); ?></strong></td>
                        <td><?php echo htmlspecialchars($doc['email'] ?? '-'); ?></td>
                        <td><?php echo htmlspecialchars($doc['phone'] ?? '-'); ?></td>
                        <td><?php echo htmlspecialchars($doc['license_number'] ?? '-'); ?></td>
                        <td>€<?php echo number_format($doc['consultation_fee'] ?? 0, 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
            <p style="color: var(--text-muted); text-align: center; padding: 30px;">No doctors found</p>
        <?php endif; ?>
    </div>

    <!-- My Doctors Tab -->
    <div id="content-mine" style="display: none;">
        <h5><i class="bi bi-heart-fill"></i> My Doctors</h5>
        <?php if (count($my_doctors) > 0): ?>
        <div style="overflow-x: auto;">
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Appointments</th>
                        <th>Fee</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($my_doctors as $doc): ?>
                    <tr>
                        <td><strong>Dr. <?php echo htmlspecialchars(($doc['first_name'] ?? '') . ' ' . ($doc['last_name'] ?? '')); ?></strong></td>
                        <td><?php echo htmlspecialchars($doc['email'] ?? '-'); ?></td>
                        <td><?php echo htmlspecialchars($doc['phone'] ?? '-'); ?></td>
                        <td><span class="status-badge status-confirmed"><?php echo $doc['appointment_count']; ?> visits</span></td>
                        <td>€<?php echo number_format($doc['consultation_fee'] ?? 0, 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
            <p style="color: var(--text-muted); text-align: center; padding: 30px;">You haven't visited any doctors yet</p>
        <?php endif; ?>
    </div>
</div>

<style>
.tab-btn {
    background: transparent;
    border: none;
    padding: 10px 20px;
    font-weight: 600;
    color: var(--text-muted);
    cursor: pointer;
    border-radius: 8px;
    transition: all 0.3s;
    font-size: 0.95rem;
    display: flex;
    align-items: center;
    gap: 8px;
}

.tab-btn:hover {
    background: rgba(14, 165, 233, 0.1);
    color: var(--primary);
}

.tab-btn.active-tab {
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    color: white;
    box-shadow: 0 4px 15px rgba(14, 165, 233, 0.3);
}
</style>

<script>
function showTab(tab) {
    const allContent = document.getElementById('content-all');
    const mineContent = document.getElementById('content-mine');
    const allTab = document.getElementById('tab-all');
    const mineTab = document.getElementById('tab-mine');

    if (tab === 'all') {
        allContent.style.display = 'block';
        mineContent.style.display = 'none';
        allTab.classList.add('active-tab');
        mineTab.classList.remove('active-tab');
    } else {
        allContent.style.display = 'none';
        mineContent.style.display = 'block';
        allTab.classList.remove('active-tab');
        mineTab.classList.add('active-tab');
    }
}
</script>

<?php include 'patient_footer.php'; ?>
