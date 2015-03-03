<?php

require_once 'CiviTest/CiviUnitTestCase.php';

class CRM_CiviMailchimp_BAO_InterestGroupsSyncSettingsTest extends CiviUnitTestCase {

  static function setUpBeforeClass() {
    // Use the Mailchimp API test class for all the tests.
    CRM_Core_BAO_Setting::setItem('CRM_MailchimpMock', 'CiviMailchimp Preferences', 'mailchimp_api_class');
  }

  function setUp() {
    parent::setUp();
  }

  function tearDown() {
    // Normally, we wouldn't want to truncate any tables as it makes running
    // the tests slower and opens the door for writing test that aren't self-
    // sufficient, but we're forced into this as CiviUnitTestCase forces a 
    // quickCleanup on civicrm_contact in its tearDown. :(
    $this->quickCleanup(array('civicrm_email', 'civicrm_queue_item', 'civimailchimp_sync_settings', 'civimailchimp_interest_groups_sync_settings'));
    civimailchimp_static('mailchimp_static_reset', NULL, TRUE);
    parent::tearDown();
  }

  function testFindBySyncSettingsId() {
    $mailchimp_list_id = 'MailchimpListsTestListA';
    $mailchimp_interest_groups = array(
      'MailchimpTestInterestGroupingA_MailchimpTestInterestGroupA',
      'MailchimpTestInterestGroupingA_MailchimpTestInterestGroupC',
    );
    $existing_mailchimp_sync_setting = CRM_CiviMailchimp_BAO_SyncSettingsTest::createTestGroupAndSyncSettings('Test Group testFindBySyncSettingsId', $mailchimp_list_id, $mailchimp_interest_groups);
    $interest_groups = CRM_CiviMailchimp_BAO_InterestGroupsSyncSettings::findBySyncSettingsId($existing_mailchimp_sync_setting->id);
    $this->assertArrayHasKey('MailchimpTestInterestGroupingA', $interest_groups);
    $this->assertCount(2, $interest_groups['MailchimpTestInterestGroupingA']);
    $this->assertEquals($existing_mailchimp_sync_setting->id, $interest_groups['MailchimpTestInterestGroupingA'][0]->civimailchimp_sync_settings_id);
    $this->assertEquals('MailchimpTestInterestGroupingA', $interest_groups['MailchimpTestInterestGroupingA'][0]->mailchimp_interest_grouping_id);
    $this->assertEquals('MailchimpTestInterestGroupA', $interest_groups['MailchimpTestInterestGroupingA'][0]->mailchimp_interest_group_id);
    $this->assertEquals('Test Interest Group A', $interest_groups['MailchimpTestInterestGroupingA'][0]->mailchimp_interest_group_name);
    $this->assertEquals($existing_mailchimp_sync_setting->id, $interest_groups['MailchimpTestInterestGroupingA'][1]->civimailchimp_sync_settings_id);
    $this->assertEquals('MailchimpTestInterestGroupingA', $interest_groups['MailchimpTestInterestGroupingA'][1]->mailchimp_interest_grouping_id);
    $this->assertEquals('MailchimpTestInterestGroupC', $interest_groups['MailchimpTestInterestGroupingA'][1]->mailchimp_interest_group_id);
    $this->assertEquals('Test Interest Group C', $interest_groups['MailchimpTestInterestGroupingA'][1]->mailchimp_interest_group_name);
  }

  function testSaveSettings() {
    $mailchimp_sync_setting = CRM_CiviMailchimp_BAO_SyncSettingsTest::createTestGroupAndSyncSettings('Test Group testSaveSettings');
    $mailchimp_interest_groups = array(
      'MailchimpTestInterestGroupingA_MailchimpTestInterestGroupA',
      'MailchimpTestInterestGroupingA_MailchimpTestInterestGroupC',
    );
    CRM_CiviMailchimp_BAO_InterestGroupsSyncSettings::saveSettings($mailchimp_sync_setting, $mailchimp_interest_groups);
    $interest_groups = CRM_CiviMailchimp_BAO_InterestGroupsSyncSettings::findBySyncSettingsId($mailchimp_sync_setting->id);
    $this->assertArrayHasKey('MailchimpTestInterestGroupingA', $interest_groups);
    $this->assertCount(2, $interest_groups['MailchimpTestInterestGroupingA']);
    $this->assertEquals($mailchimp_sync_setting->id, $interest_groups['MailchimpTestInterestGroupingA'][0]->civimailchimp_sync_settings_id);
    $this->assertEquals('MailchimpTestInterestGroupingA', $interest_groups['MailchimpTestInterestGroupingA'][0]->mailchimp_interest_grouping_id);
    $this->assertEquals('MailchimpTestInterestGroupA', $interest_groups['MailchimpTestInterestGroupingA'][0]->mailchimp_interest_group_id);
    $this->assertEquals('Test Interest Group A', $interest_groups['MailchimpTestInterestGroupingA'][0]->mailchimp_interest_group_name);
    $this->assertEquals($mailchimp_sync_setting->id, $interest_groups['MailchimpTestInterestGroupingA'][1]->civimailchimp_sync_settings_id);
    $this->assertEquals('MailchimpTestInterestGroupingA', $interest_groups['MailchimpTestInterestGroupingA'][1]->mailchimp_interest_grouping_id);
    $this->assertEquals('MailchimpTestInterestGroupC', $interest_groups['MailchimpTestInterestGroupingA'][1]->mailchimp_interest_group_id);
    $this->assertEquals('Test Interest Group C', $interest_groups['MailchimpTestInterestGroupingA'][1]->mailchimp_interest_group_name);
  }

  function testDeleteAllForSyncSettingsId() {
    $mailchimp_list_id = 'MailchimpListsTestListA';
    $mailchimp_interest_groups = array(
      'MailchimpTestInterestGroupingA_MailchimpTestInterestGroupA',
      'MailchimpTestInterestGroupingA_MailchimpTestInterestGroupC',
    );
    $mailchimp_sync_setting = CRM_CiviMailchimp_BAO_SyncSettingsTest::createTestGroupAndSyncSettings('Test Group testDeleteAllForSyncSettingsId', $mailchimp_list_id, $mailchimp_interest_groups);
    CRM_CiviMailchimp_BAO_InterestGroupsSyncSettings::deleteAllForSyncSettingsId($mailchimp_sync_setting->id);
    $interest_groups = CRM_CiviMailchimp_BAO_InterestGroupsSyncSettings::findBySyncSettingsId($mailchimp_sync_setting->id);
    $this->assertEmpty($interest_groups);
  }
}
