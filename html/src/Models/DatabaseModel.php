<?php

class DatabaseModel {
    private $conn;

    public function __construct($database) {
        $this->conn = $database;
    }

    public function create($name, $owner_id, $description = '') {

        if (!$name || strlen($name) < 3) {
            return ['success' => false, 'message' => 'Le nom doit contenir au moins 3 caractères'];
        }
        $name = Validator::sanitizeText($name, 100);

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
            
            $this->addPermission($db_id, $owner_id, 'admin');
            
            return ['success' => true, 'message' => 'Base de données créée!', 'id' => $db_id];
        } else {
            return ['success' => false, 'message' => 'Erreur lors de la création'];
        }
    }

    public function getAccessibleDatabases($user_id) {

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

   

    public function isOwner($database_id, $user_id) {
        $database_id = intval($database_id);
        $user_id = intval($user_id);
        
        $stmt = $this->conn->prepare("SELECT id FROM `databases` WHERE id = ? AND owner_id = ?");
        $stmt->bind_param("ii", $database_id, $user_id);
        $stmt->execute();
        
        return $stmt->get_result()->num_rows > 0;
    }

    public function getPermission($database_id, $user_id) {
        $database_id = intval($database_id);
        $user_id = intval($user_id);
        
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

    public function addPermission($database_id, $user_id, $permission = 'view') {
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


    public function removePermission($database_id, $user_id) {
        $database_id = intval($database_id);
        $user_id = intval($user_id);
        
        $stmt = $this->conn->prepare("DELETE FROM `database_permissions` WHERE database_id = ? AND user_id = ?");
        $stmt->bind_param("ii", $database_id, $user_id);
        
        return $stmt->execute();
    }


    public function getSharedUsers($database_id) {
        $database_id = intval($database_id);
        
        $stmt = $this->conn->prepare("
            SELECT u.id, u.username, u.email, u.profile_image, dp.permission, dp.id as permission_id
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


    public function delete($database_id) {
        $database_id = intval($database_id);
        
        $stmt = $this->conn->prepare("SELECT image_path FROM objets WHERE database_id = ? AND image_path IS NOT NULL AND image_path != ''");
        $stmt->bind_param("i", $database_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $imagesToDelete = [];
        while ($row = $result->fetch_assoc()) {
            $imagesToDelete[] = $row['image_path'];
        }
        
        $this->conn->begin_transaction();
        
        try {
            $stmt = $this->conn->prepare("DELETE FROM `database_permissions` WHERE database_id = ?");
            $stmt->bind_param("i", $database_id);
            $stmt->execute();
            
            $stmt = $this->conn->prepare("DELETE FROM objets WHERE database_id = ?");
            $stmt->bind_param("i", $database_id);
            $stmt->execute();
            
            $stmt = $this->conn->prepare("UPDATE categories SET parent_id = NULL WHERE database_id = ?");
            $stmt->bind_param("i", $database_id);
            $stmt->execute();
            
            $stmt = $this->conn->prepare("DELETE FROM categories WHERE database_id = ?");
            $stmt->bind_param("i", $database_id);
            $stmt->execute();
            
            $stmt = $this->conn->prepare("DELETE FROM `databases` WHERE id = ?");
            $stmt->bind_param("i", $database_id);
            $stmt->execute();
            
            $this->conn->commit();
            
            $uploadsDir = dirname(__DIR__, 2) . '/public/uploads/';
            foreach ($imagesToDelete as $img) {
                $file = $uploadsDir . $img;
                if (file_exists($file)) {
                    @unlink($file);
                }
            }
            
            return true;
        } catch (Exception $e) {
            $this->conn->rollback();
            return false;
        }
    }

    public function update($database_id, $name, $description) {
        $database_id = intval($database_id);
        $name = Validator::sanitizeText($name, 100);
        $description = Validator::sanitizeText($description, 255);
        
        $stmt = $this->conn->prepare("UPDATE `databases` SET name = ?, description = ? WHERE id = ?");
        $stmt->bind_param("ssi", $name, $description, $database_id);
        
        return $stmt->execute();
    }

    
    public function renameCategory($category_id, $new_name, $database_id) {
        $category_id = intval($category_id);
        $database_id = intval($database_id);
        $new_name = Validator::sanitizeText($new_name, 100);
        
        if (!$new_name) {
            return false;
        }
        
        $stmt = $this->conn->prepare("UPDATE categories SET nom = ? WHERE id = ? AND database_id = ?");
        $stmt->bind_param("sii", $new_name, $category_id, $database_id);
        
        return $stmt->execute();
    }

    
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

    $stmt = $this->conn->prepare("UPDATE objets SET id_categorie = NULL WHERE database_id = ? AND id_categorie IN (SELECT id FROM categories WHERE parent_id = ? AND database_id = ?)");
    $stmt->bind_param("iii", $database_id, $category_id, $database_id);
    $stmt->execute();
    $stmt->close();

    $stmt = $this->conn->prepare("UPDATE objets SET id_categorie = NULL WHERE id_categorie = ? AND database_id = ?");
    $stmt->bind_param("ii", $category_id, $database_id);
    $stmt->execute();
    $stmt->close();

    $stmt = $this->conn->prepare("DELETE FROM categories WHERE parent_id = ? AND database_id = ?");
    $stmt->bind_param("ii", $category_id, $database_id);
    $stmt->execute();
    $stmt->close();

    $stmt = $this->conn->prepare("DELETE FROM categories WHERE id = ? AND database_id = ?");
    $stmt->bind_param("ii", $category_id, $database_id);
    
    return $stmt->execute();
}
}
?>
