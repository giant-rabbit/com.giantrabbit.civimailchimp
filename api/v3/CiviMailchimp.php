<?php

function civicrm_api3_civi_mailchimp_sync($params) {
  CRM_CiviMailchimp_BAO_SyncLog::deleteOldMessages();
  $records_to_process = $params['records_to_process_per_run'];
  $queue = CRM_Queue_Service::singleton()->create(array(
    'type' => 'Sql',
    'name' => 'mailchimp-sync',
    'reset' => FALSE,
  ));
  if ($queue->numberOfItems() > 0) {
    $runner = new CRM_Queue_Runner(array(
      'title' => ts('Sync Contacts to Mailchimp'),
      'queue' => $queue,
    ));
    $continue_to_next_item = TRUE;
    $records_processed = 0;
    while ($continue_to_next_item && $records_processed < $records_to_process) {
      $record = $runner->runNext();
      if ($record['is_error']) {
        // Get the current Queue Item being worked on to allow for better error
        // reporting and logging.
        $query = "
          SELECT
            data
          FROM
            civicrm_queue_item
          WHERE
            queue_name = 'mailchimp-sync'
          ORDER BY
            weight ASC,
            id ASC
          LIMIT 1
        ";
        $item = CRM_Core_DAO::singleValueQuery($query);
        $item_data = unserialize($item);
        $message = "[{$item_data->arguments[0]}] There was an error syncing contacts to Mailchimp.";
        $exception_name = '';
        if (!empty($record['exception'])) {
          $exception_name = get_class($record['exception']);
          $message = "[{$item_data->arguments[0]}] {$exception_name}: {$record['exception']->getMessage()}.";
        }
        $message .= " Mailchimp List ID: {$item_data->arguments[1]}. {$records_processed} records were successfully synced before this error.";
        $error = array(
          'code' => $exception_name,
          'message' => $message,
          'exception' => $record['exception'],
        );
        CRM_Core_Error::debug_var('Fatal Error Details', $error);
        CRM_Core_Error::backtrace('backTrace', TRUE);
        CRM_CiviMailchimp_BAO_SyncLog::saveMessage('error', 'civicrm_to_mailchimp', $message, $item_data);

        return civicrm_api3_create_error($message);
      }
      $continue_to_next_item = $record['is_continue'];
      $records_processed++;
    }
    $message = ts('%1 records were successfully synced to Mailchimp.', array(1 => $records_processed));
    CRM_CiviMailchimp_BAO_SyncLog::saveMessage('success', 'civicrm_to_mailchimp', $message);

    return civicrm_api3_create_success($records_processed);
  }
}

