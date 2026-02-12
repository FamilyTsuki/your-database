<?php

class ObjetModel {
    private $conn;

    public function __construct($database) {
        $this->conn = $database;
    }

    /**
     * Récupère tous les objets avec pagination optionnelle
     */
    public function getAll($database_id = null, $limit = null, $offset = 0) {
    // Utilisation de requêtes préparées pour la pagination
        $query = "SELECT objets.*, categories.nom AS nom_categorie, p.nom AS parent_nom
            FROM objets 
            LEFT JOIN categories ON objets.id_categorie = categories.id
            LEFT JOIN categories p ON categories.parent_id = p.id
            WHERE 1=1";
        
        $params = [];
        $types = "";

        if ($database_id !== null) {
            $query .= " AND objets.database_id = ?";
            $params[] = intval($database_id);
            $types .= "i";
        }
        
        $query .= " ORDER BY objets.id DESC";
        
        if ($limit !== null) {
            $query .= " LIMIT ? OFFSET ?";
            $params[] = intval($limit);
            $params[] = intval($offset);
            $types .= "ii";
        }
        
        $stmt = $this->conn->prepare($query);
        if (!empty($params)) $stmt->bind_param($types, ...$params);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Récupère un objet par ID
     */
    public function getById($id, $database_id = null) {
        $sql = "SELECT objets.*, categories.nom AS nom_categorie, p.nom AS parent_nom
            FROM objets 
            LEFT JOIN categories ON objets.id_categorie = categories.id
            LEFT JOIN categories p ON categories.parent_id = p.id
            WHERE objets.id = ?";
        
        if ($database_id !== null) {
            $sql .= " AND objets.database_id = ?";
        }
        
        $stmt = $this->conn->prepare($sql);
        $id = intval($id);
        if ($database_id !== null) {
            $stmt->bind_param("ii", $id, intval($database_id));
        } else {
            $stmt->bind_param("i", $id);
        }
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc() ?: null;
}

    /**
     * Crée un nouvel objet
     */
    public function create($database_id, $nom, $id_categorie, $quantite, $image_path = '') {
    $stmt = $this->conn->prepare("INSERT INTO objets (database_id, nom, id_categorie, quantite, image_path) VALUES (?, ?, ?, ?, ?)");
    
    if (!$stmt) return false;
    
    $database_id = intval($database_id);
    // Le second paramètre devient "i" pour integer (id_categorie)
    $stmt->bind_param("isiis", $database_id, $nom, $id_categorie, $quantite, $image_path);
    $success = $stmt->execute();
    $insert_id = $stmt->insert_id;
    $stmt->close();
    
    return $success ? $insert_id : false;
}

    /**
     * Met à jour un objet
     */
    public function update($id, $field, $value, $database_id = null) {
        // Mise à jour de la whitelist : on remplace 'categorie' par 'id_categorie'
        $allowedFields = ['nom', 'id_categorie', 'quantite', 'image_path', 'position', 'model', 'purchase_link', 'description', 'qty_used', 'qty_degraded'];
        if (!in_array($field, $allowedFields, true)) {
            return false;
        }

        $id = intval($id);
        
        $sql = "UPDATE objets SET `$field` = ? WHERE id = ?";
        if ($database_id !== null) {
            $sql .= " AND database_id = ?";
        }
        
        $stmt = $this->conn->prepare($sql);
        
        if (!$stmt) return false;

        $types = "";
        $params = [];

        // Si on modifie la catégorie ou la quantité, c'est un entier (i)
        if (in_array($field, ['quantite', 'id_categorie', 'qty_used', 'qty_degraded'])) {
            $types .= "i";
            if ($field === 'id_categorie' && $value === null) {
                $params[] = null;
            } else {
                $params[] = intval($value);
            }
        } else {
            $types .= "s";
            $params[] = $value;
        }
        
        $types .= "i";
        $params[] = $id;

        if ($database_id !== null) {
            $types .= "i";
            $params[] = intval($database_id);
        }

        $stmt->bind_param($types, ...$params);
        
        $success = $stmt->execute();
        $stmt->close();
        return $success;
    }

    /**
     * Met à jour plusieurs champs d'un objet
     */
    public function updateFull($id, $data, $database_id = null) {
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
                    if ($key === 'id_categorie' && empty($val)) {
                        $params[] = null; // Permet de remettre à "Sans catégorie" (NULL)
                    } else {
                        $params[] = intval($val);
                    }
                } else {
                    $types .= "s";
                    $limit = 255;
                    if ($key === 'description') $limit = 2000;
                    if ($key === 'nom') $limit = 100;
                    
                    $params[] = Validator::sanitizeText($val, $limit);
                }
            }
        }

        if (empty($updates)) return false;
        
        $sql = "UPDATE objets SET " . implode(", ", $updates) . " WHERE id = ?";
        $types .= "i";
        $params[] = $id;

        if ($database_id !== null) {
            $sql .= " AND database_id = ?";
            $types .= "i";
            $params[] = intval($database_id);
        }

        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        return $stmt->execute();
    }

    /**
     * Supprime un objet
     */
    public function delete($id, $database_id = null) {
        $id = intval($id);
        $sql = "DELETE FROM objets WHERE id = ?";
        if ($database_id !== null) {
            $sql .= " AND database_id = ?";
        }
        
        $stmt = $this->conn->prepare($sql);
        
        if (!$stmt) {
            return false;
        }
        
        if ($database_id !== null) $stmt->bind_param("ii", $id, $database_id);
        else $stmt->bind_param("i", $id);
        
        $success = $stmt->execute();
        $stmt->close();
        
        return $success;
    }

    
    /**
     * Compte le nombre total d'objets
     */
    public function count($database_id = null) {
        if ($database_id !== null) {
            $stmt = $this->conn->prepare("SELECT COUNT(*) as total FROM objets WHERE database_id = ?");
            $stmt->bind_param("i", $database_id);
            $stmt->execute();
            $result = $stmt->get_result();
        } else {
            $result = $this->conn->query("SELECT COUNT(*) as total FROM objets");
        }
        
        if ($result && $row = $result->fetch_assoc()) {
            return $row['total'];
        }
        
        return 0;
    }

    
    /**
     * Incrémente la quantité
     */
    public function incrementQuantity($id, $database_id = null) {
        $id = intval($id);
        $sql = "UPDATE objets SET quantite = quantite + 1 WHERE id = ?";
        if ($database_id !== null) {
            $sql .= " AND database_id = ?";
        }
        $stmt = $this->conn->prepare($sql);
        
        if ($database_id !== null) $stmt->bind_param("ii", $id, intval($database_id));
        else $stmt->bind_param("i", $id);
        
        return $stmt->execute();
    }
    
    /**
     * Décrémente la quantité (min 0)
     */
    public function decrementQuantity($id, $database_id = null) {
        $id = intval($id);
        $sql = "UPDATE objets SET quantite = GREATEST(0, quantite - 1) WHERE id = ?";
        if ($database_id !== null) {
            $sql .= " AND database_id = ?";
        }
        $stmt = $this->conn->prepare($sql);
        
        if ($database_id !== null) $stmt->bind_param("ii", $id, intval($database_id));
        else $stmt->bind_param("i", $id);
        
        return $stmt->execute();
    }
}
?>
