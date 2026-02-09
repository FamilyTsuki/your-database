<?php
require_once '../config/config.php';

// Vérifier que l'utilisateur est admin ou propriétaire
if (!Auth::isLoggedIn()) {
    header("Location: login.php");
    exit();
}

$initialized = false;
$error = '';
$success = '';

// Vérifier si les tables existent
$tables_exist = true;
$required_tables = ['databases', 'database_permissions'];

foreach ($required_tables as $table) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    if ($result->num_rows == 0) {
        $tables_exist = false;
        break;
    }
}

if ($tables_exist) {
    $initialized = true;
    // Rediriger vers le dashboard
    header("Location: index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['init'])) {
    if (!CsrfToken::verifyFromPost()) {
        $error = 'Erreur de sécurité';
    } else {
        // Créer les tables
        $sql_queries = [
            "CREATE TABLE IF NOT EXISTS `databases` (
                id INT PRIMARY KEY AUTO_INCREMENT,
                name VARCHAR(100) NOT NULL,
                description TEXT,
                owner_id INT NOT NULL,
                redirect_on_add TINYINT(1) DEFAULT 1,
                camera_enabled TINYINT(1) DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE,
                UNIQUE KEY unique_owner_db (name, owner_id)
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
            
            "ALTER TABLE objets ADD COLUMN database_id INT DEFAULT 1",
            "ALTER TABLE objets ADD FOREIGN KEY (database_id) REFERENCES `databases`(id) ON DELETE CASCADE",
            
            "ALTER TABLE `databases` ADD COLUMN redirect_on_add TINYINT(1) DEFAULT 1",
            "ALTER TABLE `databases` ADD COLUMN camera_enabled TINYINT(1) DEFAULT 1",
            "ALTER TABLE `databases` ADD COLUMN skip_source_modal TINYINT(1) DEFAULT 0",
            "ALTER TABLE `databases` ADD COLUMN prefer_gallery TINYINT(1) DEFAULT 0"
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
            // Insérer la base de données par défaut si elle n'existe pas
            $conn->query("INSERT IGNORE INTO `databases` (id, name, description, owner_id) VALUES (1, 'Ma première base', 'Base par défaut', 1)");
            
            $success = 'Initialisation réussie! Les tables ont été créées.';
            FlashMessage::success($success);
            header("Location: index.php");
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
