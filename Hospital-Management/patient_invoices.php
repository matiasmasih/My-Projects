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
// MY INVOICES
// ============================================
$invoices = [];
try {
    $stmt = $pdo->prepare("
        SELECT * FROM invoices
        WHERE patient_id = ?
        ORDER BY issued_at DESC
    ");
    $stmt->execute([$patient_id]);
    $invoices = $stmt->fetchAll();
} catch (Exception $e) {}

// ============================================
// STATS
// ============================================
$total_invoices = count($invoices);
$total_amount = 0;
$total_paid = 0;
$total_unpaid = 0;

foreach ($invoices as $inv) {
    $amount = floatval($inv['total_amount'] ?? 0);
    $total_amount += $amount;

    if ($inv['status'] === 'paid') {
        $total_paid += $amount;
    } else {
        $total_unpaid += $amount;
    }
}

// Set page title
$page_title = 'My Invoices';

// Include shared header
include 'patient_header.php';
?>

<!-- Welcome Banner -->
<div class="welcome-banner">
    <h2>My Invoices 🧾</h2>
    <p>View all your invoices and payment history</p>
    <div class="clock">
        <div class="time" id="clock">--:--:--</div>
        <div class="date" id="date">Loading...</div>
    </div>
</div>

<!-- Stats -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon icon-invoices"><i class="bi bi-receipt"></i></div>
        <div class="stat-info">
            <h3 class="counter" data-target="<?php echo $total_invoices; ?>">0</h3>
            <p>Total Invoices</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon icon-prescriptions"><i class="bi bi-cash-stack"></i></div>
        <div class="stat-info">
            <h3>€<?php echo number_format($total_amount, 2); ?></h3>
            <p>Total Amount</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon icon-records"><i class="bi bi-check-circle-fill"></i></div>
        <div class="stat-info">
            <h3>€<?php echo number_format($total_paid, 2); ?></h3>
            <p>Paid</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon icon-messages"><i class="bi bi-exclamation-circle-fill"></i></div>
        <div class="stat-info">
            <h3>€<?php echo number_format($total_unpaid, 2); ?></h3>
            <p>Unpaid</p>
        </div>
    </div>
</div>

<!-- Invoices Table -->
<div class="table-card">
    <h5><i class="bi bi-receipt"></i> All Invoices</h5>
    <?php if (count($invoices) > 0): ?>
    <div style="overflow-x: auto;">
        <table>
            <thead>
                <tr>
                    <th>Invoice #</th>
                    <th>Date</th>
                    <th>Due Date</th>
                    <th>Amount</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($invoices as $inv): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($inv['invoice_number'] ?? '-'); ?></strong></td>
                    <td><?php echo $inv['issued_at'] ? date('M d, Y', strtotime($inv['issued_at'])) : '-'; ?></td>
                    <td><?php echo $inv['due_date'] ? date('M d, Y', strtotime($inv['due_date'])) : '-'; ?></td>
                    <td><strong>€<?php echo number_format($inv['total_amount'] ?? 0, 2); ?></strong></td>
                    <td>
                        <span class="status-badge status-<?php echo $inv['status']; ?>">
                            <?php echo str_replace('_', ' ', $inv['status']); ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
        <p style="color: var(--text-muted); text-align: center; padding: 30px;">
            <i class="bi bi-receipt" style="font-size: 2rem; display: block; margin-bottom: 10px;"></i>
            No invoices yet
        </p>
    <?php endif; ?>
</div>

<?php include 'patient_footer.php'; ?>
