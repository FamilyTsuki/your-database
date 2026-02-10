<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../src/Helpers/CsrfToken.php';
require_once __DIR__ . '/../../src/Helpers/Auth.php';
require_once __DIR__ . '/../../src/Controllers/UserController.php';

header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) session_start();

if (!Auth::isLoggedIn()) {
    http_response_code(403);
    echo json_encode(['error' => 'Non connecté']);
    exit;
}

$action = $_POST['action'] ?? '';

if ($action === 'update_profile') {
    if (!CsrfToken::verify($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        echo json_encode(['error' => 'Token CSRF invalide']);
        exit;
    }

    $user_id = Auth::getUserId();
    $username = $_POST['username'] ?? '';
    $email = $_POST['email'] ?? '';
    $file = $_FILES['profile_image'] ?? null;

    $controller = new UserController($conn);
    $result = $controller->updateProfile($user_id, $username, $email, $file);

    echo json_encode($result);
    exit;
}

echo json_encode(['error' => 'Action inconnue']);
exit;
?>