<?php
// src/Auth.php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/Helpers.php';
// Session-based authentication

class Auth {
    public static function check() {
        $headers = getallheaders();
        $authHeader = '';

        if (isset($headers['Authorization'])) {
            $authHeader = $headers['Authorization'];
        } elseif (isset($headers['authorization'])) {
            $authHeader = $headers['authorization'];
        } elseif (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
        }

        // Start or reuse session
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // If user is logged in via session, allow
        if (!empty($_SESSION['user_id'])) {
            return true;
        }

        if (!$authHeader) {
            Helpers::jsonResponse(['error' => 'Unauthorized'], 401);
            exit;
        }

        if (strpos($authHeader, 'Bearer ') === 0) {
            $token = substr($authHeader, 7);
            // API key support (legacy)
            if (hash_equals($token, API_KEY)) {
                return true;
            }
        }

        Helpers::jsonResponse(['error' => 'Unauthorized'], 401);
        exit;
    }

    public static function checkOptional() {
        // Returns true if authorized, false otherwise (doesn't exit)
        $headers = getallheaders();
        $authHeader = '';

        if (isset($headers['Authorization'])) {
            $authHeader = $headers['Authorization'];
        } elseif (isset($headers['authorization'])) {
            $authHeader = $headers['authorization'];
        } elseif (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
        }

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (!empty($_SESSION['user_id'])) {
            return true;
        }
        if (!$authHeader) {
            return false;
        }

        if (strpos($authHeader, 'Bearer ') === 0) {
            $token = substr($authHeader, 7);
            if (hash_equals($token, API_KEY)) {
                return true;
            }
        }

        return false;
    }

    public static function user() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (empty($_SESSION['user_id'])) {
            return null;
        }
        return [
            'id' => (int)$_SESSION['user_id'],
            'email' => $_SESSION['user_email'] ?? null,
            'name' => $_SESSION['user_name'] ?? null
        ];
    }
}