<?php
session_start();
include 'config.php';
include_once 'access_control.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$access = require_access('prescriptions', 'login.php');
if ($access !== 'full') {
    header("Location: prescriptions.php");
    exit;
}

$role = current_role();
$my_id = (int)$_SESSION['user_id'];

$my_doctor_id = 0;
if ($role === 'doctor') {
    try {
        $stmt = $pdo->prepare("SELECT id FROM doctors WHERE user_id = ?");
        $stmt->execute([$my_id]);
        $my_doctor_id = (int)($stmt->fetchColumn() ?: 0);
    } catch (PDOException $e) {}
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $record_id = (int)($_POST['medical_record_id'] ?? 0);
    $notes     = trim($_POST['notes'] ?? '');

    $medicines = $_POST['medicine_id'] ?? [];
    $dosages   = $_POST['dosage'] ?? [];
    $freqs     = $_POST['frequency'] ?? [];
    $durations = $_POST['duration'] ?? [];
    $qtys      = $_POST['quantity'] ?? [];
    $instrs    = $_POST['instructions'] ?? [];

    if ($record_id <= 0) {
        $error = "Please select a medical record.";
    } elseif ($my_doctor_id <= 0 && $role === 'doctor') {
        $error = "Your doctor profile is not linked.";
    } else {
        // At least one item with medicine selected
        $has_item = false;
        foreach ($medicines as $mid) {
            if ((int)$mid > 0) { $has_item = true; break; }
        }
        if (!$has_item) {
            $error = "Please add at least one medication.";
        } else {
            try {
                $pdo->beginTransaction();

                $prescribed_by = $my_doctor_id > 0 ? $my_doctor_id : null;

                $stmt = $pdo->prepare("
                    INSERT INTO prescriptions (medical_record_id, prescribed_by, prescribed_at, notes)
                    VALUES (?, ?, NOW(), ?)
                ");
                $stmt->execute([$record_id, $prescribed_by, $notes]);
                $prescription_id = (int)$pdo->lastInsertId();

                $stmtItem = $pdo->prepare("
                    INSERT INTO prescription_items
                        (prescription_id, medicine_id, dosage, frequency, duration, quantity, instructions, dispensed)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 0)
                ");

                foreach ($medicines as $i => $mid) {
                    $mid = (int)$mid;
                    if ($mid <= 0) continue;

                    $stmtItem->execute([
                        $prescription_id,
                        $mid,
                        trim($dosages[$i] ?? ''),
                        trim($freqs[$i] ?? ''),
                        trim($durations[$i] ?? ''),
                        (int)($qtys[$i] ?? 1),
                        trim($instrs[$i] ?? '')
                    ]);
                }

                $pdo->commit();

                $_SESSION['flash_success'] = "Prescription created.";
                header("Location: prescriptions.php");
                exit;
            } catch (PDOException $e) {
                $pdo->rollBack();
                $error = "Database error: " . $e->getMessage();
            }
        }
    }
}

// Dropdowns
$records = [];
$medicines_list = [];

try {
    $sql = "
        SELECT mr.id, mr.visit_date, mr.visit_type, mr.diagnosis,
               p.first_name, p.last_name, p.medical_record_number
        FROM medical_records mr
        LEFT JOIN patients p ON mr.patient_id = p.id
    ";
    $params = [];
    if ($role === 'doctor' && $my_doctor_id > 0) {
        $sql .= " WHERE mr.doctor_id = ? ";
        $params[] = $my_doctor_id;
    }
    $sql .= " ORDER BY mr.visit_date DESC LIMIT 100";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $records = $stmt->fetchAll();
} catch (PDOException $e) {}

try {
    $medicines_list = $pdo->query("SELECT id, name, form, strength FROM medicines ORDER BY name")->fetchAll();
} catch (PDOException $e) {}

$page_title = 'New Prescription';
include 'doctor_header.php';
?>

<?php if ($error): ?>
    <div class="alert alert-danger alert-soft"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <a href="prescriptions.php" class="btn btn-secondary-custom">
        <i class="bi bi-arrow-left"></i> Back to Prescriptions
    </a>
</div>

<div class="table-card">
    <h5><i class="bi bi-capsule"></i> New Prescription</h5>

    <form method="POST" id="prescForm">
        <div class="row g-3 mb-3">
            <div class="col-md-8">
                <label class="form-label">Medical Record <span style="color:#ef4444;">*</span></label>
                <select name="medical_record_id" class="form-select" required>
                    <option value="">Select Medical Record</option>
                    <?php foreach ($records as $r): ?>
                        <option value="<?= (int)$r['id'] ?>">
                            #<?= (int)$r['id'] ?>
                            — <?= htmlspecialchars(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? '')) ?>
                            (<?= htmlspecialchars($r['medical_record_number'] ?? '-') ?>)
                            · <?= !empty($r['visit_date']) ? date('M j, Y', strtotime($r['visit_date'])) : '-' ?>
                            · <?= htmlspecialchars($r['visit_type'] ?? '') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <small style="color: var(--text-muted); font-size:0.82rem;">
                    Prescription must be linked to the visit record it was issued for.
                </small>
            </div>

            <div class="col-md-4">
                <label class="form-label">Date</label>
                <input type="text" class="form-control" value="<?= date('M j, Y g:i A') ?>" readonly>
            </div>

            <div class="col-md-12">
                <label class="form-label">Prescription Notes</label>
                <textarea name="notes" class="form-control" rows="2" placeholder="General notes for this prescription..."></textarea>
            </div>
        </div>

        <h6 style="font-weight:700; color: var(--text); margin-bottom:12px;">
            <i class="bi bi-list-ul" style="color: var(--primary);"></i> Medications
        </h6>

        <div id="itemsContainer">
            <!-- item row template injected by JS -->
        </div>

        <button type="button" class="btn btn-add mt-2" onclick="addItem()">
            <i class="bi bi-plus-lg"></i> Add Medication
        </button>

        <div class="d-flex justify-content-between mt-4">
            <button type="submit" class="btn btn-add"><i class="bi bi-save"></i> Save Prescription</button>
            <a href="prescriptions.php" class="btn btn-secondary-custom"><i class="bi bi-x-lg"></i> Cancel</a>
        </div>
    </form>
