<?php
// Temporary cleanup script — deletes user 'ci_tester'.
// Protected by a one-time token. This file will be removed after use.
require_once __DIR__ . '/../config/config.php';

$expected = 'one-time-token-9f8e7d6c5b4a3';
$token = $_GET['token'] ?? '';
header('Content-Type: application/json; charset=utf-8');
if ($token !== $expected) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid token']);
    exit;
}

$username = 'ci_tester';
// Delete user
$stmt = $conn->prepare("DELETE FROM users WHERE username = ? LIMIT 1");
$stmt->bind_param('s', $username);
$ok = $stmt->execute();
echo json_encode(['success' => (bool)$ok, 'deleted_username' => $username]);
exit;

?>
