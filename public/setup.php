<?php
require_once '../config/config.php';



$initialized = false;
$error = '';
$success = '';

// Vérifier si les tables existent
$tables_exist = true;
$required_tables = ['users', 'databases', 'database_permissions', 'categories', 'objets'];

foreach ($required_tables as $table) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    if ($result->num_rows == 0) {
        $tables_exist = false;
        break;
    }
}

// Si le système est déjà installé, on protège l'accès (seul un admin connecté peut y retourner)
if ($tables_exist && !Auth::isLoggedIn()) {
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['init'])) {
    if (!CsrfToken::verifyFromPost()) {
        $error = 'Erreur de sécurité';
    } else {
        // Créer les tables
        $sql_queries = [
            "CREATE TABLE IF NOT EXISTS `users` (
                id INT PRIMARY KEY AUTO_INCREMENT,
                username VARCHAR(50) NOT NULL UNIQUE,
                email VARCHAR(100) NOT NULL UNIQUE,
                password VARCHAR(255) NOT NULL,
                profile_image VARCHAR(255) DEFAULT NULL,
                redirect_on_add TINYINT(1) DEFAULT 1,
                skip_source_modal TINYINT(1) DEFAULT 0,
                prefer_gallery TINYINT(1) DEFAULT 0,
                dark_mode TINYINT(1) DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )",

            "CREATE TABLE IF NOT EXISTS `databases` (
                id INT PRIMARY KEY AUTO_INCREMENT,
                name VARCHAR(100) NOT NULL,
                description TEXT,
                owner_id INT NOT NULL,
                camera_enabled TINYINT(1) DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE,
                UNIQUE KEY unique_owner_db (name, owner_id)
            )",

            "CREATE TABLE IF NOT EXISTS `categories` (
                id INT PRIMARY KEY AUTO_INCREMENT,
                nom VARCHAR(100) NOT NULL,
                parent_id INT DEFAULT NULL,
                database_id INT DEFAULT NULL,
                FOREIGN KEY (database_id) REFERENCES `databases`(id) ON DELETE CASCADE,
                FOREIGN KEY (parent_id) REFERENCES categories(id) ON DELETE SET NULL
            )",

            "CREATE TABLE IF NOT EXISTS `objets` (
                id INT PRIMARY KEY AUTO_INCREMENT,
                nom VARCHAR(100) NOT NULL,
                id_categorie INT DEFAULT NULL,
                database_id INT NOT NULL,
                position VARCHAR(255) DEFAULT NULL,
                quantite INT DEFAULT 1,
                image_path VARCHAR(255) DEFAULT NULL,
                model VARCHAR(255) DEFAULT NULL,
                purchase_link TEXT DEFAULT NULL,
                description TEXT DEFAULT NULL,
                qty_used INT DEFAULT 0,
                qty_degraded INT DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (id_categorie) REFERENCES categories(id) ON DELETE SET NULL,
                FOREIGN KEY (database_id) REFERENCES `databases`(id) ON DELETE CASCADE
            )",


            
            "CREATE TABLE IF NOT EXISTS `database_permissions` (
                id INT PRIMARY KEY AUTO_INCREMENT,
                database_id INT NOT NULL,
                user_id INT NOT NULL,
                permission VARCHAR(20) DEFAULT 'view',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (database_id) REFERENCES `databases`(id) ON DELETE CASCADE,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                UNIQUE KEY unique_permission (database_id, user_id)
            )",
            

        ];
        
        $all_ok = true;
        foreach ($sql_queries as $query) {
            if (!$conn->query($query)) {
                // Vérifier si c'est juste une colonne déjà existante
                if (strpos($conn->error, 'Duplicate column') === false && 
                    strpos($conn->error, 'already exists') === false) {
                    $error .= "Erreur: " . $conn->error . "<br>";
                    $all_ok = false;
                }
            }
        }
        
        if ($all_ok) {
   
            $success = 'Initialisation réussie! Les tables ont été créées.';
            FlashMessage::success($success);
           // On redirige vers l'inscription car aucun utilisateur n'existe encore
            header("Location: register.php");
            exit();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Initialisation - Mon Inventaire</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="container">
        <?php echo FlashMessage::render(); ?>
        
        <div class="setup-container">
            <h1>📦 Initialisation du système</h1>
            
            <?php if (!$initialized): ?>
                <p>Bienvenue! Le système doit être initialisé pour fonctionner correctement.</p>
                
                <?php if ($error): ?>
                    <div class="error"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <form method="POST" id="setupForm" data-ajax="true">
                    <p>Cliquez sur le bouton ci-dessous pour créer les tables nécessaires:</p>
                    
                    <?php echo CsrfToken::field(); ?>
                    <input type="hidden" name="init" value="1">
                    
                    <button type="submit" class="btn-primary">Initialiser la base de données</button>
                </form>
            <?php else: ?>
                <p>✓ Le système est déjà initialisé!</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
