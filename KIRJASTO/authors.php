<?php
session_start();
include("connection.php");

// Check if user is logged in (based on email session)
if (!isset($_SESSION['email'])) {
    header("location: login1.php");
    exit;
}

$authors = [];
$searchTerm = '';

// Handle search form submission
if (isset($_POST['submit'])) {
    $searchTerm = trim($_POST['search']);
    $stmt = $pdo->prepare("SELECT * FROM authors WHERE id LIKE ? OR name LIKE ?");
    $likeSearch = "%$searchTerm%";
    $stmt->execute([$likeSearch, $likeSearch]);
    $authors = $stmt->fetchAll();
} else {
    // Show all authors if no search
    $stmt = $pdo->query("SELECT * FROM authors");
    $authors = $stmt->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Authors Management</title>
    <link rel="stylesheet" href="style.css?=<?= time(); ?>" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <style>
        /* Container */
        .container-display {
            width: 90%;
            margin: 50px auto;
            z-index: 9999;
        }
        /* Table styling */
        table {
            background-color: Aqua;
            color: black;
            border: 1px solid Aqua;
            box-shadow: 4px 4px 10px rgb(84, 84, 84), -4px -4px 10px rgb(84, 84, 84);
        }
        table tr {
            color: darkblue;
            border: 1px solid Aqua;
        }
        a {
            text-decoration: none;
            color: white;
        }
        /* No authors found message */
        .nofound {
            border: 1px solid red;
            padding: 10px;
            border-radius: 4px;
            width: 50%;
            margin: 30px auto;
            text-align: center;
            color: white;
            background-color: tomato;
        }
        /* Search input container */
        .input-search {
            width: 100%;
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
        }
        /* Search form */
        .input-search form {
            width: 75%;
            display: flex;
        }
        /* Search input field */
        .input-search input {
            border: 1px solid #ccc;
            background-color: white;
            width: 100%;
            height: 40px;
            padding: 10px;
            border-radius: 6px;
            outline: none;
            color: black;
            font-size: 1rem;
        }
        /* Search button */
        .input-search form button {
            padding: 10px;
            width: 80px;
            height: 40px;
            border: 1px solid tomato;
            background-color: tomato;
            color: white;
            border-radius: 6px;
            margin-left: 10px;
            cursor: pointer;
            font-weight: 600;
        }
    </style>
</head>
<body>
<?php include "headerForAdmin.php"; ?>

<div class="container-display">
    <h2>Authors List</h2>

<a href="addAuthor.php" class="btn btn-dark border border-light mb-3">Add New Author</a>

    <div class="input-search">
        <form method="post" action="">
            <input type="text" name="search" placeholder="Search by ID or Name" value="<?= htmlspecialchars($searchTerm) ?>" required />
            <button type="submit" name="submit">Search</button>
        </form>
    </div>

    <?php
    if ($authors && count($authors) > 0): ?>
        <table class="table mt-5">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Biography</th>
                    <th>Email</th>
                    <th style="text-align: center;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($authors as $author): ?>
                    <tr>
                        <td><?= htmlspecialchars($author['id']) ?></td>
                        <td><?= htmlspecialchars($author['name']) ?></td>
                        <td><?= nl2br(htmlspecialchars($author['bio'])) ?></td>
                        <td><?= htmlspecialchars($author['email']) ?></td>
                        <td style="text-align: center;">
                            <a href="updateAuthor.php?id=<?= $author['id'] ?>" class="btn btn-dark border border-light">Update</a>
                            <a href="deleteAuthor.php?id=<?= $author['id'] ?>" class="btn btn-danger border border-light" onclick="return confirm('Are you sure you want to delete this author?');">Delete</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <div class="nofound">No authors found.</div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
