<?php

namespace App\Controllers;

use App\Models\ObjetModel;

class ObjetController {
    private $model;

    public function __construct($database) {
        $this->model = new ObjetModel($database);
    }

    /**
     * Ajoute un nouvel objet
     */
    public function add($nom, $categorie, $quantite, $image_path = '') {
        // Validations
        if (!Validator::isNotEmpty($nom)) {
            return ['success' => false, 'message' => 'Le nom est requis'];
        }
        
        if (!Validator::isNotEmpty($categorie)) {
            return ['success' => false, 'message' => 'La catégorie est requise'];
        }

        $nom = Validator::sanitizeText($nom, 100);
        $categorie = Validator::validateCategory($categorie);
        
        if ($categorie === false) {
            return ['success' => false, 'message' => 'Catégorie invalide'];
        }
        
        $quantite = Validator::validateQuantity($quantite);

        if ($this->model->create($nom, $categorie, $quantite, $image_path)) {
            return ['success' => true, 'message' => 'Objet ajouté avec succès ✓'];
        } else {
            return ['success' => false, 'message' => 'Erreur lors de l\'ajout'];
        }
    }

    /**
     * Met à jour un champ
     */
    public function update($id, $field, $value) {
        $id = intval($id);
        
        if ($id <= 0) {
            return ['success' => false, 'message' => 'ID invalide'];
        }

        if (!Validator::validateFieldName($field)) {
            return ['success' => false, 'message' => 'Champ non autorisé'];
        }

        // Valider selon le type de champ
        if ($field === 'nom') {
            if (!Validator::isNotEmpty($value)) {
                return ['success' => false, 'message' => 'Le nom ne peut pas être vide'];
            }
            $value = Validator::sanitizeText($value, 100);
        } 
        elseif ($field === 'categorie') {
            if (!Validator::isNotEmpty($value)) {
                return ['success' => false, 'message' => 'La catégorie ne peut pas être vide'];
            }
            $value = Validator::validateCategory($value);
            if ($value === false) {
                return ['success' => false, 'message' => 'Catégorie invalide'];
            }
        } 
        elseif ($field === 'quantite') {
            $value = Validator::validateQuantity($value);
        }

        if ($this->model->update($id, $field, $value)) {
            return ['success' => true, 'message' => 'Modification enregistrée ✓'];
        } else {
            return ['success' => false, 'message' => 'Erreur lors de la mise à jour'];
        }
    }

    /**
     * Supprime un objet
     */
    public function delete($id) {
        $id = intval($id);
        
        if ($id <= 0) {
            return ['success' => false, 'message' => 'ID invalide'];
        }

        if ($this->model->delete($id)) {
            return ['success' => true, 'message' => 'Objet supprimé ✓'];
        } else {
            return ['success' => false, 'message' => 'Erreur lors de la suppression'];
        }
    }

    /**
     * Incrémente la quantité
     */
    public function incrementQuantity($id) {
        $id = intval($id);
        
        if ($id <= 0) {
            return ['success' => false, 'message' => 'ID invalide'];
        }

        $this->model->incrementQuantity($id);
        return ['success' => true, 'message' => 'Quantité augmentée ✓'];
    }

    /**
     * Décrémente la quantité
     */
    public function decrementQuantity($id) {
        $id = intval($id);
        
        if ($id <= 0) {
            return ['success' => false, 'message' => 'ID invalide'];
        }

        $this->model->decrementQuantity($id);
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
