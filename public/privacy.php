<?php
require_once '../config/config.php';

include __DIR__ . '/../templates/includes/header.phtml';
?>

<div class="container-narrow">
    <div class="settings-header">
        <h1>Politique de Confidentialité</h1>
    </div>

    <div class="settings-section">
        <h2>Collecte des données personnelles</h2>
        <p>Nous collectons les informations que vous nous fournissez directement lors de votre inscription, notamment votre nom d'utilisateur et votre adresse e-mail.</p>
        <p>Les données relatives à vos inventaires (noms des objets, quantités, catégories, images) que vous créez sont également stockées de manière sécurisée sur nos serveurs.</p>
    </div>

    <div class="settings-section">
        <h2>Utilisation de vos données</h2>
        <p>Vos données sont utilisées exclusivement pour le bon fonctionnement du service YourDatabase. Elles nous permettent de gérer votre compte, de sécuriser vos accès et de vous fournir les fonctionnalités de l'application. Vos données ne sont jamais partagées, vendues ou louées à des tiers à des fins commerciales.</p>
    </div>

    <div class="settings-section">
        <h2>Vos droits</h2>
        <p>Conformément à la loi "Informatique et Libertés" et au RGPD, vous disposez d'un droit d'accès, de rectification, de suppression et de portabilité des données qui vous concernent. Vous pouvez exercer ces droits en nous contactant à l'adresse suivante : <a href="mailto:[alban.elie590@gmail.com]">alban.elie590@gmail.com</a>.</p>
    </div>
</div>

<?php include __DIR__ . '/../templates/includes/footer.html'; ?>