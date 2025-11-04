<?php
// public/index.php
// Basic router: maps /api/<endpoint> to ../api/<endpoint>.php
$requestUri = $_SERVER['REQUEST_URI'];
$scriptName = $_SERVER['SCRIPT_NAME'];
// Calculate base path of this public folder
$basePath = str_replace('\\', '/', dirname($scriptName));
$path = preg_replace('#^' . preg_quote($basePath) . '#', '', $requestUri);
$path = strtok($path, '?'); // remove query string
$path = trim($path, '/');

if (empty($path)) {
    header('Content-Type: text/plain');
    echo "WhatsApp Backend API - available endpoints:\n";
    echo "/api/sendMessage (POST)\n";
    echo "/api/receiveMessage (POST)\n";
    echo "/api/getMessages (GET)\n";
    echo "/api/getContacts (GET)\n";
    exit;
}

// Only allow api/* requests
$parts = explode('/', $path);
if ($parts[0] !== 'api') {
    http_response_code(404);
    echo json_encode(['error' => 'Not Found']);
    exit;
}

$endpoint = $parts[1] ?? '';
$file = realpath(__DIR__ . '/../api/' . $endpoint . '.php');

if ($file && strpos($file, realpath(__DIR__ . '/../api/')) === 0 && file_exists($file)) {
    require_once $file;
    exit;
} else {
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'API endpoint not found']);
    exit;
}
