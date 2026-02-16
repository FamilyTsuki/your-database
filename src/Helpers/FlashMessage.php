<?php

class FlashMessage {
    
    /**
     * Initialise les sessions si nécessaire
     */
    public static function init() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    /**
     * Ajoute un message flash (succès)
     */
    public static function success($message) {
        self::init();
        $_SESSION['flash'] = [
            'type' => 'success',
            'message' => $message
        ];
    }

    /**
     * Ajoute un message flash (erreur)
     */
    public static function error($message) {
        self::init();
        $_SESSION['flash'] = [
            'type' => 'error',
            'message' => $message
        ];
    }

    /**
     * Ajoute un message flash (info)
     */
    public static function info($message) {
        self::init();
        $_SESSION['flash'] = [
            'type' => 'info',
            'message' => $message
        ];
    }

    /**
     * Récupère et supprime le message flash
     */
    public static function get() {
        self::init();
        if (isset($_SESSION['flash'])) {
            $flash = $_SESSION['flash'];
            unset($_SESSION['flash']);
            return $flash;
        }
        return null;
    }

    /**
     * Affiche le HTML du message flash
     */
    public static function render() {
        $flash = self::get();
        if ($flash === null) {
            return '';
        }

        $type = htmlspecialchars($flash['type']);
        $message = htmlspecialchars($flash['message']);
        $id = 'flash-' . uniqid();

        return <<<HTML
    <div id="{$id}" class="flash-message flash-{$type}">
        <span>{$message}</span>
        <button onclick="document.getElementById('{$id}').remove()" aria-label="Fermer" style="position: absolute; top: 50%; right: 10px; transform: translateY(-50%); background: none; border: none; cursor: pointer; font-size: 20px; color: inherit;">&times;</button>
    </div>
    <script>
        setTimeout(function() {
            var f = document.getElementById('{$id}');
            if (f) {
                f.classList.add('fade-out');
                f.addEventListener('animationend', function() { f.remove(); });
            }
        }, 3000);
    </script>
    HTML;
    }
}
?>
