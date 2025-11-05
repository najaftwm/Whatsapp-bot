<?php
// api/login.php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/Helpers.php';

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


