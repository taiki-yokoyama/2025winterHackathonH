<?php
/**
 * Good&More API (Pure PHP)
 * Laravel不要のシンプルなREST API
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// データベース接続設定
define('DB_HOST', 'postgresql');
define('DB_PORT', '5432');
define('DB_NAME', 'winter');
define('DB_USER', 'posse');
define('DB_PASS', 'password');

// データベース接続
function getDb() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = sprintf('pgsql:host=%s;port=%s;dbname=%s', DB_HOST, DB_PORT, DB_NAME);
            $pdo = new PDO($dsn, DB_USER, DB_PASS);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $e->getMessage()]);
            exit;
        }
    }
    return $pdo;
}

// JSONレスポンス
function jsonResponse($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// ルーティング
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

// ルート処理
switch ($uri) {
    case '/good-more-api.php':
    case '/good-more-api.php/':
        if ($method === 'GET') {
            apiInfo();
        }
        break;
    
    case '/good-more-api.php/test':
        if ($method === 'GET') {
            testConnection();
        }
        break;
    
    case '/good-more-api.php/users':
        if ($method === 'GET') {
            getUsers();
        }
        break;
    
    case '/good-more-api.php/send':
        if ($method === 'POST') {
            sendGoodMore();
        }
        break;
    
    case '/good-more-api.php/sent':
        if ($method === 'GET') {
            getSentHistory();
        }
        break;
    
    case '/good-more-api.php/received':
        if ($method === 'GET') {
            getReceivedHistory();
        }
        break;
    
    case '/good-more-api.php/detail':
        if ($method === 'GET') {
            getDetail();
        }
        break;
    
    case '/good-more-api.php/notifications':
        if ($method === 'GET') {
            getNotifications();
        }
        break;
    
    case '/good-more-api.php/notifications/read':
        if ($method === 'POST') {
            markNotificationAsRead();
        }
        break;
    
    case '/good-more-api.php/reaction':
        if ($method === 'POST') {
            addReaction();
        } elseif ($method === 'DELETE') {
            removeReaction();
        }
        break;
    
    default:
        jsonResponse(['success' => false, 'message' => 'Route not found'], 404);
}

// API情報
function apiInfo() {
    jsonResponse([
        'success' => true,
        'name' => 'Good&More API',
        'version' => '1.0.0',
        'endpoints' => [
            'GET /good-more-api.php/test' => 'データベース接続テスト',
            'GET /good-more-api.php/users' => 'ユーザー一覧取得',
            'POST /good-more-api.php/send' => 'Good&More送信',
            'GET /good-more-api.php/sent?sender_id={id}&page={page}&per_page={per_page}' => '送信履歴取得',
            'GET /good-more-api.php/received?receiver_id={id}&page={page}&per_page={per_page}' => '受信履歴取得',
            'GET /good-more-api.php/detail?id={id}' => '詳細取得',
            'GET /good-more-api.php/notifications?user_id={id}' => '通知一覧取得',
            'POST /good-more-api.php/notifications/read' => '通知既読',
            'POST /good-more-api.php/reaction' => 'リアクション追加',
            'DELETE /good-more-api.php/reaction?id={id}' => 'リアクション削除',
        ]
    ]);
}

// データベース接続テスト
function testConnection() {
    $pdo = getDb();
    
    // テーブル存在確認
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
    $result = $stmt->fetch();
    
    jsonResponse([
        'success' => true,
        'message' => 'Database connection successful',
        'database' => DB_NAME,
        'users_count' => $result['count']
    ]);
}

// Good&More送信
function sendGoodMore() {
    $input = json_decode(file_get_contents('php://input'), true);
    
    // 必須フィールドチェック
    if (!isset($input['sender_id'], $input['receiver_id'])) {
        jsonResponse(['success' => false, 'message' => 'Required fields: sender_id, receiver_id'], 422);
    }
    
    // Goodメッセージは必須
    if (empty($input['good_message'])) {
        jsonResponse(['success' => false, 'message' => 'Good message is required'], 422);
    }
    
    // Moreメッセージがある場合、Goodメッセージも必須（既にチェック済み）
    // Moreメッセージは任意
    $moreMessage = $input['more_message'] ?? null;
    
    $pdo = getDb();
    
    $sql = "INSERT INTO good_mores (sender_id, receiver_id, good_message, more_message, status, created_at, updated_at) 
            VALUES (:sender_id, :receiver_id, :good_message, :more_message, 'sent', NOW(), NOW()) 
            RETURNING id, created_at";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':sender_id' => $input['sender_id'],
        ':receiver_id' => $input['receiver_id'],
        ':good_message' => $input['good_message'],
        ':more_message' => $moreMessage
    ]);
    
    $result = $stmt->fetch();
    
    jsonResponse([
        'success' => true,
        'message' => 'Good&More sent successfully',
        'data' => [
            'id' => $result['id'],
            'sender_id' => $input['sender_id'],
            'receiver_id' => $input['receiver_id'],
            'good_message' => $input['good_message'],
            'more_message' => $moreMessage,
            'status' => 'sent',
            'created_at' => $result['created_at']
        ]
    ], 201);
}

// 送信履歴取得
function getSentHistory() {
    $senderId = $_GET['sender_id'] ?? null;
    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPage = min(100, max(1, (int)($_GET['per_page'] ?? 20)));
    
    if (!$senderId) {
        jsonResponse(['success' => false, 'message' => 'sender_id is required'], 422);
    }
    
    $pdo = getDb();
    $offset = ($page - 1) * $perPage;
    
    // 総件数取得
    $countSql = "SELECT COUNT(*) as total FROM good_mores WHERE sender_id = :sender_id";
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute([':sender_id' => $senderId]);
    $total = $countStmt->fetch()['total'];
    
    // データ取得
    $sql = "SELECT gm.*, 
                   u_receiver.name as receiver_name,
                   u_receiver.email as receiver_email,
                   COUNT(gmr.id) as reaction_count
            FROM good_mores gm
            LEFT JOIN users u_receiver ON gm.receiver_id = u_receiver.id
            LEFT JOIN good_more_reactions gmr ON gm.id = gmr.good_more_id
            WHERE gm.sender_id = :sender_id
            GROUP BY gm.id, u_receiver.name, u_receiver.email
            ORDER BY gm.created_at DESC
            LIMIT :limit OFFSET :offset";
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':sender_id', $senderId, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    
    $data = $stmt->fetchAll();
    
    jsonResponse([
        'success' => true,
        'data' => $data,
        'pagination' => [
            'current_page' => $page,
            'per_page' => $perPage,
            'total' => (int)$total,
            'last_page' => ceil($total / $perPage)
        ]
    ]);
}

// 受信履歴取得
function getReceivedHistory() {
    $receiverId = $_GET['receiver_id'] ?? null;
    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPage = min(100, max(1, (int)($_GET['per_page'] ?? 20)));
    
    if (!$receiverId) {
        jsonResponse(['success' => false, 'message' => 'receiver_id is required'], 422);
    }
    
    $pdo = getDb();
    $offset = ($page - 1) * $perPage;
    
    // 総件数取得
    $countSql = "SELECT COUNT(*) as total FROM good_mores WHERE receiver_id = :receiver_id";
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute([':receiver_id' => $receiverId]);
    $total = $countStmt->fetch()['total'];
    
    // データ取得
    $sql = "SELECT gm.*, 
                   u_sender.name as sender_name,
                   u_sender.email as sender_email,
                   COUNT(gmr.id) as reaction_count
            FROM good_mores gm
            LEFT JOIN users u_sender ON gm.sender_id = u_sender.id
            LEFT JOIN good_more_reactions gmr ON gm.id = gmr.good_more_id
            WHERE gm.receiver_id = :receiver_id
            GROUP BY gm.id, u_sender.name, u_sender.email
            ORDER BY gm.created_at DESC
            LIMIT :limit OFFSET :offset";
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':receiver_id', $receiverId, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    
    $data = $stmt->fetchAll();
    
    // 未読を既読に更新
    $updateSql = "UPDATE good_mores SET status = 'read', updated_at = NOW() 
                  WHERE receiver_id = :receiver_id AND status = 'sent'";
    $updateStmt = $pdo->prepare($updateSql);
    $updateStmt->execute([':receiver_id' => $receiverId]);
    
    jsonResponse([
        'success' => true,
        'data' => $data,
        'pagination' => [
            'current_page' => $page,
            'per_page' => $perPage,
            'total' => (int)$total,
            'last_page' => ceil($total / $perPage)
        ]
    ]);
}

// 詳細取得
function getDetail() {
    $id = $_GET['id'] ?? null;
    
    if (!$id) {
        jsonResponse(['success' => false, 'message' => 'id is required'], 422);
    }
    
    $pdo = getDb();
    
    // Good&More取得
    $sql = "SELECT gm.*, 
                   u_sender.name as sender_name,
                   u_sender.email as sender_email,
                   u_receiver.name as receiver_name,
                   u_receiver.email as receiver_email
            FROM good_mores gm
            LEFT JOIN users u_sender ON gm.sender_id = u_sender.id
            LEFT JOIN users u_receiver ON gm.receiver_id = u_receiver.id
            WHERE gm.id = :id";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $id]);
    $goodMore = $stmt->fetch();
    
    if (!$goodMore) {
        jsonResponse(['success' => false, 'message' => 'Good&More not found'], 404);
    }
    
    // リアクション取得
    $reactionSql = "SELECT gmr.*, u.name as user_name, u.email as user_email
                    FROM good_more_reactions gmr
                    LEFT JOIN users u ON gmr.user_id = u.id
                    WHERE gmr.good_more_id = :id
                    ORDER BY gmr.created_at DESC";
    $reactionStmt = $pdo->prepare($reactionSql);
    $reactionStmt->execute([':id' => $id]);
    $reactions = $reactionStmt->fetchAll();
    
    $goodMore['reactions'] = $reactions;
    
    jsonResponse([
        'success' => true,
        'data' => $goodMore
    ]);
}

// リアクション追加
function addReaction() {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['good_more_id'], $input['user_id'], $input['reaction_type'])) {
        jsonResponse(['success' => false, 'message' => 'Required fields: good_more_id, user_id, reaction_type'], 422);
    }
    
    $pdo = getDb();
    
    // Good&More存在確認
    $checkSql = "SELECT id, receiver_id FROM good_mores WHERE id = :id";
    $checkStmt = $pdo->prepare($checkSql);
    $checkStmt->execute([':id' => $input['good_more_id']]);
    $goodMore = $checkStmt->fetch();
    
    if (!$goodMore) {
        jsonResponse(['success' => false, 'message' => 'Good&More not found'], 404);
    }
    
    // 既存のリアクションを削除（1ユーザー1リアクション）
    $deleteSql = "DELETE FROM good_more_reactions WHERE good_more_id = :good_more_id AND user_id = :user_id";
    $deleteStmt = $pdo->prepare($deleteSql);
    $deleteStmt->execute([
        ':good_more_id' => $input['good_more_id'],
        ':user_id' => $input['user_id']
    ]);
    
    // 新しいリアクションを追加
    $sql = "INSERT INTO good_more_reactions (good_more_id, user_id, reaction_type, reaction_content, created_at, updated_at) 
            VALUES (:good_more_id, :user_id, :reaction_type, :reaction_content, NOW(), NOW()) 
            RETURNING id, created_at";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':good_more_id' => $input['good_more_id'],
        ':user_id' => $input['user_id'],
        ':reaction_type' => $input['reaction_type'],
        ':reaction_content' => $input['reaction_content'] ?? null
    ]);
    
    $result = $stmt->fetch();
    
    // Good&Moreのステータスを更新
    $updateSql = "UPDATE good_mores SET status = 'reacted', updated_at = NOW() WHERE id = :id";
    $updateStmt = $pdo->prepare($updateSql);
    $updateStmt->execute([':id' => $input['good_more_id']]);
    
    jsonResponse([
        'success' => true,
        'message' => 'Reaction added successfully',
        'data' => [
            'id' => $result['id'],
            'good_more_id' => $input['good_more_id'],
            'user_id' => $input['user_id'],
            'reaction_type' => $input['reaction_type'],
            'reaction_content' => $input['reaction_content'] ?? null,
            'created_at' => $result['created_at']
        ]
    ], 201);
}

// リアクション削除
function removeReaction() {
    $id = $_GET['id'] ?? null;
    
    if (!$id) {
        jsonResponse(['success' => false, 'message' => 'id is required'], 422);
    }
    
    $pdo = getDb();
    
    $sql = "DELETE FROM good_more_reactions WHERE id = :id RETURNING good_more_id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $id]);
    $result = $stmt->fetch();
    
    if (!$result) {
        jsonResponse(['success' => false, 'message' => 'Reaction not found'], 404);
    }
    
    jsonResponse([
        'success' => true,
        'message' => 'Reaction deleted successfully'
    ]);
}

// ユーザー一覧取得
function getUsers() {
    $pdo = getDb();
    
    $sql = "SELECT id, name, email FROM users ORDER BY id";
    $stmt = $pdo->query($sql);
    $users = $stmt->fetchAll();
    
    jsonResponse([
        'success' => true,
        'data' => $users
    ]);
}

// 通知一覧取得
function getNotifications() {
    $userId = $_GET['user_id'] ?? null;
    
    if (!$userId) {
        jsonResponse(['success' => false, 'message' => 'user_id is required'], 422);
    }
    
    $pdo = getDb();
    
    // 受信したGood&More（未読）
    $sql = "SELECT gm.id, gm.sender_id, gm.good_message, gm.more_message, gm.status, gm.created_at,
                   u.name as sender_name,
                   'good_more' as type
            FROM good_mores gm
            LEFT JOIN users u ON gm.sender_id = u.id
            WHERE gm.receiver_id = :user_id AND gm.status = 'sent'
            
            UNION ALL
            
            SELECT gm.id, gmr.user_id as sender_id, 
                   CONCAT('あなたのGood&Moreにリアクションがありました') as good_message,
                   gmr.reaction_content as more_message,
                   'reacted' as status,
                   gmr.created_at,
                   u.name as sender_name,
                   'reaction' as type
            FROM good_more_reactions gmr
            LEFT JOIN good_mores gm ON gmr.good_more_id = gm.id
            LEFT JOIN users u ON gmr.user_id = u.id
            WHERE gm.sender_id = :user_id
            
            ORDER BY created_at DESC
            LIMIT 50";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':user_id' => $userId]);
    $notifications = $stmt->fetchAll();
    
    // 未読数
    $unreadSql = "SELECT COUNT(*) as count FROM good_mores WHERE receiver_id = :user_id AND status = 'sent'";
    $unreadStmt = $pdo->prepare($unreadSql);
    $unreadStmt->execute([':user_id' => $userId]);
    $unreadCount = $unreadStmt->fetch()['count'];
    
    jsonResponse([
        'success' => true,
        'data' => $notifications,
        'unread_count' => (int)$unreadCount
    ]);
}

// 通知既読
function markNotificationAsRead() {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['good_more_id'])) {
        jsonResponse(['success' => false, 'message' => 'good_more_id is required'], 422);
    }
    
    $pdo = getDb();
    
    $sql = "UPDATE good_mores SET status = 'read', updated_at = NOW() WHERE id = :id AND status = 'sent'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $input['good_more_id']]);
    
    jsonResponse([
        'success' => true,
        'message' => 'Notification marked as read'
    ]);
}
