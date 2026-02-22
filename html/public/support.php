<?php
require_once '../config/config.php';

include __DIR__ . '/../templates/includes/header.phtml';
?>

<div class="container-narrow">
    <div class="settings-header">
        <h1>Aide & Support</h1>
    </div>

    <div class="settings-section">
        <h2>Questions fréquentes (FAQ)</h2>
        <h3>Comment créer une nouvelle base de données ?</h3>
        <p>Depuis le tableau de bord principal (la page d'accueil après connexion), cliquez sur le bouton "Créer une base" et remplissez le formulaire qui apparaît.</p>
        <h3>Comment partager une base avec un autre utilisateur ?</h3>
        <p>Allez dans les "Paramètres" de la base de données concernée, puis utilisez le formulaire dans la section "Utilisateurs" pour inviter un autre utilisateur en entrant son pseudo et en choisissant ses permissions.</p>
    </div>

    <div class="settings-section">
        <h2>Contacter le support</h2>
        <p>Pour toute question technique, suggestion ou problème non résolu par la FAQ, vous pouvez nous contacter par email à l'adresse suivante :</p>
        <p class="support-email"><a href="mailto:alban.elie590@gmail.com">alban.elie590@gmail.com</a></p>
        <p>Nous nous efforçons de répondre dans les plus brefs délais.</p>
    </div>
</div>

<?php include __DIR__ . '/../templates/includes/footer.html'; ?>    