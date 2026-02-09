<?php
require_once __DIR__ . '/../config/config.php';

// 1. Authentification
if (!Auth::isLoggedIn()) {
    header("Location: login.php");
    exit();
}

// 2. Récupération et validation de l'ID
$database_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$database_id) {
    FlashMessage::error('Base de données non trouvée');
    header("Location: index.php");
    exit();
}

$user_id = Auth::getUserId();
$conn = $conn; // Assumé défini dans config.php

// Form submit is handled client-side via public/js/app.js and the API endpoint.

// 4. Données pour la vue
$cat_res = $conn->query("SELECT id, nom, parent_id FROM categories WHERE database_id = $database_id OR database_id IS NULL ORDER BY parent_id ASC, nom ASC");
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
    // Passer les catégories au JavaScript
    window.globalCategories = <?php echo json_encode(array_values($categories_tree)); ?>;
    window.csrfToken = '<?php echo $csrf_token; ?>';
    window.databaseId = <?php echo json_encode(intval($database_id)); ?>;
</script>

<div class="ajouter-container">
    <div class="add-page-header">
        <a href="database-view.php?id=<?= $database_id ?>" class="back-link">← Retour à l'inventaire</a>
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
            </div>

            <div class="form-section">
                <div class="form-group">
                    <label for="objet-nom">Nom de l'objet *</label>
                    <input type="text" id="objet-nom" name="nom" required class="form-input" autocomplete="off">
                </div>

                <label>Catégorie</label>
                <select name="parent_category" id="main_category_select" class="form-input">
                    <option value="0">-- Sans catégorie --</option>
                    <?php foreach ($categories_tree as $parent): ?>
                        <option value="<?= $parent['id'] ?>">
                            <?= htmlspecialchars($parent['nom']) ?>
                        </option>
                    <?php endforeach; ?>
                    <option value="NEW" style="color:#2c3e50; font-weight: bold;">+ Créer une nouvelle catégorie</option>
                </select>
            </div>

            <div class="form-group" id="sub_category_container" style="display:none; margin-top: 10px;">
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
                <a href="database-view.php?id=<?= $database_id ?>" class="btn btn-secondary">Annuler</a>
            </div>
            </div>

            
        </form>
    </div>
</div>

<!-- add form behaviors (dropzone, preview, new-category prompt) handled in public/js/app.js -->

<script>document.addEventListener('DOMContentLoaded', function() {
    const mainSelect = document.getElementById('main_category_select');
    const subSelect = document.getElementById('sub_category_select');
    const subContainer = document.getElementById('sub_category_container');

    mainSelect.addEventListener('change', async function() {
        const parentId = this.value;
        
        // Réinitialiser le menu des sous-catégories
        subSelect.innerHTML = '';
        
        if (parentId === 'NEW') {
            subContainer.style.display = 'none';
            const newName = prompt("Nom de la nouvelle catégorie :");
            if (newName && newName.trim() !== "") {
                try {
                    const d = await apiPost({
                        action: "create_category",
                        name: newName,
                        parent_id: 0,
                        csrf_token: window.csrfToken,
                        database_id: window.databaseId,
                    });
                    if (d && d.success) {
                        // 1. Mise à jour des données globales pour que la logique continue de fonctionner
                        const newCat = { id: d.id, nom: newName, parent_id: null, subs: [] };
                        window.globalCategories.push(newCat);

                        // 2. Ajout visuel de l'option dans le menu déroulant (avant le bouton "Créer")
                        const opt = new Option(newName, d.id);
                        this.add(opt, this.options.length - 1);
                        
                        // 3. Sélection automatique et mise à jour de l'interface
                        this.value = d.id;
                        this.dispatchEvent(new Event('change'));
                    }
                    else { alert("Erreur: " + (d.message || d.error || "Impossible de créer")); this.value = "0"; }
                } catch (e) { alert("Erreur réseau"); this.value = "0"; }
            } else {
                this.value = "0";
            }
            return;
        }

        if (parentId === '0') {
            subContainer.style.display = 'none';
            return;
        }

        // Trouver les données de la catégorie parente sélectionnée
        const parentData = window.globalCategories.find(c => c.id == parentId);

        if (parentData) {
            // Ajouter l'option par défaut (l'objet appartient à la catégorie parente elle-même)
            let optionsHtml = `<option value="${parentId}">-- Utiliser la catégorie principale --</option>`;
            
            // Ajouter les sous-catégories existantes
            if (parentData.subs && parentData.subs.length > 0) {
                parentData.subs.forEach(sub => {
                    optionsHtml += `<option value="${sub.id}">${sub.nom}</option>`;
                });
            }

            // Ajouter l'option pour créer une nouvelle sous-catégorie
            optionsHtml += `<option value="NEW_SUB:${parentId}" style="color:#2c3e50; font-weight: bold;">+ Ajouter une sous-catégorie</option>`;
            
            subSelect.innerHTML = optionsHtml;
            subContainer.style.display = 'block';
        } else {
            subContainer.style.display = 'none';
        }
    });

    subSelect.addEventListener('change', async function() {
        if (this.value && this.value.indexOf("NEW_SUB:") === 0) {
            const parts = this.value.split(":");
            const parentId = parseInt(parts[1], 10);
            const newName = prompt("Nom de la nouvelle sous-catégorie :");
            if (newName && newName.trim() !== "") {
                try {
                    const d = await apiPost({
                        action: "create_category",
                        name: newName,
                        parent_id: parentId,
                        csrf_token: window.csrfToken,
                        database_id: window.databaseId,
                    });
                    if (d && d.success) {
                        // 1. Mise à jour des données globales (ajout dans les subs du parent)
                        const parentCat = window.globalCategories.find(c => c.id == parentId);
                        if (parentCat) {
                            if (!parentCat.subs) parentCat.subs = [];
                            parentCat.subs.push({ id: d.id, nom: newName, parent_id: parentId });
                        }

                        // 2. Ajout visuel de l'option dans le sous-menu (avant le bouton "Ajouter")
                        const opt = new Option(newName, d.id);
                        this.add(opt, this.options.length - 1);
                        
                        // 3. Sélection automatique (sans recharger la page, donc le parent reste sélectionné)
                        this.value = d.id;
                    }
                    else { alert("Erreur: " + (d.message || d.error || "Impossible de créer")); this.value = parentId; }
                } catch (e) { alert("Erreur réseau"); this.value = parentId; }
            } else {
                this.value = parentId;
            }
        }
    });
});</script>
<?php include __DIR__ . '/../templates/includes/footer.html'; ?>