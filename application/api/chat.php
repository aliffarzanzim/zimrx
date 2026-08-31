<?php
/**
 * ZimRx Clinical Internal Messaging API
 * High-performance, scalable communication system supporting direct messages,
 * group channels, attachments, delivery/seen receipts, and customizable quick messages.
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';
require_login();
require_once __DIR__ . '/../db.php';

header('Content-Type: application/json');

$pdo = DbConnections::userdata();
$currentUserId = current_user_id();
$currentUserName = current_user_name();
$currentUserRole = current_user_role();

$cacheDir = ZIMRX_USERDATA_DIR . '/cache';
if (!is_dir($cacheDir)) {
    @mkdir($cacheDir, 0777, true);
}
$tickFile = $cacheDir . '/chat_tick.json';

function touch_chat_tick(string $tickFile, int $lastMsgId): void {
    $data = [
        'tick' => microtime(true),
        'last_id' => $lastMsgId,
        'timestamp' => time()
    ];
    @file_put_contents($tickFile, json_encode($data), LOCK_EX);
}

function get_chat_tick(string $tickFile): array {
    if (file_exists($tickFile)) {
        $content = @file_get_contents($tickFile);
        $data = json_decode($content, true);
        if (is_array($data) && isset($data['tick'])) {
            return $data;
        }
    }
    return ['tick' => 0, 'last_id' => 0, 'timestamp' => 0];
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    // -------------------------------------------------------------------------
    // 1. List Conversations for Current User
    // -------------------------------------------------------------------------
    if ($action === 'list_conversations') {
        $sql = "
            SELECT 
                c.id, c.type, c.title, c.description, c.last_message_id, 
                c.last_message_preview, c.last_message_sender_name, c.last_message_at,
                p.role AS participant_role, p.last_read_message_id, p.last_delivered_message_id,
                p.is_pinned, p.is_muted,
                (
                    SELECT COUNT(*) 
                    FROM zimrx_chat_messages m 
                    WHERE m.conversation_id = c.id 
                      AND m.id > p.last_read_message_id
                      AND m.is_deleted = 0
                ) AS unread_count
            FROM zimrx_chat_conversations c
            INNER JOIN zimrx_chat_participants p 
                ON c.id = p.conversation_id AND p.user_id = :user_id
            WHERE c.is_archived = 0
            ORDER BY p.is_pinned DESC, COALESCE(c.last_message_at, c.created_at) DESC
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([':user_id' => $currentUserId]);
        $conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // For direct chats, resolve the other participant's display name, role, and online/seen state
        foreach ($conversations as &$conv) {
            $conv['id'] = (int)$conv['id'];
            $conv['unread_count'] = (int)$conv['unread_count'];
            $conv['is_pinned'] = (int)$conv['is_pinned'];
            $conv['is_muted'] = (int)$conv['is_muted'];

            if ($conv['type'] === 'direct') {
                $otherStmt = $pdo->prepare("
                    SELECT u.id, u.display_name, u.role, cp.last_read_message_id, cp.last_delivered_message_id, cp.last_active_at
                    FROM zimrx_chat_participants cp
                    INNER JOIN zimrx_user_accounts u ON cp.user_id = u.id
                    WHERE cp.conversation_id = :conv_id AND cp.user_id != :my_id
                    LIMIT 1
                ");
                $otherStmt->execute([':conv_id' => $conv['id'], ':my_id' => $currentUserId]);
                $otherUser = $otherStmt->fetch(PDO::FETCH_ASSOC);
                if ($otherUser) {
                    $conv['title'] = $otherUser['display_name'] ?: 'User #' . $otherUser['id'];
                    $conv['other_user'] = [
                        'id' => (int)$otherUser['id'],
                        'display_name' => $otherUser['display_name'],
                        'role' => $otherUser['role'],
                        'last_read_id' => (int)$otherUser['last_read_message_id'],
                        'last_delivered_id' => (int)$otherUser['last_delivered_message_id']
                    ];
                } else {
                    $conv['title'] = 'Direct Chat';
                }
            }
        }
        unset($conv);

        // Fetch list of available colleagues to start new chats
        $usersStmt = $pdo->prepare("
            SELECT id, display_name, username, role 
            FROM zimrx_user_accounts 
            WHERE is_active = 1 AND id != :my_id
            ORDER BY role ASC, display_name ASC
        ");
        $usersStmt->execute([':my_id' => $currentUserId]);
        $availableUsers = $usersStmt->fetchAll(PDO::FETCH_ASSOC);

        // Total unread count across all conversations
        $totalUnread = 0;
        foreach ($conversations as $c) {
            $totalUnread += $c['unread_count'];
        }

        $tickInfo = get_chat_tick($tickFile);

        echo json_encode([
            'ok' => true,
            'conversations' => $conversations,
            'available_users' => $availableUsers,
            'total_unread' => $totalUnread,
            'tick' => $tickInfo['tick']
        ], JSON_UNESCAPED_UNICODE);
        exit();
    }

    // -------------------------------------------------------------------------
    // 2. Get Messages in a Conversation
    // -------------------------------------------------------------------------
    if ($action === 'get_messages') {
        $convId = (int)($_GET['conversation_id'] ?? 0);
        $afterId = (int)($_GET['after_id'] ?? 0);
        $limit = max(1, min(100, (int)($_GET['limit'] ?? 50)));

        if ($convId <= 0) {
            throw new RuntimeException('Invalid conversation ID.');
        }

        // Verify participant and get other participant details for status checks
        $checkStmt = $pdo->prepare("
            SELECT p.last_read_message_id, p.last_delivered_message_id, c.type
            FROM zimrx_chat_participants p
            INNER JOIN zimrx_chat_conversations c ON p.conversation_id = c.id
            WHERE p.conversation_id = :conv_id AND p.user_id = :user_id
        ");
        $checkStmt->execute([':conv_id' => $convId, ':user_id' => $currentUserId]);
        $participant = $checkStmt->fetch(PDO::FETCH_ASSOC);
        if (!$participant) {
            throw new RuntimeException('Access denied. You are not a participant in this conversation.');
        }

        $isDirect = ($participant['type'] === 'direct');
        $otherReadId = 0;
        $otherDeliveredId = 0;
        if ($isDirect) {
            $otherInfoStmt = $pdo->prepare("
                SELECT last_read_message_id, last_delivered_message_id 
                FROM zimrx_chat_participants 
                WHERE conversation_id = :conv_id AND user_id != :my_id 
                LIMIT 1
            ");
            $otherInfoStmt->execute([':conv_id' => $convId, ':my_id' => $currentUserId]);
            $otherRow = $otherInfoStmt->fetch(PDO::FETCH_ASSOC);
            if ($otherRow) {
                $otherReadId = (int)$otherRow['last_read_message_id'];
                $otherDeliveredId = (int)$otherRow['last_delivered_message_id'];
            }
        }

        if ($afterId > 0) {
            $msgStmt = $pdo->prepare("
                SELECT id, conversation_id, sender_id, sender_name, sender_role, message_type, message, 
                       metadata_json, file_path, file_name, file_type, file_size, is_deleted, deleted_by, created_at
                FROM zimrx_chat_messages
                WHERE conversation_id = :conv_id AND id > :after_id AND is_hidden = 0
                ORDER BY id ASC
                LIMIT :limit
            ");
            $msgStmt->bindValue(':conv_id', $convId, PDO::PARAM_INT);
            $msgStmt->bindValue(':after_id', $afterId, PDO::PARAM_INT);
            $msgStmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        } else {
            $msgStmt = $pdo->prepare("
                SELECT * FROM (
                    SELECT id, conversation_id, sender_id, sender_name, sender_role, message_type, message, 
                           metadata_json, file_path, file_name, file_type, file_size, is_deleted, deleted_by, created_at
                    FROM zimrx_chat_messages
                    WHERE conversation_id = :conv_id AND is_hidden = 0
                    ORDER BY id DESC
                    LIMIT :limit
                ) sub
                ORDER BY id ASC
            ");
            $msgStmt->bindValue(':conv_id', $convId, PDO::PARAM_INT);
            $msgStmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        }

        $msgStmt->execute();
        $messages = $msgStmt->fetchAll(PDO::FETCH_ASSOC);

        $maxId = (int)$participant['last_read_message_id'];
        foreach ($messages as &$m) {
            $m['id'] = (int)$m['id'];
            $m['sender_id'] = (int)$m['sender_id'];
            $m['is_mine'] = ($m['sender_id'] === $currentUserId);
            $m['is_deleted'] = (int)$m['is_deleted'];

            if ($m['id'] > $maxId) {
                $maxId = $m['id'];
            }

            // Calculate status for messages sent by me
            if ($m['is_mine']) {
                if ($isDirect) {
                    if ($otherReadId >= $m['id']) {
                        $m['status'] = 'seen';
                    } elseif ($otherDeliveredId >= $m['id']) {
                        $m['status'] = 'delivered';
                    } else {
                        $m['status'] = 'sent';
                    }
                } else {
                    $m['status'] = 'delivered';
                }
            } else {
                $m['status'] = 'seen';
            }

            if (!empty($m['metadata_json'])) {
                $m['metadata'] = json_decode($m['metadata_json'], true);
            } else {
                $m['metadata'] = null;
            }

            if ($m['is_deleted'] === 1) {
                $m['message'] = 'This message was deleted';
                $m['file_path'] = null;
                $m['file_name'] = null;
            }
        }
        unset($m);

        // Mark read & delivered
        if ($maxId > (int)$participant['last_read_message_id']) {
            $upd = $pdo->prepare("
                UPDATE zimrx_chat_participants 
                SET last_read_message_id = :max_id,
                    last_delivered_message_id = MAX(COALESCE(last_delivered_message_id, 0), :max_id),
                    last_active_at = CURRENT_TIMESTAMP
                WHERE conversation_id = :conv_id AND user_id = :user_id
            ");
            $upd->execute([':max_id' => $maxId, ':conv_id' => $convId, ':user_id' => $currentUserId]);
            touch_chat_tick($tickFile, $maxId);
        }

        echo json_encode([
            'ok' => true,
            'conversation_id' => $convId,
            'messages' => $messages,
            'last_read_id' => $maxId
        ], JSON_UNESCAPED_UNICODE);
        exit();
    }

    // -------------------------------------------------------------------------
    // 3. Send Message (Supports Text, Patient Cards, and Attachments)
    // -------------------------------------------------------------------------
    if ($action === 'send_message') {
        $convId = (int)($_POST['conversation_id'] ?? 0);
        $message = trim($_POST['message'] ?? '');
        $messageType = trim($_POST['message_type'] ?? 'text');
        $rawMetadata = trim($_POST['metadata'] ?? '');

        if ($convId <= 0) {
            throw new RuntimeException('Invalid conversation ID.');
        }

        // Verify participant
        $checkStmt = $pdo->prepare("
            SELECT c.type FROM zimrx_chat_participants p
            INNER JOIN zimrx_chat_conversations c ON p.conversation_id = c.id
            WHERE p.conversation_id = :conv_id AND p.user_id = :user_id
        ");
        $checkStmt->execute([':conv_id' => $convId, ':user_id' => $currentUserId]);
        $convInfo = $checkStmt->fetch(PDO::FETCH_ASSOC);
        if (!$convInfo) {
            throw new RuntimeException('You are not a participant in this conversation.');
        }

        // Handle File Attachment if present
        $filePath = null;
        $fileName = null;
        $fileType = null;
        $fileSize = 0;

        if (!empty($_FILES['attachment']) && is_array($_FILES['attachment'])) {
            $file = $_FILES['attachment'];
            if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                $tmpPath = (string)($file['tmp_name'] ?? '');
                if ($tmpPath !== '' && is_uploaded_file($tmpPath)) {
                    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'heic', 'heif'];
                    if (!in_array($ext, $allowed, true)) {
                        throw new RuntimeException('Allowed file types: Images (JPG, PNG, GIF, WEBP) and PDF documents.');
                    }

                    $chatUploadsDir = ZIMRX_UPLOADS_DIR . '/chat';
                    if (!is_dir($chatUploadsDir) && !mkdir($chatUploadsDir, 0777, true) && !is_dir($chatUploadsDir)) {
                        throw new RuntimeException('Failed to access chat uploads directory.');
                    }

                    $savedFilename = sprintf('chat-%d-%d-%s.%s', $convId, time(), bin2hex(random_bytes(3)), $ext);
                    $targetLocation = $chatUploadsDir . '/' . $savedFilename;

                    if (!move_uploaded_file($tmpPath, $targetLocation)) {
                        throw new RuntimeException('Failed to save file on server.');
                    }

                    $filePath = 'uploads/chat/' . $savedFilename;
                    $fileName = $file['name'];
                    $fileType = in_array($ext, ['pdf'], true) ? 'pdf' : 'image';
                    $fileSize = (int)$file['size'];

                    if ($messageType === 'text') {
                        $messageType = $fileType;
                    }
                    if ($message === '') {
                        $message = ($fileType === 'image') ? 'Sent an image' : 'Sent a document: ' . $fileName;
                    }
                }
            }
        }

        if ($message === '' && !$filePath) {
            throw new RuntimeException('Message or attachment is required.');
        }

        $metadataJson = null;
        if ($rawMetadata !== '') {
            $decoded = json_decode($rawMetadata, true);
            if (is_array($decoded)) {
                $metadataJson = json_encode($decoded, JSON_UNESCAPED_UNICODE);
            }
        }

        $preview = mb_substr($message, 0, 80);
        if ($messageType === 'patient_card') {
            $preview = '📋 [Patient Info] ' . $preview;
        } elseif ($messageType === 'quick_alert') {
            $preview = '⚠️ [Alert] ' . $preview;
        } elseif ($filePath) {
            $preview = ($fileType === 'image' ? '📷 [Image] ' : '📄 [PDF] ') . $preview;
        }

        $pdo->beginTransaction();

        $ins = $pdo->prepare("
            INSERT INTO zimrx_chat_messages 
            (conversation_id, sender_id, sender_name, sender_role, message_type, message, metadata_json, file_path, file_name, file_type, file_size, created_at)
            VALUES (:conv_id, :sender_id, :sender_name, :sender_role, :msg_type, :message, :meta, :fpath, :fname, :ftype, :fsize, CURRENT_TIMESTAMP)
        ");
        $ins->execute([
            ':conv_id' => $convId,
            ':sender_id' => $currentUserId,
            ':sender_name' => $currentUserName,
            ':sender_role' => $currentUserRole,
            ':msg_type' => $messageType,
            ':message' => $message,
            ':meta' => $metadataJson,
            ':fpath' => $filePath,
            ':fname' => $fileName,
            ':ftype' => $fileType,
            ':fsize' => $fileSize
        ]);
        $newMsgId = (int)$pdo->lastInsertId();

        // Update conversation summary
        $updConv = $pdo->prepare("
            UPDATE zimrx_chat_conversations 
            SET last_message_id = :msg_id,
                last_message_preview = :preview,
                last_message_sender_name = :sender,
                last_message_at = CURRENT_TIMESTAMP,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :conv_id
        ");
        $updConv->execute([
            ':msg_id' => $newMsgId,
            ':preview' => $preview,
            ':sender' => $currentUserName,
            ':conv_id' => $convId
        ]);

        // Mark read & delivered for sender
        $updPart = $pdo->prepare("
            UPDATE zimrx_chat_participants 
            SET last_read_message_id = :msg_id,
                last_delivered_message_id = :msg_id,
                last_active_at = CURRENT_TIMESTAMP
            WHERE conversation_id = :conv_id AND user_id = :user_id
        ");
        $updPart->execute([
            ':msg_id' => $newMsgId,
            ':conv_id' => $convId,
            ':user_id' => $currentUserId
        ]);

        $pdo->commit();
        touch_chat_tick($tickFile, $newMsgId);

        echo json_encode([
            'ok' => true,
            'message' => [
                'id' => $newMsgId,
                'conversation_id' => $convId,
                'sender_id' => $currentUserId,
                'sender_name' => $currentUserName,
                'sender_role' => $currentUserRole,
                'message_type' => $messageType,
                'message' => $message,
                'metadata' => $metadataJson ? json_decode($metadataJson, true) : null,
                'file_path' => $filePath,
                'file_name' => $fileName,
                'file_type' => $fileType,
                'file_size' => $fileSize,
                'status' => 'sent',
                'is_mine' => true,
                'created_at' => date('Y-m-d H:i:s')
            ]
        ], JSON_UNESCAPED_UNICODE);
        exit();
    }

    // -------------------------------------------------------------------------
    // 4. Delete or Hide Message
    // -------------------------------------------------------------------------
    if ($action === 'delete_message') {
        $msgId = (int)($_POST['message_id'] ?? 0);
        if ($msgId <= 0) {
            throw new RuntimeException('Invalid message ID.');
        }

        $checkStmt = $pdo->prepare("SELECT id, conversation_id, sender_id FROM zimrx_chat_messages WHERE id = :id");
        $checkStmt->execute([':id' => $msgId]);
        $msg = $checkStmt->fetch(PDO::FETCH_ASSOC);
        if (!$msg) {
            throw new RuntimeException('Message not found.');
        }

        if ((int)$msg['sender_id'] !== $currentUserId && !is_admin_user()) {
            throw new RuntimeException('You can only delete your own messages.');
        }

        $delStmt = $pdo->prepare("
            UPDATE zimrx_chat_messages 
            SET is_deleted = 1, deleted_by = :uid, deleted_at = CURRENT_TIMESTAMP 
            WHERE id = :id
        ");
        $delStmt->execute([':uid' => $currentUserId, ':id' => $msgId]);

        touch_chat_tick($tickFile, $msgId);

        echo json_encode(['ok' => true, 'message_id' => $msgId]);
        exit();
    }

    if ($action === 'hide_message') {
        $msgId = (int)($_POST['message_id'] ?? 0);
        if ($msgId <= 0) {
            throw new RuntimeException('Invalid message ID.');
        }

        $hideStmt = $pdo->prepare("UPDATE zimrx_chat_messages SET is_hidden = 1 WHERE id = :id");
        $hideStmt->execute([':id' => $msgId]);

        echo json_encode(['ok' => true, 'message_id' => $msgId]);
        exit();
    }

    // -------------------------------------------------------------------------
    // Toggle Pin/Unpin Conversation
    // -------------------------------------------------------------------------
    if ($action === 'toggle_pin') {
        $convId = (int)($_POST['conversation_id'] ?? 0);
        if ($convId <= 0) {
            throw new RuntimeException('Invalid conversation ID.');
        }

        $checkStmt = $pdo->prepare("
            SELECT is_pinned FROM zimrx_chat_participants 
            WHERE conversation_id = :conv_id AND user_id = :user_id
        ");
        $checkStmt->execute([':conv_id' => $convId, ':user_id' => $currentUserId]);
        $part = $checkStmt->fetch(PDO::FETCH_ASSOC);
        if (!$part) {
            throw new RuntimeException('You are not a participant in this conversation.');
        }

        $newPinned = ((int)$part['is_pinned'] === 1) ? 0 : 1;

        $upd = $pdo->prepare("
            UPDATE zimrx_chat_participants 
            SET is_pinned = :pinned 
            WHERE conversation_id = :conv_id AND user_id = :user_id
        ");
        $upd->execute([':pinned' => $newPinned, ':conv_id' => $convId, ':user_id' => $currentUserId]);

        echo json_encode([
            'ok' => true,
            'conversation_id' => $convId,
            'is_pinned' => $newPinned
        ]);
        exit();
    }

    // -------------------------------------------------------------------------
    // 5. Start or Find Direct Conversation
    // -------------------------------------------------------------------------
    if ($action === 'start_direct') {
        $targetUserId = (int)($_POST['target_user_id'] ?? 0);
        if ($targetUserId <= 0 || $targetUserId === $currentUserId) {
            throw new RuntimeException('Invalid target user.');
        }

        $targetStmt = $pdo->prepare("SELECT id, display_name, role FROM zimrx_user_accounts WHERE id = :id AND is_active = 1");
        $targetStmt->execute([':id' => $targetUserId]);
        $targetUser = $targetStmt->fetch(PDO::FETCH_ASSOC);
        if (!$targetUser) {
            throw new RuntimeException('Target user not found or inactive.');
        }

        $findStmt = $pdo->prepare("
            SELECT c.id 
            FROM zimrx_chat_conversations c
            INNER JOIN zimrx_chat_participants p1 ON c.id = p1.conversation_id AND p1.user_id = :u1
            INNER JOIN zimrx_chat_participants p2 ON c.id = p2.conversation_id AND p2.user_id = :u2
            WHERE c.type = 'direct'
            LIMIT 1
        ");
        $findStmt->execute([':u1' => $currentUserId, ':u2' => $targetUserId]);
        $existing = $findStmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            echo json_encode([
                'ok' => true,
                'conversation_id' => (int)$existing['id'],
                'is_new' => false,
                'title' => $targetUser['display_name']
            ]);
            exit();
        }

        $pdo->beginTransaction();
        $insConv = $pdo->prepare("
            INSERT INTO zimrx_chat_conversations (type, created_by, created_at, updated_at) 
            VALUES ('direct', :created_by, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        ");
        $insConv->execute([':created_by' => $currentUserId]);
        $convId = (int)$pdo->lastInsertId();

        $insPart = $pdo->prepare("
            INSERT INTO zimrx_chat_participants (conversation_id, user_id, role, last_read_message_id, last_delivered_message_id) 
            VALUES (:conv_id, :user_id, 'member', 0, 0)
        ");
        $insPart->execute([':conv_id' => $convId, ':user_id' => $currentUserId]);
        $insPart->execute([':conv_id' => $convId, ':user_id' => $targetUserId]);

        $pdo->commit();
        touch_chat_tick($tickFile, 0);

        echo json_encode([
            'ok' => true,
            'conversation_id' => $convId,
            'is_new' => true,
            'title' => $targetUser['display_name']
        ]);
        exit();
    }

    // -------------------------------------------------------------------------
    // 6. Quick Messages CRUD (Customizable Presets)
    // -------------------------------------------------------------------------
    if ($action === 'get_quick_messages') {
        $qStmt = $pdo->prepare("
            SELECT id, user_id, title, message, message_type, sort_order, is_active 
            FROM zimrx_chat_quick_messages 
            WHERE (user_id = 0 OR user_id = :my_id) AND is_deleted = 0 
            ORDER BY sort_order ASC, id ASC
        ");
        $qStmt->execute([':my_id' => $currentUserId]);
        $items = $qStmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($items as &$item) {
            $item['id'] = (int)$item['id'];
            $item['user_id'] = (int)$item['user_id'];
            $item['is_active'] = (int)$item['is_active'];
            $item['can_edit'] = ($item['user_id'] === $currentUserId || is_admin_user());
        }
        unset($item);

        echo json_encode(['ok' => true, 'items' => $items], JSON_UNESCAPED_UNICODE);
        exit();
    }

    if ($action === 'save_quick_message') {
        $id = (int)($_POST['id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $messageText = trim($_POST['message'] ?? '');
        $msgType = trim($_POST['message_type'] ?? 'text');
        $isActive = isset($_POST['is_active']) ? (int)$_POST['is_active'] : 1;

        if ($title === '') {
            throw new RuntimeException('Button title is required.');
        }
        if ($messageText === '') {
            throw new RuntimeException('Message content is required.');
        }

        if ($id > 0) {
            // Update existing
            $check = $pdo->prepare("SELECT user_id FROM zimrx_chat_quick_messages WHERE id = :id");
            $check->execute([':id' => $id]);
            $owner = $check->fetch(PDO::FETCH_ASSOC);
            if (!$owner) {
                throw new RuntimeException('Item not found.');
            }
            if ((int)$owner['user_id'] !== $currentUserId && (int)$owner['user_id'] !== 0 && !is_admin_user()) {
                throw new RuntimeException('Permission denied.');
            }

            $upd = $pdo->prepare("
                UPDATE zimrx_chat_quick_messages 
                SET title = :title, message = :msg, message_type = :mtype, is_active = :act, updated_at = CURRENT_TIMESTAMP 
                WHERE id = :id
            ");
            $upd->execute([
                ':title' => $title,
                ':msg' => $messageText,
                ':mtype' => $msgType,
                ':act' => $isActive,
                ':id' => $id
            ]);
            $savedId = $id;
        } else {
            // Create new
            $ins = $pdo->prepare("
                INSERT INTO zimrx_chat_quick_messages (user_id, title, message, message_type, is_active, sort_order) 
                VALUES (:uid, :title, :msg, :mtype, :act, 99)
            ");
            $ins->execute([
                ':uid' => $currentUserId,
                ':title' => $title,
                ':msg' => $messageText,
                ':mtype' => $msgType,
                ':act' => $isActive
            ]);
            $savedId = (int)$pdo->lastInsertId();
        }

        echo json_encode([
            'ok' => true,
            'item' => [
                'id' => $savedId,
                'title' => $title,
                'message' => $messageText,
                'message_type' => $msgType,
                'is_active' => $isActive,
                'can_edit' => true
            ]
        ], JSON_UNESCAPED_UNICODE);
        exit();
    }

    if ($action === 'delete_quick_message') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            throw new RuntimeException('Invalid ID.');
        }

        $check = $pdo->prepare("SELECT user_id FROM zimrx_chat_quick_messages WHERE id = :id");
        $check->execute([':id' => $id]);
        $owner = $check->fetch(PDO::FETCH_ASSOC);
        if (!$owner) {
            throw new RuntimeException('Item not found.');
        }
        if ((int)$owner['user_id'] !== $currentUserId && (int)$owner['user_id'] !== 0 && !is_admin_user()) {
            throw new RuntimeException('Permission denied.');
        }

        $del = $pdo->prepare("UPDATE zimrx_chat_quick_messages SET is_deleted = 1 WHERE id = :id");
        $del->execute([':id' => $id]);

        echo json_encode(['ok' => true, 'id' => $id]);
        exit();
    }

    // -------------------------------------------------------------------------
    // 7. Ultra-Fast Smart Polling Tick Check (Updates Delivery Status)
    // -------------------------------------------------------------------------
    if ($action === 'poll') {
        $clientTick = (float)($_GET['since_tick'] ?? 0);
        $activeConvId = (int)($_GET['active_conversation_id'] ?? 0);
        $lastMsgId = (int)($_GET['last_msg_id'] ?? 0);

        $serverTickData = get_chat_tick($tickFile);
        $serverTick = (float)$serverTickData['tick'];
        $lastKnownMsgId = (int)$serverTickData['last_id'];

        // Automatically update current user's delivered status across all their active conversations
        if ($lastKnownMsgId > 0) {
            $updDelivered = $pdo->prepare("
                UPDATE zimrx_chat_participants 
                SET last_delivered_message_id = MAX(COALESCE(last_delivered_message_id, 0), :max_id),
                    last_active_at = CURRENT_TIMESTAMP
                WHERE user_id = :user_id
            ");
            $updDelivered->execute([':max_id' => $lastKnownMsgId, ':user_id' => $currentUserId]);
        }

        // If client already has latest state, return in 0.2ms
        if ($clientTick > 0 && $serverTick <= $clientTick) {
            echo json_encode([
                'ok' => true,
                'changed' => false,
                'tick' => $serverTick
            ]);
            exit();
        }

        // New events exist. Check total unread for current user.
        $unreadStmt = $pdo->prepare("
            SELECT COALESCE(SUM(
                (SELECT COUNT(*) FROM zimrx_chat_messages m 
                 WHERE m.conversation_id = cp.conversation_id 
                   AND m.id > cp.last_read_message_id 
                   AND m.is_deleted = 0)
            ), 0) AS total_unread
            FROM zimrx_chat_participants cp
            INNER JOIN zimrx_chat_conversations c ON cp.conversation_id = c.id
            WHERE cp.user_id = :user_id AND c.is_archived = 0
        ");
        $unreadStmt->execute([':user_id' => $currentUserId]);
        $totalUnread = (int)$unreadStmt->fetchColumn();

        // If conversation is open, fetch new messages & status updates
        $newMessages = [];
        $otherReadId = 0;
        $otherDeliveredId = 0;

        if ($activeConvId > 0) {
            // Get other participant read receipt if direct chat
            $otherCheck = $pdo->prepare("
                SELECT cp.last_read_message_id, cp.last_delivered_message_id
                FROM zimrx_chat_participants cp
                INNER JOIN zimrx_chat_conversations c ON cp.conversation_id = c.id
                WHERE cp.conversation_id = :conv_id AND cp.user_id != :my_id AND c.type = 'direct'
                LIMIT 1
            ");
            $otherCheck->execute([':conv_id' => $activeConvId, ':my_id' => $currentUserId]);
            $otherRow = $otherCheck->fetch(PDO::FETCH_ASSOC);
            if ($otherRow) {
                $otherReadId = (int)$otherRow['last_read_message_id'];
                $otherDeliveredId = (int)$otherRow['last_delivered_message_id'];
            }

            if ($lastMsgId >= 0) {
                $newMsgStmt = $pdo->prepare("
                    SELECT id, conversation_id, sender_id, sender_name, sender_role, message_type, message, 
                           metadata_json, file_path, file_name, file_type, file_size, is_deleted, deleted_by, created_at
                    FROM zimrx_chat_messages
                    WHERE conversation_id = :conv_id AND id > :last_id AND is_hidden = 0
                    ORDER BY id ASC
                    LIMIT 50
                ");
                $newMsgStmt->execute([':conv_id' => $activeConvId, ':last_id' => $lastMsgId]);
                $newMessages = $newMsgStmt->fetchAll(PDO::FETCH_ASSOC);

                if (!empty($newMessages)) {
                    $maxId = 0;
                    foreach ($newMessages as &$nm) {
                        $nm['id'] = (int)$nm['id'];
                        $nm['sender_id'] = (int)$nm['sender_id'];
                        $nm['is_mine'] = ($nm['sender_id'] === $currentUserId);
                        $nm['is_deleted'] = (int)$nm['is_deleted'];

                        if ($nm['is_mine']) {
                            if ($otherReadId >= $nm['id']) {
                                $nm['status'] = 'seen';
                            } elseif ($otherDeliveredId >= $nm['id']) {
                                $nm['status'] = 'delivered';
                            } else {
                                $nm['status'] = 'sent';
                            }
                        } else {
                            $nm['status'] = 'seen';
                        }

                        $nm['metadata'] = !empty($nm['metadata_json']) ? json_decode($nm['metadata_json'], true) : null;
                        if ($nm['id'] > $maxId) {
                            $maxId = $nm['id'];
                        }
                    }
                    unset($nm);

                    // Auto mark read if active
                    if ($maxId > 0) {
                        $upd = $pdo->prepare("
                            UPDATE zimrx_chat_participants 
                            SET last_read_message_id = :max_id,
                                last_delivered_message_id = :max_id,
                                last_active_at = CURRENT_TIMESTAMP
                            WHERE conversation_id = :conv_id AND user_id = :user_id
                        ");
                        $upd->execute([':max_id' => $maxId, ':conv_id' => $activeConvId, ':user_id' => $currentUserId]);
                    }
                }
            }
        }

        echo json_encode([
            'ok' => true,
            'changed' => true,
            'tick' => $serverTick,
            'total_unread' => $totalUnread,
            'new_messages' => $newMessages,
            'other_read_id' => $otherReadId,
            'other_delivered_id' => $otherDeliveredId
        ], JSON_UNESCAPED_UNICODE);
        exit();
    }

    throw new RuntimeException('Invalid action.');
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage()
    ]);
    exit();
}
