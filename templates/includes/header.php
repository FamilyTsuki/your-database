<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon Inventaire</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <nav class="top-nav">
        <div class="div-modif-inv">
            <button onclick="showPage('consultation')">📦 Inventaire</button>
            <button onclick="showPage('ajout')" class="btn-add-nav">➕ Ajouter</button>
        </div>
        <div class="user-info">
            <?php 
            $user = Auth::getUser();
            if ($user): 
            ?>
                <span>👤 <?php echo htmlspecialchars($user['username']); ?></span>
                <a href="logout.php">Déconnexion</a>
            <?php endif; ?>
        </div>
    </nav>