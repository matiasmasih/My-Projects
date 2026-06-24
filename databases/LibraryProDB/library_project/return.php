<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

require_once __DIR__ . '/includes/connection.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$message = '';

// Handle return request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['return_book_id'])) {
    $bookId = (int)$_POST['return_book_id'];

    // Update the books table to mark the book as returned
    $stmt = $pdo->prepare("UPDATE books SET is_borrowed = 0 WHERE id = ? AND is_borrowed = 1");
    $stmt->execute([$bookId]);

    if ($stmt->rowCount() > 0) {
        $message = "Book returned successfully!";
    } else {
        $message = "Book was not borrowed or does not exist.";
    }
}

// Fetch all borrowed books
try {
    $stmt = $pdo->query("SELECT * FROM books WHERE is_borrowed = 1 ORDER BY title");
    $borrowedBooks = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die('Error fetching books: ' . $e->getMessage());
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Return Borrowed Books - LibraryPro</title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />

  <style>
    /* Your navbar styles */
    .navbar.bg-primary {
      background-color: #007bff !important;
      box-shadow: 0 4px 10px rgba(0, 123, 255, 0.6);
      border-bottom: none;
      height: 50px;
      line-height: 50px;
      padding-top: 0;
      padding-bottom: 0;
    }
    .navbar .container {
      height: 50px;
      display: flex;
      align-items: center;
      padding-left: 0;
      padding-right: 0;
    }
    .navbar-brand {
      font-weight: 900;
      font-size: 1.5rem;
      color: #fff !important;
      height: 50px;
      line-height: 50px;
      padding: 0;
    }
    .navbar-nav {
      height: 50px;
      display: flex;
      align-items: center;
    }
    .navbar-nav .nav-link {
      color: #fff !important;
      font-weight: 600;
      padding-top: 0;
      padding-bottom: 0;
      line-height: 50px;
      font-size: 1rem;
    }
    .navbar-nav .nav-link.active,
    .navbar-nav .nav-link:hover {
      color: #ffd6e8 !important;
      text-shadow: none;
    }
    .navbar-toggler {
      border-color: #fff !important;
      padding: 0.25rem 0.75rem;
      height: 40px;
      margin-top: 5px;
    }
    .navbar-toggler-icon {
      filter: brightness(0) invert(1);
      width: 30px;
      height: 30px;
      margin: auto;
      display: block;
      background-image: url("data:image/svg+xml;charset=utf8,%3csvg viewBox='0 0 30 30' xmlns='http://www.w3.org/2000/svg'%3e%3cpath stroke='%23ff7eb3' stroke-linecap='round' stroke-miterlimit='10' stroke-width='2' d='M4 7h22M4 15h22M4 23h22'/%3e%3c/svg%3e");
    }
    /* Main container spacing */
    .main-container {
      margin-top: 70px;
    }
    .book-card img {
      max-height: 180px;
      object-fit: cover;
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
        <li class="nav-item"><a class="nav-link active" href="return.php">🔁 Return Books</a></li>
        <li class="nav-item"><a class="nav-link" href="wishlist.php">📝 Wishlist</a></li>
        <li class="nav-item"><a class="nav-link" href="profile.php">👤 Profile</a></li>
        <li class="nav-item"><a class="nav-link" href="logout.php">🚪 Logout</a></li>
      </ul>
    </div>
  </div>
</nav>

<div class="container main-container">
  <h1 class="mb-4">Return Borrowed Books</h1>

  <?php if ($message): ?>
    <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
  <?php endif; ?>

  <?php if (count($borrowedBooks) === 0): ?>
    <p>You have no borrowed books to return.</p>
  <?php else: ?>
    <div class="row row-cols-1 row-cols-md-3 g-4">
      <?php foreach ($borrowedBooks as $book): ?>
        <div class="col">
          <div class="card h-100 book-card">
            <?php if ($book['cover_image']): ?>
              <img src="<?= htmlspecialchars($book['cover_image']) ?>" class="card-img-top" alt="Cover of <?= htmlspecialchars($book['title']) ?>">
            <?php else: ?>
              <img src="default_cover.png" class="card-img-top" alt="Default book cover">
            <?php endif; ?>
            <div class="card-body d-flex flex-column">
              <h5 class="card-title"><?= htmlspecialchars($book['title']) ?></h5>
              <p class="card-text">Author: <?= htmlspecialchars($book['author']) ?></p>
              <p class="card-text">Genre: <?= htmlspecialchars($book['genre'] ?? '') ?></p>
              <p class="card-text">Year: <?= htmlspecialchars($book['year'] ?? '') ?></p>
              <form method="post" class="mt-auto">
                <input type="hidden" name="return_book_id" value="<?= $book['id'] ?>">
                <button type="submit" class="btn btn-danger w-100">Return Book</button>
              </form>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
