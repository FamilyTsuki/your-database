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
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_object'])) {
    if (!CsrfToken::verifyFromPost()) {
        FlashMessage::error('Token CSRF invalide');
    } else {
        $nom = Validator::sanitizeText($_POST['nom'] ?? '', 100);
        $quantite = intval($_POST['quantite'] ?? 1);
        $categorie = ($_POST['categorie'] === 'NEW') 
            ? Validator::sanitizeText($_POST['new_category'] ?? '', 100) 
            : Validator::sanitizeText($_POST['categorie'] ?? '', 100);

        if (!empty($nom)) {
            $image_filename = '';

            // Gestion de l'image
            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $validation = Validator::validateImageFile($_FILES['image']);
                if ($validation['valid']) {
                    $uploads_dir = __DIR__ . '/uploads';
                    if (!is_dir($uploads_dir)) mkdir($uploads_dir, 0755, true);

                    $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
                    $image_filename = 'obj_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                    
                    if (!move_uploaded_file($_FILES['image']['tmp_name'], $uploads_dir . '/' . $image_filename)) {
                        FlashMessage::error('Erreur lors du transfert de l\'image');
                    }
                } else {
                    FlashMessage::error($validation['message']);
                }
            }

            // Insertion (Ajout du database_id !)
            $stmt = $conn->prepare("INSERT INTO objets (database_id, nom, categorie, quantite, image_path) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("issis", $database_id, $nom, $categorie, $quantite, $image_filename);

            if ($stmt->execute()) {
                FlashMessage::success('Objet ajouté avec succès');
                header("Location: database-view.php?id=$database_id");
                exit();
            } else {
                FlashMessage::error('Erreur BDD : ' . $conn->error);
            }
        } else {
            FlashMessage::error('Le nom est obligatoire');
        }
    }
}

// 4. Données pour la vue
$cat_res = $conn->query("SELECT DISTINCT categorie FROM objets WHERE database_id = $database_id AND categorie != '' ORDER BY categorie");
$categories = $cat_res->fetch_all(MYSQLI_ASSOC);
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
                    <input type="text" id="objet-nom" name="nom" required class="form-input" autofocus>
                </div>

                <div class="form-group">
                    <label for="objet-cat">Catégorie</label>
                    <select id="objet-cat" name="categorie" class="form-input" onchange="toggleNewCategory(this)">
                        <option value="">-- Sans catégorie --</option>
                        <?php foreach ($categories as $c): ?>
                            <option value="<?= htmlspecialchars($c['categorie']) ?>"><?= htmlspecialchars($c['categorie']) ?></option>
                        <?php endforeach; ?>
                        <option value="NEW">+ Créer nouvelle catégorie</option>
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