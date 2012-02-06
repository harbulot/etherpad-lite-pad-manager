CREATE TABLE `users` (
  `id` int(32) unsigned NOT NULL AUTO_INCREMENT,
  `openid` varchar(128) NOT NULL,
  `nickname` varchar(128) DEFAULT NULL,
  `created` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `logged` timestamp NULL DEFAULT NULL,
  `is_manager` int(1) NOT NULL DEFAULT '0',
  `is_enabled` int(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_openid_idx` (`openid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `sessions` (
  `id` varchar(32) NOT NULL,
  `data` longtext,
  `last_access` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `log` (
  `user_id` int(32) unsigned NOT NULL,
  `logged` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `ip_address` varchar(128) NOT NULL,
  `useragent` varchar(1024) DEFAULT NULL,
  KEY `log_user_id_fk` (`user_id`),
  CONSTRAINT `log_user_id_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `autologin` (
  `user_id` int(32) unsigned NOT NULL,
  `secret` char(32) NOT NULL,
  `expires` int(32) unsigned NOT NULL,
  KEY `autologin_user_idx` (`user_id`,`secret`),
  KEY `autologin_expires_idx` (`expires`),
  CONSTRAINT `autologin_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `store` (
  `key` varchar(100) NOT NULL,
  `value` longtext NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE `pads` (
  `id` varchar(128) NOT NULL,
  `name` varchar(128) NOT NULL,
  `is_private` int(1) NOT NULL DEFAULT '0',
  `user_id` int(32) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `pads_user_fk` (`user_id`),
  CONSTRAINT `pads_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

