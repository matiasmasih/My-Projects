<?php
session_start();
require_once __DIR__ . '/includes/connection.php';

// Check login
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Get book ID from GET
$book_id = $_GET['id'] ?? null;
if (!$book_id) {
    die("Book ID missing");
}

// Fetch existing book data
$stmt = $pdo->prepare("SELECT * FROM books WHERE id = ?");
$stmt->execute([$book_id]);
$book = $stmt->fetch();

if (!$book) {
    die("Book not found");
}

// Handle POST update form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'] ?? '';
    $author = $_POST['author'] ?? '';
    $isbn = $_POST['isbn'] ?? '';
    $genre = $_POST['genre'] ?? '';
    $year = $_POST['year'] ?? '';

    // TODO: Validate inputs here

    // Handle optional file upload for cover image
    $cover_image = $book['cover_image']; // keep old if no new upload
    if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] === UPLOAD_ERR_OK) {
        $tmp_name = $_FILES['cover_image']['tmp_name'];
        $filename = basename($_FILES['cover_image']['name']);
        $target_dir = 'uploads/covers/';
        if (!is_dir($target_dir)) mkdir($target_dir, 0755, true);
        $target_file = $target_dir . time() . '_' . $filename;
        move_uploaded_file($tmp_name, $target_file);
        $cover_image = $target_file;
    }

    // Update book in DB
    $updateStmt = $pdo->prepare("UPDATE books SET title = ?, author = ?, isbn = ?, genre = ?, year = ?, cover_image = ? WHERE id = ?");
    $updateStmt->execute([$title, $author, $isbn, $genre, $year, $cover_image, $book_id]);

      header('Location: books_admin.php?message=Book updated successfully');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Edit Book - LibraryPro</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <style>
    body {
      background: #f9f9f9;
      padding: 2rem;
    }
    .form-container {
      max-width: 600px;
      margin: auto;
      background: white;
      padding: 2rem;
      border-radius: 10px;
      box-shadow: 0 4px 20px rgba(0,0,0,0.1);
    }
    h2 {
      margin-bottom: 1.5rem;
      font-weight: 700;
      color: #007bff;
    }
  </style>
</head>
<body>

<div class="form-container">
  <h2>Edit Book</h2>
  <form method="POST" enctype="multipart/form-data" novalidate>
    <div class="mb-3">
      <label for="title" class="form-label fw-semibold">Book Title</label>
      <input type="text" class="form-control" id="title" name="title" required value="<?= htmlspecialchars($book['title']) ?>">
    </div>

    <div class="mb-3">
      <label for="author" class="form-label fw-semibold">Author</label>
      <input type="text" class="form-control" id="author" name="author" required value="<?= htmlspecialchars($book['author']) ?>">
    </div>

    <div class="mb-3">
      <label for="isbn" class="form-label fw-semibold">ISBN</label>
      <input type="text" class="form-control" id="isbn" name="isbn" required value="<?= htmlspecialchars($book['isbn']) ?>">
    </div>

    <div class="mb-3">
      <label for="genre" class="form-label fw-semibold">Genre</label>
      <input type="text" class="form-control" id="genre" name="genre" required value="<?= htmlspecialchars($book['genre']) ?>">
    </div>

    <div class="mb-3">
      <label for="year" class="form-label fw-semibold">Year</label>
      <input type="number" min="0" max="<?= date('Y') ?>" class="form-control" id="year" name="year" required value="<?= htmlspecialchars($book['year']) ?>">
    </div>

    <div class="mb-3">
      <label for="cover_image" class="form-label fw-semibold">Cover Image (optional)</label>
      <input class="form-control" type="file" id="cover_image" name="cover_image" accept="image/*" />
      <?php if (!empty($book['cover_image'])): ?>
        <small class="text-muted">Current: <a href="<?= htmlspecialchars($book['cover_image']) ?>" target="_blank">View</a></small>
      <?php endif; ?>
    </div>

    <button type="submit" class="btn btn-primary w-100">Update Book</button>
  </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
