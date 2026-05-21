-- ============================================================
--  TYPING TEST SYSTEM - Complete Database Schema
--  Run this in phpMyAdmin on database: typing_april_12
-- ============================================================

CREATE TABLE IF NOT EXISTS `centers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(200) NOT NULL,
  `code` varchar(50) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `admins` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL COMMENT 'MD5 hashed',
  `full_name` varchar(200) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Default admin: username=admin, password=admin123
INSERT IGNORE INTO `admins` (username, password, full_name) 
VALUES ('admin', MD5('admin123'), 'System Administrator');

CREATE TABLE IF NOT EXISTS `app_settings` (
  `skey` varchar(100) NOT NULL,
  `svalue` varchar(255) NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`skey`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT IGNORE INTO `app_settings` (`skey`, `svalue`) VALUES
  ('default_test_minutes', '1'),
  ('candidate_portal_enabled', '1'),
  ('incharge_portal_enabled', '1');

CREATE TABLE IF NOT EXISTS `type_codes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_type_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT IGNORE INTO `type_codes` (`code`, `name`, `description`, `is_active`) VALUES
  (1, 'Normal', 'Default candidate and paragraph type', 1),
  (2, 'Disable', 'Special accommodation type', 1),
  (3, 'Minority / Female', 'Special accommodation type', 1);

CREATE TABLE IF NOT EXISTS `center_incharges` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `center_id` int(11) DEFAULT NULL,
  `username` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL COMMENT 'password_hash preferred; md5 supported for legacy',
  `full_name` varchar(200) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_incharge_username` (`username`),
  KEY `idx_incharge_center` (`center_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `retest_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `center_id` int(11) NOT NULL,
  `incharge_id` int(11) NOT NULL,
  `reason` varchar(500) NOT NULL,
  `allowed_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_retest_user` (`user_id`),
  KEY `idx_retest_center` (`center_id`),
  KEY `idx_retest_incharge` (`incharge_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `candidate_center_change_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `old_center_id` int(11) NOT NULL,
  `new_center_id` int(11) NOT NULL,
  `changed_by_role` enum('admin','incharge') NOT NULL,
  `changed_by_id` int(11) NOT NULL DEFAULT '0',
  `reason` varchar(500) NOT NULL,
  `changed_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_ccl_user` (`user_id`),
  KEY `idx_ccl_old_center` (`old_center_id`),
  KEY `idx_ccl_new_center` (`new_center_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(200) NOT NULL DEFAULT '',
  `post` varchar(100) DEFAULT NULL,
  `cnic` varchar(13) NOT NULL,
  `roll_no` varchar(50) NOT NULL,
  `center_id` int(11) NOT NULL DEFAULT '0',
  `center_code` varchar(50) DEFAULT NULL,
  `center_name` varchar(255) DEFAULT NULL,
  `image_url` varchar(500) DEFAULT NULL,
  `type` tinyint(1) NOT NULL DEFAULT '1',
  `tested` tinyint(1) NOT NULL DEFAULT '0',
  `logged_once` tinyint(1) NOT NULL DEFAULT '0',
  `allow_retest` tinyint(1) NOT NULL DEFAULT '0',
  `last_login` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `cnic_roll` (`cnic`,`roll_no`),
  KEY `idx_candidate_login` (`cnic`,`roll_no`,`center_id`,`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `paragraphs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(200) NOT NULL,
  `content` text NOT NULL,
  `type_code` tinyint(1) NOT NULL DEFAULT '1',
  `type_name` varchar(100) NOT NULL DEFAULT 'Normal',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Default paragraph
INSERT IGNORE INTO `paragraphs` (title, content, is_active) VALUES (
  'Default Paragraph',
  'SUBJECT: Disposal of Enquiry cases based on anonymous or Pseudonymous complaints. I am directed to invite attention to this Department circular letter of even number dated 22/7/2011, and state that the following instructions may kindly be brought to the notice of all concerned, noted for strict compliance and may be followed during disposal of anonymous communications. Anonymous communications must invariably be filed on their receipt. No action of any kind is to be taken on them and no notice of any kind is to be taken on their contents. If the communication is found to be pseudonymous it must similarly be filed. It is however recognized that there may be exceptional cases when anonymous or pseudonymous communication contain allegations of a specific nature having a ring of truth, then these may be inquired into only after obtaining the orders of Administrative Secretaries and Head of Attached Department.',
  1
);

CREATE TABLE IF NOT EXISTS `lab_sessions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `center_id` int(11) NOT NULL,
  `session_password` varchar(10) NOT NULL,
  `status` enum('active','expired') NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `center_id` (`center_id`),
  KEY `idx_session_lookup` (`session_password`,`status`,`created_at`,`center_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `results` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uid` int(11) NOT NULL,
  `roll_no` varchar(50) NOT NULL,
  `center_id` int(11) NOT NULL DEFAULT '0',
  `characters_typed` text,
  `correct_words` int(11) NOT NULL DEFAULT '0',
  `incorrect_words` int(11) NOT NULL DEFAULT '0',
  `wpm` int(11) NOT NULL DEFAULT '0',
  `accuracy` varchar(20) NOT NULL DEFAULT '0',
  `time` varchar(20) NOT NULL DEFAULT '0',
  `date` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `live_candidates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `session_id` int(11) NOT NULL,
  `center_id` int(11) NOT NULL,
  `started_at` datetime NOT NULL,
  `finished_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_session` (`user_id`,`session_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Sample center
INSERT IGNORE INTO `centers` (name, code) VALUES 
  ('Main Examination Center', 'MEC-01'),
  ('Secondary Center Kohat', 'SCK-02');
