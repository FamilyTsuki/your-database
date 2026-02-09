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
</head>
<body>
    <div class="login-container">
        <h1>📦 Mon Inventaire</h1>
        
        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" id="loginForm" data-ajax="true">
            <label for="username">Pseudo</label>
            <input type="text" name="username" id="username" required placeholder="Votre pseudo">
            
            <label for="password">Mot de passe</label>
            <div style="position: relative;">
                <input type="password" name="password" id="password" required placeholder="Votre mot de passe" style="width: 100%; padding-right: 40px;">
                <span id="togglePassword" style="position: absolute; right: 12px; top: 50%; transform: translateY(-50%); cursor: pointer; user-select: none;">🙈</span>
            </div>
            
                <?php echo CsrfToken::field(); ?>

                <button type="submit">Se connecter</button>
        </form>

            <script>window.csrfToken = <?php echo json_encode(CsrfToken::generate()); ?>;</script>
            <script>
                const togglePassword = document.querySelector('#togglePassword');
                const password = document.querySelector('#password');
                togglePassword.addEventListener('click', function (e) {
                    const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
                    password.setAttribute('type', type);
                    this.textContent = type === 'password' ? '🙈' : '👁️';
                });
            </script>

        <div class="register-link">
            <p>Pas encore de compte? <a href="register.php">S'inscrire</a></p>
        </div>
    </div>
</body>
</html>
