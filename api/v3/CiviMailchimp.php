<?php

function civicrm_api3_civi_mailchimp_sync($params) {
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
        $message = ts('[error] There was an error syncing contacts to Mailchimp.');
        if (!empty($record['exception'])) {
          $exception_name = get_class($record['exception']);
          $message = "[error] {$exception_name}: {$record['exception']->getMessage()}.";
        }
        $message .= ts(' %1 records were successfully synced before this error.', array(1 => $records_processed));
        CRM_Core_Error::debug_log_message($message);
        return civicrm_api3_create_error($message);
      }
      $continue_to_next_item = $record['is_continue'];
      $records_processed++;
    }
    return civicrm_api3_create_success($records_processed);
  }
}

