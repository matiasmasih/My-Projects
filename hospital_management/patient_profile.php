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

// Stats
$total_appointments = 0;
$total_invoices = 0;
$total_prescriptions = 0;

try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE patient_id = ?");
    $stmt->execute([$patient_id]);
    $total_appointments = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM invoices WHERE patient_id = ?");
    $stmt->execute([$patient_id]);
    $total_invoices = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM prescriptions pr
        LEFT JOIN medical_records mr ON pr.medical_record_id = mr.id
        WHERE mr.patient_id = ?
    ");
    $stmt->execute([$patient_id]);
    $total_prescriptions = (int)$stmt->fetchColumn();
} catch (Exception $e) {}

// Success flash
$success = $_SESSION['flash_success'] ?? '';
unset($_SESSION['flash_success']);

$page_title = 'My Profile';
include 'patient_header.php';
?>

<?php if ($success): ?>
<div class="alert alert-success" style="border-radius:12px; padding:12px 16px; margin-bottom:15px; background:#dcfce7; color:#166534;">
    <i class="bi bi-check-circle-fill"></i> <?= htmlspecialchars($success) ?>
</div>
<?php endif; ?>

<!-- ============================================
     PROFILE HEADER CARD
============================================ -->
<div class="table-card" style="padding:0; overflow:hidden;">
    <div style="background: linear-gradient(135deg, var(--primary), var(--primary-dark)); padding:28px 30px; color:white; position:relative;">
        <div class="d-flex align-items-center gap-3" style="flex-wrap:wrap;">
            <!-- Avatar -->
            <?php if (!empty($patient['profile_image']) && file_exists(__DIR__ . '/' . $patient['profile_image'])): ?>
                <img src="<?= htmlspecialchars($patient['profile_image']) ?>"
                     alt="Avatar"
                     style="width:80px; height:80px; border-radius:50%; object-fit:cover; border:3px solid rgba(255,255,255,0.5); flex-shrink:0;">
            <?php else: ?>
                <div style="width:80px; height:80px; border-radius:50%; background:rgba(255,255,255,0.25); display:flex; align-items:center; justify-content:center; font-weight:800; font-size:2rem; border:3px solid rgba(255,255,255,0.5); flex-shrink:0;">
                    <?= htmlspecialchars(strtoupper(substr($patient['first_name'] ?? 'P', 0, 1) . substr($patient['last_name'] ?? '', 0, 1))) ?>
                </div>
            <?php endif; ?>
            <!-- Info -->
            <div style="flex:1; min-width:200px;">
                <h2 style="font-weight:800; font-size:1.6rem; margin:0 0 4px;">
                    <?= htmlspecialchars(($patient['first_name'] ?? '') . ' ' . ($patient['last_name'] ?? '')) ?>
                </h2>
                <div style="opacity:0.95; font-size:0.9rem;">
                    MRN: <strong><?= htmlspecialchars($patient['medical_record_number'] ?? '-') ?></strong>
                    <span style="margin:0 8px;">·</span>
                    Status: <strong><?= htmlspecialchars(ucfirst($patient['status'] ?? 'Active')) ?></strong>
                </div>
                <?php if (!empty($patient['email'])): ?>
                <div style="opacity:0.9; font-size:0.88rem; margin-top:4px;">
                    <i class="bi bi-envelope"></i> <?= htmlspecialchars($patient['email']) ?>
                    <?php if (!empty($patient['phone'])): ?>
                        <span style="margin:0 8px;">·</span>
                        <i class="bi bi-telephone"></i> <?= htmlspecialchars($patient['phone']) ?>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            <!-- Actions -->
            <div style="display:flex; gap:8px; flex-wrap:wrap;">
                <a href="edit_patient_profile.php" style="background:rgba(255,255,255,0.2); color:white; padding:8px 16px; border-radius:8px; text-decoration:none; font-weight:600; font-size:0.88rem; display:inline-flex; align-items:center; gap:6px;">
                    <i class="bi bi-pencil"></i> Edit Profile
                </a>
                <a href="patient_change_password.php" style="background:rgba(255,255,255,0.2); color:white; padding:8px 16px; border-radius:8px; text-decoration:none; font-weight:600; font-size:0.88rem; display:inline-flex; align-items:center; gap:6px;">
                    <i class="bi bi-shield-lock"></i> Change Password
                </a>
            </div>
        </div>
    </div>
</div>

<?php if ($patient): ?>

<!-- ============================================
     TWO COLUMNS: PERSONAL + CONTACT
