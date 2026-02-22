<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$host = 'localhost';
$user = 'familytsuki';
$pass = 'Tsuki4545!';
$db = 'your_home_db';
$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("La connexion a échoué: " . $conn->connect_error);
}

require_once dirname(__DIR__) . '/src/Helpers/Validator.php';
require_once dirname(__DIR__) . '/src/Helpers/FlashMessage.php';
require_once dirname(__DIR__) . '/src/Helpers/CsrfToken.php';
require_once dirname(__DIR__) . '/src/Helpers/Auth.php';

require_once dirname(__DIR__) . '/src/Models/ObjetModel.php';
require_once dirname(__DIR__) . '/src/Models/DatabaseModel.php';
require_once dirname(__DIR__) . '/src/Controllers/DatabaseController.php';
?>