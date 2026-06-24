<?php
session_start();
require_once 'includes/connection.php';

// Only allow admin access
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: login.php');
    exit;
}

// Fetch books
try {
    $stmt = $pdo->query("SELECT * FROM books ORDER BY id DESC");
    $books = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Admin Books Management</title>
<style>
  body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background: #f7f9fc;
    margin: 0;
    padding: 20px;
  }
  h1 {
    text-align: center;
    color: #333;
  }
  .container {
    max-width: 1000px;
    margin: 0 auto;
    background: #fff;
    padding: 2rem;
    border-radius: 8px;
    box-shadow: 0 8px 20px rgb(0 0 0 / 0.1);
  }
  table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 1.5rem;
  }
  table thead {
    background-color: #0077ff;
    color: white;
  }
  table th, table td {
    padding: 12px 15px;
    border: 1px solid #ddd;
    text-align: left;
  }
  table tbody tr:hover {
    background-color: #f1faff;
  }
  a.button, button {
    background-color: #0077ff;
    color: white;
    padding: 8px 14px;
    border: none;
    border-radius: 5px;
    text-decoration: none;
    cursor: pointer;
    font-weight: 600;
    transition: background-color 0.3s;
  }
  a.button:hover, button:hover {
    background-color: #005bb5;
  }
  .actions button, .actions a.button {
    margin-right: 8px;
  }
  .top-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
  }
</style>
</head>
<body>
<div class="container">
  <div class="top-bar">
    <h1>Books Management</h1>
    <a href="admin_add_book.php" class="button">+ Add New Book</a>
  </div>
  <?php if (!empty($_SESSION['success'])): ?>
    <div style="color: green; margin-bottom: 1rem;"><?= htmlspecialchars($_SESSION['success']) ?></div>
    <?php unset($_SESSION['success']); ?>
  <?php endif; ?>
  <table>
    <thead>
      <tr>
        <th>ID</th>
        <th>Title</th>
        <th>Author</th>
        <th>Published Year</th>
        <th>Category</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php if ($books): ?>
        <?php foreach ($books as $book): ?>
          <tr>
            <td><?= htmlspecialchars($book['id']) ?></td>
            <td><?= htmlspecialchars($book['title']) ?></td>
            <td><?= htmlspecialchars($book['author']) ?></td>
            <td><?= htmlspecialchars($book['published_year']) ?></td>
            <td><?= htmlspecialchars($book['category']) ?></td>
            <td class="actions">
              <a href="admin_edit_book.php?id=<?= $book['id'] ?>" class="button" style="background:#28a745;">Edit</a>
              <form action="admin_delete_book.php" method="post" style="display:inline;" onsubmit="return confirm('Delete this book?');">
                <input type="hidden" name="book_id" value="<?= $book['id'] ?>">
                <button type="submit" style="background:#dc3545;">Delete</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php else: ?>
        <tr><td colspan="6" style="text-align:center;">No books found.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>
</body>
</html>
