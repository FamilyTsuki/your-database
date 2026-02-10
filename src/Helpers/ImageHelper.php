<?php

class ImageHelper {
    /**
     * Redimensionne et convertit une image en WebP
     * @param string $sourcePath Chemin du fichier temporaire
     * @param string $destinationDir Dossier de destination
     * @param string $filenameBase Nom du fichier sans extension
     * @param int $maxWidth Largeur max
     * @param int $maxHeight Hauteur max
     * @param int $quality Qualité WebP (0-100)
     * @return string|false Le nom du fichier final (.webp) ou false si erreur
     */
    public static function processAndSave($sourcePath, $destinationDir, $filenameBase, $maxWidth = 1024, $maxHeight = 1024, $quality = 80) {
        if (!file_exists($sourcePath)) return false;
        
        $imgInfo = getimagesize($sourcePath);
        if (!$imgInfo) return false;
        
        list($width, $height, $type) = $imgInfo;
        
        $image = null;
        
        switch ($type) {
            case IMAGETYPE_JPEG: 
                if (function_exists('imagecreatefromjpeg')) $image = imagecreatefromjpeg($sourcePath); 
                break;
            case IMAGETYPE_PNG: 
                if (function_exists('imagecreatefrompng')) $image = imagecreatefrompng($sourcePath); 
                break;
            case IMAGETYPE_GIF: 
                if (function_exists('imagecreatefromgif')) $image = imagecreatefromgif($sourcePath); 
                break;
            case IMAGETYPE_WEBP: 
                if (function_exists('imagecreatefromwebp')) $image = imagecreatefromwebp($sourcePath); 
                break;
            default: return false;
        }

        // Si GD n'a pas pu charger l'image (extension manquante ou erreur), on fait une copie simple
        if (!$image) {
            $ext = image_type_to_extension($type, false);
            if (!$ext) {
                $map = [IMAGETYPE_JPEG=>'jpg', IMAGETYPE_PNG=>'png', IMAGETYPE_GIF=>'gif', IMAGETYPE_WEBP=>'webp'];
                $ext = $map[$type] ?? 'jpg';
            }
            if ($ext === 'jpeg') $ext = 'jpg';
            
            $finalFilename = $filenameBase . '.' . $ext;
            if (copy($sourcePath, $destinationDir . '/' . $finalFilename)) {
                return $finalFilename;
            }
            return false;
        }

        // Calcul des nouvelles dimensions (ratio conservé)
        $ratio = $width / $height;
        if ($width > $maxWidth || $height > $maxHeight) {
            if ($width > $height) {
                $newWidth = $maxWidth;
                $newHeight = $maxWidth / $ratio;
            } else {
                $newHeight = $maxHeight;
                $newWidth = $maxHeight * $ratio;
            }
        } else {
            $newWidth = $width;
            $newHeight = $height;
        }

        $newImage = imagecreatetruecolor($newWidth, $newHeight);

        // Gestion de la transparence (important pour PNG/WebP)
        if ($type == IMAGETYPE_PNG || $type == IMAGETYPE_WEBP || $type == IMAGETYPE_GIF) {
            imagealphablending($newImage, false);
            imagesavealpha($newImage, true);
            $transparent = imagecolorallocatealpha($newImage, 255, 255, 255, 127);
            imagefilledrectangle($newImage, 0, 0, $newWidth, $newHeight, $transparent);
        }

        imagecopyresampled($newImage, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        // Sauvegarde en WebP
        $finalFilename = $filenameBase . '.webp';
        $destinationPath = $destinationDir . '/' . $finalFilename;
        
        if (function_exists('imagewebp')) {
            $result = imagewebp($newImage, $destinationPath, $quality);
        } else {
            // Si GD est activé mais sans support WebP, on évite le crash
            $result = false;
        }
        
        imagedestroy($image);
        imagedestroy($newImage);

        return $result ? $finalFilename : false;
    }
}
?>