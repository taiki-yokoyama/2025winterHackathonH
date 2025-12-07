<?php
/**
 * 認証API (Pure PHP)
 * ログイン・新規登録・パスワードリセット機能
 */

session_start();

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Credentials: true');

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
            echo json_encode(['success' => false, 'message' => 'Database connection failed']);
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

// セッションIDを生成
function generateSessionId() {
    return bin2hex(random_bytes(32));
}

// トークン生成
function generateToken() {
    return bin2hex(random_bytes(32));
}

// メール送信（ダミー実装）
function sendEmail($to, $subject, $body) {
    // 本番環境では実際のメール送信処理を実装
    error_log("Email to {$to}: {$subject}\n{$body}");
    return true;
}

// ルーティング
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

switch ($uri) {
    case '/auth-api.php':
    case '/auth-api.php/':
        if ($method === 'GET') {
            apiInfo();
        }
        break;
    
    case '/auth-api.php/register':
        if ($method === 'POST') {
            register();
        }
        break;
    
    case '/auth-api.php/login':
        if ($method === 'POST') {
            login();
        }
        break;
    
    case '/auth-api.php/logout':
        if ($method === 'POST') {
            logout();
        }
        break;
    
    case '/auth-api.php/me':
        if ($method === 'GET') {
            getCurrentUser();
        }
        break;
    
    case '/auth-api.php/users':
        if ($method === 'GET') {
            getUsers();
        }
        break;
    
    case '/auth-api.php/verify-email':
        if ($method === 'POST') {
            verifyEmail();
        }
        break;
    
    case '/auth-api.php/resend-verification':
        if ($method === 'POST') {
            resendVerification();
        }
        break;
    
    case '/auth-api.php/forgot-password':
        if ($method === 'POST') {
            forgotPassword();
        }
        break;
    
    case '/auth-api.php/reset-password':
        if ($method === 'POST') {
            resetPassword();
        }
        break;
    
    case '/auth-api.php/check-session':
        if ($method === 'GET') {
            checkSession();
        }
        break;
    
    default:
        jsonResponse(['success' => false, 'message' => 'Route not found'], 404);
}

// API情報
function apiInfo() {
    jsonResponse([
        'success' => true,
        'name' => '認証API',
        'version' => '1.0.0',
        'endpoints' => [
            'POST /auth-api.php/register' => '新規登録',
            'POST /auth-api.php/login' => 'ログイン',
            'POST /auth-api.php/logout' => 'ログアウト',
            'GET /auth-api.php/me' => '現在のユーザー情報取得',
            'GET /auth-api.php/users' => 'ユーザー一覧取得',
            'POST /auth-api.php/verify-email' => 'メール確認',
            'POST /auth-api.php/resend-verification' => '確認メール再送信',
            'POST /auth-api.php/forgot-password' => 'パスワードリセット要求',
            'POST /auth-api.php/reset-password' => 'パスワードリセット実行',
            'GET /auth-api.php/check-session' => 'セッション確認',
        ]
    ]);
}

