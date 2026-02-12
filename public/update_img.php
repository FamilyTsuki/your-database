<?php
require_once '../config/config.php';

// 1. Auth Check
if (!Auth::isLoggedIn()) {
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id']) && isset($_FILES['new_image'])) {
    // SÉCURITÉ : Vérification CSRF
    if (!CsrfToken::verifyFromPost()) {
        FlashMessage::error('Erreur de sécurité (token invalide)');
        header("Location: index.php");
        exit();
    }

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

    // 2. Permission Check (IDOR)
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

    // 3. Utilisation de ImageHelper pour sécuriser l'upload (re-encoding)
    $target_dir = __DIR__ . '/uploads';
    if (!is_dir($target_dir)) mkdir($target_dir, 0755, true);
    
    $filenameBase = 'obj_' . $id . '_' . time();
    $image_name = ImageHelper::processAndSave($_FILES["new_image"]["tmp_name"], $target_dir, $filenameBase);

    if ($image_name) {
        // Mise à jour de la base de données D'ABORD
        $stmt = $conn->prepare("UPDATE objets SET image_path = ? WHERE id = ?");
        $stmt->bind_param("si", $image_name, $id);
        
        if ($stmt->execute()) {
            // Si succès, on supprime l'ancienne image
            if (!empty($obj['image_path'])) {
                $oldImagePath = $target_dir . '/' . $obj['image_path'];
                if (file_exists($oldImagePath)) @unlink($oldImagePath);
            }
            FlashMessage::success('Image mise à jour ✓');
        } else {
            // Si échec, on supprime la nouvelle image (nettoyage)
            @unlink($target_dir . '/' . $image_name);
            FlashMessage::error('Erreur mise à jour BD');
        }
    } else {
        FlashMessage::error('Erreur lors du téléchargement du fichier');
    }
}

header("Location: index.php");
exit();
?>