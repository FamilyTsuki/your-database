<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../src/Helpers/CsrfToken.php';
require_once __DIR__ . '/../../src/Helpers/Auth.php';
require_once __DIR__ . '/../../src/Helpers/ImageHelper.php';
require_once __DIR__ . '/../../src/Models/DatabaseModel.php';
require_once __DIR__ . '/../../src/Controllers/DatabaseController.php';

header('Content-Type: application/json; charset=utf-8');

Auth::initSession();

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
    // 1. Récupération des paramètres de pagination et filtres
    $page = max(1, intval($_GET['page'] ?? 1));
    $limit = max(1, intval($_GET['limit'] ?? 50));
    $offset = ($page - 1) * $limit;
    
    $search = trim($_GET['search'] ?? '');
    $category = trim($_GET['category'] ?? '');
    $sort = $_GET['sort'] ?? 'date_desc';

    // 2. Construction de la requête dynamique
    $whereClauses = ["objets.database_id = ?"];
    $params = [$database_id];
    $types = "i";

    if ($search !== '') {
        $whereClauses[] = "(objets.nom LIKE ? OR objets.description LIKE ? OR objets.model LIKE ?)";
        $term = "%$search%";
        $params[] = $term; $params[] = $term; $params[] = $term;
        $types .= "sss";
    }

    if ($category !== '') {
        $whereClauses[] = "(cat.nom = ? OR parent_cat.nom = ?)";
        $params[] = $category; $params[] = $category;
        $types .= "ss";
    }

    $whereSql = implode(" AND ", $whereClauses);

    // 3. Compter le total (indispensable pour la pagination)
    $countSql = "SELECT COUNT(*) as total 
                 FROM objets 
                 LEFT JOIN categories AS cat ON objets.id_categorie = cat.id 
                 LEFT JOIN categories AS parent_cat ON cat.parent_id = parent_cat.id 
                 WHERE $whereSql";
    
    $stmt = $conn->prepare($countSql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $total = $stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();

    // 4. Récupérer les données paginées
    $orderSql = "objets.id DESC";
    switch ($sort) {
        case 'date_asc': $orderSql = "objets.id ASC"; break;
        case 'alpha_asc': $orderSql = "objets.nom ASC"; break;
        case 'alpha_desc': $orderSql = "objets.nom DESC"; break;
        case 'qty_desc': $orderSql = "objets.quantite DESC"; break;
        case 'qty_asc': $orderSql = "objets.quantite ASC"; break;
    }

    $sql = "SELECT objets.*, cat.nom AS nom_categorie, cat.parent_id, parent_cat.nom AS parent_nom 
            FROM objets 
            LEFT JOIN categories AS cat ON objets.id_categorie = cat.id 
            LEFT JOIN categories AS parent_cat ON cat.parent_id = parent_cat.id 
            WHERE $whereSql 
            ORDER BY $orderSql 
            LIMIT ? OFFSET ?";
    
    // Ajout des params LIMIT et OFFSET
    $params[] = $limit;
    $params[] = $offset;
    $types .= "ii";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();

    $objs = [];
    while ($r = $res->fetch_assoc()) $objs[] = $r;
    $stmt->close();

    $cats = [];
    $stmt = $conn->prepare("SELECT id, nom, parent_id FROM categories WHERE database_id = ? OR database_id IS NULL ORDER BY parent_id ASC, nom ASC");
    $stmt->bind_param("i", $database_id);
    $stmt->execute();
    $cres = $stmt->get_result();
    while ($c = $cres->fetch_assoc()) $cats[] = $c;

    echo json_encode([
        'success' => true, 
        'objects' => $objs, 
        'categories' => $cats,
        'total' => $total, // Renvoi du total pour le JS
        'page' => $page
    ]);
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
$objet_model = new ObjetModel($conn);

if ($action === 'updateQty') {
    $objet_id = intval($_POST['id'] ?? 0);
    $new_qty = intval($_POST['qty'] ?? 0);
    
    $stmt = $conn->prepare("SELECT qty_used, qty_degraded FROM objets WHERE id = ? AND database_id = ?");
    $stmt->bind_param("ii", $objet_id, $database_id);
    $stmt->execute();
    $obj = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$obj) {
        echo json_encode(['success' => false, 'error' => 'Objet introuvable']);
        exit;
    }

    if ($obj) {
        $min_required = intval($obj['qty_used']) + intval($obj['qty_degraded']);
        if ($new_qty < $min_required) {
            echo json_encode(['success' => false, 'error' => "Impossible : Quantité inférieure à la somme (Utilisé + HS = $min_required)"]);
            exit;
        }
    }

    $result = $objet_model->update($objet_id, 'quantite', $new_qty, $database_id);
    echo json_encode(['success' => (bool)$result, 'qty' => $new_qty]);
    exit;
}

