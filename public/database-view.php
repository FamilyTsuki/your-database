<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/Helpers/Auth.php';
require_once __DIR__ . '/../src/Helpers/CsrfToken.php';
require_once __DIR__ . '/../src/Helpers/FlashMessage.php';
require_once __DIR__ . '/../src/Models/DatabaseModel.php';


$user_id = $_SESSION['user_id'];
$database_id = intval($_GET['id'] ?? 0);

// Vérifier que l'utilisateur est propriétaire
$db_controller = new DatabaseController($conn);

$database = $db_controller->getDatabase($database_id);
// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Protect route
if (!Auth::isLoggedIn()) {
    header('Location: login.php');
    exit;
}

// Get database ID
$database_id = $_GET['id'] ?? null;
if (!$database_id) {
    FlashMessage::set('error', 'Base de données non trouvée');
    header('Location: index.php');
    exit;
}

// Initialize models
$db_model = new DatabaseModel($conn);
$user_id = $_SESSION['user_id'] ?? null;

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
// REMOVED - Now handled by database-ajouter.php


// Get all objects for this database
// 1. Récupérer les objets avec le NOM de la catégorie (via LEFT JOIN)
$objets_res = $conn->query("
    SELECT objets.*, categories.nom AS nom_categorie 
    FROM objets 
    LEFT JOIN categories ON objets.id_categorie = categories.id
    WHERE objets.database_id = '$database_id' 
    ORDER BY objets.id DESC
");

$objets = [];
while ($row = $objets_res->fetch_assoc()) {
    $objets[] = $row;
}

// 2. Récupérer les catégories proprement (Table categories)
// On filtre par database_id (celles de cette base + les globales si NULL)
$cat_res = $conn->query("
    SELECT id, nom, parent_id 
    FROM categories 
    WHERE database_id = '$database_id' OR database_id IS NULL 
    ORDER BY parent_id ASC, nom ASC
");

$categories_raw = [];
while ($row = $cat_res->fetch_assoc()) {
    $categories_raw[] = $row;
}

// 3. Organiser les catégories en arbre (Parent/Enfant) pour le JS
$categories_tree = [];
foreach ($categories_raw as $cat) {
    if ($cat['parent_id'] == null) {
        $cat['subs'] = [];
        $categories_tree[$cat['id']] = $cat;
    } else {
        if (isset($categories_tree[$cat['parent_id']])) {
            $categories_tree[$cat['parent_id']]['subs'][] = $cat;
        }
    }
}

// Generate CSRF token for templates
$csrf_token = CsrfToken::generate();

// Include header
include __DIR__ . '/../templates/includes/header.phtml';
?>

<div class="database-view-container">
    <h1><?php echo htmlspecialchars($database['name'] ?? 'Base de données'); ?></h1>
    
    <p class="db-description"><?php echo htmlspecialchars($db_info['description'] ?? ''); ?></p>

    <?php
    // Include page sections (consultation only, ajout moved to separate page)
    include __DIR__ . '/../templates/consultation.phtml';
    ?>
</div>

<script>
// No longer showing ajout section - moved to database-ajouter.php

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
}// On envoie l'arbre des catégories au JS
const globalCategories = <?php echo json_encode(array_values($categories_tree)); ?>;

function editFieldderoul(id, field, currentValue, event) {
    if (field !== 'categorie') {
        let newValue = prompt("Nouvelle valeur :", currentValue);
        if (newValue !== null) sendUpdate(id, field, newValue);
        return;
    }

    const tagElement = event.currentTarget;
    const parent = tagElement.parentNode;
    
    // Créer le select
    const select = document.createElement('select');
    select.className = "form-input";
    
    const optNone = document.createElement('option');
    optNone.value = "0";
    optNone.textContent = "-- Sans catégorie --";
    select.appendChild(optNone);

    // Remplir le select avec Parent > Enfant
    globalCategories.forEach(p => {
        const opt = document.createElement('option');
        opt.value = p.id;
        opt.textContent = p.nom;
        opt.style.fontWeight = "bold";
        if (p.nom === currentValue) opt.selected = true;
        select.appendChild(opt);

        if (p.subs) {
            p.subs.forEach(s => {
                const o = document.createElement('option');
                o.value = s.id;
                o.textContent = "   ↳ " + s.nom;
                if (s.nom === currentValue) o.selected = true;
                select.appendChild(o);
            });
        }
    });

    const optNew = document.createElement('option');
    optNew.value = "NEW";
    optNew.textContent = "+ Créer nouvelle catégorie";
    select.appendChild(optNew);

    parent.replaceChild(select, tagElement);
    select.focus();

    select.onchange = function() {
        if (this.value === "NEW") {
            const newName = prompt("Nom de la nouvelle catégorie :");
            if (newName) {
                // Ici on envoie le NOM pour que le serveur sache qu'il doit créer
                // Ou mieux: vous pouvez créer une action AJAX 'addCategory'
                sendUpdate(id, 'categorie', newName); 
            } else {
                parent.replaceChild(tagElement, select);
            }
        } else {
            // On envoie l'ID
            sendUpdate(id, 'categorie', this.value);
        }
    };

    select.onblur = function() {
        setTimeout(() => { if (parent.contains(select)) parent.replaceChild(tagElement, select); }, 200);
    };
}
function sendUpdate(id, field, newVal) {
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
                // Si on a remplacé le span par un select, on rafraîchit quand même pour remettre le design propre
                location.reload(); 
            } else {
                location.reload();
            }
        } else {
            alert("Erreur: " + (d.message || "Impossible de mettre à jour"));
            location.reload();
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
