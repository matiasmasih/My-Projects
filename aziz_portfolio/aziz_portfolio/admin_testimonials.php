<?php
session_start();
require_once 'config.php';

// Check if logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin_login.php');
    exit();
}

$info_sql = "SELECT * FROM personal_info LIMIT 1";
$info_result = $conn->query($info_sql);
$info = $info_result->fetch_assoc();
$admin_name = $_SESSION['admin_name'] ?? 'Admin';

// Handle delete
if (isset($_GET['delete'])) {
    $delete_id = (int)$_GET['delete'];
    $conn->query("DELETE FROM testimonials WHERE id = $delete_id");
    header('Location: admin_testimonials.php?msg=deleted');
    exit();
}

// Handle add/edit
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $client_name = trim($_POST['client_name'] ?? '');
    $client_position = trim($_POST['client_position'] ?? '');
    $testimonial = trim($_POST['testimonial'] ?? '');
    $rating = (int)($_POST['rating'] ?? 5);
    $is_visible = isset($_POST['is_visible']) ? 1 : 0;
    $display_order = (int)($_POST['display_order'] ?? 0);

    if (isset($_POST['edit_id']) && $_POST['edit_id'] > 0) {
        $edit_id = (int)$_POST['edit_id'];
        $conn->query("UPDATE testimonials SET client_name='$client_name', client_position='$client_position', testimonial='$testimonial', rating=$rating, is_visible=$is_visible, display_order=$display_order WHERE id=$edit_id");
        $success = 'Arvostelu päivitetty!';
    } else {
        $conn->query("INSERT INTO testimonials (client_name, client_position, testimonial, rating, is_visible, display_order) VALUES ('$client_name', '$client_position', '$testimonial', $rating, $is_visible, $display_order)");
        $success = 'Arvostelu lisätty!';
    }
}

$testimonials = $conn->query("SELECT * FROM testimonials ORDER BY display_order, id DESC");
$total = $testimonials->num_rows;
?>

