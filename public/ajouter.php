<?php
// 1. Chemin modifié : on remonte d'un dossier pour aller chercher config.php
require_once '../config/config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nom = $_POST['nom'];
    $categorie = $_POST['categorie'];
    $quantite = $_POST['quantite'];
    
    $image_name = ""; 

    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        // 2. Chemin modifié : le dossier uploads est maintenant dans public/
        // Puisque ajouter.php est aussi dans public/, le chemin devient relatif
        $target_dir = "uploads/"; 
        
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }

        $image_name = time() . "_" . basename($_FILES["image"]["name"]);
        $target_file = $target_dir . $image_name;

        move_uploaded_file($_FILES["image"]["tmp_name"], $target_file);
    }

    $stmt = $conn->prepare("INSERT INTO objets (nom, categorie, quantite, image_path) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssis", $nom, $categorie, $quantite, $image_name);

    if ($stmt->execute()) {
        // 3. Redirection : index.php est dans le même dossier (public/)
        header("Location: index.php");
        exit(); 
    } else {
        echo "Erreur SQL : " . $conn->error;
    }
}
?>