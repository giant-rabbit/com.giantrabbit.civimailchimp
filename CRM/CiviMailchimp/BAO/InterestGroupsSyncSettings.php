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
      $civimailchimp_interest_group = clone $civimailchimp_interest_groups;
      $interest_groups[$civimailchimp_interest_group->mailchimp_interest_grouping_id][] = $civimailchimp_interest_group;
    }
    return $interest_groups;
  }

  static function saveSettings($mailchimp_sync_setting, $interest_groups) {
    // Get the interest groups from Mailchimp again so we can associate a
    // name with the interest group id.
    $mailchimp_interest_groups = CRM_CiviMailchimp_Utils::getInterestGroups($mailchimp_sync_setting->mailchimp_list_id);
    foreach ($interest_groups as $interest_group) {
      $interest_group = explode('_', $interest_group);
      $interest_grouping_id = $interest_group[0];
      $interest_group_id = $interest_group[1];
      // Mailchimp expects the interest group name rather than id when
      // making API subscription requests. However, since the name can be
      // changed and is not unique, we're storing both the id and name.
      $interest_group_name = $mailchimp_interest_groups[$interest_grouping_id][$interest_group_id];
      $mailchimp_interest_group_sync_setting = new CRM_CiviMailchimp_BAO_InterestGroupsSyncSettings();
      $mailchimp_interest_group_sync_setting->civimailchimp_sync_settings_id = $mailchimp_sync_setting->id;
      $mailchimp_interest_group_sync_setting->mailchimp_interest_grouping_id = $interest_grouping_id;
      $mailchimp_interest_group_sync_setting->mailchimp_interest_group_id = $interest_group_id;
      $mailchimp_interest_group_sync_setting->mailchimp_interest_group_name = $interest_group_name;
      $mailchimp_interest_group_sync_setting->save();
    }
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
