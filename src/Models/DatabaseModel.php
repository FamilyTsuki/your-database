<?php

class DatabaseModel {
    private $conn;

    public function __construct($database) {
        $this->conn = $database;
    }

    /**
     * Crée une nouvelle base de données
     */
    public function create($name, $owner_id, $description = '') {
        // Valider le nom
        if (!$name || strlen($name) < 3) {
            return ['success' => false, 'message' => 'Le nom doit contenir au moins 3 caractères'];
        }

        // Vérifier que le nom n'existe pas déjà pour cet utilisateur
        $stmt = $this->conn->prepare("SELECT id FROM `databases` WHERE name = ? AND owner_id = ?");
        $stmt->bind_param("si", $name, $owner_id);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            return ['success' => false, 'message' => 'Vous avez déjà une base avec ce nom'];
        }

        $description = Validator::sanitizeText($description, 255);
        $created_at = date('Y-m-d H:i:s');

        $stmt = $this->conn->prepare("INSERT INTO `databases` (name, description, owner_id, created_at) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $name, $description, $owner_id, $created_at);

        if ($stmt->execute()) {
            $db_id = $stmt->insert_id;
            
            // L'owner a tous les droits par défaut
            $this->addPermission($db_id, $owner_id, 'admin');
            
            return ['success' => true, 'message' => 'Base de données créée!', 'id' => $db_id];
        } else {
            return ['success' => false, 'message' => 'Erreur lors de la création'];
        }
    }

    /**
     * Récupère les bases de données accessibles pour un utilisateur
     */
    public function getAccessibleDatabases($user_id) {
        // Bases propres à l'utilisateur + bases partagées
        $query = "
            SELECT DISTINCT 
                d.id, d.name, d.description, d.owner_id, d.created_at,
                dp.permission
            FROM `databases` d
            LEFT JOIN `database_permissions` dp ON d.id = dp.database_id AND dp.user_id = ?
            WHERE d.owner_id = ? OR dp.user_id = ?
            ORDER BY d.created_at DESC
        ";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("iii", $user_id, $user_id, $user_id);
        $stmt->execute();


        
        $result = $stmt->get_result();
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Récupère une base par ID
     */
    public function getById($id) {
        $id = intval($id);
        $stmt = $this->conn->prepare("SELECT * FROM `databases` WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            return $row;
        }
        
        return null;
    }

   

    /**
     * Vérifie si l'utilisateur est propriétaire
     */
    public function isOwner($database_id, $user_id) {
        $database_id = intval($database_id);
        $user_id = intval($user_id);
        
        $stmt = $this->conn->prepare("SELECT id FROM `databases` WHERE id = ? AND owner_id = ?");
        $stmt->bind_param("ii", $database_id, $user_id);
        $stmt->execute();
        
        return $stmt->get_result()->num_rows > 0;
    }

    /**
     * Vérifie les permissions de l'utilisateur pour une base
     */
    public function getPermission($database_id, $user_id) {
        $database_id = intval($database_id);
        $user_id = intval($user_id);
        
        // L'owner est toujours admin
        if ($this->isOwner($database_id, $user_id)) {
            return 'admin';
        }
        
        $stmt = $this->conn->prepare("SELECT permission FROM `database_permissions` WHERE database_id = ? AND user_id = ?");
        $stmt->bind_param("ii", $database_id, $user_id);
        $stmt->execute();
        
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            return $row['permission'];
        }
        
        return null;
    }

