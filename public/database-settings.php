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
$permission = $db_controller->getPermission($database_id, $user_id);
if ($permission !== 'admin' && $permission !== 'edit') {
    FlashMessage::error('Vous n\'avez pas accès aux paramètres');
    header("Location: index.php");
    exit();
}

$database = $db_controller->getDatabase($database_id);
if (!$database) {
    FlashMessage::error('Base de données introuvable');
    header("Location: index.php");
    exit();
}

$is_admin = ($permission === 'admin');

// All settings actions are handled via API endpoints (public/api/database.php)
// Récupérer les données
$shared_users = $db_controller->getSharedUsers($database_id);
$categories = $db_controller->getCategories($database_id);

// expose a JS-friendly CSRF token and identifiers for client-side API calls
$csrf_token = CsrfToken::generate();

include __DIR__ . '/../templates/includes/header.phtml';
?>
<script>window.csrfToken = <?php echo json_encode($csrf_token); ?>; window.databaseId = <?php echo json_encode(intval($database_id)); ?>; window.userId = <?php echo json_encode(intval($user_id)); ?>;</script>

<div class="container-narrow">
    <?php echo FlashMessage::render(); ?>
    
    <div class="settings-header">
        <a href="database/<?php echo $database_id; ?>" class="back-link">← Retour</a>
        <h1>⚙️ Paramètres de <?php echo htmlspecialchars($database['name']); ?></h1>
    </div>

    <!-- Paramètres généraux -->
    <div class="settings-section">
        <h2>Informations générales</h2>
        <form id="updateDatabaseForm">
            <input type="hidden" name="action" value="update">
            <?php if ($is_admin): ?>
            <div class="form-group">
                <label for="name">Nom de la base</label>
                <input type="text" name="name" id="name" value="<?php echo htmlspecialchars($database['name']); ?>" required <?php echo !$is_admin ? 'disabled' : ''; ?>>
            </div>
            
            <div class="form-group">
                <label for="description">Description</label>
                <textarea name="description" id="description" rows="3" <?php echo !$is_admin ? 'disabled' : ''; ?>><?php echo htmlspecialchars($database['description'] ?? ''); ?></textarea>
            </div>
             <?php endif; ?>
            <div class="form-group">
                <label for="redirect_on_add" class="checkbox-label">
                    <input type="checkbox" name="redirect_on_add" id="redirect_on_add" value="1" <?php echo ($database['redirect_on_add'] ?? 1) ? 'checked' : ''; ?>>
                    Rediriger vers la liste après un ajout
                </label>
            </div>

            <div class="form-group">
                <label for="skip_source_modal" class="checkbox-label">
                    <input type="checkbox" name="skip_source_modal" id="skip_source_modal" value="1" <?php echo ($database['skip_source_modal'] ?? 0) ? 'checked' : ''; ?>>
                    Ne pas demander la source (ouvrir directement)
                </label>
            </div>

            <div class="form-group margin-left-20" id="prefer_gallery_group" style="display:none;">
                <label for="prefer_gallery" class="checkbox-label">
                    <input type="checkbox" name="prefer_gallery" id="prefer_gallery" value="1" <?php echo ($database['prefer_gallery'] ?? 0) ? 'checked' : ''; ?>>
                    Toujours utiliser la galerie (sinon Caméra)
                </label>
            </div>
             <button id="theme-toggle" class="theme-btn" title="Changer de thème">☀️</button>
            <?php if ($is_admin): ?>
                <button type="button" class="btn-primary" data-action="update-database">Enregistrer</button>
            <?php endif; ?>
        </form>
    </div>

    <!-- Partage d'accès -->
    <?php if ($is_admin): ?>
    <div class="settings-section">
        <h2>Utilisateurs</h2>
        
        <div class="share-form">
            <h3>Ajouter un utilisateur</h3>
            <form id="addUserForm">
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
                
                <button type="button" class="btn-primary" data-action="add-user">Ajouter</button>
            </form>
        </div>

        <?php if (!empty($shared_users)): ?>
            <div class="shared-users">
                <h3>Utilisateurs avec accès</h3>
                <table class="users-table">
                    <thead>
                        <tr>
                            <th>Avatar</th>
                            <th>Nom d'utilisateur</th>
                            <th>Email</th>
                            <th>Permission</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($shared_users as $user): ?>
                            <tr>
                                <td>
                                    <?php if (!empty($user['profile_image'])): ?>
                                        <img src="uploads/profiles/<?php echo htmlspecialchars($user['profile_image']); ?>" alt="Avatar" class="nav-profile-img">
                                    <?php else: ?>
                                        <span style="font-size: 1.5em;">👤</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($user['username']); ?></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td>
                                    <?php if ($is_admin): ?>
                                        <select class="badge badge-<?php echo $user['permission']; ?> permission-toggle" data-action="update-permission" data-user-id="<?php echo $user['id']; ?>">
                                            <option value="view" <?php echo $user['permission'] === 'view' ? 'selected' : ''; ?>>Lecture</option>
                                            <option value="edit" <?php echo $user['permission'] === 'edit' ? 'selected' : ''; ?>>Modif.</option>
                                            <option value="admin" <?php echo $user['permission'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                        </select>
                                    <?php else: ?>
                                        <span class="badge badge-<?php echo $user['permission']; ?>">
                                            <?php echo ['admin' => 'Admin', 'edit' => 'Modif.', 'view' => 'Lecture'][$user['permission']] ?? $user['permission']; ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button type="button" class="btn-danger-small" data-action="remove-user" data-permission-id="<?php echo $user['permission_id']; ?>" data-confirm="Supprimer cet utilisateur?">Supprimer</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Catégories -->
     
    
    <div class="settings-section">
        <h2>Gestion des catégories</h2>
        
        

        <div class="categories-hierarchy">
            <?php 
            $parents = !empty($categories) ? array_filter($categories, fn($c) => is_null($c['parent_id'])) : [];
            
            if (empty($parents)): ?>
                <p style="color: var(--text-muted); font-style: italic;">Aucune catégorie pour le moment.</p>
            <?php else: ?>
                <?php foreach ($parents as $parent): ?>
                    <div class="parent-cat-box">
                        <div class="cat-row">
                            <span class="font-size-1-2">🗃️</span>
                            <input type="text" id="cat-name-<?php echo $parent['id']; ?>" value="<?php echo htmlspecialchars($parent['nom']); ?>" required class="form-input flex-1">
                            <button type="button" class="btn-small" data-action="rename-category" data-category-id="<?php echo $parent['id']; ?>">Enregistrer</button>
                            <?php if ($is_admin): ?>
                            <button type="button" class="btn-danger-small" data-action="delete-category" data-category-id="<?php echo $parent['id']; ?>" data-confirm="Supprimer cette catégorie et ses sous-catégories ?">Supprimer</button>
                            <?php endif; ?>
                        </div>

                        <ul class="cat-list-ul">
                            <?php foreach ($categories as $child): ?>
                                <?php if ($child['parent_id'] == $parent['id']): ?>
                                    <li class="subcat-row">
                                        <span class="text-muted">↳ 🗂️</span>
                                        <input type="text" id="cat-name-<?php echo $child['id']; ?>" value="<?php echo htmlspecialchars($child['nom']); ?>" required class="form-input input-small">
                                        <button type="button" class="btn-small btn-small-padding" data-action="rename-category" data-category-id="<?php echo $child['id']; ?>">OK</button>
                                        <?php if ($is_admin): ?>
                                        <button type="button" class="btn-danger-small btn-small-padding" data-action="delete-category" data-category-id="<?php echo $child['id']; ?>">Suppr.</button>
                                        <?php endif; ?>
                                    </li>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </ul>

                        <div class="add-subcat-row">
                            <input type="text" id="subcat-input-<?php echo $parent['id']; ?>" placeholder="Nouvelle sous-catégorie..." required class="form-input input-small">
                            <button type="button" class="btn-small" data-action="add-subcategory" data-parent-id="<?php echo $parent['id']; ?>">Ajouter</button>
                        </div>
                    </div>
                <?php endforeach; ?>
            
        </div>
        <div class="add-root-cat-row">
            <input type="text" id="new-root-cat-name" placeholder="Nouvelle catégorie principale..." class="form-input flex-1">
            <button type="button" class="btn-primary" data-action="add-root-category">Ajouter</button>
        </div>
    </div>
    <?php endif; ?>

    <!-- Supprimer la base -->
    <?php if ($is_admin): ?>
    <div class="settings-section danger-zone">
        <h2>⚠️ Zone de danger</h2>
        
        <div class="danger-item">
            <h3>Supprimer cette base de données</h3>
            <p>Cette action est <strong>irréversible</strong>. Tous les objets et les permissions seront supprimés.</p>
            
            <form id="deleteForm">
                <input type="hidden" name="action" value="delete">
                
                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="confirm" value="no" id="confirmCheck">
                        Je comprends que cette action est irréversible
                    </label>
                </div>
                
                <button type="button" class="btn-danger" data-action="delete-database">Supprimer la base</button>
            </form>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- submitDelete handled in public/js/app.js -->

<script>
document.addEventListener('DOMContentLoaded', function() {
    const skipCheck = document.getElementById('skip_source_modal');
    const galleryGroup = document.getElementById('prefer_gallery_group');
    const toggle = () => {
        if(skipCheck && galleryGroup) galleryGroup.style.display = skipCheck.checked ? 'block' : 'none';
    };
    if(skipCheck) { skipCheck.addEventListener('change', toggle); toggle(); }
});
</script>
<?php include __DIR__ . '/../templates/includes/footer.html'; ?>
