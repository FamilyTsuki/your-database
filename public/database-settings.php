<?php
require_once '../config/config.php';

if (!Auth::isLoggedIn()) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$database_id = intval($_GET['id'] ?? 0);

// Vérifier que l'utilisateur est propriétaire
$db_controller = new DatabaseController($conn);
if (!$db_controller->isOwner($database_id, $user_id)) {
    FlashMessage::error('Vous n\'avez pas accès aux paramètres de cette base');
    header("Location: index.php");
    exit();
}

$database = $db_controller->getDatabase($database_id);
if (!$database) {
    FlashMessage::error('Base de données introuvable');
    header("Location: index.php");
    exit();
}

// Gérer les actions POST
$action = $_POST['action'] ?? '';
if ($action === 'add_category_quick') {
    $cat_name = $_POST['category_name'] ?? '';
    if (!empty($cat_name)) {
        // On utilise la connexion directe ou une méthode du controller
        $stmt = $conn->prepare("INSERT INTO categories (nom, database_id) VALUES (?, ?)");
        $stmt->bind_param("si", $cat_name, $database_id);
        
        if ($stmt->execute()) {
            FlashMessage::success("Catégorie '$cat_name' ajoutée !");
        } else {
            FlashMessage::error("Erreur lors de l'ajout");
        }
    }
    // On redirige vers l'index pour rester sur le dashboard
    header("Location: index.php");
    exit();
}
// Mettre à jour les paramètres
if ($action === 'update' && !CsrfToken::verifyFromPost()) {
    FlashMessage::error('Erreur de sécurité');
} elseif ($action === 'update') {
    $name = $_POST['name'] ?? '';
    $description = $_POST['description'] ?? '';
    
    if ($db_controller->update($database_id, $name, $description)) {
        FlashMessage::success('Paramètres mis à jour!');
        $database = $db_controller->getDatabase($database_id);
    } else {
        FlashMessage::error('Erreur lors de la mise à jour');
    }
}

// Ajouter un utilisateur
if ($action === 'add_user' && !CsrfToken::verifyFromPost()) {
    FlashMessage::error('Erreur de sécurité');
} elseif ($action === 'add_user') {
    $username = $_POST['username'] ?? '';
    $permission = $_POST['permission'] ?? 'view';
    
    // Chercher l'utilisateur
    $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        FlashMessage::error('Utilisateur non trouvé');
    } else {
        $user = $result->fetch_assoc();
        $target_user_id = $user['id'];
        
        if ($db_controller->addUser($database_id, $target_user_id, $permission)) {
            FlashMessage::success('Utilisateur ajouté!');
        } else {
            FlashMessage::error('Erreur lors de l\'ajout');
        }
    }
}

// Supprimer un utilisateur
if ($action === 'remove_user') {
    $permission_id = intval($_POST['permission_id'] ?? 0);
    
    // Récupérer le user_id depuis les permissions
    $stmt = $conn->prepare("SELECT user_id FROM database_permissions WHERE id = ? AND database_id = ?");
    $stmt->bind_param("ii", $permission_id, $database_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $perm = $result->fetch_assoc();
        $target_user_id = $perm['user_id'];
        
        if ($db_controller->removeUser($database_id, $target_user_id)) {
            FlashMessage::success('Utilisateur supprimé!');
        } else {
            FlashMessage::error('Erreur lors de la suppression');
        }
    }
}

