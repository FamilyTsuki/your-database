<?php
require_once '../config/config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Vérifier le token CSRF
    if (!CsrfToken::verifyFromPost()) {
        FlashMessage::error('Erreur de sécurité (token invalide)');
        header("Location: index.php");
        exit();
    }

    $id = intval($_POST['id'] ?? 0);
    $field = $_POST['field'] ?? '';
    $value = $_POST['value'] ?? '';

    // Vérification de l'ID
    if ($id <= 0) {
        FlashMessage::error('ID invalide');
        header("Location: index.php");
        exit();
    }

    // Whitelist stricte des champs autorisés
    if (!Validator::validateFieldName($field)) {
        FlashMessage::error('Champ non autorisé');
        header("Location: index.php");
        exit();
    }

    // Validations selon le champ
    if ($field === 'nom') {
        if (!Validator::isNotEmpty($value)) {
            FlashMessage::error('Le nom ne peut pas être vide');
            header("Location: index.php");
            exit();
        }
        $value = Validator::sanitizeText($value, 100);
    } 
    elseif ($field === 'categorie') {
        if (!Validator::isNotEmpty($value)) {
            FlashMessage::error('La catégorie ne peut pas être vide');
            header("Location: index.php");
            exit();
        }
        $value = Validator::validateCategory($value);
        if ($value === false) {
            FlashMessage::error('Catégorie invalide');
            header("Location: index.php");
            exit();
        }
    } 
    elseif ($field === 'quantite') {
        $value = Validator::validateQuantity($value);
    }

    // Préparation sécurisée de la requête
    $stmt = $conn->prepare("UPDATE objets SET $field = ? WHERE id = ?");
    
    if ($field === 'quantite') {
        $stmt->bind_param("ii", $value, $id);
    } else {
        $stmt->bind_param("si", $value, $id);
    }
    
    if ($stmt->execute()) {
        FlashMessage::success('Modification enregistrée ✓');
    } else {
        FlashMessage::error('Erreur lors de la mise à jour');
    }
}

header("Location: index.php");
exit();
?>