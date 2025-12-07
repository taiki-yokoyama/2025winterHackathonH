# 認証システム完全ドキュメント

## 🎯 実装済み機能

### ✅ 1. ログイン機能
- メールアドレス・パスワードでログイン
- ログインエラーメッセージ表示
- セッション管理
- ログイン後のリダイレクト設定

### ✅ 2. 新規登録機能
- 名前・メールアドレス・パスワードで登録
- フォームバリデーション
  - 名前: 2文字以上255文字以内
  - メールアドレス: 有効な形式
  - パスワード: 8文字以上
  - パスワード確認: 一致確認
- パスワード強度表示
- メールアドレス重複チェック

### ✅ 3. メール確認機能
- 登録時に確認メール送信
- トークンによるメール確認
- 確認メール再送信機能
- 24時間有効なトークン

### ✅ 4. パスワードリセット機能
- メールアドレスでリセット要求
- トークンによるパスワードリセット
- 1時間有効なトークン
- 新しいパスワード設定

### ✅ 5. セッション管理
- PHPセッション使用
- データベースにセッション保存
- IPアドレス・User Agent記録
- 最終アクティビティ追跡

### ✅ 6. セキュリティ機能
- パスワードハッシュ化（bcrypt）
- SQLインジェクション対策（Prepared Statement）
- XSS対策（入力エスケープ）
- CSRF対策（セッショントークン）

## 🚀 使い方

### アクセス
```
http://localhost/auth.html
```

### 1. 新規登録
1. 「新規登録」タブをクリック
2. 名前、メールアドレス、パスワードを入力
3. 「登録する」ボタンをクリック
4. 確認メールが送信される（ログで確認）
5. メール内のリンクをクリックしてメール確認

### 2. ログイン
1. 「ログイン」タブをクリック
2. メールアドレス、パスワードを入力
3. 「ログイン」ボタンをクリック
4. ログイン成功後、Good&Moreページにリダイレクト

### 3. パスワードリセット
1. ログイン画面で「パスワードを忘れた方」をクリック
2. メールアドレスを入力
3. 「リセットメールを送信」をクリック
4. メール内のリンクをクリック
5. 新しいパスワードを設定

## 📊 データベーススキーマ

