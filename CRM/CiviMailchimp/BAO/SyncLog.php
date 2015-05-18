<?php

class CRM_CiviMailchimp_BAO_SyncLog extends CRM_CiviMailchimp_DAO_SyncLog {
  
  static function saveMessage($type, $direction, $message, $details = NULL) {
    $mailchimp_sync_log = new CRM_CiviMailchimp_BAO_SyncLog();
    $mailchimp_sync_log->type = $type;
    $mailchimp_sync_log->direction = $direction;
    $mailchimp_sync_log->message = $message;
    $mailchimp_sync_log->details = serialize($details);
    $mailchimp_sync_log->timestamp = time();
    $mailchimp_sync_log->save();

    return $mailchimp_sync_log;
  }

  static function getLatestUnclearedCiviToMailchimpErrorMessage() {
    $query = "
      SELECT
        *
      FROM
        civimailchimp_sync_log
      WHERE
        direction = 'civicrm_to_mailchimp'
      ORDER BY
        id DESC
      LIMIT 1;
    ";
    $message = NULL;
    $mailchimp_sync_log = CRM_Core_DAO::executeQuery($query);
    if ($mailchimp_sync_log->fetch() && $mailchimp_sync_log->type === 'error' && $mailchimp_sync_log->cleared != 1) {
      $message = self::formatMessage($mailchimp_sync_log);
    }

    return $message;
  }

  static function getUnclearedMailchimpToCiviErrorMessages() {
    $query = "
      SELECT
        *
      FROM
        civimailchimp_sync_log
      WHERE
        direction = 'mailchimp_to_civicrm'
      AND
        cleared != 1
      AND
        type = 'error'
      ORDER BY
        id DESC;
    ";
    $messages = array();
    $mailchimp_sync_log = CRM_Core_DAO::executeQuery($query);
    while ($mailchimp_sync_log->fetch()) {
      $message = self::formatMessage($mailchimp_sync_log);
      $messages[] = $message;
    }

    return $messages;
  }

  static function formatMessage($mailchimp_sync_log) {
    $message = $mailchimp_sync_log->message;
    $message .= ts('<br /><br />Additional details can be found at the %1.', array(1 => CRM_Utils_System::href('CiviMailchimp Sync Log', 'civicrm/admin/mailchimp/log')));
    $url =  CRM_Utils_System::url('civicrm/admin/mailchimp/log/clear-message', array('id' => $mailchimp_sync_log->id));
    $message .= ts('<br /><br /><a class="clear-link" href="%1">Do not show this message again.</a>', array(1 => $url));
    return $message;
  }

  static function deleteOldMessages() {
    $query = "
      SELECT
        timestamp
      FROM 
        civimailchimp_sync_log
      ORDER BY
        timestamp DESC
      LIMIT 1 OFFSET 99;
    ";
    $timestamp = CRM_Core_DAO::singleValueQuery($query);
    if ($timestamp) {
      $query = "
        DELETE FROM
          civimailchimp_sync_log
        WHERE 
          timestamp < {$timestamp};
      ";
      CRM_Core_DAO::executeQuery($query);
    }
  }

  static function findById($message_id) {
    $mailchimp_sync_log = new CRM_CiviMailchimp_BAO_SyncLog();
    $mailchimp_sync_log->id = $message_id;
    if (!$mailchimp_sync_log->find(TRUE)) {
      throw new CRM_CiviMailchimp_Exception("Could not find CiviMailchimp Sync log message with ID {$message_id}.");
    }

    return $mailchimp_sync_log;
  }

  static function clearMessage($message_id) {
    $mailchimp_sync_log = self::findById($message_id);
    $mailchimp_sync_log->cleared = 1;
    $mailchimp_sync_log->save();
  }

  static function clearAllMessages() {
    $query = "
      UPDATE
        civimailchimp_sync_log
      SET
        cleared = 1
      WHERE
        cleared = 0;
    ";
    CRM_Core_DAO::executeQuery($query);
  }

  static function renderMessages() {
    $civi_to_mailchimp_log_message = CRM_CiviMailchimp_BAO_SyncLog::getLatestUnclearedCiviToMailchimpErrorMessage();
    $session = CRM_Core_Session::singleton();
    if ($civi_to_mailchimp_log_message) {
      $session->setStatus($civi_to_mailchimp_log_message, ts("Error Syncing CiviCRM to Mailchimp"), 'alert', array('expires' => 0));
    }
    $mailchimp_to_civi_log_messages = CRM_CiviMailchimp_BAO_SyncLog::getUnclearedMailchimpToCiviErrorMessages();
    foreach ($mailchimp_to_civi_log_messages as $message) {
      $session->setStatus($message, ts("Error Syncing Mailchimp to CiviCRM"), 'alert', array('expires' => 0));
    }
    CRM_Core_Resources::singleton()->addScriptFile('com.giantrabbit.civimailchimp', 'js/sync_log.js');
  }
}
