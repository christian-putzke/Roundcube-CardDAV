// updates from version 0.2.3
ALTER TABLE `carddav_contacts` ADD `last_modified` int(10) unsigned NOT NULL AFTER `etag` ;

// updates from version 0.2.4
ALTER TABLE `carddav_contacts` CHANGE `last_modified` `last_modified` VARCHAR(128) NOT NULL ;

// updates from version 0.2.5
ALTER TABLE `carddav_contacts` CHANGE `name` `name` VARCHAR( 255 ) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL ,
CHANGE `email` `email` VARCHAR( 255 ) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL ;

// updates from version 0.3
ALTER TABLE `carddav_contacts` ADD `words` TEXT NULL DEFAULT NULL AFTER `vcard` ,
ADD `firstname` VARCHAR( 128 ) NULL DEFAULT NULL AFTER `words` ,
ADD `surname` VARCHAR( 128 ) NULL DEFAULT NULL AFTER `firstname` ;

// updates from version 0.5
ALTER TABLE `carddav_server` ADD `read_only` tinyint(1) NOT NULL AFTER `label`;
ALTER TABLE `carddav_server` ADD `default_server` tinyint(1) NOT NULL AFTER `read_only`;
