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
    $contactId = null;

    // Validate phone number format (must contain digits, optionally with +)
    if (!preg_match('/^\+?[0-9]{10,15}$/', $phone)) {
        // If phone looks like a name, try to find existing contact by name first
        $stmt = $db->prepare("SELECT id, phone_number FROM contacts WHERE name = ? OR phone_number = ? LIMIT 1");
        $stmt->execute([$phone, $phone]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($existing && preg_match('/^\+?[0-9]{10,15}$/', $existing['phone_number'])) {
            // Use the existing contact's phone number
            $phone = $existing['phone_number'];
            $contactId = $existing['id'];
        } else {
            Helpers::jsonResponse(['error' => 'Invalid phone number format. Expected format: +919876543210'], 400);
        }
    }

    // Find or create contact by phone number (if not already found)
    if (!$contactId) {
        $stmt = $db->prepare("SELECT id, name FROM contacts WHERE phone_number = ?");
        $stmt->execute([$phone]);
        $contact = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($contact) {
            $contactId = $contact['id'];
        } else {
            // Create new contact with phone number as name initially
            $stmt = $db->prepare("INSERT INTO contacts (name, phone_number) VALUES (?, ?)");
            $stmt->execute([$phone, $phone]);
            $contactId = $db->lastInsertId();
        }
    }

    // Deduplicate: skip if identical customer message for this contact seen recently
    $dupCheck = $db->prepare("SELECT id FROM messages WHERE contact_id = ? AND sender_type = 'customer' AND message_text = ? AND timestamp >= (NOW() - INTERVAL 5 MINUTE) ORDER BY id DESC LIMIT 1");
    $dupCheck->execute([$contactId, $message]);
    $existingMsg = $dupCheck->fetch(PDO::FETCH_ASSOC);

    if ($existingMsg) {
        $messageId = (int)$existingMsg['id'];
    } else {
        // Save received message
        $stmt = $db->prepare("INSERT INTO messages (contact_id, sender_type, message_text, timestamp) VALUES (?, 'customer', ?, NOW())");
        $stmt->execute([$contactId, $message]);
        $messageId = $db->lastInsertId();
    }
    $messageId = $db->lastInsertId();

    // Auto reply
    $autoReply = "Hello, We will reach out to you within 12 hours.";

    $payload = json_encode([
        'phone_number' => $phone,
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
        Helpers::triggerPusher('chat-channel', 'new-message', [
            'id' => $messageId,
            'contact_id' => $contactId,
            'sender_type' => 'customer',
            'message' => $message,
            'message_text' => $message,
            'timestamp' => date('Y-m-d H:i:s'),
        ]);
    }

    Helpers::jsonResponse(['ok' => true, 'bot_response' => $botResponse]);
} catch (Exception $e) {
    Helpers::jsonResponse(['error' => $e->getMessage()], 500);
}
?>