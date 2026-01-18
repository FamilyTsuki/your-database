<?php
require_once '../config/config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Vérifier le token CSRF
    if (!CsrfToken::verifyFromPost()) {
        FlashMessage::error('Erreur de sécurité (token invalide)');
        header("Location: index.php");
        exit();
    }

    $nom = $_POST['nom'] ?? '';
    $categorie = $_POST['categorie'] ?? '';
    $quantite = $_POST['quantite'] ?? 1;
    
    // Validations
    if (!Validator::isNotEmpty($nom)) {
        FlashMessage::error('Le nom est requis');
        header("Location: index.php");
        exit();
    }
    
    if (!Validator::isNotEmpty($categorie)) {
        FlashMessage::error('La catégorie est requise');
        header("Location: index.php");
        exit();
    }

    // Nettoyer les données
    $nom = Validator::sanitizeText($nom, 100);
    $categorie = Validator::validateCategory($categorie);
    if ($categorie === false) {
        FlashMessage::error('Catégorie invalide');
        header("Location: index.php");
        exit();
    }
    $quantite = Validator::validateQuantity($quantite);

    $image_name = ""; 

    // Traiter l'image si présente
    if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
        $validation = Validator::validateImageFile($_FILES['image']);
        if (!$validation['valid']) {
            FlashMessage::error($validation['message']);
            header("Location: index.php");
            exit();
        }

        $target_dir = "uploads/"; 
        
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }

        $ext = strtolower(pathinfo($_FILES["image"]["name"], PATHINFO_EXTENSION));
        $allowedExts = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        
        if (!in_array($ext, $allowedExts, true)) {
            FlashMessage::error('Extension non autorisée');
            header("Location: index.php");
            exit();
        }

        $image_name = time() . "_" . bin2hex(random_bytes(8)) . "." . $ext;
        $target_file = $target_dir . $image_name;

        if (!move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
            FlashMessage::error('Erreur lors du téléchargement');
            header("Location: index.php");
            exit();
        }
    }

    // Insérer en base de données
    $stmt = $conn->prepare("INSERT INTO objets (nom, categorie, quantite, image_path) VALUES (?, ?, ?, ?)");
    if (!$stmt) {
        FlashMessage::error('Erreur préparation requête');
        header("Location: index.php");
        exit();
    }

    $stmt->bind_param("ssis", $nom, $categorie, $quantite, $image_name);

    if ($stmt->execute()) {
        FlashMessage::success('Objet ajouté avec succès ✓');
    } else {
        FlashMessage::error('Erreur lors de l\'ajout: ' . $conn->error);
    }
    $stmt->close();
}

header("Location: index.php");
exit();
?>