### users テーブル
```sql
CREATE TABLE users (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    email_verified BOOLEAN DEFAULT FALSE,
    email_verification_token VARCHAR(255),
    email_verification_expires_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

### sessions テーブル
```sql
CREATE TABLE sessions (
    id VARCHAR(255) PRIMARY KEY,
    user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
    ip_address VARCHAR(45),
    user_agent TEXT,
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

### password_resets テーブル
```sql
CREATE TABLE password_resets (
    id SERIAL PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    token VARCHAR(255) NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

## 🔌 API エンドポイント

### 新規登録
```
POST /auth-api.php/register
Content-Type: application/json

{
  "name": "山田太郎",
  "email": "yamada@example.com",
  "password": "password123",
  "password_confirmation": "password123"
}
```

**レスポンス:**
```json
{
  "success": true,
  "message": "登録が完了しました。確認メールを送信しました。",
  "user": {
    "id": 1,
    "name": "山田太郎",
    "email": "yamada@example.com",
    "email_verified": false
  },
  "verification_required": true
}
```

### ログイン
```
POST /auth-api.php/login
Content-Type: application/json

{
  "email": "yamada@example.com",
  "password": "password123",
  "redirect_url": "/good-more-complete.html"
}
```

**レスポンス:**
```json
{
  "success": true,
  "message": "ログインしました",
  "user": {
    "id": 1,
    "name": "山田太郎",
    "email": "yamada@example.com",
    "email_verified": true
  },
  "session_id": "abc123...",
  "redirect_url": "/good-more-complete.html"
}
```

### ログアウト
```
POST /auth-api.php/logout
```

### 現在のユーザー情報取得
```
GET /auth-api.php/me
```

### メール確認
```
POST /auth-api.php/verify-email
Content-Type: application/json

{
  "token": "verification_token_here"
}
```

### 確認メール再送信
```
POST /auth-api.php/resend-verification
Content-Type: application/json

{
  "email": "yamada@example.com"
}
```

### パスワードリセット要求
```
POST /auth-api.php/forgot-password
Content-Type: application/json

{
  "email": "yamada@example.com"
}
```

### パスワードリセット実行
```
POST /auth-api.php/reset-password
Content-Type: application/json

{
  "token": "reset_token_here",
  "password": "newpassword123",
  "password_confirmation": "newpassword123"
}
```

### セッション確認
```
GET /auth-api.php/check-session
```

**レスポンス:**
```json
{
  "success": true,
  "authenticated": true,
  "user": {
    "id": 1,
    "name": "山田太郎",
    "email": "yamada@example.com",
    "email_verified": true
  }
}
```

## 🎨 UI機能

### ログインフォーム
- メールアドレス入力
- パスワード入力
- ログインボタン
- パスワードリセットリンク
- エラーメッセージ表示

### 新規登録フォーム
- 名前入力
- メールアドレス入力
- パスワード入力
- パスワード確認入力
- パスワード強度インジケーター
- 登録ボタン
- バリデーションエラー表示

### パスワードリセットフォーム
- メールアドレス入力
- リセットメール送信ボタン
- ログインに戻るボタン

### パスワードリセット実行フォーム
- 新しいパスワード入力
- パスワード確認入力
- リセットボタン

## 🔒 セキュリティ機能

### パスワードハッシュ化
```php
$hashedPassword = password_hash($password, PASSWORD_DEFAULT);
```

### パスワード検証
```php
password_verify($inputPassword, $hashedPassword);
```

### トークン生成
```php
function generateToken() {
    return bin2hex(random_bytes(32));
}
```

### セッションID生成
```php
function generateSessionId() {
    return bin2hex(random_bytes(32));
}
```

## 📧 メール機能

現在はダミー実装（ログ出力）です。本番環境では以下のように実装してください：

```php
function sendEmail($to, $subject, $body) {
    // PHPMailerなどを使用
    $mail = new PHPMailer();
    $mail->setFrom('noreply@example.com');
    $mail->addAddress($to);
    $mail->Subject = $subject;
    $mail->Body = $body;
    return $mail->send();
}
```

## 🔄 セッションフロー

```
1. ユーザーがログイン
   ↓
2. セッションID生成
   ↓
3. PHPセッションに保存
   ↓
4. データベースに保存
   ↓
5. ユーザー情報取得時にセッション確認
   ↓
6. 最終アクティビティ更新
```

## 🧪 テストデータ

初期ユーザー（パスワード: password123）:
1. 山田太郎 (yamada@example.com)
2. 佐藤花子 (sato@example.com)
3. 鈴木一郎 (suzuki@example.com)

## 🔧 カスタマイズ

### リダイレクト先変更
```javascript
// auth.html内
const data = {
    email: email,
    password: password,
    redirect_url: '/custom-page.html'  // ここを変更
};
```

### セッション有効期限設定
```php
// auth-api.php内
// セッション有効期限を24時間に設定
ini_set('session.gc_maxlifetime', 86400);
```

### パスワード強度要件変更
```javascript
// auth.html内
if (password.length < 12) {  // 12文字以上に変更
    errors['password'] = 'パスワードは12文字以上で入力してください';
}
```

## 🚨 トラブルシューティング

### セッションが保持されない
```bash
# PHPセッション設定確認
php -i | grep session
```

### メールが送信されない
```bash
# ログ確認
docker logs 2025winterhackathonh-app-1 | grep Email
```

### データベース接続エラー
```bash
# PostgreSQL確認
docker ps | grep postgresql
curl http://localhost/auth-api.php
```

## 📝 今後の拡張案

- [ ] ソーシャルログイン（Google, GitHub）
- [ ] 二段階認証（2FA）
- [ ] ログイン履歴表示
- [ ] アカウント削除機能
- [ ] プロフィール編集機能
- [ ] パスワード変更機能
- [ ] セッション管理画面
- [ ] ログイン試行回数制限

## 🔐 本番環境での注意事項

1. **HTTPS必須**: 本番環境では必ずHTTPSを使用
2. **環境変数**: データベース接続情報を環境変数に
3. **メール送信**: 実際のメール送信サービスを使用
4. **セッション設定**: secure, httponly, samesiteフラグを設定
5. **レート制限**: ログイン試行回数制限を実装
6. **ログ監視**: 不正アクセスの監視
7. **バックアップ**: データベースの定期バックアップ