if ($action === 'create') {
    $nom = Validator::sanitizeText($_POST['nom'] ?? '', 100);
    $cat_value = $_POST['categorie'] ?? '';
    $quantite = Validator::validateQuantity($_POST['quantite'] ?? 1);

    $id_categorie = 'NULL';
    if (strpos($cat_value, 'NEW:') === 0) {
        $new_cat_name = Validator::sanitizeText(substr($cat_value, 4), 100);
        if (!empty($new_cat_name)) {
            $stmt = $conn->prepare("INSERT INTO categories (nom, database_id) VALUES (?, ?)");
            $stmt->bind_param("si", $new_cat_name, $database_id);
            $stmt->execute();
            $id_categorie = intval($conn->insert_id);
        }
    } elseif (intval($cat_value) > 0) {
        $id_categorie = intval($cat_value);
        
        // SÉCURITÉ : Vérifier que la catégorie appartient bien à cette database
        $check = $conn->prepare("SELECT id FROM categories WHERE id = ? AND database_id = ?");
        $check->bind_param("ii", $id_categorie, $database_id);
        $check->execute();
        if ($check->get_result()->num_rows === 0) {
            http_response_code(403); echo json_encode(['error'=>'Catégorie invalide ou hors de cette base']); exit;
        }
    }

    $image_filename = '';
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['image'];
        // SÉCURITÉ : Utilisation du validateur robuste (finfo) au lieu du type MIME envoyé par le navigateur
        $val = Validator::validateImageFile($file);
        if (!$val['valid']) {
            http_response_code(400);
            echo json_encode(['error' => $val['message']]);
            exit;
        }
        $uploads_dir = __DIR__ . '/../uploads';
        if (!is_dir($uploads_dir)) mkdir($uploads_dir, 0755, true);
        
        $filenameBase = 'obj_' . time() . '_' . bin2hex(random_bytes(4));
        $image_filename = ImageHelper::processAndSave($file['tmp_name'], $uploads_dir, $filenameBase, 1024, 1024);
        
        if (!$image_filename) {
            http_response_code(500);
            echo json_encode(['error' => 'Erreur lors du transfert de l\'image']);
            exit;
        }
    }

    // Insert objet
    $cat_param = ($id_categorie === 'NULL') ? null : $id_categorie;
    $new_id = $objet_model->create($database_id, $nom, $cat_param, $quantite, $image_filename);

    if ($new_id) {
        // Fetch inserted with category names
        $stmt = $conn->prepare("SELECT objets.*, cat.nom AS nom_categorie, parent_cat.nom AS parent_nom FROM objets LEFT JOIN categories AS cat ON objets.id_categorie = cat.id LEFT JOIN categories AS parent_cat ON cat.parent_id = parent_cat.id WHERE objets.id = ? LIMIT 1");
        $stmt->bind_param("i", $new_id);
        $stmt->execute();
        $obj = $stmt->get_result()->fetch_assoc();
        
        echo json_encode(['success' => true, 'object' => $obj]);
        exit;
    }
    
    // Nettoyage : Si l'insertion BDD échoue, on supprime l'image uploadée pour éviter les orphelins
    if ($image_filename && file_exists($uploads_dir . '/' . $image_filename)) @unlink($uploads_dir . '/' . $image_filename);

    http_response_code(500);
    echo json_encode(['error' => 'Erreur insertion BDD']);
    exit;
}

