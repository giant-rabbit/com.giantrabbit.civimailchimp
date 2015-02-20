<?php

require_once 'CiviTest/CiviUnitTestCase.php';

/**
 * Tests for CRM_CiviMailchimp_BAO_SyncSettings.
 */
class CRM_CiviMailchimp_BAO_SyncSettingsTest extends CiviUnitTestCase {
  function setUp() {
    // If your test manipulates any SQL tables, then you should truncate
    // them to ensure a consisting starting point for all tests
    // $this->quickCleanup(array('example_table_name'));
    parent::setUp();
  }

  function tearDown() {
    parent::tearDown();
  }

  static function createTestSettings($group_id, $mailchimp_list_id = 'MailchimpListsTestListA', $mailchimp_interest_groups = array()) {
    // Check to see if there is an existing record for the provided Mailchimp
    // List ID. If so, delete it.
    $mailchimp_sync_setting = CRM_CiviMailchimp_BAO_SyncSettings::findByListId($mailchimp_list_id, $throw_exception = FALSE);
    if ($mailchimp_sync_setting) {
      CRM_CiviMailchimp_BAO_SyncSettings::deleteSettings($mailchimp_sync_setting);
    }
    $params = array(
      'civicrm_group_id' => $group_id,
      'mailchimp_list_id' => $mailchimp_list_id,
      'mailchimp_interest_groups' => $mailchimp_interest_groups,
    );
    $mailchimp_sync_setting = CRM_CiviMailchimp_BAO_SyncSettings::saveSettings($params);

    return $mailchimp_sync_setting;
  }
}
