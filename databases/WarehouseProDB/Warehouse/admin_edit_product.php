<?php
session_start();
require 'config.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Check admin login
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

// Get product ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: admin_products.php');
    exit;
}
$productId = (int)$_GET['id'];

// Fetch product
$stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
$stmt->execute([$productId]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    header('Location: admin_products.php');
    exit;
}

$errors = [];
$success = false;

// Handle form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $stock = trim($_POST['stock'] ?? '');

    if ($name === '') $errors[] = "Product name is required.";
    if ($stock === '' || !ctype_digit($stock) || (int)$stock < 0) {
        $errors[] = "Stock must be a non-negative integer.";
    }

    if (empty($errors)) {
        $updateStmt = $pdo->prepare("UPDATE products SET name = ?, description = ?, category = ?, stock = ? WHERE id = ?");
        $updateStmt->execute([$name, $description, $category, $stock, $productId]);

        $success = true;
        $stmt->execute([$productId]); // Refresh product data
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Product #<?= htmlspecialchars((string)$productId) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f4f6f9;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .card {
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            margin-top: 60px;
        }
        .card-header {
            background-color: #1d3557;
            color: white;
            font-weight: 500;
            font-size: 1.25rem;
            border-radius: 12px 12px 0 0;
        }
        .form-label {
            font-weight: 500;
        }
        .btn-success {
            background-color: #2a9d8f;
            border-color: #2a9d8f;
        }
        .btn-success:hover {
            background-color: #21867a;
            border-color: #21867a;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="card mx-auto" style="max-width: 720px;">
        <div class="card-header">
            Edit Product #<?= htmlspecialchars((string)$productId) ?>
        </div>
        <div class="card-body">
            <?php if ($success): ?>
                <div class="alert alert-success">✅ Product updated successfully.</div>
            <?php endif; ?>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                            <li><?= htmlspecialchars($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="POST" novalidate>
                <div class="mb-3">
                    <label for="name" class="form-label">Product Name <span class="text-danger">*</span></label>
                    <input type="text" id="name" name="name" class="form-control" required
                           value="<?= htmlspecialchars($_POST['name'] ?? $product['name'] ?? '') ?>">
                </div>

                <div class="mb-3">
                    <label for="description" class="form-label">Description</label>
                    <textarea id="description" name="description" class="form-control" rows="3"
                              placeholder="Short product description"><?= htmlspecialchars($_POST['description'] ?? $product['description'] ?? '') ?></textarea>
                </div>

                <div class="mb-3">
                    <label for="category" class="form-label">Category</label>
                    <input type="text" id="category" name="category" class="form-control"
                           placeholder="E.g., Electronics, Home, Apparel"
                           value="<?= htmlspecialchars($_POST['category'] ?? $product['category'] ?? '') ?>">
                </div>

                <div class="mb-3">
                    <label for="stock" class="form-label">Stock Quantity <span class="text-danger">*</span></label>
                    <input type="number" id="stock" name="stock" class="form-control" min="0" required
                           value="<?= htmlspecialchars($_POST['stock'] ?? $product['stock'] ?? '') ?>">
                </div>

                <div class="d-flex justify-content-between">
                    <button type="submit" class="btn btn-success px-4">💾 Save</button>
                    <a href="admin_products.php" class="btn btn-outline-secondary">← Back to Products</a>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
