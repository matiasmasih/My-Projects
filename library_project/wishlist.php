<?php
session_start();
require_once 'includes/connection.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Handle removing an item from wishlist
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Remove from wishlist
    if (isset($_POST['remove_id'])) {
        $remove_id = intval($_POST['remove_id']);
        $stmtDelete = $pdo->prepare("DELETE FROM wishlist WHERE id = ? AND user_id = ?");
        $stmtDelete->execute([$remove_id, $user_id]);
        header('Location: wishlist.php');
        exit;
    }
    // Add to wishlist
    if (isset($_POST['add_book_id'])) {
        $book_id = intval($_POST['add_book_id']);
        // Check if already in wishlist to avoid duplicates
        $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM wishlist WHERE user_id = ? AND book_id = ?");
        $stmtCheck->execute([$user_id, $book_id]);
        if ($stmtCheck->fetchColumn() == 0) {
            $stmtAdd = $pdo->prepare("INSERT INTO wishlist (user_id, book_id, created_at) VALUES (?, ?, NOW())");
            $stmtAdd->execute([$user_id, $book_id]);
        }
        header('Location: wishlist.php');
        exit;
    }
}

// Fetch wishlist items with book details
$stmt = $pdo->prepare("
    SELECT w.id AS wishlist_id, b.title, b.author, b.cover_image
    FROM wishlist w
    JOIN books b ON w.book_id = b.id
    WHERE w.user_id = ?
    ORDER BY w.created_at DESC
");
$stmt->execute([$user_id]);
$wishlistItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch all books for add form dropdown (optional: limit or paginate if too many)
$stmtBooks = $pdo->query("SELECT id, title, author FROM books ORDER BY title");
$allBooks = $stmtBooks->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>My Wishlist - LibraryPro</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
<style>
  /* Navbar styling */
  .navbar.bg-primary {
    background-color: #007bff !important;
    box-shadow: 0 4px 10px rgba(0, 123, 255, 0.6);
    height: 50px;
  }
  .navbar .container {
    height: 50px;
    display: flex;
    align-items: center;
  }
  .navbar-brand, .nav-link {
    color: #fff !important;
    font-weight: 600;
  }
  .nav-link.active {
    color: #ffd6e8 !important;
  }

  body {
    background: #f8f9fa;
    padding-top: 60px;
  }

  .wishlist-container {
    max-width: 900px;
    margin: 40px auto 80px;
  }

  .wishlist-item {
    background: white;
    border-radius: 8px;
    padding: 15px;
    margin-bottom: 15px;
    box-shadow: 0 2px 6px rgba(0,0,0,0.1);
    display: flex;
    align-items: center;
  }
  .wishlist-item img {
    width: 80px;
    height: 120px;
    object-fit: cover;
    border-radius: 4px;
    margin-right: 20px;
    border: 1px solid #ddd;
  }
  .wishlist-item-details {
    flex: 1;
    display: flex;
    flex-direction: column;
    justify-content: center;
  }
  .wishlist-item-title {
    font-size: 1.25rem;
    font-weight: 600;
    margin-bottom: 4px;
  }
  .wishlist-item-author {
    color: #6c757d;
    font-style: italic;
    margin-bottom: 8px;
  }
  .btn-remove {
    background-color: #dc3545;
    border: none;
    color: white;
    align-self: flex-start;
    padding: 6px 12px;
    border-radius: 4px;
    font-size: 0.9rem;
    transition: background-color 0.2s ease-in-out;
  }
  .btn-remove:hover {
    background-color: #bb2d3b;
  }

  /* Add form styling */
  .add-wishlist-form {
    background: white;
    max-width: 900px;
    margin: 0 auto 40px;
    padding: 20px 25px;
    border-radius: 10px;
    box-shadow: 0 4px 12px rgb(0 0 0 / 0.1);
  }
  .add-wishlist-form h3 {
    margin-bottom: 20px;
    font-weight: 700;
    color: #007bff;
  }
  .form-select, .btn-add {
    font-size: 1rem;
    height: 45px;
  }
  .btn-add {
    background-color: #007bff;
    border: none;
    color: white;
    font-weight: 600;
    padding: 0 20px;
    border-radius: 6px;
    transition: background-color 0.3s ease;
  }
  .btn-add:hover {
    background-color: #0056b3;
  }

  @media (max-width: 576px) {
    .wishlist-item {
      flex-direction: column;
      align-items: flex-start;
    }
    .wishlist-item img {
      margin-bottom: 10px;
      margin-right: 0;
    }
  }
</style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark bg-primary fixed-top">
  <div class="container">
    <a class="navbar-brand" href="#">LibraryPro</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"
      aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarNav">
      <ul class="navbar-nav ms-auto">
        <li class="nav-item"><a class="nav-link" href="dashboard.php">🏠 Dashboard</a></li>
        <li class="nav-item"><a class="nav-link" href="borrow.php">📚 Borrow Book</a></li>
        <li class="nav-item"><a class="nav-link" href="borrow_device.php">💻 Borrow Device</a></li>
        <li class="nav-item"><a class="nav-link" href="return_device.php">🔁 Return Device</a></li>
        <li class="nav-item"><a class="nav-link" href="return.php">🔁 Return Books</a></li>
        <li class="nav-item"><a class="nav-link active" href="wishlist.php">📝 Wishlist</a></li>
        <li class="nav-item"><a class="nav-link" href="profile.php">👤 Profile</a></li>
        <li class="nav-item"><a class="nav-link" href="logout.php">🚪 Logout</a></li>
      </ul>
    </div>
  </div>
</nav>

<div class="add-wishlist-form shadow-sm">
  <h3>Add Book to Wishlist</h3>
  <form method="POST" class="d-flex gap-2 flex-wrap align-items-center">
    <select name="add_book_id" class="form-select" required>
      <option value="" selected disabled>Select a book...</option>
      <?php foreach ($allBooks as $book): ?>
        <option value="<?= $book['id'] ?>">
          <?= htmlspecialchars($book['title']) ?> — <?= htmlspecialchars($book['author']) ?>
        </option>
      <?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-add">Add</button>
  </form>
</div>

<div class="wishlist-container">
  <h2 class="mb-4">My Wishlist</h2>

  <?php if (empty($wishlistItems)): ?>
    <p class="text-muted">Your wishlist is empty.</p>
  <?php else: ?>
    <?php foreach ($wishlistItems as $item): ?>
      <div class="wishlist-item">
        <img src="<?= htmlspecialchars($item['cover_image'] ?: 'default_cover.jpg') ?>" alt="<?= htmlspecialchars($item['title']) ?>" />
        <div class="wishlist-item-details">
          <div class="wishlist-item-title"><?= htmlspecialchars($item['title']) ?></div>
          <div class="wishlist-item-author">by <?= htmlspecialchars($item['author']) ?></div>
          <form method="POST" onsubmit="return confirm('Remove this book from your wishlist?');" style="margin-top:auto;">
            <input type="hidden" name="remove_id" value="<?= $item['wishlist_id'] ?>" />
            <button type="submit" class="btn btn-remove btn-sm">Remove</button>
          </form>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
