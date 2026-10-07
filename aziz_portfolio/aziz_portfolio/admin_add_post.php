<?php
session_start();
require_once 'config.php';

// Check if logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin_login.php');
    exit();
}

// Get site info
$info_sql = "SELECT * FROM personal_info LIMIT 1";
$info_result = $conn->query($info_sql);
$info = $info_result->fetch_assoc();

$admin_name = $_SESSION['admin_name'] ?? 'Admin';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = trim($_POST['title'] ?? '');
    $content = $_POST['content'] ?? '';
    $category = trim($_POST['category'] ?? '');
    $status = $_POST['status'] ?? 'published';
    
    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title)));
    $slug = trim($slug, '-');
    
    $excerpt = strip_tags($content);
    $excerpt = substr($excerpt, 0, 150) . '...';
    
    if (empty($title)) {
        $error = 'Otsikko on pakollinen';
    } elseif (empty($content)) {
        $error = 'Sisältö on pakollinen';
    } else {
        $insert_sql = "INSERT INTO blog_posts (title, slug, content, excerpt, category, status, published_at) 
                       VALUES (?, ?, ?, ?, ?, ?, NOW())";
        $insert_stmt = $conn->prepare($insert_sql);
        $insert_stmt->bind_param("ssssss", $title, $slug, $content, $excerpt, $category, $status);
        
        if ($insert_stmt->execute()) {
            $success = 'Blogipostaus lisätty onnistuneesti!';
            $_POST = array();
        } else {
            $error = 'Virhe: ' . $conn->error;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Luo uusi blogipostaus | <?php echo htmlspecialchars($info['full_name']); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <!-- Summernote CSS -->
    <link href="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.js"></script>
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
            position: relative;
        }

        /* Animated Background */
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: 
                radial-gradient(circle at 20% 50%, rgba(0, 229, 255, 0.1), transparent 50%),
                radial-gradient(circle at 80% 80%, rgba(138, 43, 226, 0.1), transparent 50%),
                repeating-linear-gradient(45deg, rgba(255,255,255,0.02) 0px, rgba(255,255,255,0.02) 2px, transparent 2px, transparent 8px);
            z-index: -2;
        }

        body::after {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="50" cy="50" r="40" fill="none" stroke="rgba(0,229,255,0.03)" stroke-width="2"/></svg>') repeat;
            opacity: 0.5;
            pointer-events: none;
            z-index: -1;
            animation: rotateBg 60s linear infinite;
        }

        @keyframes rotateBg {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        .container {
            max-width: 1100px;
            margin: 0 auto;
            padding: 0 25px;
        }

        /* Modern Header */
        header {
            position: relative;
            width: 95%;
            max-width: 1400px;
            margin: 20px auto 0;
            padding: 12px 30px;
            border-radius: 80px;
            background: rgba(10, 12, 21, 0.75);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(0, 229, 255, 0.25);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
            animation: slideDown 0.6s ease;
        }

        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-30px); }
            to { opacity: 1; transform: translateY(0); }
        }

        header .container {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo h1 {
            font-size: 26px;
            font-weight: 800;
            background: linear-gradient(135deg, #00e5ff, #8a2be2, #ff6b6b);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
            animation: gradientFlow 3s ease infinite;
            background-size: 200% 200%;
        }

        @keyframes gradientFlow {
            0%, 100% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
        }

        .admin-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .admin-badge {
            background: rgba(0, 229, 255, 0.12);
            padding: 8px 18px;
            border-radius: 40px;
            font-size: 14px;
            border: 1px solid rgba(0, 229, 255, 0.25);
            backdrop-filter: blur(5px);
            transition: all 0.3s;
        }

        .admin-badge i {
            color: #00e5ff;
            margin-right: 8px;
        }

        .back-btn {
            background: rgba(0, 229, 255, 0.12);
            color: #00e5ff;
            padding: 8px 20px;
            border-radius: 40px;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            border: 1px solid rgba(0, 229, 255, 0.25);
            transition: all 0.3s;
        }

        .back-btn:hover {
            background: #00e5ff;
            color: #010714;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 229, 255, 0.3);
        }

        /* Main Content */
        .main-content {
            padding: 40px 0 60px;
            animation: fadeInUp 0.8s ease;
        }

        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Form Card */
        .form-card {
            background: rgba(3, 11, 39, 0.55);
            backdrop-filter: blur(20px);
            border-radius: 32px;
            padding: 45px;
            border: 1px solid rgba(0, 229, 255, 0.2);
            box-shadow: 0 25px 45px rgba(0, 0, 0, 0.3);
            transition: all 0.3s;
        }

        .form-card:hover {
            border-color: rgba(0, 229, 255, 0.4);
            box-shadow: 0 30px 55px rgba(0, 229, 255, 0.1);
        }

        .form-header {
            text-align: center;
            margin-bottom: 40px;
            position: relative;
        }

        .form-header h2 {
            font-size: 36px;
            font-weight: 800;
            background: linear-gradient(135deg, #fff, #00e5ff);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 12px;
        }

        .form-header p {
            color: rgba(255,255,255,0.6);
            font-size: 15px;
            letter-spacing: 0.5px;
        }

        .form-header::after {
            content: '';
            position: absolute;
            bottom: -15px;
            left: 50%;
            transform: translateX(-50%);
            width: 80px;
            height: 3px;
            background: linear-gradient(90deg, #00e5ff, #8a2be2);
            border-radius: 3px;
        }

        /* Form Groups */
        .form-group {
            margin-bottom: 28px;
        }

        label {
            display: block;
            margin-bottom: 12px;
            font-weight: 600;
            color: #00e5ff;
            font-size: 14px;
            letter-spacing: 0.5px;
        }

        label i {
            margin-right: 10px;
            font-size: 14px;
        }

        input, select {
            width: 100%;
            padding: 15px 20px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(0, 229, 255, 0.2);
            border-radius: 20px;
            color: #fff;
            font-size: 15px;
            transition: all 0.3s;
        }

        input:focus, select:focus {
            outline: none;
            border-color: #00e5ff;
            background: rgba(255, 255, 255, 0.08);
            box-shadow: 0 0 20px rgba(0, 229, 255, 0.2);
            transform: translateY(-2px);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
        }

        /* Summernote Styling */
        .note-editor {
            border-radius: 20px !important;
            overflow: hidden;
            background: rgba(255, 255, 255, 0.03) !important;
            border: 1px solid rgba(0, 229, 255, 0.2) !important;
            transition: all 0.3s;
        }

        .note-editor:hover {
            border-color: rgba(0, 229, 255, 0.5) !important;
            box-shadow: 0 0 20px rgba(0, 229, 255, 0.1);
        }

        .note-toolbar {
            background: rgba(0, 0, 0, 0.4) !important;
            border-bottom: 1px solid rgba(0, 229, 255, 0.2) !important;
            padding: 12px !important;
        }

        .note-btn {
            background: rgba(255, 255, 255, 0.08) !important;
            color: #fff !important;
            border: 1px solid rgba(0, 229, 255, 0.2) !important;
            border-radius: 12px !important;
            transition: all 0.2s;
        }

        .note-btn:hover {
            background: rgba(0, 229, 255, 0.2) !important;
            border-color: #00e5ff !important;
        }

        .note-editable {
            background: rgba(0, 0, 0, 0.2) !important;
            color: #fff !important;
            min-height: 400px !important;
            font-size: 15px;
            line-height: 1.8;
        }

        .note-editable p {
            color: rgba(255,255,255,0.9);
        }

        .note-editable h2 {
            color: #00e5ff;
            margin-top: 20px;
            margin-bottom: 15px;
        }

        .note-editable h3 {
            color: #8a2be2;
        }

        .note-editable ul, .note-editable ol {
            color: rgba(255,255,255,0.9);
            margin-left: 20px;
        }

        /* Buttons */
        .btn-group {
            display: flex;
            gap: 20px;
            margin-top: 35px;
        }

        .btn-save, .btn-reset {
            padding: 16px 32px;
            border-radius: 60px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 12px;
            border: none;
        }

        .btn-save {
            background: linear-gradient(135deg, #00e5ff, #8a2be2);
            color: white;
            flex: 2;
            box-shadow: 0 5px 15px rgba(0, 229, 255, 0.3);
        }

        .btn-save:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 30px rgba(0, 229, 255, 0.4);
            gap: 15px;
        }

        .btn-reset {
            background: rgba(231, 76, 60, 0.15);
            color: #e74c3c;
            border: 1px solid rgba(231, 76, 60, 0.3);
            flex: 1;
        }

        .btn-reset:hover {
            background: #e74c3c;
            color: white;
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(231, 76, 60, 0.3);
        }

        /* Messages */
        .error, .success {
            padding: 16px 22px;
            border-radius: 20px;
            margin-bottom: 28px;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: slideIn 0.5s ease;
        }

        @keyframes slideIn {
            from { opacity: 0; transform: translateX(-30px); }
            to { opacity: 1; transform: translateX(0); }
        }

        .error {
            background: rgba(231, 76, 60, 0.15);
            border: 1px solid #e74c3c;
            color: #e74c3c;
        }

        .success {
            background: rgba(16, 185, 129, 0.15);
            border: 1px solid #10b981;
            color: #10b981;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            .btn-group {
                flex-direction: column;
            }
            .form-card {
                padding: 30px 25px;
            }
            .form-header h2 {
                font-size: 28px;
            }
            header {
                padding: 10px 20px;
            }
            .logo h1 {
                font-size: 20px;
            }
            .admin-badge, .back-btn {
                padding: 6px 12px;
                font-size: 12px;
            }
        }

        @media (max-width: 480px) {
            .admin-info {
                gap: 8px;
            }
            .admin-badge {
                display: none;
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
                <div class="form-header">
                    <h2><i class="fas fa-feather-alt"></i> Luo uusi blogipostaus</h2>
                    <p>Kirjoita ajatuksiasi, jaa oppimiasi asioita ja inspiroi lukijoitasi</p>
                </div>

                <?php if ($error): ?>
                    <div class="error">
                        <i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="success">
                        <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" id="postForm">
                    <div class="form-group">
                        <label><i class="fas fa-heading"></i> Otsikko</label>
                        <input type="text" name="title" id="title" placeholder="Kirjoita postauksen otsikko..." required value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>">
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label><i class="fas fa-tag"></i> Kategoria</label>
                            <select name="category">
                                <option value="Web-kehitys">🌐 Web-kehitys</option>
                                <option value="Urapolku">🚀 Urapolku</option>
                                <option value="Oppiminen">📚 Oppiminen</option>
                                <option value="Projektit">💻 Projektit</option>
                                <option value="Yleinen">📝 Yleinen</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-eye"></i> Tila</label>
                            <select name="status">
                                <option value="published">📢 Julkaistu</option>
                                <option value="draft">✏️ Luonnos</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-file-alt"></i> Sisältö</label>
                        <textarea name="content" id="content" required><?php echo htmlspecialchars($_POST['content'] ?? ''); ?></textarea>
                    </div>

                    <div class="btn-group">
                        <button type="submit" class="btn-save">
                            <i class="fas fa-save"></i> Tallenna ja julkaise
                        </button>
                        <button type="button" class="btn-reset" id="resetBtn">
                            <i class="fas fa-undo-alt"></i> Tyhjennä
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        $(document).ready(function() {
            // Initialize Summernote with custom settings
            $('#content').summernote({
                height: 450,
                placeholder: 'Kirjoita blogipostauksen sisältö tähän...',
                toolbar: [
                    ['style', ['style']],
                    ['font', ['bold', 'italic', 'underline', 'clear']],
                    ['fontname', ['fontname']],
                    ['fontsize', ['fontsize']],
                    ['color', ['color']],
                    ['para', ['ul', 'ol', 'paragraph']],
                    ['table', ['table']],
                    ['insert', ['link', 'picture']],
                    ['view', ['fullscreen', 'codeview', 'help']]
                ],
                fontSizes: ['8', '9', '10', '11', '12', '14', '16', '18', '20', '24', '28', '32', '36'],
                fontNames: ['Poppins', 'Arial', 'Helvetica', 'Times New Roman'],
                callbacks: {
                    onInit: function() {
                        console.log('Summernote ready');
                    }
                }
            });

            // Reset form
            $('#resetBtn').click(function() {
                if (confirm('Haluatko varmasti tyhjentää lomakkeen? Kaikki kirjoittamasi sisältö katoaa.')) {
                    $('#title').val('');
                    $('#content').summernote('code', '');
                    $('#title').focus();
                }
            });

            // Live character count for title
            $('#title').on('input', function() {
                var count = $(this).val().length;
                if (count < 10) {
                    $(this).css('border-color', 'rgba(245, 158, 11, 0.5)');
                } else if (count < 60) {
                    $(this).css('border-color', 'rgba(16, 185, 129, 0.5)');
                } else {
                    $(this).css('border-color', 'rgba(0, 229, 255, 0.5)');
                }
            });
        });
    </script>
</body>
</html>
