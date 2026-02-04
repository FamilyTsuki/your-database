<?php

require_once '../config/config.php';

if (!Auth::isLoggedIn()) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$database_id = intval($_GET['id'] ?? 0);

$db_controller = new DatabaseController($conn);
$permission = $db_controller->getPermission($database_id, $user_id);
if (!$permission) {
    FlashMessage::error('Vous n\'avez pas accès à cette base');
    header("Location: index.php");
    exit();
}

$database = $db_controller->getDatabase($database_id);
if (!$database) {
    FlashMessage::error('Base de données introuvable');
    header("Location: index.php");
    exit();
}
// expose CSRF and ids to JS
$csrf_token = CsrfToken::generate();

include __DIR__ . '/../templates/includes/header.phtml';
?>
<script>window.csrfToken = <?php echo json_encode($csrf_token); ?>; window.databaseId = <?php echo json_encode(intval($database_id)); ?>; window.userId = <?php echo json_encode(intval($user_id)); ?>;</script>

<div class="container">
    <?php echo FlashMessage::render(); ?>
    
    <div class="database-header">
        <div class="header-title">
            <a href="index.php" class="back-link">← Retour</a>
            <h1><?php echo htmlspecialchars($database['name']); ?></h1>
            <?php if ($database['description']): ?>
                <p class="subtitle"><?php echo htmlspecialchars($database['description']); ?></p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Barre de recherche et filtres -->
    <div class="search-zone">
        <input type="text" id="searchInput" placeholder="Rechercher un objet...">
        <select id="categoryFilter">
            <option value="">Toutes les catégories</option>
        </select>
        
        <?php if ($permission === 'admin' || $permission === 'edit'): ?>
            <button class="btn-primary" data-action="toggle-add">+ Ajouter un objet</button>
        <?php endif; ?>
        
        <?php if ($permission === 'admin'): ?>
            <a href="database-settings.php?id=<?php echo $database_id; ?>" class="btn-secondary">Paramètres</a>
        <?php endif; ?>
    </div>

    <!-- Formulaire d'ajout -->
    <?php if ($permission === 'admin' || $permission === 'edit'): ?>
        <div id="addForm" class="add-form" style="display:none;">
            <form class="add-form-full">
                <div class="form-group">
                    <label for="nom">Nom *</label>
                    <input type="text" name="nom" id="nom" required placeholder="Nom de l'objet" maxlength="100">
                </div>

                <div class="form-group">
                    <label for="add_item_category_id">Catégorie</label>
                    <select name="categorie" id="add_item_category_id" class="form-input">
                        <option value="0">-- Sans catégorie --</option>
                        <option value="NEW" style="color: #3498db; font-weight: bold;">+ Créer nouvelle catégorie</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="quantite">Quantité</label>
                    <input type="number" name="quantite" id="quantite" value="1" min="0">
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn-primary">Ajouter</button>
                    <button type="button" class="btn-secondary" data-action="toggle-add">Annuler</button>
                </div>
            </form>
        </div>
    <?php endif; ?>

    <!-- Grid des objets (comme l'ancienne page) -->
    <div class="grid" id="inventoryGrid">
        <!-- Client-side rendered inventory (via public/js/app.js) -->
    </div>
</div>

<script>
function toggleAddForm() {
    const form = document.getElementById('addForm');
    if (form) {
        form.style.display = form.style.display === 'none' ? 'block' : 'none';
    }
}
</script>

<?php include __DIR__ . '/../templates/includes/footer.html'; ?>
