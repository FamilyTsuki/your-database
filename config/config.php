<?php
$host = 'localhost';
$user = 'root';
$pass = '';
$db = 'maison_db';
$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("La connexion a échoué: " . $conn->connect_error);
}