// 新規登録
function register() {
    $input = json_decode(file_get_contents('php://input'), true);
    
    // バリデーション
    $errors = [];
    
    if (empty($input['name'])) {
        $errors['name'] = '名前は必須です';
    } elseif (strlen($input['name']) < 2) {
        $errors['name'] = '名前は2文字以上で入力してください';
    } elseif (strlen($input['name']) > 255) {
        $errors['name'] = '名前は255文字以内で入力してください';
    }
    
    if (empty($input['email'])) {
        $errors['email'] = 'メールアドレスは必須です';
    } elseif (!filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = '有効なメールアドレスを入力してください';
    }
    
    if (empty($input['team_name'])) {
        $errors['team_name'] = 'チームは必須です';
    } elseif (!in_array($input['team_name'], ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'])) {
        $errors['team_name'] = '有効なチームを選択してください';
    }
    
    if (empty($input['password'])) {
        $errors['password'] = 'パスワードは必須です';
    } elseif (strlen($input['password']) < 8) {
        $errors['password'] = 'パスワードは8文字以上で入力してください';
    }
    
    if (empty($input['password_confirmation'])) {
        $errors['password_confirmation'] = 'パスワード確認は必須です';
    } elseif ($input['password'] !== $input['password_confirmation']) {
        $errors['password_confirmation'] = 'パスワードが一致しません';
    }
    
    if (!empty($errors)) {
        jsonResponse(['success' => false, 'errors' => $errors], 422);
    }
    
    $pdo = getDb();
    
    // メールアドレス重複チェック
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email");
    $stmt->execute([':email' => $input['email']]);
    if ($stmt->fetch()) {
        jsonResponse(['success' => false, 'errors' => ['email' => 'このメールアドレスは既に登録されています']], 422);
    }
    
    try {
        $pdo->beginTransaction();
        
        // ユーザー作成（メール確認済みで登録）
        $hashedPassword = password_hash($input['password'], PASSWORD_DEFAULT);
        
        $sql = "INSERT INTO users (name, email, password, team_name, email_verified, created_at, updated_at) 
                VALUES (:name, :email, :password, :team_name, TRUE, NOW(), NOW()) 
                RETURNING id, name, email, team_name, email_verified";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':name' => $input['name'],
            ':email' => $input['email'],
            ':password' => $hashedPassword,
            ':team_name' => $input['team_name']
        ]);
        
        $user = $stmt->fetch();
        
        // チームIDを取得（チームA=1, チームB=2, ...）
        $teamMap = ['A' => 1, 'B' => 2, 'C' => 3, 'D' => 4, 'E' => 5, 'F' => 6, 'G' => 7, 'H' => 8];
        $teamId = $teamMap[$input['team_name']];
        
        // チームメンバーに追加
        $stmt = $pdo->prepare("INSERT INTO team_members (team_id, user_id, role, created_at) 
                               VALUES (:team_id, :user_id, 'member', NOW())");
        $stmt->execute([
            ':team_id' => $teamId,
            ':user_id' => $user['id']
        ]);
        
        $pdo->commit();
        
        jsonResponse([
            'success' => true,
            'message' => '登録が完了しました。ログインしてください。',
            'user' => $user,
            'verification_required' => false
        ], 201);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        jsonResponse(['success' => false, 'message' => '登録に失敗しました'], 500);
    }
}

