<?php

require_once 'CRM/Core/Page.php';

class CRM_CiviMailchimp_Page_Webhook extends CRM_Core_Page {
  function run() {
    if (CRM_Utils_System::authenticateKey()) {
      parent::run();

      $request_type = CRM_Utils_Request::retrieve('type', 'String');
      $request_data = CRM_Utils_Request::retrieve('data', 'String');

      $config = CRM_Core_Config::singleton();
      if ($config->debug) {
        $request_data_log = print_r($request_data, TRUE);
        CRM_Core_Error::debug_log_message("Mailchimp Webhook Request [{$request_type}]: \n{$request_data_log}");
      }

      $function_name = 'self::mailchimpWebhook' . ucwords($request_type);
      if (is_callable($function_name)) {
        call_user_func($function_name, $request_data);
      }
    }
  }

  /**
   * Add a Mailchimp subscriber to a CiviCRM Group.
   */
  static function mailchimpWebhookSubscribe($request_data) {
  }

  /**
   * Remove a Mailchimp subscriber from a CiviCRM Group.
   */
  static function mailchimpWebhookUnsubscribe($request_data) {
  }

  /**
   * Update a Mailchimp subscriber's email in CiviCRM.
   */
  static function mailchimpWebhookUpemail($request_data) {
  }

  /**
   * Update a Mailchimp subscriber's Contact data in CiviCRM.
   */
  static function mailchimpWebhookProfile($request_data) {
  }

  /**
   * Put a Mailchimp subscriber's email On Hold in CiviCRM.
   */
  static function mailchimpWebhookCleaned($request_data) {
  }
}
