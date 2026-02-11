<?php
require_once '../config/config.php';

// 1. Auth Check
if (!Auth::isLoggedIn()) {
    header("Location: login.php");
    exit();
}

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
    
    // 2. Validation de la base de données cible
    $database_id = intval($_POST['database_id'] ?? 0);
    if ($database_id <= 0) {
        FlashMessage::error('Base de données non spécifiée');
        header("Location: index.php");
        exit();
    }

    // 3. Vérification des permissions
    $db_model = new DatabaseModel($conn);
    $perm = $db_model->getPermission($database_id, $_SESSION['user_id']);
    if ($perm !== 'admin' && $perm !== 'edit') {
        FlashMessage::error('Action non autorisée');
        header("Location: index.php");
        exit();
    }

    // Validations
    $nom = Validator::sanitizeText($nom, 100);
    $quantite = Validator::validateQuantity($quantite);
    
    // Nouvelle gestion de l'ID de catégorie
    $id_categorie = null;
    $cat_input = $_POST['categorie'] ?? '';

    if ($cat_input === 'NEW') {
        $new_cat_name = Validator::sanitizeText($_POST['new_category'] ?? '', 100);
        if (!empty($new_cat_name)) {
            // On insère la nouvelle catégorie
            $stmt_cat = $conn->prepare("INSERT INTO categories (nom, database_id) VALUES (?, ?)");
            $stmt_cat->bind_param("si", $new_cat_name, $database_id);
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
    $image_name = '';
    if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
        $validation = Validator::validateImageFile($_FILES['image']);
        if (!$validation['valid']) {
            FlashMessage::error($validation['message']);
            header("Location: index.php");
            exit();
        }

        // Utilisation de ImageHelper
        $target_dir = __DIR__ . '/uploads';
        if (!is_dir($target_dir)) mkdir($target_dir, 0755, true);
        
        $filenameBase = 'obj_' . time() . '_' . bin2hex(random_bytes(4));
        $image_name = ImageHelper::processAndSave($_FILES["image"]["tmp_name"], $target_dir, $filenameBase);
        
        if (!$image_name) {
            FlashMessage::error('Erreur lors du traitement de l\'image');
            header("Location: index.php");
            exit();
        }
    }

    // Utilisation du modèle pour l'insertion
    $objet_model = new ObjetModel($conn);
    $new_id = $objet_model->create($database_id, $nom, $id_categorie, $quantite, $image_name);

    if ($new_id) {
        FlashMessage::success('Objet ajouté avec succès ✓');
    } else {
        FlashMessage::error('Erreur lors de l\'ajout');
    }
}

header("Location: index.php");
exit();
?>