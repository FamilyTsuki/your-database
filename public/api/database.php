<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../src/Helpers/CsrfToken.php';
require_once __DIR__ . '/../../src/Helpers/Auth.php';
require_once __DIR__ . '/../../src/Models/DatabaseModel.php';
require_once __DIR__ . '/../../src/Controllers/DatabaseController.php';

header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) session_start();

$action = $_REQUEST['action'] ?? null;
$database_id = intval($_REQUEST['database_id'] ?? ($_GET['id'] ?? 0));
$user_id = $_SESSION['user_id'] ?? null;

$db_model = new DatabaseModel($conn);

if (!$database_id) {
    http_response_code(400);
    echo json_encode(['error' => 'database_id manquant']);
    exit;
}

// Verify permission
$permission = $db_model->getPermission($database_id, $user_id);
if (!$permission) {
    http_response_code(403);
    echo json_encode(['error' => 'Accès refusé']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($action === 'list' || $action === null)) {
    // Return objects + categories
    $objs = [];
    $res = $conn->query("SELECT objets.*, cat.nom AS nom_categorie, cat.parent_id, parent_cat.nom AS parent_nom FROM objets LEFT JOIN categories AS cat ON objets.id_categorie = cat.id LEFT JOIN categories AS parent_cat ON cat.parent_id = parent_cat.id WHERE objets.database_id = '$database_id' ORDER BY objets.id DESC");
    while ($r = $res->fetch_assoc()) $objs[] = $r;

    $cats = [];
    $cres = $conn->query("SELECT id, nom, parent_id FROM categories WHERE database_id = '$database_id' OR database_id IS NULL ORDER BY parent_id ASC, nom ASC");
    while ($c = $cres->fetch_assoc()) $cats[] = $c;

    echo json_encode(['success' => true, 'objects' => $objs, 'categories' => $cats]);
    exit;
}

// For POST requests, verify CSRF
if (!CsrfToken::verify($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['error' => 'Token CSRF invalide']);
    exit;
}

// Only edit/admin can modify
if ($permission !== 'edit' && $permission !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Permission insuffisante']);
    exit;
}

$db_controller = new DatabaseController($conn);

if ($action === 'updateQty') {
    $objet_id = intval($_POST['id'] ?? 0);
    $new_qty = intval($_POST['qty'] ?? 0);
    $result = $conn->query("UPDATE objets SET quantite = $new_qty WHERE id = $objet_id AND database_id = '$database_id' LIMIT 1");
    echo json_encode(['success' => (bool)$result, 'qty' => $new_qty]);
    exit;
}

if ($action === 'create') {
    $nom = $conn->real_escape_string(trim($_POST['nom'] ?? ''));
    $cat_value = $_POST['categorie'] ?? '';
    $quantite = intval($_POST['quantite'] ?? 1);

    $id_categorie = 'NULL';
    if (strpos($cat_value, 'NEW:') === 0) {
        $new_cat_name = $conn->real_escape_string(substr($cat_value, 4));
        if (!empty($new_cat_name)) {
            $conn->query("INSERT INTO categories (nom, database_id) VALUES ('$new_cat_name', '$database_id')");
            $id_categorie = intval($conn->insert_id);
        }
    } elseif (intval($cat_value) > 0) {
        $id_categorie = intval($cat_value);
    }

    $image_filename = '';
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['image'];
        $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($file['type'], $allowed)) {
            http_response_code(400);
            echo json_encode(['error' => 'Type de fichier non autorisé']);
            exit;
        }
        if ($file['size'] > 5242880) {
            http_response_code(400);
            echo json_encode(['error' => 'Fichier trop volumineux']);
            exit;
        }
        $uploads_dir = __DIR__ . '/../uploads';
        if (!is_dir($uploads_dir)) mkdir($uploads_dir, 0755, true);
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $image_filename = 'obj_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        if (!move_uploaded_file($file['tmp_name'], $uploads_dir . '/' . $image_filename)) {
            http_response_code(500);
            echo json_encode(['error' => 'Erreur lors du transfert de l\'image']);
            exit;
        }
    }

    // Insert objet
    $stmt = $conn->prepare("INSERT INTO objets (database_id, nom, id_categorie, quantite, image_path) VALUES (?, ?, ?, ?, ?)");
    $cat_param = ($id_categorie === 'NULL') ? null : $id_categorie;
    $stmt->bind_param("isiss", $database_id, $nom, $cat_param, $quantite, $image_filename);
    if ($stmt->execute()) {
        $new_id = $conn->insert_id;
        // Fetch inserted with category names
        $res = $conn->query("SELECT objets.*, cat.nom AS nom_categorie, parent_cat.nom AS parent_nom FROM objets LEFT JOIN categories AS cat ON objets.id_categorie = cat.id LEFT JOIN categories AS parent_cat ON cat.parent_id = parent_cat.id WHERE objets.id = $new_id LIMIT 1");
        $obj = $res->fetch_assoc();
        echo json_encode(['success' => true, 'object' => $obj]);
        exit;
    }
    http_response_code(500);
    echo json_encode(['error' => 'Erreur insertion BDD', 'debug' => $conn->error]);
    exit;
}

