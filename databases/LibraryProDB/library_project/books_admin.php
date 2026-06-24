<?php
session_start();
require_once 'includes/connection.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

$addError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add_book') {
        $title = trim($_POST['title']);
        $author = trim($_POST['author']);
        $isbn = trim($_POST['isbn']) ?: null;
        $genre = trim($_POST['genre']) ?: null;
        $year = intval($_POST['year']) ?: null;
        $is_borrowed = 0;
        $coverImage = null;

        if (!$title || !$author) {
            $addError = "Title and Author are required.";
        } else {
            if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = 'uploads/covers/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                $filename = basename($_FILES['cover_image']['name']);
                $targetFile = $uploadDir . time() . '_' . preg_replace('/[^a-zA-Z0-9_\.-]/', '_', $filename);
                if (move_uploaded_file($_FILES['cover_image']['tmp_name'], $targetFile)) {
                    $coverImage = $targetFile;
                } else {
                    $addError = "Failed to upload cover image.";
                }
            }

            if (!$addError) {
                $stmt = $pdo->prepare("INSERT INTO books (title, author, isbn, genre, year, is_borrowed, cover_image) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$title, $author, $isbn, $genre, $year, $is_borrowed, $coverImage]);
                header("Location: books_admin.php?added=1");
                exit;
            }
        }
    } elseif ($_POST['action'] === 'delete_book' && isset($_POST['book_id'])) {
        $stmt = $pdo->prepare("DELETE FROM books WHERE id = ?");
        $stmt->execute([$_POST['book_id']]);
        header("Location: books_admin.php?deleted=1");
        exit;
    }
}

$stmt = $pdo->query("SELECT * FROM books ORDER BY id DESC");
$books = $stmt->fetchAll();
?>

<!-- HEADER START -->
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>LibraryPro Admin - Books</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body {
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      background-color: #f1f5f9;
    }

    .navbar {
      background: #0d6efd;
      padding: 1rem 2rem;
      box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
    }

    .navbar-brand {
      font-weight: 600;
      font-size: 1.5rem;
      color: #fff !important;
    }

    .container {
      max-width: 1200px;
      margin-top: 2rem;
    }

    .form-card {
      background: #ffffff;
      border-radius: 16px;
      box-shadow: 0 8px 24px rgba(0, 0, 0, 0.06);
      padding: 2.5rem;
      margin-bottom: 2rem;
      transition: all 0.3s ease-in-out;
    }

    .form-card:hover {
      box-shadow: 0 10px 28px rgba(0, 0, 0, 0.08);
    }

    .form-card label {
      font-weight: 500;
      margin-bottom: 0.3rem;
    }

    .form-control {
      border-radius: 10px;
    }

    .btn-primary {
      border-radius: 10px;
      padding: 0.5rem 1.2rem;
    }

    .cover-img {
      height: 60px;
      width: auto;
      border-radius: 6px;
      object-fit: cover;
    }

    .message {
      padding: 1rem;
      border-radius: 12px;
      font-weight: 500;
      margin-bottom: 1.5rem;
    }

    .success {
      background-color: #d1e7dd;
      color: #0f5132;
    }

    .error {
      background-color: #f8d7da;
      color: #842029;
    }

    .table thead {
      background: #0d6efd;
      color: white;
    }

    .table-hover tbody tr:hover {
      background-color: #eef4ff;
    }

    .btn-warning {
      color: white;
      background-color: #f59f00;
      border: none;
    }

    .btn-danger {
      background-color: #e03131;
      border: none;
    }

    .badge {
      font-size: 0.85rem;
      padding: 0.5em 0.8em;
      border-radius: 50px;
    }
  </style>
</head>
<body>
  <!-- NAVIGATION -->
  <nav class="navbar navbar-expand-lg navbar-dark">
    <div class="container-fluid px-4">
      <a class="navbar-brand" href="#">📚 LibraryPro <small class="text-light">Admin Panel</small></a>
    </div>
  </nav>
