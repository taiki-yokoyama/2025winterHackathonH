<?php
/**
 * フィードバックAPI (Pure PHP)
 */

session_start();

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

define('DB_HOST', 'postgresql');
define('DB_PORT', '5432');
define('DB_NAME', 'winter');
define('DB_USER', 'posse');
define('DB_PASS', 'password');

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
            echo json_encode(['success' => false, 'message' => 'Database connection failed']);
            exit;
        }
    }
    return $pdo;
}

function jsonResponse($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

switch ($uri) {
    case '/feedback-api.php':
    case '/feedback-api.php/':
        if ($method === 'GET') {
            apiInfo();
        }
        break;
    
    case '/feedback-api.php/retrospectives':
        if ($method === 'GET') {
            getTeamRetrospectives();
        }
        break;
    
    case '/feedback-api.php/feedbacks':
        if ($method === 'GET') {
            getFeedbacks();
        } elseif ($method === 'POST') {
            createFeedback();
        }
        break;
    
    case '/feedback-api.php/feedback':
        if ($method === 'PUT') {
            updateFeedback();
        } elseif ($method === 'DELETE') {
            deleteFeedback();
        }
        break;
    
    case '/feedback-api.php/reply':
        if ($method === 'POST') {
            createReply();
        }
        break;
    
    case '/feedback-api.php/reaction':
        if ($method === 'POST') {
            toggleReaction();
        }
        break;
    
    case '/feedback-api.php/mark-read':
        if ($method === 'PUT') {
            markAsRead();
        }
        break;
    
    default:
        jsonResponse(['success' => false, 'message' => 'Route not found'], 404);
}

function apiInfo() {
    jsonResponse([
        'success' => true,
        'name' => 'フィードバックAPI',
        'version' => '1.0.0',
        'endpoints' => [
            'GET /feedback-api.php/retrospectives?team_id={id}' => 'チームの振り返り一覧取得',
            'GET /feedback-api.php/feedbacks?user_id={id}&type={received|sent}' => 'フィードバック一覧取得',
            'POST /feedback-api.php/feedbacks' => 'フィードバック送信',
            'PUT /feedback-api.php/feedback' => 'フィードバック編集',
            'DELETE /feedback-api.php/feedback?id={id}' => 'フィードバック削除',
            'POST /feedback-api.php/reply' => '返信送信',
            'POST /feedback-api.php/reaction' => 'リアクション切り替え',
            'PUT /feedback-api.php/mark-read' => '既読マーク',
        ]
    ]);
}

// チームの振り返り一覧取得
function getTeamRetrospectives() {
    $teamId = $_GET['team_id'] ?? null;
    
    if (!$teamId) {
        jsonResponse(['success' => false, 'message' => 'team_id is required'], 422);
    }
    
    $pdo = getDb();
    
    $sql = "SELECT r.*, u.name as user_name
            FROM retrospectives r
            INNER JOIN users u ON r.user_id = u.id
            INNER JOIN team_members tm ON u.id = tm.user_id
            WHERE tm.team_id = :team_id
            ORDER BY r.week_start_date DESC, r.created_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':team_id' => $teamId]);
    $retrospectives = $stmt->fetchAll();
    
    jsonResponse([
        'success' => true,
        'data' => $retrospectives
    ]);
}

