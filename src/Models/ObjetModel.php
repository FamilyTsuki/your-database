<?php

class ObjetModel {
    private $conn;

    public function __construct($database) {
        $this->conn = $database;
    }

    /**
     * Récupère tous les objets avec pagination optionnelle
     */
    public function getAll($limit = null, $offset = 0) {
        $query = "SELECT * FROM objets ORDER BY id DESC";
        
        if ($limit !== null) {
            $limit = intval($limit);
            $offset = intval($offset);
            $query .= " LIMIT $limit OFFSET $offset";
        }
        
        $result = $this->conn->query($query);
        $objets = [];
        
        while ($row = $result->fetch_assoc()) {
            $objets[] = $row;
        }
        
        return $objets;
    }

    /**
     * Récupère un objet par ID
     */
    public function getById($id) {
        $id = intval($id);
        $result = $this->conn->query("SELECT * FROM objets WHERE id = $id");
        
        if ($result && $row = $result->fetch_assoc()) {
            return $row;
        }
        
        return null;
    }

    /**
     * Crée un nouvel objet
     */
    public function create($nom, $categorie, $quantite, $image_path = '') {
        $stmt = $this->conn->prepare("INSERT INTO objets (nom, categorie, quantite, image_path) VALUES (?, ?, ?, ?)");
        
        if (!$stmt) {
            return false;
        }
        
        $stmt->bind_param("ssis", $nom, $categorie, $quantite, $image_path);
        $success = $stmt->execute();
        $stmt->close();
        
        return $success;
    }

    /**
     * Met à jour un objet
     */
    public function update($id, $field, $value) {
        // Whitelist de sécurité
        $allowedFields = ['nom', 'categorie', 'quantite', 'image_path'];
        if (!in_array($field, $allowedFields, true)) {
            return false;
        }

        $id = intval($id);
        $stmt = $this->conn->prepare("UPDATE objets SET $field = ? WHERE id = ?");
        
        if (!$stmt) {
            return false;
        }

        if ($field === 'quantite') {
            $stmt->bind_param("ii", $value, $id);
        } else {
            $stmt->bind_param("si", $value, $id);
        }
        
        $success = $stmt->execute();
        $stmt->close();
        
        return $success;
    }

    /**
     * Supprime un objet
     */
    public function delete($id) {
        $id = intval($id);
        $stmt = $this->conn->prepare("DELETE FROM objets WHERE id = ?");
        
        if (!$stmt) {
            return false;
        }
        
        $stmt->bind_param("i", $id);
        $success = $stmt->execute();
        $stmt->close();
        
        return $success;
    }

    /**
     * Récupère les catégories uniques
     */
    public function getCategories() {
        $result = $this->conn->query("SELECT DISTINCT categorie FROM objets WHERE categorie != '' ORDER BY categorie ASC");
        $categories = [];
        
        while ($row = $result->fetch_assoc()) {
            $categories[] = $row['categorie'];
        }
        
        return $categories;
    }

    /**
     * Compte le nombre total d'objets
     */
    public function count() {
        $result = $this->conn->query("SELECT COUNT(*) as total FROM objets");
        
        if ($result && $row = $result->fetch_assoc()) {
            return $row['total'];
        }
        
        return 0;
    }

    /**
     * Incrémente la quantité
     */
    public function incrementQuantity($id) {
        $id = intval($id);
        $this->conn->query("UPDATE objets SET quantite = quantite + 1 WHERE id = $id");
        return true;
    }

    /**
     * Décrémente la quantité (min 0)
     */
    public function decrementQuantity($id) {
        $id = intval($id);
        $this->conn->query("UPDATE objets SET quantite = GREATEST(0, quantite - 1) WHERE id = $id");
        return true;
    }
}
?>
