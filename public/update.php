<?php
// 1. Chemin modifié pour atteindre la configuration
require_once '../config/config.php';

// Sécurisation des entrées
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$action = isset($_GET['action']) ? $_GET['action'] : '';

if ($id > 0) {
    if ($action == 'inc') {
        // Augmenter la quantité
        $conn->query("UPDATE objets SET quantite = quantite + 1 WHERE id = $id");
    } elseif ($action == 'dec') {
        // Diminuer sans descendre en dessous de 0
        $conn->query("UPDATE objets SET quantite = GREATEST(0, quantite - 1) WHERE id = $id");
    } elseif ($action == 'delete') {
        // Supprimer l'objet
        $stmt = $conn->prepare("DELETE FROM objets WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
    }
}

// 2. Retour à l'index (situé dans le même dossier public/)
header("Location: index.php");
exit();
?>