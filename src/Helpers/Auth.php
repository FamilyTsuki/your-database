<?php

class Auth {
    private $conn;

    public function __construct($database) {
        $this->conn = $database;
    }

    /**
     * Enregistre un nouvel utilisateur
     */
    public function register($username, $email, $password) {
        // Validations
        if (!Validator::isNotEmpty($username) || strlen($username) < 3) {
            return ['success' => false, 'message' => 'Le pseudo doit contenir au moins 3 caractères'];
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Email invalide'];
        }

        if (strlen($password) < 6) {
            return ['success' => false, 'message' => 'Le mot de passe doit contenir au moins 6 caractères'];
        }

        // Vérifier que l'utilisateur n'existe pas déjà
        $stmt = $this->conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->bind_param("ss", $username, $email);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            return ['success' => false, 'message' => 'Pseudo ou email déjà utilisé'];
        }

        // Hacher le mot de passe
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

        // Insérer l'utilisateur
        $stmt = $this->conn->prepare("INSERT INTO users (username, email, password) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $username, $email, $hashedPassword);

        if ($stmt->execute()) {
            return ['success' => true, 'message' => 'Inscription réussie! Vous pouvez maintenant vous connecter.'];
        } else {
            return ['success' => false, 'message' => 'Erreur lors de l\'inscription'];
        }
    }

    /**
     * Connecte un utilisateur
     */
    public function login($username, $password) {
        if (!Validator::isNotEmpty($username) || !Validator::isNotEmpty($password)) {
            return ['success' => false, 'message' => 'Veuillez remplir tous les champs'];
        }

        $stmt = $this->conn->prepare("SELECT id, username, email, password FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            return ['success' => false, 'message' => 'Pseudo ou mot de passe incorrect'];
        }

        $user = $result->fetch_assoc();

        if (!password_verify($password, $user['password'])) {
            return ['success' => false, 'message' => 'Pseudo ou mot de passe incorrect'];
        }

        // Créer la session
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['email'] = $user['email'];

        return ['success' => true, 'message' => 'Connexion réussie!'];
    }

    /**
     * Déconnecte l'utilisateur
     */
    public function logout() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        session_destroy();
        return true;
    }

    /**
     * Vérifie si l'utilisateur est connecté
     */
    public static function isLoggedIn() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        return isset($_SESSION['user_id']);
    }

    /**
     * Récupère l'utilisateur connecté
     */
    public static function getUser() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (self::isLoggedIn()) {
            return [
                'id' => $_SESSION['user_id'],
                'username' => $_SESSION['username'],
                'email' => $_SESSION['email']
            ];
        }
        
        return null;
    }
}
?>