<!-- HEADER END -->

<div class="container">

   <h2 class="my-4 text-primary fw-bold text-center">📖 Manage Books</h2>

  <!-- Flash Messages -->
  <?php if (isset($_GET['added'])): ?>
    <div class="message success">✅ Book added successfully!</div>
  <?php elseif (isset($_GET['deleted'])): ?>
    <div class="message error">🗑️ Book deleted successfully.</div>
  <?php elseif (!empty($addError)): ?>
    <div class="message error"><?= htmlspecialchars($addError) ?></div>
  <?php endif; ?>

  <!-- ADD BOOK FORM -->
<div class="form-card mx-auto" style="max-width: 500px;">
  <form method="post" enctype="multipart/form-data">
    <input type="hidden" name="action" value="add_book">

    <div class="mb-3">
      <label class="form-label">Book Title</label>
      <input type="text" name="title" class="form-control" required>
    </div>

    <div class="mb-3">
      <label class="form-label">Author</label>
      <input type="text" name="author" class="form-control" required>
    </div>

    <div class="mb-3">
      <label class="form-label">ISBN</label>
      <input type="text" name="isbn" class="form-control">
    </div>

    <div class="mb-3">
      <label class="form-label">Genre</label>
      <input type="text" name="genre" class="form-control">
    </div>

    <div class="mb-3">
      <label class="form-label">Year</label>
      <input type="number" name="year" class="form-control" min="1000" max="9999">
    </div>

    <div class="mb-3">
      <label class="form-label">Cover Image (optional)</label>
      <input type="file" name="cover_image" class="form-control" accept="image/*">
    </div>

<div class="d-flex mt-3">
  <button type="submit" class="btn btn-primary">➕ Add Book</button>
  <a href="admin.php" class="btn btn-secondary ms-auto">⬅ Back</a>
</div>
  </form>
</div>

  <!-- BOOKS TABLE -->
  <div class="table-responsive">
    <table class="table table-hover table-bordered align-middle">
      <thead class="text-center">
        <tr>
          <th>ID</th>
          <th>Cover</th>
          <th>Title</th>
          <th>Author</th>
          <th>ISBN</th>
          <th>Genre</th>
          <th>Year</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($books): foreach ($books as $book): ?>
          <tr>
            <td class="text-center"><?= $book['id'] ?></td>
            <td class="text-center">
              <?php if ($book['cover_image'] && file_exists($book['cover_image'])): ?>
                <img src="<?= htmlspecialchars($book['cover_image']) ?>" class="cover-img" alt="Cover">
              <?php else: ?>
                <span class="text-muted">No Image</span>
              <?php endif; ?>
            </td>
            <td><?= htmlspecialchars($book['title']) ?></td>
            <td><?= htmlspecialchars($book['author']) ?></td>
            <td><?= htmlspecialchars($book['isbn']) ?></td>
            <td><?= htmlspecialchars($book['genre']) ?></td>
            <td><?= htmlspecialchars($book['year']) ?></td>
            <td class="text-center">
              <?php if ($book['is_borrowed']): ?>
                <span class="badge bg-danger">Borrowed</span>
              <?php else: ?>
                <span class="badge bg-success">Available</span>
              <?php endif; ?>
            </td>
            <td class="text-center">
              <a href="edit_book.php?id=<?= $book['id'] ?>" class="btn btn-sm btn-warning me-1">✏️ Edit</a>
              <form method="post" class="d-inline" onsubmit="return confirm('Delete this book?');">
                <input type="hidden" name="action" value="delete_book">
                <input type="hidden" name="book_id" value="<?= $book['id'] ?>">
                <button type="submit" class="btn btn-sm btn-danger">&#128465; Delete</button>
              </form>
            </td>
          </tr>
        <?php endforeach; else: ?>
          <tr>
            <td colspan="9" class="text-center text-muted">No books found.</td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

</div>
</body>
</html>
