<?php

require_once 'CRM/Core/Page.php';

class CRM_CiviMailchimp_Page_Webhook extends CRM_Core_Page {
  function run() {
    if (CRM_Utils_System::authenticateKey()) {
      $request_type = CRM_Utils_Request::retrieve('type', 'String');
      $request_data = CRM_Utils_Request::retrieve('data', 'String');
      $config = CRM_Core_Config::singleton();
      if ($config->debug) {
        $request_data_log = print_r($request_data, TRUE);
        CRM_Core_Error::debug_log_message("Mailchimp Webhook Request [{$request_type}]: \n{$request_data_log}");
      }
      $function_name = 'self::mailchimpWebhook' . ucwords($request_type);
      if (is_callable($function_name)) {
        // Set a canary to prevent CiviMailchimp hooks from firing, which
        // would trigger updates back to Mailchimp, resulting in an endless
        // loop.
        civimailchimp_static('mailchimp_do_not_run_hooks', TRUE);
        try {
          call_user_func($function_name, $request_data);
        }
        catch (Exception $e) {
          $error = array(
            'code' => get_class($e),
            'message' => $e->getMessage(),
            'exception' => $e,
          );
          $message = "Mailchimp Webhook Request [{$request_type}]: {$error['code']}: {$error['message']}";
          CRM_CiviMailchimp_BAO_SyncLog::saveMessage('error', 'mailchimp_to_civicrm', $message, $request_data);
          CRM_Core_Error::debug_var('Fatal Error Details', $error);
          CRM_Core_Error::backtrace('backTrace', TRUE);
          throw $e;
        }
      }
    }
    parent::run();
  }

  /**
   * Add a Mailchimp subscriber to a CiviCRM Group.
   */
  static function mailchimpWebhookSubscribe($request_data) {
    $config = CRM_Core_Config::singleton();
    if ($config->debug) {
      $request_data_log = print_r($request_data, TRUE);
      CRM_Core_Error::debug_log_message("Mailchimp Webhook Request [subscribe]: \nSearching for contacts with the following data.\n{$request_data_log}");
    }
    $contacts = CRM_CiviMailchimp_Utils::getContactsWithPrimaryOrBulkEmail($request_data['email'], FALSE);
    if (empty($contacts)) {
      $mailchimp_contact = CRM_CiviMailchimp_Utils::createContactFromMailchimpRequest($request_data);
      if ($config->debug) {
        $request_data_log = print_r($mailchimp_contact, TRUE);
        CRM_Core_Error::debug_log_message("Mailchimp Webhook Request [subscribe]: \nExisting contact not found, so a new contact record was created with the following details.\n{$request_data_log}");
      }
    }
    else {
      $mailchimp_contact = $contacts[0];
      if ($config->debug) {
        $request_data_log = print_r($mailchimp_contact, TRUE);
        CRM_Core_Error::debug_log_message("Mailchimp Webhook Request [subscribe]: \nExisting contact found with the following details.\n{$request_data_log}");
      }
    }
    CRM_CiviMailchimp_Utils::addContactToGroup($mailchimp_contact, $request_data['list_id']);
  }

  /**
   * Remove a Mailchimp subscriber from a CiviCRM Group.
   */
  static function mailchimpWebhookUnsubscribe($request_data) {
    $mailchimp_contact = CRM_CiviMailchimp_Utils::getContactInMailchimpListByEmail($request_data['email'], $request_data['list_id']);
    CRM_CiviMailchimp_Utils::removeContactFromGroup($mailchimp_contact, $request_data['list_id']);
  }

  /**
   * Update a Mailchimp subscriber's email in CiviCRM.
   */
  static function mailchimpWebhookUpemail($request_data) {
    $mailchimp_contact = CRM_CiviMailchimp_Utils::getContactInMailchimpListByEmail($request_data['old_email'], $request_data['list_id']);
    foreach ($mailchimp_contact->email as $email) {
      if ($email->email === $request_data['old_email']) {
        // We have to go the circuitous route to saving so we can trigger
        // CiviCRM's hooks to allow other extensions to act.
        $params = array();
        CRM_Core_DAO::storeValues($email, $params);
        $params['email'] = $request_data['new_email'];
        CRM_Core_BAO_Email::add($params);
      }
    }
  }

  /**
   * Update a Mailchimp subscriber's Contact data in CiviCRM.
   */
  static function mailchimpWebhookProfile($request_data) {
    $mailchimp_contact = CRM_CiviMailchimp_Utils::getContactInMailchimpListByEmail($request_data['email'], $request_data['list_id']);
    CRM_CiviMailchimp_Utils::updateContactFromMailchimpRequest($request_data, $mailchimp_contact);
  }

  /**
   * Put a Mailchimp subscriber's email On Hold in CiviCRM.
   */
  static function mailchimpWebhookCleaned($request_data) {
    $mailchimp_contact = CRM_CiviMailchimp_Utils::getContactInMailchimpListByEmail($request_data['email'], $request_data['list_id']);
    foreach ($mailchimp_contact->email as $email) {
      if ($email->email === $request_data['email']) {
        // We have to go the circuitous route to saving so we can trigger
        // CiviCRM's hooks to allow other extensions to act.
        $params = array();
        CRM_Core_DAO::storeValues($email, $params);
        $params['on_hold'] = 1;
        CRM_Core_BAO_Email::add($params);
      }
    }
  }
}
