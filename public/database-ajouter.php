<?php
require_once __DIR__ . '/../config/config.php';

// Vérifier l'authentification
if (!Auth::isLoggedIn()) {
    header("Location: login.php");
    exit();
}

// Get database ID
$database_id = $_GET['id'] ?? null;
if (!$database_id) {
    FlashMessage::error('Base de données non trouvée');
    header("Location: index.php");
    exit();
}

$database_id = intval($database_id);
$user_id = Auth::getUserId();

// Vérifier les permissions (simplifié - l'utilisateur peut ajouter à sa propre base)
// À améliorer plus tard avec un système de permissions

// Get database info
$stmt = $conn->prepare("SELECT * FROM objets WHERE database_id = ? LIMIT 1");
if (!$stmt) {
    // Si la colonne database_id n'existe pas, on utilise juste la base par défaut
    $db_info = ['id' => $database_id, 'nom' => 'Mon Inventaire'];
} else {
    $stmt->bind_param("i", $database_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    // On va utiliser un nom simplifié
    $db_info = [
        'id' => $database_id,
        'nom' => 'Mon Inventaire'
    ];
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_object'])) {
    if (!CsrfToken::verifyFromPost()) {
        FlashMessage::error('Token CSRF invalide');
    } else {
        $nom = $_POST['nom'] ?? '';
        $categorie = $_POST['categorie'] ?? '';
        $quantite = intval($_POST['quantite'] ?? 1);
        
        // Handle new category
        if ($categorie === 'NEW') {
            $categorie = Validator::sanitizeText($_POST['new_category'] ?? '', 100);
            if (empty($categorie)) {
                FlashMessage::error('Nom de catégorie vide');
                $categorie = '';
            }
        } else {
            $categorie = Validator::sanitizeText($categorie, 100);
        }
        
        $nom = Validator::sanitizeText($nom, 100);
        
        if (!empty($nom)) {
            $image_path = '';
            
            // Handle image upload
            if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
                $validation = Validator::validateImageFile($_FILES['image']);
                if (!$validation['valid']) {
                    FlashMessage::error($validation['message']);
                } else {
                    $file = $_FILES['image'];
                    
                    // Create uploads directory if needed
                    $uploads_dir = __DIR__ . '/uploads';
                    if (!is_dir($uploads_dir)) {
                        mkdir($uploads_dir, 0755, true);
                    }
                    
                    // Generate filename
                    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                    $filename = 'obj_' . time() . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
                    $filepath = $uploads_dir . '/' . $filename;
                    
                    if (move_uploaded_file($file['tmp_name'], $filepath)) {
                        $image_path = $filename;
                    } else {
                        FlashMessage::error('Erreur lors du téléchargement de l\'image');
                    }
                }
            }
            
            // Insert object
            $stmt = $conn->prepare("INSERT INTO objets (nom, categorie, quantite, image_path) VALUES (?, ?, ?, ?)");
            if ($stmt) {
                $stmt->bind_param("ssis", $nom, $categorie, $quantite, $image_path);
                $result = $stmt->execute();
            
                if ($result) {
                    FlashMessage::success('Objet ajouté avec succès');
                    header('Location: index.php');
                    exit();
                } else {
                    FlashMessage::error('Erreur lors de l\'ajout de l\'objet');
                }
                $stmt->close();
            } else {
                FlashMessage::error('Erreur de base de données');
            }
        } else {
            FlashMessage::error('Nom de l\'objet vide');
        }
    }
}

