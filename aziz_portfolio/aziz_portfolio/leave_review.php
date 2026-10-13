<?php
// leave_review.php — Public testimonial submission (pending moderation)
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/i18n.php';

header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = t('review_err_method');
    echo json_encode($response);
    exit;
}

$client_name     = trim($_POST['client_name'] ?? '');
$client_position = trim($_POST['client_position'] ?? '');
$rating          = max(1, min(5, (int)($_POST['rating'] ?? 5)));
$testimonial     = trim($_POST['testimonial'] ?? '');

if ($client_name === '') {
    $response['message'] = t('review_err_name');
    echo json_encode($response);
    exit;
}

if ($testimonial === '' || mb_strlen($testimonial) < 20) {
    $response['message'] = t('review_err_short');
    echo json_encode($response);
    exit;
}

if (mb_strlen($testimonial) > 1000) {
    $response['message'] = t('review_err_long');
    echo json_encode($response);
    exit;
}

// Save as pending
$stmt = $conn->prepare(
    "INSERT INTO testimonials (client_name, client_position, testimonial, rating, is_visible, status, display_order)
     VALUES (?, ?, ?, ?, 0, 'pending', 0)"
);
$stmt->bind_param("sssi", $client_name, $client_position, $testimonial, $rating);

if ($stmt->execute()) {
    $response['success'] = true;
    $response['message'] = t('review_success');
} else {
    $response['message'] = t('review_err_db');
}

echo json_encode($response);
