<?php
/**
 * sendMessage.php
 * ----------------
 * Sends a message from the company WhatsApp number to a customer.
 * - Stores the message in MySQL (sender_type = 'company')
 * - Forwards it to the Python bot
 * - Triggers a real-time Pusher event
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/Helpers.php';

// ✅ Authentication check
Auth::check();

// ✅ Parse JSON input
$input = json_decode(file_get_contents('php://input'), true);
$number = $input['number'] ?? '';
$message = $input['message'] ?? '';
$contactId = $input['contact_id'] ?? 0;

if (!$number || !$message) {
    Helpers::jsonResponse(['error' => 'Missing parameters'], 400);
}

try {
    $db = Database::getInstance();

    // ✅ Store message in DB
    $stmt = $db->prepare("INSERT INTO messages (contact_id, sender_type, message_text, timestamp) VALUES (?, 'company', ?, NOW())");
    $stmt->execute([$contactId, $message]);
    $messageId = $db->lastInsertId();

    // ✅ Forward message to Python bot
    $botPayload = [
        'company_number' => COMPANY_WHATSAPP_NUMBER,
        'recipient_number' => $number,
        'message' => $message
    ];

    $ch = curl_init(BOT_URL . '/send');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($botPayload),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json']
    ]);
    $botResponse = curl_exec($ch);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        error_log("Bot send error: " . $curlError);
    }

    // ✅ Trigger Pusher real-time update
    Helpers::triggerPusher('chat-channel', 'new-message', [
        'contact_id' => $contactId,
        'sender_type' => 'company',
        'message_text' => $message,
        'timestamp' => date('Y-m-d H:i:s'),
        'message_id' => $messageId,
        'number' => $number,
        'company_number' => COMPANY_WHATSAPP_NUMBER
    ]);

    Helpers::jsonResponse([
        'success' => true,
        'message_id' => $messageId,
        'bot_response' => $botResponse ? json_decode($botResponse, true) : null
    ]);
} catch (Exception $e) {
    Helpers::jsonResponse(['error' => $e->getMessage()], 500);
}