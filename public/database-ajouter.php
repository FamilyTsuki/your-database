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

// 3. Traitement du formulaire
// 3. Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_object'])) {
    if (!CsrfToken::verifyFromPost()) {
        FlashMessage::error('Token CSRF invalide');
    } else {
        $nom = Validator::sanitizeText($_POST['nom'] ?? '', 100);
        $quantite = intval($_POST['quantite'] ?? 1);
        
        $id_categorie = null;
        $cat_value = $_POST['categorie'] ?? '';

        // Gestion de la catégorie
        if ($cat_value === 'NEW') {
            $new_cat_name = Validator::sanitizeText($_POST['new_category'] ?? '', 100);
            if (!empty($new_cat_name)) {
                // On insère la nouvelle catégorie pour obtenir un ID
                $stmt_cat = $conn->prepare("INSERT INTO categories (nom, database_id) VALUES (?, ?)");
                $stmt_cat->bind_param("si", $new_cat_name, $database_id);
                if ($stmt_cat->execute()) {
                    $id_categorie = $conn->insert_id;
                }
            }
        } elseif (!empty($cat_value)) {
            $id_categorie = intval($cat_value);
        }

        if (!empty($nom)) {
            $image_filename = '';
            // ... (Gardez votre code de gestion d'image tel quel) ...

            // Insertion mise à jour : id_categorie au lieu de categorie
            $stmt = $conn->prepare("INSERT INTO objets (database_id, nom, id_categorie, quantite, image_path) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("isiis", $database_id, $nom, $id_categorie, $quantite, $image_filename);

            if ($stmt->execute()) {
                FlashMessage::success('Objet ajouté avec succès');
                header("Location: database-view.php?id=$database_id");
                exit();
            } else {
                FlashMessage::error('Erreur BDD : ' . $conn->error);
            }
        }
    }
}

// 4. Données pour la vue
// 4. Données pour la vue
$cat_res = $conn->query("SELECT id, nom, parent_id FROM categories WHERE database_id = $database_id OR database_id IS NULL ORDER BY nom ASC");
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

                <div class="form-group">
                    <label for="objet-cat">Catégorie</label>
                    <select id="objet-cat" name="categorie" class="form-input" onchange="toggleNewCategory(this)">
                        <option value="">-- Sans catégorie --</option>
                        <?php foreach ($categories_tree as $parent): ?>
                            <option value="<?= $parent['id'] ?>" style="font-weight: bold;">
                                <?= htmlspecialchars($parent['nom']) ?>
                            </option>
                            
                            <?php foreach ($parent['subs'] as $sub): ?>
                                <option value="<?= $sub['id'] ?>">
                                    &nbsp;&nbsp;&nbsp;↳ <?= htmlspecialchars($sub['nom']) ?>
                                </option>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                        
                        <option value="NEW" style="color: #3498db; font-weight: bold;">+ Créer nouvelle catégorie</option>
                    </select>
                    <input type="text" id="new-cat-input" name="new_category" placeholder="Nom de la catégorie" class="form-input" style="display: none; margin-top: 10px;">
                </div>

                <div class="form-group">
                    <label for="objet-qty">Quantité</label>
                    <input type="number" id="objet-qty" name="quantite" value="1" min="0" class="form-input">
                </div>
            </div>

            <div class="form-buttons-section">
                <button type="submit" name="add_object" class="btn btn-primary">✓ Ajouter</button>
                <a href="database-view.php?id=<?= $database_id ?>" class="btn btn-secondary">Annuler</a>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const dropZone = document.getElementById('dropZone');
    const fileInput = document.getElementById('fileInput');
    const previewImage = document.getElementById('previewImage');
    const placeholder = document.getElementById('placeholder');

    // Déclenchement de l'input file
    dropZone.addEventListener('click', () => fileInput.click());

    // Gestion du Drag & Drop
    ['dragover', 'dragleave', 'drop'].forEach(eventName => {
        dropZone.addEventListener(eventName, (e) => {
            e.preventDefault();
            e.stopPropagation();
        });
    });

    dropZone.addEventListener('dragover', () => {
        dropZone.classList.add('drag-active'); // Ajoutez ce style en CSS si besoin
        dropZone.style.background = '#ecf0f1';
    });

    dropZone.addEventListener('dragleave', () => {
        dropZone.style.background = '#f8f9fa';
    });

    dropZone.addEventListener('drop', (e) => {
        dropZone.style.background = '#f8f9fa';
        const files = e.dataTransfer.files;
        if (files.length > 0) {
            fileInput.files = files;
            handlePreview(files[0]);
        }
    });

    fileInput.addEventListener('change', (e) => {
        if (e.target.files.length > 0) handlePreview(e.target.files[0]);
    });

    function handlePreview(file) {
        if (!file.type.startsWith('image/')) return;
        const reader = new FileReader();
        reader.onload = (e) => {
            previewImage.src = e.target.result;
            previewImage.style.display = "block";
            placeholder.style.display = "none";
            dropZone.style.border = "2px dashed #3498db";
        };
        reader.readAsDataURL(file);
    }
});

function toggleNewCategory(select) {
    const newInput = document.getElementById('new-cat-input');
    newInput.style.display = (select.value === 'NEW') ? 'block' : 'none';
    if (select.value === 'NEW') newInput.focus();
}
</script>

<?php include __DIR__ . '/../templates/includes/footer.html'; ?>