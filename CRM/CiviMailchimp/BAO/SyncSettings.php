<?php

class CRM_CiviMailchimp_BAO_SyncSettings extends CRM_CiviMailchimp_DAO_SyncSettings {
  /**
   * Find Mailchimp sync settings by Group ID.
   */
  static function findByGroupId($group_id) {
    $mailchimp_sync_settings = new CRM_CiviMailchimp_BAO_SyncSettings();
    $mailchimp_sync_settings->civicrm_group_id = $group_id;
    $mailchimp_sync_settings->find(TRUE);
    if (empty($mailchimp_sync_settings->id)) {
      return NULL;
    }
    return $mailchimp_sync_settings;
  }

  /**
   * Save Mailchimp sync settings.
   */
  static function saveSettings($params) {
    $transaction = new CRM_Core_Transaction();
    try {
      $existing_mailchimp_sync_settings = self::findByGroupId($params['civicrm_group_id']);
      if ($existing_mailchimp_sync_settings) {
        $params['id'] = $existing_mailchimp_sync_settings->id;
      }
      $mailchimp_sync_settings = new CRM_CiviMailchimp_BAO_SyncSettings();
      $mailchimp_sync_settings->copyValues($params);
      $mailchimp_sync_settings->save();
      if (!$existing_mailchimp_sync_settings) {
        CRM_CiviMailchimp_Utils::addWebhookToMailchimpList($mailchimp_sync_settings->mailchimp_list_id);
      }
    }
    catch (Exception $e) {
      $transaction->rollback();
      throw $e;
    }
    $transaction->commit();
    return $mailchimp_sync_settings;
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

  /**
   * Get the Mailchimp sync settings for a contact's groups.
   */
  static function findByContactId($contact_id) {
    $query = "
      SELECT
        *
      FROM
        mailchimp_sync_settings
      JOIN
        civicrm_group_contact ON (mailchimp_sync_settings.civicrm_group_id = civicrm_group_contact.group_id)
      WHERE
        civicrm_group_contact.status = 'Added'
      AND
        civicrm_group_contact.contact_id = %1;
    ";
    $params = array(1 => array($contact_id, 'Integer'));
    $result = CRM_Core_DAO::executeQuery($query, $params, TRUE, 'CRM_CiviMailchimp_DAO_Group');
    $mailchimp_sync_settings = array();
    while ($result->fetch()) {
      $mailchimp_sync_setting = clone $result;
      $mailchimp_sync_settings[$mailchimp_sync_setting->group_id] = $mailchimp_sync_setting;
    }
    return $mailchimp_sync_settings;
  }
}