</div>

<!-- Hidden template -->
<template id="itemTemplate">
    <div class="item-row" style="background:#f8fafc; border-left:4px solid var(--primary); padding:14px 16px; border-radius:8px; margin-bottom:10px;">
        <div class="row g-2">
            <div class="col-md-4">
                <label class="form-label">Medicine <span style="color:#ef4444;">*</span></label>
                <select name="medicine_id[]" class="form-select" required>
                    <option value="">Select Medicine</option>
                    <?php foreach ($medicines_list as $m): ?>
                        <option value="<?= (int)$m['id'] ?>">
                            <?= htmlspecialchars($m['name']) ?>
                            <?= !empty($m['strength']) ? ' — ' . htmlspecialchars($m['strength']) : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Dosage</label>
                <input type="text" name="dosage[]" class="form-control" placeholder="e.g. 500mg">
            </div>
            <div class="col-md-2">
                <label class="form-label">Frequency</label>
                <input type="text" name="frequency[]" class="form-control" placeholder="e.g. 2x daily">
            </div>
            <div class="col-md-2">
                <label class="form-label">Duration</label>
                <input type="text" name="duration[]" class="form-control" placeholder="e.g. 7 days">
            </div>
            <div class="col-md-1">
                <label class="form-label">Qty</label>
                <input type="number" name="quantity[]" class="form-control" value="1" min="1">
            </div>
            <div class="col-md-1 d-flex align-items-end">
                <button type="button" class="btn btn-delete" onclick="this.closest('.item-row').remove()" title="Remove">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
            <div class="col-md-12">
                <label class="form-label">Instructions</label>
                <input type="text" name="instructions[]" class="form-control" placeholder="e.g. Take after meals with water">
            </div>
        </div>
    </div>
</template>

<script>
function addItem() {
    const tpl = document.getElementById('itemTemplate');
    const clone = tpl.content.cloneNode(true);
    document.getElementById('itemsContainer').appendChild(clone);
}

// Add one item row by default
document.addEventListener('DOMContentLoaded', addItem);
</script>

<?php include 'doctor_footer.php'; ?>
