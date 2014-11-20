<?php

class CRM_CiviMailchimp_BAO_InterestGroupsSyncSettings extends CRM_CiviMailchimp_DAO_InterestGroupsSyncSettings {

  /**
   * Find all Interest Groupings and Groups by the Sync Settings ID.
   */
  static function findBySyncSettingsId($sync_settings_id) {
    $civimailchimp_interest_groups = new CRM_CiviMailchimp_BAO_InterestGroupsSyncSettings();
    $civimailchimp_interest_groups->civimailchimp_sync_settings_id = $sync_settings_id;
    $civimailchimp_interest_groups->find();
    $interest_groups = array();
    while ($civimailchimp_interest_groups->fetch()) {
      $interest_groups[$civimailchimp_interest_groups->mailchimp_interest_grouping_id][] = $civimailchimp_interest_groups->mailchimp_interest_group_id;
    }
    return $interest_groups;
  }

  static function deleteAllForSyncSettingsId($sync_settings_id) {
    $query = "
      DELETE FROM
        civimailchimp_interest_groups_sync_settings
      WHERE
        civimailchimp_sync_settings_id = %1;
    ";
    $params = array(1 => array($sync_settings_id, 'Integer'));
    $result = CRM_Core_DAO::executeQuery($query, $params);
    return $result;
  }
}
