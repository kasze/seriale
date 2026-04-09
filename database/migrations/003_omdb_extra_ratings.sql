ALTER TABLE `{{prefix}}shows`
    ADD COLUMN `rotten_tomatoes_rating` VARCHAR(16) DEFAULT NULL AFTER `imdb_rating_source`,
    ADD COLUMN `rotten_tomatoes_source` VARCHAR(80) DEFAULT NULL AFTER `rotten_tomatoes_rating`,
    ADD COLUMN `metacritic_rating` SMALLINT DEFAULT NULL AFTER `rotten_tomatoes_source`,
    ADD COLUMN `metacritic_rating_source` VARCHAR(80) DEFAULT NULL AFTER `metacritic_rating`;
