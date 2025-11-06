<?php
// api/sendMessage.php
// CORS headers must be set before any output
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowedOrigins = ['http://localhost:5173', 'http://localhost:5174'];
if (in_array($origin, $allowedOrigins, true)) {
	header('Access-Control-Allow-Origin: ' . $origin);
	header('Access-Control-Allow-Credentials: true');
	header('Vary: Origin');
}
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Methods: POST, OPTIONS');

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

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/Helpers.php';
require_once __DIR__ . '/../src/Auth.php';

Auth::check();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Helpers::jsonResponse(['error' => 'Method not allowed'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);
$contactId = (int)($input['contact_id'] ?? 0);
$message = trim($input['message'] ?? '');

if (!$contactId || !$message) {
    Helpers::jsonResponse(['error' => 'Missing contact_id or message'], 400);
}

try {
    $db = Database::getInstance()->getConnection();
    // Get contact phone number
    $stmt = $db->prepare("SELECT phone_number FROM contacts WHERE id = ?");
    $stmt->execute([$contactId]);
    $contact = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$contact) Helpers::jsonResponse(['error' => 'Contact not found'], 404);

    $phone = $contact['phone_number'];

    // Save to DB - use sender_type and message_text to match schema
    $stmt = $db->prepare("INSERT INTO messages (contact_id, sender_type, message_text, timestamp) VALUES (?, 'company', ?, NOW())");
    $stmt->execute([$contactId, $message]);

    // Notify Python bot
    $payload = json_encode([
        'phone_number' => $phone,
        'message' => $message,
    ]);
    $ch = curl_init(BOT_URL . '/send_message');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'x-api-key: ' . API_KEY],
        CURLOPT_RETURNTRANSFER => true,
    ]);
    $response = curl_exec($ch);
    curl_close($ch);

    // Push to frontend
    if (ENABLE_PUSHER) {
        require_once __DIR__ . '/../src/pusherInstance.php';
        $pusher->trigger('chat-channel', 'new-message', [
            'contact_id' => $contactId,
            'id' => $db->lastInsertId(),
            'sender_type' => 'company',
            'message_text' => $message,
            'message' => $message, // Also include for backward compatibility
            'timestamp' => date('Y-m-d H:i:s'),
        ]);
    }

    Helpers::jsonResponse(['ok' => true, 'bot_response' => $response]);
} catch (Exception $e) {
    Helpers::jsonResponse(['error' => $e->getMessage()], 500);
}
?>