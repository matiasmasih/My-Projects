<?php
// maintenance.php — Under maintenance page

require_once __DIR__ . '/includes/config.php';

// If maintenance mode is OFF, redirect to homepage
if (setting('maintenance_mode') !== '1') {
    header('Location: ' . SITE_URL . 'login.php');
    exit;
}

// If user is an admin, let them through (they can always access)
if (!empty($_SESSION['user_id']) && ($_SESSION['user_role'] ?? '') === 'admin') {
    header('Location: ' . SITE_URL . 'admin/dashboard.php');
    exit;
}

$company_name = setting('company_name', 'Attendance System');
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Under Maintenance — <?= e($company_name) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            color: #f1f5f9;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            text-align: center;
        }
        .card {
            max-width: 480px;
            width: 100%;
        }
        .icon {
            font-size: 4rem;
            color: #f59e0b;
            margin-bottom: 24px;
            animation: spin 3s linear infinite;
        }
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        h1 {
            font-size: 2rem;
            font-weight: 800;
            margin-bottom: 16px;
            letter-spacing: -0.02em;
        }
        p {
            font-size: 1.05rem;
            color: #94a3b8;
            line-height: 1.6;
            margin-bottom: 24px;
        }
        .badge {
            display: inline-block;
            padding: 8px 20px;
            border-radius: 20px;
            background: rgba(245,158,11,0.15);
            color: #fbbf24;
            font-size: 0.85rem;
            font-weight: 700;
            letter-spacing: 0.05em;
            text-transform: uppercase;
        }
        .footer {
            margin-top: 40px;
            font-size: 0.85rem;
            color: #64748b;
        }
        .admin-link {
            margin-top: 20px;
            display: inline-block;
            color: #60a5fa;
            text-decoration: none;
            font-size: 0.88rem;
            font-weight: 600;
        }
        .admin-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon"><i class="fas fa-tools"></i></div>
        <span class="badge">Maintenance</span>
        <h1 style="margin-top:20px;">We'll Be Back Soon</h1>
        <p>
            <?= e($company_name) ?> is currently under scheduled maintenance.
            We're working to make things better and will be back shortly.
        </p>
        <p style="font-size:0.92rem;color:#64748b;">
            If this takes longer than expected, please contact your administrator.
        </p>
        <a href="login.php" class="admin-link">
            <i class="fas fa-user-shield"></i> Admin login
        </a>
        <div class="footer">
            &copy; <?= date('Y') ?> <?= e($company_name) ?>
        </div>
    </div>
</body>
</html>
