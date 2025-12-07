<?php
/**
 * ダッシュボードAPI (Pure PHP)
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
    case '/dashboard-api.php':
    case '/dashboard-api.php/':
        if ($method === 'GET') {
            apiInfo();
        }
        break;
    
    case '/dashboard-api.php/stats':
        if ($method === 'GET') {
            getStats();
        }
        break;
    
    case '/dashboard-api.php/tasks':
        if ($method === 'GET') {
            getTasks();
        } elseif ($method === 'POST') {
            createTask();
        }
        break;
    
    case '/dashboard-api.php/task':
        if ($method === 'PUT') {
            updateTask();
        } elseif ($method === 'DELETE') {
            deleteTask();
        }
        break;
    
    case '/dashboard-api.php/team-members':
        if ($method === 'GET') {
            getTeamMembers();
        }
        break;
    
    default:
        jsonResponse(['success' => false, 'message' => 'Route not found'], 404);
}

function apiInfo() {
    jsonResponse([
        'success' => true,
        'name' => 'ダッシュボードAPI',
        'version' => '1.0.0',
        'endpoints' => [
            'GET /dashboard-api.php/stats?user_id={id}&team_id={id}' => '統計情報取得',
            'GET /dashboard-api.php/tasks?user_id={id}&team_id={id}' => 'タスク一覧取得',
            'POST /dashboard-api.php/tasks' => 'タスク作成',
            'PUT /dashboard-api.php/task' => 'タスク更新',
            'DELETE /dashboard-api.php/task?id={id}' => 'タスク削除',
            'GET /dashboard-api.php/team-members?team_id={id}' => 'チームメンバー取得',
        ]
    ]);
}

// 統計情報取得
function getStats() {
    $userId = $_GET['user_id'] ?? null;
    $teamId = $_GET['team_id'] ?? 1; // デフォルトチーム
    
    if (!$userId) {
        jsonResponse(['success' => false, 'message' => 'user_id is required'], 422);
    }
    
    $pdo = getDb();
    
    // 個人タスク達成率
    $personalSql = "SELECT 
                        COUNT(*) as total,
                        COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed
                    FROM tasks 
                    WHERE assigned_to = :user_id";
    $stmt = $pdo->prepare($personalSql);
    $stmt->execute([':user_id' => $userId]);
    $personal = $stmt->fetch();
    
    $personalRate = $personal['total'] > 0 
        ? round(($personal['completed'] / $personal['total']) * 100, 1) 
        : 0;
    
    // チーム全体のタスク達成率
    $teamSql = "SELECT 
                    COUNT(*) as total,
                    COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed
                FROM tasks t
                INNER JOIN team_members tm ON t.assigned_to = tm.user_id
                WHERE tm.team_id = :team_id";
    $stmt = $pdo->prepare($teamSql);
    $stmt->execute([':team_id' => $teamId]);
    $team = $stmt->fetch();
    
    $teamRate = $team['total'] > 0 
        ? round(($team['completed'] / $team['total']) * 100, 1) 
        : 0;
    
    // チームメンバーの達成率
    $membersSql = "SELECT 
                        u.id,
                        u.name,
                        COUNT(t.id) as total,
                        COUNT(CASE WHEN t.status = 'completed' THEN 1 END) as completed
                    FROM users u
                    INNER JOIN team_members tm ON u.id = tm.user_id
                    LEFT JOIN tasks t ON u.id = t.assigned_to
                    WHERE tm.team_id = :team_id
                    GROUP BY u.id, u.name
                    ORDER BY u.id";
    $stmt = $pdo->prepare($membersSql);
    $stmt->execute([':team_id' => $teamId]);
    $members = $stmt->fetchAll();
    
    foreach ($members as &$member) {
        $member['rate'] = $member['total'] > 0 
            ? round(($member['completed'] / $member['total']) * 100, 1) 
            : 0;
    }
    
    jsonResponse([
        'success' => true,
        'data' => [
            'personal' => [
                'total' => (int)$personal['total'],
                'completed' => (int)$personal['completed'],
                'rate' => $personalRate
            ],
            'team' => [
                'total' => (int)$team['total'],
                'completed' => (int)$team['completed'],
                'rate' => $teamRate
            ],
            'members' => $members
        ]
    ]);
}

// タスク一覧取得
function getTasks() {
    $userId = $_GET['user_id'] ?? null;
    $teamId = $_GET['team_id'] ?? 1;
    
    $pdo = getDb();
    
    if ($userId) {
        // 個人のタスク
        $sql = "SELECT t.*, 
                       u_assigned.name as assigned_name,
                       u_creator.name as creator_name
                FROM tasks t
                LEFT JOIN users u_assigned ON t.assigned_to = u_assigned.id
                LEFT JOIN users u_creator ON t.user_id = u_creator.id
                WHERE t.assigned_to = :user_id
                ORDER BY t.due_date ASC, t.created_at DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':user_id' => $userId]);
    } else {
        // チーム全体のタスク
        $sql = "SELECT t.*, 
                       u_assigned.name as assigned_name,
                       u_creator.name as creator_name
                FROM tasks t
                INNER JOIN team_members tm ON t.assigned_to = tm.user_id
                LEFT JOIN users u_assigned ON t.assigned_to = u_assigned.id
                LEFT JOIN users u_creator ON t.user_id = u_creator.id
                WHERE tm.team_id = :team_id
                ORDER BY t.due_date ASC, t.created_at DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':team_id' => $teamId]);
    }
    
    $tasks = $stmt->fetchAll();
    
    // 期限切れチェック
    $today = date('Y-m-d');
    foreach ($tasks as &$task) {
        $task['is_overdue'] = $task['due_date'] && $task['due_date'] < $today && $task['status'] !== 'completed';
    }
    
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
    
    // 期日が指定されていない場合は1週間後
    $dueDate = $input['due_date'] ?? date('Y-m-d', strtotime('+7 days'));
    
    $sql = "INSERT INTO tasks (user_id, assigned_to, title, description, status, due_date, created_at, updated_at) 
            VALUES (:user_id, :assigned_to, :title, :description, :status, :due_date, NOW(), NOW()) 
            RETURNING id, created_at";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':user_id' => $input['user_id'],
        ':assigned_to' => $input['assigned_to'] ?? $input['user_id'],
        ':title' => $input['title'],
        ':description' => $input['description'] ?? null,
        ':status' => $input['status'] ?? 'pending',
        ':due_date' => $dueDate
    ]);
    
    $result = $stmt->fetch();
    
    jsonResponse([
        'success' => true,
        'message' => 'タスクを作成しました',
        'data' => [
            'id' => $result['id'],
            'created_at' => $result['created_at']
        ]
    ], 201);
}

// タスク更新
function updateTask() {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['id'])) {
        jsonResponse(['success' => false, 'message' => 'id is required'], 422);
    }
    
    $pdo = getDb();
    
    $updates = [];
    $params = [':id' => $input['id']];
    
    if (isset($input['title'])) {
        $updates[] = 'title = :title';
        $params[':title'] = $input['title'];
    }
    
    if (isset($input['description'])) {
        $updates[] = 'description = :description';
        $params[':description'] = $input['description'];
    }
    
    if (isset($input['status'])) {
        $updates[] = 'status = :status';
        $params[':status'] = $input['status'];
        
        // 完了時に完了日時を設定
        if ($input['status'] === 'completed') {
            $updates[] = 'completed_at = NOW()';
        }
    }
    
    if (isset($input['assigned_to'])) {
        $updates[] = 'assigned_to = :assigned_to';
        $params[':assigned_to'] = $input['assigned_to'];
    }
    
    if (isset($input['due_date'])) {
        $updates[] = 'due_date = :due_date';
        $params[':due_date'] = $input['due_date'];
    }
    
    if (empty($updates)) {
        jsonResponse(['success' => false, 'message' => 'No fields to update'], 422);
    }
    
    $updates[] = 'updated_at = NOW()';
    
    $sql = "UPDATE tasks SET " . implode(', ', $updates) . " WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    jsonResponse([
        'success' => true,
        'message' => 'タスクを更新しました'
    ]);
}

// タスク削除
function deleteTask() {
    $id = $_GET['id'] ?? null;
    
    if (!$id) {
        jsonResponse(['success' => false, 'message' => 'id is required'], 422);
    }
    
    $pdo = getDb();
    
    $sql = "DELETE FROM tasks WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $id]);
    
    jsonResponse([
        'success' => true,
        'message' => 'タスクを削除しました'
    ]);
}

// チームメンバー取得
function getTeamMembers() {
    $teamId = $_GET['team_id'] ?? 1;
    
    $pdo = getDb();
    
    $sql = "SELECT u.id, u.name, u.email, tm.role
            FROM users u
            INNER JOIN team_members tm ON u.id = tm.user_id
            WHERE tm.team_id = :team_id
            ORDER BY u.id";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':team_id' => $teamId]);
    $members = $stmt->fetchAll();
    
    jsonResponse([
        'success' => true,
        'data' => $members
    ]);
}
