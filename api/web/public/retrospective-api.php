<?php
/**
 * 振り返りAPI (Pure PHP)
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
    case '/retrospective-api.php':
    case '/retrospective-api.php/':
        if ($method === 'GET') {
            apiInfo();
        }
        break;
    
    case '/retrospective-api.php/tasks':
        if ($method === 'GET') {
            getTasks();
        } elseif ($method === 'POST') {
            createTask();
        }
        break;
    
    case '/retrospective-api.php/retrospectives':
        if ($method === 'GET') {
            getRetrospectives();
        } elseif ($method === 'POST') {
            createRetrospective();
        }
        break;
    
    case '/retrospective-api.php/retrospective':
        if ($method === 'GET') {
            getRetrospective();
        }
        break;
    
    case '/retrospective-api.php/current-week':
        if ($method === 'GET') {
            getCurrentWeekRetrospective();
        }
        break;
    
    default:
        jsonResponse(['success' => false, 'message' => 'Route not found'], 404);
}

function apiInfo() {
    jsonResponse([
        'success' => true,
        'name' => '振り返りAPI',
        'version' => '1.0.0',
        'endpoints' => [
            'GET /retrospective-api.php/tasks?user_id={id}' => 'タスク一覧取得',
            'POST /retrospective-api.php/tasks' => 'タスク作成',
            'GET /retrospective-api.php/retrospectives?user_id={id}' => '振り返り一覧取得',
            'POST /retrospective-api.php/retrospectives' => '振り返り作成',
            'GET /retrospective-api.php/retrospective?id={id}' => '振り返り詳細取得',
            'GET /retrospective-api.php/current-week?user_id={id}' => '今週の振り返り取得',
        ]
    ]);
}

// タスク一覧取得
function getTasks() {
    $userId = $_GET['user_id'] ?? null;
    
    if (!$userId) {
        jsonResponse(['success' => false, 'message' => 'user_id is required'], 422);
    }
    
    $pdo = getDb();
    
    // 過去1週間のタスク
    $sql = "SELECT * FROM tasks 
            WHERE user_id = :user_id 
            AND created_at >= NOW() - INTERVAL '7 days'
            ORDER BY created_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':user_id' => $userId]);
    $tasks = $stmt->fetchAll();
    
    jsonResponse([
        'success' => true,
        'data' => $tasks
    ]);
}

// タスク作成
function createTask() {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['user_id'], $input['title'])) {
        jsonResponse(['success' => false, 'message' => 'Required fields: user_id, title'], 422);
    }
    
    $pdo = getDb();
    
    $sql = "INSERT INTO tasks (user_id, title, description, status, created_at, updated_at) 
            VALUES (:user_id, :title, :description, :status, NOW(), NOW()) 
            RETURNING id, created_at";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':user_id' => $input['user_id'],
        ':title' => $input['title'],
        ':description' => $input['description'] ?? null,
        ':status' => $input['status'] ?? 'pending'
    ]);
    
    $result = $stmt->fetch();
    
    jsonResponse([
        'success' => true,
        'data' => [
            'id' => $result['id'],
            'user_id' => $input['user_id'],
            'title' => $input['title'],
            'description' => $input['description'] ?? null,
            'status' => $input['status'] ?? 'pending',
            'created_at' => $result['created_at']
        ]
    ], 201);
}

// 振り返り一覧取得
function getRetrospectives() {
    $userId = $_GET['user_id'] ?? null;
    
    if (!$userId) {
        jsonResponse(['success' => false, 'message' => 'user_id is required'], 422);
    }
    
    $pdo = getDb();
    
    $sql = "SELECT * FROM retrospectives 
            WHERE user_id = :user_id 
            ORDER BY week_start_date DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':user_id' => $userId]);
    $retrospectives = $stmt->fetchAll();
    
    jsonResponse([
        'success' => true,
        'data' => $retrospectives
    ]);
}

// 振り返り作成
function createRetrospective() {
    $input = json_decode(file_get_contents('php://input'), true);
    
    // バリデーション
    $errors = [];
    
    if (empty($input['user_id'])) {
        $errors['user_id'] = 'ユーザーIDは必須です';
    }
    
    if (empty($input['week_start_date'])) {
        $errors['week_start_date'] = '週の開始日は必須です';
    }
    
    if (empty($input['week_end_date'])) {
        $errors['week_end_date'] = '週の終了日は必須です';
    }
    
    // 5つの評価観点のバリデーション
    $aspects = ['requirements', 'development', 'presentation', 'retrospective', 'other'];
    foreach ($aspects as $aspect) {
        $rating = $aspect . '_rating';
        $reason = $aspect . '_reason';
        
        if (empty($input[$rating]) || $input[$rating] < 1 || $input[$rating] > 5) {
            $errors[$rating] = '評価は1〜5の範囲で選択してください';
        }
        
        if (empty($input[$reason])) {
            $errors[$reason] = '理由は必須です';
        } elseif (mb_strlen($input[$reason]) > 1000) {
            $errors[$reason] = '理由は1000文字以内で入力してください';
        }
    }
    
    if (empty($input['future_outlook'])) {
        $errors['future_outlook'] = '今後の展望は必須です';
    } elseif (mb_strlen($input['future_outlook']) > 1000) {
        $errors['future_outlook'] = '展望は1000文字以内で入力してください';
    }
    
    if (!empty($errors)) {
        jsonResponse(['success' => false, 'errors' => $errors], 422);
    }
    
    $pdo = getDb();
    
    try {
        $pdo->beginTransaction();
        
        // 振り返り作成
        $sql = "INSERT INTO retrospectives (
                    user_id, week_start_date, week_end_date,
                    requirements_rating, requirements_reason,
                    development_rating, development_reason,
                    presentation_rating, presentation_reason,
                    retrospective_rating, retrospective_reason,
                    other_rating, other_reason,
                    future_outlook, status, submitted_at, created_at, updated_at
                ) VALUES (
                    :user_id, :week_start_date, :week_end_date,
                    :requirements_rating, :requirements_reason,
                    :development_rating, :development_reason,
                    :presentation_rating, :presentation_reason,
                    :retrospective_rating, :retrospective_reason,
                    :other_rating, :other_reason,
                    :future_outlook, 'submitted', NOW(), NOW(), NOW()
                ) RETURNING id";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':user_id' => $input['user_id'],
            ':week_start_date' => $input['week_start_date'],
            ':week_end_date' => $input['week_end_date'],
            ':requirements_rating' => $input['requirements_rating'],
            ':requirements_reason' => $input['requirements_reason'],
            ':development_rating' => $input['development_rating'],
            ':development_reason' => $input['development_reason'],
            ':presentation_rating' => $input['presentation_rating'],
            ':presentation_reason' => $input['presentation_reason'],
            ':retrospective_rating' => $input['retrospective_rating'],
            ':retrospective_reason' => $input['retrospective_reason'],
            ':other_rating' => $input['other_rating'],
            ':other_reason' => $input['other_reason'],
            ':future_outlook' => $input['future_outlook']
        ]);
        
        $result = $stmt->fetch();
        $retrospectiveId = $result['id'];
        
        // タスク関連付け（task_idsが提供されている場合のみ）
        if (!empty($input['task_ids']) && is_array($input['task_ids'])) {
            $taskSql = "INSERT INTO retrospective_tasks (retrospective_id, task_id, created_at) 
                        VALUES (:retrospective_id, :task_id, NOW())";
            $taskStmt = $pdo->prepare($taskSql);
            
            foreach ($input['task_ids'] as $taskId) {
                $taskStmt->execute([
                    ':retrospective_id' => $retrospectiveId,
                    ':task_id' => $taskId
                ]);
            }
        }
        
        $pdo->commit();
        
        jsonResponse([
            'success' => true,
            'message' => '振り返りを提出しました',
            'data' => ['id' => $retrospectiveId]
        ], 201);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        jsonResponse(['success' => false, 'message' => 'エラーが発生しました: ' . $e->getMessage()], 500);
    }
}

// 振り返り詳細取得
function getRetrospective() {
    $id = $_GET['id'] ?? null;
    
    if (!$id) {
        jsonResponse(['success' => false, 'message' => 'id is required'], 422);
    }
    
    $pdo = getDb();
    
    // 振り返り取得
    $sql = "SELECT * FROM retrospectives WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $id]);
    $retrospective = $stmt->fetch();
    
    if (!$retrospective) {
        jsonResponse(['success' => false, 'message' => 'Retrospective not found'], 404);
    }
    
    // 関連タスク取得
    $taskSql = "SELECT t.* FROM tasks t
                INNER JOIN retrospective_tasks rt ON t.id = rt.task_id
                WHERE rt.retrospective_id = :id";
    $taskStmt = $pdo->prepare($taskSql);
    $taskStmt->execute([':id' => $id]);
    $tasks = $taskStmt->fetchAll();
    
    $retrospective['tasks'] = $tasks;
    
    jsonResponse([
        'success' => true,
        'data' => $retrospective
    ]);
}

// 今週の振り返り取得
function getCurrentWeekRetrospective() {
    $userId = $_GET['user_id'] ?? null;
    
    if (!$userId) {
        jsonResponse(['success' => false, 'message' => 'user_id is required'], 422);
    }
    
    $pdo = getDb();
    
    // 今週の月曜日と日曜日を取得
    $monday = date('Y-m-d', strtotime('monday this week'));
    $sunday = date('Y-m-d', strtotime('sunday this week'));
    
    $sql = "SELECT * FROM retrospectives 
            WHERE user_id = :user_id 
            AND week_start_date = :monday";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':user_id' => $userId,
        ':monday' => $monday
    ]);
    $retrospective = $stmt->fetch();
    
    jsonResponse([
        'success' => true,
        'data' => $retrospective ?: null,
        'current_week' => [
            'start' => $monday,
            'end' => $sunday
        ]
    ]);
}
