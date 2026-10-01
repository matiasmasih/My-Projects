<?php
session_start();
require_once 'connection.php';

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);

    if (empty($email)) {
        $errors[] = "Sähköposti on pakollinen";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Syötä kelvollinen sähköpostiosoite";
    }

    if (empty($errors)) {
        try {
            // Check if email exists
            $sql = "SELECT id, etunimi, email FROM jasenet WHERE email = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();

            if ($user) {
                // Generate password reset token
                $token = bin2hex(random_bytes(32));
                $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

                // Check if columns exist, if not, add them
                $check_columns_sql = "SHOW COLUMNS FROM jasenet LIKE 'reset_token'";
                $check_result = $conn->query($check_columns_sql);

                if ($check_result->num_rows == 0) {
                    // Add missing columns
                    $alter_sql = "ALTER TABLE jasenet
                                 ADD COLUMN reset_token VARCHAR(64) NULL,
                                 ADD COLUMN reset_expires DATETIME NULL,
                                 ADD INDEX idx_reset_token (reset_token)";
                    $conn->query($alter_sql);
                }

                // Store token in database
                $update_sql = "UPDATE jasenet SET reset_token = ?, reset_expires = ? WHERE id = ?";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bind_param("ssi", $token, $expires, $user['id']);

                if ($update_stmt->execute()) {
                    // In a real application, send email here
                    // For demo purposes, we'll show a message with the reset link
                    $reset_link = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/reset_password.php?token=" . $token;

                    // Store in session for demo display
                    $_SESSION['demo_reset_link'] = $reset_link;
                    $_SESSION['demo_email'] = $email;
                    $_SESSION['demo_user_name'] = $user['etunimi'];

                    $success = true;
                    $success_message = "Salasanan palautuslinkki on lähetetty sähköpostiisi. Tarkista sähköpostisi jatkaaksesi.";
                } else {
                    $errors[] = "Tietokantavirhe. Yritä uudelleen.";
                }
            } else {
                // Don't reveal if email exists for security
                $success = true;
                $success_message = "Jos sähköpostiosoite löytyy järjestelmästämme, lähetimme palautuslinkin.";
            }
        } catch (Exception $e) {
            $errors[] = "Tietokantavirhe: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Unohdin salasanani - Kirjasto</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', sans-serif;
        }

        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(rgba(15, 23, 42, 0.2), rgba(15, 23, 42, 0.2)),
                        url('https://images.unsplash.com/photo-1521587760476-6c12a4b040da?ixlib=rb-4.0.3&auto=format&fit=crop&w=2000&q=80');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            padding: 20px;
        }

        /* Main container */
        .forgot-card {
            max-width: 1000px;
            width: 100%;
            background: white;
            border-radius: 40px;
            box-shadow: 0 30px 60px rgba(0, 0, 0, 0.3);
            display: flex;
            overflow: hidden;
            animation: fadeIn 0.8s ease;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: scale(0.95);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }

        /* Left side - Brand */
        .brand-side {
            flex: 1;
            background: linear-gradient(145deg, #1e2b4a 0%, #2c3e67 100%);
            padding: 60px 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }

        .brand-side::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" opacity="0.1"><circle cx="50" cy="50" r="40" fill="none" stroke="white" stroke-width="2"/><circle cx="50" cy="50" r="20" fill="none" stroke="white" stroke-width="2"/></svg>');
            background-size: 100px;
        }

        .brand-content {
            position: relative;
            z-index: 2;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 50px;
        }

        .logo-icon {
            width: 60px;
            height: 60px;
            background: rgba(255, 255, 255, 0.15);
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            color: white;
            backdrop-filter: blur(10px);
        }

        .logo-text h2 {
            color: white;
            font-weight: 700;
            font-size: 1.8rem;
            margin-bottom: 5px;
        }

        .logo-text p {
            color: rgba(255, 255, 255, 0.7);
            font-size: 0.9rem;
        }

        .welcome-message {
            margin-bottom: 40px;
        }

        .welcome-message h1 {
            color: white;
            font-size: 2.5rem;
            font-weight: 700;
            line-height: 1.2;
            margin-bottom: 20px;
        }

        .welcome-message p {
            color: rgba(255, 255, 255, 0.8);
            font-size: 1rem;
            line-height: 1.6;
            max-width: 300px;
        }

        .feature-list {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .feature-item {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .feature-icon {
            width: 45px;
            height: 45px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.3rem;
            backdrop-filter: blur(10px);
        }

        .feature-text {
            color: white;
        }

        .feature-text h4 {
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 4px;
        }

        .feature-text p {
            font-size: 0.8rem;
            color: rgba(255, 255, 255, 0.7);
        }

        /* Right side - Form */
        .form-side {
            flex: 1.2;
            padding: 60px 50px;
            background: white;
            overflow-y: auto;
            max-height: 800px;
        }

        .form-side::-webkit-scrollbar {
            width: 6px;
        }

        .form-side::-webkit-scrollbar-track {
            background: #f1f5f9;
        }

        .form-side::-webkit-scrollbar-thumb {
            background: #94a3b8;
            border-radius: 3px;
        }

        .form-side::-webkit-scrollbar-thumb:hover {
            background: #64748b;
        }

        .form-header {
            margin-bottom: 30px;
        }

        .form-header h2 {
            font-size: 2rem;
            color: #0f172a;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .form-header p {
            color: #64748b;
            font-size: 0.95rem;
        }

        /* Instructions */
        .instructions {
            background: linear-gradient(135deg, #f8fafc, #f1f5f9);
            padding: 20px;
            border-radius: 16px;
            margin-bottom: 30px;
            border-left: 4px solid #2c3e67;
        }

        .instructions h4 {
            color: #1e293b;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 1rem;
        }

        .instructions ul {
            padding-left: 20px;
            color: #475569;
            font-size: 0.9rem;
        }

        .instructions li {
            margin-bottom: 8px;
            line-height: 1.5;
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-label {
            display: block;
            margin-bottom: 6px;
            color: #334155;
            font-weight: 500;
            font-size: 0.9rem;
        }

        .input-field {
            position: relative;
        }

        .input-field i {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 1rem;
            transition: color 0.2s;
            z-index: 1;
        }

        .input-field input {
            width: 100%;
            padding: 14px 16px 14px 48px;
            border: 2px solid #e2e8f0;
            border-radius: 14px;
            font-size: 0.95rem;
            transition: all 0.3s;
            background: white;
        }

        .input-field input:focus {
            outline: none;
            border-color: #2c3e67;
            box-shadow: 0 4px 12px rgba(44, 62, 103, 0.15);
        }

        .input-field input:focus + i {
            color: #2c3e67;
        }

        /* Buttons */
        .btn {
            width: 100%;
            padding: 14px;
            border: none;
            border-radius: 14px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            text-decoration: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, #1e2b4a, #2c3e67);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(44, 62, 103, 0.3);
        }

        .btn-secondary {
            background: #f1f5f9;
            color: #334155;
        }

        .btn-secondary:hover {
            background: #e2e8f0;
            transform: translateY(-2px);
        }

        .btn.loading {
            opacity: 0.7;
            pointer-events: none;
        }

        .btn.loading i {
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        /* Action buttons */
        .action-buttons {
            display: flex;
            gap: 15px;
            margin-top: 10px;
        }

        .action-buttons .btn {
            flex: 1;
        }

        /* Alert messages */
        .alert {
            padding: 15px 18px;
            border-radius: 12px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: slideDown 0.3s ease;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .alert-error {
            background: #fef2f2;
            border-left: 4px solid #ef4444;
            color: #7f1d1d;
        }

        .alert-success {
            background: #f0fdf4;
            border-left: 4px solid #22c55e;
            color: #14532d;
        }

        .alert-info {
            background: #eff6ff;
            border-left: 4px solid #3b82f6;
            color: #1e3a8a;
        }

        /* User greeting */
        .user-greeting {
            text-align: center;
            padding: 15px;
            background: linear-gradient(135deg, #eff6ff, #ffffff);
            border-radius: 12px;
            margin-bottom: 25px;
            border: 1px solid #e2e8f0;
        }

        .user-greeting h4 {
            color: #1e293b;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-bottom: 5px;
        }

        /* Demo reset link */
        .demo-reset-link {
            background: linear-gradient(135deg, #f8fafc, #f1f5f9);
            padding: 20px;
            border-radius: 16px;
            margin-top: 30px;
            border: 2px dashed #2c3e67;
        }

        .demo-reset-link h4 {
            color: #1e293b;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .reset-link {
            word-break: break-all;
            padding: 12px;
            background: white;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            font-family: monospace;
            font-size: 0.85rem;
            margin: 10px 0;
            color: #334155;
        }

        .note {
            color: #64748b;
            font-size: 0.85rem;
            margin-top: 10px;
            padding: 10px;
            background: #fff7ed;
            border-radius: 8px;
        }

        /* Back link */
        .back-link {
            text-align: center;
            margin-top: 30px;
            padding-top: 25px;
            border-top: 1px solid #e2e8f0;
            color: #64748b;
        }

        .back-link a {
            color: #2c3e67;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .back-link a:hover {
            color: #1e2b4a;
            text-decoration: none;
        }

        .register-link {
            margin-top: 10px;
            font-size: 0.9rem;
        }

        .register-link a {
            color: #2c3e67;
            text-decoration: none;
            font-weight: 500;
        }

        .register-link a:hover {
            text-decoration: none;
        }

        /* Responsive */
        @media (max-width: 900px) {
            .forgot-card {
                flex-direction: column;
                max-width: 500px;
            }

            .brand-side {
                padding: 40px 30px;
            }

            .welcome-message h1 {
                font-size: 2rem;
            }

            .form-side {
                padding: 40px 30px;
            }

            .action-buttons {
                flex-direction: column;
            }
        }

        @media (max-width: 480px) {
            body {
                padding: 10px;
            }

            .welcome-message h1 {
                font-size: 1.8rem;
            }

            .form-side {
                padding: 30px 20px;
            }
        }
    </style>
</head>
<body>
    <div class="forgot-card">
        <!-- Left side - Branding -->
        <div class="brand-side">
            <div class="brand-content">
                <div class="logo">
                    <div class="logo-icon">
                        <i class="fas fa-key"></i>
                    </div>
                    <div class="logo-text">
                        <h2>Kirjasto</h2>
                        <p>lukemisen iloa</p>
                    </div>
                </div>

                <div class="welcome-message">
                    <h1>Salasana<br>hukassa?</h1>
                    <p>Autamme sinua palauttamaan pääsyn tilillesi</p>
                </div>

                <div class="feature-list">
                    <div class="feature-item">
                        <div class="feature-icon">
                            <i class="fas fa-shield-alt"></i>
                        </div>
                        <div class="feature-text">
                            <h4>Turvallinen palautus</h4>
                            <p>Salattu linkki, voimassa 1 tunti</p>
                        </div>
                    </div>
                    <div class="feature-item">
                        <div class="feature-icon">
                            <i class="fas fa-envelope"></i>
                        </div>
                        <div class="feature-text">
                            <h4>Sähköpostivarmennus</h4>
                            <p>Linkki lähetetään sähköpostiisi</p>
                        </div>
                    </div>
                    <div class="feature-item">
                        <div class="feature-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="feature-text">
                            <h4>Nopea prosessi</h4>
                            <p>Palauta salasana hetkessä</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right side - Form -->
        <div class="form-side">
            <div class="form-header">
                <h2>Palauta salasana</h2>
                <p>Anna sähköpostiosoitteesi, niin lähetämme palautuslinkin</p>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <div>
                        <?php foreach ($errors as $error): ?>
                            <p><?php echo $error; ?></p>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <?php if (isset($_SESSION['demo_user_name'])): ?>
                    <div class="user-greeting">
                        <h4><i class="fas fa-user-circle"></i> Hei <?php echo htmlspecialchars($_SESSION['demo_user_name']); ?>!</h4>
                        <p>Salasanan palautuslinkki on lähetetty</p>
                    </div>
                <?php endif; ?>

                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <div>
                        <p><strong>Onnistui!</strong></p>
                        <p><?php echo $success_message; ?></p>
                    </div>
                </div>

                <?php if (isset($_SESSION['demo_reset_link'])): ?>
                    <div class="demo-reset-link">
                        <h4><i class="fas fa-code"></i> Demo: Palautuslinkki</h4>
                        <p>Oikeassa ympäristössä tämä linkki lähetettäisiin sähköpostilla:</p>
                        <p><strong><?php echo htmlspecialchars($_SESSION['demo_email']); ?></strong></p>
                        <div class="reset-link">
                            <?php echo htmlspecialchars($_SESSION['demo_reset_link']); ?>
                        </div>
                        <div class="note">
                            <i class="fas fa-info-circle"></i> Tämä on demo. Oikeassa sovelluksessa linkki lähetetään sähköpostitse.
                        </div>
                    </div>

                    <div class="action-buttons">
                        <a href="<?php echo $_SESSION['demo_reset_link']; ?>" class="btn btn-primary" target="_blank">
                            <i class="fas fa-external-link-alt"></i> Testaa linkkiä
                        </a>
                        <button onclick="copyResetLink()" class="btn btn-secondary">
                            <i class="fas fa-copy"></i> Kopioi
                        </button>
                    </div>
                <?php endif; ?>

                <div class="back-link">
                    <a href="login.php">
                        <i class="fas fa-arrow-left"></i> Palaa kirjautumissivulle
                    </a>
                </div>

                <?php
                // Clear demo data after display
                unset($_SESSION['demo_reset_link']);
                unset($_SESSION['demo_email']);
                unset($_SESSION['demo_user_name']);
                ?>

            <?php else: ?>

                <div class="instructions">
                    <h4><i class="fas fa-info-circle"></i> Näin se toimii:</h4>
                    <ul>
                        <li>Anna rekisteröity sähköpostiosoitteesi</li>
                        <li>Saat sähköpostiisi turvallisen palautuslinkin</li>
                        <li>Linkki on voimassa 1 tunnin</li>
                        <li>Klikkaa linkkiä ja aseta uusi salasana</li>
                    </ul>
                </div>

                <form method="POST" action="" id="resetForm">
                    <div class="form-group">
                        <label class="form-label">Sähköpostiosoite</label>
                        <div class="input-field">
                            <i class="fas fa-envelope"></i>
                            <input type="email" 
                                   name="email" 
                                   value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                                   placeholder="etunimi.sukunimi@esimerkki.fi" 
                                   required>
                        </div>
                    </div>

                    <div class="action-buttons">
                        <button type="submit" class="btn btn-primary" id="submitBtn">
                            <i class="fas fa-paper-plane"></i>
                            Lähetä palautuslinkki
                        </button>
                        <a href="login.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i>
                            Peruuta
                        </a>
                    </div>
                </form>

                <div class="back-link">
                    <p>
                        <a href="login.php">
                            <i class="fas fa-sign-in-alt"></i> Kirjaudu sisään
                        </a>
                    </p>
                    <p class="register-link">
                        Eikö sinulla ole tiliä?
                        <a href="register.php">Rekisteröidy tästä</a>
                    </p>
                </div>

            <?php endif; ?>
        </div>
    </div>

    <script>
        // Form submission with loading state
        const resetForm = document.getElementById('resetForm');
        const submitBtn = document.getElementById('submitBtn');

        if (resetForm) {
            resetForm.addEventListener('submit', function(e) {
                submitBtn.classList.add('loading');
                submitBtn.innerHTML = '<i class="fas fa-spinner"></i><span>Lähetetään...</span>';
                submitBtn.disabled = true;
            });
        }

        // Copy reset link to clipboard
        function copyResetLink() {
            const resetLink = document.querySelector('.reset-link');
            if (!resetLink) return;

            const text = resetLink.textContent.trim();

            navigator.clipboard.writeText(text).then(function() {
                const button = document.querySelector('.btn-secondary');
                const originalText = button.innerHTML;
                button.innerHTML = '<i class="fas fa-check"></i> Kopioitu!';
                button.classList.add('btn-primary');
                button.classList.remove('btn-secondary');

                setTimeout(() => {
                    button.innerHTML = originalText;
                    button.classList.remove('btn-primary');
                    button.classList.add('btn-secondary');
                }, 2000);
            }).catch(function(err) {
                console.error('Kopiointi epäonnistui: ', err);
                alert('Kopiointi epäonnistui. Kopioi linkki manuaalisesti.');
            });
        }

        // Auto-focus email field
        document.addEventListener('DOMContentLoaded', function() {
            const emailInput = document.querySelector('input[name="email"]');
            if (emailInput) {
                emailInput.focus();
            }
        });

        // Enter key to submit
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                const submitBtn = document.getElementById('submitBtn');
                if (submitBtn && !submitBtn.disabled) {
                    const focused = document.activeElement;
                    if (focused.tagName === 'INPUT') {
                        resetForm?.requestSubmit();
                    }
                }
            }
        });
    </script>
</body>
</html>
