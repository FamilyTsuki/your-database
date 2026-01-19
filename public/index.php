<?php
// Inclusion de la config (on remonte d'un dossier vers config/)
require_once '../config/config.php'; 

// Vérifier que l'utilisateur est connecté
if (!Auth::isLoggedIn()) {
    header("Location: login.php");
    exit();
}

// Vérifier l'initialisation
$tables_exist = true;
$required_tables = ['databases', 'database_permissions'];

foreach ($required_tables as $table) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    if ($result->num_rows == 0) {
        header("Location: setup.php");
        exit();
    }
}

$user_id = $_SESSION['user_id'];

// Gérer les actions POST
$action = $_GET['action'] ?? '';

// Créer une nouvelle base de données
if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CsrfToken::verifyFromPost()) {
        FlashMessage::error('Erreur de sécurité (token invalide)');
    } else {
        $db_controller = new DatabaseController($conn);
        $name = $_POST['name'] ?? '';
        $description = $_POST['description'] ?? '';
        
        $result = $db_controller->create($name, $description, $user_id);
        if ($result['success']) {
            FlashMessage::success($result['message']);
            header("Location: index.php");
            exit();
        } else {
            FlashMessage::error($result['message']);
        }
    }
}

// Récupérer les bases de données accessibles
$db_controller = new DatabaseController($conn);
$databases = $db_controller->getAccessible($user_id);

// Affichage
include __DIR__ . '/../templates/includes/header.phtml';
include __DIR__ . '/../templates/dashboard.phtml';
include __DIR__ . '/../templates/includes/footer.html';
?>