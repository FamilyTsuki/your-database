<?php
// 1. Chemin modifié pour atteindre la configuration
require_once '../config/config.php';

if (isset($_POST['id']) && isset($_FILES['new_image'])) {
    $id = intval($_POST['id']);
    
    // 2. Chemin du dossier uploads (relatif à ce fichier dans public/)
    $target_dir = "uploads/";
    
    // Sécurité : on vérifie que le dossier existe
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true);
    }

    $image_name = time() . "_" . basename($_FILES["new_image"]["name"]);
    
    if (move_uploaded_file($_FILES["new_image"]["tmp_name"], $target_dir . $image_name)) {
        // Mise à jour de la base de données
        $stmt = $conn->prepare("UPDATE objets SET image_path = ? WHERE id = ?");
        $stmt->bind_param("si", $image_name, $id);
        $stmt->execute();
    }
}

// 3. Redirection vers l'index situé dans le même dossier
header("Location: index.php");
exit();
?>