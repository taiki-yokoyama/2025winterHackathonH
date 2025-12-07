# Good&More 完全版ドキュメント

## 🎯 実装済み機能

### ✅ 1. Good機能（特定の相手に送信）
- ユーザー選択機能
- Good（良かったこと）メッセージ入力
- 特定の受信者への送信

### ✅ 2. More機能（Goodとセットで送信）
- More（改善提案）メッセージ入力
- GoodとMoreを1つのセットとして送信
- 両方必須入力

### ✅ 3. Good&More通知機能
- リアルタイム通知バッジ表示
- 未読件数カウント
- 通知パネルでの一覧表示
- 30秒ごとの自動更新

### ✅ 4. リアクション機能
- 受信者が絵文字でリアクション可能
- 4種類の絵文字（👍❤️🎉👏）
- リアクション数の表示
- 1ユーザー1リアクション制限

### ✅ 5. 送信履歴表示機能
- 送信履歴タブ
- 受信履歴タブ
- ステータス表示（送信済み/既読/リアクション済み）
- リアクション数表示

## 🚀 使い方

### 1. アクセス
```
http://localhost/good-more-complete.html
```

### 2. ユーザー選択
右上のドロップダウンから現在のユーザーを選択

### 3. Good&More送信
1. 送信先を選択
2. Good（良かったこと）を入力
3. More（改善提案）を入力
4. 「送信する」ボタンをクリック

### 4. 履歴確認
- **送信履歴タブ**: 自分が送ったGood&More
- **受信履歴タブ**: 自分が受け取ったGood&More

### 5. リアクション
受信履歴で絵文字ボタンをクリックしてリアクション

### 6. 通知確認
右上の🔔アイコンをクリックして通知パネルを開く

## 📊 データベーススキーマ

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

## 🔌 API エンドポイント

### ユーザー一覧取得
```
GET /good-more-api.php/users
```

**レスポンス:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "山田太郎",
      "email": "yamada@example.com"
    }
  ]
}
```

### Good&More送信
```
POST /good-more-api.php/send
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
GET /good-more-api.php/sent?sender_id=1&page=1&per_page=20
```

### 受信履歴取得
```
GET /good-more-api.php/received?receiver_id=2&page=1&per_page=20
```

### 通知一覧取得
```
GET /good-more-api.php/notifications?user_id=1
```

**レスポンス:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "sender_id": 2,
      "sender_name": "佐藤花子",
      "good_message": "プロジェクトの進行管理が素晴らしかったです！",
      "status": "sent",
      "type": "good_more",
      "created_at": "2024-01-01 10:00:00"
    }
  ],
  "unread_count": 3
}
```

### 通知既読
```
POST /good-more-api.php/notifications/read
Content-Type: application/json

{
  "good_more_id": 1
}
```

### リアクション追加
```
POST /good-more-api.php/reaction
Content-Type: application/json

{
  "good_more_id": 1,
  "user_id": 2,
  "reaction_type": "emoji",
  "reaction_content": "👍"
}
```

### リアクション削除
```
DELETE /good-more-api.php/reaction?id=1
```

## 🎨 UI機能

### ヘッダー
- タイトル表示
- ユーザー選択ドロップダウン
- 通知バッジ（未読件数表示）

### 送信フォーム
- 送信先選択
- Good入力欄（オレンジ背景）
- More入力欄（青背景）
- 送信ボタン
- 成功メッセージ表示

### 履歴表示
- タブ切り替え（送信/受信）
- カード形式の履歴表示
- ステータスバッジ
- リアクションボタン（受信履歴のみ）
- リアクション数表示

### 通知パネル
- スライドイン式パネル
- 通知一覧表示
- 未読/既読の視覚的区別
- クリックで詳細表示

## 📱 レスポンシブ対応

- デスクトップ: 2カラムレイアウト
- モバイル: 1カラムレイアウト
- 通知パネル: 全画面表示

## 🔄 自動更新

- 通知チェック: 30秒ごと
- 未読バッジ: 自動更新

## 🎯 ステータス遷移

```
sent (送信済み)
  ↓ 受信者が閲覧
read (既読)
  ↓ 受信者がリアクション
reacted (リアクション済み)
```

## 🧪 テストデータ

初期ユーザー:
1. 山田太郎 (yamada@example.com)
2. 佐藤花子 (sato@example.com)
3. 鈴木一郎 (suzuki@example.com)

サンプルGood&More:
- 山田太郎 → 佐藤花子
- 佐藤花子 → 山田太郎
- 山田太郎 → 鈴木一郎

## 🔧 カスタマイズ

### 絵文字の追加
`good-more-complete.html`の`renderHistory`関数内:
```javascript
<button class="reaction-btn" onclick="addReaction(${item.id}, 'emoji', '🌟')">🌟</button>
```

### 通知チェック間隔の変更
```javascript
setInterval(checkNotifications, 30000); // ミリ秒単位
```

### 表示件数の変更
```javascript
const url = `/good-more-api.php/${endpoint}?${param}=${currentUserId}&per_page=50`;
```

## 🚨 トラブルシューティング

### データベース接続エラー
```bash
# PostgreSQLコンテナ確認
docker ps | grep postgresql

# 接続テスト
curl http://localhost/good-more-api.php/test
```

### 通知が表示されない
1. ユーザーが選択されているか確認
2. ブラウザのコンソールでエラー確認
3. APIレスポンス確認

### リアクションが反映されない
1. 受信履歴タブで表示されているか確認
2. 送信履歴ではリアクションボタンは表示されません

## 📝 今後の拡張案

- [ ] コメント機能
- [ ] 画像添付
- [ ] タグ機能
- [ ] 検索機能
- [ ] エクスポート機能
- [ ] 統計ダッシュボード
- [ ] メール通知
- [ ] Slack連携

## 🔐 セキュリティ注意事項

本実装は開発用です。本番環境では以下を実装してください：

- 認証・認可機能
- CSRF対策
- XSS対策（実装済み: escapeHtml）
- SQLインジェクション対策（実装済み: Prepared Statement）
- レート制限
- HTTPS通信
