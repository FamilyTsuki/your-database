<?php
require_once '../config/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id']) && isset($_FILES['new_image'])) {
    $id = intval($_POST['id']);
    
    if ($id <= 0) {
        FlashMessage::error('ID invalide');
        header("Location: index.php");
        exit();
    }

    // Valider l'image
    $validation = Validator::validateImageFile($_FILES['new_image']);
    if (!$validation['valid']) {
        FlashMessage::error($validation['message']);
        header("Location: index.php");
        exit();
    }

    $target_dir = "uploads/";
    
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true);
    }

    // Générer un nom de fichier sécurisé
    $ext = strtolower(pathinfo($_FILES["new_image"]["name"], PATHINFO_EXTENSION));
    $allowedExts = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    
    if (!in_array($ext, $allowedExts, true)) {
        FlashMessage::error('Extension non autorisée');
        header("Location: index.php");
        exit();
    }

    $image_name = time() . "_" . bin2hex(random_bytes(8)) . "." . $ext;
    $target_file = $target_dir . $image_name;

    if (move_uploaded_file($_FILES["new_image"]["tmp_name"], $target_file)) {
        // Récupérer l'ancienne image et la supprimer
        $result = $conn->query("SELECT image_path FROM objets WHERE id = $id");
        if ($result && $row = $result->fetch_assoc()) {
            if (!empty($row['image_path'])) {
                $oldImagePath = $target_dir . $row['image_path'];
                if (file_exists($oldImagePath)) {
                    unlink($oldImagePath);
                }
            }
        }

        // Mise à jour de la base de données
        $stmt = $conn->prepare("UPDATE objets SET image_path = ? WHERE id = ?");
        $stmt->bind_param("si", $image_name, $id);
        
        if ($stmt->execute()) {
            FlashMessage::success('Image mise à jour ✓');
        } else {
            FlashMessage::error('Erreur mise à jour BD');
        }
    } else {
        FlashMessage::error('Erreur lors du téléchargement du fichier');
    }
}

header("Location: index.php");
exit();
?>