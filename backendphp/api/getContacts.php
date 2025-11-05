<?php
// api/getContacts.php
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