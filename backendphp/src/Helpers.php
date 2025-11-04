<?php
require_once __DIR__ . '/../vendor/autoload.php';
use Pusher\Pusher;

class Helpers {
    public static function triggerPusher($channel, $event, $data) {
        if (!defined('ENABLE_PUSHER') || !ENABLE_PUSHER) return;

        try {
            $pusher = new Pusher(
                PUSHER_KEY,
                PUSHER_SECRET,
                PUSHER_APP_ID,
                ['cluster' => PUSHER_CLUSTER, 'useTLS' => true]
            );
            $pusher->trigger($channel, $event, $data);
        } catch (Exception $e) {
            error_log("Pusher error: " . $e->getMessage());
        }
    }

    public static function jsonResponse($data, $statusCode = 200) {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
}
