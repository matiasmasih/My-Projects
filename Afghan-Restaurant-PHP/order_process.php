<?php
// order_process.php — Saves order + items to database

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/i18n.php';

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . SITE_URL . 'checkout.php');
    exit;
}

// ---------- Read form ----------
$order_type       = $_POST['order_type']       ?? 'delivery';
$payment_method   = $_POST['payment_method']   ?? 'cash';
$customer_name    = trim($_POST['customer_name']  ?? '');
$customer_phone   = trim($_POST['customer_phone'] ?? '');
$customer_email   = trim($_POST['customer_email'] ?? '');
$delivery_address = trim($_POST['delivery_address'] ?? '');
$delivery_city    = trim($_POST['delivery_city']    ?? '');
$delivery_postal  = trim($_POST['delivery_postal']  ?? '');
$notes            = trim($_POST['notes'] ?? '');
$currency_code    = $_POST['currency_code'] ?? ($_SESSION['currency'] ?? 'EUR');
$cart_json        = $_POST['cart_json'] ?? '[]';

// Validate order_type / payment_method against allowed values
$allowed_order   = ['delivery', 'pickup', 'dine-in'];
$allowed_payment = ['cash', 'card', 'online'];
if (!in_array($order_type, $allowed_order, true))     $order_type = 'delivery';
if (!in_array($payment_method, $allowed_payment, true)) $payment_method = 'cash';

// ---------- Validate required fields ----------
$errors = [];
if ($customer_name === '')  $errors[] = 'Name is required.';
if ($customer_phone === '') $errors[] = 'Phone is required.';
if ($order_type === 'delivery') {
    if ($delivery_address === '') $errors[] = 'Delivery address is required.';
    if ($delivery_city === '')    $errors[] = 'City is required.';
}

// Parse cart
$cart = json_decode($cart_json, true);
if (!is_array($cart) || empty($cart)) {
    $errors[] = 'Cart is empty.';
}

if (!empty($errors)) {
    $_SESSION['flash_error'] = implode(' ', $errors);
    header('Location: ' . SITE_URL . 'checkout.php');
    exit;
}

// ---------- Get current branch ----------
$branch_id = $_SESSION['branch_id'] ?? null;
if (!$branch_id) {
    try {
        $row = $pdo->query("SELECT id FROM branches WHERE is_active = 1 ORDER BY sort_order LIMIT 1")->fetch();
        $branch_id = $row['id'] ?? 1;
    } catch (PDOException $e) {
        $branch_id = 1;
    }
}

// ---------- Calculate totals ----------
$subtotal_cents = 0;
foreach ($cart as $item) {
    $subtotal_cents += (int)$item['price'] * (int)$item['qty'];
}

$delivery_fee_cents = 0;
if ($order_type === 'delivery') {
    $fee = (int)setting('delivery_fee_cents', '500');
    $freeOver = (int)setting('free_delivery_over', '5000');
    $delivery_fee_cents = ($freeOver > 0 && $subtotal_cents >= $freeOver) ? 0 : $fee;
}

$discount_cents = 0;
$total_cents    = $subtotal_cents + $delivery_fee_cents - $discount_cents;

// ---------- Generate order number ----------
function generate_order_number(PDO $pdo, ?string $branch_slug = null): string {
    $prefix = 'ORD';
    if ($branch_slug) {
        $prefix = strtoupper(substr($branch_slug, 0, 3));
    }
    $date = date('Ymd');
    for ($i = 0; $i < 5; $i++) {
        $rand = str_pad((string)random_int(1, 9999), 4, '0', STR_PAD_LEFT);
        $candidate = "{$prefix}-{$date}-{$rand}";
        $stmt = $pdo->prepare("SELECT id FROM orders WHERE order_number = ? LIMIT 1");
        $stmt->execute([$candidate]);
        if (!$stmt->fetch()) return $candidate;
    }
    return 'ORD-' . $date . '-' . time();
}

// Get branch slug for order number prefix
$branch_slug = $_SESSION['branch_slug'] ?? null;
$order_number = generate_order_number($pdo, $branch_slug);

