<?php
session_start();
include 'config.php';

// Only admin (1) or manager (2)
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role_id'], [1,2])) {
    header("Location: login.php");
    exit;
}

// Get invoice ID
$invoice_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($invoice_id <= 0) die("Invalid invoice ID.");

// Fetch invoice and patient info
try {
    $stmt = $pdo->prepare("
        SELECT i.*, p.first_name AS patient_first, p.last_name AS patient_last
        FROM invoices i
        LEFT JOIN patients p ON i.patient_id = p.id
        WHERE i.id = ?
    ");
    $stmt->execute([$invoice_id]);
    $invoice = $stmt->fetch();
    if (!$invoice) die("Invoice not found.");

    // Fetch invoice items if you have a table like invoice_items
    $items = $pdo->prepare("
        SELECT description, quantity, unit_price
        FROM invoice_items
        WHERE invoice_id = ?
    ");
    $items->execute([$invoice_id]);
    $invoice_items = $items->fetchAll();

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>View Invoice</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
<style>

/* ----------------------- General ----------------------- */
body {
    font-family: 'Montserrat', sans-serif;
    margin: 0;
    background: #f0f2f5;
}

/* ----------------------- Navbar ----------------------- */
.navbar {
    background: linear-gradient(135deg, #4b6cb7, #182848);
}
.navbar .navbar-brand,
.navbar .nav-link {
    color: #fff;
}
.navbar .nav-link:hover {
    color: #ffd700;
}

/* ----------------------- Sidebar ----------------------- */
.sidebar {
    width: 230px;
    min-height: 100vh;
    background-color: #1e1e2f;
    padding: 20px;
    color: #fff;
    display: flex;
    flex-direction: column;
}
.sidebar h4 {
    font-weight: 600;
    margin-bottom: 1rem;
    color: #ffd700;
}
.sidebar a {
    display: flex;
    align-items: center;
    color: #c1c1c1;
    text-decoration: none;
    padding: 12px 15px;
    border-radius: 8px;
    margin-bottom: 5px;
    transition: all 0.3s ease;
    white-space: nowrap;
}
.sidebar a:hover {
    background-color: #4b6cb7;
    color: #fff;
}
.sidebar a.active {
    background-color: #ffd700;
    color: #1e1e2f;
    font-weight: 600;
}

/* ----------------------- Main Content ----------------------- */
.main-content {
    flex: 1;
    padding: 20px;
}

/* ----------------------- Card ----------------------- */
.card {
    border-radius: 12px;
    box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
}
.card-header {
    background: linear-gradient(135deg, #36d1dc, #5b86e5);
    color: #fff;
    font-weight: 600;
}

/* ----------------------- Table Modern ----------------------- */
.table-modern {
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
    overflow: hidden;
}
.table-modern th {
    background: linear-gradient(135deg, #36d1dc, #5b86e5);
    color: #fff;
    font-weight: 600;
    text-align: center;
}
.table-modern td,
.table-modern th {
    vertical-align: middle;
    text-align: center;
}

/* ----------------------- Buttons ----------------------- */
.btn-back {
    background: #ccc;
    color: #000;
    border-radius: 6px;
    font-weight: 500;
    padding: 8px 18px;
}
.btn-back:hover {
    background: #999;
    color: #fff;
}
.btn-print {
    background: #1d3557;
    color: #fff;
    border-radius: 6px;
    font-weight: 500;
    padding: 8px 18px;
}
.btn-print:hover {
    background: #0f2340;
}

/* ----------------------- Responsive Adjustments ----------------------- */
@media (max-width: 768px) {
    .sidebar {
        width: 100%;
        min-height: auto;
        flex-direction: row;
        flex-wrap: wrap;
    }
    .main-content {
        padding: 10px;
    }
    .card {
        margin-bottom: 20px;
    }
}

</style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg">
  <div class="container-fluid">
    <a class="navbar-brand" href="#">Invoices Management</a>
    <div class="collapse navbar-collapse">
      <ul class="navbar-nav ms-auto">
        <li class="nav-item"><a class="nav-link" href="logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a></li>
      </ul>
    </div>
  </div>
</nav>

<div class="d-flex">

  <!-- Sidebar -->
  <div class="sidebar">
    <h4>Dashboard Menu</h4>
    <a href="manager_dashboard.php"><i class="bi bi-speedometer2 me-2"></i>Dashboard</a>
    <a href="users.php"><i class="bi bi-people-fill me-2"></i>Users</a>
    <a href="roles.php"><i class="bi bi-shield-lock me-2"></i>Roles</a>
    <a href="patients.php"><i class="bi bi-person-fill me-2"></i>Patients</a>
    <a href="doctors.php"><i class="bi bi-person-badge me-2"></i>Doctors</a>
    <a href="appointments.php"><i class="bi bi-calendar-check me-2"></i>Appointments</a>
    <a href="invoices.php" class="active"><i class="bi bi-receipt me-2"></i>Invoices</a>
    <a href="payments.php"><i class="bi bi-cash-stack me-2"></i>Payments</a>
    <a href="pharmacy_stock.php"><i class="bi bi-capsule me-2"></i>Pharmacy</a>
    <a href="medicines.php"><i class="bi bi-heart-pulse me-2"></i>Medicines</a>
    <a href="wards.php"><i class="bi bi-house-door me-2"></i>Wards</a>
    <a href="rooms.php"><i class="bi bi-door-closed me-2"></i>Rooms</a>
    <a href="messages.php"><i class="bi bi-chat-dots me-2"></i>Messages</a>
    <a href="admissions.php"><i class="bi bi-journal-plus me-2"></i>Admissions</a>
    <a href="admin_dashboard.php"><i class="bi bi-speedometer2 me-2"></i>Admin Dashboard</a>
  </div>

  <!-- Main Content -->
  <div class="main-content container">
    <div class="card mb-3">
      <div class="card-header">
        <i class="bi bi-receipt me-2"></i>Invoice #<?= htmlspecialchars($invoice['invoice_number']) ?>
      </div>
      <div class="card-body">
        <!-- Invoice Info Grid -->
        <div class="row mb-3">
          <div class="col-md-6"><strong>Patient:</strong> <?= htmlspecialchars($invoice['patient_first'].' '.$invoice['patient_last']) ?></div>
          <div class="col-md-6"><strong>Total Amount:</strong> $<?= number_format($invoice['total_amount'],2) ?></div>
        </div>
        <div class="row mb-3">
          <div class="col-md-6"><strong>Status:</strong> <?= ucfirst($invoice['status']) ?></div>
          <div class="col-md-6"><strong>Issued At:</strong> <?= $invoice['issued_at'] ?></div>
        </div>
        <div class="row mb-3">
          <div class="col-md-6"><strong>Due Date:</strong> <?= $invoice['due_date'] ?></div>
          <div class="col-md-6"><strong>Notes:</strong> <?= htmlspecialchars($invoice['notes']) ?></div>
        </div>

        <!-- Invoice Items Table -->
        <?php if($invoice_items): ?>
        <h5 class="mt-4">Items</h5>
        <table class="table table-modern">
          <thead>
            <tr>
              <th>#</th>
              <th>Description</th>
              <th>Quantity</th>
              <th>Unit Price</th>
              <th>Subtotal</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach($invoice_items as $index => $item): ?>
            <tr>
              <td><?= $index+1 ?></td>
              <td><?= htmlspecialchars($item['description']) ?></td>
              <td><?= $item['quantity'] ?></td>
              <td>$<?= number_format($item['unit_price'],2) ?></td>
              <td>$<?= number_format($item['quantity'] * $item['unit_price'],2) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <?php endif; ?>

        <!-- Buttons -->
        <div class="mt-4 d-flex justify-content-between">
          <a href="invoices.php" class="btn btn-back"><i class="bi bi-arrow-left me-1"></i>Back</a>
          <a href="print_invoice.php?id=<?= $invoice['id'] ?>" class="btn btn-print"><i class="bi bi-printer me-1"></i>Print</a>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
