ALTER TABLE questions ADD COLUMN merged_into_id INT UNSIGNED DEFAULT NULL AFTER closed_by;
ALTER TABLE questions ADD CONSTRAINT fk_questions_merged_into FOREIGN KEY (merged_into_id) REFERENCES questions(id) ON DELETE SET NULL;
ALTER TABLE questions ADD INDEX (merged_into_id);
