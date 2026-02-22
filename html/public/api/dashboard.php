<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../src/Helpers/Auth.php';
require_once __DIR__ . '/../../src/Helpers/CsrfToken.php';
require_once __DIR__ . '/../../src/Controllers/DatabaseController.php';

header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) session_start();

if (!Auth::isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Non authentifié']);
    exit;
}

$user_id = $_SESSION['user_id'] ?? null;
$db_controller = new DatabaseController($conn);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $list = $db_controller->getAccessible($user_id);
    echo json_encode(['success' => true, 'databases' => $list]);
    exit;
}

$action = $_POST['action'] ?? '';
if ($action === 'create') {
    if (!CsrfToken::verify($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        echo json_encode(['error' => 'Token CSRF invalide']);
        exit;
    }
    $name = $_POST['name'] ?? '';
    $description = $_POST['description'] ?? '';
    $res = $db_controller->create($name, $description, $user_id);
    if (is_array($res) && isset($res['success']) && $res['success']) {
        echo json_encode(['success' => true, 'id' => $res['id']]);
        exit;
    }
    http_response_code(400);
    echo json_encode(['error' => $res['message'] ?? 'Erreur création']);
    exit;
}

echo json_encode(['error' => 'Action inconnue']);
exit;

?>