// Create a category (used by client when adding objects and creating subcategories)
if ($action === 'create_category') {
    if ($permission !== 'edit' && $permission !== 'admin') {
        http_response_code(403); echo json_encode(['error'=>'Permission insuffisante']); exit;
    }

    $name = Validator::sanitizeText($_POST['name'] ?? '', 100);
    $parent_id = intval($_POST['parent_id'] ?? 0);
    if ($name === '') { http_response_code(400); echo json_encode(['error'=>'Nom requis']); exit; }

    // SÉCURITÉ : Vérifier que le parent appartient bien à cette database
    if ($parent_id > 0) {
        $check = $conn->prepare("SELECT id FROM categories WHERE id = ? AND database_id = ?");
        $check->bind_param("ii", $parent_id, $database_id);
        $check->execute();
        if ($check->get_result()->num_rows === 0) {
            http_response_code(403); echo json_encode(['error'=>'Catégorie parente invalide ou hors de cette base']); exit;
        }
    }
    
    $stmt = $conn->prepare("INSERT INTO categories (nom, database_id, parent_id) VALUES (?, ?, ?)");
    $pid = ($parent_id > 0) ? $parent_id : null;
    $stmt->bind_param("sii", $name, $database_id, $pid);
    $ok = $stmt->execute();
    
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
    
    $stmt = $conn->prepare("SELECT image_path FROM objets WHERE id = ? AND database_id = ?");
    $stmt->bind_param("ii", $objet_id, $database_id);
    $stmt->execute();
    $objet = $stmt->get_result()->fetch_assoc();
    
    // On tente la suppression en BDD d'abord
    if ($objet_model->delete($objet_id, $database_id)) {
        // Si succès BDD, on supprime l'image physique
        if ($objet && $objet['image_path']) @unlink(__DIR__ . '/../uploads/' . $objet['image_path']);
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false]);
    }
    exit;
}

