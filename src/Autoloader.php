<?php

/**
 * Autoloader PSR-4
 * 
 * Charge automatiquement les classes selon leur namespace
 * Namespaces supportés:
 * - App\Models\*
 * - App\Controllers\*
 * - App\Helpers\*
 */
class Autoloader {
    
    /**
     * Enregistre l'autoloader
     */
    public static function register() {
        spl_autoload_register([self::class, 'autoload']);
    }

    /**
     * Charge une classe automatiquement
     * 
     * @param string $class Nom complet de la classe avec namespace
     */
    public static function autoload($class) {
        // Namespace de base
        $prefix = 'App\\';
        
        // Vérifier si la classe commence par notre namespace
        if (strpos($class, $prefix) !== 0) {
            return;
        }

        // Retirer le prefix
        $relative_class = substr($class, strlen($prefix));

        // Convertir namespace en chemin fichier
        $path = __DIR__ . '/' . str_replace('\\', '/', $relative_class) . '.php';

        // Vérifier et charger le fichier
        if (file_exists($path)) {
            require_once $path;
        }
    }
}
?>
