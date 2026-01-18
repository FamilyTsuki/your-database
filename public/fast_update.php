<?php
// 1. Chemin modifié pour atteindre la configuration
require_once '../config/config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = intval($_POST['id']);
    $field = $_POST['field']; // 'quantite', 'categorie' ou 'nom'
    $value = $_POST['value'];

    // Sécurité : vérification des champs autorisés
    if ($field === 'quantite' || $field === 'categorie' || $field === 'nom') {
        
        // Préparation de la requête
        $stmt = $conn->prepare("UPDATE objets SET $field = ? WHERE id = ?");
        
        if ($field === 'quantite') {
            $val = intval($value);
            $stmt->bind_param("ii", $val, $id);
        } else {
            $stmt->bind_param("si", $value, $id);
        }
        
        $stmt->execute();
    }
}

// 2. Redirection vers l'index qui est dans le même dossier
header("Location: index.php");
exit();
?>