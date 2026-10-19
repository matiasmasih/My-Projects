<?php
// account.php — Customer hub (profile, orders, reservations, messages)

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/i18n.php';

if (empty($_SESSION['customer_id'])) {
    header('Location: ' . SITE_URL . 'login.php');
    exit;
}

$customer_id = (int)$_SESSION['customer_id'];
$lang = current_language();

// Flash
$flash_success = $_SESSION['flash_success'] ?? '';
$flash_error   = $_SESSION['flash_error']   ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// Tab
$tab = $_GET['tab'] ?? 'profile';
if (!in_array($tab, ['profile','orders','reservations','messages'], true)) {
    $tab = 'profile';
}

// ---------- Load customer ----------
$customer = null;
try {
    $stmt = $pdo->prepare("SELECT * FROM customers WHERE id = ? LIMIT 1");
    $stmt->execute([$customer_id]);
    $customer = $stmt->fetch();
    if (!$customer) {
        session_destroy();
        header('Location: ' . SITE_URL . 'login.php');
        exit;
    }
} catch (PDOException $e) {
    die('Could not load account.');
}

// ---------- Load tab data ----------
$orders = [];
$reservations = [];
$messages = [];

try {
    // Orders
    $stmt = $pdo->prepare("
        SELECT o.*, b.name AS branch_name
        FROM orders o
        JOIN branches b ON b.id = o.branch_id
        WHERE o.customer_id = ?
        ORDER BY o.placed_at DESC
        LIMIT 50
    ");
    $stmt->execute([$customer_id]);
    $orders = $stmt->fetchAll();

    // Reservations
    $stmt = $pdo->prepare("
        SELECT r.*, b.name AS branch_name
        FROM reservations r
        JOIN branches b ON b.id = r.branch_id
        WHERE r.customer_id = ?
        ORDER BY r.reservation_date DESC, r.reservation_time DESC
        LIMIT 50
    ");
    $stmt->execute([$customer_id]);
    $reservations = $stmt->fetchAll();

    // Messages (by email)
    $stmt = $pdo->prepare("
        SELECT m.*, b.name AS branch_name
        FROM contact_messages m
        LEFT JOIN branches b ON b.id = m.branch_id
        WHERE m.email = ?
        ORDER BY m.created_at DESC
        LIMIT 50
    ");
    $stmt->execute([$customer['email']]);
    $messages = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log('account load: ' . $e->getMessage());
}

$page_title = 'My Account';
require_once __DIR__ . '/includes/header.php';
?>

<section class="admin-page">
    <div class="container">

        <!-- Hero -->
        <div class="admin-hero">
            <div>
                <h1><?= e(t('account_welcome')) ?>, <?= e($customer['full_name']) ?></h1>
                <p><?= e($customer['email']) ?></p>
            </div>
            <a href="logout.php" class="btn btn-outline" style="background:rgba(255,255,255,0.15);color:#fff;border-color:rgba(255,255,255,0.3);">
                <i class="fas fa-right-from-bracket"></i> Logout
            </a>
        </div>

        <?php if ($flash_success): ?>
            <div class="flash flash-success"><i class="fas fa-check-circle"></i> <?= e($flash_success) ?></div>
        <?php endif; ?>
        <?php if ($flash_error): ?>
            <div class="flash flash-error"><i class="fas fa-exclamation-circle"></i> <?= e($flash_error) ?></div>
        <?php endif; ?>

        <!-- Tabs -->
        <div class="admin-panel" style="padding:12px 20px;">
            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <a href="account.php?tab=profile"      class="category-tab <?= $tab === 'profile'      ? 'active' : '' ?>">
                    <i class="fas fa-user"></i> <?= e(t('account_profile')) ?>
                </a>
                <a href="account.php?tab=orders"       class="category-tab <?= $tab === 'orders'       ? 'active' : '' ?>">
                    <i class="fas fa-receipt"></i> <?= e(t('account_orders_tab')) ?> (<?= count($orders) ?>)
                </a>
                <a href="account.php?tab=reservations" class="category-tab <?= $tab === 'reservations' ? 'active' : '' ?>">
                    <i class="fas fa-calendar-check"></i> <?= e(t('account_res_tab')) ?> (<?= count($reservations) ?>)
                </a>
                <a href="account.php?tab=messages"     class="category-tab <?= $tab === 'messages'     ? 'active' : '' ?>">
                    <i class="fas fa-envelope"></i> <?= e(t('account_messages_tab')) ?> (<?= count($messages) ?>)
                </a>
            </div>
        </div>

        <!-- ======================== PROFILE ======================== -->
        <?php if ($tab === 'profile'): ?>
            <div class="admin-panel">
                <h3><i class="fas fa-user"></i> <?= e(t('account_profile')) ?> information</h3>

                <form method="post" action="account_update.php">
                    <div class="form-row">
                        <div class="form-field">
                            <label for="full_name">Full name *</label>
                            <input type="text" id="full_name" name="full_name" required
                                   value="<?= e($customer['full_name']) ?>">
                        </div>
                        <div class="form-field">
                            <label for="phone"><?= e(t('checkout_phone')) ?></label>
                            <input type="tel" id="phone" name="phone"
                                   value="<?= e($customer['phone']) ?>">
                        </div>
                    </div>

                    <div class="form-field">
                        <label for="email">Email *</label>
                        <input type="email" id="email" name="email" required
                               value="<?= e($customer['email']) ?>">
                    </div>

                    <div class="form-row">
                        <div class="form-field">
                            <label for="preferred_lang"><?= e(t('account_preferred_lang')) ?></label>
                            <select id="preferred_lang" name="preferred_lang">
                                <option value="en" <?= $customer['preferred_lang'] === 'en' ? 'selected' : '' ?>>English</option>
                                <option value="fi" <?= $customer['preferred_lang'] === 'fi' ? 'selected' : '' ?>>Suomi</option>
                                <option value="fa" <?= $customer['preferred_lang'] === 'fa' ? 'selected' : '' ?>>فارسی</option>
                            </select>
                        </div>
                        <div class="form-field">
                            <label for="preferred_currency"><?= e(t('account_preferred_curr')) ?></label>
                            <select id="preferred_currency" name="preferred_currency">
                                <option value="EUR" <?= $customer['preferred_currency'] === 'EUR' ? 'selected' : '' ?>>€ EUR</option>
                                <option value="USD" <?= $customer['preferred_currency'] === 'USD' ? 'selected' : '' ?>>$ USD</option>
                                <option value="AFN" <?= $customer['preferred_currency'] === 'AFN' ? 'selected' : '' ?>>؋ AFN</option>
                            </select>
                        </div>
                    </div>

                    <div style="margin-top:24px;padding-top:20px;border-top:1px solid var(--border);">
                        <h4 style="font-size:0.9rem;color:var(--text-muted);margin-bottom:12px;">Change password (optional)</h4>
                        <div class="form-row">
                            <div class="form-field">
                                <label for="password"><?= e(t('account_new_password')) ?></label>
                                <input type="password" id="password" name="password" minlength="6">
                            </div>
                            <div class="form-field">
                                <label for="password2"><?= e(t('account_confirm_new')) ?></label>
                                <input type="password" id="password2" name="password2" minlength="6">
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-accent btn-lg" style="margin-top:20px;">
                        <i class="fas fa-save"></i> Save changes
                    </button>
                </form>
            </div>

        <!-- ======================== ORDERS ======================== -->
        <?php elseif ($tab === 'orders'): ?>
            <div class="admin-panel">
                <h3><i class="fas fa-receipt"></i> My orders</h3>

                <?php if (empty($orders)): ?>
                    <div class="admin-empty">
                        <i class="fas fa-receipt"></i>
                        You haven't ordered yet.
                        <div style="margin-top:16px;">
                            <a href="menu.php" class="btn btn-accent"><?= e(t('account_browse_menu')) ?></a>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="admin-table-wrap">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Order #</th>
                                    <th><?= e(t('reserve_date')) ?></th>
                                    <th><?= e(t('order_branch_label')) ?></th>
                                    <th><?= e(t('admin_total')) ?></th>
                                    <th><?= e(t('admin_status')) ?></th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orders as $o): ?>
                                    <tr>
                                        <td><strong><?= e($o['order_number']) ?></strong></td>
                                        <td><?= e(date('Y-m-d H:i', strtotime($o['placed_at']))) ?></td>
                                        <td><?= e($o['branch_name']) ?></td>
                                        <td><?= e(number_format($o['total_cents'] / 100, 2)) ?> <?= e($o['currency_code']) ?></td>
                                        <td><span class="status-badge status-<?= e($o['status']) ?>"><?= e($o['status']) ?></span></td>
                                        <td>
                                            <a href="track_order.php?order=<?= e($o['order_number']) ?>" class="btn btn-outline btn-sm">
                                                <i class="fas fa-truck"></i> Track
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

        <!-- ======================== RESERVATIONS ======================== -->
        <?php elseif ($tab === 'reservations'): ?>
            <div class="admin-panel">
                <div class="admin-panel-header">
                    <h3><i class="fas fa-calendar-check"></i> My reservations</h3>
                    <a href="reserve.php" class="btn btn-accent btn-sm"><i class="fas fa-plus"></i> New reservation</a>
                </div>

                <?php if (empty($reservations)): ?>
                    <div class="admin-empty">
                        <i class="fas fa-calendar"></i>
                        No reservations yet.
                    </div>
                <?php else: ?>
                    <div class="admin-table-wrap">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th><?= e(t('admin_slug')) ?></th>
                                    <th><?= e(t('reserve_date')) ?></th>
                                    <th><?= e(t('reserve_time')) ?></th>
                                    <th><?= e(t('admin_party')) ?></th>
                                    <th><?= e(t('order_branch_label')) ?></th>
                                    <th><?= e(t('admin_status')) ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($reservations as $r): ?>
                                    <tr>
                                        <td><strong><?= e($r['reservation_code']) ?></strong></td>
                                        <td><?= e(date('D, M j, Y', strtotime($r['reservation_date']))) ?></td>
                                        <td><?= e(substr($r['reservation_time'], 0, 5)) ?></td>
                                        <td><?= (int)$r['party_size'] ?></td>
                                        <td><?= e($r['branch_name']) ?></td>
                                        <td><span class="status-badge status-<?= e($r['status']) ?>"><?= e($r['status']) ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

        <!-- ======================== MESSAGES ======================== -->
        <?php elseif ($tab === 'messages'): ?>
            <div class="admin-panel">
                <div class="admin-panel-header">
                    <h3><i class="fas fa-envelope"></i> My messages</h3>
                    <a href="contact.php" class="btn btn-accent btn-sm"><i class="fas fa-plus"></i> New message</a>
                </div>

                <?php if (empty($messages)): ?>
                    <div class="admin-empty">
                        <i class="fas fa-envelope-open"></i>
                        You haven't sent any messages yet.
                    </div>
                <?php else: ?>
                    <?php foreach ($messages as $m): ?>
                        <div style="border:1px solid var(--border);border-radius:var(--radius-sm);padding:16px;margin-bottom:14px;background:var(--bg-alt);">
                            <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:10px;flex-wrap:wrap;gap:8px;">
                                <div>
                                    <strong style="font-size:1rem;"><?= e($m['subject']) ?></strong>
                                    <div style="font-size:0.8rem;color:var(--text-muted);margin-top:2px;">
                                        <?= e(date('Y-m-d H:i', strtotime($m['created_at']))) ?>
                                        <?php if ($m['branch_name']): ?> · <?= e($m['branch_name']) ?><?php endif; ?>
                                        <?php if ($m['related_type'] !== 'general'): ?>
                                            · <span class="badge badge-accent">Re: <?= e($m['related_type']) ?> #<?= (int)$m['related_id'] ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php if ($m['is_replied']): ?>
                                    <span class="status-badge status-ready"><i class="fas fa-check"></i> Replied</span>
                                <?php else: ?>
                                    <span class="status-badge status-pending"><?= e(t('account_awaiting')) ?></span>
                                <?php endif; ?>
                            </div>

                            <div style="font-size:0.9rem;line-height:1.7;white-space:pre-wrap;margin-bottom:10px;">
                                <?= e($m['message']) ?>
                            </div>

                            <?php if (!empty($m['admin_reply'])): ?>
                                <div style="border-left:3px solid var(--accent);padding:10px 14px;background:var(--card-bg);border-radius:6px;margin-top:10px;">
                                    <div style="font-size:0.78rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.05em;margin-bottom:6px;">
                                        <i class="fas fa-reply"></i> Restaurant reply
                                        <?php if ($m['replied_at']): ?>
                                            · <?= e(date('Y-m-d H:i', strtotime($m['replied_at']))) ?>
                                        <?php endif; ?>
                                    </div>
                                    <div style="font-size:0.9rem;line-height:1.7;white-space:pre-wrap;">
                                        <?= e($m['admin_reply']) ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>

    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
