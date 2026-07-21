-- CMS pages get the same category concept blog posts already have.
-- Mirrors blog_categories exactly, so admin/page_categories.php and
-- admin/blog_categories.php can share one template.
CREATE TABLE IF NOT EXISTS page_categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(80) NOT NULL UNIQUE,
    slug VARCHAR(100) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE pages
    ADD COLUMN page_category_id INT UNSIGNED DEFAULT NULL,
    ADD CONSTRAINT fk_pages_category FOREIGN KEY (page_category_id) REFERENCES page_categories(id) ON DELETE SET NULL;
