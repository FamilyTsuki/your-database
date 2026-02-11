<?php

class ObjetController {
    private $model;

    public function __construct($database) {
        require_once dirname(__DIR__) . '/Models/ObjetModel.php';
        $this->model = new ObjetModel($database);
    }

    /**
     * Ajoute un nouvel objet
     */
    public function add($database_id, $nom, $id_categorie, $quantite, $image_path = '') {
        if (!Validator::isNotEmpty($nom)) {
            return ['success' => false, 'message' => 'Le nom est requis'];
        }
        
        // On valide que l'ID de catégorie est un nombre
        $id_categorie = intval($id_categorie);
        if ($id_categorie <= 0) {
            // Optionnel : permettre 0 ou null si "Sans catégorie" est autorisé
            $id_categorie = null; 
        }

        $nom = Validator::sanitizeText($nom, 100);
        $quantite = Validator::validateQuantity($quantite);

        // On passe id_categorie au lieu de categorie
        if ($this->model->create($database_id, $nom, $id_categorie, $quantite, $image_path)) {
            return ['success' => true, 'message' => 'Objet ajouté avec succès ✓'];
        } else {
            return ['success' => false, 'message' => 'Erreur lors de l\'ajout'];
        }
    }

    /**
     * Met à jour un champ
     */
    public function update($id, $field, $value, $database_id = null) {
        $id = intval($id);
        
        if ($id <= 0) {
            return ['success' => false, 'message' => 'ID invalide'];
        }

        // On change 'categorie' par 'id_categorie' dans les tests ci-dessous
        if ($field === 'categorie') $field = 'id_categorie';

        if (!Validator::validateFieldName($field)) {
            return ['success' => false, 'message' => 'Champ non autorisé'];
        }

        if ($field === 'nom') {
            if (!Validator::isNotEmpty($value)) {
                return ['success' => false, 'message' => 'Le nom ne peut pas être vide'];
            }
            $value = Validator::sanitizeText($value, 100);
        } 
        elseif ($field === 'id_categorie') { // Changé ici
            $value = intval($value);
            if ($value <= 0) $value = null; // Permet de remettre à "Sans catégorie"
        } 
        elseif ($field === 'quantite') {
            $value = Validator::validateQuantity($value);
        }

        if ($this->model->update($id, $field, $value, $database_id)) {
            return ['success' => true, 'message' => 'Modification enregistrée ✓'];
        } else {
            return ['success' => false, 'message' => 'Erreur lors de la mise à jour'];
        }
    }

    /**
     * Supprime un objet
     */
    public function delete($id, $database_id = null) {
        $id = intval($id);
        
        if ($id <= 0) {
            return ['success' => false, 'message' => 'ID invalide'];
        }

        if ($this->model->delete($id, $database_id)) {
            return ['success' => true, 'message' => 'Objet supprimé ✓'];
        } else {
            return ['success' => false, 'message' => 'Erreur lors de la suppression'];
        }
    }

    /**
     * Incrémente la quantité
     */
    public function incrementQuantity($id, $database_id = null) {
        $id = intval($id);
        
        if ($id <= 0) {
            return ['success' => false, 'message' => 'ID invalide'];
        }

        $this->model->incrementQuantity($id, $database_id);
        return ['success' => true, 'message' => 'Quantité augmentée ✓'];
    }

    /**
     * Décrémente la quantité
     */
    public function decrementQuantity($id, $database_id = null) {
        $id = intval($id);
        
        if ($id <= 0) {
            return ['success' => false, 'message' => 'ID invalide'];
        }

        $this->model->decrementQuantity($id, $database_id);
        return ['success' => true, 'message' => 'Quantité diminuée ✓'];
    }

    /**
     * Récupère tous les objets
     */
    public function getAll() {
        return $this->model->getAll();
    }

    /**
     * Récupère les catégories
     */
    public function getCategories() {
        return $this->model->getCategories();
    }

    /**
     * Récupère un objet par ID
     */
    public function getById($id) {
        return $this->model->getById($id);
    }
}
?>
