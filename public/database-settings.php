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
    <?php if (!empty($categories)): ?>
        <div class="settings-section">
            <h2>Renommer les catégories</h2>
            <div class="categories-list">
                <?php foreach ($categories as $category): ?>
                    <form method="POST" class="category-rename-form" style="margin-bottom: 10px; display: flex; gap: 10px;">
                        <input type="hidden" name="action" value="rename_category">
                        <input type="hidden" name="category_id" value="<?php echo $category['id']; ?>">
                        
                        <input type="text" name="new_category" value="<?php echo htmlspecialchars($category['nom']); ?>" required class="form-input">
                        
                        <?php echo CsrfToken::field(); ?>
                        <button type="submit" class="btn-small">Enregistrer</button>
                    </form>
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