<!DOCTYPE html>
<html lang="fi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Asiakaspalautteet | <?php echo htmlspecialchars($info['full_name']); ?></title>
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
            background: rgba(10,12,21,0.75);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(0,229,255,0.25);
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
            flex-wrap: wrap;
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

        .stats-card {
            background: rgba(3,11,39,0.6);
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
            background: rgba(3,11,39,0.6);
            backdrop-filter: blur(12px);
            border-radius: 25px;
            padding: 30px;
            margin-bottom: 30px;
            border: 1px solid rgba(0,229,255,0.15);
            display: none;
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

        input, textarea, select {
            width: 100%;
            padding: 12px 15px;
            background: rgba(0,0,0,0.5);
            border: 1px solid rgba(0,229,255,0.25);
            border-radius: 15px;
            color: #fff;
            font-size: 14px;
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
            padding: 12px 28px;
            border: none;
            border-radius: 30px;
            cursor: pointer;
            font-weight: 600;
        }

        .testimonials-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 25px;
            margin-top: 30px;
        }

        .testimonial-card {
            background: rgba(3,11,39,0.6);
            backdrop-filter: blur(12px);
            border-radius: 20px;
            padding: 20px;
            border: 1px solid rgba(0,229,255,0.15);
            transition: all 0.3s;
            position: relative;
        }

        .testimonial-card:hover {
            transform: translateY(-5px);
            border-color: rgba(0,229,255,0.4);
        }

        .rating {
            color: #f59e0b;
            margin-bottom: 10px;
        }

        .testimonial-text {
            font-style: italic;
            color: rgba(255,255,255,0.8);
            margin-bottom: 15px;
            line-height: 1.6;
        }

        .client-name {
            font-weight: 600;
            color: #00e5ff;
        }

        .client-position {
            font-size: 12px;
            color: rgba(255,255,255,0.5);
        }

        .card-actions {
            position: absolute;
            top: 15px;
            right: 15px;
            display: flex;
            gap: 8px;
        }

        .edit-action, .delete-action {
            background: rgba(0,0,0,0.5);
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            text-decoration: none;
            color: #fff;
        }

        .edit-action {
            background: rgba(52,152,219,0.3);
        }

        .delete-action {
            background: rgba(231,76,60,0.3);
        }

        .visible-badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 11px;
            margin-top: 10px;
        }

        .visible-yes {
            background: rgba(16,185,129,0.2);
            color: #10b981;
        }

        .visible-no {
            background: rgba(239,68,68,0.2);
            color: #ef4444;
        }

        .msg {
            background: rgba(16,185,129,0.2);
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
                <i class="fas fa-star" style="font-size: 40px; color: #00e5ff;"></i>
                <h3><?php echo $total; ?></h3>
                <p>Asiakaspalautetta</p>
            </div>

            <?php if (isset($_GET['msg']) && $_GET['msg'] == 'deleted'): ?>
                <div class="msg">Arvostelu poistettu!</div>
            <?php endif; ?>
            <?php if (isset($success)): ?>
                <div class="msg"><?php echo $success; ?></div>
            <?php endif; ?>

            <a href="#" class="add-btn" id="showAddFormBtn">
                <i class="fas fa-plus-circle"></i> Lisää uusi palaute
            </a>

            <div id="addForm" class="form-card">
                <h3 style="margin-bottom: 20px; color: #00e5ff;"><i class="fas fa-plus"></i> Lisää uusi palaute</h3>
                <form method="POST" action="">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Asiakkaan nimi</label>
                            <input type="text" name="client_name" required>
                        </div>
                        <div class="form-group">
                            <label>Tehtävä / Yritys</label>
                            <input type="text" name="client_position">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Arvostelu (1-5 tähteä)</label>
                        <select name="rating">
                            <option value="5">⭐⭐⭐⭐⭐ 5 tähteä</option>
                            <option value="4">⭐⭐⭐⭐ 4 tähteä</option>
                            <option value="3">⭐⭐⭐ 3 tähteä</option>
                            <option value="2">⭐⭐ 2 tähteä</option>
                            <option value="1">⭐ 1 tähti</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Palaute</label>
                        <textarea name="testimonial" placeholder="Kirjoita asiakkaan palaute..." required></textarea>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Järjestysnumero</label>
                            <input type="number" name="display_order" value="0">
                        </div>
                        <div class="form-group">
                            <label>
                                <input type="checkbox" name="is_visible" checked> Näkyvä verkkosivulla
                            </label>
                        </div>
                    </div>
                    <button type="submit" class="btn-save"><i class="fas fa-save"></i> Tallenna</button>
                </form>
            </div>

            <div class="testimonials-grid">
                <?php while($row = $testimonials->fetch_assoc()): ?>
                <div class="testimonial-card">
                    <div class="card-actions">
                        <a href="#" class="edit-action" onclick='editTestimonial(<?php echo json_encode($row); ?>)'><i class="fas fa-edit"></i></a>
                        <a href="?delete=<?php echo $row['id']; ?>" class="delete-action" onclick="return confirm('Poistetaanko?')"><i class="fas fa-trash"></i></a>
                    </div>
                    <div class="rating">
                        <?php for($i=1;$i<=5;$i++): ?>
                            <i class="fas fa-star<?php echo $i <= $row['rating'] ? '' : '-o'; ?>"></i>
                        <?php endfor; ?>
                    </div>
                    <div class="testimonial-text">"<?php echo htmlspecialchars($row['testimonial']); ?>"</div>
                    <div class="client-name"><?php echo htmlspecialchars($row['client_name']); ?></div>
                    <div class="client-position"><?php echo htmlspecialchars($row['client_position']); ?></div>
                    <span class="visible-badge visible-<?php echo $row['is_visible'] ? 'yes' : 'no'; ?>">
                        <?php echo $row['is_visible'] ? '✓ Näkyvä' : '✗ Piilotettu'; ?>
                    </span>
                </div>
                <?php endwhile; ?>
            </div>

            <div id="editForm" class="form-card">
                <h3 style="margin-bottom: 20px; color: #00e5ff;"><i class="fas fa-edit"></i> Muokkaa palautetta</h3>
                <form method="POST" action="" id="editFormElement">
                    <input type="hidden" name="edit_id" id="edit_id">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Asiakkaan nimi</label>
                            <input type="text" name="client_name" id="edit_name" required>
                        </div>
                        <div class="form-group">
                            <label>Tehtävä / Yritys</label>
                            <input type="text" name="client_position" id="edit_position">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Arvostelu</label>
                        <select name="rating" id="edit_rating">
                            <option value="5">⭐⭐⭐⭐⭐</option>
                            <option value="4">⭐⭐⭐⭐</option>
                            <option value="3">⭐⭐⭐</option>
                            <option value="2">⭐⭐</option>
                            <option value="1">⭐</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Palaute</label>
                        <textarea name="testimonial" id="edit_text" required></textarea>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Järjestysnumero</label>
                            <input type="number" name="display_order" id="edit_order">
                        </div>
                        <div class="form-group">
                            <label>
                                <input type="checkbox" name="is_visible" id="edit_visible"> Näkyvä verkkosivulla
                            </label>
                        </div>
                    </div>
                    <button type="submit" class="btn-save"><i class="fas fa-save"></i> Päivitä</button>
                    <button type="button" class="btn-save" style="background:#95a5a6; margin-left:10px;" onclick="hideEditForm()"><i class="fas fa-times"></i> Peruuta</button>
                </form>
            </div>
        </div>
    </div>

    <script>
        function editTestimonial(t) {
            document.getElementById('edit_id').value = t.id;
            document.getElementById('edit_name').value = t.client_name;
            document.getElementById('edit_position').value = t.client_position || '';
            document.getElementById('edit_rating').value = t.rating;
            document.getElementById('edit_text').value = t.testimonial;
            document.getElementById('edit_order').value = t.display_order || 0;
            document.getElementById('edit_visible').checked = t.is_visible == 1;
            document.getElementById('editForm').style.display = 'block';
            document.getElementById('addForm').style.display = 'none';
            window.scrollTo({ top: document.getElementById('editForm').offsetTop - 100, behavior: 'smooth' });
        }

        function hideEditForm() {
            document.getElementById('editForm').style.display = 'none';
        }

        document.getElementById('showAddFormBtn').onclick = function(e) {
            e.preventDefault();
            document.getElementById('addForm').style.display = 'block';
            document.getElementById('editForm').style.display = 'none';
            window.scrollTo({ top: document.getElementById('addForm').offsetTop - 100, behavior: 'smooth' });
        };
    </script>
</body>
</html>
