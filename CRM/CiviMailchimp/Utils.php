<?php

require_once 'vendor/autoload.php';

class CRM_CiviMailchimp_Utils {
  
  /**
   * Begin a connection with the Mailchimp API service and return the API
   * object.
   */
  static function initiateMailchimpApiCall() {
    $api_key = CRM_Core_BAO_Setting::getItem('CiviMailchimp Preferences', 'mailchimp_api_key');
    // Allow the Mailchimp class to use to be overridden, which helps for
    // automated tests.
    $mailchimp_class = CRM_Core_BAO_Setting::getItem('CiviMailchimp Preferences', 'mailchimp_api_class');
    if (!$mailchimp_class) {
      $mailchimp_class = 'CRM_CiviMailchimp';
    }
    $options = array('timeout' => 60);
    $config = CRM_Core_Config::singleton();
    if ($config->debug) {
      $options['debug'] = TRUE;
    }
    $mailchimp = new $mailchimp_class($api_key, $options);
    return $mailchimp;
  }

  /**
   * Get all of the lists with corresponding interest groups from Mailchimp 
   * and optionally allow filtering for specific lists.
   */
  static function getLists($list_ids = array()) {
    $lists = array();
    $mailchimp = self::initiateMailchimpApiCall();
    $result = $mailchimp->lists->getList(array(), 0, 100);
    if ($result['total'] > 0) {
      foreach ($result['data'] as $list) {
        if ($list['stats']['group_count']) {
          $list['interest_groups'] = self::getInterestGroups($list['id']);
        }
        $lists[$list['id']] = $list;
      }
    }
    if (!empty($list_ids)) {
      $filtered_lists = array();
      foreach ($list_ids as $id) {
        if (array_key_exists($id, $lists)) {
          $filtered_lists[$id] = $lists[$id];
        }
      }
      return $filtered_lists;
    }
    else {
      return $lists;
    }
  }

  /**
   * Get all interest groups for a specified Mailchimp list.
   */
  static function getInterestGroups($list_id) {
    $interest_groups = array();
    $mailchimp = self::initiateMailchimpApiCall();
    $mailchimp_interest_groups = $mailchimp->lists->interestGroupings($list_id);
    if ($mailchimp_interest_groups) {
      foreach ($mailchimp_interest_groups as $mailchimp_interest_grouping) {
        foreach ($mailchimp_interest_grouping['groups'] as $mailchimp_interest_group) {
          $interest_groups[$mailchimp_interest_grouping['id']][$mailchimp_interest_group['id']] = $mailchimp_interest_group['name'];
        }
      }
    }
    return $interest_groups;
  }

  /**
   * Format the array of Mailchimp lists returned from getLists() into a 
   * select field options array.
   */
  static function formatListsAsSelectOptions($mailchimp_lists) {
    $select_options = array('' => ts('- select a list -'));
    foreach ($mailchimp_lists as $mailchimp_list) {
      $select_options[$mailchimp_list['id']] = $mailchimp_list['name'];
    }
    return $select_options;
  }

  /**
   * Format the array of Mailchimp lists returned from getLists() into a
   * lookup array matching interest groups to their corresponding lists.
   */
  static function formatInterestGroupsLookup($mailchimp_lists) {
    $interest_groups_lookup = array();
    foreach ($mailchimp_lists as $mailchimp_list) {
      if (isset($mailchimp_list['interest_groups'])) {
        foreach ($mailchimp_list['interest_groups'] as $interest_grouping => $interest_groups) {
          foreach ($interest_groups as $interest_group_id => $interest_group_name) {
            $interest_group_key = "{$interest_grouping}_{$interest_group_id}";
            $interest_groups_lookup[$mailchimp_list['id']][$interest_group_key] = $interest_group_name;
          }
        }
      }
    }
    return $interest_groups_lookup;
  }

