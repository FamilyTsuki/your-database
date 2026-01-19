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
        $databases = [];
        
        while ($row = $result->fetch_assoc()) {
            $databases[] = $row;
        }
        
        return $databases;
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
     * Vérifie si l'utilisateur a accès à une base
     */
    public function hasAccess($database_id, $user_id) {
        $database_id = intval($database_id);
        $user_id = intval($user_id);
        
        // L'owner a toujours accès
        $stmt = $this->conn->prepare("SELECT id FROM `databases` WHERE id = ? AND owner_id = ?");
        $stmt->bind_param("ii", $database_id, $user_id);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            return true;
        }
        
        // Vérifier les permissions
        $stmt = $this->conn->prepare("SELECT id FROM `database_permissions` WHERE database_id = ? AND user_id = ?");
        $stmt->bind_param("ii", $database_id, $user_id);
        $stmt->execute();
        
        return $stmt->get_result()->num_rows > 0;
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

    /**
     * Met à jour le nom et la description d'une base
     */
    public function update($database_id, $name, $description) {
        $database_id = intval($database_id);
        $name = Validator::sanitizeText($name, 100);
        $description = Validator::sanitizeText($description, 255);
        
        $stmt = $this->conn->prepare("UPDATE `databases` SET name = ?, description = ? WHERE id = ?");
        $stmt->bind_param("ssi", $name, $description, $database_id);
        
        return $stmt->execute();
    }

    /**
     * Renomme une catégorie dans une base de données
     */
    public function renameCategory($database_id, $old_name, $new_name) {
        $database_id = intval($database_id);
        $old_name = Validator::validateCategory($old_name);
        $new_name = Validator::validateCategory($new_name);
        
        if (!$old_name || !$new_name) {
            return false;
        }
        
        $stmt = $this->conn->prepare("UPDATE objets SET categorie = ? WHERE database_id = ? AND categorie = ?");
        $stmt->bind_param("sis", $new_name, $database_id, $old_name);
        
        return $stmt->execute();
    }

    /**
     * Récupère toutes les catégories d'une base de données
     */
    public function getCategories($database_id) {
        $database_id = intval($database_id);
        
        $stmt = $this->conn->prepare("SELECT DISTINCT categorie FROM objets WHERE database_id = ? AND categorie != '' ORDER BY categorie ASC");
        $stmt->bind_param("i", $database_id);
        $stmt->execute();
        
        $result = $stmt->get_result();
        $categories = [];
        
        while ($row = $result->fetch_assoc()) {
            $categories[] = $row['categorie'];
        }
        
        return $categories;
    }
}
?>