    /**
     * Ajoute une permission pour un utilisateur
     */
    public function addPermission($database_id, $user_id, $permission = 'view') {
        // Les permissions valides sont: admin, edit, view
        $validPermissions = ['admin', 'edit', 'view'];
        if (!in_array($permission, $validPermissions)) {
            $permission = 'view';
        }
        
        $database_id = intval($database_id);
        $user_id = intval($user_id);
        
        $stmt = $this->conn->prepare("
            INSERT INTO `database_permissions` (database_id, user_id, permission) 
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE permission = ?
        ");
        $stmt->bind_param("iiss", $database_id, $user_id, $permission, $permission);
        
        return $stmt->execute();
    }

    /**
     * Supprime une permission
     */
    public function removePermission($database_id, $user_id) {
        $database_id = intval($database_id);
        $user_id = intval($user_id);
        
        $stmt = $this->conn->prepare("DELETE FROM `database_permissions` WHERE database_id = ? AND user_id = ?");
        $stmt->bind_param("ii", $database_id, $user_id);
        
        return $stmt->execute();
    }

    /**
     * Récupère tous les utilisateurs ayant accès à une base
     */
    public function getSharedUsers($database_id) {
        $database_id = intval($database_id);
        
        $stmt = $this->conn->prepare("
            SELECT u.id, u.username, u.email, dp.permission, dp.id as permission_id
            FROM users u
            JOIN `database_permissions` dp ON u.id = dp.user_id
            WHERE dp.database_id = ?
            ORDER BY u.username ASC
        ");
        $stmt->bind_param("i", $database_id);
        $stmt->execute();
        
        $result = $stmt->get_result();
        $users = [];
        
        while ($row = $result->fetch_assoc()) {
            $users[] = $row;
        }
        
        return $users;
    }

    /**
     * Supprime une base de données (et tous ses objets et permissions)
     */
    public function delete($database_id) {
        $database_id = intval($database_id);
        
        // Supprimer les permissions
        $stmt = $this->conn->prepare("DELETE FROM `database_permissions` WHERE database_id = ?");
        $stmt->bind_param("i", $database_id);
        $stmt->execute();
        
        // Supprimer les objets
        $stmt = $this->conn->prepare("DELETE FROM objets WHERE database_id = ?");
        $stmt->bind_param("i", $database_id);
        $stmt->execute();
        
        // Supprimer la base
        $stmt = $this->conn->prepare("DELETE FROM `databases` WHERE id = ?");
        $stmt->bind_param("i", $database_id);
        
        return $stmt->execute();
    }
    public function deleteCategory2($category_id) {
    // On prépare la requête de suppression
        $stmt = $this->conn->prepare("DELETE FROM categories WHERE id = ?");
        $stmt->bind_param("i", $category_id);
        return $stmt->execute();
    }

    /**
     * Met à jour le nom et la description d'une base
     */
    public function update($database_id, $name, $description, $redirect_on_add = 1) {
        $database_id = intval($database_id);
        $name = Validator::sanitizeText($name, 100);
        $description = Validator::sanitizeText($description, 255);
        $redirect_on_add = intval($redirect_on_add);
        
        $stmt = $this->conn->prepare("UPDATE `databases` SET name = ?, description = ?, redirect_on_add = ? WHERE id = ?");
        $stmt->bind_param("ssii", $name, $description, $redirect_on_add, $database_id);
        
        return $stmt->execute();
    }

    /**
     * Renomme une catégorie dans une base de données
     */
    /**
 * Renomme une catégorie dans la table globale des catégories
 */
    public function renameCategory($category_id, $new_name) {
        $category_id = intval($category_id);
        $new_name = Validator::sanitizeText($new_name, 100);
        
        if (!$new_name) {
            return false;
        }
        
        // On met à jour le nom directement dans la table des catégories
        $stmt = $this->conn->prepare("UPDATE categories SET nom = ? WHERE id = ?");
        $stmt->bind_param("si", $new_name, $category_id);
        
        return $stmt->execute();
    }

    /**
     * Récupère toutes les catégories d'une base de données
     */
    /**
 * Récupère toutes les catégories (utilisé pour les filtres et menus)
 */
   public function getCategories($database_id) {
    $stmt = $this->conn->prepare("SELECT id, nom, parent_id FROM categories WHERE database_id = ? ORDER BY nom ASC");
    $stmt->bind_param("i", $database_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_all(MYSQLI_ASSOC);
}
public function deleteCategorySecure($category_id, $database_id) {
    $category_id = intval($category_id);
    $database_id = intval($database_id);

    // 1. Supprimer d'abord les sous-catégories (enfants)
    $stmt = $this->conn->prepare("DELETE FROM categories WHERE parent_id = ? AND database_id = ?");
    $stmt->bind_param("ii", $category_id, $database_id);
    $stmt->execute();
    $stmt->close();

    // 2. Supprimer la catégorie parente
    $stmt = $this->conn->prepare("DELETE FROM categories WHERE id = ? AND database_id = ?");
    $stmt->bind_param("ii", $category_id, $database_id);
    
    return $stmt->execute();
}
}
?>
