<?php
// rules.php — Public: Library rules & policy (reads from settings table)
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/access_control.php';
require_once __DIR__ . '/lang.php';

$page_title = 'Library Rules';

// Branch list for opening hours
$branches = $pdo->query(
    "SELECT name, city, phone, email, opening_hours
     FROM branches
     WHERE is_active = 1
     ORDER BY name"
)->fetchAll();

include 'header.php';
?>

<style>
    .main-content { min-width: 0; max-width: 100%; }

    .rules-hero {
        background: linear-gradient(135deg, var(--primary), var(--primary-dark));
        color: #fff; border-radius: var(--radius);
        padding: 32px 34px; margin-bottom: 24px;
        box-shadow: 0 6px 24px rgba(13,148,136,0.25);
    }
    .rules-hero h1 { margin: 0 0 8px; font-size: 1.8rem; font-weight: 800; }
    .rules-hero p { margin: 0; opacity: .92; font-size: .98rem; }

    .rules-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 18px; margin-bottom: 24px;
    }
    .rule-card {
        background: var(--card-bg); border-radius: 14px;
        padding: 22px; box-shadow: var(--shadow);
        border: 1px solid rgba(13,148,136,0.08);
        display: flex; gap: 16px; align-items: flex-start;
    }
    .rule-card .ic {
        width: 46px; height: 46px; border-radius: 12px; flex-shrink: 0;
        display: grid; place-items: center; color: #fff; font-size: 1.3rem;
        background: linear-gradient(135deg, var(--primary), var(--accent));
    }
    .rule-card .body { flex: 1; min-width: 0; }
    .rule-card .label {
        font-size: .78rem; text-transform: uppercase; letter-spacing: .04em;
        color: var(--text-muted); font-weight: 600; margin-bottom: 4px;
    }
    .rule-card .value {
        font-size: 1.5rem; font-weight: 800; color: var(--text); line-height: 1.1;
    }
    .rule-card .value small {
        font-size: .85rem; font-weight: 600;
        color: var(--text-muted); margin-left: 4px;
    }
    .rule-card .hint {
        font-size: .82rem; color: var(--text-muted);
        margin-top: 8px; line-height: 1.5;
    }

    .section {
        background: var(--card-bg); border-radius: 14px;
        padding: 24px; box-shadow: var(--shadow);
        border: 1px solid rgba(13,148,136,0.08);
        margin-bottom: 20px;
    }
    .section h3 {
        margin: 0 0 16px; font-size: 1.1rem;
        display: flex; align-items: center; gap: 8px;
        padding-bottom: 10px; border-bottom: 1px solid rgba(0,0,0,0.06);
    }
    .section h3 i { color: var(--primary); }

    .section ul { margin: 0; padding-left: 22px; line-height: 1.85; }
    .section li { color: var(--text); font-size: .94rem; margin-bottom: 4px; }
    .section p { color: var(--text); line-height: 1.7; font-size: .94rem; }

    .branch-list {
        display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
        gap: 14px;
    }
    .branch-item {
        background: #f8fffe; padding: 14px 16px; border-radius: 10px;
        border: 1px solid rgba(13,148,136,0.10);
    }
    .branch-item .name { font-weight: 700; color: var(--text); margin-bottom: 6px; }
    .branch-item .meta { font-size: .82rem; color: var(--text-muted); line-height: 1.5; }
    .branch-item .meta i { color: var(--primary); width: 16px; }

    .updated-line {
        text-align: center; color: var(--text-muted); font-size: .82rem;
        margin-top: 24px; padding-top: 18px; border-top: 1px solid rgba(0,0,0,0.05);
    }
</style>

<div class="rules-hero">
    <h1><i class="bi bi-journal-bookmark-fill"></i> Library Rules & Policy</h1>
    <p>Everything you need to know about borrowing, returning, and using <?= htmlspecialchars(SITE_NAME) ?>.</p>
</div>

<!-- KEY NUMBERS -->
<div class="rules-grid">
    <div class="rule-card">
        <div class="ic"><i class="bi bi-calendar-check"></i></div>
        <div class="body">
            <div class="label">Loan period</div>
            <div class="value"><?= (int)LOAN_DAYS ?> <small>days</small></div>
            <div class="hint">How long you can keep a book before returning it.</div>
        </div>
    </div>

    <div class="rule-card">
        <div class="ic"><i class="bi bi-arrow-clockwise"></i></div>
        <div class="body">
            <div class="label">Renewals</div>
            <div class="value"><?= (int)MAX_RENEWALS ?> <small>times</small></div>
            <div class="hint">Extend a loan by another <?= (int)LOAN_DAYS ?> days, up to <?= (int)MAX_RENEWALS ?> times.</div>
        </div>
    </div>

    <div class="rule-card">
        <div class="ic"><i class="bi bi-stack"></i></div>
        <div class="body">
            <div class="label">Simultaneous loans</div>
            <div class="value"><?= (int)MAX_LOANS_PER_MEMBER ?> <small>books</small></div>
            <div class="hint">Maximum number of books you can have borrowed at the same time.</div>
        </div>
    </div>

    <div class="rule-card">
        <div class="ic"><i class="bi bi-cash-coin"></i></div>
        <div class="body">
            <div class="label">Overdue charge</div>
            <div class="value">€<?= number_format(FINE_PER_DAY, 2) ?> <small>per day</small></div>
            <div class="hint">Per book, per day after the due date.</div>
        </div>
    </div>

    <div class="rule-card">
        <div class="ic"><i class="bi bi-shield-check"></i></div>
        <div class="body">
            <div class="label">Maximum fine per book</div>
            <div class="value">€<?= number_format(MAX_FINE_PER_BOOK, 2) ?></div>
            <div class="hint">Charges stop growing at this amount, even if the book stays out longer.</div>
        </div>
    </div>

    <div class="rule-card">
        <div class="ic"><i class="bi bi-exclamation-triangle"></i></div>
        <div class="body">
            <div class="label">Borrowing block</div>
            <div class="value">€<?= number_format(FINE_BLOCK_THRESHOLD, 2) ?></div>
            <div class="hint">If your unpaid fines exceed this, you cannot borrow new books until you settle them.</div>
        </div>
    </div>
