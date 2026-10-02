<?php
session_start();
include 'config.php';
include_once 'access_control.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$access = require_access('medical_records', 'login.php');
$role   = current_role();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header("Location: medical_records.php");
    exit;
}

// Fetch record
try {
    $stmt = $pdo->prepare("
        SELECT mr.*,
               p.first_name AS patient_first, p.last_name AS patient_last,
               p.medical_record_number AS patient_mrn,
               p.dob AS patient_dob, p.gender AS patient_gender,
               p.email AS patient_email, p.phone AS patient_phone,
               doc_user.first_name AS doctor_first, doc_user.last_name AS doctor_last
        FROM medical_records mr
        LEFT JOIN patients p ON mr.patient_id = p.id
        LEFT JOIN doctors d ON mr.doctor_id = d.id
        LEFT JOIN users doc_user ON d.user_id = doc_user.id
        WHERE mr.id = ?
    ");
    $stmt->execute([$id]);
    $record = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$record) {
        header("Location: medical_records.php");
        exit;
    }
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

// Related prescriptions
$prescriptions = [];
try {
    $stmt = $pdo->prepare("
        SELECT pr.id, pr.prescribed_at, pr.notes,
               (SELECT COUNT(*) FROM prescription_items WHERE prescription_id = pr.id) AS item_count
        FROM prescriptions pr
        WHERE pr.medical_record_id = ?
        ORDER BY pr.prescribed_at DESC
    ");
    $stmt->execute([$id]);
    $prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

$page_title = 'Medical Record';
include 'doctor_header.php';
?>

<!-- Action bar -->
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <a href="medical_records.php" class="btn btn-secondary-custom">
        <i class="bi bi-arrow-left"></i> Back to Records
    </a>
    <span class="badge" style="background: var(--primary); font-size: 0.85rem; padding: 8px 14px;">
        Record #<?= (int)$record['id'] ?>
    </span>
</div>

<!-- Header info -->
<div class="table-card">
    <h5><i class="bi bi-file-medical"></i> Visit Details</h5>

    <div class="row g-3">
        <div class="col-md-6">
            <div style="color: var(--text-muted); font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px;">Patient</div>
            <div style="font-weight: 700; font-size: 1.05rem;">
                <?= htmlspecialchars(($record['patient_first'] ?? '') . ' ' . ($record['patient_last'] ?? '')) ?>
            </div>
            <?php if (!empty($record['patient_mrn'])): ?>
                <div style="color: var(--text-muted); font-size: 0.85rem; margin-top: 2px;">
                    MRN: <?= htmlspecialchars($record['patient_mrn']) ?>
                    <?php if (!empty($record['patient_dob'])): ?>
                        · DOB: <?= date('M j, Y', strtotime($record['patient_dob'])) ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="col-md-6">
            <div style="color: var(--text-muted); font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px;">Doctor</div>
            <div style="font-weight: 700; font-size: 1.05rem;">
                <?= !empty($record['doctor_first']) ? 'Dr. ' . htmlspecialchars($record['doctor_first'] . ' ' . $record['doctor_last']) : '-' ?>
            </div>
        </div>

        <div class="col-md-4">
            <div style="color: var(--text-muted); font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px;">Visit Date</div>
            <div style="font-weight: 600;">
                <?= !empty($record['visit_date']) ? date('M j, Y g:i A', strtotime($record['visit_date'])) : '-' ?>
            </div>
        </div>

        <div class="col-md-4">
            <div style="color: var(--text-muted); font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px;">Visit Type</div>
            <div style="font-weight: 600;"><?= htmlspecialchars($record['visit_type'] ?? '-') ?></div>
        </div>
    </div>
</div>

<!-- Summary / Diagnosis / Treatment -->
<div class="table-card">
    <h5><i class="bi bi-clipboard2-pulse"></i> Clinical Notes</h5>

    <div class="row g-3">
        <div class="col-md-12">
            <div style="color: var(--text-muted); font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px;">Summary</div>
            <div style="background: #f8fafc; padding: 12px 16px; border-radius: 8px; line-height: 1.6;">
                <?= !empty($record['summary']) ? nl2br(htmlspecialchars($record['summary'])) : '<span style="color:var(--text-muted);">No summary recorded.</span>' ?>
            </div>
        </div>

        <div class="col-md-12">
            <div style="color: var(--text-muted); font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px;">Diagnosis</div>
            <div style="background: #fef3c7; padding: 12px 16px; border-radius: 8px; line-height: 1.6;">
                <?= !empty($record['diagnosis']) ? nl2br(htmlspecialchars($record['diagnosis'])) : '<span style="color:var(--text-muted);">No diagnosis recorded.</span>' ?>
            </div>
        </div>

        <div class="col-md-12">
            <div style="color: var(--text-muted); font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px;">Treatment</div>
            <div style="background: #ecfdf5; padding: 12px 16px; border-radius: 8px; line-height: 1.6;">
                <?= !empty($record['treatment']) ? nl2br(htmlspecialchars($record['treatment'])) : '<span style="color:var(--text-muted);">No treatment recorded.</span>' ?>
            </div>
        </div>

        <?php if (!empty($record['notes'])): ?>
        <div class="col-md-12">
            <div style="color: var(--text-muted); font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px;">Additional Notes</div>
            <div style="background: #f8fafc; padding: 12px 16px; border-radius: 8px; line-height: 1.6;">
                <?= nl2br(htmlspecialchars($record['notes'])) ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Related Prescriptions -->
<div class="table-card">
    <h5><i class="bi bi-capsule"></i> Related Prescriptions (<?= count($prescriptions) ?>)</h5>

    <?php if (count($prescriptions) > 0): ?>
    <div style="overflow-x: auto;">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Date</th>
                    <th>Items</th>
                    <th>Notes</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($prescriptions as $i => $p): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td><?= !empty($p['prescribed_at']) ? date('M j, Y g:i A', strtotime($p['prescribed_at'])) : '-' ?></td>
                    <td><span class="status-badge"><?= (int)$p['item_count'] ?> item<?= (int)$p['item_count'] !== 1 ? 's' : '' ?></span></td>
                    <td style="white-space: normal; max-width: 320px;">
                        <?= !empty($p['notes']) ? htmlspecialchars($p['notes']) : '-' ?>
                    </td>
                    <td>
                        <a href="view_prescription.php?id=<?= (int)$p['id'] ?>" class="btn-edit" title="View"><i class="bi bi-eye"></i></a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
        <p style="color: var(--text-muted); text-align: center; padding: 24px;">
            No prescriptions linked to this record.
        </p>
    <?php endif; ?>
</div>

<?php include 'doctor_footer.php'; ?>
