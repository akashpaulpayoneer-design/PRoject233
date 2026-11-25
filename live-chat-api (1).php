<?php
require_once __DIR__ . '/_common.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Content-Type-Options: nosniff');

function ts_admin_chat_json(int $status, array $payload): void {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

try {
    $pdo = db();
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $action = (string)($_GET['action'] ?? 'list');
        if ($action === 'list') {
            $stmt = $pdo->query("SELECT c.id, c.visitor_name, c.visitor_phone, c.visitor_email, c.status,
                DATE_FORMAT(c.last_message_at, '%Y-%m-%d %H:%i:%s') AS last_message_at,
                (SELECT COUNT(*) FROM live_chat_messages m WHERE m.conversation_id = c.id AND m.sender = 'visitor' AND m.is_read_by_admin = 0) AS unread_count,
                (SELECT message FROM live_chat_messages m2 WHERE m2.conversation_id = c.id ORDER BY m2.id DESC LIMIT 1) AS last_message
                FROM live_chat_conversations c ORDER BY c.last_message_at DESC, c.id DESC LIMIT 100");
            ts_admin_chat_json(200, ['ok' => true, 'conversations' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        }
        if ($action === 'messages') {
            $conversationId = (int)($_GET['conversation_id'] ?? 0);
            $afterId = max(0, (int)($_GET['after_id'] ?? 0));
            if ($conversationId < 1) ts_admin_chat_json(422, ['ok' => false, 'message' => 'Choose a conversation.']);
            $exists = $pdo->prepare('SELECT id FROM live_chat_conversations WHERE id = ? LIMIT 1');
            $exists->execute([$conversationId]);
            if (!$exists->fetchColumn()) ts_admin_chat_json(404, ['ok' => false, 'message' => 'Conversation not found.']);
            if ($afterId > 0) {
                $stmt = $pdo->prepare("SELECT id, sender, message, DATE_FORMAT(created_at, '%Y-%m-%d %H:%i:%s') AS created_at FROM live_chat_messages WHERE conversation_id = ? AND id > ? ORDER BY id ASC LIMIT 100");
                $stmt->execute([$conversationId, $afterId]);
                $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } else {
                $stmt = $pdo->prepare("SELECT * FROM (SELECT id, sender, message, DATE_FORMAT(created_at, '%Y-%m-%d %H:%i:%s') AS created_at FROM live_chat_messages WHERE conversation_id = ? ORDER BY id DESC LIMIT 100) AS recent_messages ORDER BY id ASC");
                $stmt->execute([$conversationId]);
                $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            $mark = $pdo->prepare("UPDATE live_chat_messages SET is_read_by_admin = 1 WHERE conversation_id = ? AND sender = 'visitor'");
            $mark->execute([$conversationId]);
            ts_admin_chat_json(200, ['ok' => true, 'messages' => $messages]);
        }
        ts_admin_chat_json(400, ['ok' => false, 'message' => 'Unknown action.']);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Allow: GET, POST');
        ts_admin_chat_json(405, ['ok' => false, 'message' => 'Method not allowed.']);
    }
    $csrf = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if ($csrf === '' || !hash_equals((string)($_SESSION['csrf'] ?? ''), $csrf)) {
        ts_admin_chat_json(400, ['ok' => false, 'message' => 'Security token expired. Refresh the Admin page and try again.']);
    }
    $data = json_decode((string)file_get_contents('php://input'), true);
    if (!is_array($data)) ts_admin_chat_json(400, ['ok' => false, 'message' => 'Invalid request data.']);
    $action = (string)($data['action'] ?? '');
    $conversationId = (int)($data['conversation_id'] ?? 0);
    if ($conversationId < 1) ts_admin_chat_json(422, ['ok' => false, 'message' => 'Choose a conversation.']);

    $exists = $pdo->prepare('SELECT id FROM live_chat_conversations WHERE id = ? LIMIT 1');
    $exists->execute([$conversationId]);
    if (!$exists->fetchColumn()) ts_admin_chat_json(404, ['ok' => false, 'message' => 'Conversation not found.']);

    if ($action === 'reply') {
        $message = trim((string)($data['message'] ?? ''));
        $length = function_exists('mb_strlen') ? mb_strlen($message, 'UTF-8') : strlen($message);
        if ($length < 1 || $length > 2000) ts_admin_chat_json(422, ['ok' => false, 'message' => 'Reply must be 1–2000 characters.']);
        $stmt = $pdo->prepare("INSERT INTO live_chat_messages (conversation_id, sender, message, is_read_by_admin, is_read_by_visitor, created_at) VALUES (?, 'admin', ?, 1, 0, NOW())");
        $stmt->execute([$conversationId, $message]);
        $messageId = (int)$pdo->lastInsertId();
        $update = $pdo->prepare("UPDATE live_chat_conversations SET status = 'open', updated_at = NOW(), last_message_at = NOW() WHERE id = ?");
        $update->execute([$conversationId]);
        ts_admin_chat_json(200, ['ok' => true, 'message_id' => $messageId]);
    }

    if ($action === 'status') {
        $status = (string)($data['status'] ?? '');
        if (!in_array($status, ['open', 'closed'], true)) ts_admin_chat_json(422, ['ok' => false, 'message' => 'Invalid conversation status.']);
        $stmt = $pdo->prepare('UPDATE live_chat_conversations SET status = ?, updated_at = NOW() WHERE id = ?');
        $stmt->execute([$status, $conversationId]);
        ts_admin_chat_json(200, ['ok' => true, 'status' => $status]);
    }

    ts_admin_chat_json(400, ['ok' => false, 'message' => 'Unknown action.']);
} catch (Throwable $e) {
    error_log('TOP STUDIO admin live chat API error: ' . $e->getMessage());
    ts_admin_chat_json(500, ['ok' => false, 'message' => 'Live Chat could not load. Check the server error log.']);
}
