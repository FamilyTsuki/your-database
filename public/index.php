<?php
// Inclusion de la config (on remonte d'un dossier vers config/)
require_once '../config/config.php'; 

// Vérifier que l'utilisateur est connecté
if (!Auth::isLoggedIn()) {
    header("Location: login.php");
    exit();
}

// Affichage
include '../templates/includes/header.php';
include '../templates/home.php';
include '../templates/includes/footer.php';
?>