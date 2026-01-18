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
        $bgColor = $type === 'success' ? '#d4edda' : ($type === 'error' ? '#f8d7da' : '#d1ecf1');
        $textColor = $type === 'success' ? '#155724' : ($type === 'error' ? '#721c24' : '#0c5460');
        $borderColor = $type === 'success' ? '#c3e6cb' : ($type === 'error' ? '#f5c6cb' : '#bee5eb');

        return <<<HTML
<div class="flash-message flash-{$type}" style="
    background-color: {$bgColor};
    color: {$textColor};
    border: 1px solid {$borderColor};
    padding: 12px 16px;
    border-radius: 8px;
    margin-bottom: 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
">
    <span>{$message}</span>
    <button onclick="this.parentElement.style.display='none';" style="background: none; border: none; cursor: pointer; font-size: 18px;">✕</button>
</div>
HTML;
    }
}
?>
