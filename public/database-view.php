<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/Helpers/Auth.php';
require_once __DIR__ . '/../src/Helpers/CsrfToken.php';
require_once __DIR__ . '/../src/Helpers/FlashMessage.php';
require_once __DIR__ . '/../src/Models/DatabaseModel.php';

// Protect route
Auth::requireLogin();

// Get database ID
$database_id = $_GET['id'] ?? null;
if (!$database_id) {
    FlashMessage::set('error', 'Base de données non trouvée');
    header('Location: index.php');
    exit;
}

// Initialize models
$db_model = new DatabaseModel($conn);
$user_id = Auth::getUserId();

// Check access
$permission = $db_model->getPermission($database_id, $user_id);
if (!$permission) {
    FlashMessage::set('error', 'Accès refusé à cette base de données');
    header('Location: index.php');
    exit;
}

// Get database info
$db_info = $conn->query("
    SELECT * FROM `databases` 
    WHERE id = '$database_id' 
    LIMIT 1
")->fetch_assoc();

if (!$db_info) {
    FlashMessage::set('error', 'Base de données non trouvée');
    header('Location: index.php');
    exit;
}

// Handle AJAX requests for fast operations
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    // Verify CSRF token
    if (!CsrfToken::verify($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        die(json_encode(['error' => 'Token CSRF invalide']));
    }
    
    // Check permission
    if ($permission !== 'edit' && $permission !== 'admin') {
        http_response_code(403);
        die(json_encode(['error' => 'Permission insuffisante']));
    }
    
    $action = $_POST['action'];
    $objet_id = intval($_POST['id'] ?? 0);
    
    if ($action === 'updateQty') {
        $new_qty = intval($_POST['qty'] ?? 0);
        $result = $conn->query("
            UPDATE objets 
            SET quantite = $new_qty 
            WHERE id = $objet_id AND database_id = '$database_id'
            LIMIT 1
        ");
        die(json_encode(['success' => $result, 'qty' => $new_qty]));
    }
    
    if ($action === 'delete') {
        // Get image path before deleting
        $objet = $conn->query("
            SELECT image_path FROM objets 
            WHERE id = $objet_id AND database_id = '$database_id'
            LIMIT 1
        ")->fetch_assoc();
        
        if ($objet && $objet['image_path']) {
            @unlink(__DIR__ . '/uploads/' . $objet['image_path']);
        }
        
        $result = $conn->query("
            DELETE FROM objets 
            WHERE id = $objet_id AND database_id = '$database_id'
            LIMIT 1
        ");
        die(json_encode(['success' => $result]));
    }
    
    if ($action === 'edit') {
        $field = $_POST['field'] ?? '';
        $value = $conn->real_escape_string($_POST['value'] ?? '');
        
        if (in_array($field, ['nom', 'categorie', 'quantite'])) {
            if ($field === 'quantite') {
                $value = intval($value);
            }
            $result = $conn->query("
                UPDATE objets 
                SET `$field` = '$value' 
                WHERE id = $objet_id AND database_id = '$database_id'
                LIMIT 1
            ");
            die(json_encode(['success' => $result, 'value' => htmlspecialchars($value)]));
        }
    }
    
    if ($action === 'updateImage') {
        if (!isset($_FILES['image'])) {
            http_response_code(400);
            die(json_encode(['error' => 'Aucune image fournie']));
        }
        
        // Get old image
        $objet = $conn->query("
            SELECT image_path FROM objets 
            WHERE id = $objet_id AND database_id = '$database_id'
            LIMIT 1
        ")->fetch_assoc();
        
        // Process new image
        $file = $_FILES['image'];
        $allowed = ['image/jpeg', 'image/png', 'image/gif'];
        
        if (!in_array($file['type'], $allowed)) {
            http_response_code(400);
            die(json_encode(['error' => 'Type de fichier non autorisé']));
        }
        
        if ($file['size'] > 5242880) { // 5MB
            http_response_code(400);
            die(json_encode(['error' => 'Fichier trop volumineux']));
        }
        
        // Create uploads directory if needed
        $uploads_dir = __DIR__ . '/uploads';
        if (!is_dir($uploads_dir)) {
            mkdir($uploads_dir, 0755, true);
        }
        
        // Generate filename
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'obj_' . $objet_id . '_' . time() . '.' . $ext;
        $filepath = $uploads_dir . '/' . $filename;
        
        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            // Delete old image
            if ($objet && $objet['image_path']) {
                @unlink(__DIR__ . '/uploads/' . $objet['image_path']);
            }
            
            // Update database
            $conn->query("
                UPDATE objets 
                SET image_path = '$filename' 
                WHERE id = $objet_id AND database_id = '$database_id'
                LIMIT 1
            ");
            
            die(json_encode([
                'success' => true,
                'image_path' => 'uploads/' . $filename
            ]));
        }
        
        http_response_code(500);
        die(json_encode(['error' => 'Erreur lors de l\'upload']));
    }
    
    die(json_encode(['error' => 'Action inconnue']));
}

// Handle form submission for adding objects
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_object'])) {
    if (!CsrfToken::verify($_POST['csrf_token'] ?? '')) {
        FlashMessage::set('error', 'Token CSRF invalide');
    } else if ($permission !== 'edit' && $permission !== 'admin') {
        FlashMessage::set('error', 'Vous n\'avez pas la permission d\'ajouter des objets');
    } else {
        $nom = $conn->real_escape_string($_POST['nom'] ?? '');
        $categorie = $conn->real_escape_string($_POST['categorie'] ?? '');
        $quantite = intval($_POST['quantite'] ?? 1);
        
        // Handle new category
        if ($categorie === 'NEW') {
            $categorie = $conn->real_escape_string($_POST['new_category'] ?? '');
            if (empty($categorie)) {
                FlashMessage::set('error', 'Nom de catégorie vide');
                $categorie = '';
            }
        }
        
        if (!empty($nom)) {
            $image_path = '';
            
            // Handle image upload
            if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
                $file = $_FILES['image'];
                $allowed = ['image/jpeg', 'image/png', 'image/gif'];
                
                if (!in_array($file['type'], $allowed)) {
                    FlashMessage::set('error', 'Type de fichier non autorisé');
                } else if ($file['size'] > 5242880) { // 5MB
                    FlashMessage::set('error', 'Fichier trop volumineux');
                } else {
                    // Create uploads directory if needed
                    $uploads_dir = __DIR__ . '/uploads';
                    if (!is_dir($uploads_dir)) {
                        mkdir($uploads_dir, 0755, true);
                    }
                    
                    // Generate filename
                    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                    $filename = 'obj_' . time() . '_' . uniqid() . '.' . $ext;
                    $filepath = $uploads_dir . '/' . $filename;
                    
                    if (move_uploaded_file($file['tmp_name'], $filepath)) {
                        $image_path = $filename;
                    }
                }
            }
            
            // Insert object
            $image_path_esc = $conn->real_escape_string($image_path);
            $result = $conn->query("
                INSERT INTO objets (database_id, nom, categorie, quantite, image_path, created_at)
                VALUES ('$database_id', '$nom', '$categorie', $quantite, '$image_path_esc', NOW())
            ");
            
            if ($result) {
                FlashMessage::set('success', 'Objet ajouté avec succès');
            } else {
                FlashMessage::set('error', 'Erreur lors de l\'ajout de l\'objet');
            }
        } else {
            FlashMessage::set('error', 'Nom de l\'objet vide');
        }
    }
    
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

// Get all objects for this database
$objets_res = $conn->query("
    SELECT * FROM objets 
    WHERE database_id = '$database_id' 
    ORDER BY id DESC
");

$objets = [];
while ($row = $objets_res->fetch_assoc()) {
    $objets[] = $row;
}

// Get categories
$cat_res = $conn->query("
    SELECT DISTINCT categorie FROM objets 
    WHERE database_id = '$database_id' AND categorie != '' 
    ORDER BY categorie
");

$categories = [];
while ($row = $cat_res->fetch_assoc()) {
    $categories[] = $row['categorie'];
}

// Generate CSRF token for templates
$csrf_token = CsrfToken::generate();

// Include header
include __DIR__ . '/../templates/includes/header.phtml';
?>

<div class="database-view-container">
    <h1><?php echo htmlspecialchars($db_info['nom']); ?></h1>
    <p class="db-description"><?php echo htmlspecialchars($db_info['description'] ?? ''); ?></p>
    
    <div class="db-controls">
        <button class="btn btn-primary" onclick="toggleAddForm()">➕ Ajouter un objet</button>
        <?php if ($permission === 'admin'): ?>
            <a href="database-settings.php?id=<?php echo $database_id; ?>" class="btn btn-secondary">⚙️ Paramètres</a>
        <?php endif; ?>
    </div>

    <?php
    // Include page sections
    include __DIR__ . '/../templates/consultation.phtml';
    include __DIR__ . '/../templates/ajout.html';
    ?>
</div>

<script>
function toggleAddForm() {
    const form = document.getElementById('page-ajout');
    form.style.display = form.style.display === 'none' ? 'block' : 'none';
    if (form.style.display === 'block') {
        document.getElementById('objet-nom').focus();
    }
}

function updateQuantity(id, action) {
    const current = parseInt(document.getElementById('qty-' + id).textContent);
    const qty = action === 'inc' ? current + 1 : Math.max(0, current - 1);
    
    fetch('?id=<?php echo $database_id; ?>', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=updateQty&id=' + id + '&qty=' + qty + '&csrf_token=<?php echo $csrf_token; ?>'
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            document.getElementById('qty-' + id).textContent = qty;
        }
    });
}

function editField(id, field, value) {
    const newVal = prompt('Modifier ' + field + ':', value);
    if (newVal === null) return;
    
    fetch('?id=<?php echo $database_id; ?>', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=edit&id=' + id + '&field=' + field + '&value=' + encodeURIComponent(newVal) + '&csrf_token=<?php echo $csrf_token; ?>'
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            if (field === 'quantite') {
                document.getElementById('qty-' + id).textContent = newVal;
            } else {
                location.reload();
            }
        }
    });
}

function deleteObject(id) {
    if (!confirm('Supprimer cet objet ?')) return;
    
    fetch('?id=<?php echo $database_id; ?>', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=delete&id=' + id + '&csrf_token=<?php echo $csrf_token; ?>'
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            location.reload();
        }
    });
}

function changeImage(id) {
    const input = document.createElement('input');
    input.type = 'file';
    input.accept = 'image/*';
    input.onchange = (e) => {
        const file = e.target.files[0];
        if (!file) return;
        
        const fd = new FormData();
        fd.append('action', 'updateImage');
        fd.append('id', id);
        fd.append('image', file);
        fd.append('csrf_token', '<?php echo $csrf_token; ?>');
        
        fetch('?id=<?php echo $database_id; ?>', {
            method: 'POST',
            body: fd
        })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                location.reload();
            } else {
                alert('Erreur: ' + (d.error || 'Unknown error'));
            }
        });
    };
    input.click();
}

