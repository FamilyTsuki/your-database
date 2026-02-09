<?php
require_once '../config/config.php';

include __DIR__ . '/../templates/includes/header.phtml';
?>

<div class="container-narrow">
    <div class="settings-header">
        <h1>Mentions Légales</h1>
    </div>

    <div class="settings-section">
        <h2>Éditeur du site</h2>
        <p><strong>Nom de l'entreprise :</strong> Tsuki's industrys</p>
        <p><strong>Adresse :</strong> 24 Rue Jeanne d'Arc, 45000 Orléans</p>
        <p><strong>Email :</strong> <a href="mailto:[alban.elie590@gmail.com]">alban.elie590@gmail.com</a></p>
    </div>

    <div class="settings-section">
        <h2>Hébergement</h2>
        <p>Ce site est hébergé sur un serveur local (XAMPP) à des fins de développement.</p>
    </div>

    <div class="settings-section">
        <h2>Propriété intellectuelle</h2>
        <p>L'ensemble de ce site relève de la législation française et internationale sur le droit d'auteur et la propriété intellectuelle. Tous les droits de reproduction sont réservés, y compris pour les documents téléchargeables et les représentations iconographiques et photographiques.</p>
    </div>
</div>

<?php include __DIR__ . '/../templates/includes/footer.html'; ?>