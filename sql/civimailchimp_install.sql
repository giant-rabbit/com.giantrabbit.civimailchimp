-- /*******************************************************
-- *
-- * civimailchimp_sync_settings
-- *
-- *******************************************************/
CREATE TABLE `civimailchimp_sync_settings` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique Identifier',
  `civicrm_group_id` int unsigned NOT NULL COMMENT 'CiviCRM Group ID',
  `mailchimp_list_id` varchar(255) NOT NULL COMMENT 'Mailchimp List ID',
  PRIMARY KEY (`id`),
  CONSTRAINT FK_civimailchimp_sync_settings_group_id FOREIGN KEY (`civicrm_group_id`) REFERENCES `civicrm_group`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci;

-- /*******************************************************
-- *
-- * civimailchimp_interest_groups_sync_settings
-- *
-- *******************************************************/
CREATE TABLE `civimailchimp_interest_groups_sync_settings` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique Identifier',
  `civimailchimp_sync_settings_id` int unsigned NOT NULL COMMENT 'CiviCRM Sync Settings ID',
  `mailchimp_interest_grouping_id` varchar(255) NOT NULL COMMENT 'Mailchimp Interest Grouping ID',
  `mailchimp_interest_group_id` varchar(255) DEFAULT NULL COMMENT 'Mailchimp Interest Group ID',
  PRIMARY KEY (`id`),
  CONSTRAINT FK_civimailchimp_interest_groups_sync_settings_id FOREIGN KEY (`civimailchimp_sync_settings_id`) REFERENCES `civimailchimp_sync_settings`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci;