============================================ -->
<div class="row g-3 mb-3" style="display:grid; grid-template-columns:1fr 1fr; gap:24px;">
    <!-- Personal Info -->
    <div class="table-card" style="margin-bottom:20px;">
        <h5><i class="bi bi-person-vcard"></i> Personal Information</h5>

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px;">
            <div>
                <div style="color: var(--text-muted); font-size:0.72rem; text-transform:uppercase; letter-spacing:0.5px; font-weight:700; margin-bottom:3px;">Date of Birth</div>
                <div style="font-weight:600;"><?= !empty($patient['dob']) ? date('M d, Y', strtotime($patient['dob'])) : '-' ?></div>
            </div>
            <div>
                <div style="color: var(--text-muted); font-size:0.72rem; text-transform:uppercase; letter-spacing:0.5px; font-weight:700; margin-bottom:3px;">Gender</div>
                <div style="font-weight:600; text-transform:capitalize;"><?= htmlspecialchars($patient['gender'] ?? '-') ?></div>
            </div>
            <div style="grid-column: 1 / -1;">
                <div style="color: var(--text-muted); font-size:0.72rem; text-transform:uppercase; letter-spacing:0.5px; font-weight:700; margin-bottom:3px;">National ID</div>
                <div style="font-weight:600;"><?= htmlspecialchars($patient['national_id'] ?? '-') ?></div>
            </div>
        </div>
    </div>

    <!-- Contact -->
    <div class="table-card" style="margin-bottom:20px;">
        <h5><i class="bi bi-telephone-fill"></i> Contact Details</h5>

        <div style="display:grid; grid-template-columns:1fr; gap:14px;">
            <div>
                <div style="color: var(--text-muted); font-size:0.72rem; text-transform:uppercase; letter-spacing:0.5px; font-weight:700; margin-bottom:3px;">Email</div>
                <div style="font-weight:600; word-break:break-word;"><?= htmlspecialchars($patient['email'] ?? '-') ?></div>
            </div>
            <div>
                <div style="color: var(--text-muted); font-size:0.72rem; text-transform:uppercase; letter-spacing:0.5px; font-weight:700; margin-bottom:3px;">Phone</div>
                <div style="font-weight:600;"><?= htmlspecialchars($patient['phone'] ?? '-') ?></div>
            </div>
            <div>
                <div style="color: var(--text-muted); font-size:0.72rem; text-transform:uppercase; letter-spacing:0.5px; font-weight:700; margin-bottom:3px;">Address</div>
                <div style="font-weight:600;"><?= htmlspecialchars($patient['address'] ?? '-') ?></div>
            </div>
        </div>
    </div>
</div>

<!-- ============================================
     TWO COLUMNS: EMERGENCY + ACTIVITY
============================================ -->
<div class="row g-3 mb-3" style="display:grid; grid-template-columns:1fr 1fr; gap:24px;">
    <!-- Emergency -->
    <div class="table-card" style="margin-bottom:20px;">
        <h5><i class="bi bi-heart-pulse-fill"></i> Emergency Contact</h5>

        <?php if (!empty($patient['emergency_contact_name']) || !empty($patient['emergency_contact_phone'])): ?>
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px;">
            <div>
                <div style="color: var(--text-muted); font-size:0.72rem; text-transform:uppercase; letter-spacing:0.5px; font-weight:700; margin-bottom:3px;">Contact Name</div>
                <div style="font-weight:600;"><?= htmlspecialchars($patient['emergency_contact_name'] ?? '-') ?></div>
            </div>
            <div>
                <div style="color: var(--text-muted); font-size:0.72rem; text-transform:uppercase; letter-spacing:0.5px; font-weight:700; margin-bottom:3px;">Contact Phone</div>
                <div style="font-weight:600;"><?= htmlspecialchars($patient['emergency_contact_phone'] ?? '-') ?></div>
            </div>
        </div>
        <?php else: ?>
        <p style="color: var(--text-muted); text-align:center; padding:20px 0;">
            <i class="bi bi-telephone-x" style="font-size:1.8rem; display:block; margin-bottom:8px;"></i>
            No emergency contact on file.
            <br><a href="edit_patient_profile.php" style="color: var(--primary); font-weight:600;">Add one now →</a>
        </p>
        <?php endif; ?>
    </div>

    <!-- Activity -->
    <div class="table-card" style="margin-bottom:20px;">
        <h5><i class="bi bi-activity"></i> Activity Summary</h5>

        <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:12px; text-align:center;">
            <div style="padding:12px; background: rgba(14, 165, 233, 0.08); border-radius:10px;">
                <div style="font-weight:800; font-size:1.5rem; color: var(--primary);"><?= $total_appointments ?></div>
                <div style="color: var(--text-muted); font-size:0.72rem; text-transform:uppercase; letter-spacing:0.5px; font-weight:700; margin-top:4px;">Appointments</div>
            </div>
            <div style="padding:12px; background: rgba(14, 165, 233, 0.08); border-radius:10px;">
                <div style="font-weight:800; font-size:1.5rem; color: var(--primary);"><?= $total_invoices ?></div>
                <div style="color: var(--text-muted); font-size:0.72rem; text-transform:uppercase; letter-spacing:0.5px; font-weight:700; margin-top:4px;">Invoices</div>
            </div>
            <div style="padding:12px; background: rgba(14, 165, 233, 0.08); border-radius:10px;">
                <div style="font-weight:800; font-size:1.5rem; color: var(--primary);"><?= $total_prescriptions ?></div>
                <div style="color: var(--text-muted); font-size:0.72rem; text-transform:uppercase; letter-spacing:0.5px; font-weight:700; margin-top:4px;">Prescriptions</div>
            </div>
        </div>

        <div style="margin-top:16px; padding:12px 14px; background:#f8fafc; border-radius:10px; text-align:center; font-size:0.9rem;">
            <i class="bi bi-calendar-check" style="color: var(--primary);"></i>
            Member since <strong><?= !empty($patient['created_at']) ? date('F Y', strtotime($patient['created_at'])) : '-' ?></strong>
        </div>
    </div>
</div>

<!-- Responsive tweak -->
<style>
@media (max-width: 768px) {
    .row.g-3.mb-3 { grid-template-columns: 1fr !important; }
}
</style>

<?php else: ?>
    <div class="table-card">
        <p style="color: var(--text-muted); text-align: center; padding: 40px;">
            <i class="bi bi-person-x" style="font-size: 2.5rem; display: block; margin-bottom: 10px;"></i>
            No profile information found.
        </p>
    </div>
<?php endif; ?>

<?php include 'patient_footer.php'; ?>
