<?php

require_once 'CRM/Core/Page.php';

class CRM_CiviMailchimp_Page_SyncLog extends CRM_Core_Page {

  function run() {
    $rows = self::getAllRows();
    $this->assign('rows', $rows);
    CRM_Core_Resources::singleton()->addScriptFile('com.giantrabbit.civimailchimp', 'js/sync_log.js');
    parent::run();
  }

  static function getAllRows() {
    $query = "
      SELECT
        *
      FROM 
        civimailchimp_sync_log
      ORDER BY id DESC;
    ";
    $mailchimp_sync_log = CRM_Core_DAO::executeQuery($query);
    $rows = array();
    while ($mailchimp_sync_log->fetch()) {
      if ($mailchimp_sync_log->direction === 'civicrm_to_mailchimp') {
        $direction = 'CiviCRM to Mailchimp';
      }
      elseif ($mailchimp_sync_log->direction === 'mailchimp_to_civicrm') {
        $direction = 'Mailchimp to CiviCRM';
      }
      $details = unserialize($mailchimp_sync_log->details);
      $details = print_r($details, TRUE);
      $timestamp = date('c', $mailchimp_sync_log->timestamp);
      $timestamp = CRM_Utils_Date::customFormat($timestamp);
      $rows[] = array(
        'id' => $mailchimp_sync_log->id,
        'type' => ucfirst($mailchimp_sync_log->type),
        'direction' => $direction,
        'message' => $mailchimp_sync_log->message,
        'details' => $details,
        'timestamp' => $timestamp,
      );
    }

    return $rows;
  }
}