function previewImage(event) {
    const file = event.target.files[0];
    if (!file) return;
    
    const preview = document.getElementById('preview');
    const reader = new FileReader();
    
    reader.onload = (e) => {
        preview.innerHTML = '<img src="' + e.target.result + '" style="max-width: 200px; border-radius: 8px;">';
    };
    
    reader.readAsDataURL(file);
}

function filterItems() {
    const search = document.getElementById('searchInput').value.toLowerCase();
    const category = document.getElementById('categoryFilter').value.toLowerCase();
    
    document.querySelectorAll('.card').forEach(card => {
        const name = card.dataset.name || '';
        const cat = card.dataset.cat.toLowerCase();
        
        const matches = (!search || name.includes(search)) && 
                       (!category || cat === category);
        card.style.display = matches ? 'block' : 'none';
    });
}

// Handle category "NEW" option
document.addEventListener('DOMContentLoaded', function() {
    const catSelect = document.getElementById('objet-cat');
    if (catSelect) {
        catSelect.addEventListener('change', function() {
            const newInput = document.getElementById('new-cat-input');
            if (this.value === 'NEW') {
                newInput.style.display = 'block';
                newInput.focus();
            } else {
                newInput.style.display = 'none';
            }
        });
    }
});
</script>

<?php include __DIR__ . '/../templates/includes/footer.html'; ?>
