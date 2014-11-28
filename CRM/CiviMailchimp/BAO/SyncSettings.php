<?php

class CRM_CiviMailchimp_BAO_SyncSettings extends CRM_CiviMailchimp_DAO_SyncSettings {

  public $mailchimp_interest_groups = array();

  /**
   * Find Mailchimp sync settings by Group ID.
   */
  static function findByGroupId($group_id) {
    $mailchimp_sync_setting = new CRM_CiviMailchimp_BAO_SyncSettings();
    $mailchimp_sync_setting->civicrm_group_id = $group_id;
    $mailchimp_sync_setting->find(TRUE);
    if (empty($mailchimp_sync_setting->id)) {
      return NULL;
    }
    $mailchimp_sync_setting->mailchimp_interest_groups = CRM_CiviMailchimp_BAO_InterestGroupsSyncSettings::findBySyncSettingsId($mailchimp_sync_setting->id);
    return $mailchimp_sync_setting;
  }

  /**
   * Find Mailchimp sync settings by list ID.
   */
  static function findByListId($list_id) {
    $mailchimp_sync_setting = new CRM_CiviMailchimp_BAO_SyncSettings();
    $mailchimp_sync_setting->mailchimp_list_id = $list_id;
    $mailchimp_sync_setting->find(TRUE);
    if (empty($mailchimp_sync_setting->id)) {
      return NULL;
    }
    $mailchimp_sync_setting->mailchimp_interest_groups = CRM_CiviMailchimp_BAO_InterestGroupsSyncSettings::findBySyncSettingsId($mailchimp_sync_setting->id);
    return $mailchimp_sync_setting;
  }

  /**
   * Get the Mailchimp sync settings for a contact's groups.
   */
  static function findByContactId($contact_id) {
    $query = "
      SELECT
        civimailchimp_sync_settings.id,
        civimailchimp_sync_settings.civicrm_group_id,
        civimailchimp_sync_settings.mailchimp_list_id
      FROM
        civimailchimp_sync_settings
      JOIN
        civicrm_group_contact ON (civimailchimp_sync_settings.civicrm_group_id = civicrm_group_contact.group_id)
      WHERE
        civicrm_group_contact.status = 'Added'
      AND
        civicrm_group_contact.contact_id = %1;
    ";
    $params = array(1 => array($contact_id, 'Integer'));
    $result = CRM_Core_DAO::executeQuery($query, $params, TRUE, 'CRM_CiviMailchimp_BAO_SyncSettings');
    $mailchimp_sync_settings = array();
    while ($result->fetch()) {
      $mailchimp_sync_setting = clone $result;
      $mailchimp_sync_setting->mailchimp_interest_groups = CRM_CiviMailchimp_BAO_InterestGroupsSyncSettings::findBySyncSettingsId($mailchimp_sync_setting->id);
      $mailchimp_sync_settings[$mailchimp_sync_setting->civicrm_group_id] = $mailchimp_sync_setting;
    }
    return $mailchimp_sync_settings;
  }

  /**
   * Save Mailchimp sync settings.
   */
  static function saveSettings($params) {
    $transaction = new CRM_Core_Transaction();
    try {
      $existing_mailchimp_sync_setting = self::findByGroupId($params['civicrm_group_id']);
      if ($existing_mailchimp_sync_setting) {
        $params['id'] = $existing_mailchimp_sync_setting->id;
        CRM_CiviMailchimp_BAO_InterestGroupsSyncSettings::deleteAllForSyncSettingsId($existing_mailchimp_sync_setting->id);
      }
      $mailchimp_sync_setting = new CRM_CiviMailchimp_BAO_SyncSettings();
      $mailchimp_sync_setting->copyValues($params);
      $mailchimp_sync_setting->save();
      if (isset($params['mailchimp_interest_groups'])) {
        foreach ($params['mailchimp_interest_groups'] as $mailchimp_interest_group) {
          $mailchimp_interest_group = explode('_', $mailchimp_interest_group);
          $mailchimp_interest_group_sync_setting = new CRM_CiviMailchimp_BAO_InterestGroupsSyncSettings();
          $mailchimp_interest_group_sync_setting->civimailchimp_sync_settings_id = $mailchimp_sync_setting->id;
          $mailchimp_interest_group_sync_setting->mailchimp_interest_grouping_id = $mailchimp_interest_group[0];
          $mailchimp_interest_group_sync_setting->mailchimp_interest_group_id = $mailchimp_interest_group[1];
          $mailchimp_interest_group_sync_setting->save();
        }
      }
      if (!$existing_mailchimp_sync_setting) {
        CRM_CiviMailchimp_Utils::addWebhookToMailchimpList($mailchimp_sync_setting->mailchimp_list_id);
      }
    }
    catch (Exception $e) {
      $transaction->rollback();
      throw $e;
    }
    $transaction->commit();
    return $mailchimp_sync_setting;
  }

  /**
   * Delete Mailchimp sync settings.
   */
  static function deleteSettings($params) {
    $transaction = new CRM_Core_Transaction();
    try {
      $mailchimp_sync_settings = self::findByGroupId($params['civicrm_group_id']);
      if ($mailchimp_sync_settings) {
        $mailchimp_sync_settings->delete();
        CRM_CiviMailchimp_Utils::deleteWebhookFromMailchimpList($mailchimp_sync_settings->mailchimp_list_id);
      }
    }
    catch (Exception $e) {
      $transaction->rollback();
      throw $e;
    }
    $transaction->commit();
    return $mailchimp_sync_settings;
  }
}