// Create a category (used by client when adding objects and creating subcategories)
if ($action === 'create_category') {
    $name = $conn->real_escape_string(trim($_POST['name'] ?? ''));
    $parent_id = intval($_POST['parent_id'] ?? 0);
    if ($name === '') { http_response_code(400); echo json_encode(['error'=>'Nom requis']); exit; }
    $ok = $conn->query("INSERT INTO categories (nom, database_id, parent_id) VALUES ('$name', '$database_id', " . ($parent_id > 0 ? $parent_id : 'NULL') . ")");
    if ($ok) {
        echo json_encode(['success' => true, 'id' => $conn->insert_id]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $conn->error]);
    }
    exit;
}

if ($action === 'delete') {
    $objet_id = intval($_POST['id'] ?? 0);
    $objet = $conn->query("SELECT image_path FROM objets WHERE id = $objet_id AND database_id = '$database_id' LIMIT 1")->fetch_assoc();
    if ($objet && $objet['image_path']) @unlink(__DIR__ . '/../uploads/' . $objet['image_path']);
    $result = $conn->query("DELETE FROM objets WHERE id = $objet_id AND database_id = '$database_id' LIMIT 1");
    echo json_encode(['success' => (bool)$result]);
    exit;
}

if ($action === 'edit') {
    $field = $_POST['field'] ?? '';
    $value = $_POST['value'] ?? '';
    $objet_id = intval($_POST['id'] ?? 0);

    if ($field === 'new_subcategory_create') {
        $sub_name = $conn->real_escape_string($value);
        $parent_id = intval($_POST['parent_id'] ?? 0);
        $conn->query("INSERT INTO categories (nom, database_id, parent_id) VALUES ('$sub_name', '$database_id', $parent_id)");
        $new_id = $conn->insert_id;
        $result = $conn->query("UPDATE objets SET id_categorie = $new_id WHERE id = $objet_id AND database_id = '$database_id'");
        echo json_encode(['success' => (bool)$result]);
        exit;
    }

    if ($field === 'new_category_create' || $field === 'new_category_create') {
        $cat_name = $conn->real_escape_string($value);
        $conn->query("INSERT INTO categories (nom, database_id) VALUES ('$cat_name', '$database_id')");
        $new_id = $conn->insert_id;
        $result = $conn->query("UPDATE objets SET id_categorie = $new_id WHERE id = $objet_id AND database_id = '$database_id'");
        echo json_encode(['success' => (bool)$result]);
        exit;
    }

    if ($field === 'categorie') $field = 'id_categorie';

    $allowedFields = ['nom', 'id_categorie', 'quantite'];
    if (in_array($field, $allowedFields)) {
        if ($field === 'nom') {
            $clean_val = "'" . $conn->real_escape_string($value) . "'";
        } else {
            $clean_val = intval($value);
            if ($field === 'id_categorie' && $clean_val === 0) $clean_val = "NULL";
        }
        $result = $conn->query("UPDATE objets SET `$field` = $clean_val WHERE id = $objet_id AND database_id = '$database_id' LIMIT 1");
        echo json_encode(['success' => (bool)$result]);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Champ non autorisé']);
    exit;
}

// Add subcategory (admin)
if ($action === 'add_subcategory') {
    if ($permission !== 'admin') { http_response_code(403); echo json_encode(['error'=>'Permission admin requise']); exit; }
    $parent_id = intval($_POST['parent_id'] ?? 0);
    $name = $conn->real_escape_string(trim($_POST['name'] ?? ''));
    if (empty($name)) { http_response_code(400); echo json_encode(['error'=>'Nom requis']); exit; }
    $ok = $conn->query("INSERT INTO categories (nom, database_id, parent_id) VALUES ('$name', '$database_id', $parent_id)");
    if ($ok) echo json_encode(['success'=>true, 'id'=>$conn->insert_id]); else echo json_encode(['success'=>false, 'error'=>$conn->error]);
    exit;
}

if ($action === 'updateImage') {
    $objet_id = intval($_POST['id'] ?? 0);
    if (!isset($_FILES['image'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Aucune image fournie']);
        exit;
    }
    $objet = $conn->query("SELECT image_path FROM objets WHERE id = $objet_id AND database_id = '$database_id' LIMIT 1")->fetch_assoc();
    $file = $_FILES['image'];
    $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!in_array($file['type'], $allowed)) {
        http_response_code(400);
        echo json_encode(['error' => 'Type de fichier non autorisé']);
        exit;
    }
    if ($file['size'] > 5242880) {
        http_response_code(400);
        echo json_encode(['error' => 'Fichier trop volumineux']);
        exit;
    }
    $uploads_dir = __DIR__ . '/../uploads';
    if (!is_dir($uploads_dir)) mkdir($uploads_dir, 0755, true);
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'obj_' . $objet_id . '_' . time() . '.' . $ext;
    $filepath = $uploads_dir . '/' . $filename;
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        if ($objet && $objet['image_path']) @unlink(__DIR__ . '/../uploads/' . $objet['image_path']);
        $conn->query("UPDATE objets SET image_path = '$filename' WHERE id = $objet_id AND database_id = '$database_id' LIMIT 1");
        echo json_encode(['success' => true, 'image_path' => 'uploads/' . $filename]);
        exit;
    }
    http_response_code(500);
    echo json_encode(['error' => 'Erreur lors de l\'upload']);
    exit;
}

// ADMIN actions for settings
if ($action === 'rename_category') {
    if ($permission !== 'admin') { http_response_code(403); echo json_encode(['error'=>'Permission admin requise']); exit; }
    $category_id = intval($_POST['category_id'] ?? 0);
    $new_name = $_POST['new_name'] ?? '';
    if ($db_controller->renameCategory($category_id, $new_name)) { echo json_encode(['success'=>true]); } else { echo json_encode(['success'=>false]); }
    exit;
}

if ($action === 'delete_category') {
    if ($permission !== 'admin') { http_response_code(403); echo json_encode(['error'=>'Permission admin requise']); exit; }
    $category_id = intval($_POST['category_id'] ?? 0);
    if ($db_controller->deleteCategory($category_id, $database_id)['success'] ?? false) { echo json_encode(['success'=>true]); } else { echo json_encode(['success'=>false]); }
    exit;
}

if ($action === 'add_user') {
    if ($permission !== 'admin') { http_response_code(403); echo json_encode(['error'=>'Permission admin requise']); exit; }
    $username = $conn->real_escape_string($_POST['username'] ?? '');
    $perm = $_POST['permission'] ?? 'view';
    // find user id
    $u = $conn->query("SELECT id FROM users WHERE username = '$username' LIMIT 1")->fetch_assoc();
    if (!$u) { http_response_code(404); echo json_encode(['error'=>'Utilisateur non trouvé']); exit; }
    $uid = intval($u['id']);
    $ok = $db_controller->addUser($database_id, $uid, $perm);
    echo json_encode(['success'=>(bool)$ok]);
    exit;
}

if ($action === 'update_permission') {
    if ($permission !== 'admin') { http_response_code(403); echo json_encode(['error'=>'Permission admin requise']); exit; }
    $target_user_id = intval($_POST['user_id'] ?? 0);
    $new_perm = $_POST['new_permission'] ?? 'view';
    
    if ($target_user_id <= 0) { http_response_code(400); echo json_encode(['error'=>'ID utilisateur invalide']); exit; }
    
    $ok = $db_controller->addUser($database_id, $target_user_id, $new_perm);
    echo json_encode(['success'=>(bool)$ok]);
    exit;
}

if ($action === 'remove_user') {
    if ($permission !== 'admin') { http_response_code(403); echo json_encode(['error'=>'Permission admin requise']); exit; }
    $perm_id = intval($_POST['permission_id'] ?? 0);
    // permission_id corresponds to db_permissions.id in some forms; we'll remove by id
    $res = $conn->query("SELECT user_id FROM database_permissions WHERE id = $perm_id AND database_id = '$database_id' LIMIT 1")->fetch_assoc();
    if (!$res) { http_response_code(404); echo json_encode(['error'=>'Permission non trouvée']); exit; }
    $uid = intval($res['user_id']);
    $ok = $db_controller->removeUser($database_id, $uid);
    echo json_encode(['success'=>(bool)$ok]);
    exit;
}

if ($action === 'delete_database') {
    if ($permission !== 'admin') { http_response_code(403); echo json_encode(['error'=>'Permission admin requise']); exit; }
    $confirm = $_POST['confirm'] ?? '';
    if ($confirm !== 'yes') { http_response_code(400); echo json_encode(['error'=>'Confirmation requise']); exit; }
    $ok = $db_controller->delete($database_id);
    echo json_encode(['success'=>(bool)$ok]);
    exit;
}

if ($action === 'update') {
    if ($permission !== 'admin') { http_response_code(403); echo json_encode(['error'=>'Permission admin requise']); exit; }
    $name = $_POST['name'] ?? '';
    $description = $_POST['description'] ?? '';
    $redirect_on_add = intval($_POST['redirect_on_add'] ?? 1);
    $skip_source_modal = intval($_POST['skip_source_modal'] ?? 0);
    $prefer_gallery = intval($_POST['prefer_gallery'] ?? 0);
    
    $ok = $db_controller->update($database_id, $name, $description, $redirect_on_add, $skip_source_modal, $prefer_gallery);
    echo json_encode(['success'=>(bool)$ok]);
    exit;
}

echo json_encode(['error' => 'Action inconnue']);
exit;

?>
