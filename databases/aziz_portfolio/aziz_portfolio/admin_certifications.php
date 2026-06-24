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

// Handle delete
if (isset($_GET['delete'])) {
    $delete_id = (int)$_GET['delete'];
    $delete_sql = "DELETE FROM certifications WHERE id = ?";
    $delete_stmt = $conn->prepare($delete_sql);
    $delete_stmt->bind_param("i", $delete_id);
    $delete_stmt->execute();
    header('Location: admin_certifications.php?msg=deleted');
    exit();
}

// Handle add/edit
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = trim($_POST['title'] ?? '');
    $issuer = trim($_POST['issuer'] ?? '');
    $issue_date = $_POST['issue_date'] ?? '';
    $expiry_date = $_POST['expiry_date'] ?? '';
    $credential_url = trim($_POST['credential_url'] ?? '');
    $image_url = trim($_POST['image_url'] ?? '');
    $display_order = (int)($_POST['display_order'] ?? 0);
    
    if (isset($_POST['edit_id']) && $_POST['edit_id'] > 0) {
        // Update existing
        $edit_id = (int)$_POST['edit_id'];
        $update_sql = "UPDATE certifications SET title=?, issuer=?, issue_date=?, expiry_date=?, credential_url=?, image_url=?, display_order=? WHERE id=?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("ssssssii", $title, $issuer, $issue_date, $expiry_date, $credential_url, $image_url, $display_order, $edit_id);
        if ($update_stmt->execute()) {
            $success = 'Sertifikaatti päivitetty onnistuneesti!';
        } else {
            $error = 'Virhe päivityksessä: ' . $conn->error;
        }
    } else {
        // Add new
        $insert_sql = "INSERT INTO certifications (title, issuer, issue_date, expiry_date, credential_url, image_url, display_order) VALUES (?, ?, ?, ?, ?, ?, ?)";
        $insert_stmt = $conn->prepare($insert_sql);
        $insert_stmt->bind_param("ssssssi", $title, $issuer, $issue_date, $expiry_date, $credential_url, $image_url, $display_order);
        if ($insert_stmt->execute()) {
            $success = 'Sertifikaatti lisätty onnistuneesti!';
        } else {
            $error = 'Virhe lisäyksessä: ' . $conn->error;
        }
    }
}

// Get all certifications
$certs_sql = "SELECT * FROM certifications ORDER BY display_order, issue_date DESC";
$certs_result = $conn->query($certs_sql);
$certifications = [];
while($row = $certs_result->fetch_assoc()) {
    $certifications[] = $row;
}
$total_certs = count($certifications);
?>

