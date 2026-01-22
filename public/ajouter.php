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
    
    // Validations$nom = Validator::sanitizeText($nom, 100);
    $quantite = Validator::validateQuantity($quantite);
    
    // Nouvelle gestion de l'ID de catégorie
    $id_categorie = null;
    $cat_input = $_POST['categorie'] ?? '';

    if ($cat_input === 'NEW') {
        $new_cat_name = Validator::sanitizeText($_POST['new_category'] ?? '', 100);
        if (!empty($new_cat_name)) {
            // On insère la nouvelle catégorie
            // Note: Ajustez database_id si nécessaire selon votre contexte
            $stmt_cat = $conn->prepare("INSERT INTO categories (nom) VALUES (?)");
            $stmt_cat->bind_param("s", $new_cat_name);
            if ($stmt_cat->execute()) {
                $id_categorie = $conn->insert_id;
            }
            $stmt_cat->close();
        }
    } else {
        $id_categorie = intval($cat_input) > 0 ? intval($cat_input) : null;
    }

    if ($id_categorie === null && $cat_input !== "") {
        FlashMessage::error('Catégorie invalide');
        header("Location: index.php");
        exit();
    }
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

    // Insérer en base de données (id_categorie au lieu de categorie)
    $stmt = $conn->prepare("INSERT INTO objets (nom, id_categorie, quantite, image_path) VALUES (?, ?, ?, ?)");
    if (!$stmt) {
        FlashMessage::error('Erreur préparation requête');
        header("Location: index.php");
        exit();
    }

    // Le type change : "siis" (string, int, int, string)
    $stmt->bind_param("siis", $nom, $id_categorie, $quantite, $image_name);

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