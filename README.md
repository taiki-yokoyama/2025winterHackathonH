# 2025winterHackathonH — タスク管理 & チーム振り返り支援システム

このリポジトリは、チームの振り返りで出たアクション（タスク）の洗い出し・担当割当・進捗管理と、個人の振り返り・フィードバック機能を提供するシステムの実装プロジェクトです。

以下は「これから作ろうとしている機能全体」をまとめた仕様書兼 README です。採点者・開発者がプロジェクトの目的や環境構成、受け入れ基準を短時間で理解できるように構成しています。

---

## 目的

- チーム振り返りの内容を実行可能。
- 個人の振り返り（自己評価・今後の展望）を記録し、週次での達成度合いやチーム全体の達成度を可視化する。

## 想定スタック

- 言語: PHP（プレーン PHP または将来的に Laravel）
- DB: MySQL（Docker での起動を想定）
- Web: Nginx（Docker）
- フロント: サーバサイドレンダリング（PHP テンプレート）／必要に応じて Vue/React を導入
- 開発/実行環境: Docker / docker-compose

## 高レベル機能一覧

1. 認証（登録・ログイン）
2. トップページ（ダッシュボード）
 - チーム評価（5段階）をメンバー全員が入力 → メンバーごとの評価値を表示する。
   ここでの評価は「振り返りがきちんと行えているか／振り返りの質」に関する主観的な評価を可視化するためのものであり、個々の数値は議論のきっかけ（ファシリテーション用の指標）として利用する。**評価結果から自動的に具体的タスクを生成する仕組みは持たない**（議論の結果として必要なタスクがあれば、別途タスク作成機能で登録する）。
 - 週のタスク表示：タスク一覧から与えられたタスクを表示する
 - 振り返りで行った今後の展望を「今週の目標」として表示する
 - 個人・チームの目標達成率と実績をグラフで表示
3. 個人振り返りフォーム
 - 5段階評価（要件定義、開発、プレゼン、振り返り、その他）と理由記述
 - 今後の展望（次回までの目標）を記入
4. タスク管理
 - タスクの追加・削除
 - ステータス管理（未着手／進行中／完了）、完了日時の記録
5. Good & More（フィードバック）
 - Good: 特定の相手に Good のみ送信可能
 - More: Good とセットで送信（改善提案）
 - 受け取り側はリアクションで応答可能
6. フィードバック機能
 - 振り返り投稿へのコメントやリアクション

## MVP（優先実装）

まずは以下を最小実装（MVP）として優先します。

- ユーザー登録 / ログイン
- タスク CRUD（追加・削除・編集）＋複数担当者割当
- トップダッシュボード（週タスク選択 → ダッシュボードにタスクバー表示、チェックで完了）
- 個人振り返りフォーム（保存・一覧）

拡張フェーズ:

- メール通知、Good/More の UI・運用ルール、API化 / SPA化

## データモデル（主要テーブル）

- users : id, name, email, password, created_at, updated_at
- tasks : id, title, description, status (enum: pending,in_progress,completed), due_date, completed_at, created_by, created_at, updated_at
- task_user : id, task_id, user_id, created_at, updated_at  （unique index: task_id,user_id）
- reflections : id, user_id, week_start, answers(json), future_plan(text), created_at, updated_at
- ratings : id, user_id, week_start, category, score (1-5), created_at, updated_at
- feedbacks : id, sender_id, receiver_id, type(enum: good,more), content, related_reflection_id, created_at, updated_at
- notifications : id, user_id, type, payload(json), read_at, created_at, updated_at

## 受け入れ基準（Acceptance Criteria）

- タスク作成時に期日は自動的に「登録日 + 7日」に設定される。
- タスク作成時の初期ステータスは `pending`（未着手）になる。
- title は必須かつ最大200文字、`description` は最大1000文字でバリデーションされる。
- タスクを削除すると関連ピボットも削除される（外部キー制約）。
- 週評価（5段階）を集計し、メンバー毎の値を表示する。これらの評価は「振り返りの実施・質を評価する尺度」であり、議論のきっかけとなる指標として表示する。評価値そのものから自動的にタスクは生成されない（必要なアクションは議論後に手動でタスク化する）。


## 開発フロー

1. ブランチ戦略: feature/*
2. 新機能は feature/ ブランチで実装 → PR → レビュー → main にマージ

## セットアップ（採点者・開発者向け 簡易手順）

> 前提: Docker と Docker Compose がインストールされていること

1. リポジトリをクローン

bash
git clone <repo-url>
cd 2025winterHackathonH

2. コンテナ起動（ビルド含む）

docker compose up -d --build


3. アプリにアクセス

- PHP 実装: http://localhost/auth.html

## テスト

- ユニット / Feature テストを用意
- 主要な自動テストケース: タスク作成 / 編集 / 削除、振り返り作成、評価集計。

## セキュリティ上の注意

- 本プロジェクト（開発段階）では CSRF 保護、厳格な入力サニタイズ、認可チェックを必ず実装してください。
- メール送信や外部公開時は HTTPS、パスワードポリシー、ログ監視、バックアップ戦略を整備してください。