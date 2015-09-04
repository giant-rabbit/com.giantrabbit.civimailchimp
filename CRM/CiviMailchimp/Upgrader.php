<?php

/**
 * Collection of upgrade steps
 */
class CRM_CiviMailchimp_Upgrader extends CRM_CiviMailchimp_Upgrader_Base {

  /**
   * Add a civicrm_queue_item_id column to the civimailchimp_sync_log table.
   *
   * @return TRUE on success
   * @throws Exception
   */
  public function upgrade_1200() {
    $this->ctx->log->info('Applying update 1200.');
    $query = '
      ALTER TABLE
        civimailchimp_sync_log
      ADD
        civicrm_queue_item_id INT UNSIGNED DEFAULT NULL
      AFTER
        cleared
    ';
    CRM_Core_DAO::executeQuery($query);
    $query = '
      ALTER TABLE
        civimailchimp_sync_log
      ADD CONSTRAINT
        FK_civimailchimp_sync_log_queue_item_id
      FOREIGN KEY
        (civicrm_queue_item_id)
      REFERENCES
        civicrm_queue_item(id)
      ON DELETE SET NULL
    ';
    CRM_Core_DAO::executeQuery($query);
    return TRUE;
  }
}
