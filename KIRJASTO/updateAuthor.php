<?php
session_start();
include("connection.php");

if (!isset($_SESSION['email'])) {
    header("location: login1.php");
    exit;
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['nofound'] = "<div class='nofound'>Invalid author ID.</div>";
    header("location: authors.php");
    exit;
}

$id = (int)$_GET['id'];

$stmt = $pdo->prepare("SELECT * FROM authors WHERE id = ?");
$stmt->execute([$id]);
$author = $stmt->fetch();

if (!$author) {
    $_SESSION['nofound'] = "<div class='nofound'>Author not found.</div>";
    header("location: authors.php");
    exit;
}

if (isset($_POST['submit'])) {
    $name = trim($_POST['name']);
    $bio = trim($_POST['bio']);
    $email = trim($_POST['email']);

    if (empty($name)) {
        $_SESSION['error'] = "Name is required.";
    } else {
        $stmt = $pdo->prepare("UPDATE authors SET name = ?, bio = ?, email = ? WHERE id = ?");
        $result = $stmt->execute([$name, $bio ?: null, $email ?: null, $id]);

        if ($result) {
            $_SESSION['user-update'] = "<div class='alert alert-success'>Author updated successfully!</div>";
            header("location: authors.php");
            exit;
        } else {
            $_SESSION['error'] = "Failed to update author.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Update Author</title>
<link rel="stylesheet" href="style.css?=<?php echo time(); ?>" />
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet" />
<style>
.container-display {
    width: 90%;
    margin: 50px auto;
    z-index: 9999;
    background-color: Aqua;
    padding: 20px;
    box-shadow: 4px 4px 10px rgb(84, 84, 84), -4px -4px 10px rgb(84, 84, 84);
    border-radius: 10px;
    color: darkblue;
}
a {
    text-decoration: none;
    color: white;
}
</style>
</head>
<body>
<?php include "headerForAdmin.php"; ?>

<div class="container-display">
    <h2>Update Author</h2>

    <?php
    if (isset($_SESSION['error'])) {
        echo "<div class='nofound'>{$_SESSION['error']}</div>";
        unset($_SESSION['error']);
    }
    ?>

    <form method="post" action="">
        <div class="mb-3">
            <label for="name" class="form-label">Name <span class="text-danger">*</span></label>
            <input type="text" name="name" id="name" class="form-control" maxlength="255" required value="<?= htmlspecialchars($author['name']) ?>" />
        </div>
        <div class="mb-3">
            <label for="bio" class="form-label">Biography</label>
            <textarea name="bio" id="bio" class="form-control" rows="4"><?= htmlspecialchars($author['bio']) ?></textarea>
        </div>
        <div class="mb-3">
            <label for="email" class="form-label">Email (optional)</label>
            <input type="email" name="email" id="email" class="form-control" maxlength="255" value="<?= htmlspecialchars($author['email']) ?>" />
        </div>
        <button type="submit" name="submit" class="btn btn-dark border border-light">Update Author</button>
        <a href="authors.php" class="btn btn-danger border border-light">Cancel</a>
    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