if ($action === 'edit') {
    $field = $_POST['field'] ?? '';
    $value = $_POST['value'] ?? '';
    $objet_id = intval($_POST['id'] ?? 0);

    if ($field === 'new_subcategory_create') {
        $sub_name = Validator::sanitizeText($value, 100);
        $parent_id = intval($_POST['parent_id'] ?? 0);

        // SÉCURITÉ : Vérifier que le parent appartient bien à cette database
        if ($parent_id > 0) {
            $check = $conn->prepare("SELECT id FROM categories WHERE id = ? AND database_id = ?");
            $check->bind_param("ii", $parent_id, $database_id);
            $check->execute();
            if ($check->get_result()->num_rows === 0) {
                echo json_encode(['success' => false, 'message' => 'Catégorie parente invalide']); exit;
            }
        }
        
        $pid = ($parent_id > 0) ? $parent_id : null;
        $stmt = $conn->prepare("INSERT INTO categories (nom, database_id, parent_id) VALUES (?, ?, ?)");
        $stmt->bind_param("sii", $sub_name, $database_id, $pid); // Note: bind_param avec 'i' et null fonctionne généralement, mais attention aux drivers stricts.
        $stmt->execute();
        $new_id = $conn->insert_id;

        if ($new_id > 0) {
            $result = $objet_model->update($objet_id, 'id_categorie', $new_id, $database_id);
            echo json_encode(['success' => (bool)$result]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Erreur création catégorie']);
        }
        exit;
    }

    if ($field === 'new_category_create') {
        $cat_name = Validator::sanitizeText($value, 100);
        
        $stmt = $conn->prepare("INSERT INTO categories (nom, database_id) VALUES (?, ?)");
        $stmt->bind_param("si", $cat_name, $database_id);
        $stmt->execute();
        $new_id = $conn->insert_id;

        if ($new_id > 0) {
            $result = $objet_model->update($objet_id, 'id_categorie', $new_id, $database_id);
            echo json_encode(['success' => (bool)$result]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Erreur création catégorie']);
        }
        exit;
    }

    if ($field === 'categorie') $field = 'id_categorie';

    $allowedFields = ['nom', 'id_categorie', 'quantite', 'position', 'model', 'purchase_link', 'description', 'qty_used', 'qty_degraded'];
    if (in_array($field, $allowedFields)) {
        // SÉCURITÉ : Distinction stricte entre champs texte (à nettoyer) et champs numériques (à convertir)
        $stringFields = ['nom', 'model', 'purchase_link', 'description'];
        
        if (in_array($field, $stringFields)) {
            $limit = 255;
            if ($field === 'description') $limit = 2000;
            if ($field === 'nom') $limit = 100;
            $clean_val = Validator::sanitizeText($value, $limit);
        } else {
            if (in_array($field, ['quantite', 'qty_used', 'qty_degraded'])) {
                $clean_val = Validator::validateQuantity($value);
            } else {
                $clean_val = intval($value);
            }
            if ($field === 'id_categorie' && $clean_val === 0) $clean_val = null; // Model handles null? Model expects int. 
            // Correction: ObjetModel expects int for id_categorie, but if we pass 0 it might be issue if 0 is not valid.
            // Let's assume 0 or null is handled by DB as NULL if foreign key allows.

            // SÉCURITÉ : Si on change la catégorie, vérifier qu'elle appartient à la base courante
            if ($field === 'id_categorie' && $clean_val !== null) {
                $check = $conn->prepare("SELECT id FROM categories WHERE id = ? AND database_id = ?");
                $check->bind_param("ii", $clean_val, $database_id);
                $check->execute();
                if ($check->get_result()->num_rows === 0) {
                    echo json_encode(['success' => false, 'message' => 'Catégorie invalide ou hors de cette base']); exit;
                }
            }
            
            // Vérification de cohérence des stocks (Total >= Utilisé + HS)
            if (in_array($field, ['quantite', 'qty_used', 'qty_degraded'])) {
                $obj = $objet_model->getById($objet_id, $database_id);
                if ($obj) {
                    $q = ($field === 'quantite') ? $clean_val : intval($obj['quantite']);
                    $u = ($field === 'qty_used') ? $clean_val : intval($obj['qty_used']);
                    $d = ($field === 'qty_degraded') ? $clean_val : intval($obj['qty_degraded']);
                    
                    if ($u + $d > $q) {
                        echo json_encode(['success' => false, 'message' => "Incohérence : Utilisé ($u) + HS ($d) > Total ($q)"]);
                        exit;
                    }
                }
            }
        }
        $result = $objet_model->update($objet_id, $field, $clean_val, $database_id);
        echo json_encode(['success' => (bool)$result]);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Champ non autorisé']);
    exit;
}

if ($action === 'update_full') {
    $objet_id = intval($_POST['id'] ?? 0);
    // On récupère tout le POST sauf action, id, csrf_token, database_id
    $data = $_POST;
    unset($data['action'], $data['id'], $data['csrf_token'], $data['database_id']);
    
    // Validation de cohérence des stocks
    // On récupère les valeurs envoyées ou on garde null pour aller chercher celles en BDD
    $q = isset($data['quantite']) ? Validator::validateQuantity($data['quantite']) : null;
    $u = isset($data['qty_used']) ? Validator::validateQuantity($data['qty_used']) : null;
    $d = isset($data['qty_degraded']) ? Validator::validateQuantity($data['qty_degraded']) : null;

    // Si une des valeurs manque, on récupère l'état actuel de l'objet pour comparer correctement
    if ($q === null || $u === null || $d === null) {
        $curr = $objet_model->getById($objet_id, $database_id);
        if ($curr) {
            if ($q === null) $q = intval($curr['quantite']);
            if ($u === null) $u = intval($curr['qty_used']);
            if ($d === null) $d = intval($curr['qty_degraded']);
        }
    }

    // SÉCURITÉ : Vérifier la catégorie si elle est modifiée
    if (isset($data['id_categorie']) && intval($data['id_categorie']) > 0) {
        $cat_check_id = intval($data['id_categorie']);
        $check = $conn->prepare("SELECT id FROM categories WHERE id = ? AND database_id = ?");
        $check->bind_param("ii", $cat_check_id, $database_id);
        $check->execute();
        if ($check->get_result()->num_rows === 0) {
            echo json_encode(['success' => false, 'error' => 'Catégorie invalide ou hors de cette base']); exit;
        }
    }

    if ($u + $d > $q) {
        echo json_encode(['success' => false, 'error' => 'Incohérence: Utilisé + Dégradé > Total']);
        exit;
    }

    if ($objet_model->updateFull($objet_id, $data, $database_id)) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Erreur mise à jour']);
    }
    exit;
}

// Add subcategory (admin)
if ($action === 'add_subcategory') {
    if ($permission !== 'admin' && $permission !== 'edit') { http_response_code(403); echo json_encode(['error'=>'Permission insuffisante']); exit; }
    $parent_id = intval($_POST['parent_id'] ?? 0);
    $name = Validator::sanitizeText($_POST['name'] ?? '', 100);
    if (empty($name)) { http_response_code(400); echo json_encode(['error'=>'Nom requis']); exit; }

    // SÉCURITÉ : Vérifier que le parent appartient bien à cette database
    if ($parent_id > 0) {
        $check = $conn->prepare("SELECT id FROM categories WHERE id = ? AND database_id = ?");
        $check->bind_param("ii", $parent_id, $database_id);
        $check->execute();
        if ($check->get_result()->num_rows === 0) {
            http_response_code(403); echo json_encode(['error'=>'Catégorie parente invalide']); exit;
        }
    }
    
    $pid = ($parent_id > 0) ? $parent_id : null;
    $stmt = $conn->prepare("INSERT INTO categories (nom, database_id, parent_id) VALUES (?, ?, ?)");
    $stmt->bind_param("sii", $name, $database_id, $pid);
    $ok = $stmt->execute();
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
    $stmt = $conn->prepare("SELECT image_path FROM objets WHERE id = ? AND database_id = ?");
    $stmt->bind_param("ii", $objet_id, $database_id);
    $stmt->execute();
    $objet = $stmt->get_result()->fetch_assoc();
    
    if (!$objet) {
        http_response_code(404);
        echo json_encode(['error' => 'Objet introuvable ou accès refusé']);
        exit;
    }

    $file = $_FILES['image'];
    
    $val = Validator::validateImageFile($file);
    if (!$val['valid']) {
        http_response_code(400);
        echo json_encode(['error' => $val['message']]);
        exit;
    }
    $uploads_dir = __DIR__ . '/../uploads';
    if (!is_dir($uploads_dir)) mkdir($uploads_dir, 0755, true);
    
    $filenameBase = 'obj_' . $objet_id . '_' . time();
    $filename = ImageHelper::processAndSave($file['tmp_name'], $uploads_dir, $filenameBase, 1024, 1024);
    
    if ($filename) {
        // On met à jour la BDD d'abord
        if ($objet_model->update($objet_id, 'image_path', $filename, $database_id)) {
            // Si succès, on supprime l'ancienne image
            if ($objet && $objet['image_path']) @unlink(__DIR__ . '/../uploads/' . $objet['image_path']);
            echo json_encode(['success' => true, 'image_path' => 'uploads/' . $filename]);
        } else {
            // Si échec BDD, on supprime la nouvelle image qui vient d'être créée
            @unlink($uploads_dir . '/' . $filename);
            http_response_code(500);
            echo json_encode(['error' => 'Erreur mise à jour BDD']);
        }
        exit;
    }
    http_response_code(500);
    echo json_encode(['error' => 'Erreur lors de l\'upload']);
    exit;
}

// ADMIN actions for settings
if ($action === 'rename_category') {
    // Autoriser 'edit' pour renommer (correction UX)
    if ($permission !== 'admin' && $permission !== 'edit') { http_response_code(403); echo json_encode(['error'=>'Permission insuffisante']); exit; }
    $category_id = intval($_POST['category_id'] ?? 0);
    $new_name = $_POST['new_name'] ?? '';
    
    if ($db_controller->renameCategory($category_id, $new_name, $database_id)) { echo json_encode(['success'=>true]); } else { echo json_encode(['success'=>false]); }
    exit;
}

if ($action === 'delete_category') {
    if ($permission !== 'admin') { http_response_code(403); echo json_encode(['error'=>'Permission admin requise']); exit; }
    $category_id = intval($_POST['category_id'] ?? 0);
    $result = $db_controller->deleteCategory($category_id, $database_id);
    echo json_encode($result);
    exit;
}

if ($action === 'add_user') {
    if ($permission !== 'admin') { http_response_code(403); echo json_encode(['error'=>'Permission admin requise']); exit; }
    $username = $_POST['username'] ?? '';
    $perm = $_POST['permission'] ?? 'view';
    // find user id
    $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $u = $stmt->get_result()->fetch_assoc();
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
    $stmt = $conn->prepare("SELECT user_id FROM database_permissions WHERE id = ? AND database_id = ? LIMIT 1");
    $stmt->bind_param("ii", $perm_id, $database_id);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    
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
    
    $ok = $db_controller->update($database_id, $name, $description);
    echo json_encode(['success'=>(bool)$ok]);
    exit;
}

echo json_encode(['error' => 'Action inconnue']);
exit;

?>
