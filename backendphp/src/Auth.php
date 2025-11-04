<?php
// src/Auth.php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/Helpers.php';

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

        if (!$authHeader) {
            Helpers::jsonResponse(['error' => 'Missing Authorization header'], 401);
            exit;
        }

        if (strpos($authHeader, 'Bearer ') === 0) {
            $token = substr($authHeader, 7);
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
}
