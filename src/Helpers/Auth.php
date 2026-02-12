<?php

class Auth {
    private $conn;

    public function __construct($database) {
        $this->conn = $database;
    }

    /**
     * Démarre la session de manière sécurisée
     */
    public static function initSession() {
        if (session_status() === PHP_SESSION_NONE) {
            // Configuration sécurisée des cookies
            session_set_cookie_params([
                'lifetime' => 0, 'path' => '/', 'domain' => '',
                'secure' => isset($_SERVER['HTTPS']), 'httponly' => true, 'samesite' => 'Lax'
            ]);
            session_start();
        }
        
        // SÉCURITÉ : En-têtes HTTP pour protéger le navigateur
        // Empêche le site d'être chargé dans une iframe (Clickjacking)
        header('X-Frame-Options: SAMEORIGIN');
        // Empêche le navigateur de deviner le type MIME (évite l'exécution de fichiers texte comme du JS)
        header('X-Content-Type-Options: nosniff');
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

        if (strlen($password) < 8) {
            return ['success' => false, 'message' => 'Le mot de passe doit contenir au moins 8 caractères'];
        }

        // Vérifier que l'utilisateur n'existe pas déjà
        $stmt = $this->conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->bind_param("ss", $username, $email);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            // Protection anti-énumération / spam
            usleep(500000); // 500ms
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

        $stmt = $this->conn->prepare("SELECT id, username, email, password, profile_image, redirect_on_add, skip_source_modal, prefer_gallery, dark_mode FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            // Protection contre le brute-force : délai de 500ms
            usleep(500000);
            return ['success' => false, 'message' => 'Pseudo ou mot de passe incorrect'];
        }

        $user = $result->fetch_assoc();

        if (!password_verify($password, $user['password'])) {
            // Protection contre le brute-force : délai de 500ms
            usleep(500000);
            return ['success' => false, 'message' => 'Pseudo ou mot de passe incorrect'];
        }

        // Créer la session
        self::initSession();

        // SÉCURITÉ : Régénérer l'ID de session pour éviter la fixation de session
        session_regenerate_id(true);

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['profile_image'] = $user['profile_image'];
        $_SESSION['redirect_on_add'] = $user['redirect_on_add'];
        $_SESSION['skip_source_modal'] = $user['skip_source_modal'];
        $_SESSION['prefer_gallery'] = $user['prefer_gallery'];
        $_SESSION['dark_mode'] = $user['dark_mode'];

        return ['success' => true, 'message' => 'Connexion réussie!'];
    }

    /**
     * Déconnecte l'utilisateur
     */
    public function logout() {
        self::initSession();
        
        session_destroy();
        return true;
    }

    /**
     * Vérifie si l'utilisateur est connecté
     */
    public static function isLoggedIn() {
        self::initSession();
        
        return isset($_SESSION['user_id']);
    }

    /**
     * Récupère l'utilisateur connecté
     */
    public static function getUser() {
        self::initSession();
        
        if (self::isLoggedIn()) {
            return [
                'id' => $_SESSION['user_id'],
                'username' => $_SESSION['username'],
                'email' => $_SESSION['email'],
                'profile_image' => $_SESSION['profile_image'] ?? null,
                'redirect_on_add' => $_SESSION['redirect_on_add'] ?? 1,
                'skip_source_modal' => $_SESSION['skip_source_modal'] ?? 0,
                'prefer_gallery' => $_SESSION['prefer_gallery'] ?? 0,
                'dark_mode' => $_SESSION['dark_mode'] ?? 0
            ];
        }
        
        return null;
    }

    /**
     * Récupère l'ID de l'utilisateur connecté
     */
    public static function getUserId() {
        self::initSession();
        
        return $_SESSION['user_id'] ?? null;
    }

    /**
     * Requiert une authentification (redirige vers login si pas connecté)
     */
    public static function requireLogin() {
        if (!self::isLoggedIn()) {
            header("Location: login.php");
            exit();
        }
    }
}
?>
