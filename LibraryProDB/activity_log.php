<?php
// activity_log.php — Admin: full audit trail viewer
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/access_control.php';
require_once __DIR__ . '/lang.php';

require_access('activity_log');
if (my_access('activity_log') !== 'full') {
    header('Location: index.php'); exit;
}

$page_title = 'Activity Log';

// FILTERS
$q          = trim($_GET['q'] ?? '');
$user_id    = (int)($_GET['user'] ?? 0);
$action     = trim($_GET['action'] ?? '');
$entity     = trim($_GET['entity'] ?? '');
$date_from  = trim($_GET['from'] ?? '');
$date_to    = trim($_GET['to'] ?? '');
$page       = max(1, (int)($_GET['page'] ?? 1));
$per_page   = 50;

$where = []; $args = [];
if ($q !== '') {
    $where[] = "(a.description LIKE ? OR a.action LIKE ? OR u.username LIKE ?)";
    $like = "%$q%";
    array_push($args, $like, $like, $like);
}
if ($user_id > 0)   { $where[] = "a.user_id = ?";     $args[] = $user_id; }
if ($action !== '') { $where[] = "a.action = ?";      $args[] = $action; }
if ($entity !== '') { $where[] = "a.entity_type = ?"; $args[] = $entity; }
if ($date_from !== '') { $where[] = "DATE(a.created_at) >= ?"; $args[] = $date_from; }
if ($date_to !== '')   { $where[] = "DATE(a.created_at) <= ?"; $args[] = $date_to; }
$where_sql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

// PAGINATION
$count_sql = "SELECT COUNT(*) FROM activity_log a LEFT JOIN users u ON u.id = a.user_id $where_sql";
$cstmt = $pdo->prepare($count_sql);
$cstmt->execute($args);
$total = (int)$cstmt->fetchColumn();
$total_pages = max(1, (int)ceil($total / $per_page));
if ($page > $total_pages) $page = $total_pages;
$offset = ($page - 1) * $per_page;

// LOAD ENTRIES
$sql = "SELECT a.id, a.user_id, a.action, a.description,
               a.entity_type, a.entity_id, a.ip_address, a.user_agent, a.created_at,
               u.username, u.first_name, u.last_name, u.role
        FROM activity_log a
        LEFT JOIN users u ON u.id = a.user_id
        $where_sql
        ORDER BY a.id ASC
        LIMIT $per_page OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($args);
$entries = $stmt->fetchAll();

// DROPDOWN DATA
$all_users = $pdo->query(
    "SELECT DISTINCT u.id, u.username, u.first_name, u.last_name
     FROM activity_log a JOIN users u ON u.id = a.user_id
     ORDER BY u.username"
)->fetchAll();

$all_actions = $pdo->query(
    "SELECT action, COUNT(*) AS c FROM activity_log GROUP BY action ORDER BY c DESC"
)->fetchAll();

$all_entities = $pdo->query(
    "SELECT DISTINCT entity_type FROM activity_log WHERE entity_type IS NOT NULL ORDER BY entity_type"
)->fetchAll(PDO::FETCH_COLUMN);

// STATS
$stats = $pdo->query(
    "SELECT COUNT(*) AS total, COUNT(DISTINCT user_id) AS users,
            COUNT(DISTINCT action) AS actions,
            SUM(DATE(created_at) = CURDATE()) AS today
     FROM activity_log"
)->fetch();

$action_meta = [
    'book_added'      => ['bi-book',                '#0d9488'],
    'book_updated'    => ['bi-pencil-square',       '#0d9488'],
    'book_deleted'    => ['bi-trash',               '#dc2626'],
    'copy_added'      => ['bi-plus-circle',         '#0d9488'],
    'copy_deleted'    => ['bi-dash-circle',         '#dc2626'],
    'checkout'        => ['bi-box-arrow-right',     '#0891b2'],
    'checkin'         => ['bi-box-arrow-in-left',   '#0891b2'],
    'loan_renewed'    => ['bi-arrow-clockwise',     '#8b5cf6'],
    'fine_paid'       => ['bi-cash-coin',           '#059669'],
    'fine_waived'     => ['bi-x-octagon',           '#d97706'],
    'member_updated'  => ['bi-person-gear',         '#657aa8'],
    'member_status'   => ['bi-person-x',            '#dc2626'],
    'hold_placed'     => ['bi-bookmark-star',       '#7c3aed'],
    'hold_cancelled'  => ['bi-bookmark-x',          '#dc2626'],
    'hold_update'     => ['bi-bookmark-check',      '#0d9488'],
    'device_added'    => ['bi-plus-circle',         '#0d9488'],
    'device_updated'  => ['bi-pencil-square',       '#0d9488'],
    'device_deleted'  => ['bi-trash',               '#dc2626'],
    'review_deleted'  => ['bi-star-half',           '#dc2626'],
    'review_updated'  => ['bi-star',                '#0d9488'],
    'profile_update'  => ['bi-person-badge',        '#657aa8'],
    'password_change' => ['bi-shield-lock',         '#dc2626'],
];

