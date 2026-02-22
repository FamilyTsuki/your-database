<?php

class CsrfToken {
    
    private static $tokenName = 'csrf_token';
    private static $tokenField = 'csrf_token';


    public static function init() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }


    public static function generate() {
        self::init();
        if (empty($_SESSION[self::$tokenName])) {
            $_SESSION[self::$tokenName] = bin2hex(random_bytes(32));
        }
        return $_SESSION[self::$tokenName];
    }

    public static function get() {
        self::init();
        return self::generate();
    }


    public static function verify($token) {
        self::init();
        if (empty($_SESSION[self::$tokenName])) {
            return false;
        }
        return hash_equals($_SESSION[self::$tokenName], $token ?? '');
    }


    public static function verifyFromPost() {
        return self::verify($_POST[self::$tokenField] ?? '');
    }


    public static function field() {
        $token = self::get();
        return '<input type="hidden" name="' . self::$tokenField . '" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
    }
}
?>
