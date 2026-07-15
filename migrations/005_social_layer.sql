-- TechConnect v2 Phase 2: social/community layer
SET NAMES utf8mb4;

CREATE TABLE follows (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    follower_id INT UNSIGNED NOT NULL,
    followable_type ENUM('user','tag') NOT NULL,
    followable_id INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_follow (follower_id, followable_type, followable_id),
    FOREIGN KEY (follower_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX (followable_type, followable_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE badges (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) NOT NULL UNIQUE,
    name VARCHAR(80) NOT NULL,
    description VARCHAR(255) NOT NULL,
    icon VARCHAR(10) DEFAULT NULL,
    tier ENUM('bronze','silver','gold') NOT NULL DEFAULT 'bronze'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE user_badges (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    badge_id INT UNSIGNED NOT NULL,
    awarded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_user_badge (user_id, badge_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (badge_id) REFERENCES badges(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE saved_questions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    question_id INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_saved (user_id, question_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE user_blocks (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    blocker_id INT UNSIGNED NOT NULL,
    blocked_id INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_block (blocker_id, blocked_id),
    FOREIGN KEY (blocker_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (blocked_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO badges (code, name, description, icon, tier) VALUES
    ('first_question', 'Curious Mind', 'Asked your first question', '❓', 'bronze'),
    ('first_answer', 'Helper', 'Posted your first answer', '🙋', 'bronze'),
    ('first_accepted', 'Problem Solver', 'Had an answer accepted', '✅', 'bronze'),
    ('ten_accepted', 'Trusted Expert', '10 accepted answers', '🏅', 'silver'),
    ('fifty_reputation', 'Rising Star', 'Reached 50 reputation', '⭐', 'bronze'),
    ('two_hundred_reputation', 'Community Pillar', 'Reached 200 reputation', '🌟', 'gold'),
    ('popular_question', 'Popular Question', 'A question reached 10 upvotes', '🔥', 'silver');
