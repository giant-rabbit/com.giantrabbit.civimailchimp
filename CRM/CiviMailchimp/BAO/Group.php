<?php

class CRM_CiviMailchimp_BAO_Group extends CRM_CiviMailchimp_DAO_Group {
  /**
   * Find a Mailchimp Group sync configuration by Group ID.
   */
  static function getSyncSettingsByGroupId($group_id) {
    $civimailchimp_group = new CRM_CiviMailchimp_BAO_Group();
    $civimailchimp_group->civicrm_group_id = $group_id;
    $civimailchimp_group->find(TRUE);
    if (empty($civimailchimp_group->id)) {
      return NULL;
    }
    return $civimailchimp_group;
  }

  /**
   * Add or update a Mailchimp Group sync configuration entry.
   */
  static function updateSettings($params) {
    $transaction = new CRM_Core_Transaction();
    try {
      $existing_group = self::findByGroupId($params['civicrm_group_id']);
      if ($existing_group) {
        $params['id'] = $existing_group->id;
      }
      $civimailchimp_group = new CRM_CiviMailchimp_DAO_Group();
      $civimailchimp_group->copyValues($params);
      $civimailchimp_group->save();
    } catch (Exception $e) {
      $transaction->rollback();
      throw $e;
    }
    $transaction->commit();
  }

  /**
   * Delete a Mailchimp Group sync configuration entry.
   */
  static function deleteSettings($params) {
    $transaction = new CRM_Core_Transaction();
    try {
      $civimailchimp_group = self::findByGroupId($params['civicrm_group_id']);
      $civimailchimp_group->delete();
    } catch (Exception $e) {
      $transaction->rollback();
      throw $e;
    }
    $transaction->commit();
  }

  /**
   * Get the Mailchimp sync settings for a contact's groups.
   */
  static function getMailchimpSyncSettingsByContactId($contact_id) {
    $query = "
      SELECT
        *
      FROM
        civimailchimp_group
      JOIN
        civicrm_group_contact ON (civimailchimp_group.civicrm_group_id = civicrm_group_contact.group_id)
      WHERE
        civicrm_group_contact.status = 'Added'
      AND
        civicrm_group_contact.contact_id = %1;
    ";
    $params = array(1 => array($contact_id, 'Integer'));
    $result = CRM_Core_DAO::executeQuery($query, $params, TRUE, 'CRM_CiviMailchimp_DAO_Group');
    $groups = array();
    while ($result->fetch()) {
      $group = clone $result;
      $groups[$group->group_id] = $group;
    }
    return $groups;
  }
}
