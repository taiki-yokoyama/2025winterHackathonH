# Good&More API (Pure PHP版)

## 概要
Laravel不要のシンプルなPure PHP実装のGood&More機能です。

## 特徴
- ✅ Laravel不要（Pure PHP）
- ✅ PostgreSQL対応
- ✅ REST API
- ✅ フロントエンドHTML付き

## セットアップ

### 1. データベース初期化
Dockerコンテナを再起動してテーブルを作成：

```bash
docker-compose down
docker-compose up -d
```

### 2. 動作確認

#### API接続テスト
```bash
curl http://localhost/good-more-api.php/test
```

#### フロントエンド
ブラウザで以下にアクセス：
```
http://localhost/good-more.html
```

## API エンドポイント

### 基本情報
```
GET http://localhost/good-more-api.php
```

### データベース接続テスト
```
GET http://localhost/good-more-api.php/test
```

### Good&More送信
```
POST http://localhost/good-more-api.php/send
Content-Type: application/json

{
  "sender_id": 1,
  "receiver_id": 2,
  "good_message": "プロジェクトの進行管理が素晴らしかったです！",
  "more_message": "次回は事前の情報共有をもう少し早めにお願いします。"
}
```

### 送信履歴取得
```
GET http://localhost/good-more-api.php/sent?sender_id=1&page=1&per_page=20
```

**レスポンス例:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "sender_id": 1,
      "receiver_id": 2,
      "receiver_name": "佐藤花子",
      "receiver_email": "sato@example.com",
      "good_message": "プロジェクトの進行管理が素晴らしかったです！",
      "more_message": "次回は事前の情報共有をもう少し早めにお願いします。",
      "status": "sent",
      "reaction_count": 0,
      "created_at": "2024-01-01 10:00:00"
    }
  ],
  "pagination": {
    "current_page": 1,
    "per_page": 20,
    "total": 3,
    "last_page": 1
  }
}
```

### 受信履歴取得
```
GET http://localhost/good-more-api.php/received?receiver_id=2&page=1&per_page=20
```

### 詳細取得
```
GET http://localhost/good-more-api.php/detail?id=1
```

**レスポンス例:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "sender_id": 1,
    "sender_name": "山田太郎",
    "sender_email": "yamada@example.com",
    "receiver_id": 2,
    "receiver_name": "佐藤花子",
    "receiver_email": "sato@example.com",
    "good_message": "プロジェクトの進行管理が素晴らしかったです！",
    "more_message": "次回は事前の情報共有をもう少し早めにお願いします。",
    "status": "sent",
    "created_at": "2024-01-01 10:00:00",
    "reactions": []
  }
}
```

### リアクション追加
```
POST http://localhost/good-more-api.php/reaction
Content-Type: application/json

{
  "good_more_id": 1,
  "user_id": 2,
  "reaction_type": "emoji",
  "reaction_content": "👍"
}
```

**リアクションタイプ:**
- `emoji`: 絵文字
- `comment`: コメント
- `like`: いいね

### リアクション削除
```
DELETE http://localhost/good-more-api.php/reaction?id=1
```

## データベーススキーマ

### users テーブル
```sql
CREATE TABLE users (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

### good_mores テーブル
```sql
CREATE TABLE good_mores (
    id SERIAL PRIMARY KEY,
    sender_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    receiver_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    good_message TEXT NOT NULL,
    more_message TEXT NOT NULL,
    status VARCHAR(50) DEFAULT 'sent',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

### good_more_reactions テーブル
```sql
CREATE TABLE good_more_reactions (
    id SERIAL PRIMARY KEY,
    good_more_id INTEGER NOT NULL REFERENCES good_mores(id) ON DELETE CASCADE,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    reaction_type VARCHAR(50) NOT NULL,
    reaction_content TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(good_more_id, user_id)
);
```

## サンプルデータ

初期化時に以下のユーザーが作成されます：

| ID | 名前 | メール |
|----|------|--------|
| 1 | 山田太郎 | yamada@example.com |
| 2 | 佐藤花子 | sato@example.com |
| 3 | 鈴木一郎 | suzuki@example.com |

## 使用例（curl）

### Good&More送信
```bash
curl -X POST http://localhost/good-more-api.php/send \
  -H "Content-Type: application/json" \
  -d '{
    "sender_id": 1,
    "receiver_id": 2,
    "good_message": "素晴らしい仕事でした！",
    "more_message": "次回はもう少し早めに報告をお願いします。"
  }'
```

### 送信履歴取得
```bash
curl "http://localhost/good-more-api.php/sent?sender_id=1&page=1&per_page=10"
```

### リアクション追加
```bash
curl -X POST http://localhost/good-more-api.php/reaction \
  -H "Content-Type: application/json" \
  -d '{
    "good_more_id": 1,
    "user_id": 2,
    "reaction_type": "emoji",
    "reaction_content": "👍"
  }'
```

## トラブルシューティング

### データベース接続エラー
```bash
# PostgreSQLコンテナの状態確認
docker ps | grep postgresql

# ログ確認
docker logs 2025winterhackathonh-postgresql-1
```

### テーブルが存在しない
```bash
# コンテナを再起動してテーブルを再作成
docker-compose down
docker-compose up -d
```

### 接続テスト
```bash
curl http://localhost/good-more-api.php/test
```

## ファイル構成

```
api/web/public/
├── good-more-api.php      # REST API（Pure PHP）
└── good-more.html         # フロントエンド

docker/postgresql/initdb.d/
└── 01_create_tables.sql   # テーブル作成SQL
```

## ステータス

| ステータス | 説明 |
|----------|------|
| sent | 送信済み（未読） |
| read | 既読 |
| reacted | リアクション済み |

## 注意事項

- 認証機能は未実装（必要に応じて追加してください）
- 本番環境では適切なセキュリティ対策を実施してください
- SQLインジェクション対策としてPrepared Statementを使用しています