// Renommer une catégorie
if ($action === 'rename_category' && !CsrfToken::verifyFromPost()) {
    FlashMessage::error('Erreur de sécurité');
} elseif ($action === 'rename_category') {
    $cat_id = intval($_POST['category_id'] ?? 0); // On récupère l'ID
    $new_cat_name = $_POST['new_category'] ?? '';

    // On appelle la fonction avec l'ID (le database_id n'est plus utile si l'ID est unique)
    if ($db_controller->renameCategory($cat_id, $new_cat_name)) {
        FlashMessage::success('Catégorie renommée!');
    } else {
        FlashMessage::error('Erreur lors du renommage');
    }
}
// Supprimer une catégorie
if ($action === 'delete_category' && !CsrfToken::verifyFromPost()) {
    FlashMessage::error('Erreur de sécurité');
} elseif ($action === 'delete_category') {
    $cat_id = intval($_POST['category_id'] ?? 0);

    // Vérifier si la catégorie appartient bien à cette base avant de supprimer
    // (Sécurité supplémentaire)
    if ($db_controller->deleteCategory($cat_id , $database_id)) {
        FlashMessage::success('Catégorie supprimée !');
    } else {
        FlashMessage::error('Erreur lors de la suppression (vérifiez si elle est utilisée)');
    }
}
// Supprimer la base de données
if ($action === 'delete' && !CsrfToken::verifyFromPost()) {
    FlashMessage::error('Erreur de sécurité');
} elseif ($action === 'delete' && $_POST['confirm'] === 'yes') {
    if ($db_controller->delete($database_id)) {
        FlashMessage::success('Base de données supprimée!');
        header("Location: index.php");
        exit();
    } else {
        FlashMessage::error('Erreur lors de la suppression');
    }
}
if ($action === 'edit') {
    $field = $_POST['field'] ?? '';
    $value = $_POST['value'] ?? '';
    $id_objet = intval($_POST['id'] ?? 0);

    // À ajouter dans database-settings.php dans le bloc action === 'edit'
    if ($field === 'new_subcategory_create') {
        $sub_name = $conn->real_escape_string($value); // Le nom tapé dans le prompt
        $parent_id = intval($_POST['parent_id']);    // L'ID du parent sélectionné
        
        // 1. Création de la sous-catégorie
        $conn->query("INSERT INTO categories (nom, database_id, parent_id) VALUES ('$sub_name', '$database_id', $parent_id)");
        $new_id = $conn->insert_id;
        
        // 2. Assignation à l'objet
        $result = $conn->query("UPDATE objets SET id_categorie = $new_id WHERE id = $id_objet");
        die(json_encode(['success' => $result]));
    }
    // CAS : CRÉATION D'UNE NOUVELLE CATÉGORIE
    if ($field === 'new_category_create') {
        $cat_name = $conn->real_escape_string($value);
        
        // 1. Créer la catégorie
        $conn->query("INSERT INTO categories (nom, database_id) VALUES ('$cat_name', '$database_id')");
        $new_id = $conn->insert_id;
        
        // 2. Lier l'objet à cet ID
        $result = $conn->query("UPDATE objets SET id_categorie = $new_id WHERE id = $id_objet");
        
        die(json_encode(['success' => $result]));
    }
    
    // CAS CLASSIQUE : MISE À JOUR ID
    if ($field === 'id_categorie') {
        $id_cat = intval($value) > 0 ? intval($value) : "NULL";
        $result = $conn->query("UPDATE objets SET id_categorie = $id_cat WHERE id = $id_objet");
        die(json_encode(['success' => $result]));
    }
}
if ($action === 'add_subcategory') {
    $parent_id = intval($_POST['parent_id']);
    $name = $_POST['subcategory_name'];
    
    $stmt = $conn->prepare("INSERT INTO categories (nom, database_id, parent_id) VALUES (?, ?, ?)");
    $stmt->bind_param("sii", $name, $database_id, $parent_id);
    
    if ($stmt->execute()) {
        FlashMessage::success('Sous-catégorie ajoutée !');
    }
}
// Récupérer les données
$shared_users = $db_controller->getSharedUsers($database_id);
$categories = $db_controller->getCategories($database_id);

include __DIR__ . '/../templates/includes/header.phtml';
?>

