<?php
// access_control.php - Central access rules for all pages

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Check if current user can access a given page
 *
 * @param string $page      The page key (dashboard or page name)
 * @param string $role      'manager', 'admin', 'doctor', 'staff', 'patient'
 * @return string           'full', 'view', or 'no'
 */
function check_access($page, $role) {

    // ============================================
    // DASHBOARDS
    // ============================================
    $dashboards = [
        'manager_dashboard' => [
            'manager' => 'full', 'admin' => 'view', 'doctor' => 'view',
            'staff' => 'view', 'patient' => 'no',
        ],
        'admin_dashboard' => [
            'manager' => 'full', 'admin' => 'full', 'doctor' => 'view',
            'staff' => 'view', 'patient' => 'no',
        ],
        'doctor_dashboard' => [
            'manager' => 'full', 'admin' => 'full', 'doctor' => 'full',
            'staff' => 'view', 'patient' => 'view',
        ],
        'staff_dashboard' => [
            'manager' => 'full', 'admin' => 'full', 'doctor' => 'full',
            'staff' => 'full', 'patient' => 'view',
        ],
        'patient_dashboard' => [
            'manager' => 'full', 'admin' => 'full', 'doctor' => 'full',
            'staff' => 'full', 'patient' => 'full',
        ],
    ];

    // ============================================
    // INDIVIDUAL PAGES
    // ============================================
    $pages = [
        'patients' => [
            'manager' => 'full', 'admin' => 'full', 'doctor' => 'view',
            'staff' => 'view', 'patient' => 'own',
        ],
        'doctors' => [
            'manager' => 'full', 'admin' => 'full', 'doctor' => 'view',
            'staff' => 'view', 'patient' => 'view',
        ],
        'staff' => [
            'manager' => 'full', 'admin' => 'full', 'doctor' => 'view',
            'staff' => 'view', 'patient' => 'view',
        ],
        'appointments' => [
            'manager' => 'full', 'admin' => 'full', 'doctor' => 'full',
            'staff' => 'view', 'patient' => 'own',
        ],
        'invoices' => [
            'manager' => 'full', 'admin' => 'full', 'doctor' => 'view',
            'staff' => 'view', 'patient' => 'own',
        ],
        'payments' => [
            'manager' => 'full', 'admin' => 'full', 'doctor' => 'view',
            'staff' => 'view', 'patient' => 'own',
        ],
        'prescriptions' => [
            'manager' => 'full', 'admin' => 'full', 'doctor' => 'full',
            'staff' => 'view', 'patient' => 'own',
        ],
        'medical_records' => [
            'manager' => 'full', 'admin' => 'full', 'doctor' => 'full',
            'staff' => 'view', 'patient' => 'own',
        ],
        'messages' => [
            'manager' => 'full', 'admin' => 'full', 'doctor' => 'full',
            'staff' => 'view', 'patient' => 'own',
        ],
        'pharmacy' => [
            'manager' => 'full', 'admin' => 'full', 'doctor' => 'view',
            'staff' => 'view', 'patient' => 'no',
        ],
        'medicines' => [
            'manager' => 'full', 'admin' => 'full', 'doctor' => 'view',
            'staff' => 'view', 'patient' => 'no',
        ],
        'wards' => [
            'manager' => 'full', 'admin' => 'full', 'doctor' => 'view',
            'staff' => 'view', 'patient' => 'no',
        ],
        'rooms' => [
            'manager' => 'full', 'admin' => 'full', 'doctor' => 'view',
            'staff' => 'view', 'patient' => 'no',
        ],
        'admissions' => [
            'manager' => 'full', 'admin' => 'full', 'doctor' => 'view',
            'staff' => 'view', 'patient' => 'no',
        ],
        'manager_messages' => [
            'manager' => 'full', 'admin' => 'view', 'doctor' => 'no',
            'staff' => 'no', 'patient' => 'no',
        ],
        'admin_messages' => [
            'manager' => 'view', 'admin' => 'full', 'doctor' => 'no',
            'staff' => 'no', 'patient' => 'no',
        ],
        'users' => [
            'manager' => 'no', 'admin' => 'full', 'doctor' => 'no',
            'staff' => 'no', 'patient' => 'no',
        ],
        'roles' => [
            'manager' => 'no', 'admin' => 'full', 'doctor' => 'no',
            'staff' => 'no', 'patient' => 'no',
        ], 
   ];

    // Check dashboards first
    if (isset($dashboards[$page])) {
        return $dashboards[$page][$role] ?? 'no';
    }

    // Check pages
    if (isset($pages[$page])) {
        return $pages[$page][$role] ?? 'no';
    }

    // Unknown page - deny
    return 'no';
}

/**
 * Get current user's role name from session
 */
function current_role() {
    $role_id = $_SESSION['role_id'] ?? 0;
    switch ($role_id) {
        case 1: return 'admin';
        case 2: return 'manager';
        case 3: return 'doctor';
        case 4: return 'staff';
        case 6: return 'patient';
        default: return 'guest';
    }
}

/**
 * Get access for current user on a page
 */
function my_access($page) {
    return check_access($page, current_role());
}

/**
 * Require access - redirect if not allowed
 */
function require_access($page, $redirect = 'login.php') {
    $access = my_access($page);
    if ($access === 'no') {
        header("Location: $redirect");
        exit;
    }
    return $access; // 'full', 'view', or 'own'
}
