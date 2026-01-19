<?php
require_once '../config/config.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CsrfToken::verifyFromPost()) {
        $error = 'Erreur de sécurité (token invalide)';
    } else {
        $auth = new Auth($conn);
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';

        $result = $auth->login($username, $password);
        
        if ($result['success']) {
            FlashMessage::success($result['message']);
            header("Location: index.php");
            exit();
        } else {
            $error = $result['message'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - Mon Inventaire</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .login-container {
            max-width: 400px;
            margin: 50px auto;
            background: white;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .login-container h1 {
            text-align: center;
            color: #2c3e50;
            margin-bottom: 30px;
        }
        .login-container form {
            display: flex;
            flex-direction: column;
        }
        .login-container label {
            font-weight: bold;
            color: #34495e;
            margin-top: 15px;
            margin-bottom: 5px;
        }
        .login-container input {
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
        }
        .login-container button {
            background: #27ae60;
            color: white;
            border: none;
            padding: 15px;
            width: 100%;
            border-radius: 8px;
            margin-top: 25px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
        }
        .login-container button:hover {
            background: #219150;
        }
        .login-container .error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .login-container .register-link {
            text-align: center;
            margin-top: 20px;
        }
        .login-container .register-link a {
            color: #3498db;
            text-decoration: none;
        }
        .login-container .register-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <h1>📦 Mon Inventaire</h1>
        
        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST">
            <label for="username">Pseudo</label>
            <input type="text" name="username" id="username" required placeholder="Votre pseudo">
            
            <label for="password">Mot de passe</label>
            <input type="password" name="password" id="password" required placeholder="Votre mot de passe">
            
            <?php echo CsrfToken::field(); ?>
            
            <button type="submit">Se connecter</button>
        </form>

        <div class="register-link">
            <p>Pas encore de compte? <a href="register.php">S'inscrire</a></p>
        </div>
    </div>
</body>
</html>
