# プロダクト概要

2025年冬季ハッカソンプロジェクト。複数のソースからコンテンツを検索・取得するAPIエンドポイントを提供します。

## 主要機能

- **YouTube連携**: 動画検索とトレンド動画の取得
- **書籍検索**: Google Books APIを使用した書籍検索
- **マルチソースコンテンツ検索**: 複数のプラットフォームからコンテンツを統合的に検索

## APIエンドポイント

- `/api/youtube/search?q={keyword}&max={limit}` - YouTube動画検索
- `/api/youtube/trending` - 日本のトレンド動画取得
- `/api/search?q={query}` - Google Books APIによる書籍検索
