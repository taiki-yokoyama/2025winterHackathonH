# プロジェクト構造

## ルートレイアウト

```
/
├── api/web/              # Laravelアプリケーションルート
├── docker/               # Docker設定ファイル
├── compose.yaml          # Docker Composeオーケストレーション
└── README.md
```

## Laravelアプリケーション (`api/web/`)

### 主要ディレクトリ

- **`app/`**: アプリケーションロジック
  - `Console/`: Artisanコマンド
  - `Exceptions/`: 例外ハンドラー
  - `Http/Controllers/`: リクエストハンドラー
    - `YoutubeController.php`: YouTube API連携
    - `BooksSearchController.php`: Google Books API連携
  - `Http/Middleware/`: リクエスト/レスポンスミドルウェア
  - `Models/`: Eloquent ORMモデル
  - `Providers/`: サービスプロバイダー

- **`routes/`**: ルート定義
  - `api.php`: APIエンドポイント（`/api`プレフィックス）
  - `web.php`: Webルート
  - `console.php`: コンソールコマンド
  - `channels.php`: ブロードキャストチャンネル

- **`config/`**: 設定ファイル
  - フレームワーク、データベース、サービスなど

- **`database/`**: データベース関連ファイル
  - `migrations/`: スキーママイグレーション
  - `seeders/`: データベースシーダー
  - `factories/`: テスト用モデルファクトリー

- **`resources/`**: フロントエンドアセットとビュー
  - `views/`: Bladeテンプレート
  - `js/`: JavaScriptファイル
  - `css/`: スタイルシート

- **`storage/`**: 生成ファイル
  - `app/`: アプリケーションストレージ
  - `framework/`: フレームワークキャッシュとセッション
  - `logs/`: アプリケーションログ

- **`tests/`**: PHPUnitテスト
  - `Feature/`: 機能テスト
  - `Unit/`: ユニットテスト

- **`public/`**: Webサーバードキュメントルート
  - エントリーポイント（`index.php`）
  - 公開アセット

- **`vendor/`**: Composer依存関係（gitignore対象）
- **`node_modules/`**: NPM依存関係（gitignore対象）

## Docker設定 (`docker/`)

- **`php/`**: PHP-FPMコンテナ設定
  - `Dockerfile`: PHPコンテナビルド
  - `php.ini`: PHP設定
  - `startup.sh`: コンテナ起動スクリプト
  - `php-fpm.d/`: PHP-FPMプール設定

- **`nginx/`**: Nginx Webサーバー設定
  - `default.conf`: Nginxサイト設定

- **`postgresql/`**: PostgreSQL初期化
  - `initdb.d/init.sql`: データベース初期化スクリプト

## 規約

### 名前空間
- コントローラー: `App\Http\Controllers`
- モデル: `App\Models`
- PSR-4オートローディング標準に従う

### ルーティング
- APIルートは`/api`プレフィックス
- RESTfulエンドポイントにはリソースコントローラーを使用
- 関連するルートはグループ化

### コントローラー
- コントローラーは薄く保ち、必要に応じてビジネスロジックをサービスに委譲
- 外部サービスには依存性注入を使用
- APIエンドポイントはJSONレスポンスを返す

### 環境変数
- `.env`ファイルは絶対にコミットしない
- `.env.example`をテンプレートとして使用
- APIキーとシークレットは環境変数に保存