  /**
   * Get the merge fields configured for a particular list. If the merge fields
   * for a list are not specified, return the default merge fields.
   */
  static function getMailchimpMergeFields($list_id = NULL) {
    $merge_fields = array(
      'FNAME' => 'first_name',
      'LNAME' => 'last_name',
    );
    $custom_merge_fields = CRM_Core_BAO_Setting::getItem('CiviMailchimp Preferences', 'mailchimp_merge_fields'); 
    if ($custom_merge_fields) {
      if ($list_id && isset($custom_merge_fields[$list_id])) {
        $merge_fields = $custom_merge_fields[$list_id];
      }
    }

    return $merge_fields;
  }

  /**
   * Format the Mailchimp merge variables for an API request.
   */
  static function formatMailchimpMergeVars($merge_fields, $contact, $updated_mailchimp_email = NULL) {
    $merge_vars = array();
    foreach ($merge_fields as $merge_field => $civicrm_field) {
      $merge_vars[$merge_field] = $contact->$civicrm_field;
    }
    if ($updated_mailchimp_email) {
      $merge_vars['new-email'] = $updated_mailchimp_email;
    }

    return $merge_vars;
  }

  /**
   * Add the Interest Groups to the merge variables for an API request.
   *
   * We do this at the last possible point before an API request to prevent
   * the scenario where an Interest Group has been renamed in Mailchimp but the
   * Group sync setting has not been updated in CiviCRM yet. There is no
   * mechanism in Mailchimp for notifying via a webhook when the Interest Group
   * name changes. Also, annoyingly, Mailchimp requires sending the name of the
   * Interest Group, which can change, rather than the name, which doesn't.
   */
  static function interestGroupingsMergeVar($mailchimp_list_id) {
    $mailchimp_sync_setting = CRM_CiviMailchimp_BAO_SyncSettings::findByListId($mailchimp_list_id);
    if ($mailchimp_sync_setting->mailchimp_interest_groups) {
      $groupings_merge_var = array();
      foreach ($mailchimp_sync_setting->mailchimp_interest_groups as $interest_grouping => $interest_groups) {
        $groups = array();
        foreach ($interest_groups as $interest_group) {
          $groups[] = $interest_group->mailchimp_interest_group_name;
        }
        $groupings_merge_var[] = array(
          'id' => $interest_grouping,
          'groups' => $groups,
        );
      }

      return $groupings_merge_var;
    }
  }

  /**
   * Create a contact record with data from an incoming Mailchimp request.
   */
  static function createContactFromMailchimpRequest($request_data) {
    $params['contact_type'] = 'Individual';
    $location_type = CRM_Core_BAO_LocationType::getDefault();
    $params['email'][1] = array(
      'email' => $request_data['email'],
      'is_primary' => 1,
      'location_type_id' => $location_type->id,
    );
    $merge_fields = self::getMailchimpMergeFields($request_data['list_id']);
    foreach ($merge_fields as $merge_field => $civicrm_field) {
      if (!empty($request_data['merges'][$merge_field])) {
        $params[$civicrm_field] = $request_data['merges'][$merge_field];
      }
    }
    $contact = CRM_Contact_BAO_Contact::create($params);

    return $contact;
  }

  /**
   * Update a contact record with data from an incoming Mailchimp request.
   */
  static function updateContactFromMailchimpRequest($request_data, $contact) {
    // We have to go the circuitous route to saving so we can trigger
    // CiviCRM's hooks to allow other extensions to act.
    $params = array();
    CRM_Core_DAO::storeValues($contact, $params);
    $params['contact_id'] = $params['id'];
    $merge_fields = self::getMailchimpMergeFields($request_data['list_id']);
    foreach ($merge_fields as $merge_field => $civicrm_field) {
      if (!empty($request_data['merges'][$merge_field])) {
        $params[$civicrm_field] = $request_data['merges'][$merge_field];
      }
    }
    $contact = CRM_Contact_BAO_Contact::create($params);

    return $contact;
  }

