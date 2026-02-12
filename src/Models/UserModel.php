<?php

class UserModel {
    private $conn;

    public function __construct($database) {
        $this->conn = $database;
    }

    public function getById($id) {
        $id = intval($id);
        $stmt = $this->conn->prepare("SELECT id, username, email, profile_image, created_at, redirect_on_add, skip_source_modal, prefer_gallery, dark_mode FROM users WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    public function update($id, $username, $email, $image_path = null, $redirect_on_add = 1, $skip_source_modal = 0, $prefer_gallery = 0, $dark_mode = 0) {
        $id = intval($id);
        $username = Validator::sanitizeText($username, 50);
        $email = filter_var($email, FILTER_SANITIZE_EMAIL);
        $redirect_on_add = intval($redirect_on_add);
        $skip_source_modal = intval($skip_source_modal);
        $prefer_gallery = intval($prefer_gallery);
        $dark_mode = intval($dark_mode);
        
        // Construction dynamique de la requête
        $sql = "UPDATE users SET username = ?, email = ?, redirect_on_add = ?, skip_source_modal = ?, prefer_gallery = ?, dark_mode = ?";
        $params = [$username, $email, $redirect_on_add, $skip_source_modal, $prefer_gallery, $dark_mode];
        $types = "ssiiii";
        
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