<div class="container">
    <?php echo FlashMessage::render(); ?>
    
    <div class="settings-header">
        <a href="database-view.php?id=<?php echo $database_id; ?>" class="back-link">← Retour</a>
        <h1>⚙️ Paramètres de <?php echo htmlspecialchars($database['name']); ?></h1>
    </div>

    <!-- Paramètres généraux -->
    <div class="settings-section">
        <h2>Informations générales</h2>
        <form method="POST">
            <input type="hidden" name="action" value="update">
            
            <div class="form-group">
                <label for="name">Nom de la base</label>
                <input type="text" name="name" id="name" value="<?php echo htmlspecialchars($database['name']); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="description">Description</label>
                <textarea name="description" id="description" rows="3"><?php echo htmlspecialchars($database['description'] ?? ''); ?></textarea>
            </div>
            
            <?php echo CsrfToken::field(); ?>
            <button type="submit" class="btn-primary">Enregistrer</button>
        </form>
    </div>

    <!-- Partage d'accès -->
    <div class="settings-section">
        <h2>Partage d'accès</h2>
        
        <div class="share-form">
            <h3>Ajouter un utilisateur</h3>
            <form method="POST">
                <input type="hidden" name="action" value="add_user">
                
                <div class="form-group">
                    <label for="username">Nom d'utilisateur</label>
                    <input type="text" name="username" id="username" required placeholder="Entrez le pseudo">
                </div>
                
                <div class="form-group">
                    <label for="permission">Permission</label>
                    <select name="permission" id="permission">
                        <option value="view">Lecture seule</option>
                        <option value="edit">Modifier</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
                
                <?php echo CsrfToken::field(); ?>
                <button type="submit" class="btn-primary">Ajouter</button>
            </form>
        </div>

        <?php if (!empty($shared_users)): ?>
            <div class="shared-users">
                <h3>Utilisateurs avec accès</h3>
                <table class="users-table">
                    <thead>
                        <tr>
                            <th>Nom d'utilisateur</th>
                            <th>Email</th>
                            <th>Permission</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($shared_users as $user): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($user['username']); ?></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td>
                                    <span class="badge badge-<?php echo $user['permission']; ?>">
                                        <?php 
                                            $labels = ['admin' => 'Admin', 'edit' => 'Modif.', 'view' => 'Lecture'];
                                            echo $labels[$user['permission']] ?? $user['permission'];
                                        ?>
                                    </span>
                                </td>
                                <td>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="action" value="remove_user">
                                        <input type="hidden" name="permission_id" value="<?php echo $user['permission_id']; ?>">
                                        <?php echo CsrfToken::field(); ?>
                                        <button type="submit" class="btn-danger-small" onclick="return confirm('Supprimer cet utilisateur?')">Supprimer</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Catégories -->
     
    <?php if (!empty($categories)): 
        $parents = array_filter($categories, fn($c) => is_null($c['parent_id']));
        ?>
        <div class="settings-section">
            <h2>Renommer les catégories</h2>
            <div class="categories-list">
                <?php foreach ($parents as $category): ?>
                    <form method="POST" style="margin-bottom: 10px; display: flex; gap: 10px;">
                        <input type="hidden" name="action" value="rename_category">
                        <input type="hidden" name="category_id" value="<?php echo $category['id']; ?>">
                        <?php echo CsrfToken::field(); ?>

                        <input type="text" name="new_category" value="<?php echo htmlspecialchars($category['nom']); ?>" required class="form-input">
                        
                        <button type="submit" class="btn-small">Enregistrer</button>

                        <button type="submit" name="action" value="delete_category" class="btn-danger-small" onclick="return confirm('Supprimer ?')">
                            Supprimer
                        </button>
                    </form>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="settings-section">
    <h2>Gestion des catégories et sous-catégories</h2>
    <div class="categories-hierarchy">
        <?php 
        // Filtrer pour n'avoir que les catégories qui n'ont pas de parent
        
        foreach ($parents as $parent): ?>
            <div class="parent-cat-box" style="background: #f9f9f9; padding: 15px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #3498db;">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <strong>📁 <?php echo htmlspecialchars($parent['nom']); ?></strong>
                </div>

                <ul style="list-style: none; margin: 10px 0 10px 20px; padding: 0;">
                    <?php foreach ($categories as $child): ?>
                        <?php if ($child['parent_id'] == $parent['id']): ?>
                            <li style="display: flex; gap: 10px; align-items: center; margin-bottom: 5px;">
                                ↳ 📄 <?php echo htmlspecialchars($child['nom']); ?>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="action" value="delete_category">
                                    <input type="hidden" name="category_id" value="<?php echo $child['id']; ?>">
                                    <?php echo CsrfToken::field(); ?>
                                    <button type="submit" style="color:red; background:none; border:none; cursor:pointer; font-size: 0.8em;">[Supprimer]</button>
                                </form>
                            </li>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </ul>

                <form method="POST" style="display: flex; gap: 5px;">
                    <input type="hidden" name="action" value="add_subcategory">
                    <input type="hidden" name="parent_id" value="<?php echo $parent['id']; ?>">
                    <input type="text" name="subcategory_name" placeholder="Nouvelle sous-catégorie..." required class="form-input" style="font-size: 0.9em;">
                    <?php echo CsrfToken::field(); ?>
                    <button type="submit" class="btn-small">Ajouter</button>
                </form>
            </div>
        <?php endforeach; ?>
    </div>
</div>
    <?php endif; ?>

    <!-- Supprimer la base -->
    <div class="settings-section danger-zone">
        <h2>⚠️ Zone de danger</h2>
        
        <div class="danger-item">
            <h3>Supprimer cette base de données</h3>
            <p>Cette action est <strong>irréversible</strong>. Tous les objets et les permissions seront supprimés.</p>
            
            <form method="POST" id="deleteForm">
                <input type="hidden" name="action" value="delete">
                <?php echo CsrfToken::field(); ?>
                
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="confirm" value="no" id="confirmCheck">
                        Je comprends que cette action est irréversible
                    </label>
                </div>
                
                <button type="button" class="btn-danger" onclick="submitDelete()">Supprimer la base</button>
            </form>
        </div>
    </div>
</div>

<script>
function submitDelete() {
    const checkbox = document.getElementById('confirmCheck');
    if (checkbox.checked) {
        const form = document.getElementById('deleteForm');
        const input = form.querySelector('input[name="confirm"]');
        input.value = 'yes';
        form.submit();
    } else {
        alert('Veuillez cocher la case pour confirmer');
    }
}
</script>

<?php include __DIR__ . '/../templates/includes/footer.html'; ?>
