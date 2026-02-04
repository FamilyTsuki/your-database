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
            <form method="POST" id="registerForm" data-ajax="true">
            <label for="username">Pseudo</label>
            <input type="text" name="username" id="username" required placeholder="Votre pseudo (min 3 caractères)" autocomplete="username">
            
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

            <script>window.csrfToken = <?php echo json_encode(CsrfToken::generate()); ?>;</script>

        <div class="login-link">
            <p>Déjà inscrit? <a href="login.php">Se connecter</a></p>
        </div>
    </div>
</body>
</html>
