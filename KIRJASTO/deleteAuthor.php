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

$stmt = $pdo->prepare("DELETE FROM authors WHERE id = ?");
$result = $stmt->execute([$id]);

if ($result) {
    $_SESSION['delete'] = "<div class='alert alert-success'>Author deleted successfully!</div>";
} else {
    $_SESSION['nofound'] = "<div class='nofound'>Failed to delete author.</div>";
}

header("location: authors.php");
exit;
