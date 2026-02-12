<?php
require_once '../config/config.php';
require_once '../src/Models/ObjetModel.php';

if (!Auth::isLoggedIn()) {
    FlashMessage::error('Vous devez être connecté');
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

    $id = intval($_POST['id'] ?? 0);
    $field = $_POST['field'] ?? '';
    $value = $_POST['value'] ?? '';

    // Vérification de l'ID
    if ($id <= 0) {
        FlashMessage::error('ID invalide');
        header("Location: index.php");
        exit();
    }

    // SÉCURITÉ : Vérifier les permissions sur l'objet
    // On joint les tables pour vérifier si l'utilisateur est propriétaire ou a une permission edit/admin
    $permStmt = $conn->prepare("
        SELECT d.owner_id, dp.permission, o.database_id 
        FROM objets o
        JOIN `databases` d ON o.database_id = d.id
        LEFT JOIN database_permissions dp ON d.id = dp.database_id AND dp.user_id = ?
        WHERE o.id = ?
    ");
    $userId = $_SESSION['user_id'];
    $permStmt->bind_param("ii", $userId, $id);
    $permStmt->execute();
    $permResult = $permStmt->get_result();
    $permRow = $permResult->fetch_assoc();

    $hasAccess = $permRow && ($permRow['owner_id'] == $userId || in_array($permRow['permission'], ['admin', 'edit']));
    $database_id = $permRow['database_id'] ?? 0;

    if (!$hasAccess) {
        FlashMessage::error('Accès refusé : Vous n\'avez pas les droits de modification sur cet objet');
        header("Location: index.php");
        exit();
    }

    // Whitelist stricte des champs autorisés
    if (!Validator::validateFieldName($field)) {
        FlashMessage::error('Champ non autorisé');
        header("Location: index.php");
        exit();
    }

    // Validations selon le champ
    if ($field === 'nom') {
        if (!Validator::isNotEmpty($value)) {
            FlashMessage::error('Le nom ne peut pas être vide');
            header("Location: index.php");
            exit();
        }
        $value = Validator::sanitizeText($value, 100);
    } 
    elseif ($field === 'id_categorie') { // Nom du champ mis à jour
        $value = intval($value);
        if ($value <= 0) {
            $value = null; // Autorise "Sans catégorie"
        }
    }
    elseif ($field === 'quantite') {
        $value = Validator::validateQuantity($value);
    }
    else{
         FlashMessage::error('Champ non autorisé');
         header("Location: index.php");
         exit();
    }

    // Utilisation du modèle pour la mise à jour (plus sûr et cohérent)
    $objetModel = new ObjetModel($conn);
    $result = $objetModel->update($id, $field, $value, $database_id);
    
    if ($result) {
        FlashMessage::success('Modification enregistrée ✓');
    } else {
        FlashMessage::error('Erreur lors de la mise à jour');
    }
}

header("Location: index.php");
exit();
?>