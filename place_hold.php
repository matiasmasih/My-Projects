<?php
// place_hold.php — Member places a hold on a book
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/access_control.php';
require_once __DIR__ . '/lang.php';

require_access('my_holds');

$user_id = (int)($_SESSION['user_id'] ?? 0);
$book_id = (int)($_GET['book_id'] ?? $_POST['book_id'] ?? 0);

if ($user_id <= 0) { header('Location: login.php'); exit; }
if ($book_id <= 0) { header('Location: catalog.php'); exit; }

$role = current_role();
if ($role === 'guest') { header('Location: login.php'); exit; }

$error = '';
$success = '';

// ---------- VALIDATE BOOK ----------
$b = $pdo->prepare("SELECT id, title FROM books WHERE id = ?");
$b->execute([$book_id]);
$book = $b->fetch();
if (!$book) { header('Location: catalog.php'); exit; }

// ---------- HANDLE SUBMIT ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $branch_id = (int)($_POST['branch_id'] ?? 0) ?: null;
    $notes     = trim($_POST['notes'] ?? '');

    try {
        $pdo->beginTransaction();

        // 1. Already has an active hold for this book?
        $chk = $pdo->prepare(
            "SELECT id FROM holds
             WHERE user_id = ? AND book_id = ? AND status IN ('pending','ready')
             FOR UPDATE"
        );
        $chk->execute([$user_id, $book_id]);
        if ($chk->fetchColumn()) {
            throw new Exception('You already have an active hold on this book.');
        }

        // 2. Already has this book on loan?
        $chk = $pdo->prepare(
            "SELECT br.id FROM borrowings br
             JOIN copies c ON c.id = br.copy_id
             WHERE br.user_id = ? AND c.book_id = ? AND br.status = 'borrowed'"
        );
        $chk->execute([$user_id, $book_id]);
        if ($chk->fetchColumn()) {
            throw new Exception('You already have a copy of this book on loan.');
        }

        // 3. Compute queue position (count of active holds + 1)
        $q = $pdo->prepare(
            "SELECT COUNT(*) FROM holds
             WHERE book_id = ? AND status IN ('pending','ready')"
        );
        $q->execute([$book_id]);
        $queue_position = (int)$q->fetchColumn() + 1;

        // 4. Insert hold
        $ins = $pdo->prepare(
            "INSERT INTO holds (book_id, user_id, branch_id, status, queue_position, notes)
             VALUES (?, ?, ?, 'pending', ?, ?)"
        );
        $ins->execute([
            $book_id, $user_id, $branch_id, $queue_position,
            $notes !== '' ? $notes : null
        ]);
        $hold_id = (int)$pdo->lastInsertId();

        // 5. Log
        $pdo->prepare(
            "INSERT INTO activity_log (user_id, action, description, entity_type, entity_id)
             VALUES (?, 'hold_placed', ?, 'hold', ?)"
        )->execute([
            $user_id,
            "Placed hold on \"{$book['title']}\" (position #{$queue_position})",
            $hold_id
        ]);

        $pdo->commit();

        header('Location: my_holds.php?placed=success');
        exit;

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $error = $e->getMessage();
    }
}

// ---------- LOAD BRANCHES + AVAILABILITY ----------
$branches = $pdo->query("SELECT id, name, city FROM branches WHERE is_active=1 ORDER BY name")->fetchAll();

$avail = $pdo->prepare(
    "SELECT
        (SELECT COUNT(*) FROM copies WHERE book_id = ? AND status = 'available') AS available,
        (SELECT COUNT(*) FROM copies WHERE book_id = ?) AS total"
);
$avail->execute([$book_id, $book_id]);
$avail = $avail->fetch();

// Existing active hold by this user?
$eh = $pdo->prepare(
    "SELECT id, queue_position FROM holds
     WHERE user_id = ? AND book_id = ? AND status IN ('pending','ready')"
);
$eh->execute([$user_id, $book_id]);
$existing = $eh->fetch();

$page_title = 'Place a Hold';
include 'header.php';
?>

<div class="table-card" style="max-width:640px;margin:0 auto;">

    <h3 style="margin-bottom:6px;">
        <i class="bi bi-bookmark-star" style="color:var(--primary);"></i> Place a Hold
    </h3>
    <p style="color:var(--text-muted);font-size:.9rem;margin-bottom:18px;">
        For: <strong><?= htmlspecialchars($book['title']) ?></strong>
        ·
        <?= (int)$avail['available'] ?> of <?= (int)$avail['total'] ?> copies available
    </p>

    <?php if ($error): ?>
        <div class="status-badge status-danger" style="display:block;padding:12px 16px;margin-bottom:14px;">
            <i class="bi bi-exclamation-triangle-fill"></i> <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <?php if ($existing): ?>
        <div class="status-badge status-pending" style="display:block;padding:14px 16px;margin-bottom:14px;">
            <i class="bi bi-bookmark-check-fill"></i>
            You already have an active hold on this book (position #<?= (int)$existing['queue_position'] ?>).
        </div>
        <div style="display:flex;gap:10px;">
            <a href="my_holds.php" class="btn-add" style="text-decoration:none;">
                <i class="bi bi-list"></i> View My Holds
            </a>
            <a href="book.php?id=<?= (int)$book_id ?>" class="btn-secondary-custom">
                <i class="bi bi-arrow-left"></i> Back to Book
            </a>
        </div>
    <?php else: ?>
        <form method="POST" action="place_hold.php">
            <input type="hidden" name="book_id" value="<?= (int)$book_id ?>">

            <div style="margin-bottom:14px;">
                <label style="display:block;font-weight:600;margin-bottom:6px;">Preferred Branch (optional)</label>
                <select name="branch_id"
                        style="width:100%;padding:10px 12px;border:1px solid var(--border);border-radius:10px;font-size:1rem;">
                    <option value="">— Any branch —</option>
                    <?php foreach ($branches as $br): ?>
                        <option value="<?= (int)$br['id'] ?>">
                            <?= htmlspecialchars($br['name']) ?><?= $br['city'] ? ' · ' . htmlspecialchars($br['city']) : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="margin-bottom:14px;">
                <label style="display:block;font-weight:600;margin-bottom:6px;">Notes (optional)</label>
                <textarea name="notes" rows="3"
                          style="width:100%;padding:10px 12px;border:1px solid var(--border);border-radius:10px;font-size:1rem;resize:vertical;"
                          placeholder="e.g. I need it for a school project"></textarea>
            </div>

            <p style="color:var(--text-muted);font-size:.82rem;margin-bottom:18px;">
                You'll be added to the queue. When a copy becomes available, the library will notify you.
                Holds expire 3 days after they're marked ready.
            </p>

            <div style="display:flex;justify-content:space-between;align-items:center;">
                <a href="book.php?id=<?= (int)$book_id ?>" class="btn-secondary-custom">← Back</a>
                <button type="submit" class="btn-add">
                    <i class="bi bi-check-circle"></i> Place Hold
                </button>
            </div>
        </form>
    <?php endif; ?>
</div>

<?php include 'footer.php'; ?>
