-- Soft deletes for CMS content: deleting moves to Trash instead of destroying.
-- Nullable timestamp + index so the "not trashed" filter (deleted_at IS NULL)
-- on every public read stays cheap.
ALTER TABLE blog_posts
    ADD COLUMN deleted_at DATETIME DEFAULT NULL,
    ADD COLUMN deleted_by INT UNSIGNED DEFAULT NULL,
    ADD INDEX idx_blog_deleted (deleted_at);

ALTER TABLE pages
    ADD COLUMN deleted_at DATETIME DEFAULT NULL,
    ADD COLUMN deleted_by INT UNSIGNED DEFAULT NULL,
    ADD INDEX idx_pages_deleted (deleted_at);