// ---------- Determine customer ----------
$customer_id = $_SESSION['customer_id'] ?? null;

// ---------- Insert order ----------
try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        INSERT INTO orders (
            order_number, branch_id, customer_id,
            customer_name, customer_phone, customer_email,
            order_type, delivery_address, delivery_city, delivery_postal,
            payment_method, status, currency_code,
            subtotal_cents, delivery_fee_cents, discount_cents, total_cents,
            notes
        ) VALUES (
            :order_number, :branch_id, :customer_id,
            :customer_name, :customer_phone, :customer_email,
            :order_type, :delivery_address, :delivery_city, :delivery_postal,
            :payment_method, 'pending', :currency_code,
            :subtotal_cents, :delivery_fee_cents, :discount_cents, :total_cents,
            :notes
        )
    ");
    $stmt->execute([
        ':order_number'       => $order_number,
        ':branch_id'          => $branch_id,
        ':customer_id'        => $customer_id,
        ':customer_name'      => $customer_name,
        ':customer_phone'     => $customer_phone,
        ':customer_email'     => $customer_email,
        ':order_type'         => $order_type,
        ':delivery_address'   => $delivery_address,
        ':delivery_city'      => $delivery_city,
        ':delivery_postal'    => $delivery_postal,
        ':payment_method'     => $payment_method,
        ':currency_code'      => $currency_code,
        ':subtotal_cents'     => $subtotal_cents,
        ':delivery_fee_cents' => $delivery_fee_cents,
        ':discount_cents'     => $discount_cents,
        ':total_cents'        => $total_cents,
        ':notes'              => $notes,
    ]);

    $order_id = (int)$pdo->lastInsertId();

    // ---------- Insert order items ----------
    $itemStmt = $pdo->prepare("
        INSERT INTO order_items (
            order_id, menu_item_id, name_snapshot,
            price_cents, quantity, line_total_cents, notes
        ) VALUES (?, ?, ?, ?, ?, ?, ?)
    ");

    foreach ($cart as $item) {
        $menu_item_id = isset($item['id']) ? (int)$item['id'] : null;
        $name         = (string)($item['name'] ?? 'Item');
        $price        = (int)($item['price'] ?? 0);
        $qty          = (int)($item['qty'] ?? 1);
        $line_total   = $price * $qty;

        $itemStmt->execute([
            $order_id,
            $menu_item_id ?: null,
            $name,
            $price,
            $qty,
            $line_total,
            '',
        ]);
    }

    // ---------- Update customer stats (if logged in) ----------
    if ($customer_id) {
        try {
            $pdo->prepare("
                UPDATE customers
                SET total_orders = total_orders + 1,
                    total_spent_cents = total_spent_cents + ?,
                    last_order_at = NOW()
                WHERE id = ?
            ")->execute([$total_cents, $customer_id]);

            // Loyalty points (if enabled): 10 points per € spent
            if (setting('loyalty_enabled', '1') === '1') {
                $ppeur = (int)setting('loyalty_points_per_eur', '10');
                $earned = (int)floor(($total_cents / 100) * $ppeur);
                if ($earned > 0) {
                    $pdo->prepare("
                        INSERT INTO loyalty_points (customer_id, order_id, points, type, reason, balance_after)
                        VALUES (?, ?, ?, 'earned', 'Order placed', 0)
                    ")->execute([$customer_id, $order_id, $earned]);

                    $pdo->prepare("UPDATE customers SET loyalty_points = loyalty_points + ? WHERE id = ?")
                        ->execute([$earned, $customer_id]);
                }
            }
        } catch (PDOException $e) {
            // Non-fatal
            error_log('order_process customer stats error: ' . $e->getMessage());
        }
    }

    $pdo->commit();

    // Clear the cart (client-side will clear localStorage when success page loads)
    header('Location: ' . SITE_URL . 'order_success.php?order=' . urlencode($order_number));
    exit;

} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('order_process error: ' . $e->getMessage());
    $_SESSION['flash_error'] = 'Could not save order. Please try again.';
    header('Location: ' . SITE_URL . 'checkout.php');
    exit;
}