include 'header.php';
?>

<style>

    /* === Layout containment === */
    .main-container { min-width: 0; }
    .main-content { min-width: 0; max-width: 100%; }

    /* === Stats === */
    .log-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
        gap: 14px;
        margin-bottom: 22px;
    }
    .lstat {
        background: var(--card-bg); border-radius: 14px; padding: 18px;
        box-shadow: var(--shadow); border: 1px solid rgba(13,148,136,0.08);
        text-align: center;
    }
    .lstat .v { font-size: 1.7rem; font-weight: 700; color: var(--primary-dark); line-height: 1; }
    .lstat .l { font-size: .82rem; color: var(--text-muted); margin-top: 6px; }

    /* === Toolbar === */
    .log-toolbar {
        display: flex; gap: 10px; flex-wrap: nowrap; align-items: center;
        background: var(--card-bg); padding: 14px; border-radius: 12px;
        box-shadow: var(--shadow); border: 1px solid rgba(13,148,136,0.08);
        margin-bottom: 18px;
        overflow-x: auto;
    }
    .log-toolbar input, .log-toolbar select {
        padding: 9px 12px; border: 1px solid var(--border); border-radius: 8px;
        background: var(--card-bg); color: var(--text); font-size: .9rem;
    }
    .log-toolbar input[type=text] { flex: 1; min-width: 150px; max-width: 320px; }
    .log-toolbar select { min-width: 130px; }
    .log-toolbar input[type=date] { min-width: 140px; }
    .log-toolbar button, .log-toolbar a.btn-secondary-custom { flex-shrink: 0; }

    /* === Table card === */
    .log-card {
        background: var(--card-bg); border-radius: 12px;
        box-shadow: var(--shadow); border: 1px solid rgba(13,148,136,0.08);
        overflow: hidden; max-width: 100%;
    }
    .log-scroll {
        width: 100%; max-width: 100%;
        overflow-x: auto; overflow-y: visible;
        -webkit-overflow-scrolling: touch;
        padding-bottom: 10px;
    }

    /* === Table — wider so Description gets more room === */
    table.log-table {
        width: 1400px;
        min-width: 1400px;
        max-width: none;
        border-collapse: collapse;
        table-layout: fixed;
    }
    table.log-table th, table.log-table td {
        padding: 12px 14px;
        vertical-align: middle;
        white-space: nowrap;
        border-bottom: 1px solid rgba(0,0,0,0.05);
        border-right: 1px solid rgba(13,148,136,0.10);
        text-align: left;
        font-size: .9rem;
        color: var(--text);
        font-weight: 500;
    }
    table.log-table th:last-child,
    table.log-table td:last-child { border-right: none; }

    table.log-table thead th {
        background: var(--primary);
        color: #ffffff;
        font-weight: 600;
        font-size: .78rem;
        text-transform: uppercase;
        letter-spacing: .03em;
        border-bottom: none;
        position: sticky;
        top: 0;
        z-index: 2;
        padding: 14px;
    }
    table.log-table tbody tr:hover { background: #f8fffe; }

    /* === Cells === */
    .log-icon {
        width: 32px; height: 32px; border-radius: 8px;
        display: grid; place-items: center; color: #fff; font-size: .9rem;
        flex-shrink: 0;
    }
    .log-action {
        display: inline-flex; align-items: center; gap: 8px;
        font-weight: 600; font-size: .82rem;
        text-transform: capitalize;
    }
    .log-desc {
        white-space: normal;
        line-height: 1.5;
        font-size: .85rem;
        color: var(--text);
        word-break: break-word;
    }
    .log-meta { font-size: .78rem; color: var(--text-muted); }

    /* === Pagination === */
    .pagination {
        display: flex; gap: 6px; justify-content: center; align-items: center;
        margin-top: 20px; flex-wrap: wrap;
    }
    .pagination a, .pagination span {
        padding: 7px 13px; border-radius: 8px; text-decoration: none;
        font-size: .85rem; font-weight: 600; border: 1px solid var(--border);
        color: var(--text); background: var(--card-bg);
    }
    .pagination a:hover { background: var(--primary); color: #fff; border-color: var(--primary); }
    .pagination .current { background: var(--primary); color: #fff; border-color: var(--primary); }
    .pagination .disabled { opacity: .4; pointer-events: none; }
</style>

<h2 style="margin-bottom:18px;"><i class="bi bi-clock-history" style="color:var(--primary);"></i> Activity Log</h2>

<!-- STATS -->
<div class="log-stats">
    <div class="lstat">
        <div class="v"><?= number_format((int)$stats['total']) ?></div>
        <div class="l">Total Entries</div>
    </div>
    <div class="lstat">
        <div class="v"><?= number_format((int)$stats['today']) ?></div>
        <div class="l">Today</div>
    </div>
    <div class="lstat">
        <div class="v"><?= (int)$stats['users'] ?></div>
        <div class="l">Users Involved</div>
    </div>
    <div class="lstat">
        <div class="v"><?= (int)$stats['actions'] ?></div>
        <div class="l">Action Types</div>
    </div>
</div>

<!-- FILTERS -->
<form class="log-toolbar" method="get" action="activity_log.php">
    <input type="text" name="q" placeholder="Search description, action, user…" value="<?= htmlspecialchars($q) ?>">

    <select name="user">
        <option value="0">All users</option>
        <?php foreach ($all_users as $u): ?>
            <option value="<?= (int)$u['id'] ?>" <?= $user_id===(int)$u['id']?'selected':'' ?>>
                <?= htmlspecialchars($u['username']) ?>
                <?= $u['first_name'] || $u['last_name'] ? ' (' . htmlspecialchars(trim($u['first_name'] . ' ' . $u['last_name'])) . ')' : '' ?>
            </option>
        <?php endforeach; ?>
    </select>

    <select name="action">
        <option value="">All actions</option>
        <?php foreach ($all_actions as $a): ?>
            <option value="<?= htmlspecialchars($a['action']) ?>" <?= $action===$a['action']?'selected':'' ?>>
                <?= htmlspecialchars(str_replace('_',' ', $a['action'])) ?> (<?= (int)$a['c'] ?>)
            </option>
        <?php endforeach; ?>
    </select>

    <select name="entity">
        <option value="">All entities</option>
        <?php foreach ($all_entities as $e): ?>
            <option value="<?= htmlspecialchars($e) ?>" <?= $entity===$e?'selected':'' ?>>
                <?= htmlspecialchars($e) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <input type="date" name="from" value="<?= htmlspecialchars($date_from) ?>">
    <input type="date" name="to"   value="<?= htmlspecialchars($date_to) ?>">

    <button type="submit" class="btn-add" style="white-space:nowrap;"><i class="bi bi-search"></i> Filter</button>
    <a href="activity_log.php" class="btn-secondary-custom" style="white-space:nowrap;">Reset</a>
</form>

<!-- TABLE -->
<?php if (!$entries): ?>
    <div class="log-card" style="text-align:center;padding:50px;">
        <i class="bi bi-clock-history" style="font-size:3rem;color:var(--text-muted);"></i>
        <p style="color:var(--text-muted);margin-top:12px;">No matching log entries.</p>
    </div>
<?php else: ?>
    <div class="log-card">
        <div class="log-scroll">
            <table class="log-table">
                <thead>
                    <tr>
                        <th style="width:60px;">#</th>
                        <th style="width:150px;">When</th>
                        <th style="width:180px;">User</th>
                        <th style="width:180px;">Action</th>
                        <th style="width:700px;">Description</th>
                        <th style="width:110px;">Entity</th>
                        <th style="width:120px;">IP</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($entries as $e):
                    $meta = $action_meta[$e['action']] ?? ['bi-info-circle', '#64748b'];
                    $user_label = $e['username']
                        ? '@' . $e['username']
                        : ($e['user_id'] ? 'user #' . (int)$e['user_id'] : 'system');
                ?>
                    <tr>
                        <td style="text-align:center;">
                            <code style="font-size:.8rem;"><?= (int)$e['id'] ?></code>
                        </td>
                        <td>
                            <div style="font-weight:600;font-size:.85rem;">
                                <?= htmlspecialchars(date('Y-m-d', strtotime($e['created_at']))) ?>
                            </div>
                            <div class="log-meta">
                                <?= htmlspecialchars(date('H:i:s', strtotime($e['created_at']))) ?>
                            </div>
                        </td>
                        <td>
                            <div style="font-weight:600;">
                                <?php if ($e['user_id']): ?>
                                    <?= htmlspecialchars($user_label) ?>
                                <?php else: ?>
                                    <span style="color:var(--text-muted);">system</span>
                                <?php endif; ?>
                            </div>
                            <?php if ($e['first_name'] || $e['last_name']): ?>
                                <div class="log-meta"><?= htmlspecialchars(trim($e['first_name'] . ' ' . $e['last_name'])) ?></div>
                            <?php endif; ?>
                            <?php if ($e['role']): ?>
                                <div class="log-meta" style="text-transform:capitalize;"><?= htmlspecialchars($e['role']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="log-action">
                                <span class="log-icon" style="background:<?= $meta[1] ?>;">
                                    <i class="bi <?= $meta[0] ?>"></i>
                                </span>
                                <?= htmlspecialchars(str_replace('_',' ', $e['action'])) ?>
                            </span>
                        </td>
                        <td class="log-desc"><?= htmlspecialchars($e['description'] ?? '—') ?></td>
                        <td style="background:#f8fffe;text-align:center;">
                            <?php if ($e['entity_type']): ?>
                                <div style="font-weight:600;text-transform:capitalize;font-size:.85rem;">
                                    <?= htmlspecialchars($e['entity_type']) ?>
                                </div>
                                <?php if ($e['entity_id']): ?>
                                    <div class="log-meta" style="margin-top:2px;">
                                        #<?= (int)$e['entity_id'] ?>
                                    </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <span style="color:var(--text-muted);">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="log-meta" style="text-align:center;">
                            <?= htmlspecialchars($e['ip_address'] ?? '—') ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- PAGINATION -->
    <?php if ($total_pages > 1):
        $qs = $_GET;
    ?>
        <div class="pagination">
            <?php
                $qs['page'] = 1;
                $first = 'activity_log.php?' . http_build_query($qs);
                $qs['page'] = max(1, $page - 1);
                $prev = 'activity_log.php?' . http_build_query($qs);
            ?>
            <a href="<?= htmlspecialchars($first) ?>" class="<?= $page <= 1 ? 'disabled' : '' ?>">« First</a>
            <a href="<?= htmlspecialchars($prev) ?>"  class="<?= $page <= 1 ? 'disabled' : '' ?>">‹ Prev</a>

            <?php
                $start = max(1, $page - 2);
                $end   = min($total_pages, $page + 2);
                for ($i = $start; $i <= $end; $i++):
                    $qs['page'] = $i;
            ?>
                <a href="activity_log.php?<?= htmlspecialchars(http_build_query($qs)) ?>"
                   class="<?= $i === $page ? 'current' : '' ?>"><?= $i ?></a>
            <?php endfor; ?>

            <?php
                $qs['page'] = min($total_pages, $page + 1);
                $next = 'activity_log.php?' . http_build_query($qs);
                $qs['page'] = $total_pages;
                $last = 'activity_log.php?' . http_build_query($qs);
            ?>
            <a href="<?= htmlspecialchars($next) ?>" class="<?= $page >= $total_pages ? 'disabled' : '' ?>">Next ›</a>
            <a href="<?= htmlspecialchars($last) ?>" class="<?= $page >= $total_pages ? 'disabled' : '' ?>">Last »</a>
        </div>
    <?php endif; ?>

    <p style="color:var(--text-muted);font-size:.82rem;margin-top:10px;text-align:center;">
        Showing <?= count($entries) ?> of <?= number_format($total) ?> entries
        (page <?= $page ?> of <?= $total_pages ?>)
    </p>
<?php endif; ?>

<?php include 'footer.php'; ?>
