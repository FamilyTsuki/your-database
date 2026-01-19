<?php
/**
 * Script de test pour vérifier la structure du projet
 * Accédez à: http://localhost/your-database/public/test.php
 */

require_once '../config/config.php';

if (!Auth::isLoggedIn()) {
    $test_user_id = null;
} else {
    $test_user_id = $_SESSION['user_id'];
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test - Mon Inventaire</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .test-container {
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
        }
        .test-item {
            padding: 15px;
            margin: 10px 0;
            border-radius: 8px;
            border-left: 4px solid #ddd;
        }
        .test-item.pass {
            background: #d4edda;
            border-color: #28a745;
        }
        .test-item.fail {
            background: #f8d7da;
            border-color: #dc3545;
        }
        .test-item.warning {
            background: #fff3cd;
            border-color: #ffc107;
        }
        code {
            background: #f4f4f4;
            padding: 2px 6px;
            border-radius: 3px;
        }
    </style>
</head>
<body>
    <div class="test-container">
        <h1>🧪 Tests de Configuration</h1>
        
        <?php
        $tests = [];
        
        // Test 1: Connexion BD
        try {
            $result = $conn->query("SELECT 1");
            $tests[] = ['name' => 'Connexion à la base de données', 'pass' => true, 'message' => 'MySQL connecté'];
        } catch (Exception $e) {
            $tests[] = ['name' => 'Connexion à la base de données', 'pass' => false, 'message' => $e->getMessage()];
        }
        
        // Test 2: Table users
        $result = $conn->query("SHOW TABLES LIKE 'users'");
        $tests[] = ['name' => 'Table users', 'pass' => $result->num_rows > 0, 'message' => $result->num_rows > 0 ? 'Table existante' : 'Table manquante'];
        
        // Test 3: Table databases
        $result = $conn->query("SHOW TABLES LIKE 'databases'");
        $tests[] = ['name' => 'Table databases', 'pass' => $result->num_rows > 0, 'message' => $result->num_rows > 0 ? 'Table existante' : 'Table à créer via setup.php'];
        
        // Test 4: Table database_permissions
        $result = $conn->query("SHOW TABLES LIKE 'database_permissions'");
        $tests[] = ['name' => 'Table database_permissions', 'pass' => $result->num_rows > 0, 'message' => $result->num_rows > 0 ? 'Table existante' : 'Table à créer via setup.php'];
        
        // Test 5: Table objets
        $result = $conn->query("SHOW TABLES LIKE 'objets'");
        $tests[] = ['name' => 'Table objets', 'pass' => $result->num_rows > 0, 'message' => $result->num_rows > 0 ? 'Table existante' : 'Table manquante'];
        
        // Test 6: Classe Auth
        $tests[] = ['name' => 'Classe Auth', 'pass' => class_exists('Auth'), 'message' => class_exists('Auth') ? 'Chargée' : 'Non trouvée'];
        
        // Test 7: Classe DatabaseController
        $tests[] = ['name' => 'Classe DatabaseController', 'pass' => class_exists('DatabaseController'), 'message' => class_exists('DatabaseController') ? 'Chargée' : 'Non trouvée'];
        
        // Test 8: Classe Validator
        $tests[] = ['name' => 'Classe Validator', 'pass' => class_exists('Validator'), 'message' => class_exists('Validator') ? 'Chargée' : 'Non trouvée'];
        
        // Test 9: Session
        $tests[] = ['name' => 'Session', 'pass' => session_status() === PHP_SESSION_ACTIVE, 'message' => session_status() === PHP_SESSION_ACTIVE ? 'Active' : 'Inactive'];
        
        // Test 10: Utilisateur authentifié
        $tests[] = ['name' => 'Authentification', 'pass' => $test_user_id !== null, 'message' => $test_user_id !== null ? "Connecté (ID: $test_user_id)" : 'Non connecté'];
        
        // Affichage des résultats
        foreach ($tests as $test) {
            $class = $test['pass'] ? 'pass' : ($test['message'] === 'Table à créer via setup.php' ? 'warning' : 'fail');
            echo '<div class="test-item ' . $class . '">';
            echo '<strong>' . htmlspecialchars($test['name']) . ':</strong> ';
            echo htmlspecialchars($test['message']);
            if ($test['pass']) {
                echo ' ✅';
            } elseif ($class === 'warning') {
                echo ' ⚠️';
            } else {
                echo ' ❌';
            }
            echo '</div>';
        }
        
        // Comptage des tests
        $pass_count = count(array_filter($tests, fn($t) => $t['pass']));
        $total_count = count($tests);
        
        echo '<hr>';
        echo '<p><strong>Résultat: ' . $pass_count . ' / ' . $total_count . ' tests réussis</strong></p>';
        
        if ($pass_count === $total_count) {
            echo '<div class="test-item pass">';
            echo '<strong>✅ Tout fonctionne! Allez à <a href="index.php">index.php</a></strong>';
            echo '</div>';
        } elseif ($pass_count >= $total_count - 2) {
            echo '<div class="test-item warning">';
            echo '<strong>⚠️ Initialisation en cours</strong><br>';
            echo 'Allez à <a href="setup.php">setup.php</a> pour initialiser les tables manquantes';
            echo '</div>';
        } else {
            echo '<div class="test-item fail">';
            echo '<strong>❌ Des erreurs détectées</strong><br>';
            echo 'Vérifiez la configuration dans config/config.php';
            echo '</div>';
        }
        ?>
        
        <hr>
        <h2>🔗 Liens utiles</h2>
        <ul>
            <li><a href="index.php">Aller au Dashboard</a></li>
            <li><a href="setup.php">Initialiser la base de données</a></li>
            <li><a href="login.php">Se connecter</a></li>
            <li><a href="register.php">S'inscrire</a></li>
        </ul>
    </div>
</body>
</html>
