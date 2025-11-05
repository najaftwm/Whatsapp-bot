<?php
// api/login.php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/Helpers.php';

// CORS for Vite dev server
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowedOrigin = 'http://localhost:5173';
if ($origin === $allowedOrigin) {
	header('Access-Control-Allow-Origin: ' . $allowedOrigin);
	header('Access-Control-Allow-Credentials: true');
	header('Vary: Origin');
}
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Methods: POST, OPTIONS');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
	header('Content-Length: 0');
	http_response_code(204);
	exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	Helpers::jsonResponse(['error' => 'Method not allowed'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);
$email = isset($input['email']) ? trim($input['email']) : '';
$password = $input['password'] ?? '';

if (!$email || !$password) {
	Helpers::jsonResponse(['error' => 'email and password are required'], 400);
}

try {
	$db = Database::getInstance()->getConnection();
	$stmt = $db->prepare('SELECT id, name, email, password_hash FROM users WHERE email = :email LIMIT 1');
	$stmt->execute([':email' => $email]);
	$user = $stmt->fetch();
	if (!$user) {
		Helpers::jsonResponse(['error' => 'Invalid credentials'], 401);
	}
	if (!password_verify($password, $user['password_hash'])) {
		Helpers::jsonResponse(['error' => 'Invalid credentials'], 401);
	}

	if (session_status() === PHP_SESSION_NONE) {
		session_start();
	}
	$_SESSION['user_id'] = (int)$user['id'];
	$_SESSION['user_email'] = $user['email'];
	$_SESSION['user_name'] = $user['name'];

	Helpers::jsonResponse([
		'ok' => true,
		'user' => [
			'id' => (int)$user['id'],
			'name' => $user['name'],
			'email' => $user['email']
		]
	]);
} catch (Exception $e) {
	Helpers::jsonResponse(['error' => $e->getMessage()], 500);
}


