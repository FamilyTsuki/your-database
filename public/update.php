<?php
require_once '../config/config.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$action = isset($_GET['action']) ? $_GET['action'] : '';

if ($id <= 0) {
    FlashMessage::error('ID invalide');
    header("Location: index.php");
    exit();
}

// Actions autorisées
$allowedActions = ['inc', 'dec', 'delete'];
if (!in_array($action, $allowedActions, true)) {
    FlashMessage::error('Action non autorisée');
    header("Location: index.php");
    exit();
}

try {
    if ($action == 'inc') {
        $conn->query("UPDATE objets SET quantite = quantite + 1 WHERE id = $id");
        FlashMessage::success('Quantité augmentée ✓');
    } 
    elseif ($action == 'dec') {
        $conn->query("UPDATE objets SET quantite = GREATEST(0, quantite - 1) WHERE id = $id");
        FlashMessage::success('Quantité diminuée ✓');
    } 
    elseif ($action == 'delete') {
        // Supprimer aussi l'image associée
        $result = $conn->query("SELECT image_path FROM objets WHERE id = $id");
        if ($result && $row = $result->fetch_assoc()) {
            if (!empty($row['image_path'])) {
                $imagePath = "uploads/" . $row['image_path'];
                if (file_exists($imagePath)) {
                    unlink($imagePath);
                }
            }
        }
        
        $stmt = $conn->prepare("DELETE FROM objets WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        FlashMessage::success('Objet supprimé ✓');
    }
} catch (Exception $e) {
    FlashMessage::error('Erreur : ' . $e->getMessage());
}

header("Location: index.php");
exit();
?>