<?php
// manager/get_copies.php — Returns HTML for all physical copies of a book

require_once __DIR__ . '/../includes/config.php';

if (empty($_SESSION['user_id']) || $_SESSION['user_role'] !== 'manager') {
    http_response_code(403);
    echo '<p style="color: var(--danger); padding: 20px;">Access denied.</p>';
    exit;
}

$book_id = (int)($_GET['book_id'] ?? 0);
if ($book_id <= 0) {
    echo '<p style="color: var(--danger); padding: 20px;">Invalid book.</p>';
    exit;
}

// Fetch book info
$stmt = $pdo->prepare("SELECT title, author FROM books WHERE id = ?");
$stmt->execute([$book_id]);
$book = $stmt->fetch();

if (!$book) {
    echo '<p style="color: var(--danger); padding: 20px;">Book not found.</p>';
    exit;
}

// Fetch all copies
$stmt = $pdo->prepare("
    SELECT bc.*, 
           (SELECT COUNT(*) FROM loans WHERE book_copy_id = bc.id AND status = 'active') AS active_loan
    FROM book_copies bc
    WHERE bc.book_id = ?
    ORDER BY bc.copy_number ASC
");
$stmt->execute([$book_id]);
$copies = $stmt->fetchAll();

// Status colors
function status_color($status) {
    switch ($status) {
        case 'available': return '#10b981';
        case 'borrowed': return '#f59e0b';
        case 'reserved': return '#3b82f6';
        case 'lost': return '#ef4444';
        case 'repair': return '#8b5cf6';
        default: return '#64748b';
    }
}

function condition_color($condition) {
    switch ($condition) {
        case 'excellent': return '#10b981';
        case 'good': return '#3b82f6';
        case 'fair': return '#f59e0b';
        case 'poor': return '#ef4444';
        case 'repair': return '#8b5cf6';
        default: return '#64748b';
    }
}
?>

<div style="margin-bottom: 20px;">
    <h4 style="font-size: 1.1rem; color: var(--text); margin-bottom: 4px;"><?= e($book['title']) ?></h4>
    <p style="color: var(--text-muted); font-size: 0.85rem;">by <?= e($book['author']) ?></p>
</div>

<?php if (empty($copies)): ?>
    <div style="text-align: center; padding: 40px 20px; color: var(--text-muted);">
        <i class="fas fa-copy" style="font-size: 2.5rem; opacity: 0.3; display: block; margin-bottom: 12px;"></i>
        <p>No copies exist for this book yet.</p>
    </div>
<?php else: ?>
    <div style="overflow-x: auto;">
        <table class="modern-table">
            <thead>
                <tr>
                    <th>Copy #</th>
                    <th>Condition</th>
                    <th>Status</th>
                    <th>Location</th>
                    <th>Loan</th>
                    <th>Added</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($copies as $copy): ?>
                    <tr>
                        <td style="font-weight: 700; color: var(--text);"><?= e($copy['copy_number']) ?></td>
                        <td>
                            <span style="padding: 4px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 700; text-transform: capitalize; background: <?= condition_color($copy['condition']) ?>20; color: <?= condition_color($copy['condition']) ?>;">
                                <?= e($copy['condition']) ?>
                            </span>
                        </td>
                        <td>
                            <span style="padding: 4px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 700; text-transform: capitalize; background: <?= status_color($copy['status']) ?>20; color: <?= status_color($copy['status']) ?>;">
                                <?= e($copy['status']) ?>
                            </span>
                        </td>
                        <td style="color: var(--text-muted); font-size: 0.85rem;"><?= e($copy['location'] ?: '—') ?></td>
                        <td>
                            <?php if ($copy['active_loan'] > 0): ?>
                                <span style="color: var(--warning); font-weight: 600; font-size: 0.85rem;">
                                    <i class="fas fa-user"></i> On loan
                                </span>
                            <?php else: ?>
                                <span style="color: var(--text-muted); font-size: 0.85rem;">—</span>
                            <?php endif; ?>
                        </td>
                        <td style="color: var(--text-muted); font-size: 0.85rem;"><?= date('M j, Y', strtotime($copy['created_at'])) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <div style="margin-top: 16px; padding: 12px 16px; background: var(--bg-alt); border-radius: 10px; font-size: 0.85rem; color: var(--text-muted);">
        <i class="fas fa-info-circle"></i> 
        <strong><?= count($copies) ?></strong> total copies · 
        <strong><?= count(array_filter($copies, fn($c) => $c['status'] === 'available')) ?></strong> available · 
        <strong><?= count(array_filter($copies, fn($c) => $c['status'] === 'borrowed')) ?></strong> on loan
    </div>
<?php endif; ?>
