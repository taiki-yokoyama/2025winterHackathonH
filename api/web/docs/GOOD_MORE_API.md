# Good&More API ドキュメント

## 概要
Good&More機能は、ユーザー間で感謝のメッセージ（Good）と改善提案（More）を送信できる機能です。

## 機能一覧
- Good&Moreの送信
- 送信履歴の表示
- 受信履歴の表示
- リアクション機能
- 既読管理

## API エンドポイント

### 1. Good&Moreを送信
```
POST /api/good-more/send
```

**リクエスト**
```json
{
  "receiver_id": 2,
  "good_message": "プロジェクトの進行管理が素晴らしかったです！",
  "more_message": "次回は事前の情報共有をもう少し早めにお願いします。"
}
```

**レスポンス**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "sender_id": 1,
    "receiver_id": 2,
    "good_message": "プロジェクトの進行管理が素晴らしかったです！",
    "more_message": "次回は事前の情報共有をもう少し早めにお願いします。",
    "status": "sent",
    "created_at": "2024-01-01T10:00:00.000000Z",
    "sender": {
      "id": 1,
      "name": "山田太郎"
    },
    "receiver": {
      "id": 2,
      "name": "佐藤花子"
    }
  }
}
```

### 2. 送信履歴を取得
```
GET /api/good-more/sent?page=1&per_page=20
```

**レスポンス**
```json
{
  "success": true,
  "data": {
    "current_page": 1,
    "data": [
      {
        "id": 1,
        "sender_id": 1,
        "receiver_id": 2,
        "good_message": "プロジェクトの進行管理が素晴らしかったです！",
        "more_message": "次回は事前の情報共有をもう少し早めにお願いします。",
        "status": "reacted",
        "created_at": "2024-01-01T10:00:00.000000Z",
        "receiver": {
          "id": 2,
          "name": "佐藤花子"
        },
        "reactions": [
          {
            "id": 1,
            "reaction_type": "emoji",
            "reaction_content": "👍",
            "user": {
              "id": 2,
              "name": "佐藤花子"
            }
          }
        ]
      }
    ],
    "last_page": 5,
    "per_page": 20,
    "total": 95
  }
}
```

### 3. 受信履歴を取得
```
GET /api/good-more/received?page=1&per_page=20
```

**レスポンス形式は送信履歴と同様**

### 4. Good&More詳細を取得
```
GET /api/good-more/{id}
```

**レスポンス**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "sender_id": 1,
    "receiver_id": 2,
    "good_message": "プロジェクトの進行管理が素晴らしかったです！",
    "more_message": "次回は事前の情報共有をもう少し早めにお願いします。",
    "status": "read",
    "created_at": "2024-01-01T10:00:00.000000Z",
    "sender": {
      "id": 1,
      "name": "山田太郎"
    },
    "receiver": {
      "id": 2,
      "name": "佐藤花子"
    },
    "reactions": []
  }
}
```

### 5. リアクションを追加
```
POST /api/good-more/{id}/reaction
```

**リクエスト**
```json
{
  "reaction_type": "emoji",
  "reaction_content": "👍"
}
```

**リアクションタイプ**
- `emoji`: 絵文字リアクション
- `comment`: コメント
- `like`: いいね

**レスポンス**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "good_more_id": 1,
    "user_id": 2,
    "reaction_type": "emoji",
    "reaction_content": "👍",
    "created_at": "2024-01-01T10:30:00.000000Z",
    "user": {
      "id": 2,
      "name": "佐藤花子"
    }
  }
}
```

### 6. リアクションを削除
```
DELETE /api/good-more/{id}/reaction
```

**レスポンス**
```json
{
  "success": true,
  "message": "リアクションを削除しました"
}
```

## ステータス

| ステータス | 説明 |
|----------|------|
| sent | 送信済み（未読） |
| read | 既読 |
| reacted | リアクション済み |

## 認証

すべてのエンドポイントは Laravel Sanctum による認証が必要です。

```
Authorization: Bearer {token}
```

## エラーレスポンス

```json
{
  "success": false,
  "errors": {
    "receiver_id": ["受信者IDは必須です"],
    "good_message": ["Goodメッセージは必須です"]
  }
}
```

## データベース設定

マイグレーションを実行してテーブルを作成してください：

```bash
php artisan migrate
```

## 使用例（JavaScript）

```javascript
import { getSentHistory, addReaction } from './services/goodMoreService';

// 送信履歴を取得
const history = await getSentHistory(1, 20);

// リアクションを追加
const reaction = await addReaction(1, 'emoji', '👍');
```
