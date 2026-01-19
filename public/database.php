<?php
require_once '../config/config.php';

if (!Auth::isLoggedIn()) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$database_id = intval($_GET['id'] ?? 0);

// Vérifier que l'utilisateur a accès à cette base
$db_controller = new DatabaseController($conn);
if (!$db_controller->hasAccess($database_id, $user_id)) {
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

$permission = $db_controller->getPermission($database_id, $user_id);

// Créer un modèle pour cette base
class DatabaseObjets {
    private $conn;
    private $database_id;
    
    public function __construct($conn, $database_id) {
        $this->conn = $conn;
        $this->database_id = intval($database_id);
    }
    
    public function getAll($limit = null, $offset = 0) {
        $query = "SELECT * FROM objets WHERE database_id = ? ORDER BY id DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $this->database_id);
        
        if ($limit !== null) {
            $limit = intval($limit);
            $offset = intval($offset);
            $query = "SELECT * FROM objets WHERE database_id = ? ORDER BY id DESC LIMIT $limit OFFSET $offset";
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param("i", $this->database_id);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        $objets = [];
        
        while ($row = $result->fetch_assoc()) {
            $objets[] = $row;
        }
        
        return $objets;
    }
    
    public function getById($id) {
        $id = intval($id);
        $stmt = $this->conn->prepare("SELECT * FROM objets WHERE id = ? AND database_id = ?");
        $stmt->bind_param("ii", $id, $this->database_id);
        $stmt->execute();
        
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            return $row;
        }
        return null;
    }
    
    public function create($nom, $categorie, $quantite, $image_path = '') {
        $stmt = $this->conn->prepare("INSERT INTO objets (nom, categorie, quantite, image_path, database_id) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("ssisi", $nom, $categorie, $quantite, $image_path, $this->database_id);
        return $stmt->execute();
    }
    
    public function update($id, $field, $value) {
        $allowedFields = ['nom', 'categorie', 'quantite', 'image_path'];
        if (!in_array($field, $allowedFields, true)) {
            return false;
        }
        
        $id = intval($id);
        $stmt = $this->conn->prepare("UPDATE objets SET $field = ? WHERE id = ? AND database_id = ?");
        
        if ($field === 'quantite') {
            $stmt->bind_param("iii", $value, $id, $this->database_id);
        } else {
            $stmt->bind_param("sii", $value, $id, $this->database_id);
        }
        
        return $stmt->execute();
    }
    
    public function delete($id) {
        $id = intval($id);
        $stmt = $this->conn->prepare("DELETE FROM objets WHERE id = ? AND database_id = ?");
        $stmt->bind_param("ii", $id, $this->database_id);
        return $stmt->execute();
    }
    
    public function getCategories() {
        $stmt = $this->conn->prepare("SELECT DISTINCT categorie FROM objets WHERE database_id = ? AND categorie != '' ORDER BY categorie ASC");
        $stmt->bind_param("i", $this->database_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $categories = [];
        
        while ($row = $result->fetch_assoc()) {
            $categories[] = $row['categorie'];
        }
        
        return $categories;
    }
    
    public function count() {
        $stmt = $this->conn->prepare("SELECT COUNT(*) as total FROM objets WHERE database_id = ?");
        $stmt->bind_param("i", $this->database_id);
        $stmt->execute();
        
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            return $row['total'];
        }
        
        return 0;
    }
    
    public function incrementQuantity($id) {
        $id = intval($id);
        $stmt = $this->conn->prepare("UPDATE objets SET quantite = quantite + 1 WHERE id = ? AND database_id = ?");
        $stmt->bind_param("ii", $id, $this->database_id);
        return $stmt->execute();
    }
    
    public function decrementQuantity($id) {
        $id = intval($id);
        $stmt = $this->conn->prepare("UPDATE objets SET quantite = quantite - 1 WHERE id = ? AND database_id = ? AND quantite > 0");
        $stmt->bind_param("ii", $id, $this->database_id);
        return $stmt->execute();
    }
}

// Créer le modèle pour cette base
$objet_model = new DatabaseObjets($conn, $database_id);

// Gérer les actions POST
$action = $_GET['action'] ?? '';

// Ajouter un objet
if ($action === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // Vérifier la permission
    if ($permission !== 'admin' && $permission !== 'edit') {
        FlashMessage::error('Vous n\'avez pas la permission d\'ajouter des objets');
    } elseif (!CsrfToken::verifyFromPost()) {
        FlashMessage::error('Erreur de sécurité');
    } else {
        $nom = $_POST['nom'] ?? '';
        $categorie = $_POST['categorie'] ?? '';
        $quantite = intval($_POST['quantite'] ?? 1);
        
        $nom = Validator::sanitizeText($nom, 100);
        $categorie = Validator::validateCategory($categorie);
        $quantite = Validator::validateQuantity($quantite);
        
        if ($nom && $categorie) {
            if ($objet_model->create($nom, $categorie, $quantite)) {
                FlashMessage::success('Objet ajouté avec succès!');
                header("Location: database.php?id=$database_id");
                exit();
            } else {
                FlashMessage::error('Erreur lors de l\'ajout');
            }
        } else {
            FlashMessage::error('Veuillez remplir tous les champs correctement');
        }
    }
}

// Récupérer les objets
$objets = $objet_model->getAll();
$categories = $objet_model->getCategories();

include __DIR__ . '/../templates/includes/header.phtml';
?>

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
        <input type="text" id="searchInput" onkeyup="filterItems()" placeholder="Rechercher un objet...">
        <select id="categoryFilter" onchange="filterItems()">
            <option value="">Toutes les catégories</option>
            <?php foreach ($categories as $cat): ?>
                <option value="<?php echo htmlspecialchars($cat); ?>"><?php echo htmlspecialchars($cat); ?></option>
            <?php endforeach; ?>
        </select>
        
        <?php if ($permission === 'admin' || $permission === 'edit'): ?>
            <button class="btn-primary" onclick="toggleAddForm()">+ Ajouter un objet</button>
        <?php endif; ?>
        
        <?php if ($permission === 'admin'): ?>
            <a href="database-settings.php?id=<?php echo $database_id; ?>" class="btn-secondary">Paramètres</a>
        <?php endif; ?>
    </div>

    <!-- Formulaire d'ajout -->
    <?php if ($permission === 'admin' || $permission === 'edit'): ?>
        <div id="addForm" class="add-form" style="display:none;">
            <form method="POST" action="?id=<?php echo $database_id; ?>&action=add">
                <div class="form-group">
                    <label for="nom">Nom *</label>
                    <input type="text" name="nom" id="nom" required placeholder="Nom de l'objet" maxlength="100">
                </div>
                
                <div class="form-group">
                    <label for="categorie">Catégorie *</label>
                    <input type="text" name="categorie" id="categorie" list="categories" required placeholder="Ex: Électronique, Outils...">
                    <datalist id="categories">
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo htmlspecialchars($cat); ?>">
                        <?php endforeach; ?>
                    </datalist>
                </div>
                
                <div class="form-group">
                    <label for="quantite">Quantité</label>
                    <input type="number" name="quantite" id="quantite" value="1" min="0">
                </div>
                
                <?php echo CsrfToken::field(); ?>
                
                <div class="form-actions">
                    <button type="submit" class="btn-primary">Ajouter</button>
                    <button type="button" class="btn-secondary" onclick="toggleAddForm()">Annuler</button>
                </div>
            </form>
        </div>
    <?php endif; ?>

    <!-- Grid des objets (comme l'ancienne page) -->
    <div class="grid" id="inventoryGrid">
        <?php if (empty($objets)): ?>
            <div class="empty-state">
                <p>Aucun objet dans cette base.</p>
                <?php if ($permission === 'admin' || $permission === 'edit'): ?>
                    <button class="btn-primary" onclick="toggleAddForm()">Ajouter le premier objet</button>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <?php foreach ($objets as $objet): ?>
                <div class="card" data-name="<?php echo strtolower(htmlspecialchars($objet['nom'])); ?>" data-cat="<?php echo htmlspecialchars($objet['categorie']); ?>">
                    
                    <?php if ($objet['image_path']): ?>
                        <img src="<?php echo htmlspecialchars($objet['image_path']); ?>" alt="<?php echo htmlspecialchars($objet['nom']); ?>">
                    <?php else: ?>
                        <div class="card-no-image">
                            <span>📷</span>
                            <p>Pas de photo</p>
                        </div>
                    <?php endif; ?>

                    <div class="card-details">
                        <h3><?php echo htmlspecialchars($objet['nom']); ?></h3>
                        
                        <span class="tag"><?php echo htmlspecialchars($objet['categorie']); ?></span>
                        
                        <div class="qty-zone">
                            <?php if ($permission === 'admin' || $permission === 'edit'): ?>
                                <button class="btn-qty" onclick="updateQuantity(<?php echo $objet['id']; ?>, 'dec')">-</button>
                            <?php endif; ?>
                            <span class="qty-val" id="qty-<?php echo $objet['id']; ?>">
                                <?php echo $objet['quantite']; ?>
                            </span>
                            <?php if ($permission === 'admin' || $permission === 'edit'): ?>
                                <button class="btn-qty" onclick="updateQuantity(<?php echo $objet['id']; ?>, 'inc')">+</button>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($permission === 'admin' || $permission === 'edit'): ?>
                            <a href="#" class="delete-link" onclick="deleteObjet(<?php echo $objet['id']; ?>); return false;">🗑 Supprimer</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<script>
function toggleAddForm() {
    const form = document.getElementById('addForm');
    if (form) {
        form.style.display = form.style.display === 'none' ? 'block' : 'none';
    }
}

// Filtre et recherche (comme l'ancienne page)
function filterItems() {
    const searchInput = document.getElementById('searchInput').value.toLowerCase();
    const categoryFilter = document.getElementById('categoryFilter').value;
    const cards = document.querySelectorAll('.grid .card');
    
    cards.forEach(card => {
        const name = card.dataset.name || '';
        const category = card.dataset.cat || '';
        
        const matchesSearch = name.includes(searchInput);
        const matchesCategory = categoryFilter === '' || category === categoryFilter;
        
        card.style.display = (matchesSearch && matchesCategory) ? 'block' : 'none';
    });
}

function updateQuantity(id, action) {
    const currentQty = parseInt(document.getElementById('qty-' + id).textContent);
    let newQty = currentQty;
    
    if (action === 'inc') {
        newQty = currentQty + 1;
    } else if (action === 'dec' && currentQty > 0) {
        newQty = currentQty - 1;
    }
    
    // TODO: Implémenter l'appel AJAX pour mettre à jour la BD
    document.getElementById('qty-' + id).textContent = newQty;
}

function deleteObjet(id) {
    if (confirm('Êtes-vous sûr de vouloir supprimer cet objet?')) {
        // TODO: Implémenter la suppression
    }
}
</script>

<?php include __DIR__ . '/../templates/includes/footer.html'; ?>
