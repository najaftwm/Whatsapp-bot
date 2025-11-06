<?php
// api/getContacts.php
// CORS headers must be set before any output
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowedOrigins = ['http://localhost:5173', 'http://localhost:5174'];
if (in_array($origin, $allowedOrigins, true)) {
	header('Access-Control-Allow-Origin: ' . $origin);
	header('Access-Control-Allow-Credentials: true');
	header('Vary: Origin');
}
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Methods: GET, OPTIONS');

// Handle preflight - must return CORS headers even if origin doesn't match
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
	if (in_array($origin, $allowedOrigins, true)) {
		header('Access-Control-Allow-Origin: ' . $origin);
		header('Access-Control-Allow-Credentials: true');
	}
	header('Content-Length: 0');
	http_response_code(204);
	exit;
}

require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/Helpers.php';

Auth::check();

$db = Database::getInstance()->getConnection();

// Simple listing: include last message (already stored)
$stmt = $db->prepare("SELECT id, name, phone_number, last_message, last_seen, created_at FROM contacts ORDER BY last_seen DESC, id DESC");
$stmt->execute();
$contacts = $stmt->fetchAll();

// Optionally include last message details
Helpers::jsonResponse([
    'ok' => true,
    'contacts' => $contacts
]);