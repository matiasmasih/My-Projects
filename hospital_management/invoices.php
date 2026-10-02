<?php
session_start();
include 'config.php';
include_once 'access_control.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$access = require_access('invoices', 'login.php');
// full | view | own | no

$role = current_role();
$my_email = $_SESSION['email'] ?? '';

// For patient: find their patient_id
$my_patient_id = 0;
if ($role === 'patient') {
    try {
        $stmt = $pdo->prepare("SELECT id FROM patients WHERE email = ?");
        $stmt->execute([$my_email]);
        $my_patient_id = (int)($stmt->fetchColumn() ?: 0);
    } catch (Exception $e) {}
}

// Flash messages (PRG)
$error   = $_SESSION['flash_error']   ?? '';
$success = $_SESSION['flash_success'] ?? '';
unset($_SESSION['flash_error'], $_SESSION['flash_success']);

// ============================================
// HANDLE DELETE INVOICE (POST, full access)
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_invoice']) && $access === 'full') {
    $del_id = (int)($_POST['invoice_id'] ?? 0);
    if ($del_id > 0) {
        try {
            $stmt = $pdo->prepare("DELETE FROM invoices WHERE id = ?");
            $stmt->execute([$del_id]);
            $_SESSION['flash_success'] = "Invoice deleted successfully.";
        } catch (PDOException $e) {
            $_SESSION['flash_error'] = "Delete failed: " . $e->getMessage();
        }
    } else {
        $_SESSION['flash_error'] = "Invalid invoice ID.";
    }
    header("Location: invoices.php");
    exit;
}

// ============================================
// FETCH INVOICES (role-aware)
// ============================================
try {
    $sql = "
        SELECT
            inv.id,
            inv.invoice_number,
            inv.total_amount,
            inv.status,
            inv.issued_at,
            inv.due_date,
            inv.notes,
            CONCAT(p.first_name, ' ', p.last_name) AS patient_name,
            p.medical_record_number AS patient_mrn,
            CONCAT(u.first_name, ' ', u.last_name) AS issued_by
        FROM invoices inv
        LEFT JOIN patients p ON inv.patient_id = p.id
        LEFT JOIN users u ON inv.issued_by = u.id
    ";

    $params = [];
    if ($role === 'patient') {
        if ($my_patient_id > 0) {
            $sql .= " WHERE inv.patient_id = ?";
            $params[] = $my_patient_id;
        } else {
            // Patient has no linked record → force empty result
            $sql .= " WHERE 1 = 0";
        }
    }

    $sql .= " ORDER BY inv.issued_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

$page_title = 'Invoices';
include 'invoices_header.php';
?>

<!-- Alerts -->
<?php if ($error): ?>
    <div class="alert alert-danger alert-soft"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>
<?php if ($success): ?>
    <div class="alert alert-success alert-soft"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<!-- Toolbar -->
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div class="d-flex align-items-center gap-2">
        <input type="text" class="form-control search-box" id="searchInput" placeholder="Search invoices...">
        <button class="btn btn-add" id="searchButton"><i class="bi bi-search"></i> Search</button>
    </div>
    <span class="badge" style="background: var(--primary); font-size: 0.85rem; padding: 8px 14px;">
        Total: <?= count($invoices); ?> invoices
    </span>
</div>

<!-- Invoices Table -->
<div class="table-card">
    <h5><i class="bi bi-receipt-cutoff"></i> <?= $role === 'patient' ? 'My Invoices' : 'All Invoices' ?> (<?= count($invoices); ?>)</h5>

    <?php if (count($invoices) > 0): ?>
    <div style="overflow-x: auto;">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Invoice #</th>
                    <th>Patient</th>
                    <th>Issued By</th>
                    <th>Total</th>
                    <th>Status</th>
                    <th>Issued At</th>
                    <th>Due Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="invoiceTable">
                <?php foreach ($invoices as $index => $inv): ?>
                <tr>
                    <td><?= $index + 1 ?></td>
                    <td><strong><?= htmlspecialchars($inv['invoice_number'] ?? '-') ?></strong></td>
                    <td>
                        <?php if (!empty($inv['patient_mrn'])): ?>
                            <span style="color: var(--text-muted); font-size: 0.8rem;"><?= htmlspecialchars($inv['patient_mrn']) ?></span><br>
                        <?php endif; ?>
                        <strong><?= htmlspecialchars($inv['patient_name'] ?? '-') ?></strong>
                    </td>
                    <td><?= htmlspecialchars($inv['issued_by'] ?? 'N/A') ?></td>
                    <td>€<?= number_format((float)($inv['total_amount'] ?? 0), 2) ?></td>
                    <td>
                        <span class="status-badge status-<?= htmlspecialchars($inv['status'] ?? 'issued') ?>">
                            <?= ucfirst(str_replace('_', ' ', htmlspecialchars($inv['status'] ?? '-'))) ?>
                        </span>
                    </td>
                    <td><?= $inv['issued_at'] ? date('M d, Y', strtotime($inv['issued_at'])) : '-' ?></td>
                    <td><?= $inv['due_date'] ? date('M d, Y', strtotime($inv['due_date'])) : '-' ?></td>
                    <td>
                        <a href="view_invoice.php?id=<?= $inv['id'] ?>" class="btn-view" title="View Invoice"><i class="bi bi-eye"></i></a>

                        <?php if ($access === 'full'): ?>
                            <a href="payments.php?invoice_id=<?= $inv['id'] ?>" class="btn-pay" title="Record Payment"><i class="bi bi-cash-stack"></i></a>

                            <form method="POST" action="invoices.php" style="display:inline;" onsubmit="return confirm('Delete this invoice?');">
                                <input type="hidden" name="invoice_id" value="<?= $inv['id'] ?>">
                                <input type="hidden" name="delete_invoice" value="1">
                                <button type="submit" class="btn-delete" title="Delete"><i class="bi bi-trash"></i></button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
        <p style="color: var(--text-muted); text-align: center; padding: 30px;">
            <i class="bi bi-receipt" style="font-size: 2rem; display: block; margin-bottom: 10px;"></i>
            No invoices found.
        </p>
    <?php endif; ?>
</div>

<!-- CLIENT-SIDE SEARCH -->
<script>
const searchInput = document.getElementById('searchInput');
const searchButton = document.getElementById('searchButton');
const invoiceTable = document.getElementById('invoiceTable');

function filterTable() {
    const filter = (searchInput?.value || '').toLowerCase();
    const rows = invoiceTable?.getElementsByTagName('tr') || [];
    Array.from(rows).forEach(row => {
        const cells = row.getElementsByTagName('td');
        if (cells.length < 5) return;
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(filter) ? '' : 'none';
    });
}
if (searchInput)  searchInput.addEventListener('keyup', filterTable);
if (searchButton) searchButton.addEventListener('click', filterTable);
</script>

<?php include 'invoices_footer.php'; ?>
