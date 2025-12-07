<?php
// Pure PHP Good&More API
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// OPTIONSリクエストの処理
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
function getDbConnection() {
    try {
        $dsn = sprintf('pgsql:host=%s;port=%s;dbname=%s', DB_HOST, DB_PORT, DB_NAME);
        $pdo = new PDO($dsn, DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return $pdo;
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database connection failed']);
        exit;
    }
}

// ルーティング
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$requestMethod = $_SERVER['REQUEST_METHOD'];

// ルート定義
$routes = [
    'GET /api/good-more/sent' => 'getSentHistory',
    'GET /api/good-more/received' => 'getReceivedHistory',
    'GET /api/good-more/detail' => 'getDetail',
    'POST /api/good-more/send' => 'sendGoodMore',
    'POST /api/good-more/reaction' => 'addReaction',
    'DELETE /api/good-more/reaction' => 'removeReaction',
    'GET /api/test' => 'test',
    'GET /' => 'home',
];

$routeKey = $requestMethod . ' ' . $requestUri;

if (isset($routes[$routeKey])) {
    $function = $routes[$routeKey];
    $function();
} else {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Route not found']);
}

// ホーム
function home() {
    echo json_encode([
        'success' => true,
        'message' => 'Good&More API',
        'version' => '1.0.0',
        'endpoints' => [
            'GET /api/test' => 'テスト接続',
            'POST /api/good-more/send' => 'Good&More送信',
            'GET /api/good-more/sent' => '送信履歴取得',
            'GET /api/good-more/received' => '受信履歴取得',
            'GET /api/good-more/detail?id={id}' => '詳細取得',
            'POST /api/good-more/reaction' => 'リアクション追加',
            'DELETE /api/good-more/reaction?id={id}' => 'リアクション削除',
        ]
    ]);
}

// テスト接続
function test() {
    $pdo = getDbConnection();
    echo json_encode([
        'success' => true,
        'message' => 'Database connection successful',
        'database' => DB_NAME
    ]);
}

// Good&More送信
function sendGoodMore() {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['sender_id']) || !isset($input['receiver_id']) || 
        !isset($input['good_message']) || !isset($input['more_message'])) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Required fields missing']);
        return;
    }
    
    $pdo = getDbConnection();
    
    $sql = "INSERT INTO good_mores (sender_id, receiver_id, good_message, more_message, status, created_at, updated_at) 
            VALUES (:sender_id, :receiver_id, :good_message, :more_message, 'sent', NOW(), NOW()) 
            RETURNING id";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':sender_id' => $input['sender_id'],
        ':receiver_id' => $input['receiver_id'],
        ':good_message' => $input['good_message'],
        ':more_message' => $input['more_message']
    ]);
    
    $result = $stmt->fetch();
    
    http_response_code(201);
    echo json_encode([
        'success' => true,
        'data' => [
            'id' => $result['id'],
            'sender_id' => $input['sender_id'],
            'receiver_id' => $input['receiver_id'],
            'good_message' => $input['good_message'],
            'more_message' => $input['more_message'],
            'status' => 'sent'
        ]
    ]);
}

// 送信履歴取得
function getSentHistory() {
    $senderId = $_GET['sender_id'] ?? null;
    $page = $_GET['page'] ?? 1;
    $perPage = $_GET['per_page'] ?? 20;
    
    if (!$senderId) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'sender_id is required']);
        return;
    }
    
    $pdo = getDbConnection();
    $offset = ($page - 1) * $perPage;
    
    $sql = "SELECT gm.*, 
                   u_receiver.name as receiver_name,
                   COUNT(gmr.id) as reaction_count
            FROM good_mores gm
            LEFT JOIN users u_receiver ON gm.receiver_id = u_receiver.id
            LEFT JOIN good_more_reactions gmr ON gm.id = gmr.good_more_id
            WHERE gm.sender_id = :sender_id
            GROUP BY gm.id, u_receiver.name
            ORDER BY gm.created_at DESC
            LIMIT :limit OFFSET :offset";
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':sender_id', $senderId, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    
    $data = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'data' => $data,
        'page' => (int)$page,
        'per_page' => (int)$perPage
    ]);
}

