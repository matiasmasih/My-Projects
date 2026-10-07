<?php
// access_control.php — Role-based access rules

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Get current user's role.
 * Roles: guest, member, librarian, admin
 */
function current_role() {
    $role = $_SESSION['role'] ?? '';
    switch (strtolower($role)) {
        case 'admin':     return 'admin';
        case 'librarian': return 'librarian';
        case 'member':    return 'member';
        default:          return 'guest';
    }
}

/**
 * Check access for a page + role.
 * @return string 'full' | 'view' | 'no'
 */
function check_access($page, $role) {

    // ============================================
    // PUBLIC PAGES (guest allowed)
    // ============================================
    $public = [
        'index'    => ['admin'=>'full','librarian'=>'full','member'=>'full','guest'=>'full'],
        'catalog'  => ['admin'=>'full','librarian'=>'full','member'=>'full','guest'=>'full'],
        'book'     => ['admin'=>'full','librarian'=>'full','member'=>'full','guest'=>'full'],
        'search'   => ['admin'=>'full','librarian'=>'full','member'=>'full','guest'=>'full'],
        'login'    => ['admin'=>'full','librarian'=>'full','member'=>'full','guest'=>'full'],
        'register' => ['admin'=>'full','librarian'=>'full','member'=>'full','guest'=>'full'],
        'logout'   => ['admin'=>'full','librarian'=>'full','member'=>'full','guest'=>'full'],
    ];

    // ============================================
    // DASHBOARDS
    // ============================================
    $dashboards = [
        'admin_dashboard'     => ['admin'=>'full','librarian'=>'view','member'=>'no','guest'=>'no'],
        'librarian_dashboard' => ['admin'=>'full','librarian'=>'full','member'=>'no','guest'=>'no'],
        'member_dashboard'    => ['admin'=>'full','librarian'=>'full','member'=>'full','guest'=>'no'],
    ];

    // ============================================
    // MEMBER PORTAL (own data)
    // ============================================
    $member = [
        'my_account'  => ['admin'=>'full','librarian'=>'full','member'=>'full','guest'=>'no'],
        'my_loans'    => ['admin'=>'full','librarian'=>'full','member'=>'full','guest'=>'no'],
        'my_history'  => ['admin'=>'full','librarian'=>'full','member'=>'full','guest'=>'no'],
        'my_holds'    => ['admin'=>'full','librarian'=>'full','member'=>'full','guest'=>'no'],
        'my_fines'    => ['admin'=>'full','librarian'=>'full','member'=>'full','guest'=>'no'],
        'my_reviews'  => ['admin'=>'full','librarian'=>'full','member'=>'full','guest'=>'no'],
        'my_messages' => ['admin'=>'full','librarian'=>'full','member'=>'full','guest'=>'no'],
        'my_preferences' => ['admin'=>'full','librarian'=>'full','member'=>'full','guest'=>'no'],
    ];

    // ============================================
    // LIBRARIAN TOOLS
    // ============================================
    $librarian = [
        'manage_books'    => ['admin'=>'full','librarian'=>'full','member'=>'no','guest'=>'no'],
        'add_book'        => ['admin'=>'full','librarian'=>'full','member'=>'no','guest'=>'no'],
        'edit_book'       => ['admin'=>'full','librarian'=>'full','member'=>'no','guest'=>'no'],
        'delete_book'     => ['admin'=>'full','librarian'=>'full','member'=>'no','guest'=>'no'],
        'manage_copies'   => ['admin'=>'full','librarian'=>'full','member'=>'no','guest'=>'no'],
        'add_copy'        => ['admin'=>'full','librarian'=>'full','member'=>'no','guest'=>'no'],
        'manage_members'  => ['admin'=>'full','librarian'=>'full','member'=>'no','guest'=>'no'],
        'checkout'        => ['admin'=>'full','librarian'=>'full','member'=>'no','guest'=>'no'],
        'checkin'         => ['admin'=>'full','librarian'=>'full','member'=>'no','guest'=>'no'],
        'renew'           => ['admin'=>'full','librarian'=>'full','member'=>'no','guest'=>'no'],
        'manage_holds'    => ['admin'=>'full','librarian'=>'full','member'=>'no','guest'=>'no'],
        'manage_fines'    => ['admin'=>'full','librarian'=>'full','member'=>'no','guest'=>'no'],
        'manage_devices'  => ['admin'=>'full','librarian'=>'full','member'=>'no','guest'=>'no'],
    ];

    // ============================================
    // ADMIN ONLY
    // ============================================
    $admin = [
        'manage_users'     => ['admin'=>'full','librarian'=>'no','member'=>'no','guest'=>'no'],
        'manage_branches'  => ['admin'=>'full','librarian'=>'view','member'=>'no','guest'=>'no'],
        'manage_settings'  => ['admin'=>'full','librarian'=>'full','member'=>'no','guest'=>'no'],
        'reports'          => ['admin'=>'full','librarian'=>'view','member'=>'no','guest'=>'no'],
        'activity_log'     => ['admin'=>'full','librarian'=>'no','member'=>'no','guest'=>'no'],
    ];

    // Combine all
    $pages = array_merge($public, $dashboards, $member, $librarian, $admin);

    if (isset($pages[$page])) {
        return $pages[$page][$role] ?? 'no';
    }
    return 'no';
}

/** Current user's access to a page */
function my_access($page) {
    return check_access($page, current_role());
}

/** Require access — redirect if not allowed */
function require_access($page, $redirect = 'login.php') {
    $access = my_access($page);
    if ($access === 'no') {
        header("Location: $redirect");
        exit;
    }
    return $access; // 'full' or 'view'
}
?>
