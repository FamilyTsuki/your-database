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
    $redirect_on_add = isset($_POST['redirect_on_add']) ? 1 : 0;
    $skip_source_modal = isset($_POST['skip_source_modal']) ? 1 : 0;
    $prefer_gallery = isset($_POST['prefer_gallery']) ? 1 : 0;
    $dark_mode = isset($_POST['dark_mode']) ? 1 : 0;

    $controller = new UserController($conn);
    $result = $controller->updateProfile($user_id, $username, $email, $file, $redirect_on_add, $skip_source_modal, $prefer_gallery, $dark_mode);

    echo json_encode($result);
    exit;
}

echo json_encode(['error' => 'Action inconnue']);
exit;
?>