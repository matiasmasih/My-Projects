<?php
// reserve_process.php — Save reservation to database

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/i18n.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . SITE_URL . 'reserve.php');
    exit;
}

// ---------- Read form ----------
$customer_name    = trim($_POST['customer_name']    ?? '');
$customer_phone   = trim($_POST['customer_phone']   ?? '');
$customer_email   = trim($_POST['customer_email']   ?? '');
$reservation_date = trim($_POST['reservation_date'] ?? '');
$reservation_time = trim($_POST['reservation_time'] ?? '');
$party_size       = (int)($_POST['party_size']      ?? 0);
$special_requests = trim($_POST['special_requests'] ?? '');

// ---------- Validate ----------
$errors = [];
if ($customer_name === '')    $errors[] = 'Name is required.';
if ($customer_phone === '')   $errors[] = 'Phone is required.';
if ($party_size < 1 || $party_size > 20) $errors[] = 'Party size must be between 1 and 20.';

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $reservation_date)) {
    $errors[] = 'Invalid date.';
} elseif ($reservation_date < date('Y-m-d')) {
    $errors[] = 'Reservation date cannot be in the past.';
}

if (!preg_match('/^\d{2}:\d{2}$/', $reservation_time)) {
    $errors[] = 'Invalid time.';
}

if (!empty($errors)) {
    $_SESSION['flash_error'] = implode(' ', $errors);
    header('Location: ' . SITE_URL . 'reserve.php');
    exit;
}

// ---------- Branch ----------
$branch_id = $_SESSION['branch_id'] ?? null;
if (!$branch_id) {
    try {
        $row = $pdo->query("SELECT id FROM branches WHERE is_active = 1 ORDER BY sort_order LIMIT 1")->fetch();
        $branch_id = $row['id'] ?? 1;
    } catch (PDOException $e) {
        $branch_id = 1;
    }
}

// ---------- Generate reservation code ----------
function generate_reservation_code(PDO $pdo): string {
    $date = date('ymd');
    for ($i = 0; $i < 5; $i++) {
        $rand = str_pad((string)random_int(1, 9999), 4, '0', STR_PAD_LEFT);
        $candidate = "RSV-{$date}-{$rand}";
        $stmt = $pdo->prepare("SELECT id FROM reservations WHERE reservation_code = ? LIMIT 1");
        $stmt->execute([$candidate]);
        if (!$stmt->fetch()) return $candidate;
    }
    return 'RSV-' . $date . '-' . time();
}

$reservation_code = generate_reservation_code($pdo);

// ---------- Save ----------
$customer_id = $_SESSION['customer_id'] ?? null;

try {
    $stmt = $pdo->prepare("
        INSERT INTO reservations (
            reservation_code, branch_id, customer_id,
            customer_name, customer_phone, customer_email,
            party_size, reservation_date, reservation_time,
            status, special_requests
        ) VALUES (
            :code, :branch_id, :customer_id,
            :name, :phone, :email,
            :party, :date, :time,
            'pending', :notes
        )
    ");
    $stmt->execute([
        ':code'        => $reservation_code,
        ':branch_id'   => $branch_id,
        ':customer_id' => $customer_id,
        ':name'        => $customer_name,
        ':phone'       => $customer_phone,
        ':email'       => $customer_email,
        ':party'       => $party_size,
        ':date'        => $reservation_date,
        ':time'        => $reservation_time . ':00',
        ':notes'       => $special_requests,
    ]);

    $_SESSION['last_reservation_code'] = $reservation_code;

    header('Location: ' . SITE_URL . 'reserve.php?submitted=1');
    exit;

} catch (PDOException $e) {
    error_log('reserve_process error: ' . $e->getMessage());
    $_SESSION['flash_error'] = 'Could not save reservation. Please try again.';
    header('Location: ' . SITE_URL . 'reserve.php');
    exit;
}
