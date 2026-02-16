<?php
// Inclusion de la config (on remonte d'un dossier vers config/)
require_once '../config/config.php'; 

// Vérifier que l'utilisateur est connecté
if (!Auth::isLoggedIn()) {
    header("Location: login");
    exit();
}

// Vérifier l'initialisation
$tables_exist = true;
$required_tables = ['databases', 'database_permissions'];

foreach ($required_tables as $table) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    if ($result->num_rows == 0) {
        header("Location: setup");
        exit();
    }
}


// Affichage
include __DIR__ . '/../templates/includes/header.phtml';
include __DIR__ . '/../templates/dashboard.phtml';
include __DIR__ . '/../templates/includes/footer.html';
?>