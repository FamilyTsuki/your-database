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
        FlashMessage::error('error', 'Base de données non trouvée');
        header('Location: index.php');
        exit;
    }

    // Initialize models
    $db_model = new DatabaseModel($conn);
    $user_id = $_SESSION['user_id'] ?? null;

    // Check access
    $permission = $db_model->getPermission($database_id, $user_id);
    if (!$permission) {
        FlashMessage::error('error', 'Accès refusé à cette base de données');
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
        FlashMessage::error('error', 'Base de données non trouvée');
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
            $value = $_POST['value'] ?? '';
            $objet_id = intval($_POST['id'] ?? 0);
            // À ajouter dans database-settings.php dans le bloc action === 'edit'
            if ($field === 'new_subcategory_create') {
                $sub_name = $conn->real_escape_string($value); // Le nom tapé dans le prompt
                $parent_id = intval($_POST['parent_id']);    // L'ID du parent sélectionné
                
                // 1. Création de la sous-catégorie
                $conn->query("INSERT INTO categories (nom, database_id, parent_id) VALUES ('$sub_name', '$database_id', $parent_id)");
                $new_id = $conn->insert_id;
                
                // 2. Assignation à l'objet
                $result = $conn->query("UPDATE objets SET id_categorie = $new_id WHERE id = $objet_id AND database_id = '$database_id'");
                die(json_encode(['success' => $result]));
            }
            // CAS 1 : Créer une toute nouvelle catégorie et l'associer à l'objet
            if ($field === 'new_category_create') {
                $cat_name = $conn->real_escape_string(trim($value));
                // On insère la catégorie dans la table dédiée
                $conn->query("INSERT INTO categories (nom, database_id) VALUES ('$cat_name', $database_id)");
                $new_cat_id = $conn->insert_id;
                
                // On lie l'objet à cet ID
                $result = $conn->query("UPDATE objets SET id_categorie = $new_cat_id WHERE id = $objet_id AND database_id = '$database_id'");
                die(json_encode(['success' => $result]));
            }

            // CAS 2 : Mise à jour classique (nom, quantité, ou changer pour une catégorie existante)
            // On transforme 'categorie' en 'id_categorie' pour la BDD
            if ($field === 'categorie') $field = 'id_categorie';

            $allowedFields = ['nom', 'id_categorie', 'quantite'];
            if (in_array($field, $allowedFields)) {
                if ($field === 'nom') {
                    $clean_val = "'" . $conn->real_escape_string($value) . "'";
                } else {
                    // Pour quantité ou id_categorie, on veut un chiffre (ou NULL si id_categorie = 0)
                    $clean_val = intval($value);
                    if ($field === 'id_categorie' && $clean_val === 0) $clean_val = "NULL";
                }

                $result = $conn->query("
                    UPDATE objets 
                    SET `$field` = $clean_val 
                    WHERE id = $objet_id AND database_id = '$database_id'
                    LIMIT 1
                ");
                die(json_encode(['success' => $result]));
            }
            die(json_encode(['success' => false, 'message' => 'Champ non autorisé']));
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
        SELECT 
            objets.*, 
            cat.nom AS nom_categorie,
            cat.parent_id,
            parent_cat.nom AS parent_nom
        FROM objets 
        LEFT JOIN categories AS cat ON objets.id_categorie = cat.id
        LEFT JOIN categories AS parent_cat ON cat.parent_id = parent_cat.id
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
    window.globalCategories = <?php echo json_encode(array_values($categories_tree)); ?>;

    function editFieldderoul(id, field, currentValue, event) {
        const tagElement = event.currentTarget;
        const parent = tagElement.parentNode;
        
        const select = document.createElement('select');
        select.className = "form-input";
        
        // Option par défaut
        const optNone = document.createElement('option');
        optNone.value = "0";
        optNone.textContent = "-- Sans catégorie --";
        select.appendChild(optNone);

        // Remplissage avec les catégories existantes
        if (window.globalCategories) {
            window.globalCategories.forEach(p => {
                const opt = document.createElement('option');
                opt.value = p.id;
                opt.textContent = p.nom;
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
        }

        // AJOUT DE L'OPTION NOUVEAU
        const optNew = document.createElement('option');
        optNew.value = "NEW";
        optNew.textContent = "+ Créer nouvelle catégorie";
        optNew.style.color = "#3498db";
        optNew.style.fontWeight = "bold";
        select.appendChild(optNew);

        parent.replaceChild(select, tagElement);
        select.focus();

        select.onchange = function() {
            if (this.value === "NEW") {
                const newName = prompt("Nom de la nouvelle catégorie :");
                if (newName && newName.trim() !== "") {
                    // On envoie le texte au lieu de l'ID pour que PHP sache qu'il faut créer
                    sendUpdate(id, 'new_category_create', newName);
                } else {
                    // Annulation : on remet le tag d'origine
                    parent.replaceChild(tagElement, select);
                }
            } else {
                sendUpdate(id, 'id_categorie', this.value);
            }
        };

        select.onblur = function() {
            setTimeout(() => {
                if (parent.contains(select)) parent.replaceChild(tagElement, select);
            }, 200);
        };
    }
    function sendUpdate(id, field, newVal) {
        // On prépare les données à envoyer
        const params = new URLSearchParams();
        params.append('action', 'edit');
        params.append('id', id);
        params.append('field', field);
        params.append('value', newVal);
        params.append('csrf_token', '<?php echo $csrf_token; ?>');

        fetch('database-view.php?id=<?php echo $database_id; ?>', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: params.toString()
        })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                // Rechargement pour voir la nouvelle catégorie dans les filtres et le tag
                location.reload(); 
            } else {
                alert("Erreur: " + (d.message || "Impossible de mettre à jour"));
            }
        })
        .catch(err => {
            console.error("Erreur Fetch:", err);
            alert("Erreur réseau ou serveur");
        });
    }
    

    // Fonction pour gérer le choix
    function handleCategoryChange(selectElement, idObjet) {
        const value = selectElement.value;

        // Si l'utilisateur a choisi "Nouvelle sous-catégorie"
        if (value.startsWith('NEW_SUB_')) {
            const parentId = value.replace('NEW_SUB_', '');
            const subName = prompt("Nom de la nouvelle sous-catégorie :");
            
            if (subName && subName.trim() !== "") {
                // On utilise AJAX pour envoyer au serveur sans recharger
                const formData = new FormData();
                formData.append('action', 'edit');
                formData.append('field', 'new_subcategory_create');
                formData.append('value', subName);
                formData.append('id', idObjet);
                formData.append('parent_id', parentId);

                fetch('database-settings.php?id=' + <?= $database_id ?>, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        
                        location.reload(); // On recharge pour voir le nouveau nom
                    }
                });
            } else {
                location.reload(); // Annuler si vide
            }
        } else {
            // Sinon, c'est un changement de catégorie classique (déjà existante)
            // Appelle ta fonction habituelle, par exemple :
            editField(idObjet, 'id_categorie', value);
        }
    }
    function editField(id, field, value) {
    // Si on clique sur une catégorie, on ne fait rien ici (c'est géré par editFieldderoul)
    if (field === 'id_categorie' || field === 'categorie') return;

    const newVal = prompt('Modifier ' + field + ':', value);
    if (newVal === null || newVal.trim() === "") return;
    
    // On appelle sendUpdate pour enregistrer proprement
    sendUpdate(id, field, newVal);
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

    /*function filterItems() {
        const searchInput = document.getElementById('searchInput');
        const categorySelect = document.getElementById('categoryFilter');
        alert(searchInput , categorySelect)
        if (!searchInput || !categorySelect) return;

        const search = searchInput.value.toLowerCase().trim();
        const categoryFilter = categorySelect.value.toLowerCase().trim();
        
        document.querySelectorAll('.card').forEach(card => {
            const name = (card.dataset.name || '').toLowerCase();
            const cat = (card.dataset.cat || '').toLowerCase();
            const parent = (card.dataset.parent || '').toLowerCase();
            const matchesSearch = name.includes(search);
            
            // On vérifie si le filtre est vide, 
            // OU s'il correspond à la catégorie, 
            // OU s'il correspond au parent
            const matchesCategory = (categoryFilter === "" || 
                                    cat === categoryFilter || 
                                    parent === categoryFilter);
            
            if (matchesSearch && matchesCategory) {
                card.style.display = "flex";
            } else {
                card.style.display = "none";
            }
        });
    }*/

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