  /**
   * Determine the appropriate email to use for Mailchimp for a given contact.
   */
  static function determineMailchimpEmailForContact($contact) {
    $mailchimp_email = NULL;
    if (!$contact->do_not_email && !$contact->is_opt_out && isset($contact->email)) {
      foreach ($contact->email as $email) {
        // We have to explicitly check for 'null' as the $contact object
        // included in hook_civicrm_post has 'null' in a bunch of places where
        // it should be NULL.
        if (!$email->on_hold || $email->on_hold === 'null') {
          if ($email->is_bulkmail && $email->is_bulkmail !== 'null') {
            $mailchimp_email = $email->email;
            continue;
          }
          elseif ($email->is_primary) {
            $mailchimp_email = $email->email;
          }
        }
      }
    }

    return $mailchimp_email;
  }

  /**
   * Check if a group has just been added for a contact.
   */
  static function contactAddedToGroup($group_id, $contact_id) {
    $query = "
      SELECT
        status
      FROM
        civicrm_group_contact
      WHERE
        group_id = %1
      AND
        contact_id = %2;
    ";
    $params = array(
      1 => array($group_id, 'Integer'),
      2 => array($contact_id, 'Integer')
    );
    $status = CRM_Core_DAO::singleValueQuery($query, $params);
    if ($status !== "Added") {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Get a Contact and Email DAO objects for a given contact id.
   */
  static function getContactById($contact_id) {
    $contact = new CRM_Contact_BAO_Contact();
    $contact->id = $contact_id;
    if (!$contact->find(TRUE)) {
      throw new CRM_Core_Exception("Could not find Contact record with ID {$contact_id}");
    }
    $emails = new CRM_Core_BAO_Email();
    $emails->contact_id = $contact->id;
    $emails->find();
    while ($emails->fetch()) {
      $email = clone $emails;
      $contact->email[] = $email;
    }
    return $contact;
  }

  /**
   * Get Contacts with the given email address, where the email is not
   * On Hold and is either the Primary or Bulk email.
   */
  static function getContactsWithPrimaryOrBulkEmail($email, $throw_exception = TRUE) {
    $query = "
      SELECT
        contact_id
      FROM
        civicrm_email
      WHERE
        email = %1
      AND
        on_hold = 0
      AND
      (
          is_primary = 1
        OR
          is_bulkmail = 1
      );
    ";
    $params = array(1 => array($email, 'String'));
    $result = CRM_Core_DAO::executeQuery($query, $params);
    $contact_ids = array();
    while ($result->fetch()) {
      $contact_ids[] = $result->contact_id;
    }
    $contacts = array();
    foreach ($contact_ids as $contact_id) {
      $contact = self::getContactById($contact_id);
      $mailchimp_email = self::determineMailchimpEmailForContact($contact);
      if ($email === $mailchimp_email) {
        $contacts[] = $contact;
      }
    }
    if (count($contacts) > 1) {
      CRM_Core_Error::debug_log_message(ts('There are %1 Contacts with the email %2. In order to limit potential syncing issues with Mailchimp, it is recommended that all but one Contact have this email marked as On Hold or have the email type changed from being the Primary or Bulk Mailings email address.', array(1 => count($contacts), 2 => $email)));
    }
    if (empty($contacts) && $throw_exception) {
      throw new CRM_Core_Exception("Could not find contact record with the email {$email}.");
    }

    return $contacts;
  }

  /**
   * Given an array of Contacts, return the first contact in the given
   * Mailchimp list.
   */
  static function getContactInMailchimpListByEmail($email, $mailchimp_list_id) {
    $contacts = self::getContactsWithPrimaryOrBulkEmail($email);
    $mailchimp_sync_settings = CRM_CiviMailchimp_BAO_SyncSettings::findByListId($mailchimp_list_id);
    $civicrm_group_id = $mailchimp_sync_settings->civicrm_group_id;
    $mailchimp_contact = NULL;
    foreach ($contacts as $key => $contact) {
      if (CRM_Contact_BAO_GroupContact::isContactInGroup($contact->id, $civicrm_group_id)) {
        $mailchimp_contact = $contact;
        break;
      }
    }
    if (!$mailchimp_contact) {
      throw new CRM_Core_Exception("Contact record with email {$email} not found in group ID {$civicrm_group_id}.");
    }

    return $mailchimp_contact;
  }

  /**
   * Get an Email for a given email id.
   */
  static function getEmailbyId($email_id) {
    $email = new CRM_Core_BAO_Email();
    $email->id = $email_id;
    if (!$email->find(TRUE)) {
      throw new CRM_Core_Exception("Could not find Email record with ID {$contact_id}");
    }
    return $email;
  }

  /**
   * Get Group DAO object for a given group id.
   */
  static function getGroupById($group_id) {
    $group = new CRM_Contact_BAO_Group();
    $group->id = $group_id;
    if (!$group->find(TRUE)) {
      throw new CRM_Core_Exception("Could not find Group record with ID {$group_id}");
    }
    return $group;
  }

  /**
   * Add a given contact to the group set to sync with the given
   * Mailchimp list.
   */
  static function addContactToGroup($contact, $mailchimp_list_id) {
    $contact_ids = array($contact->id);
    $mailchimp_sync_settings = CRM_CiviMailchimp_BAO_SyncSettings::findByListId($mailchimp_list_id);
    CRM_Contact_BAO_GroupContact::addContactsToGroup($contact_ids, $mailchimp_sync_settings->civicrm_group_id);
  }

  /**
   * Remove a given contact from the group set to sync with the given
   * Mailchimp list.
   */
  static function removeContactFromGroup($contact, $mailchimp_list_id) {
    $contact_ids = array($contact->id);
    $mailchimp_sync_settings = CRM_CiviMailchimp_BAO_SyncSettings::findByListId($mailchimp_list_id);
    CRM_Contact_BAO_GroupContact::removeContactsFromGroup($contact_ids, $mailchimp_sync_settings->civicrm_group_id);
  }

  /**
   * Add a Mailchimp Webhook for the specified list.
   */
  static function addWebhookToMailchimpList($list_id) {
    $webhook_url = self::formatMailchimpWebhookUrl();
    $mailchimp = self::initiateMailchimpApiCall();
    $result = $mailchimp->lists->webhookAdd($list_id, $webhook_url);
    return $result;
  }

  /**
   * Delete a Mailchimp Webhook from the specified list.
   */
  static function deleteWebhookFromMailchimpList($list_id) {
    $webhook_url = self::formatMailchimpWebhookUrl();
    $mailchimp = self::initiateMailchimpApiCall();
    $result = $mailchimp->lists->webhookDel($list_id, $webhook_url);
    return $result;
  }

  /**
   * Format Mailchimp Webhook url.
   */
  static function formatMailchimpWebhookUrl() {
    $base_url = CRM_Core_BAO_Setting::getItem('CiviMailchimp Preferences', 'mailchimp_webhook_base_url');
    if (!$base_url) {
      $base_url = CIVICRM_UF_BASEURL;
    }
    $base_url = CRM_Utils_File::addTrailingSlash($base_url);
    $site_key = self::getSiteKey();
    $webhook_url = "{$base_url}civicrm/mailchimp/webhook?key={$site_key}";
    return $webhook_url;
  }

  /**
   * Get CIVICRM_SITE_KEY and throw exception if it is not set.
   */
  static function getSiteKey() {
    $site_key = defined('CIVICRM_SITE_KEY') ? CIVICRM_SITE_KEY : NULL;
    if (!$site_key) {
      throw new CRM_Core_Exception("You need to set a valid site key in civicrm.settings.php for Mailchimp to be able to communicate with CiviCRM using Mailchimp Webhooks.");
    }
    return $site_key;
  }

  /**
   * Add a mailchimp sync item to the queue.
   */
  static function addMailchimpSyncQueueItem($action, $mailchimp_list_id, $email, $merge_vars = array()) {
    $queue = CRM_Queue_Service::singleton()->create(array(
      'type' => 'Sql',
      'name' => 'mailchimp-sync',
      'reset' => FALSE,
    ));
    $queue->createItem(new CRM_Queue_Task(
      array('CRM_CiviMailchimp_Utils', 'processCiviMailchimpQueueItem'),
      array($action, $mailchimp_list_id, $email, $merge_vars)
    ));
  }

  /**
   * Process a CiviMailchimp Queue Item.
   */
  static function processCiviMailchimpQueueItem(CRM_Queue_TaskContext $ctx, $action, $mailchimp_list_id, $email, $merge_vars) {
    $result = NULL;
    $function_name = "self::{$action}";
    if (is_callable($function_name)) {
      $result = call_user_func($function_name, $mailchimp_list_id, $email, $merge_vars);
    }

    return $result;
  }

  /**
   * Subscribe a Contact to a Mailchimp List.
   */
  static function subscribeContactToMailchimpList($mailchimp_list_id, $email, $merge_vars) {
    $email = array('email' => $email);
    $merge_vars['groupings'] = self::interestGroupingsMergeVar($mailchimp_list_id);
    $mailchimp = self::initiateMailchimpApiCall();
    $result = $mailchimp->lists->subscribe($mailchimp_list_id, $email, $merge_vars, $email_type = 'html', $double_optin = FALSE, $update_existing = TRUE);

    return $result;
  }

  /**
   * Unsubscribe a Contact from a Mailchimp List.
   */
  static function unsubscribeContactFromMailchimpList($mailchimp_list_id, $email, $merge_vars = array()) {
    $email = array('email' => $email);
    $mailchimp = self::initiateMailchimpApiCall();
    $result = $mailchimp->lists->unsubscribe($mailchimp_list_id, $email, $delete_member = FALSE, $send_goodbye = FALSE, $send_notify = FALSE);

    return $result;
  }

  /**
   * Update a Contact's info in Mailchimp.
   */
  static function updateContactProfileInMailchimp($mailchimp_list_id, $email, $merge_vars) {
    $email = array('email' => $email);
    $merge_vars['groupings'] = self::interestGroupingsMergeVar($mailchimp_list_id);
    $mailchimp = self::initiateMailchimpApiCall();
    $result = $mailchimp->lists->updateMember($mailchimp_list_id, $email, $merge_vars);

    return $result;
  }

  /**
   * Get all members of a Mailchimp List.
   *
   * We do this using Mailchimp Export API as the standard API has a 100 member
   * return limit: https://apidocs.mailchimp.com/export/1.0/list.func.php
   */
  static function getAllMembersOfMailchimpList($list_id) {
    $url = self::formatMailchimpExportApiUrl($list_id);
    $file_path = self::retrieveMailchimpMemberExportFile($url, $list_id);
    $members = self::extractMembersFromMailchimpExportFile($file_path, $list_id);
    self::deleteMailchimpMemberExportFile($file_path);

    return $members;
  }

  static function formatMailchimpExportApiUrl($list_id) {
    $api_key = CRM_Core_BAO_Setting::getItem('CiviMailchimp Preferences', 'mailchimp_api_key');
    $data_center = 'us1';
    if (preg_match('/-(.+)$/', $api_key, $matches)) {
      $data_center = $matches[1];
    }
    $url = "http://{$data_center}.api.mailchimp.com/export/1.0/list?apikey={$api_key}&id={$list_id}";

    return $url;
  }

  /**
   * Download the Mailchimp export file to a temporary folder.
   */
  static function retrieveMailchimpMemberExportFile($url, $list_id) {
    $config = CRM_Core_Config::singleton();
    $timestamp = microtime();
    $temp_dir = CRM_Utils_File::addTrailingSlash($config->uploadDir);
    $file_path = "{$temp_dir}mailchimp_export_{$list_id}_{$timestamp}.tmp";
    $file = fopen($file_path, 'w');
    if ($file === FALSE) {
      throw new CRM_Core_Exception("Unable to open the temporary Mailchimp Export file located at {$file_path}.");
    }
    $ch = curl_init($url);
    if ($ch === FALSE) {
      $err_number = curl_errno($ch);
      $err_string = curl_error($ch);
      throw new CRM_Core_Exception("cURL failed to initiate for the url {$url}. cURL error # {$err_number}: {$err_string}.");
    }
    curl_setopt($ch, CURLOPT_TIMEOUT, 50);
    curl_setopt($ch, CURLOPT_FILE, $file);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    $data = curl_exec($ch);
    if ($data === FALSE) {
      $err_number = curl_errno($ch);
      $err_string = curl_error($ch);
      throw new CRM_Core_Exception("cURL failed to retrieve data from the url {$url}. cURL error # {$err_number}: {$err_string}.");
    }
    curl_close($ch);
    fclose($file);

    return $file_path;
  }

  /**
   * Extract member data from the Mailchimp export file.
   */
  static function extractMembersFromMailchimpExportFile($file_path, $list_id) {
    $file = fopen($file_path, 'r');
    if ($file === FALSE) {
      throw new CRM_Core_Exception("Unable to access Mailchimp export file at the following path: {$file_path}");
    }
    $i = 0;
    $header = array();
    $members = array();
    while (!feof($file)) {
      $buffer = fgets($file, 4096);
      if ($buffer === FALSE && !feof($file)) {
        throw new CRM_Core_Exception("There was an error reading the Mailchimp export file at the path {$file_path}: " . print_r(error_get_last(), TRUE));
      }
      if (trim($buffer) != ''){
        $row = json_decode($buffer);
        if ($row === NULL) {
          throw new CRM_Core_Exception("Unable to decode JSON string from Mailchimp export file at {$file_path}: {$buffer}");
        }
        if (count($row) < 3) {
          throw new CRM_Core_Exception("Error processing the Mailchimp export file located at {$file_path}. The following record has less than the required number of items: " . print_r($row, TRUE));
        }
        // Ignore the header row.
        if ($i != 0) {
          // We only use the email, first name and last name fields. We also
          // format this to match the standard webhook request_data format so
          // we can use the same utility functions to process the new members.
          $members[] = array(
            'email' => $row[0],
            'list_id' => $list_id,
            'merges' => array(
              'FNAME' => $row[1],
              'LNAME' => $row[2],
            ),
          );
        }
        $i++;
      }
    }
    fclose($file);

    return $members;
  }

  /**
   * Delete the temporary Mailchimp export file.
   */
  static function deleteMailchimpMemberExportFile($file_path) {
    $result = unlink($file_path);
    if (!$result) {
      throw new CRM_Core_Exception("Unable to delete the temporary Mailchimp export file located at {$file_path}: " . print_r(error_get_last(), TRUE));
    }

    return $result;
  }

  /**
   * Add a Scheduled Job of Syncing from CiviCRM to Mailchimp.
   *
   * CiviCRM 4.2 does not have a BAO or API method for adding a scheduled job,
   * so we're forced to do it ourselves.
   */
  static function createSyncScheduledJob() {
    $domain_id = CRM_Core_Config::domainID();
    $params = array(
      'domain_id' => $domain_id,
      'run_frequency' => 'Always',
      'name' => 'Sync Contacts to Mailchimp',
      'description' => 'Sync CiviCRM Contacts to Mailchimp Lists.',
      'api_entity' => 'CiviMailchimp',
      'api_action' => 'sync',
      'parameters' => 'records_to_process_per_run=100',
      'is_active' => 0,
    );
    $job = new CRM_Core_BAO_Job();
    $job->copyValues($params);
    return $job->save();
  }
}