<!DOCTYPE html>
<html lang="fi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sertifikaatit | <?php echo htmlspecialchars($info['full_name']); ?></title>
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
            background: radial-gradient(circle at 20% 50%, rgba(0, 229, 255, 0.08), transparent 50%),
                        radial-gradient(circle at 80% 80%, rgba(138, 43, 226, 0.08), transparent 50%);
            z-index: -2;
        }

        .container {
            max-width: 1200px;
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
            background: rgba(10, 12, 21, 0.75);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(0, 229, 255, 0.25);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
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
        }

        .back-btn {
            background: rgba(0, 229, 255, 0.12);
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

        .stats-card {
            background: rgba(3, 11, 39, 0.6);
            backdrop-filter: blur(12px);
            border-radius: 20px;
            padding: 20px;
            text-align: center;
            margin-bottom: 30px;
        }

        .stats-card h3 {
            font-size: 32px;
            color: #00e5ff;
        }

        .add-btn {
            background: linear-gradient(135deg, #00e5ff, #8a2be2);
            color: white;
            padding: 12px 24px;
            border-radius: 40px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 25px;
            transition: all 0.3s;
        }

        .add-btn:hover {
            transform: translateY(-2px);
            gap: 15px;
        }

        .form-card {
            background: rgba(3, 11, 39, 0.6);
            backdrop-filter: blur(12px);
            border-radius: 25px;
            padding: 30px;
            margin-bottom: 30px;
            border: 1px solid rgba(0, 229, 255, 0.15);
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            color: #00e5ff;
            font-size: 14px;
        }

        input, textarea {
            width: 100%;
            padding: 12px 15px;
            background: rgba(0, 0, 0, 0.5);
            border: 1px solid rgba(0, 229, 255, 0.25);
            border-radius: 15px;
            color: #fff;
            font-size: 14px;
        }

        input:focus {
            outline: none;
            border-color: #00e5ff;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .btn-save {
            background: linear-gradient(135deg, #00e5ff, #8a2be2);
            color: white;
            padding: 12px 28px;
            border: none;
            border-radius: 30px;
            cursor: pointer;
            font-weight: 600;
        }

        .certs-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 25px;
            margin-top: 30px;
        }

        .cert-card {
            background: rgba(3, 11, 39, 0.6);
            backdrop-filter: blur(12px);
            border-radius: 20px;
            padding: 20px;
            border: 1px solid rgba(0, 229, 255, 0.15);
            transition: all 0.3s;
            position: relative;
        }

        .cert-card:hover {
            transform: translateY(-5px);
            border-color: rgba(0, 229, 255, 0.4);
        }

        .cert-card img {
            width: 60px;
            height: 60px;
            margin-bottom: 15px;
        }

        .cert-card h3 {
            font-size: 18px;
            margin-bottom: 8px;
        }

        .cert-card p {
            color: rgba(255,255,255,0.6);
            font-size: 13px;
            margin-bottom: 5px;
        }

        .cert-card .date {
            color: #00e5ff;
            font-size: 12px;
        }

        .card-actions {
            position: absolute;
            top: 15px;
            right: 15px;
            display: flex;
            gap: 8px;
        }

        .card-actions a {
            background: rgba(0, 0, 0, 0.5);
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            text-decoration: none;
            color: #fff;
        }

        .edit-action {
            background: rgba(52, 152, 219, 0.3);
        }

        .delete-action {
            background: rgba(231, 76, 60, 0.3);
        }

        .msg {
            background: rgba(16, 185, 129, 0.2);
            border: 1px solid #10b981;
            color: #10b981;
            padding: 12px 20px;
            border-radius: 20px;
            margin-bottom: 20px;
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
            <div class="stats-card">
                <i class="fas fa-certificate" style="font-size: 40px; color: #00e5ff;"></i>
                <h3><?php echo $total_certs; ?></h3>
                <p>Sertifikaattia</p>
            </div>

            <?php if (isset($success)): ?>
                <div class="msg"><?php echo $success; ?></div>
            <?php endif; ?>
            <?php if (isset($_GET['msg']) && $_GET['msg'] == 'deleted'): ?>
                <div class="msg">Sertifikaatti poistettu onnistuneesti!</div>
            <?php endif; ?>

            <a href="#" class="add-btn" id="showAddFormBtn">
                <i class="fas fa-plus-circle"></i> Lisää uusi sertifikaatti
            </a>

            <div id="addForm" class="form-card" style="display: none;">
                <h3 style="margin-bottom: 20px; color: #00e5ff;"><i class="fas fa-plus"></i> Lisää uusi sertifikaatti</h3>
                <form method="POST" action="">
                    <div class="form-group">
                        <label>Sertifikaatin nimi</label>
                        <input type="text" name="title" required>
                    </div>
                    <div class="form-group">
                        <label>Myöntäjä</label>
                        <input type="text" name="issuer" required>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Myöntämispäivä</label>
                            <input type="date" name="issue_date">
                        </div>
                        <div class="form-group">
                            <label>Vanhenemispäivä</label>
                            <input type="date" name="expiry_date">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Kuvan URL (logo/kuvake)</label>
                        <input type="url" name="image_url" placeholder="https://...">
                    </div>
                    <div class="form-group">
                        <label>Todistuksen URL (linkki)</label>
                        <input type="url" name="credential_url" placeholder="https://...">
                    </div>
                    <div class="form-group">
                        <label>Järjestysnumero</label>
                        <input type="number" name="display_order" value="0">
                    </div>
                    <button type="submit" class="btn-save"><i class="fas fa-save"></i> Tallenna</button>
                </form>
            </div>

            <div class="certs-grid">
                <?php foreach($certifications as $cert): ?>
                <div class="cert-card">
                    <div class="card-actions">
                        <a href="#" class="edit-action" onclick="editCert(<?php echo htmlspecialchars(json_encode($cert)); ?>)"><i class="fas fa-edit"></i></a>
                        <a href="?delete=<?php echo $cert['id']; ?>" class="delete-action" onclick="return confirm('Haluatko varmasti poistaa tämän sertifikaatin?')"><i class="fas fa-trash"></i></a>
                    </div>
                    <?php if (!empty($cert['image_url'])): ?>
                        <img src="<?php echo htmlspecialchars($cert['image_url']); ?>" alt="<?php echo htmlspecialchars($cert['title']); ?>">
                    <?php else: ?>
                        <i class="fas fa-certificate" style="font-size: 50px; color: #00e5ff;"></i>
                    <?php endif; ?>
                    <h3><?php echo htmlspecialchars($cert['title']); ?></h3>
                    <p><i class="fas fa-building"></i> <?php echo htmlspecialchars($cert['issuer']); ?></p>
                    <?php if (!empty($cert['issue_date'])): ?>
                        <p class="date"><i class="far fa-calendar-alt"></i> <?php echo date('d.m.Y', strtotime($cert['issue_date'])); ?></p>
                    <?php endif; ?>
                    <?php if (!empty($cert['credential_url'])): ?>
                        <a href="<?php echo htmlspecialchars($cert['credential_url']); ?>" target="_blank" style="color: #00e5ff; font-size: 12px; text-decoration: none;">
                            <i class="fas fa-external-link-alt"></i> Näytä todistus
                        </a>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div id="editForm" class="form-card" style="display: none;">
        <h3 style="margin-bottom: 20px; color: #00e5ff;"><i class="fas fa-edit"></i> Muokkaa sertifikaattia</h3>
        <form method="POST" action="" id="editFormElement">
            <input type="hidden" name="edit_id" id="edit_id">
            <div class="form-group">
                <label>Sertifikaatin nimi</label>
                <input type="text" name="title" id="edit_title" required>
            </div>
            <div class="form-group">
                <label>Myöntäjä</label>
                <input type="text" name="issuer" id="edit_issuer" required>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Myöntämispäivä</label>
                    <input type="date" name="issue_date" id="edit_issue_date">
                </div>
                <div class="form-group">
                    <label>Vanhenemispäivä</label>
                    <input type="date" name="expiry_date" id="edit_expiry_date">
                </div>
            </div>
            <div class="form-group">
                <label>Kuvan URL</label>
                <input type="url" name="image_url" id="edit_image_url">
            </div>
            <div class="form-group">
                <label>Todistuksen URL</label>
                <input type="url" name="credential_url" id="edit_credential_url">
            </div>
            <div class="form-group">
                <label>Järjestysnumero</label>
                <input type="number" name="display_order" id="edit_display_order" value="0">
            </div>
            <button type="submit" class="btn-save"><i class="fas fa-save"></i> Päivitä</button>
            <button type="button" class="btn-save" style="background: #95a5a6; margin-left: 10px;" onclick="hideEditForm()"><i class="fas fa-times"></i> Peruuta</button>
        </form>
    </div>

    <script>
        function editCert(cert) {
            document.getElementById('edit_id').value = cert.id;
            document.getElementById('edit_title').value = cert.title;
            document.getElementById('edit_issuer').value = cert.issuer;
            document.getElementById('edit_issue_date').value = cert.issue_date;
            document.getElementById('edit_expiry_date').value = cert.expiry_date;
            document.getElementById('edit_image_url').value = cert.image_url || '';
            document.getElementById('edit_credential_url').value = cert.credential_url || '';
            document.getElementById('edit_display_order').value = cert.display_order || 0;
            document.getElementById('editForm').style.display = 'block';
            document.getElementById('addForm').style.display = 'none';
            window.scrollTo({ top: document.getElementById('editForm').offsetTop - 100, behavior: 'smooth' });
        }
        
        function hideEditForm() {
            document.getElementById('editForm').style.display = 'none';
        }
        
        document.getElementById('showAddFormBtn').addEventListener('click', function(e) {
            e.preventDefault();
            document.getElementById('addForm').style.display = 'block';
            document.getElementById('editForm').style.display = 'none';
            window.scrollTo({ top: document.getElementById('addForm').offsetTop - 100, behavior: 'smooth' });
        });
    </script>
</body>
</html>