// ログイン
function login() {
    $input = json_decode(file_get_contents('php://input'), true);
    
    // バリデーション
    if (empty($input['email']) || empty($input['password'])) {
        jsonResponse(['success' => false, 'message' => 'メールアドレスとパスワードを入力してください'], 422);
    }
    
    $pdo = getDb();
    
    // ユーザー取得
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email");
    $stmt->execute([':email' => $input['email']]);
    $user = $stmt->fetch();
    
    if (!$user || !password_verify($input['password'], $user['password'])) {
        jsonResponse(['success' => false, 'message' => 'メールアドレスまたはパスワードが正しくありません'], 401);
    }
    
    // セッション作成
    $sessionId = generateSessionId();
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['session_id'] = $sessionId;
    
    // セッションをDBに保存
    $stmt = $pdo->prepare("INSERT INTO sessions (id, user_id, ip_address, user_agent, last_activity, created_at) 
                           VALUES (:id, :user_id, :ip, :user_agent, NOW(), NOW())");
    $stmt->execute([
        ':id' => $sessionId,
        ':user_id' => $user['id'],
        ':ip' => $_SERVER['REMOTE_ADDR'] ?? null,
        ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
    ]);
    
    // パスワードを除外
    unset($user['password']);
    
    jsonResponse([
        'success' => true,
        'message' => 'ログインしました',
        'user' => $user,
        'session_id' => $sessionId,
        'redirect_url' => $input['redirect_url'] ?? '/index.html'
    ]);
}

// ログアウト
function logout() {
    $pdo = getDb();
    
    if (isset($_SESSION['session_id'])) {
        // セッション削除
        $stmt = $pdo->prepare("DELETE FROM sessions WHERE id = :id");
        $stmt->execute([':id' => $_SESSION['session_id']]);
    }
    
    session_destroy();
    
    jsonResponse([
        'success' => true,
        'message' => 'ログアウトしました'
    ]);
}

// 現在のユーザー情報取得
function getCurrentUser() {
    if (!isset($_SESSION['user_id'])) {
        jsonResponse(['success' => false, 'message' => '認証されていません'], 401);
    }
    
    $pdo = getDb();
    
    $stmt = $pdo->prepare("SELECT id, name, email, team_name, email_verified, created_at FROM users WHERE id = :id");
    $stmt->execute([':id' => $_SESSION['user_id']]);
    $user = $stmt->fetch();
    
    if (!$user) {
        jsonResponse(['success' => false, 'message' => 'ユーザーが見つかりません'], 404);
    }
    
    jsonResponse([
        'success' => true,
        'data' => $user
    ]);
}

// ユーザー一覧取得
function getUsers() {
    $pdo = getDb();
    
    $stmt = $pdo->prepare("SELECT id, name, email, team_name, email_verified, created_at FROM users ORDER BY name ASC");
    $stmt->execute();
    $users = $stmt->fetchAll();
    
    jsonResponse([
        'success' => true,
        'data' => $users
    ]);
}

// メール確認
function verifyEmail() {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (empty($input['token'])) {
        jsonResponse(['success' => false, 'message' => 'トークンが必要です'], 422);
    }
    
    $pdo = getDb();
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email_verification_token = :token");
    $stmt->execute([':token' => $input['token']]);
    $user = $stmt->fetch();
    
    if (!$user) {
        jsonResponse(['success' => false, 'message' => '無効なトークンです'], 404);
    }
    
    if ($user['email_verified']) {
        jsonResponse(['success' => false, 'message' => 'このメールアドレスは既に確認済みです'], 400);
    }
    
    if (strtotime($user['email_verification_expires_at']) < time()) {
        jsonResponse(['success' => false, 'message' => 'トークンの有効期限が切れています'], 400);
    }
    
    // メール確認済みに更新
    $stmt = $pdo->prepare("UPDATE users SET email_verified = TRUE, email_verification_token = NULL, 
                           email_verification_expires_at = NULL, updated_at = NOW() WHERE id = :id");
    $stmt->execute([':id' => $user['id']]);
    
    jsonResponse([
        'success' => true,
        'message' => 'メールアドレスが確認されました'
    ]);
}

// 確認メール再送信
function resendVerification() {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (empty($input['email'])) {
        jsonResponse(['success' => false, 'message' => 'メールアドレスが必要です'], 422);
    }
    
    $pdo = getDb();
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email");
    $stmt->execute([':email' => $input['email']]);
    $user = $stmt->fetch();
    
    if (!$user) {
        jsonResponse(['success' => false, 'message' => 'ユーザーが見つかりません'], 404);
    }
    
    if ($user['email_verified']) {
        jsonResponse(['success' => false, 'message' => 'このメールアドレスは既に確認済みです'], 400);
    }
    
    // 新しいトークン生成
    $verificationToken = generateToken();
    $expiresAt = date('Y-m-d H:i:s', strtotime('+24 hours'));
    
    $stmt = $pdo->prepare("UPDATE users SET email_verification_token = :token, 
                           email_verification_expires_at = :expires_at, updated_at = NOW() WHERE id = :id");
    $stmt->execute([
        ':token' => $verificationToken,
        ':expires_at' => $expiresAt,
        ':id' => $user['id']
    ]);
    
    // 確認メール送信
    $verificationUrl = "http://localhost/auth.html?verify=" . $verificationToken;
    sendEmail(
        $user['email'],
        'メールアドレスの確認',
        "以下のリンクをクリックしてメールアドレスを確認してください:\n{$verificationUrl}"
    );
    
    jsonResponse([
        'success' => true,
        'message' => '確認メールを再送信しました'
    ]);
}

// パスワードリセット要求
function forgotPassword() {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (empty($input['email'])) {
        jsonResponse(['success' => false, 'message' => 'メールアドレスが必要です'], 422);
    }
    
    $pdo = getDb();
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email");
    $stmt->execute([':email' => $input['email']]);
    $user = $stmt->fetch();
    
    if (!$user) {
        // セキュリティのため、ユーザーが存在しなくても成功レスポンスを返す
        jsonResponse([
            'success' => true,
            'message' => 'パスワードリセットメールを送信しました'
        ]);
    }
    
    // トークン生成
    $token = generateToken();
    $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));
    
    // 既存のトークンを削除
    $stmt = $pdo->prepare("DELETE FROM password_resets WHERE email = :email");
    $stmt->execute([':email' => $input['email']]);
    
    // 新しいトークンを保存
    $stmt = $pdo->prepare("INSERT INTO password_resets (email, token, expires_at, created_at) 
                           VALUES (:email, :token, :expires_at, NOW())");
    $stmt->execute([
        ':email' => $input['email'],
        ':token' => $token,
        ':expires_at' => $expiresAt
    ]);
    
    // リセットメール送信
    $resetUrl = "http://localhost/auth.html?reset=" . $token;
    sendEmail(
        $input['email'],
        'パスワードリセット',
        "以下のリンクをクリックしてパスワードをリセットしてください:\n{$resetUrl}\n\nこのリンクは1時間有効です。"
    );
    
    jsonResponse([
        'success' => true,
        'message' => 'パスワードリセットメールを送信しました'
    ]);
}

// パスワードリセット実行
function resetPassword() {
    $input = json_decode(file_get_contents('php://input'), true);
    
    // バリデーション
    $errors = [];
    
    if (empty($input['token'])) {
        $errors['token'] = 'トークンが必要です';
    }
    
    if (empty($input['password'])) {
        $errors['password'] = 'パスワードは必須です';
    } elseif (strlen($input['password']) < 8) {
        $errors['password'] = 'パスワードは8文字以上で入力してください';
    }
    
    if (empty($input['password_confirmation'])) {
        $errors['password_confirmation'] = 'パスワード確認は必須です';
    } elseif ($input['password'] !== $input['password_confirmation']) {
        $errors['password_confirmation'] = 'パスワードが一致しません';
    }
    
    if (!empty($errors)) {
        jsonResponse(['success' => false, 'errors' => $errors], 422);
    }
    
    $pdo = getDb();
    
    // トークン確認
    $stmt = $pdo->prepare("SELECT * FROM password_resets WHERE token = :token");
    $stmt->execute([':token' => $input['token']]);
    $reset = $stmt->fetch();
    
    if (!$reset) {
        jsonResponse(['success' => false, 'message' => '無効なトークンです'], 404);
    }
    
    if (strtotime($reset['expires_at']) < time()) {
        jsonResponse(['success' => false, 'message' => 'トークンの有効期限が切れています'], 400);
    }
    
    // パスワード更新
    $hashedPassword = password_hash($input['password'], PASSWORD_DEFAULT);
    
    $stmt = $pdo->prepare("UPDATE users SET password = :password, updated_at = NOW() WHERE email = :email");
    $stmt->execute([
        ':password' => $hashedPassword,
        ':email' => $reset['email']
    ]);
    
    // トークン削除
    $stmt = $pdo->prepare("DELETE FROM password_resets WHERE token = :token");
    $stmt->execute([':token' => $input['token']]);
    
    jsonResponse([
        'success' => true,
        'message' => 'パスワードがリセットされました'
    ]);
}

// セッション確認
function checkSession() {
    if (!isset($_SESSION['user_id'])) {
        jsonResponse(['success' => false, 'authenticated' => false]);
    }
    
    $pdo = getDb();
    
    // セッション確認
    if (isset($_SESSION['session_id'])) {
        $stmt = $pdo->prepare("SELECT * FROM sessions WHERE id = :id AND user_id = :user_id");
        $stmt->execute([
            ':id' => $_SESSION['session_id'],
            ':user_id' => $_SESSION['user_id']
        ]);
        $session = $stmt->fetch();
        
        if (!$session) {
            session_destroy();
            jsonResponse(['success' => false, 'authenticated' => false]);
        }
        
        // 最終アクティビティ更新
        $stmt = $pdo->prepare("UPDATE sessions SET last_activity = NOW() WHERE id = :id");
        $stmt->execute([':id' => $_SESSION['session_id']]);
    }
    
    // ユーザー情報取得
    $stmt = $pdo->prepare("SELECT id, name, email, email_verified FROM users WHERE id = :id");
    $stmt->execute([':id' => $_SESSION['user_id']]);
    $user = $stmt->fetch();
    
    jsonResponse([
        'success' => true,
        'authenticated' => true,
        'user' => $user
    ]);
}
