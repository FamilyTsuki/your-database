<?php

class DatabaseController {
    private $model;

    public function __construct($database) {
        require_once dirname(__DIR__) . '/Models/DatabaseModel.php';
        $this->model = new DatabaseModel($database);
    }
    

    public function create($name, $description = '', $owner_id = null) {
        if ($owner_id === null && isset($_SESSION['user_id'])) {
            $owner_id = $_SESSION['user_id'];
        }

        if (!$owner_id) {
            return ['success' => false, 'message' => 'Utilisateur non authentifié'];
        }

        return $this->model->create($name, $owner_id, $description);
    }


    public function getAccessible($user_id) {
        return $this->model->getAccessibleDatabases($user_id);
    }


    public function getDatabase($database_id) {
        return $this->model->getById($database_id);
    }


    public function isOwner($database_id, $user_id) {
        return $this->model->isOwner($database_id, $user_id);
    }

 
    public function getPermission($database_id, $user_id) {
        return $this->model->getPermission($database_id, $user_id);
    }


    public function addUser($database_id, $user_id, $permission = 'view') {
        return $this->model->addPermission($database_id, $user_id, $permission);
    }


    public function removeUser($database_id, $user_id) {
        return $this->model->removePermission($database_id, $user_id);
    }


    public function getSharedUsers($database_id) {
        return $this->model->getSharedUsers($database_id);
    }


    public function delete($database_id) {
        return $this->model->delete($database_id);
    }


    public function update($database_id, $name, $description) {
        return $this->model->update($database_id, $name, $description);
    }


    public function renameCategory($category_id, $new_name, $database_id) {

        return $this->model->renameCategory($category_id, $new_name, $database_id);
    }


    public function getCategories($database_id) {
        return $this->model->getCategories($database_id);
    }

    public function deleteCategory($category_id, $database_id) {
        $user_id = Auth::getUserId();
    
        $permission = $this->getPermission($database_id, $user_id);
        if ($permission !== 'admin') {
            return ['success' => false, 'message' => 'Action non autorisée'];
        }

        if ($this->model->deleteCategorySecure($category_id, $database_id)) {
            return ['success' => true, 'message' => 'Catégorie supprimée ✓'];
        }
        
        return ['success' => false, 'message' => 'Erreur lors de la suppression'];
    }
}
?>