// Get categories for this database
$cat_res = $conn->query("
    SELECT DISTINCT categorie FROM objets 
    WHERE database_id = '$database_id' AND categorie != '' 
    ORDER BY categorie
");

$categories = [];
while ($row = $cat_res->fetch_assoc()) {
    $categories[] = $row['categorie'];
}

// Generate CSRF token
$csrf_token = CsrfToken::generate();

// Include header
include __DIR__ . '/../templates/includes/header.phtml';
?>

<div class="ajouter-container">
    <div class="add-page-header">
        <a href="database-view.php?id=<?php echo $database_id; ?>" class="back-link">← Retour à <?php echo htmlspecialchars($db_info['nom']); ?></a>
    </div>

    <div class="add-page-form">
        <h1>✨ Ajouter un nouvel objet</h1>
        <p class="subtitle">Base: <strong><?php echo htmlspecialchars($db_info['nom']); ?></strong></p>

        <form method="POST" enctype="multipart/form-data" class="add-form-full">
            
            <!-- Image Upload Section -->
            <div class="image-section">
                <label>Photo de l'objet</label>
                <div class="drop-zone" id="dropZone">
                    <svg width="80" height="80" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                        <polyline points="17 8 12 3 7 8"></polyline>
                        <line x1="12" y1="3" x2="12" y2="15"></line>
                    </svg>
                    <p>Déposer une image ici</p>
                    <p class="small">ou cliquer pour sélectionner</p>
                    <input type="file" name="image" id="fileInput" accept="image/*" style="display: none;">
                </div>
                <div id="preview" class="preview-container"></div>
            </div>

            <!-- Form Fields -->
            <div class="form-section">
                
                <!-- Nom -->
                <div class="form-group">
                    <label for="objet-nom">
                        <span class="label-text">Nom de l'objet *</span>
                        <span class="required">Requis</span>
                    </label>
                    <input 
                        type="text" 
                        id="objet-nom" 
                        name="nom" 
                        required 
                        placeholder="Ex: Bouteille plastique 5L"
                        class="form-input"
                    >
                </div>

                <!-- Catégorie -->
                <div class="form-group">
                    <label for="objet-cat">
                        <span class="label-text">Catégorie</span>
                    </label>
                    <select id="objet-cat" name="categorie" class="form-input" onchange="toggleNewCategory()">
                        <option value="">-- Sans catégorie --</option>
                        <?php foreach ($categories as $c): ?>
                            <option value="<?php echo htmlspecialchars($c); ?>">
                                <?php echo htmlspecialchars($c); ?>
                            </option>
                        <?php endforeach; ?>
                        <option value="">---</option>
                        <option value="NEW">+ Créer nouvelle catégorie</option>
                    </select>
                    <input 
                        type="text" 
                        id="new-cat-input" 
                        name="new_category" 
                        placeholder="Nom de la nouvelle catégorie"
                        class="form-input"
                        style="display: none; margin-top: 10px;"
                    >
                </div>

                <!-- Quantité -->
                <div class="form-group">
                    <label for="objet-qty">
                        <span class="label-text">Quantité</span>
                    </label>
                    <input 
                        type="number" 
                        id="objet-qty" 
                        name="quantite" 
                        value="1" 
                        min="0"
                        class="form-input qty-input"
                    >
                </div>
            </div>

            <!-- CSRF Token -->
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">

            <!-- Buttons -->
            <div class="form-buttons-section">
                <button type="submit" name="add_object" class="btn btn-primary btn-large">
                    ✓ Ajouter l'objet
                </button>
                <a href="database-view.php?id=<?php echo $database_id; ?>" class="btn btn-secondary btn-large">
                    ✗ Annuler
                </a>
            </div>
        </form>
    </div>
</div>

<style>
.ajouter-container {
    max-width: 600px;
    margin: 0 auto;
    padding: 20px;
}

.add-page-header {
    margin-bottom: 20px;
}

.back-link {
    color: #3498db;
    text-decoration: none;
    font-size: 14px;
    display: inline-block;
}

.back-link:hover {
    text-decoration: underline;
}

.add-page-form {
    background: white;
    border-radius: 12px;
    padding: 40px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.add-page-form h1 {
    margin: 0 0 5px 0;
    font-size: 28px;
    color: #2c3e50;
}

.subtitle {
    color: #7f8c8d;
    margin: 0 0 30px 0;
    font-size: 14px;
}

.image-section {
    margin-bottom: 40px;
}

.image-section > label {
    display: block;
    font-weight: 600;
    margin-bottom: 15px;
    color: #2c3e50;
}

.drop-zone {
    border: 2px dashed #bdc3c7;
    border-radius: 12px;
    padding: 40px 20px;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s ease;
    background: #f8f9fa;
}

.drop-zone:hover {
    border-color: #3498db;
    background: #ecf0f1;
}

.drop-zone svg {
    color: #3498db;
    margin-bottom: 10px;
}

.drop-zone p {
    margin: 5px 0;
    color: #2c3e50;
}

.drop-zone .small {
    font-size: 12px;
    color: #7f8c8d;
}

.preview-container {
    margin-top: 20px;
    text-align: center;
}

.preview-container img {
    max-width: 100%;
    max-height: 300px;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.15);
}

.form-section {
    margin-bottom: 30px;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
    font-weight: 600;
    color: #2c3e50;
}

.label-text {
    flex: 1;
}

.required {
    font-size: 12px;
    color: #e74c3c;
    font-weight: normal;
}

.form-input {
    width: 100%;
    padding: 12px 15px;
    border: 1px solid #bdc3c7;
    border-radius: 8px;
    font-size: 14px;
    font-family: inherit;
    transition: border-color 0.3s ease;
    box-sizing: border-box;
}

.form-input:focus {
    outline: none;
    border-color: #3498db;
    box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
}

.qty-input {
    max-width: 120px;
}

.form-buttons-section {
    display: flex;
    gap: 15px;
    margin-top: 40px;
    padding-top: 20px;
    border-top: 1px solid #ecf0f1;
}

.btn {
    flex: 1;
    padding: 12px 20px;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-size: 16px;
    font-weight: 600;
    text-decoration: none;
    text-align: center;
    transition: all 0.3s ease;
    display: inline-block;
}

.btn-primary {
    background: #3498db;
    color: white;
}

.btn-primary:hover {
    background: #2980b9;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(52, 152, 219, 0.3);
}

.btn-secondary {
    background: #ecf0f1;
    color: #2c3e50;
}

.btn-secondary:hover {
    background: #bdc3c7;
}

.btn-large {
    padding: 15px 25px;
    font-size: 16px;
}
</style>

<script>
const dropZone = document.getElementById('dropZone');
const fileInput = document.getElementById('fileInput');
const preview = document.getElementById('preview');

// Click to select file
dropZone.addEventListener('click', () => fileInput.click());

// Drag and drop
dropZone.addEventListener('dragover', (e) => {
    e.preventDefault();
    dropZone.style.borderColor = '#3498db';
    dropZone.style.background = '#ecf0f1';
});

dropZone.addEventListener('dragleave', () => {
    dropZone.style.borderColor = '#bdc3c7';
    dropZone.style.background = '#f8f9fa';
});

dropZone.addEventListener('drop', (e) => {
    e.preventDefault();
    dropZone.style.borderColor = '#bdc3c7';
    dropZone.style.background = '#f8f9fa';
    
    const files = e.dataTransfer.files;
    if (files.length > 0) {
        fileInput.files = files;
        previewImage();
    }
});

// File input change
fileInput.addEventListener('change', previewImage);

function previewImage() {
    const file = fileInput.files[0];
    if (!file) {
        preview.innerHTML = '';
        return;
    }
    
    const reader = new FileReader();
    reader.onload = (e) => {
        preview.innerHTML = `<img src="${e.target.result}" alt="Aperçu">`;
    };
    reader.readAsDataURL(file);
}

function toggleNewCategory() {
    const select = document.getElementById('objet-cat');
    const newInput = document.getElementById('new-cat-input');
    
    if (select.value === 'NEW') {
        newInput.style.display = 'block';
        newInput.focus();
    } else {
        newInput.style.display = 'none';
    }
}

// Focus on nom field when page loads
document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('objet-nom').focus();
});
</script>

<?php include __DIR__ . '/../templates/includes/footer.html'; ?>