// フィードバック一覧取得
function getFeedbacks() {
    $userId = $_GET['user_id'] ?? null;
    $type = $_GET['type'] ?? 'received'; // received or sent
    
    if (!$userId) {
        jsonResponse(['success' => false, 'message' => 'user_id is required'], 422);
    }
    
    $pdo = getDb();
    
    if ($type === 'received') {
        $sql = "SELECT f.*, 
                       u_sender.name as sender_name,
                       r.week_start_date,
                       (SELECT COUNT(*) FROM feedback_replies WHERE feedback_id = f.id) as reply_count,
                       (SELECT COUNT(*) FROM feedback_reactions WHERE feedback_id = f.id) as reaction_count
                FROM feedbacks f
                INNER JOIN users u_sender ON f.sender_id = u_sender.id
                INNER JOIN retrospectives r ON f.retrospective_id = r.id
                WHERE f.receiver_id = :user_id
                ORDER BY f.created_at DESC";
    } else {
        $sql = "SELECT f.*, 
                       u_receiver.name as receiver_name,
                       r.week_start_date,
                       (SELECT COUNT(*) FROM feedback_replies WHERE feedback_id = f.id) as reply_count,
                       (SELECT COUNT(*) FROM feedback_reactions WHERE feedback_id = f.id) as reaction_count
                FROM feedbacks f
                INNER JOIN users u_receiver ON f.receiver_id = u_receiver.id
                INNER JOIN retrospectives r ON f.retrospective_id = r.id
                WHERE f.sender_id = :user_id
                ORDER BY f.created_at DESC";
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':user_id' => $userId]);
    $feedbacks = $stmt->fetchAll();
    
    // 各フィードバックの返信とリアクションを取得
    foreach ($feedbacks as &$feedback) {
        // 返信取得
        $replySql = "SELECT fr.*, u.name as user_name
                     FROM feedback_replies fr
                     INNER JOIN users u ON fr.user_id = u.id
                     WHERE fr.feedback_id = :feedback_id
                     ORDER BY fr.created_at ASC";
        $replyStmt = $pdo->prepare($replySql);
        $replyStmt->execute([':feedback_id' => $feedback['id']]);
        $feedback['replies'] = $replyStmt->fetchAll();
        
        // リアクション取得
        $reactionSql = "SELECT reaction_type, COUNT(*) as count
                        FROM feedback_reactions
                        WHERE feedback_id = :feedback_id
                        GROUP BY reaction_type";
        $reactionStmt = $pdo->prepare($reactionSql);
        $reactionStmt->execute([':feedback_id' => $feedback['id']]);
        $feedback['reactions'] = $reactionStmt->fetchAll();
    }
    
    jsonResponse([
        'success' => true,
        'data' => $feedbacks
    ]);
}

// フィードバック送信
function createFeedback() {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $errors = [];
    
    if (empty($input['retrospective_id'])) {
        $errors['retrospective_id'] = '振り返りIDは必須です';
    }
    
    if (empty($input['sender_id'])) {
        $errors['sender_id'] = '送信者IDは必須です';
    }
    
    if (empty($input['receiver_id'])) {
        $errors['receiver_id'] = '受信者IDは必須です';
    }
    
    if (empty($input['content'])) {
        $errors['content'] = 'フィードバック内容は必須です';
    } elseif (mb_strlen($input['content']) > 1000) {
        $errors['content'] = 'フィードバックは1000文字以内で入力してください';
    }
    
    if (!empty($errors)) {
        jsonResponse(['success' => false, 'errors' => $errors], 422);
    }
    
    $pdo = getDb();
    
    $sql = "INSERT INTO feedbacks (retrospective_id, sender_id, receiver_id, content, created_at, updated_at)
            VALUES (:retrospective_id, :sender_id, :receiver_id, :content, NOW(), NOW())
            RETURNING id";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':retrospective_id' => $input['retrospective_id'],
        ':sender_id' => $input['sender_id'],
        ':receiver_id' => $input['receiver_id'],
        ':content' => $input['content']
    ]);
    
    $result = $stmt->fetch();
    
    jsonResponse([
        'success' => true,
        'message' => 'フィードバックを送信しました',
        'data' => ['id' => $result['id']]
    ], 201);
}

// フィードバック編集
function updateFeedback() {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['id'], $input['content'])) {
        jsonResponse(['success' => false, 'message' => 'Required fields: id, content'], 422);
    }
    
    if (mb_strlen($input['content']) > 1000) {
        jsonResponse(['success' => false, 'message' => 'フィードバックは1000文字以内で入力してください'], 422);
    }
    
    $pdo = getDb();
    
    $sql = "UPDATE feedbacks SET content = :content, updated_at = NOW() WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':id' => $input['id'],
        ':content' => $input['content']
    ]);
    
    jsonResponse([
        'success' => true,
        'message' => 'フィードバックを更新しました'
    ]);
}

