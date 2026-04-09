ALTER TABLE `{{prefix}}users`
    ADD COLUMN `password_hash` VARCHAR(255) DEFAULT NULL AFTER `display_name`;

ALTER TABLE `{{prefix}}login_tokens`
    ADD COLUMN `purpose` VARCHAR(40) NOT NULL DEFAULT 'login' AFTER `identity`;

ALTER TABLE `{{prefix}}login_tokens`
    ADD KEY `idx_{{prefix}}login_tokens_purpose` (`purpose`, `expires_at`);
