<?php

class UserModel {
    private $conn;

    public function __construct($database) {
        $this->conn = $database;
    }

    public function getById($id) {
        $id = intval($id);
        $stmt = $this->conn->prepare("SELECT id, username, email, profile_image, created_at FROM users WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    public function update($id, $username, $email, $image_path = null) {
        $id = intval($id);
        $username = Validator::sanitizeText($username, 50);
        $email = filter_var($email, FILTER_SANITIZE_EMAIL);
        
        // Construction dynamique de la requête
        $sql = "UPDATE users SET username = ?, email = ?";
        $params = [$username, $email];
        $types = "ss";
        
        if ($image_path !== null) {
            $sql .= ", profile_image = ?";
            $params[] = $image_path;
            $types .= "s";
        }
        
        $sql .= " WHERE id = ?";
        $params[] = $id;
        $types .= "i";
        
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            // Si la préparation échoue (ex: colonne manquante), on retourne false au lieu de crasher
            return false;
        }
        $stmt->bind_param($types, ...$params);
        
        return $stmt->execute();
    }
}
?>