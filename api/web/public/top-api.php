<?php
/**
 * トップページAPI (Pure PHP)
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
    case '/top-api.php':
    case '/top-api.php/':
        if ($method === 'GET') {
            apiInfo();
        }
        break;
    
    case '/top-api.php/dashboard':
        if ($method === 'GET') {
            getDashboardData();
        }
        break;
    
    case '/top-api.php/team-evaluation':
        if ($method === 'POST') {
            submitTeamEvaluation();
        }
        break;
    
    case '/top-api.php/outlook-check':
        if ($method === 'PUT') {
            updateOutlookCheck();
        }
        break;
    
    case '/top-api.php/goal-evaluation':
        if ($method === 'PUT') {
            updateGoalEvaluation();
        }
        break;
    
    default:
        jsonResponse(['success' => false, 'message' => 'Route not found'], 404);
}

function apiInfo() {
    jsonResponse([
        'success' => true,
        'name' => 'トップページAPI',
        'version' => '1.0.0',
        'endpoints' => [
            'GET /top-api.php/dashboard?user_id={id}&team_id={id}' => 'ダッシュボードデータ取得',
            'POST /top-api.php/team-evaluation' => 'チーム振り返り評価送信',
            'PUT /top-api.php/outlook-check' => '展望チェック更新',
            'PUT /top-api.php/goal-evaluation' => '目標評価更新',
        ]
    ]);
}

// ダッシュボードデータ取得
function getDashboardData() {
    $userId = $_GET['user_id'] ?? null;
    $teamId = $_GET['team_id'] ?? null;
    
    if (!$userId || !$teamId) {
        jsonResponse(['success' => false, 'message' => 'user_id and team_id are required'], 422);
    }
    
    $pdo = getDb();
    
    // 今週の月曜日
    $monday = date('Y-m-d', strtotime('monday this week'));
    
    // 個人タスク達成率
    $personalTaskSql = "SELECT 
                            COUNT(*) as total,
                            COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed
                        FROM tasks 
                        WHERE assigned_to = :user_id";
    $stmt = $pdo->prepare($personalTaskSql);
    $stmt->execute([':user_id' => $userId]);
    $personalTask = $stmt->fetch();
    $personalTaskRate = $personalTask['total'] > 0 
        ? round(($personalTask['completed'] / $personalTask['total']) * 100, 1) 
        : 0;
    
    // チームタスク達成率
    $teamTaskSql = "SELECT 
                        COUNT(*) as total,
                        COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed
                    FROM tasks t
                    INNER JOIN team_members tm ON t.assigned_to = tm.user_id
                    WHERE tm.team_id = :team_id";
    $stmt = $pdo->prepare($teamTaskSql);
    $stmt->execute([':team_id' => $teamId]);
    $teamTask = $stmt->fetch();
    $teamTaskRate = $teamTask['total'] > 0 
        ? round(($teamTask['completed'] / $teamTask['total']) * 100, 1) 
        : 0;
    
    // 個人目標達成率
    $personalGoalSql = "SELECT 
                            COUNT(*) as total,
                            COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed
                        FROM goals 
                        WHERE user_id = :user_id";
    $stmt = $pdo->prepare($personalGoalSql);
    $stmt->execute([':user_id' => $userId]);
    $personalGoal = $stmt->fetch();
    $personalGoalRate = $personalGoal['total'] > 0 
        ? round(($personalGoal['completed'] / $personalGoal['total']) * 100, 1) 
        : 0;
    
    // チーム目標達成率
    $teamGoalSql = "SELECT 
                        COUNT(*) as total,
                        COUNT(CASE WHEN g.status = 'completed' THEN 1 END) as completed
                    FROM goals g
                    INNER JOIN team_members tm ON g.user_id = tm.user_id
                    WHERE tm.team_id = :team_id";
    $stmt = $pdo->prepare($teamGoalSql);
    $stmt->execute([':team_id' => $teamId]);
    $teamGoal = $stmt->fetch();
    $teamGoalRate = $teamGoal['total'] > 0 
        ? round(($teamGoal['completed'] / $teamGoal['total']) * 100, 1) 
        : 0;
    
    // チームメンバーの評価
    $evaluationsSql = "SELECT u.id, u.name, tre.evaluation_score
                       FROM users u
                       INNER JOIN team_members tm ON u.id = tm.user_id
                       LEFT JOIN team_retrospective_evaluations tre 
                           ON u.id = tre.user_id 
                           AND tre.week_start_date = :monday
                       WHERE tm.team_id = :team_id
                       ORDER BY u.id";
    $stmt = $pdo->prepare($evaluationsSql);
    $stmt->execute([':team_id' => $teamId, ':monday' => $monday]);
    $evaluations = $stmt->fetchAll();
    
    // 評価平均
    $scores = array_filter(array_column($evaluations, 'evaluation_score'));
    $avgScore = count($scores) > 0 ? round(array_sum($scores) / count($scores), 1) : null;
    
    // 前週の展望
    $lastMonday = date('Y-m-d', strtotime('monday last week'));
    $outlookSql = "SELECT r.id, r.future_outlook, oc.is_completed
                   FROM retrospectives r
                   LEFT JOIN outlook_checks oc ON r.id = oc.retrospective_id AND oc.user_id = :user_id
                   WHERE r.user_id = :user_id AND r.week_start_date = :last_monday";
    $stmt = $pdo->prepare($outlookSql);
    $stmt->execute([':user_id' => $userId, ':last_monday' => $lastMonday]);
    $outlook = $stmt->fetch();
    
    // 個人の目標一覧
    $goalsSql = "SELECT * FROM goals WHERE user_id = :user_id ORDER BY created_at DESC";
    $stmt = $pdo->prepare($goalsSql);
    $stmt->execute([':user_id' => $userId]);
    $goals = $stmt->fetchAll();
    
    jsonResponse([
        'success' => true,
        'data' => [
            'personal_task_rate' => $personalTaskRate,
            'team_task_rate' => $teamTaskRate,
            'personal_goal_rate' => $personalGoalRate,
            'team_goal_rate' => $teamGoalRate,
            'evaluations' => $evaluations,
            'average_score' => $avgScore,
            'last_week_outlook' => $outlook,
            'goals' => $goals,
            'current_week' => $monday
        ]
    ]);
}

// チーム振り返り評価送信
function submitTeamEvaluation() {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['user_id'], $input['team_id'], $input['evaluation_score'])) {
        jsonResponse(['success' => false, 'message' => 'Required fields: user_id, team_id, evaluation_score'], 422);
    }
    
    if ($input['evaluation_score'] < 1 || $input['evaluation_score'] > 5) {
        jsonResponse(['success' => false, 'message' => '評価は1〜5の範囲で入力してください'], 422);
    }
    
    $pdo = getDb();
    $monday = date('Y-m-d', strtotime('monday this week'));
    
    // 既存の評価を更新または新規作成
    $sql = "INSERT INTO team_retrospective_evaluations (user_id, team_id, week_start_date, evaluation_score, created_at, updated_at)
            VALUES (:user_id, :team_id, :week_start_date, :evaluation_score, NOW(), NOW())
            ON CONFLICT (user_id, team_id, week_start_date)
            DO UPDATE SET evaluation_score = :evaluation_score, updated_at = NOW()";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':user_id' => $input['user_id'],
        ':team_id' => $input['team_id'],
        ':week_start_date' => $monday,
        ':evaluation_score' => $input['evaluation_score']
    ]);
    
    jsonResponse([
        'success' => true,
        'message' => '評価を送信しました'
    ]);
}

// 展望チェック更新
function updateOutlookCheck() {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['user_id'], $input['retrospective_id'], $input['is_completed'])) {
        jsonResponse(['success' => false, 'message' => 'Required fields: user_id, retrospective_id, is_completed'], 422);
    }
    
    $pdo = getDb();
    
    $sql = "INSERT INTO outlook_checks (user_id, retrospective_id, is_completed, created_at, updated_at)
            VALUES (:user_id, :retrospective_id, :is_completed, NOW(), NOW())
            ON CONFLICT (user_id, retrospective_id)
            DO UPDATE SET is_completed = :is_completed, updated_at = NOW()";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':user_id' => $input['user_id'],
        ':retrospective_id' => $input['retrospective_id'],
        ':is_completed' => $input['is_completed'] ? 'true' : 'false'
    ]);
    
    jsonResponse([
        'success' => true,
        'message' => '展望チェックを更新しました'
    ]);
}

// 目標評価更新
function updateGoalEvaluation() {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['goal_id'], $input['evaluation'])) {
        jsonResponse(['success' => false, 'message' => 'Required fields: goal_id, evaluation'], 422);
    }
    
    if (!in_array($input['evaluation'], ['+', '-', null])) {
        jsonResponse(['success' => false, 'message' => '評価は+、-、またはnullである必要があります'], 422);
    }
    
    $pdo = getDb();
    
    $sql = "UPDATE goals SET evaluation = :evaluation, updated_at = NOW() WHERE id = :goal_id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':goal_id' => $input['goal_id'],
        ':evaluation' => $input['evaluation']
    ]);
    
    jsonResponse([
        'success' => true,
        'message' => '目標評価を更新しました'
    ]);
}
