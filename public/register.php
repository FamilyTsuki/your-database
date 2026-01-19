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
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if ($password !== $confirm) {
            $error = 'Les mots de passe ne correspondent pas';
        } else {
            $result = $auth->register($username, $email, $password);
            
            if ($result['success']) {
                $success = $result['message'];
            } else {
                $error = $result['message'];
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscription - Mon Inventaire</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .register-container {
            max-width: 400px;
            margin: 30px auto;
            background: white;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .register-container h1 {
            text-align: center;
            color: #2c3e50;
            margin-bottom: 30px;
        }
        .register-container form {
            display: flex;
            flex-direction: column;
        }
        .register-container label {
            font-weight: bold;
            color: #34495e;
            margin-top: 15px;
            margin-bottom: 5px;
        }
        .register-container input {
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
        }
        .register-container button {
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
        .register-container button:hover {
            background: #219150;
        }
        .register-container .error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .register-container .success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .register-container .login-link {
            text-align: center;
            margin-top: 20px;
        }
        .register-container .login-link a {
            color: #3498db;
            text-decoration: none;
        }
        .register-container .login-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="register-container">
        <h1>📦 Mon Inventaire</h1>
        
        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="success">
                <?php echo htmlspecialchars($success); ?>
                <p style="margin-top: 10px;"><a href="login.php" style="color: #155724;">Cliquez ici pour vous connecter</a></p>
            </div>
        <?php endif; ?>

        <?php if (!$success): ?>
        <form method="POST">
            <label for="username">Pseudo</label>
            <input type="text" name="username" id="username" required placeholder="Votre pseudo (min 3 caractères)">
            
            <label for="email">Email</label>
            <input type="email" name="email" id="email" required placeholder="votre@email.com">
            
            <label for="password">Mot de passe</label>
            <input type="password" name="password" id="password" required placeholder="Min 6 caractères">
            
            <label for="confirm_password">Confirmer le mot de passe</label>
            <input type="password" name="confirm_password" id="confirm_password" required placeholder="Confirmer le mot de passe">
            
            <?php echo CsrfToken::field(); ?>
            
            <button type="submit">S'inscrire</button>
        </form>
        <?php endif; ?>

        <div class="login-link">
            <p>Déjà inscrit? <a href="login.php">Se connecter</a></p>
        </div>
    </div>
</body>
</html>
