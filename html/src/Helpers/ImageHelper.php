<?php
 
class ImageHelper {
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

        if (!$image) return false;

        // --- ICI : ON REMET L'IMAGE DROITE SI ELLE A UNE ÉTIQUETTE DE ROTATION ---
        if ($type === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
            $exif = @exif_read_data($sourcePath);
            if ($exif && isset($exif['Orientation'])) {
                switch ($exif['Orientation']) {
                    case 3:
                        $image = imagerotate($image, 180, 0);
                        break;
                    case 6:
                        $image = imagerotate($image, -90, 0);
                        // Important : on inverse largeur et hauteur car l'image a pivoté
                        $temp = $width; $width = $height; $height = $temp;
                        break;
                    case 8:
                        $image = imagerotate($image, 90, 0);
                        // Important : on inverse largeur et hauteur
                        $temp = $width; $width = $height; $height = $temp;
                        break;
                }
            }
        }

        // Calcul du ratio (utilisera les nouvelles dimensions si pivotées)
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
        
        // PHP 8+ nécessite des entiers pour imagecreatetruecolor
        $newWidth = (int)$newWidth;
        $newHeight = (int)$newHeight;

        $newImage = imagecreatetruecolor($newWidth, $newHeight);

        if ($type == IMAGETYPE_PNG || $type == IMAGETYPE_WEBP || $type == IMAGETYPE_GIF) {
            imagealphablending($newImage, false);
            imagesavealpha($newImage, true);
            $transparent = imagecolorallocatealpha($newImage, 255, 255, 255, 127);
            imagefilledrectangle($newImage, 0, 0, $newWidth, $newHeight, $transparent);
        }

        // On recopie l'image (maintenant dans le bon sens) vers la nouvelle taille
        imagecopyresampled($newImage, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        // Tentative de sauvegarde en WebP, sinon repli sur JPEG
        $result = false;
        $finalFilename = '';

        if (function_exists('imagewebp')) {
            $finalFilename = $filenameBase . '.webp';
            $destinationPath = $destinationDir . '/' . $finalFilename;
            $result = imagewebp($newImage, $destinationPath, $quality);
        }
        
        // Si WebP a échoué ou n'est pas dispo, on utilise JPEG
        if (!$result) {
            $finalFilename = $filenameBase . '.jpg';
            $destinationPath = $destinationDir . '/' . $finalFilename;
            $result = imagejpeg($newImage, $destinationPath, $quality);
        }

        if ($image) imagedestroy($image);
        if ($newImage) imagedestroy($newImage);

        return $result ? $finalFilename : false;
    }
}