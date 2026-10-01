k<?php
session_start();
require_once 'config.php';

// Check if logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin_login.php');
    exit();
}

$post_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Get site info
$info_sql = "SELECT * FROM personal_info LIMIT 1";
$info_result = $conn->query($info_sql);
$info = $info_result->fetch_assoc();

$admin_name = $_SESSION['admin_name'] ?? 'Admin';

// Get post data
$post_sql = "SELECT * FROM blog_posts WHERE id = ?";
$post_stmt = $conn->prepare($post_sql);
$post_stmt->bind_param("i", $post_id);
$post_stmt->execute();
$post_result = $post_stmt->get_result();
$post = $post_result->fetch_assoc();

if (!$post) {
    header('Location: admin_dashboard.php');
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = trim($_POST['title'] ?? '');
    $content = $_POST['content'] ?? '';
    $category = trim($_POST['category'] ?? '');
    $status = $_POST['status'] ?? 'published';
    
    // Create slug from title
    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title)));
    $slug = trim($slug, '-');
    
    // Create excerpt
    $excerpt = strip_tags($content);
    $excerpt = substr($excerpt, 0, 150) . '...';
    
    if (empty($title)) {
        $error = 'Otsikko on pakollinen';
    } elseif (empty($content)) {
        $error = 'Sisältö on pakollinen';
    } else {
        $update_sql = "UPDATE blog_posts SET title=?, slug=?, content=?, excerpt=?, category=?, status=? WHERE id=?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("ssssssi", $title, $slug, $content, $excerpt, $category, $status, $post_id);
        
        if ($update_stmt->execute()) {
            $success = 'Blogipostaus päivitetty onnistuneesti!';
            // Refresh post data
            $post['title'] = $title;
            $post['content'] = $content;
            $post['category'] = $category;
            $post['status'] = $status;
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
    <title>Muokkaa blogipostausta | <?php echo htmlspecialchars($info['full_name']); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <!-- Summernote CSS -->
    <link href="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.js"></script>
    <style>
/* ========================================
   COMPLETE FIXED CSS WITH DARK DROPDOWNS
   ======================================== */

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

.view-btn {
    background: rgba(16, 185, 129, 0.12);
    color: #10b981;
    padding: 8px 20px;
    border-radius: 40px;
    text-decoration: none;
    font-size: 14px;
    font-weight: 500;
    border: 1px solid rgba(16, 185, 129, 0.25);
    transition: all 0.3s;
}

.view-btn:hover {
    background: #10b981;
    color: white;
    transform: translateY(-2px);
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

/* ========================================
   DARK INPUTS & SELECTS - FIXED
   ======================================== */
input, select {
    width: 100%;
    padding: 15px 20px;
    background: rgba(0, 0, 0, 0.5);
    border: 1px solid rgba(0, 229, 255, 0.25);
    border-radius: 20px;
    color: #fff;
    font-size: 15px;
    transition: all 0.3s;
    cursor: pointer;
}

input:focus, select:focus {
    outline: none;
    border-color: #00e5ff;
    background: rgba(0, 0, 0, 0.6);
    box-shadow: 0 0 20px rgba(0, 229, 255, 0.2);
    transform: translateY(-2px);
}

/* Dark Dropdown Styling */
select {
    appearance: none;
    -webkit-appearance: none;
    -moz-appearance: none;
    background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%2300e5ff' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><polyline points='6 9 12 15 18 9'></polyline></svg>");
    background-repeat: no-repeat;
    background-position: right 20px center;
    background-size: 16px;
}

select option {
    background: #0a0c15;
    color: #fff;
    padding: 12px 15px;
    border: none;
    border-bottom: 1px solid rgba(0, 229, 255, 0.1);
}

select option:checked {
    background: linear-gradient(135deg, rgba(0, 229, 255, 0.25), rgba(138, 43, 226, 0.25));
    color: #00e5ff;
}

select option:hover {
    background: rgba(0, 229, 255, 0.2);
}

/* For Firefox */
select:-moz-focusring {
    color: transparent;
    text-shadow: 0 0 0 #fff;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 25px;
}

/* Info Cards */
.info-cards {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
    margin-bottom: 35px;
}

.info-card {
    background: rgba(0, 229, 255, 0.05);
    border: 1px solid rgba(0, 229, 255, 0.15);
    border-radius: 20px;
    padding: 15px 20px;
    text-align: center;
    transition: all 0.3s;
}

.info-card:hover {
    background: rgba(0, 229, 255, 0.1);
    border-color: rgba(0, 229, 255, 0.3);
    transform: translateY(-3px);
}

.info-card i {
    font-size: 24px;
    color: #00e5ff;
    margin-bottom: 8px;
    display: inline-block;
}

.info-card .label {
    font-size: 12px;
    color: rgba(255,255,255,0.5);
    margin-bottom: 5px;
}

.info-card .value {
    font-size: 16px;
    font-weight: 600;
    color: #fff;
}

/* Summernote Styling */
.note-editor {
    border-radius: 20px !important;
    overflow: hidden;
    background: rgba(0, 0, 0, 0.3) !important;
    border: 1px solid rgba(0, 229, 255, 0.25) !important;
    transition: all 0.3s;
}

.note-editor:hover {
    border-color: rgba(0, 229, 255, 0.5) !important;
    box-shadow: 0 0 20px rgba(0, 229, 255, 0.1);
}

.note-toolbar {
    background: rgba(0, 0, 0, 0.5) !important;
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
    background: rgba(0, 229, 255, 0.25) !important;
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

.btn-save, .btn-cancel {
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

.btn-cancel {
    background: rgba(149, 165, 166, 0.15);
    color: #95a5a6;
    border: 1px solid rgba(149, 165, 166, 0.3);
    flex: 1;
    text-decoration: none;
    justify-content: center;
}

.btn-cancel:hover {
    background: #95a5a6;
    color: white;
    transform: translateY(-3px);
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
    .info-cards {
        grid-template-columns: 1fr;
        gap: 15px;
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
    .admin-badge, .back-btn, .view-btn {
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
    input, select {
        padding: 12px 16px;
        font-size: 14px;
    }
    select {
        background-position: right 15px center;
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
                <a href="blog_post.php?id=<?php echo $post_id; ?>" target="_blank" class="view-btn">
                    <i class="fas fa-external-link-alt"></i> Näytä
                </a>
            </div>
        </div>
    </header>

    <div class="main-content">
        <div class="container">
            <div class="form-card">
                <div class="form-header">
                    <h2><i class="fas fa-edit"></i> Muokkaa blogipostausta</h2>
                    <p>Päivitä ajatuksiasi, korjaa virheitä ja paranna sisältöäsi</p>
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

                <div class="info-cards">
                    <div class="info-card">
                        <i class="fas fa-hashtag"></i>
                        <div class="label">Postauksen ID</div>
                        <div class="value">#<?php echo $post['id']; ?></div>
                    </div>
                    <div class="info-card">
                        <i class="fas fa-eye"></i>
                        <div class="label">Katselukertoja</div>
                        <div class="value"><?php echo $post['views']; ?></div>
                    </div>
                    <div class="info-card">
                        <i class="fas fa-calendar-alt"></i>
                        <div class="label">Julkaistu</div>
                        <div class="value"><?php echo date('d.m.Y', strtotime($post['published_at'])); ?></div>
                    </div>
                </div>

                <form method="POST" action="" id="editForm">
                    <div class="form-group">
                        <label><i class="fas fa-heading"></i> Otsikko</label>
                        <input type="text" name="title" id="title" placeholder="Kirjoita postauksen otsikko..." required value="<?php echo htmlspecialchars($post['title']); ?>">
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label><i class="fas fa-tag"></i> Kategoria</label>
                            <select name="category">
                                <option value="Web-kehitys" <?php echo $post['category'] == 'Web-kehitys' ? 'selected' : ''; ?>>🌐 Web-kehitys</option>
                                <option value="Urapolku" <?php echo $post['category'] == 'Urapolku' ? 'selected' : ''; ?>>🚀 Urapolku</option>
                                <option value="Oppiminen" <?php echo $post['category'] == 'Oppiminen' ? 'selected' : ''; ?>>📚 Oppiminen</option>
                                <option value="Projektit" <?php echo $post['category'] == 'Projektit' ? 'selected' : ''; ?>>💻 Projektit</option>
                                <option value="Yleinen" <?php echo $post['category'] == 'Yleinen' ? 'selected' : ''; ?>>📝 Yleinen</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-eye"></i> Tila</label>
                            <select name="status">
                                <option value="published" <?php echo $post['status'] == 'published' ? 'selected' : ''; ?>>📢 Julkaistu</option>
                                <option value="draft" <?php echo $post['status'] == 'draft' ? 'selected' : ''; ?>>✏️ Luonnos</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-file-alt"></i> Sisältö</label>
                        <textarea name="content" id="content" required><?php echo htmlspecialchars($post['content']); ?></textarea>
                    </div>

                    <div class="btn-group">
                        <button type="submit" class="btn-save">
                            <i class="fas fa-save"></i> Tallenna muutokset
                        </button>
                        <a href="admin_dashboard.php" class="btn-cancel">
                            <i class="fas fa-times"></i> Peruuta
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        $(document).ready(function() {
            // Initialize Summernote
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
                fontNames: ['Poppins', 'Arial', 'Helvetica', 'Times New Roman']
            });

            // Auto-save indicator
            var typingTimer;
            $('#title, #content').on('input', function() {
                clearTimeout(typingTimer);
                $('.form-header p').html('<i class="fas fa-spinner fa-pulse"></i> Kirjoitetaan...');
                typingTimer = setTimeout(function() {
                    $('.form-header p').html('Päivitä ajatuksiasi, korjaa virheitä ja paranna sisältöäsi');
                }, 1000);
            });

            // Warn before leaving if changes made
            var formChanged = false;
            $('#editForm input, #editForm select, #editForm textarea').on('change', function() {
                formChanged = true;
            });

            window.addEventListener('beforeunload', function(e) {
                if (formChanged) {
                    e.preventDefault();
                    e.returnValue = '';
                }
            });

            $('#editForm').on('submit', function() {
                formChanged = false;
            });
        });
    </script>
</body>
</html>
