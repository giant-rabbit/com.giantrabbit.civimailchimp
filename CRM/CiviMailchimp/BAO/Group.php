<?php

class CRM_CiviMailchimp_BAO_Group extends CRM_CiviMailchimp_DAO_Group {
  /**
   * Find a CiviMailchimp Group sync configuration by Group ID.
   */
  static function findByGroupId($group_id) {
    $civimailchimp_group = new CRM_CiviMailchimp_BAO_Group();
    $civimailchimp_group->civicrm_group_id = $group_id;
    $civimailchimp_group->find(TRUE);
    if (empty($civimailchimp_group->id)) {
      return NULL;
    }
    return $civimailchimp_group;
  }

  /**
   * Add or update a CiviMailchimp Group sync configuration entry.
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
   * Delete a CiviMailchimp Group sync configuration entry.
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
}
