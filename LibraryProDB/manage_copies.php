<?php
// manage_copies.php — Librarian: list + add + delete physical copies
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/access_control.php';
require_once __DIR__ . '/lang.php';

require_access('manage_copies');

if (my_access('manage_copies') !== 'full') {
    header('Location: index.php'); exit;
}

$page_title = 'Manage Copies';

$action = $_GET['action'] ?? 'list';
$error  = '';

// ---------- ADD ----------
if ($action === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $book_id   = (int)($_POST['book_id'] ?? 0);
    $barcode   = trim($_POST['barcode'] ?? '');
    $shelf_id  = (int)($_POST['shelf_id'] ?? 0) ?: null;
    $condition = $_POST['copy_condition'] ?? 'good';
    $price     = $_POST['price'] !== '' ? (float)$_POST['price'] : null;
    $acquired  = trim($_POST['acquired_date'] ?? '') ?: null;

    $valid_conditions = ['new','good','worn','damaged','lost'];

    if ($book_id <= 0) {
        $error = 'Please choose a book.';
    } elseif ($barcode === '') {
        $error = 'Barcode is required.';
    } elseif (!preg_match('/^[A-Z0-9\-]{4,50}$/', strtoupper($barcode))) {
        $error = 'Barcode must be 4–50 characters (letters, numbers, dash).';
    } elseif (!in_array($condition, $valid_conditions, true)) {
        $error = 'Invalid condition.';
    } else {
        $dup = $pdo->prepare("SELECT id FROM copies WHERE barcode = ?");
        $dup->execute([strtoupper($barcode)]);
        if ($dup->fetchColumn()) {
            $error = 'That barcode is already used.';
        }
    }

    if (!$error) {
        try {
            $stmt = $pdo->prepare(
                "INSERT INTO copies (book_id, barcode, shelf_id, copy_condition, status, acquired_date, price)
                 VALUES (?, ?, ?, ?, 'available', ?, ?)"
            );
            $stmt->execute([$book_id, strtoupper($barcode), $shelf_id, $condition, $acquired, $price]);
            $newId = (int)$pdo->lastInsertId();

            $log = $pdo->prepare(
                "INSERT INTO activity_log (user_id, action, description) VALUES (?, ?, ?)"
            );
            $log->execute([
                $_SESSION['user_id'],
                'copy_added',
                "Added copy #{$newId} ({$barcode}) for book #{$book_id}"
            ]);

            header('Location: manage_copies.php?saved=success');
            exit;
        } catch (PDOException $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}

// ---------- DELETE ----------
if ($action === 'delete' && current_role() === 'admin') {
    $id = (int)($_GET['id'] ?? 0);
    if ($id > 0) {
        $chk = $pdo->prepare("SELECT status FROM copies WHERE id = ?");
        $chk->execute([$id]);
        $status = $chk->fetchColumn();
        if ($status === 'borrowed') {
            header('Location: manage_copies.php?error=borrowed');
            exit;
        }
        $pdo->prepare("DELETE FROM copies WHERE id = ?")->execute([$id]);
        $log = $pdo->prepare("INSERT INTO activity_log (user_id, action, description) VALUES (?, 'copy_deleted', ?)");
        $log->execute([$_SESSION['user_id'], "Deleted copy #{$id}"]);
    }
    header('Location: manage_copies.php?deleted=success');
    exit;
}

// ---------- LOAD DATA ----------
if ($action === 'add') {
    $books  = $pdo->query("SELECT id, title, isbn FROM books ORDER BY title")->fetchAll();
    $shelves = $pdo->query(
        "SELECT s.id, s.code, s.section, b.name AS branch_name
         FROM shelves s JOIN branches b ON b.id = s.branch_id
         ORDER BY b.name, s.code"
    )->fetchAll();
} else {
    $q = trim($_GET['q'] ?? '');
    $where = ''; $args = [];
    if ($q !== '') {
        $where = "WHERE c.barcode LIKE ? OR b.title LIKE ?";
        array_push($args, "%$q%", "%$q%");
    }
    $copies = $pdo->prepare(
        "SELECT c.id, c.barcode, c.status, c.copy_condition, c.acquired_date, c.price,
                b.id AS book_id, b.title, b.cover_image,
                s.code AS shelf_code, s.section,
                br.name AS branch_name, br.city
         FROM copies c
         JOIN books b ON b.id = c.book_id
         LEFT JOIN shelves s ON s.id = c.shelf_id
         LEFT JOIN branches br ON br.id = s.branch_id
         $where
         ORDER BY c.id DESC
         LIMIT 500"
    );
    $copies->execute($args);
    $copies = $copies->fetchAll();
}

include 'header.php';
?>

<?php if (isset($_GET['saved']) && $_GET['saved'] === 'success'): ?>
    <div class="status-badge status-active" style="display:block;padding:12px 16px;margin-bottom:14px;">
        Copy added successfully.
    </div>
<?php endif; ?>
<?php if (isset($_GET['deleted']) && $_GET['deleted'] === 'success'): ?>
    <div class="status-badge status-active" style="display:block;padding:12px 16px;margin-bottom:14px;">
        Copy deleted.
    </div>
<?php endif; ?>
<?php if (isset($_GET['error']) && $_GET['error'] === 'borrowed'): ?>
    <div class="status-badge status-danger" style="display:block;padding:12px 16px;margin-bottom:14px;">
        Cannot delete — this copy is currently borrowed.
    </div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="status-badge status-danger" style="display:block;padding:12px 16px;margin-bottom:14px;">
        <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<?php if ($action === 'add'): ?>
    <!-- ============ ADD COPY FORM ============ -->
    <div class="table-card" style="max-width:720px;margin:0 auto;">
        <h5><i class="bi bi-plus-circle-fill"></i> Add Physical Copy</h5>

        <form method="POST" action="manage_copies.php?action=add">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">

                <div style="grid-column:span 2;">
                    <label style="display:block;font-weight:600;margin-bottom:6px;">Book *</label>
                    <select name="book_id" required
                            style="width:100%;padding:10px 12px;border:1px solid #cbd5e1;border-radius:10px;font-size:1rem;">
                        <option value="">— Select book —</option>
                        <?php foreach ($books as $b): ?>
                            <option value="<?= (int)$b['id'] ?>" <?= ((int)($_POST['book_id'] ?? 0) === (int)$b['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($b['title']) ?>
                                <?= $b['isbn'] ? ' · ' . htmlspecialchars($b['isbn']) : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label style="display:block;font-weight:600;margin-bottom:6px;">Barcode *</label>
                    <input type="text" name="barcode" required maxlength="50" placeholder="e.g. BC-0001"
                           value="<?= htmlspecialchars($_POST['barcode'] ?? '') ?>"
                           style="width:100%;padding:10px 12px;border:1px solid #cbd5e1;border-radius:10px;font-size:1rem;text-transform:uppercase;">
                </div>

                <div>
                    <label style="display:block;font-weight:600;margin-bottom:6px;">Condition</label>
                    <select name="copy_condition"
                            style="width:100%;padding:10px 12px;border:1px solid #cbd5e1;border-radius:10px;font-size:1rem;">
                        <?php foreach (['new','good','worn','damaged'] as $c): ?>
                            <option value="<?= $c ?>" <?= (($_POST['copy_condition'] ?? 'good') === $c) ? 'selected' : '' ?>>
                                <?= ucfirst($c) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div style="grid-column:span 2;">
                    <label style="display:block;font-weight:600;margin-bottom:6px;">Shelf (Branch · Section)</label>
                    <select name="shelf_id"
                            style="width:100%;padding:10px 12px;border:1px solid #cbd5e1;border-radius:10px;font-size:1rem;">
                        <option value="">— Unassigned —</option>
                        <?php foreach ($shelves as $s): ?>
                            <option value="<?= (int)$s['id'] ?>" <?= ((int)($_POST['shelf_id'] ?? 0) === (int)$s['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($s['branch_name']) ?> · <?= htmlspecialchars($s['code']) ?>
                                <?= $s['section'] ? ' (' . htmlspecialchars($s['section']) . ')' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label style="display:block;font-weight:600;margin-bottom:6px;">Acquired Date</label>
                    <input type="date" name="acquired_date"
                           value="<?= htmlspecialchars($_POST['acquired_date'] ?? date('Y-m-d')) ?>"
                           style="width:100%;padding:10px 12px;border:1px solid #cbd5e1;border-radius:10px;font-size:1rem;">
                </div>

                <div>
                    <label style="display:block;font-weight:600;margin-bottom:6px;">Price (€)</label>
                    <input type="number" step="0.01" min="0" name="price"
                           value="<?= htmlspecialchars($_POST['price'] ?? '') ?>"
                           style="width:100%;padding:10px 12px;border:1px solid #cbd5e1;border-radius:10px;font-size:1rem;">
                </div>
            </div>

            <div style="display:flex;justify-content:space-between;align-items:center;margin-top:22px;">
                <a href="manage_copies.php" class="btn-secondary-custom">← Back</a>
                <button type="submit" class="btn-add">
                    <i class="bi bi-check-circle"></i> Add Copy
                </button>
            </div>
        </form>
    </div>

<?php else: ?>
    <!-- ============ LIST ============ -->
    <div class="table-card">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
            <h5 style="margin:0;"><i class="bi bi-collection"></i> Physical Copies (<?= count($copies) ?>)</h5>
            <a href="manage_copies.php?action=add" class="btn-add" style="text-decoration:none;">
                <i class="bi bi-plus-circle-fill"></i> Add Copy
            </a>
        </div>

        <form method="get" style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:18px;">
            <input type="text" name="q" value="<?= htmlspecialchars($_GET['q'] ?? '') ?>"
                   placeholder="Search barcode or title…"
                   style="flex:1;min-width:220px;padding:10px 12px;border:1px solid var(--border);border-radius:8px;background:var(--card-bg);color:var(--text);">
            <button type="submit" class="btn-add"><i class="bi bi-search"></i> Search</button>
            <a href="manage_copies.php" class="btn-secondary-custom"><i class="bi bi-x-circle"></i> Reset</a>
        </form>

        <?php if (!$copies): ?>
            <p style="color:var(--text-muted);">No copies yet. Click "Add Copy" to register the first physical copy.</p>
        <?php else: ?>
        <div style="overflow:auto;max-height:70vh;">
            <table>
                <thead>
                    <tr style="position:sticky;top:0;background:var(--card-bg);z-index:2;">
                        <th style="width:60px;"></th>
                        <th>Barcode</th>
                        <th>Book</th>
                        <th>Branch · Shelf</th>
                        <th>Condition</th>
                        <th>Status</th>
                        <th>Acquired</th>
                        <?php if (current_role() === 'admin'): ?><th></th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($copies as $c): ?>
                    <tr>
                        <td style="padding:6px;">
                            <?php if (!empty($c['cover_image']) && file_exists(__DIR__ . '/uploads/covers/' . $c['cover_image'])): ?>
                                <img src="uploads/covers/<?= htmlspecialchars($c['cover_image']) ?>" alt=""
                                     style="width:40px;height:60px;object-fit:cover;border-radius:4px;">
                            <?php else: ?>
                                <div style="width:40px;height:60px;border-radius:4px;background:linear-gradient(135deg,var(--primary),var(--accent));display:grid;place-items:center;color:white;font-size:1.2rem;">
                                    <i class="bi bi-book"></i>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td><code><?= htmlspecialchars($c['barcode']) ?></code></td>
                        <td><?= htmlspecialchars($c['title']) ?></td>
                        <td><?= htmlspecialchars($c['branch_name'] ?? '—') ?><?= $c['shelf_code'] ? ' · ' . htmlspecialchars($c['shelf_code']) : '' ?></td>
                        <td>
                            <span class="status-badge <?= in_array($c['copy_condition'], ['new','good'], true) ? 'status-active' : 'status-warning' ?>">
                                <?= htmlspecialchars($c['copy_condition']) ?>
                            </span>
                        </td>
                        <td>
                            <span class="status-badge status-<?= $c['status'] === 'available' ? 'active' : ($c['status'] === 'borrowed' ? 'pending' : 'cancelled') ?>">
                                <?= htmlspecialchars($c['status']) ?>
                            </span>
                        </td>
                        <td><?= htmlspecialchars($c['acquired_date'] ?? '—') ?></td>
                        <?php if (current_role() === 'admin'): ?>
                            <td>
                                <?php if ($c['status'] !== 'borrowed'): ?>
                                    <a href="manage_copies.php?action=delete&id=<?= (int)$c['id'] ?>"
                                       class="btn-add" style="background:#dc2626;color:#fff;padding:4px 10px;font-size:.8rem;text-decoration:none;"
                                       onclick="return confirm('Delete this copy?');">Delete</a>
                                <?php else: ?>
                                    <span style="color:var(--text-muted);font-size:.8rem;">borrowed</span>
                                <?php endif; ?>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php include 'footer.php'; ?>
