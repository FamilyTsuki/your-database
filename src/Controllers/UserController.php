<?php

class UserController {
    private $model;

    public function __construct($database) {
        require_once dirname(__DIR__) . '/Models/UserModel.php';
        require_once dirname(__DIR__) . '/Helpers/Validator.php';
        require_once dirname(__DIR__) . '/Helpers/ImageHelper.php';
        $this->model = new UserModel($database);
    }

    public function updateProfile($id, $username, $email, $file = null) {
        $image_path = null;

        // Gestion de l'upload d'image
        if ($file && $file['error'] === UPLOAD_ERR_OK) {
            $val = Validator::validateImageFile($file);
            if (!$val['valid']) {
                return ['success' => false, 'message' => $val['message']];
            }
            
            $filenameBase = 'user_' . $id . '_' . time();
            $dir = __DIR__ . '/../../public/uploads/profiles';
            
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            
            $processedFilename = ImageHelper::processAndSave($file['tmp_name'], $dir, $filenameBase, 500, 500);
            
            if ($processedFilename) {
                $image_path = $processedFilename;
            } else {
                return ['success' => false, 'message' => 'Erreur lors de l\'enregistrement de l\'image'];
            }
        }

        if ($this->model->update($id, $username, $email, $image_path)) {
            // Mise à jour de la session pour refléter les changements immédiatement
            $user = $this->model->getById($id);
            if (session_status() === PHP_SESSION_NONE) session_start();
            
            $_SESSION['username'] = $user['username'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['profile_image'] = $user['profile_image'];
            
            return ['success' => true, 'message' => 'Profil mis à jour', 'user' => $user];
        }
        
        return ['success' => false, 'message' => 'Erreur lors de la mise à jour en base de données'];
    }
}
?>