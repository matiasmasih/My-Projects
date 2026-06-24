<?php
session_start();
require_once __DIR__ . '/includes/connection.php';

// Check admin role
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

$errors = [];
$user_id = '';
$book_id = '';

// Handle Add Wishlist Item POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    $user_id = $_POST['user_id'] ?? '';
    $book_id = $_POST['book_id'] ?? '';

    // Validate inputs
    if (!$user_id) $errors[] = "Please select a user.";
    if (!$book_id) $errors[] = "Please select a book.";

    // Check if wishlist entry already exists
    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM wishlist WHERE user_id = ? AND book_id = ?");
        $stmt->execute([$user_id, $book_id]);
        if ($stmt->fetchColumn() > 0) {
            $errors[] = "This book is already in the user's wishlist.";
        }
    }

    // Insert if no errors
    if (empty($errors)) {
        $stmt = $pdo->prepare("INSERT INTO wishlist (user_id, book_id) VALUES (?, ?)");
        try {
            $stmt->execute([$user_id, $book_id]);
            header("Location: admin_wishlist.php?success=1");
            exit;
        } catch (PDOException $e) {
            $errors[] = "Database error: " . $e->getMessage();
        }
    }
}

// Get all users and books for dropdowns
$users = $pdo->query("SELECT id, firstname, lastname FROM users ORDER BY firstname")->fetchAll();
$books = $pdo->query("SELECT id, title, author FROM books ORDER BY title")->fetchAll();

// Get all wishlist entries with user and book details
$sql = "SELECT w.id, u.firstname, u.lastname, b.title, b.author, w.created_at
        FROM wishlist w
        JOIN users u ON w.user_id = u.id
        JOIN books b ON w.book_id = b.id
        ORDER BY w.created_at DESC";

$wishlistEntries = $pdo->query($sql)->fetchAll();

?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<title>Admin - Manage Wishlist</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
<style>
body {
  background: #f5f7fa;
  font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
  padding: 40px 20px;
  color: #333;
}

.container {
  max-width: 900px;
  margin: 0 auto;
  background: white;
  padding: 30px 40px;
  border-radius: 12px;
  box-shadow: 0 8px 25px rgb(0 0 0 / 0.1);
}

h2 {
  color: #0d6efd;
  font-weight: 700;
  margin-bottom: 25px;
  text-align: center;
}

form label {
  font-weight: 600;
  color: #333;
  display: block;
  margin-bottom: 6px;
}

form select {
  border-radius: 8px;
  border: 1px solid #ced4da;
  padding: 10px 14px;
  font-size: 1rem;
  width: 100%;
  transition: border-color 0.3s ease;
}

form select:focus {
  border-color: #0d6efd;
  outline: none;
  box-shadow: 0 0 6px rgba(13, 110, 253, 0.5);
}

form button.btn-primary {
  padding: 12px 28px;
  font-size: 1.1rem;
  cursor: pointer;
  border-radius: 10px;
  background-color: #0d6efd;
  color: white;
  border: none;
  font-weight: 600;
  transition: background-color 0.3s ease;
}

form button.btn-primary:hover {
  background-color: #084cdf;
}

table {
  width: 100%;
  border-collapse: collapse;
  margin-bottom: 25px;
}

th, td {
  padding: 12px 15px;
  border: 1px solid #dee2e6;
  text-align: left;
  vertical-align: middle;
  font-size: 0.95rem;
}

th {
  background-color: #e9f0ff;
  color: #0d6efd;
  font-weight: 600;
}

tr:nth-child(even) {
  background-color: #f9fbff;
}

.btn {
  padding: 6px 14px;
  font-size: 0.9rem;
  border-radius: 8px;
  font-weight: 600;
  cursor: pointer;
  transition: background-color 0.3s ease;
  text-decoration: none;
  display: inline-block;
}

.btn-edit {
  background-color: #198754; /* Bootstrap success */
  color: white;
  border: none;
}

.btn-edit:hover {
  background-color: #157347;
}

.btn-delete {
  background-color: #dc3545; /* Bootstrap danger */
  color: white;
  border: none;
}

.btn-delete:hover {
  background-color: #b02a37;
}

.alert {
  font-size: 0.95rem;
  margin-bottom: 20px;
}
</style>
</head>
<body>

<div class="container">
  <h2>Manage Wishlist</h2>

  <?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
      <ul class="mb-0">
        <?php foreach ($errors as $err): ?>
          <li><?= htmlspecialchars($err) ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success">Wishlist item added successfully.</div>
  <?php endif; ?>

  <?php if (isset($_GET['deleted'])): ?>
    <div class="alert alert-success">Wishlist item deleted successfully.</div>
  <?php endif; ?>

  <!-- Add Wishlist Item Form -->
  <form method="POST" novalidate class="mb-4">
    <input type="hidden" name="action" value="add" />
    <div class="row g-3">
      <div class="col-md-5">
        <label for="user_id">Select User *</label>
        <select id="user_id" name="user_id" required>
          <option value="">-- Select User --</option>
          <?php foreach ($users as $user): ?>
            <option value="<?= $user['id'] ?>" <?= ($user['id'] == $user_id) ? 'selected' : '' ?>>
              <?= htmlspecialchars($user['firstname'] . ' ' . $user['lastname']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-5">
        <label for="book_id">Select Book *</label>
        <select id="book_id" name="book_id" required>
          <option value="">-- Select Book --</option>
          <?php foreach ($books as $book): ?>
            <option value="<?= $book['id'] ?>" <?= ($book['id'] == $book_id) ? 'selected' : '' ?>>
              <?= htmlspecialchars($book['title'] . ' by ' . $book['author']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <!-- Button row -->
    <div class="row mt-3">
      <div class="col text-end">
        <button type="submit" class="btn btn-primary">Add to Wishlist</button>
      </div>
    </div>
  </form>

  <!-- Wishlist Table -->
  <table class="table table-bordered table-hover align-middle">
    <thead class="table-light">
      <tr>
        <th>#</th>
        <th>User</th>
        <th>Book</th>
        <th>Author</th>
        <th>Date Added</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php if (count($wishlistEntries) === 0): ?>
        <tr><td colspan="6" class="text-center">No wishlist items found.</td></tr>
      <?php else: ?>
        <?php foreach ($wishlistEntries as $entry): ?>
          <tr>
            <td><?= $entry['id'] ?></td>
            <td><?= htmlspecialchars($entry['firstname'] . ' ' . $entry['lastname']) ?></td>
            <td><?= htmlspecialchars($entry['title']) ?></td>
            <td><?= htmlspecialchars($entry['author']) ?></td>
            <td><?= $entry['created_at'] ?></td>
            <td>
              <form method="POST" action="delete_wishlist.php" onsubmit="return confirm('Are you sure you want to delete this wishlist entry?');" style="display:inline;">
                <input type="hidden" name="wishlist_id" value="<?= $entry['id'] ?>">
                <button type="submit" class="btn btn-sm btn-delete">🗑 Delete</button>
              </form>
              <!-- Future: Add edit button here -->
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>

</body>
</html>
