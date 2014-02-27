CREATE TABLE IF NOT EXISTS `carddav_contacts` (
  `carddav_contact_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `carddav_server_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `etag` varchar(64) NOT NULL,
  `last_modified` varchar(128) NOT NULL,
  `vcard_id` varchar(64) NOT NULL,
  `vcard` longtext NOT NULL,
  `words` text,
  `firstname` varchar(128) DEFAULT NULL,
  `surname` varchar(128) DEFAULT NULL,
  `name` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`carddav_contact_id`),
  UNIQUE KEY `carddav_server_id` (`carddav_server_id`,`user_id`,`vcard_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

CREATE TABLE IF NOT EXISTS `carddav_server` (
  `carddav_server_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `url` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `username` varchar(128) COLLATE utf8_unicode_ci NOT NULL,
  `password` varchar(128) COLLATE utf8_unicode_ci NOT NULL,
  `label` varchar(128) COLLATE utf8_unicode_ci NOT NULL,
  `read_only` tinyint(1) NOT NULL,
  `default_server` tinyint(1) NOT NULL,
  PRIMARY KEY (`carddav_server_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;

ALTER TABLE `carddav_contacts`
  ADD CONSTRAINT `carddav_contacts_ibfk_1` FOREIGN KEY (`carddav_server_id`) REFERENCES `carddav_server` (`carddav_server_id`) ON DELETE CASCADE;

ALTER TABLE `carddav_server`
  ADD CONSTRAINT `carddav_server_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;