<?php

class Validator {
    
    /**
     * Valide et nettoie une chaîne de texte
     */
    public static function sanitizeText($value, $maxLength = 255) {
        if (!is_string($value)) {
            return '';
        }
        $value = trim($value);
        $value = stripslashes($value);
        $value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        if (strlen($value) > $maxLength) {
            $value = substr($value, 0, $maxLength);
        }
        return $value;
    }

    /**
     * Valide une quantité (doit être un entier positif)
     */
    public static function validateQuantity($value) {
        $value = intval($value);
        return max(0, $value);
    }

    /**
     * Vérifie si un texte est vide
     */
    public static function isNotEmpty($value) {
        return !empty(trim($value));
    }

    /**
     * Valide une catégorie (autorise alphanumérique, espaces, tirets)
     */
    public static function validateCategory($value) {
        $value = self::sanitizeText($value, 100);
        // Vérifier que c'est du texte valide (pas de caractères spéciaux)
        if (!preg_match('/^[a-zA-Z0-9\s\-àâäçéèêëîïôöœûüù]+$/u', $value)) {
            return false;
        }
        return $value;
    }

    /**
     * Valide qu'une valeur de champ fait partie d'une whitelist
     */
    public static function validateFieldName($fieldName, $allowedFields = ['nom', 'id_categorie', 'quantite', 'image_path', 'position', 'model', 'purchase_link', 'description', 'qty_used', 'qty_degraded']) {
    return in_array($fieldName, $allowedFields, true);
     }

    /**
     * Valide un fichier image
     */
    public static function validateImageFile($file, $maxSizeInMB = 5) {
        // Vérifier les erreurs de upload
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['valid' => false, 'message' => 'Erreur lors du téléchargement'];
        }

        // Vérifier la taille
        $maxBytes = $maxSizeInMB * 1024 * 1024;
        if ($file['size'] > $maxBytes) {
            return ['valid' => false, 'message' => "Image trop grande (max {$maxSizeInMB}MB)"];
        }

        // Vérifier le type MIME
        $mimeType = null;
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo) {
                $mimeType = finfo_file($finfo, $file['tmp_name']);
                finfo_close($finfo);
            }
        }

        // Fallback si finfo échoue ou n'est pas dispo
        if (!$mimeType) {
            $info = getimagesize($file['tmp_name']);
            if ($info) $mimeType = $info['mime'];
        }

        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        if (!$mimeType || !in_array($mimeType, $allowedMimes, true)) {
            return ['valid' => false, 'message' => 'Format d\'image non autorisé'];
        }

        // Vérifier que c'est vraiment une image
        $imageInfo = getimagesize($file['tmp_name']);
        if ($imageInfo === false) {
            return ['valid' => false, 'message' => 'Le fichier n\'est pas une image valide'];
        }

        return ['valid' => true, 'message' => 'OK'];
    }
}
?>
