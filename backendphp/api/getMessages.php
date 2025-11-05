<?php
// api/getMessages.php
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/Helpers.php';

Auth::check();

$contact_id = isset($_GET['contact_id']) ? intval($_GET['contact_id']) : null;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = isset($_GET['per_page']) ? max(1, min(200, intval($_GET['per_page']))) : 50;

if (!$contact_id) {
    Helpers::jsonResponse(['error' => 'contact_id is required'], 400);
    exit;
}

$db = Database::getInstance()->getConnection();

// check contact exists
$stmt = $db->prepare("SELECT id, phone_number FROM contacts WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $contact_id]);
$contact = $stmt->fetch();
if (!$contact) {
    Helpers::jsonResponse(['error' => 'Contact not found'], 404);
    exit;
}

$offset = ($page - 1) * $per_page;

// fetch total count
$stmt = $db->prepare("SELECT COUNT(*) as cnt FROM messages WHERE contact_id = :cid");
$stmt->execute([':cid' => $contact_id]);
$total = $stmt->fetchColumn();

// fetch messages ordered asc
$stmt = $db->prepare("SELECT id, contact_id, sender_type, message_text, metadata, timestamp FROM messages WHERE contact_id = :cid ORDER BY timestamp ASC, id ASC LIMIT :limit OFFSET :offset");
$stmt->bindValue(':cid', $contact_id, PDO::PARAM_INT);
$stmt->bindValue(':limit', (int)$per_page, PDO::PARAM_INT);
$stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
$stmt->execute();
$messages = $stmt->fetchAll();

// decode metadata
foreach ($messages as &$m) {
    $m['metadata'] = $m['metadata'] ? json_decode($m['metadata'], true) : null;
}

Helpers::jsonResponse([
    'ok' => true,
    'contact' => $contact,
    'pagination' => [
        'page' => $page,
        'per_page' => $per_page,
        'total' => (int)$total,
        'pages' => ceil($total / $per_page)
    ],
    'messages' => $messages
]);