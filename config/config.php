<?php
// Démarrer la session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Configuration base de données
$host = 'localhost';
$user = 'root';
$pass = '';
$db = 'maison_db';
$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("La connexion a échoué: " . $conn->connect_error);
}

// Charger les helpers
require_once dirname(__DIR__) . '/src/Helpers/Validator.php';
require_once dirname(__DIR__) . '/src/Helpers/FlashMessage.php';
require_once dirname(__DIR__) . '/src/Helpers/CsrfToken.php';
require_once dirname(__DIR__) . '/src/Helpers/Auth.php';

// Charger les models et controllers
require_once dirname(__DIR__) . '/src/Models/ObjetModel.php';
require_once dirname(__DIR__) . '/src/Models/DatabaseModel.php';
require_once dirname(__DIR__) . '/src/Controllers/ObjetController.php';
require_once dirname(__DIR__) . '/src/Controllers/DatabaseController.php';
?>