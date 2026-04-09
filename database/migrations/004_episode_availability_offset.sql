INSERT INTO `{{prefix}}app_settings` (`key`, `value`, `type`, `group_name`) VALUES
    ('episode_availability_offset_days', '1', 'int', 'sync')
ON DUPLICATE KEY UPDATE `key` = `key`;
