<?php
// includes/auth.php — Login state helpers

require_once __DIR__ . '/config.php';

/**
 * Is someone logged in as admin?
 */
function is_logged_in(): bool {
    return !empty($_SESSION['admin_id']);
}

/**
 * Get the current admin's data (id, username, full_name, email, role).
 * Returns null if not logged in.
 */
function current_admin(): ?array {
    if (!is_logged_in()) return null;

    return [
        'id'        => (int)$_SESSION['admin_id'],
        'username'  => $_SESSION['admin_username']  ?? '',
        'full_name' => $_SESSION['admin_full_name'] ?? '',
        'email'     => $_SESSION['admin_email']     ?? '',
        'role'      => $_SESSION['admin_role']      ?? 'admin',
    ];
}

/**
 * Get current admin's display name (full name or username).
 */
function current_admin_name(): string {
    $a = current_admin();
    if (!$a) return '';
    return $a['full_name'] !== '' ? $a['full_name'] : $a['username'];
}

/**
 * Log an admin in (sets session).
 */
function login_admin(array $admin): void {
    $_SESSION['admin_id']        = (int)$admin['id'];
    $_SESSION['admin_username']  = $admin['username'];
    $_SESSION['admin_full_name'] = $admin['full_name'] ?? '';
    $_SESSION['admin_email']     = $admin['email']     ?? '';
    $_SESSION['admin_role']      = $admin['role']      ?? 'admin';
}

/**
 * Log the current admin out.
 */
function logout_admin(): void {
    unset(
        $_SESSION['admin_id'],
        $_SESSION['admin_username'],
        $_SESSION['admin_full_name'],
        $_SESSION['admin_email'],
        $_SESSION['admin_role']
    );
}

/**
 * Require login — redirect to admin_login.php if not logged in.
 */
function require_login(string $redirect = 'admin_login.php'): void {
    if (!is_logged_in()) {
        header('Location: ' . $redirect);
        exit;
    }
}
