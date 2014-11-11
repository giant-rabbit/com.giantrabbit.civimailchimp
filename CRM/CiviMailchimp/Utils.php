<?php

require_once 'vendor/autoload.php';

class CRM_CiviMailchimp_Utils {
  
  /**
   * Begin a connection with the Mailchimp API service and return the API
   * object.
   */
  static function getApiObject() {
    $api_key = CRM_Core_BAO_Setting::getItem('CiviMailchimp Preferences', 'mailchimp_api_key');
    $options = array('timeout' => 60);
    $mailchimp = new Mailchimp($api_key, $options);
    return $mailchimp;
  }

  /**
   * Get all of the lists with corresponding interest groups from Mailchimp 
   * and optionally allow filtering for specific lists.
   */
  static function getLists($list_ids = array()) {
    $lists = array();
    $mailchimp = self::getApiObject();
    $result = $mailchimp->lists->getList(array(), 0, 100);
    if ($result['total'] > 0) {
      foreach ($result['data'] as $list) {
        if ($list['stats']['group_count']) {
          $list['interest_groups'] = $mailchimp->lists->interestGroupings($list['id']);
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
   * Format the array of Mailchimp lists returned from get_lists() into a 
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
   * Format the array of Mailchimp lists returned from get_lists() into a
   * lookup array matching interest groups to their corresponding lists.
   */
  static function formatInterestGroupsLookup($mailchimp_lists) {
    $interest_groups_lookup = array();
    foreach ($mailchimp_lists as $mailchimp_list) {
      if (isset($mailchimp_list['interest_groups'])) {
        foreach ($mailchimp_list['interest_groups'] as $interest_group_container) {
          foreach ($interest_group_container['groups'] as $interest_group) {
            $interest_groups_lookup[$mailchimp_list['id']][$interest_group['id']] = $interest_group['name'];
          }
        }
      }
    }
    return $interest_groups_lookup;
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
    dd($status, "status");
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
      throw new Exception("Could not find Contact record with ID {$contact_id}");
    }
    $emails = new CRM_Core_BAO_Email();
    $emails->contact_id = $contact->id;
    while ($emails->fetch()) {
      $email = clone $emails;
      $contact->email[] = $email;
    }
    return $contact;
  }

  /**
   * Get Group DAO object for a given group id.
   */
  static function getGroupById($group_id) {
    $group = new CRM_Contact_BAO_Group();
    $group->id = $group_id;
    if (!$group->find(TRUE)) {
      throw new Exception("Could not find Group record with ID {$group_id}");
    }
    return $group;
  }
}
