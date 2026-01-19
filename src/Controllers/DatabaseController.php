<?php

class DatabaseController {
    private $model;

    public function __construct($database) {
        require_once dirname(__DIR__) . '/Models/DatabaseModel.php';
        $this->model = new DatabaseModel($database);
    }

    /**
     * Crée une nouvelle base de données
     */
    public function create($name, $description = '', $owner_id = null) {
        if ($owner_id === null && isset($_SESSION['user_id'])) {
            $owner_id = $_SESSION['user_id'];
        }

        if (!$owner_id) {
            return ['success' => false, 'message' => 'Utilisateur non authentifié'];
        }

        return $this->model->create($name, $owner_id, $description);
    }

    /**
     * Récupère les bases accessibles
     */
    public function getAccessible($user_id) {
        return $this->model->getAccessibleDatabases($user_id);
    }

    /**
     * Obtient une base par ID
     */
    public function getDatabase($database_id) {
        return $this->model->getById($database_id);
    }

    /**
     * Vérifie l'accès
     */
    public function hasAccess($database_id, $user_id) {
        return $this->model->hasAccess($database_id, $user_id);
    }

    /**
     * Vérifie si propriétaire
     */
    public function isOwner($database_id, $user_id) {
        return $this->model->isOwner($database_id, $user_id);
    }

    /**
     * Obtient la permission
     */
    public function getPermission($database_id, $user_id) {
        return $this->model->getPermission($database_id, $user_id);
    }

    /**
     * Ajoute un utilisateur
     */
    public function addUser($database_id, $user_id, $permission = 'view') {
        return $this->model->addPermission($database_id, $user_id, $permission);
    }

    /**
     * Supprime un utilisateur
     */
    public function removeUser($database_id, $user_id) {
        return $this->model->removePermission($database_id, $user_id);
    }

    /**
     * Récupère les utilisateurs partagés
     */
    public function getSharedUsers($database_id) {
        return $this->model->getSharedUsers($database_id);
    }

    /**
     * Supprime une base
     */
    public function delete($database_id) {
        return $this->model->delete($database_id);
    }

    /**
     * Met à jour une base
     */
    public function update($database_id, $name, $description) {
        return $this->model->update($database_id, $name, $description);
    }

    /**
     * Renomme une catégorie
     */
    public function renameCategory($database_id, $old_name, $new_name) {
        return $this->model->renameCategory($database_id, $old_name, $new_name);
    }

    /**
     * Récupère les catégories
     */
    public function getCategories($database_id) {
        return $this->model->getCategories($database_id);
    }
}
?>
