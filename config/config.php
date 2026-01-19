<?php

/**
 * Configuration Centrale - Mon Inventaire
 */

// ============================================================================
// SESSION
// ============================================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ============================================================================
// BASE DE DONNÉES
// ============================================================================
$host = 'localhost';
$user = 'root';
$pass = '';
$db = 'maison_db';

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die('Erreur de connexion à la base de données: ' . $conn->connect_error);
}

$conn->set_charset('utf8mb4');

// ============================================================================
// AUTOLOADER PSR-4
// ============================================================================
require_once dirname(__DIR__) . '/src/Autoloader.php';
\Autoloader::register();

// ============================================================================
// IMPORTS GLOBAUX
// ============================================================================
use App\Helpers\Validator;
use App\Helpers\FlashMessage;
use App\Helpers\CsrfToken;
use App\Helpers\Auth;
use App\Models\ObjetModel;
use App\Models\DatabaseModel;
use App\Controllers\ObjetController;
use App\Controllers\DatabaseController;
