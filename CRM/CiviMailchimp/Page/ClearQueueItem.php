<?php

require_once 'CRM/Core/Page.php';

class CRM_CiviMailchimp_Page_ClearQueueItem extends CRM_Core_Page {

  function run() {
    parent::run();
  }

  static function clear() {
    $message_id = CRM_Utils_Request::retrieve('id', 'Integer');
    $civicrm_queue_item_id = CRM_Utils_Request::retrieve('qid', 'Integer');
    try {
      CRM_CiviMailchimp_BAO_SyncLog::clearQueueItem($civicrm_queue_item_id);
      CRM_CiviMailchimp_BAO_SyncLog::clearMessage($message_id);
    }
    catch (Exception $e) {
      $error = array(
        'status' => 'error',
        'code' => get_class($e),
        'message' => $e->getMessage(),
        'exception' => $e
      );
      // CRM-11831 @see http://www.malsup.com/jquery/form/#file-upload
      if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest') {
        header('Content-Type: application/json');
      }
      echo json_encode($error);
      CRM_Core_Error::debug_var('Fatal Error Details', $error);
      CRM_Core_Error::backtrace('backTrace', TRUE);
      CRM_Utils_System::civiExit();
    }
  }
}
