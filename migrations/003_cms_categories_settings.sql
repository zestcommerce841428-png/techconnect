-- TechConnect v2 Phase 1.5: universal categories, CMS pages, blog, site settings
SET NAMES utf8mb4;

CREATE TABLE categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(80) NOT NULL UNIQUE,
    slug VARCHAR(100) NOT NULL UNIQUE,
    description VARCHAR(255) DEFAULT NULL,
    icon VARCHAR(10) DEFAULT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE questions ADD COLUMN category_id INT UNSIGNED DEFAULT NULL AFTER user_id;
ALTER TABLE questions ADD CONSTRAINT fk_questions_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL;
ALTER TABLE questions ADD INDEX (category_id);

INSERT INTO categories (name, slug, description, icon, sort_order) VALUES
    ('Technology', 'technology', 'Programming, hardware, software, IT support', '💻', 1),
    ('Business & Finance', 'business-finance', 'Startups, money, careers, marketing', '💼', 2),
    ('Health & Wellness', 'health-wellness', 'Fitness, mental health, medical questions', '🩺', 3),
    ('Education & Learning', 'education-learning', 'Study help, courses, skills', '📚', 4),
    ('Home & Lifestyle', 'home-lifestyle', 'DIY, cooking, relationships, everyday life', '🏠', 5),
    ('Travel & Local', 'travel-local', 'Travel tips, local recommendations', '✈️', 6),
    ('Creative & Arts', 'creative-arts', 'Design, writing, music, photography', '🎨', 7),
    ('General Discussion', 'general', 'Anything that does not fit elsewhere', '💬', 99);

CREATE TABLE pages (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(100) NOT NULL UNIQUE,
    title VARCHAR(200) NOT NULL,
    body MEDIUMTEXT NOT NULL,
    meta_description VARCHAR(255) DEFAULT NULL,
    is_published TINYINT(1) NOT NULL DEFAULT 1,
    show_in_footer TINYINT(1) NOT NULL DEFAULT 0,
    updated_by INT UNSIGNED DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE blog_categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(80) NOT NULL UNIQUE,
    slug VARCHAR(100) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE blog_posts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    author_id INT UNSIGNED NOT NULL,
    blog_category_id INT UNSIGNED DEFAULT NULL,
    title VARCHAR(200) NOT NULL,
    slug VARCHAR(220) NOT NULL UNIQUE,
    excerpt VARCHAR(300) DEFAULT NULL,
    body MEDIUMTEXT NOT NULL,
    cover_image VARCHAR(255) DEFAULT NULL,
    meta_description VARCHAR(255) DEFAULT NULL,
    status ENUM('draft','published') NOT NULL DEFAULT 'draft',
    published_at DATETIME DEFAULT NULL,
    view_count INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (blog_category_id) REFERENCES blog_categories(id) ON DELETE SET NULL,
    FULLTEXT KEY ft_title_body (title, body),
    INDEX (status, published_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE site_settings (
    setting_key VARCHAR(80) NOT NULL PRIMARY KEY,
    setting_value TEXT,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO site_settings (setting_key, setting_value) VALUES
    ('site_name', 'TechConnect'),
    ('tagline', 'Ask, answer, connect — worldwide or nearby'),
    ('logo_path', ''),
    ('favicon_path', ''),
    ('footer_text', ''),
    ('social_twitter', ''),
    ('social_facebook', ''),
    ('social_linkedin', ''),
    ('social_instagram', '');
