<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin_login.php');
    exit();
}

$info_sql = "SELECT * FROM personal_info LIMIT 1";
$info_result = $conn->query($info_sql);
$info = $info_result->fetch_assoc();
$admin_name = $_SESSION['admin_name'] ?? 'Admin';

// Update settings
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    foreach ($_POST as $key => $value) {
        if (strpos($key, 'setting_') === 0) {
            $setting_key = substr($key, 8);
            $setting_value = $conn->real_escape_string($value);
            $conn->query("INSERT INTO site_settings (setting_key, setting_value) VALUES ('$setting_key', '$setting_value') ON DUPLICATE KEY UPDATE setting_value='$setting_value'");
        }
    }
    $success = 'Asetukset tallennettu!';
}

$settings = [];
$result = $conn->query("SELECT * FROM site_settings");
while($row = $result->fetch_assoc()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
?>

<!DOCTYPE html>
<html lang="fi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sivuston asetukset | <?php echo htmlspecialchars($info['full_name']); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        body {
            background: #010714;
            color: #fff;
            min-height: 100vh;
        }

        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: radial-gradient(circle at 20% 50%, rgba(0,229,255,0.08), transparent 50%),
                        radial-gradient(circle at 80% 80%, rgba(138,43,226,0.08), transparent 50%);
            z-index: -2;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 0 25px;
        }

        header {
            position: relative;
            width: 95%;
            max-width: 1400px;
            margin: 20px auto 0;
            padding: 12px 30px;
            border-radius: 80px;
            background: rgba(10,12,21,0.75);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(0,229,255,0.25);
        }

        header .container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            max-width: 1400px;
        }

        .logo h1 {
            font-size: 26px;
            font-weight: 800;
            background: linear-gradient(135deg, #00e5ff, #8a2be2, #ff6b6b);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .admin-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .admin-badge {
            background: rgba(0,229,255,0.15);
            padding: 8px 18px;
            border-radius: 40px;
            font-size: 14px;
            border: 1px solid rgba(0,229,255,0.3);
        }

        .back-btn {
            background: rgba(0,229,255,0.15);
            color: #00e5ff;
            padding: 8px 20px;
            border-radius: 40px;
            text-decoration: none;
            font-size: 14px;
            transition: all 0.3s;
        }

        .back-btn:hover {
            background: #00e5ff;
            color: #010714;
        }

        .main-content {
            padding: 40px 0 60px;
        }

        .form-card {
            background: rgba(3,11,39,0.6);
            backdrop-filter: blur(12px);
            border-radius: 25px;
            padding: 35px;
            border: 1px solid rgba(0,229,255,0.15);
        }

        .form-card h2 {
            margin-bottom: 30px;
            text-align: center;
            background: linear-gradient(135deg, #fff, #00e5ff);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .form-group {
            margin-bottom: 25px;
        }

        label {
            display: block;
            margin-bottom: 10px;
            color: #00e5ff;
            font-weight: 500;
        }

        input, textarea, select {
            width: 100%;
            padding: 14px 18px;
            background: rgba(0,0,0,0.5);
            border: 1px solid rgba(0,229,255,0.25);
            border-radius: 15px;
            color: #fff;
            font-size: 15px;
        }

        textarea {
            min-height: 100px;
            resize: vertical;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .btn-save {
            background: linear-gradient(135deg, #00e5ff, #8a2be2);
            color: white;
            padding: 14px 32px;
            border: none;
            border-radius: 40px;
            cursor: pointer;
            font-weight: 600;
            font-size: 16px;
            width: 100%;
            transition: all 0.3s;
        }

        .btn-save:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0,229,255,0.3);
        }

        .msg {
            background: rgba(16,185,129,0.2);
            border: 1px solid #10b981;
            color: #10b981;
            padding: 12px 20px;
            border-radius: 20px;
            margin-bottom: 25px;
        }

        .color-preview {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            margin-top: 10px;
            border: 1px solid rgba(255,255,255,0.2);
        }

        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <header>
        <div class="container">
            <div class="logo">
                <h1><?php echo htmlspecialchars($info['full_name']); ?></h1>
            </div>
            <div class="admin-info">
                <div class="admin-badge">
                    <i class="fas fa-user-shield"></i> <?php echo htmlspecialchars($admin_name); ?>
                </div>
                <a href="admin_dashboard.php" class="back-btn">
                    <i class="fas fa-arrow-left"></i> Takaisin
                </a>
            </div>
        </div>
    </header>

    <div class="main-content">
        <div class="container">
            <div class="form-card">
                <h2><i class="fas fa-sliders-h"></i> Sivuston asetukset</h2>

                <?php if (isset($success)): ?>
                    <div class="msg">
                        <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <div class="form-row">
                        <div class="form-group">
                            <label><i class="fas fa-globe"></i> Sivuston otsikko</label>
                            <input type="text" name="setting_site_title" value="<?php echo htmlspecialchars($settings['site_title'] ?? 'Aziz Rahman Noyan | Portfolio'); ?>">
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-palette"></i> Pääväri</label>
                            <input type="text" name="setting_primary_color" id="primary_color" value="<?php echo htmlspecialchars($settings['primary_color'] ?? '#00e5ff'); ?>">
                            <div class="color-preview" id="colorPreview" style="background: <?php echo htmlspecialchars($settings['primary_color'] ?? '#00e5ff'); ?>"></div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-info-circle"></i> Sivuston kuvaus</label>
                        <textarea name="setting_site_description" rows="3"><?php echo htmlspecialchars($settings['site_description'] ?? 'Verkkokehittäjän portfolio - Aziz Rahman Noyan'); ?></textarea>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label><i class="fas fa-phone"></i> Puhelin (footer)</label>
                            <input type="text" name="setting_contact_phone" value="<?php echo htmlspecialchars($settings['contact_phone'] ?? '+358 41 311 4312'); ?>">
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-envelope"></i> Sähköposti (footer)</label>
                            <input type="email" name="setting_contact_email" value="<?php echo htmlspecialchars($settings['contact_email'] ?? 'matiasmasih@gmail.com'); ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-map-marker-alt"></i> Osoite</label>
                        <input type="text" name="setting_contact_address" value="<?php echo htmlspecialchars($settings['contact_address'] ?? 'Vaahtokuja 5 E50, Vantaa, Suomi'); ?>">
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-copyright"></i> Footer-teksti</label>
                        <input type="text" name="setting_footer_text" value="<?php echo htmlspecialchars($settings['footer_text'] ?? '© 2025 Aziz Rahman Noyan. Kaikki oikeudet pidätetään.'); ?>">
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label><i class="fab fa-github"></i> GitHub URL</label>
                            <input type="url" name="setting_github_url" value="<?php echo htmlspecialchars($settings['github_url'] ?? 'https://github.com/matiasmasih'); ?>">
                        </div>
                        <div class="form-group">
                            <label><i class="fab fa-linkedin"></i> LinkedIn URL</label>
                            <input type="url" name="setting_linkedin_url" value="<?php echo htmlspecialchars($settings['linkedin_url'] ?? 'https://linkedin.com/in/matiasmasih'); ?>">
                        </div>
                    </div>

                    <button type="submit" class="btn-save">
                        <i class="fas fa-save"></i> Tallenna asetukset
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Color preview
        document.getElementById('primary_color').addEventListener('input', function() {
            document.getElementById('colorPreview').style.background = this.value;
        });
    </script>
</body>
</html>

