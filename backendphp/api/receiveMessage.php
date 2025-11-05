<?php
/**
 * receiveMessage.php
 * ------------------
 * Receives a message from the customer (Python bot → backend).
 * - Finds or creates a contact
 * - Stores message in MySQL (sender_type = 'customer')
 * - Updates last_message and last_seen
 * - Triggers a Pusher event for real-time UI updates
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/Helpers.php';

//  Authentication check
Auth::check();

//  Parse JSON input
$input = json_decode(file_get_contents('php://input'), true);
$number = $input['number'] ?? '';
$message = $input['message'] ?? '';
$timestamp = $input['timestamp'] ?? date('Y-m-d H:i:s');

if (!$number || !$message) {
    Helpers::jsonResponse(['error' => 'Missing parameters'], 400);
}

try {
    $db = Database::getInstance();

    // ✅ Find or create contact
    $stmt = $db->prepare("SELECT id FROM contacts WHERE phone_number = ?");
    $stmt->execute([$number]);
    $contact = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$contact) {
        $stmt = $db->prepare("INSERT INTO contacts (name, phone_number, created_at) VALUES (?, ?, NOW())");
        $stmt->execute([$number, $number]);
        $contactId = $db->lastInsertId();
    } else {
        $contactId = $contact['id'];
    }

    // ✅ Store message in DB
    $stmt = $db->prepare("INSERT INTO messages (contact_id, sender_type, message_text, timestamp) VALUES (?, 'customer', ?, ?)");
    $stmt->execute([$contactId, $message, $timestamp]);
    $messageId = $db->lastInsertId();

    // ✅ Update contact last message & last seen
    $stmt = $db->prepare("UPDATE contacts SET last_message = ?, last_seen = NOW() WHERE id = ?");
    $stmt->execute([$message, $contactId]);

    // ✅ Trigger real-time Pusher event
    Helpers::triggerPusher('chat-channel', 'new-message', [
        'contact_id' => $contactId,
        'sender_type' => 'customer',
        'message_text' => $message,
        'timestamp' => $timestamp,
        'message_id' => $messageId,
        'number' => $number,
        'company_number' => COMPANY_WHATSAPP_NUMBER
    ]);

    // Optionally trigger contacts list update
    Helpers::triggerPusher('chat-channel', 'contacts-updated', [
        'contact_id' => $contactId,
        'last_message' => $message,
        'last_seen' => date('Y-m-d H:i:s')
    ]);

    Helpers::jsonResponse(['success' => true, 'message_id' => $messageId]);
} catch (Exception $e) {
    Helpers::jsonResponse(['error' => $e->getMessage()], 500);
}
