<?php

require_once 'CRM/Core/Page.php';

class CRM_CiviMailchimp_Page_ClearAllMessages extends CRM_Core_Page {
  function run() {
    CRM_CiviMailchimp_BAO_SyncLog::clearAllMessages();
    $session = CRM_Core_Session::singleton();
    $session->setStatus(ts("All unread CiviMailchimp log messages cleared."), '', 'success');
    $url = CRM_Utils_System::url('civicrm/admin/mailchimp/log', 'reset=1');
    CRM_Utils_System::redirect($url);
    parent::run();
  }
}
