<?php
// reserve.php — Public table reservation form

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/i18n.php';
$page_title = t('reserve_title');
require_once __DIR__ . '/includes/header.php';

$lang = current_language();

// Show success message if redirected after a successful submission
$just_submitted = isset($_GET['submitted']) && $_GET['submitted'] === '1';
$reservation_code = $_SESSION['last_reservation_code'] ?? '';
unset($_SESSION['last_reservation_code']);

// Pre-fill from logged-in customer
$is_logged = !empty($_SESSION['customer_id']);
$prefill = [
    'name'  => '',
    'email' => '',
    'phone' => '',
];
if ($is_logged) {
    try {
        $stmt = $pdo->prepare("SELECT full_name, email, phone FROM customers WHERE id = ? LIMIT 1");
        $stmt->execute([$_SESSION['customer_id']]);
        $row = $stmt->fetch();
        if ($row) {
            $prefill['name']  = $row['full_name'];
            $prefill['email'] = $row['email'];
            $prefill['phone'] = $row['phone'];
        }
    } catch (PDOException $e) {}
}

// Current branch for defaults
$branch_id = $_SESSION['branch_id'] ?? null;
?>

<section class="form-page">
    <div class="container">

        <div class="section-header">
            <h2><?= e(t('reserve_title')) ?></h2>
            <p class="section-subtitle"><?= e(setting('site_tagline', 'Book your table with us')) ?></p>
        </div>

        <?php if ($just_submitted && $reservation_code): ?>

            <!-- Success card -->
            <div class="success-card" style="max-width:560px;margin:0 auto;">
                <div class="success-icon">
                    <i class="fas fa-check"></i>
                </div>
                <h1 class="auth-title">Reservation received!</h1>
                <p class="auth-subtitle">We'll confirm your booking shortly.</p>

                <div class="success-order-number"><?= e($reservation_code) ?></div>

                <p class="text-muted" style="margin:16px 0 24px;">
                    Save this code — you can use it to check your reservation.
                </p>

                <a href="index.php" class="btn btn-accent">
                    <i class="fas fa-home"></i> <?= e(t('nav_home')) ?>
                </a>
            </div>

        <?php else: ?>

            <div style="max-width:640px;margin:0 auto;">
                <form class="form-card" method="post" action="reserve_process.php">

                    <h3 style="margin-bottom:20px;">
                        <i class="fas fa-calendar-check"></i> Booking details
                    </h3>

                    <div class="form-row">
                        <div class="form-field">
                            <label for="reservation_date"><?= e(t('reserve_date')) ?> *</label>
                            <input type="date" id="reservation_date" name="reservation_date"
                                   min="<?= e(date('Y-m-d')) ?>"
                                   value="<?= e($_GET['date'] ?? date('Y-m-d')) ?>" required>
                        </div>
                        <div class="form-field">
                            <label for="reservation_time"><?= e(t('reserve_time')) ?> *</label>
                            <input type="time" id="reservation_time" name="reservation_time"
                                   min="11:00" max="21:00" step="900"
                                   value="<?= e($_GET['time'] ?? '19:00') ?>" required>
                        </div>
                    </div>

                    <div class="form-field">
                        <label for="party_size"><?= e(t('reserve_party')) ?> *</label>
                        <select id="party_size" name="party_size" required>
                            <?php for ($i = 1; $i <= 20; $i++): ?>
                                <option value="<?= $i ?>" <?= $i === 2 ? 'selected' : '' ?>>
                                    <?= $i ?> <?= $i === 1 ? 'person' : 'people' ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>

                    <h3 style="margin:24px 0 20px;">
                        <i class="fas fa-user"></i> Your details
                    </h3>

                    <div class="form-row">
                        <div class="form-field">
                            <label for="customer_name"><?= e(t('checkout_name')) ?> *</label>
                            <input type="text" id="customer_name" name="customer_name"
                                   value="<?= e($prefill['name']) ?>" required>
                        </div>
                        <div class="form-field">
                            <label for="customer_phone"><?= e(t('checkout_phone')) ?> *</label>
                            <input type="tel" id="customer_phone" name="customer_phone"
                                   value="<?= e($prefill['phone']) ?>" required>
                        </div>
                    </div>

                    <div class="form-field">
                        <label for="customer_email"><?= e(t('checkout_email')) ?></label>
                        <input type="email" id="customer_email" name="customer_email"
                               value="<?= e($prefill['email']) ?>">
                    </div>

                    <div class="form-field">
                        <label for="special_requests"><?= e(t('reserve_notes')) ?></label>
                        <textarea id="special_requests" name="special_requests" rows="3"
                                  placeholder="Allergies, seating preference, occasion..."></textarea>
                    </div>

                    <button type="submit" class="btn btn-accent btn-lg" style="width:100%;margin-top:8px;">
                        <i class="fas fa-calendar-check"></i> <?= e(t('reserve_submit')) ?>
                    </button>

                </form>
            </div>

        <?php endif; ?>

    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