// 受信履歴取得
function getReceivedHistory() {
    $receiverId = $_GET['receiver_id'] ?? null;
    $page = $_GET['page'] ?? 1;
    $perPage = $_GET['per_page'] ?? 20;
    
    if (!$receiverId) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'receiver_id is required']);
        return;
    }
    
    $pdo = getDbConnection();
    $offset = ($page - 1) * $perPage;
    
    $sql = "SELECT gm.*, 
                   u_sender.name as sender_name,
                   COUNT(gmr.id) as reaction_count
            FROM good_mores gm
            LEFT JOIN users u_sender ON gm.sender_id = u_sender.id
            LEFT JOIN good_more_reactions gmr ON gm.id = gmr.good_more_id
            WHERE gm.receiver_id = :receiver_id
            GROUP BY gm.id, u_sender.name
            ORDER BY gm.created_at DESC
            LIMIT :limit OFFSET :offset";
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':receiver_id', $receiverId, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    
    $data = $stmt->fetchAll();
    
    // 既読更新
    $updateSql = "UPDATE good_mores SET status = 'read', updated_at = NOW() 
                  WHERE receiver_id = :receiver_id AND status = 'sent'";
    $updateStmt = $pdo->prepare($updateSql);
    $updateStmt->execute([':receiver_id' => $receiverId]);
    
    echo json_encode([
        'success' => true,
        'data' => $data,
        'page' => (int)$page,
        'per_page' => (int)$perPage
    ]);
}

// 詳細取得
function getDetail() {
    $id = $_GET['id'] ?? null;
    
    if (!$id) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'id is required']);
        return;
    }
    
    $pdo = getDbConnection();
    
    $sql = "SELECT gm.*, 
                   u_sender.name as sender_name,
                   u_receiver.name as receiver_name
            FROM good_mores gm
            LEFT JOIN users u_sender ON gm.sender_id = u_sender.id
            LEFT JOIN users u_receiver ON gm.receiver_id = u_receiver.id
            WHERE gm.id = :id";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $id]);
    $goodMore = $stmt->fetch();
    
    if (!$goodMore) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Not found']);
        return;
    }
    
    // リアクション取得
    $reactionSql = "SELECT gmr.*, u.name as user_name
                    FROM good_more_reactions gmr
                    LEFT JOIN users u ON gmr.user_id = u.id
                    WHERE gmr.good_more_id = :id";
    $reactionStmt = $pdo->prepare($reactionSql);
    $reactionStmt->execute([':id' => $id]);
    $reactions = $reactionStmt->fetchAll();
    
    $goodMore['reactions'] = $reactions;
    
    echo json_encode([
        'success' => true,
        'data' => $goodMore
    ]);
}

// リアクション追加
function addReaction() {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['good_more_id']) || !isset($input['user_id']) || !isset($input['reaction_type'])) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Required fields missing']);
        return;
    }
    
    $pdo = getDbConnection();
    
    // 既存のリアクションを削除
    $deleteSql = "DELETE FROM good_more_reactions WHERE good_more_id = :good_more_id AND user_id = :user_id";
    $deleteStmt = $pdo->prepare($deleteSql);
    $deleteStmt->execute([
        ':good_more_id' => $input['good_more_id'],
        ':user_id' => $input['user_id']
    ]);
    
    // 新しいリアクションを追加
    $sql = "INSERT INTO good_more_reactions (good_more_id, user_id, reaction_type, reaction_content, created_at, updated_at) 
            VALUES (:good_more_id, :user_id, :reaction_type, :reaction_content, NOW(), NOW()) 
            RETURNING id";
    
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
    
    http_response_code(201);
    echo json_encode([
        'success' => true,
        'data' => [
            'id' => $result['id'],
            'good_more_id' => $input['good_more_id'],
            'user_id' => $input['user_id'],
            'reaction_type' => $input['reaction_type'],
            'reaction_content' => $input['reaction_content'] ?? null
        ]
    ]);
}

// リアクション削除
function removeReaction() {
    $id = $_GET['id'] ?? null;
    
    if (!$id) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'id is required']);
        return;
    }
    
    $pdo = getDbConnection();
    
    $sql = "DELETE FROM good_more_reactions WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $id]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Reaction deleted'
    ]);
}
