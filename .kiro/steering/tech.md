# 技術スタック

## バックエンド

- **フレームワーク**: Laravel 10.x (PHP 8.1+)
- **データベース**: PostgreSQL 14.7
- **Webサーバー**: Nginx
- **PHP-FPM**: カスタム設定

## フロントエンドビルドツール

- **Vite**: アセットバンドリングと開発サーバー
- **Laravel Vite Plugin**: LaravelとViteの統合
- **Axios**: HTTPクライアント

## 主要な依存関係

- **Google API Client**: YouTubeとGoogle Books APIの統合
- **Guzzle**: 外部API呼び出し用HTTPクライアント
- **Laravel Sanctum**: API認証
- **Laravel Tinker**: Laravel用REPL

## インフラストラクチャ

- **Docker Compose**: マルチコンテナオーケストレーション
- **サービス構成**:
  - `app`: PHP-FPMアプリケーションコンテナ
  - `web`: Nginx Webサーバー (ポート80)
  - `postgresql`: データベース (ポート5433)
  - `phpmyadmin`: データベース管理UI (ポート1234)

## よく使うコマンド

### Docker操作
```bash
docker-compose up -d          # 全サービス起動
docker-compose down           # 全サービス停止
docker-compose logs -f app    # アプリケーションログ表示
```

### Laravel/PHP
```bash
# appコンテナ内で実行
php artisan migrate           # データベースマイグレーション実行
php artisan tinker           # Laravel REPL起動
php artisan route:list       # 全ルート一覧表示
composer install             # PHP依存関係インストール
composer dump-autoload       # オートロードファイル再生成
```

### フロントエンドアセット
```bash
npm install                  # Node依存関係インストール
npm run dev                  # Vite開発サーバー起動
npm run build               # 本番用アセットビルド
```

## 環境設定

- `.env.example`を`.env`にコピーして以下を設定:
  - `GOOGLE_API_KEY`: YouTube API用（必須）
  - `GOOGLE_BOOKS_API_KEY`: Books API用（必須）
  - データベース認証情報（デフォルト: user=posse, password=password, db=winter）
