-- drop extension tables
DROP TABLE IF EXISTS civimailchimp_sync_settings;
DROP TABLE IF EXISTS civimailchimp_interest_groups_sync_settings;

-- delete queue items
DELETE FROM civicrm_queue_item WHERE queue_name = 'mailchimp-sync';

-- delete job entry
-- DELETE FROM `civicrm_job` WHERE name = 'CiviMailchimp Sync';
