<?php
session_start();
include 'config.php';
include_once 'access_control.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$access = require_access('prescriptions', 'login.php');
$role   = current_role();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header("Location: prescriptions.php");
    exit;
}

// Fetch prescription
try {
    $stmt = $pdo->prepare("
        SELECT pr.*,
               mr.patient_id AS patient_id,
               mr.visit_date, mr.diagnosis,
               p.first_name AS patient_first, p.last_name AS patient_last,
               p.medical_record_number AS patient_mrn,
               p.dob AS patient_dob, p.gender AS patient_gender,
               p.email AS patient_email, p.phone AS patient_phone,
               doc_user.first_name AS doctor_first, doc_user.last_name AS doctor_last
        FROM prescriptions pr
        LEFT JOIN medical_records mr ON pr.medical_record_id = mr.id
        LEFT JOIN patients p ON mr.patient_id = p.id
        LEFT JOIN doctors d ON pr.prescribed_by = d.id
        LEFT JOIN users doc_user ON d.user_id = doc_user.id
        WHERE pr.id = ?
    ");
    $stmt->execute([$id]);
    $prescription = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$prescription) {
        header("Location: prescriptions.php");
        exit;
    }
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

// Fetch items
$items = [];
try {
    $stmt = $pdo->prepare("
        SELECT pi.*,
               m.name AS medicine_name, m.generic_name, m.brand,
               m.form, m.strength, m.unit
        FROM prescription_items pi
        LEFT JOIN medicines m ON pi.medicine_id = m.id
        WHERE pi.prescription_id = ?
        ORDER BY pi.id
    ");
    $stmt->execute([$id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

$page_title = 'Prescription';
include 'doctor_header.php';
?>

<!-- Action bar -->
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <a href="prescriptions.php" class="btn btn-secondary-custom">
        <i class="bi bi-arrow-left"></i> Back to Prescriptions
    </a>
    <span class="badge" style="background: var(--primary); font-size: 0.85rem; padding: 8px 14px;">
        Prescription #<?= (int)$prescription['id'] ?>
    </span>
</div>

<!-- Header info -->
<div class="table-card">
    <h5><i class="bi bi-capsule"></i> Prescription Details</h5>

    <div class="row g-3">
        <div class="col-md-6">
            <div style="color: var(--text-muted); font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px;">Patient</div>
            <div style="font-weight: 700; font-size: 1.05rem;">
                <?= htmlspecialchars(($prescription['patient_first'] ?? '') . ' ' . ($prescription['patient_last'] ?? '')) ?>
            </div>
            <?php if (!empty($prescription['patient_mrn'])): ?>
                <div style="color: var(--text-muted); font-size: 0.85rem; margin-top: 2px;">
                    MRN: <?= htmlspecialchars($prescription['patient_mrn']) ?>
                    <?php if (!empty($prescription['patient_dob'])): ?>
                        · DOB: <?= date('M j, Y', strtotime($prescription['patient_dob'])) ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="col-md-6">
            <div style="color: var(--text-muted); font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px;">Prescribed By</div>
            <div style="font-weight: 700; font-size: 1.05rem;">
                <?= !empty($prescription['doctor_first']) ? 'Dr. ' . htmlspecialchars($prescription['doctor_first'] . ' ' . $prescription['doctor_last']) : '-' ?>
            </div>
        </div>

        <div class="col-md-4">
            <div style="color: var(--text-muted); font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px;">Prescribed At</div>
            <div style="font-weight: 600;">
                <?= !empty($prescription['prescribed_at']) ? date('M j, Y g:i A', strtotime($prescription['prescribed_at'])) : '-' ?>
            </div>
        </div>

        <div class="col-md-8">
            <div style="color: var(--text-muted); font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px;">Related Diagnosis</div>
            <div style="font-weight: 600;">
                <?= !empty($prescription['diagnosis']) ? htmlspecialchars($prescription['diagnosis']) : '-' ?>
            </div>
        </div>
    </div>

    <?php if (!empty($prescription['notes'])): ?>
    <div class="mt-3">
        <div style="color: var(--text-muted); font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px;">Notes</div>
        <div style="background: #f8fafc; padding: 12px 16px; border-radius: 8px; line-height: 1.6;">
            <?= nl2br(htmlspecialchars($prescription['notes'])) ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Items -->
<div class="table-card">
    <h5><i class="bi bi-list-ul"></i> Medications (<?= count($items) ?>)</h5>

    <?php if (count($items) > 0): ?>
    <div style="overflow-x: auto;">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Medicine</th>
                    <th>Dosage</th>
                    <th>Frequency</th>
                    <th>Duration</th>
                    <th>Qty</th>
                    <th>Instructions</th>
                    <th>Dispensed</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $i => $it): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td>
                        <strong><?= htmlspecialchars($it['medicine_name'] ?? '-') ?></strong>
                        <?php if (!empty($it['generic_name'])): ?>
                            <br><span style="color: var(--text-muted); font-size: 0.8rem;"><?= htmlspecialchars($it['generic_name']) ?></span>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($it['dosage'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($it['frequency'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($it['duration'] ?? '-') ?></td>
                    <td><strong><?= (int)($it['quantity'] ?? 0) ?></strong></td>
                    <td style="white-space: normal; max-width: 260px;">
                        <?= !empty($it['instructions']) ? htmlspecialchars($it['instructions']) : '-' ?>
                    </td>
                    <td>
                        <?php if (!empty($it['dispensed'])): ?>
                            <span class="status-badge status-completed">Yes</span>
                        <?php else: ?>
                            <span class="status-badge status-scheduled">No</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
        <p style="color: var(--text-muted); text-align: center; padding: 24px;">
            No items in this prescription.
        </p>
    <?php endif; ?>
</div>

<?php include 'doctor_footer.php'; ?>
