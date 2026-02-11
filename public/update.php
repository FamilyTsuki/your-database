<?php
require_once '../config/config.php';

// 1. Vérifier que l'utilisateur est connecté
if (!Auth::isLoggedIn()) {
    header("Location: login.php");
    exit();
}

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

// 2. SÉCURITÉ : Vérifier les permissions (IDOR)
// On récupère l'objet pour connaître sa base de données
$stmt = $conn->prepare("SELECT database_id, image_path FROM objets WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$obj = $stmt->get_result()->fetch_assoc();

if (!$obj) {
    FlashMessage::error('Objet introuvable');
    header("Location: index.php");
    exit();
}

$db_model = new DatabaseModel($conn);
$perm = $db_model->getPermission($obj['database_id'], $_SESSION['user_id']);

if ($perm !== 'admin' && $perm !== 'edit') {
    FlashMessage::error('Action non autorisée');
    header("Location: index.php");
    exit();
}

try {
    if ($action == 'inc') {
        $stmt = $conn->prepare("UPDATE objets SET quantite = quantite + 1 WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        FlashMessage::success('Quantité augmentée ✓');
    } 
    elseif ($action == 'dec') {
        $stmt = $conn->prepare("UPDATE objets SET quantite = GREATEST(0, quantite - 1) WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        FlashMessage::success('Quantité diminuée ✓');
    } 
    elseif ($action == 'delete') {
        // Supprimer aussi l'image associée
        if (!empty($obj['image_path'])) {
            $imagePath = __DIR__ . "/../public/uploads/" . $obj['image_path'];
            if (file_exists($imagePath)) {
                @unlink($imagePath);
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