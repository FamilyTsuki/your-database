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
    // Utilisation de requêtes préparées pour la pagination
        $query = "SELECT objets.*, categories.nom AS nom_categorie, p.nom AS parent_nom
            FROM objets 
            LEFT JOIN categories ON objets.id_categorie = categories.id
            LEFT JOIN categories p ON categories.parent_id = p.id
            ORDER BY objets.id DESC";
        
        if ($limit !== null) {
            $stmt = $this->conn->prepare($query . " LIMIT ? OFFSET ?");
            $l = intval($limit);
            $o = intval($offset);
            $stmt->bind_param("ii", $l, $o);
            $stmt->execute();
            return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        }
        
        $result = $this->conn->query($query);
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Récupère un objet par ID
     */
    public function getById($id) {
        $stmt = $this->conn->prepare("SELECT objets.*, categories.nom AS nom_categorie, p.nom AS parent_nom
            FROM objets 
            LEFT JOIN categories ON objets.id_categorie = categories.id
            LEFT JOIN categories p ON categories.parent_id = p.id
            WHERE objets.id = ?
        ");
        $id = intval($id);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc() ?: null;
}

    /**
     * Crée un nouvel objet
     */
    public function create($nom, $id_categorie, $quantite, $image_path = '') {
    $stmt = $this->conn->prepare("INSERT INTO objets (nom, id_categorie, quantite, image_path) VALUES (?, ?, ?, ?)");
    
    if (!$stmt) return false;
    
    // Le second paramètre devient "i" pour integer (id_categorie)
    $stmt->bind_param("siis", $nom, $id_categorie, $quantite, $image_path);
    $success = $stmt->execute();
    $stmt->close();
    
    return $success;
}

    /**
     * Met à jour un objet
     */
    public function update($id, $field, $value) {
        // Mise à jour de la whitelist : on remplace 'categorie' par 'id_categorie'
        $allowedFields = ['nom', 'id_categorie', 'quantite', 'image_path', 'position', 'model', 'purchase_link', 'description', 'qty_used', 'qty_degraded'];
        if (!in_array($field, $allowedFields, true)) {
            return false;
        }

        $id = intval($id);
        $stmt = $this->conn->prepare("UPDATE objets SET `$field` = ? WHERE id = ?");
        
        if (!$stmt) return false;

        // Si on modifie la catégorie ou la quantité, c'est un entier (i)
        if (in_array($field, ['quantite', 'id_categorie', 'qty_used', 'qty_degraded'])) {
            $stmt->bind_param("ii", $value, $id);
        } else {
            $stmt->bind_param("si", $value, $id);
        }
        
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }

    /**
     * Met à jour plusieurs champs d'un objet
     */
    public function updateFull($id, $data) {
        $id = intval($id);
        $updates = [];
        $types = "";
        $params = [];

        $allowed = ['nom', 'id_categorie', 'quantite', 'position', 'model', 'purchase_link', 'description', 'qty_used', 'qty_degraded'];

        foreach ($data as $key => $val) {
            if (in_array($key, $allowed)) {
                $updates[] = "`$key` = ?";
                if (in_array($key, ['id_categorie', 'quantite', 'qty_used', 'qty_degraded'])) {
                    $types .= "i";
                    $params[] = intval($val);
                } else {
                    $types .= "s";
                    $params[] = Validator::sanitizeText($val, 2000); // Allow longer text for description
                }
            }
        }

        if (empty($updates)) return false;
        $sql = "UPDATE objets SET " . implode(", ", $updates) . " WHERE id = ?";
        $types .= "i";
        $params[] = $id;
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        return $stmt->execute();
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
