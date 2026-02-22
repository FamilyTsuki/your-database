<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/Models/DatabaseModel.php';

if (!Auth::isLoggedIn()) {
    header("Location: login");
    exit();
} 

$database_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$database_id) {
    FlashMessage::error('Base de données non trouvée');
    header("Location: index");
    exit();
}

$user_id = Auth::getUserId();
$conn = $conn;

$db_model = new DatabaseModel($conn);
$permission = $db_model->getPermission($database_id, $user_id);

if (!$permission || ($permission !== 'admin' && $permission !== 'edit')) {
    FlashMessage::error('Accès refusé : Vous n\'avez pas les droits pour ajouter des objets dans cette base');
    header("Location: index");
    exit();
}

$stmt = $conn->prepare("SELECT * FROM `databases` WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $database_id);
$stmt->execute();
$db_info = $stmt->get_result()->fetch_assoc();


$stmt = $conn->prepare("SELECT id, nom, parent_id FROM categories WHERE database_id = ? OR database_id IS NULL ORDER BY parent_id ASC, nom ASC");
$stmt->bind_param("i", $database_id);
$stmt->execute();
$cat_res = $stmt->get_result();
$all_cats = $cat_res->fetch_all(MYSQLI_ASSOC);

$categories_tree = [];
foreach ($all_cats as $c) {
    if ($c['parent_id'] == null) {
        $c['subs'] = [];
        $categories_tree[$c['id']] = $c;
    } else {
        if (isset($categories_tree[$c['parent_id']])) {
            $categories_tree[$c['parent_id']]['subs'][] = $c;
        }
    }
}
$csrf_token = CsrfToken::generate();

include __DIR__ . '/../templates/includes/header.phtml';
?>

<script>
    window.globalCategories = <?php echo json_encode(array_values($categories_tree)); ?>;
    window.csrfToken = '<?php echo $csrf_token; ?>';
    window.databaseId = <?php echo json_encode(intval($database_id)); ?>;
    <?php $user_prefs = Auth::getUser(); ?>
    window.dbRedirectOnAdd = <?php echo json_encode((bool)($user_prefs['redirect_on_add'] ?? true)); ?>;
    window.dbCameraEnabled = <?php echo json_encode((bool)($db_info['camera_enabled'] ?? true)); ?>;
    window.dbSkipSourceModal = <?php echo json_encode((bool)($user_prefs['skip_source_modal'] ?? false)); ?>;
    window.dbPreferGallery = <?php echo json_encode((bool)($user_prefs['prefer_gallery'] ?? false)); ?>;
</script>

<div class="ajouter-container">
    <div class="add-page-header">
        <a href="database/<?= $database_id ?>" class="back-link">← Retour à l'inventaire</a>
    </div>

    <div class="add-page-form">
        <h1>✨ Ajouter un nouvel objet</h1>

        <form method="POST" enctype="multipart/form-data" class="add-form-full">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
            
            <div class="image-section">
                <label>Photo de l'objet</label>
                <div class="drop-zone" id="dropZone">
                    <div id="placeholder">
                        <svg width="50" height="50" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                            <polyline points="17 8 12 3 7 8"></polyline>
                            <line x1="12" y1="3" x2="12" y2="15"></line>
                        </svg>
                        <p>Déposer ou cliquer</p>
                    </div>
                    <img id="previewImage" class="preview-image" style="display:none; max-width: 100%;">
                    <input type="file" name="image" id="fileInput" accept="image/*" style="display:none;">
                </div>
                
                <!-- Zone de recadrage (cachée par défaut) -->
                <div id="cropperWrapper" style="display:none; margin-top: 15px; background: #fff; padding: 10px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
                    <div style="max-height: 500px; overflow: hidden;">
                        <img id="imageToCrop" style="max-width: 100%; display: block;">
                    </div>
                    <button type="button" id="confirmCrop" class="btn btn-primary" style="margin-top: 10px; width: 100%;">✂️ Valider le cadrage</button>
                </div>
            </div>
            

            <div class="form-section">
                <div class="form-group">
                    <label for="objet-nom">Nom de l'objet *</label>
                    <input type="text" id="objet-nom" name="nom" required class="form-input" autocomplete="off" maxlength="20">
                </div>

                <label>Catégorie</label>
                <select name="parent_category" id="main_category_select" class="form-input">
                    <option value="0">-- Sans catégorie --</option>
                    <?php foreach ($categories_tree as $parent): ?>
                        <option value="<?= $parent['id'] ?>">
                            <?= htmlspecialchars($parent['nom']) ?>
                        </option>
                    <?php endforeach; ?>
                    <option value="NEW" class="option-new">+ Créer une nouvelle catégorie</option>
                </select>
            </div>

            <div class="form-group sub-category-container" id="sub_category_container">
                <label>Sous-catégorie</label>
                <select name="categorie" id="sub_category_select" class="form-input">
                    </select>
            </div>
                <div class="form-group">
                    <label for="objet-qty">Quantité</label>
                    <input type="number" id="objet-qty" name="quantite" value="1" min="0" class="form-input">
                </div>
                <div class="form-buttons-section">
                <button type="submit" name="add_object" class="btn btn-primary">✓ Ajouter</button>
                <a href="database/<?= $database_id ?>" class="btn btn-secondary">Annuler</a>
            </div>
            </div>

            
        </form>
    </div>
</div>


<?php include __DIR__ . '/../templates/includes/footer.html'; ?>