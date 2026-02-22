<?php

require_once '../config/config.php'; 

if (!Auth::isLoggedIn()) {
    header("Location: login");
    exit();
}

$tables_exist = true;
$required_tables = ['databases', 'database_permissions'];

foreach ($required_tables as $table) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    if ($result->num_rows == 0) {
        header("Location: setup");
        exit();
    }
}


include __DIR__ . '/../templates/includes/header.phtml';
include __DIR__ . '/../templates/dashboard.phtml';
include __DIR__ . '/../templates/includes/footer.html';
?>