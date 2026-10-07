<?php
session_start();
include 'config.php';
include_once 'access_control.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$access = require_access('admissions', 'login.php');
if ($access !== 'full') {
    header("Location: admissions.php");
    exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header("Location: admissions.php");
    exit;
}

// Fetch admission + joined info
try {
    $stmt = $pdo->prepare("
        SELECT a.*,
               p.first_name AS patient_first_name,
               p.last_name AS patient_last_name,
               p.medical_record_number,
               doc_user.first_name AS doctor_first_name,
               doc_user.last_name AS doctor_last_name,
               w.name AS ward_name,
               r.room_number
        FROM admissions a
        LEFT JOIN patients p ON a.patient_id = p.id
        LEFT JOIN doctors d ON a.admitting_doctor_id = d.id
        LEFT JOIN users doc_user ON d.user_id = doc_user.id
        LEFT JOIN wards w ON a.ward_id = w.id
        LEFT JOIN rooms r ON a.room_id = r.id
        WHERE a.id = ?
    ");
    $stmt->execute([$id]);
    $admission = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$admission) {
        header("Location: admissions.php");
        exit;
    }
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $summary = trim($_POST['discharge_summary'] ?? '');

    if ($summary === '') {
        $error = "Please provide a discharge summary.";
    } else {
        try {
            $stmt = $pdo->prepare("
                UPDATE admissions SET
                    status = 'discharged',
                    discharged_at = NOW(),
                    discharge_summary = ?
                WHERE id = ?
            ");
            $stmt->execute([$summary, $id]);

            $_SESSION['flash_success'] = "Patient discharged successfully.";
            header("Location: admissions.php");
            exit;
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}

$page_title = 'Discharge Patient';
include 'admissions_header.php';
?>

<?php if ($error): ?>
    <div class="alert alert-danger alert-soft"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="table-card">
    <h5><i class="bi bi-box-arrow-right"></i> Discharge — <?= htmlspecialchars(($admission['patient_first_name'] ?? '') . ' ' . ($admission['patient_last_name'] ?? '')) ?></h5>

    <!-- CURRENT ADMISSION INFO -->
    <div style="background:#f0fdfa; border-left:4px solid var(--primary); padding:16px 20px; border-radius:8px; margin-bottom:22px;">
        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(180px,1fr)); gap:14px;">
            <div>
                <div style="color: var(--text-muted); font-size:0.75rem; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:3px;">Patient</div>
                <div style="font-weight:700;"><?= htmlspecialchars(($admission['patient_first_name'] ?? '') . ' ' . ($admission['patient_last_name'] ?? '')) ?></div>
                <?php if (!empty($admission['medical_record_number'])): ?>
                    <div style="color: var(--text-muted); font-size:0.82rem;"><?= htmlspecialchars($admission['medical_record_number']) ?></div>
                <?php endif; ?>
            </div>
            <div>
                <div style="color: var(--text-muted); font-size:0.75rem; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:3px;">Admitting Doctor</div>
                <div style="font-weight:700;">
                    <?= !empty($admission['doctor_first_name']) ? 'Dr. ' . htmlspecialchars($admission['doctor_first_name'] . ' ' . $admission['doctor_last_name']) : '-' ?>
                </div>
            </div>
            <div>
                <div style="color: var(--text-muted); font-size:0.75rem; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:3px;">Ward / Room</div>
                <div style="font-weight:700;"><?= htmlspecialchars($admission['ward_name'] ?? '-') ?></div>
                <?php if (!empty($admission['room_number'])): ?>
                    <div style="color: var(--text-muted); font-size:0.82rem;">Room <?= htmlspecialchars($admission['room_number']) ?></div>
                <?php endif; ?>
            </div>
            <div>
                <div style="color: var(--text-muted); font-size:0.75rem; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:3px;">Admitted</div>
                <div style="font-weight:700;"><?= !empty($admission['admitted_at']) ? date('M j, Y', strtotime($admission['admitted_at'])) : '-' ?></div>
                <div style="color: var(--text-muted); font-size:0.82rem;"><?= !empty($admission['admitted_at']) ? date('g:i A', strtotime($admission['admitted_at'])) : '' ?></div>
            </div>
        </div>
    </div>

    <?php if (($admission['status'] ?? '') === 'discharged'): ?>
        <div class="alert alert-success alert-soft">
            <i class="bi bi-check-circle-fill"></i>
            Already discharged on <?= !empty($admission['discharged_at']) ? date('M j, Y g:i A', strtotime($admission['discharged_at'])) : '—' ?>.
        </div>

        <?php if (!empty($admission['discharge_summary'])): ?>
        <div style="background:#f8fafc; border-left:4px solid #14b8a6; padding:14px 18px; border-radius:8px; margin-bottom:20px;">
            <div style="color: var(--text-muted); font-size:0.75rem; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:6px;">Discharge Summary</div>
            <div><?= nl2br(htmlspecialchars($admission['discharge_summary'])) ?></div>
        </div>
        <?php endif; ?>

        <a href="admissions.php" class="btn btn-secondary-custom"><i class="bi bi-arrow-left"></i> Back to Admissions</a>

    <?php else: ?>
        <form method="POST">
            <div class="row g-3">
                <div class="col-md-12">
                    <label class="form-label">Discharge Summary <span style="color:#ef4444;">*</span></label>
                    <textarea name="discharge_summary" class="form-control" rows="6" required
                              placeholder="Describe the patient's condition, treatment given, follow-up instructions, medications..."></textarea>
                    <small style="color: var(--text-muted); font-size:0.82rem;">
                        This will set the admission status to <strong>Discharged</strong> and record the current timestamp.
                    </small>
                </div>
            </div>

            <div class="d-flex justify-content-between mt-4">
                <button type="submit" class="btn btn-discharge"
                        onclick="return confirm('Discharge this patient now?');">
                    <i class="bi bi-box-arrow-right"></i> Discharge Patient
                </button>
                <a href="admissions.php" class="btn btn-secondary-custom"><i class="bi bi-arrow-left"></i> Cancel</a>
            </div>
        </form>
    <?php endif; ?>
</div>

<?php include 'admissions_footer.php'; ?>