</div>

<!-- BORROWING RULES -->
<div class="section">
    <h3><i class="bi bi-book"></i> How borrowing works</h3>
    <ul>
        <li><strong>Loan period:</strong> each book may be kept for <strong><?= (int)LOAN_DAYS ?> days</strong> from the checkout date.</li>
        <li><strong>Renewals:</strong> you may renew a loan up to <strong><?= (int)MAX_RENEWALS ?> times</strong>, each extending the due date by <?= (int)LOAN_DAYS ?> days.</li>
        <li><strong>Renewal restrictions:</strong> a loan cannot be renewed if it is overdue, if another member is waiting on hold, or if you have unpaid fines above €<?= number_format(FINE_BLOCK_THRESHOLD, 2) ?>.</li>
        <li><strong>Concurrent loans:</strong> you may have up to <strong><?= (int)MAX_LOANS_PER_MEMBER ?> books</strong> borrowed at once.</li>
        <li><strong>Returns:</strong> please return books to the front desk of any branch.</li>
    </ul>
</div>

<!-- FINES -->
<div class="section">
    <h3><i class="bi bi-cash-coin"></i> Fines & charges</h3>
    <ul>
        <li><strong>Overdue:</strong> €<?= number_format(FINE_PER_DAY, 2) ?> per day, per book, starting the day after the due date.</li>
        <li><strong>Cap:</strong> the fine per book will not exceed €<?= number_format(MAX_FINE_PER_BOOK, 2) ?>.</li>
        <li><strong>Damage or loss:</strong> the replacement cost of the item may be charged.</li>
        <li><strong>Payment:</strong> pay at any branch front desk. Cash, card, bank transfer, or online payment accepted.</li>
        <li><strong>Borrowing block:</strong> if your unpaid fines exceed €<?= number_format(FINE_BLOCK_THRESHOLD, 2) ?>, you cannot borrow new items until the balance is settled.</li>
    </ul>
</div>

<!-- HOLDS -->
<div class="section">
    <h3><i class="bi bi-bookmark-star"></i> Holds & reservations</h3>
    <ul>
        <li>If all copies of a book are currently borrowed, you may <strong>place a hold</strong> from the book's page.</li>
        <li>You will be notified when a copy becomes available.</li>
        <li>Holds are held for <strong>3 days</strong> after they are marked ready, then expire.</li>
        <li>You may cancel a hold at any time from <a href="my_holds.php" style="color:var(--primary-dark);">My Holds</a>.</li>
    </ul>
</div>

<!-- MEMBERSHIP -->
<div class="section">
    <h3><i class="bi bi-person-badge"></i> Membership</h3>
    <ul>
        <li>Membership is free and open to all residents.</li>
        <li>Bring a valid photo ID to any branch to register — you'll receive a library card with your member number.</li>
        <li>Your card is personal — please do not lend it to others.</li>
        <li>Report a lost card to the front desk immediately; a replacement can be issued.</li>
        <li>Accounts with unpaid fines above €<?= number_format(FINE_BLOCK_THRESHOLD, 2) ?> may be suspended until the balance is settled.</li>
    </ul>
</div>

<!-- CONDUCT -->
<div class="section">
    <h3><i class="bi bi-people"></i> Conduct & respect</h3>
    <ul>
        <li>Please keep noise to a minimum in study areas.</li>
        <li>Food and drink are permitted in designated areas only.</li>
        <li>Treat library materials with care — do not write in or damage them.</li>
        <li>Respect other members and staff.</li>
        <li>Return items on time so others can enjoy them.</li>
    </ul>
</div>

<!-- BRANCHES -->
<?php if ($branches): ?>
<div class="section">
    <h3><i class="bi bi-building"></i> Branch locations & hours</h3>
    <div class="branch-list">
        <?php foreach ($branches as $b): ?>
            <div class="branch-item">
                <div class="name"><?= htmlspecialchars($b['name']) ?></div>
                <div class="meta">
                    <?php if ($b['city']): ?>
                        <div><i class="bi bi-geo-alt"></i> <?= htmlspecialchars($b['city']) ?></div>
                    <?php endif; ?>
                    <?php if ($b['opening_hours']): ?>
                        <div><i class="bi bi-clock"></i> <?= htmlspecialchars($b['opening_hours']) ?></div>
                    <?php endif; ?>
                    <?php if ($b['phone']): ?>
                        <div><i class="bi bi-telephone"></i> <?= htmlspecialchars($b['phone']) ?></div>
                    <?php endif; ?>
                    <?php if ($b['email']): ?>
                        <div><i class="bi bi-envelope"></i> <?= htmlspecialchars($b['email']) ?></div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<div class="updated-line">
    <i class="bi bi-info-circle"></i>
    These rules are current. Policy values are maintained by library staff and may be updated without notice.
</div>

<?php include 'footer.php'; ?>
