INSERT INTO `{{prefix}}app_settings` (`key`, `value`, `type`, `group_name`) VALUES
    ('app_env', 'development', 'string', 'general'),
    ('app_timezone', 'Europe/Warsaw', 'string', 'general'),
    ('single_user_identity', 'you@example.com', 'string', 'general'),
    ('mail_transport', 'log', 'string', 'mail'),
    ('cache_ttl_hours', '12', 'int', 'sync'),
    ('episode_availability_offset_days', '1', 'int', 'sync')
ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `type` = VALUES(`type`), `group_name` = VALUES(`group_name`);