// フィードバック削除
function deleteFeedback() {
    $id = $_GET['id'] ?? null;
    
    if (!$id) {
        jsonResponse(['success' => false, 'message' => 'id is required'], 422);
    }
    
    $pdo = getDb();
    
    $sql = "DELETE FROM feedbacks WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $id]);
    
    jsonResponse([
        'success' => true,
        'message' => 'フィードバックを削除しました'
    ]);
}

// 返信送信
function createReply() {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['feedback_id'], $input['user_id'], $input['content'])) {
        jsonResponse(['success' => false, 'message' => 'Required fields: feedback_id, user_id, content'], 422);
    }
    
    if (mb_strlen($input['content']) > 1000) {
        jsonResponse(['success' => false, 'message' => '返信は1000文字以内で入力してください'], 422);
    }
    
    $pdo = getDb();
    
    $sql = "INSERT INTO feedback_replies (feedback_id, user_id, content, created_at, updated_at)
            VALUES (:feedback_id, :user_id, :content, NOW(), NOW())
            RETURNING id";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':feedback_id' => $input['feedback_id'],
        ':user_id' => $input['user_id'],
        ':content' => $input['content']
    ]);
    
    $result = $stmt->fetch();
    
    jsonResponse([
        'success' => true,
        'message' => '返信を送信しました',
        'data' => ['id' => $result['id']]
    ], 201);
}

// リアクション切り替え
function toggleReaction() {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['feedback_id'], $input['user_id'], $input['reaction_type'])) {
        jsonResponse(['success' => false, 'message' => 'Required fields: feedback_id, user_id, reaction_type'], 422);
    }
    
    $pdo = getDb();
    
    // 既存のリアクションをチェック
    $checkSql = "SELECT id FROM feedback_reactions 
                 WHERE feedback_id = :feedback_id AND user_id = :user_id AND reaction_type = :reaction_type";
    $checkStmt = $pdo->prepare($checkSql);
    $checkStmt->execute([
        ':feedback_id' => $input['feedback_id'],
        ':user_id' => $input['user_id'],
        ':reaction_type' => $input['reaction_type']
    ]);
    
    if ($checkStmt->fetch()) {
        // 既存のリアクションを削除
        $deleteSql = "DELETE FROM feedback_reactions 
                      WHERE feedback_id = :feedback_id AND user_id = :user_id AND reaction_type = :reaction_type";
        $deleteStmt = $pdo->prepare($deleteSql);
        $deleteStmt->execute([
            ':feedback_id' => $input['feedback_id'],
            ':user_id' => $input['user_id'],
            ':reaction_type' => $input['reaction_type']
        ]);
        
        jsonResponse([
            'success' => true,
            'message' => 'リアクションを削除しました',
            'action' => 'removed'
        ]);
    } else {
        // 新しいリアクションを追加
        $insertSql = "INSERT INTO feedback_reactions (feedback_id, user_id, reaction_type, created_at)
                      VALUES (:feedback_id, :user_id, :reaction_type, NOW())";
        $insertStmt = $pdo->prepare($insertSql);
        $insertStmt->execute([
            ':feedback_id' => $input['feedback_id'],
            ':user_id' => $input['user_id'],
            ':reaction_type' => $input['reaction_type']
        ]);
        
        jsonResponse([
            'success' => true,
            'message' => 'リアクションを追加しました',
            'action' => 'added'
        ]);
    }
}

// 既読マーク
function markAsRead() {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['feedback_id'])) {
        jsonResponse(['success' => false, 'message' => 'feedback_id is required'], 422);
    }
    
    $pdo = getDb();
    
    $sql = "UPDATE feedbacks SET is_read = TRUE, updated_at = NOW() WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $input['feedback_id']]);
    
    jsonResponse([
        'success' => true,
        'message' => '既読にしました'
    ]);
}
