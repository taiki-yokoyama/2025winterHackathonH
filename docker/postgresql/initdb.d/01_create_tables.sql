-- Users テーブル
CREATE TABLE IF NOT EXISTS users (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    team_name VARCHAR(10),
    email_verified BOOLEAN DEFAULT FALSE,
    email_verification_token VARCHAR(255),
    email_verification_expires_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Good&Mores テーブル
CREATE TABLE IF NOT EXISTS good_mores (
    id SERIAL PRIMARY KEY,
    sender_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    receiver_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    good_message TEXT NOT NULL,
    more_message TEXT,
    status VARCHAR(50) DEFAULT 'sent',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Good&More Reactions テーブル
CREATE TABLE IF NOT EXISTS good_more_reactions (
    id SERIAL PRIMARY KEY,
    good_more_id INTEGER NOT NULL REFERENCES good_mores(id) ON DELETE CASCADE,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    reaction_type VARCHAR(50) NOT NULL,
    reaction_content TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(good_more_id, user_id)
);

-- インデックス
CREATE INDEX IF NOT EXISTS idx_good_mores_sender ON good_mores(sender_id, created_at);
CREATE INDEX IF NOT EXISTS idx_good_mores_receiver ON good_mores(receiver_id, created_at);
CREATE INDEX IF NOT EXISTS idx_reactions_good_more ON good_more_reactions(good_more_id);

-- セッションテーブル
CREATE TABLE IF NOT EXISTS sessions (
    id VARCHAR(255) PRIMARY KEY,
    user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
    ip_address VARCHAR(45),
    user_agent TEXT,
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- パスワードリセットテーブル
CREATE TABLE IF NOT EXISTS password_resets (
    id SERIAL PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    token VARCHAR(255) NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_password_resets_email ON password_resets(email);
CREATE INDEX IF NOT EXISTS idx_password_resets_token ON password_resets(token);

-- サンプルユーザー（パスワードはハッシュ化: password123）
INSERT INTO users (name, email, password, team_name, email_verified) VALUES
    ('山田太郎', 'yamada@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'A', TRUE),
    ('佐藤花子', 'sato@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'A', TRUE),
    ('鈴木一郎', 'suzuki@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'B', TRUE)
ON CONFLICT (email) DO NOTHING;

-- タスクテーブル
CREATE TABLE IF NOT EXISTS tasks (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    assigned_to INTEGER REFERENCES users(id) ON DELETE SET NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    status VARCHAR(50) DEFAULT 'pending',
    due_date DATE,
    completed_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- チームテーブル
CREATE TABLE IF NOT EXISTS teams (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- チームメンバーテーブル
CREATE TABLE IF NOT EXISTS team_members (
    id SERIAL PRIMARY KEY,
    team_id INTEGER NOT NULL REFERENCES teams(id) ON DELETE CASCADE,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    role VARCHAR(50) DEFAULT 'member',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(team_id, user_id)
);

-- 振り返りテーブル
CREATE TABLE IF NOT EXISTS retrospectives (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    week_start_date DATE NOT NULL,
    week_end_date DATE NOT NULL,
    
    -- 5段階評価（5つの観点）
    requirements_rating INTEGER CHECK (requirements_rating >= 1 AND requirements_rating <= 5),
    requirements_reason TEXT NOT NULL,
    
    development_rating INTEGER CHECK (development_rating >= 1 AND development_rating <= 5),
    development_reason TEXT NOT NULL,
    
    presentation_rating INTEGER CHECK (presentation_rating >= 1 AND presentation_rating <= 5),
    presentation_reason TEXT NOT NULL,
    
    retrospective_rating INTEGER CHECK (retrospective_rating >= 1 AND retrospective_rating <= 5),
    retrospective_reason TEXT NOT NULL,
    
    other_rating INTEGER CHECK (other_rating >= 1 AND other_rating <= 5),
    other_reason TEXT NOT NULL,
    
    -- 今後の展望
    future_outlook TEXT NOT NULL,
    
    -- ステータス
    status VARCHAR(50) DEFAULT 'draft',
    submitted_at TIMESTAMP,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    UNIQUE(user_id, week_start_date)
);

-- 振り返りとタスクの関連テーブル
CREATE TABLE IF NOT EXISTS retrospective_tasks (
    id SERIAL PRIMARY KEY,
    retrospective_id INTEGER NOT NULL REFERENCES retrospectives(id) ON DELETE CASCADE,
    task_id INTEGER NOT NULL REFERENCES tasks(id) ON DELETE CASCADE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(retrospective_id, task_id)
);

CREATE INDEX IF NOT EXISTS idx_retrospectives_user ON retrospectives(user_id, week_start_date);
CREATE INDEX IF NOT EXISTS idx_tasks_user ON tasks(user_id, created_at);

-- チーム振り返り評価テーブル
CREATE TABLE IF NOT EXISTS team_retrospective_evaluations (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    team_id INTEGER NOT NULL REFERENCES teams(id) ON DELETE CASCADE,
    week_start_date DATE NOT NULL,
    evaluation_score INTEGER CHECK (evaluation_score >= 1 AND evaluation_score <= 5),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(user_id, team_id, week_start_date)
);

-- 展望チェックテーブル
CREATE TABLE IF NOT EXISTS outlook_checks (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    retrospective_id INTEGER NOT NULL REFERENCES retrospectives(id) ON DELETE CASCADE,
    is_completed BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(user_id, retrospective_id)
);

-- 目標テーブル
CREATE TABLE IF NOT EXISTS goals (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    status VARCHAR(50) DEFAULT 'pending',
    evaluation VARCHAR(10),
    due_date DATE,
    completed_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_team_evaluations ON team_retrospective_evaluations(team_id, week_start_date);
CREATE INDEX IF NOT EXISTS idx_outlook_checks ON outlook_checks(user_id, retrospective_id);
CREATE INDEX IF NOT EXISTS idx_goals_user ON goals(user_id, status);

-- フィードバックテーブル
CREATE TABLE IF NOT EXISTS feedbacks (
    id SERIAL PRIMARY KEY,
    retrospective_id INTEGER NOT NULL REFERENCES retrospectives(id) ON DELETE CASCADE,
    sender_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    receiver_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    content TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- フィードバック返信テーブル
CREATE TABLE IF NOT EXISTS feedback_replies (
    id SERIAL PRIMARY KEY,
    feedback_id INTEGER NOT NULL REFERENCES feedbacks(id) ON DELETE CASCADE,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    content TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- フィードバックリアクションテーブル
CREATE TABLE IF NOT EXISTS feedback_reactions (
    id SERIAL PRIMARY KEY,
    feedback_id INTEGER NOT NULL REFERENCES feedbacks(id) ON DELETE CASCADE,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    reaction_type VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(feedback_id, user_id, reaction_type)
);

CREATE INDEX IF NOT EXISTS idx_feedbacks_retrospective ON feedbacks(retrospective_id);
CREATE INDEX IF NOT EXISTS idx_feedbacks_sender ON feedbacks(sender_id, created_at);
CREATE INDEX IF NOT EXISTS idx_feedbacks_receiver ON feedbacks(receiver_id, created_at);
CREATE INDEX IF NOT EXISTS idx_feedback_replies ON feedback_replies(feedback_id);
CREATE INDEX IF NOT EXISTS idx_feedback_reactions ON feedback_reactions(feedback_id);

-- サンプルGood&More
INSERT INTO good_mores (sender_id, receiver_id, good_message, more_message, status) VALUES
    (1, 2, 'プロジェクトの進行管理が素晴らしかったです！', '次回は事前の情報共有をもう少し早めにお願いします。', 'sent'),
    (2, 1, 'コードレビューが丁寧で助かりました。', 'レビューのタイミングをもう少し早くしていただけると嬉しいです。', 'read'),
    (1, 3, 'ドキュメント作成ありがとうございました。', '図解をもう少し増やしていただけると理解しやすいです。', 'sent')
ON CONFLICT DO NOTHING;

-- チームA~Hを作成
INSERT INTO teams (name) VALUES 
    ('チームA'),
    ('チームB'),
    ('チームC'),
    ('チームD'),
    ('チームE'),
    ('チームF'),
    ('チームG'),
    ('チームH')
ON CONFLICT DO NOTHING;

-- サンプルチームメンバー（ユーザーのteam_nameに基づいて自動的に割り当て）
-- チームAのメンバー
INSERT INTO team_members (team_id, user_id, role)
SELECT 1, id, 'member' FROM users WHERE team_name = 'A'
ON CONFLICT DO NOTHING;

-- チームBのメンバー
INSERT INTO team_members (team_id, user_id, role)
SELECT 2, id, 'member' FROM users WHERE team_name = 'B'
ON CONFLICT DO NOTHING;

-- サンプルタスク
INSERT INTO tasks (user_id, assigned_to, title, description, status, due_date, completed_at) VALUES
    (1, 1, 'ログイン機能の実装', 'ユーザー認証システムの開発', 'completed', NOW()::DATE + INTERVAL '7 days', NOW() - INTERVAL '2 days'),
    (1, 2, 'データベース設計', 'テーブル設計とER図作成', 'completed', NOW()::DATE + INTERVAL '7 days', NOW() - INTERVAL '3 days'),
    (1, 1, 'API開発', 'RESTful APIの実装', 'in_progress', NOW()::DATE + INTERVAL '7 days', NULL),
    (1, 3, 'フロントエンド開発', 'React UIコンポーネント作成', 'pending', NOW()::DATE + INTERVAL '7 days', NULL),
    (1, 2, 'テスト作成', 'ユニットテストとE2Eテスト', 'pending', NOW()::DATE - INTERVAL '2 days', NULL),
    (2, 2, 'ドキュメント作成', 'API仕様書作成', 'completed', NOW()::DATE + INTERVAL '7 days', NOW() - INTERVAL '1 day'),
    (2, 1, 'コードレビュー', 'プルリクエストのレビュー', 'in_progress', NOW()::DATE + INTERVAL '7 days', NULL),
    (3, 3, 'デザイン作成', 'UIデザインのモックアップ', 'completed', NOW()::DATE + INTERVAL '7 days', NOW() - INTERVAL '4 days')
ON CONFLICT DO NOTHING;
