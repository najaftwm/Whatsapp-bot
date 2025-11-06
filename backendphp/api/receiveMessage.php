<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/Helpers.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Helpers::jsonResponse(['error' => 'Method not allowed'], 405);
}

// API key validation
$headers = getallheaders();
if (($headers['x-api-key'] ?? '') !== API_KEY) {
    Helpers::jsonResponse(['error' => 'Invalid API key'], 403);
}

$input = json_decode(file_get_contents('php://input'), true);
$phone = trim($input['phone'] ?? '');
$message = trim($input['message'] ?? '');

if (!$phone || !$message) {
    Helpers::jsonResponse(['error' => 'Missing phone or message'], 400);
}

try {
    $db = Database::getInstance()->getConnection();

    // Find or create contact
    $stmt = $db->prepare("SELECT id FROM contacts WHERE phone_number = ?");
    $stmt->execute([$phone]);
    $contact = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($contact) {
        $contactId = $contact['id'];
    } else {
        $stmt = $db->prepare("INSERT INTO contacts (name, phone_number) VALUES (?, ?)");
        $stmt->execute([$phone, $phone]);
        $contactId = $db->lastInsertId();
    }

    // Save received message
    $stmt = $db->prepare("INSERT INTO messages (contact_id, sender, message, created_at) VALUES (?, 'client', ?, NOW())");
    $stmt->execute([$contactId, $message]);

    // Auto reply
    $autoReply = "Hello, We will reach out to you within 12 hours.";

    $payload = json_encode([
        'phone' => $phone,
        'message' => $autoReply,
    ]);
    $ch = curl_init(BOT_URL . '/send_message');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'x-api-key: ' . API_KEY],
        CURLOPT_RETURNTRANSFER => true,
    ]);
    $botResponse = curl_exec($ch);
    curl_close($ch);

    // Push to frontend
    if (ENABLE_PUSHER) {
        require_once __DIR__ . '/../src/pusherInstance.php';
        $pusher->trigger('chat-channel', 'new-message', [
            'contact_id' => $contactId,
            'sender' => 'client',
            'message' => $message,
        ]);
    }

    Helpers::jsonResponse(['ok' => true, 'bot_response' => $botResponse]);
} catch (Exception $e) {
    Helpers::jsonResponse(['error' => $e->getMessage()], 500);
}
?>