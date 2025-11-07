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
        $messageId = (int)$db->lastInsertId();
    }

    // Auto reply (contact-level cooldown)
    $autoReply = "Hello, We will reach out to you within 12 hours.";
    $cooldownMinutes = 0.5;
    $autoReplyMessageId = null;
    $autoReplyDispatched = false;
    $botResponse = null;
    $lockName = 'auto_reply_' . $contactId;
    $lockAcquired = false;

    try {
        // Acquire lightweight contact-level lock to avoid double sends under concurrency
        $lockStmt = $db->prepare("SELECT GET_LOCK(?, 5)");
        $lockStmt->execute([$lockName]);
        $lockResult = $lockStmt->fetch(PDO::FETCH_NUM);
        $lockAcquired = $lockResult && isset($lockResult[0]) && (int)$lockResult[0] === 1;

        $lastAutoStmt = $db->prepare("SELECT id, timestamp, TIMESTAMPDIFF(SECOND, timestamp, NOW()) AS seconds_since FROM messages WHERE contact_id = ? AND sender_type = 'company' AND message_text = ? ORDER BY timestamp DESC LIMIT 1");
        $lastAutoStmt->execute([$contactId, $autoReply]);
        $lastAuto = $lastAutoStmt->fetch(PDO::FETCH_ASSOC);

        $withinCooldown = false;
        if ($lastAuto) {
            $secondsSince = is_numeric($lastAuto['seconds_since']) ? (int)$lastAuto['seconds_since'] : null;
            $withinCooldown = $secondsSince !== null && $secondsSince < ($cooldownMinutes * 60);
            if ($withinCooldown) {
                $autoReplyMessageId = (int)$lastAuto['id'];
            }
        }

        if (!$withinCooldown) {
            // Use insert-if-not-exists pattern to prevent duplicate rows
            $insertStmt = $db->prepare(
                "INSERT INTO messages (contact_id, sender_type, message_text, timestamp)
                 SELECT ?, 'company', ?, NOW()
                 FROM DUAL
                 WHERE NOT EXISTS (
                     SELECT 1 FROM messages
                     WHERE contact_id = ?
                       AND sender_type = 'company'
                       AND message_text = ?
                       AND timestamp >= (NOW() - INTERVAL 60 SECOND)
                 )"
            );
            $insertStmt->execute([$contactId, $autoReply, $contactId, $autoReply]);
            $autoReplyMessageId = (int)$db->lastInsertId();

            if ($autoReplyMessageId) {
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

                $autoReplyDispatched = true;
            } else {
                // Row already exists; fetch latest ID for use in frontend updates
                $existingStmt = $db->prepare("SELECT id FROM messages WHERE contact_id = ? AND sender_type = 'company' AND message_text = ? ORDER BY id DESC LIMIT 1");
                $existingStmt->execute([$contactId, $autoReply]);
                $existingRow = $existingStmt->fetch(PDO::FETCH_ASSOC);
                if ($existingRow) {
                    $autoReplyMessageId = (int)$existingRow['id'];
                }
            }
        }
    } finally {
        if ($lockAcquired) {
            $releaseStmt = $db->prepare("SELECT RELEASE_LOCK(?)");
            $releaseStmt->execute([$lockName]);
        }
    }

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

    if ($autoReplyDispatched && $autoReplyMessageId && ENABLE_PUSHER) {
        Helpers::triggerPusher('chat-channel', 'new-message', [
            'id' => $autoReplyMessageId,
            'contact_id' => $contactId,
            'sender_type' => 'company',
            'message' => $autoReply,
            'message_text' => $autoReply,
            'timestamp' => date('Y-m-d H:i:s'),
        ]);
    }

    Helpers::jsonResponse([
        'ok' => true,
        'bot_response' => $botResponse,
        'auto_reply_sent' => $autoReplyDispatched,
        'cooldown_minutes' => $cooldownMinutes,
    ]);
} catch (Exception $e) {
    Helpers::jsonResponse(['error' => $e->getMessage()], 500);
}
?>