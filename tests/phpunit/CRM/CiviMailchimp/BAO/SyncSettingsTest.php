<?php

require_once 'CiviTest/CiviUnitTestCase.php';

/**
 * Tests for CRM_CiviMailchimp_BAO_SyncSettings.
 */
class CRM_CiviMailchimp_BAO_SyncSettingsTest extends CiviUnitTestCase {

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

  function testFindByGroupId() {
    $expected_mailchimp_sync_setting = self::createTestGroupAndSyncSettings('Test group testfindByGroupId');
    $mailchimp_sync_setting = CRM_CiviMailchimp_BAO_SyncSettings::findByGroupId($expected_mailchimp_sync_setting->civicrm_group_id);
    $this->assertEquals($expected_mailchimp_sync_setting->id, $mailchimp_sync_setting->id);
    $this->assertEquals($expected_mailchimp_sync_setting->civicrm_group_id, $mailchimp_sync_setting->civicrm_group_id);
    $this->assertEquals($expected_mailchimp_sync_setting->mailchimp_list_id, $mailchimp_sync_setting->mailchimp_list_id);
    $this->assertEquals($expected_mailchimp_sync_setting->mailchimp_interest_groups, $mailchimp_sync_setting->mailchimp_interest_groups);
  }

  function testFindByGroupIdNoSyncSettings() {
    $group_name = 'Test Group testFindByGroupIdNoSyncSettings';
    $group_id = $this->groupCreate(array('name' => $group_name, 'title' => $group_name));
    $mailchimp_sync_setting = CRM_CiviMailchimp_BAO_SyncSettings::findByGroupId($group_id);
    $this->assertNull($mailchimp_sync_setting);
  }

  function testFindByListId() {
    $expected_mailchimp_sync_setting = self::createTestGroupAndSyncSettings('Test group testFindByListId');
    $mailchimp_sync_setting = CRM_CiviMailchimp_BAO_SyncSettings::findByListId($expected_mailchimp_sync_setting->mailchimp_list_id);
    $this->assertEquals($expected_mailchimp_sync_setting->id, $mailchimp_sync_setting->id);
    $this->assertEquals($expected_mailchimp_sync_setting->civicrm_group_id, $mailchimp_sync_setting->civicrm_group_id);
    $this->assertEquals($expected_mailchimp_sync_setting->mailchimp_list_id, $mailchimp_sync_setting->mailchimp_list_id);
    $this->assertEquals($expected_mailchimp_sync_setting->mailchimp_interest_groups, $mailchimp_sync_setting->mailchimp_interest_groups);
  }

  function testFindByListIdThrowException() {
    $mailchimp_list_id = 'MailchimpListsTestListA';
    $this->setExpectedException('CRM_CiviMailchimp_Exception', "Could not find a CiviCRM Group configured to sync with Mailchimp List ID {$mailchimp_list_id}.");
    $mailchimp_sync_setting = CRM_CiviMailchimp_BAO_SyncSettings::findByListId($mailchimp_list_id);
  }

  function testFindByListIdNoException() {
    $mailchimp_list_id = 'MailchimpListsTestListA';
    $mailchimp_sync_setting = CRM_CiviMailchimp_BAO_SyncSettings::findByListId($mailchimp_list_id, FALSE);
    $this->assertNull($mailchimp_sync_setting);
  }

  function testFindByContactId() {
    $expected_mailchimp_sync_setting = self::createTestGroupAndSyncSettings('Test group testFindByContactId');
    $params = CRM_CiviMailchimp_UtilsTest::sampleContactParams();
    $contact = CRM_Contact_BAO_Contact::create($params);
    $contact_ids = array($contact->id);
    CRM_Contact_BAO_GroupContact::addContactsToGroup($contact_ids, $expected_mailchimp_sync_setting->civicrm_group_id);
    $mailchimp_sync_settings = CRM_CiviMailchimp_BAO_SyncSettings::findByContactId($contact->id);
    $mailchimp_sync_setting = $mailchimp_sync_settings[$expected_mailchimp_sync_setting->civicrm_group_id];
    $this->assertEquals($expected_mailchimp_sync_setting->id, $mailchimp_sync_setting->id);
    $this->assertEquals($expected_mailchimp_sync_setting->civicrm_group_id, $mailchimp_sync_setting->civicrm_group_id);
    $this->assertEquals($expected_mailchimp_sync_setting->mailchimp_list_id, $mailchimp_sync_setting->mailchimp_list_id);
    $this->assertEquals($expected_mailchimp_sync_setting->mailchimp_interest_groups, $mailchimp_sync_setting->mailchimp_interest_groups);
  }

  function testFindByContactIdNoSyncSettings() {
    $params = CRM_CiviMailchimp_UtilsTest::sampleContactParams();
    $contact = CRM_Contact_BAO_Contact::create($params);
    $mailchimp_sync_settings = CRM_CiviMailchimp_BAO_SyncSettings::findByContactId($contact->id);
    $this->assertEmpty($mailchimp_sync_settings);
  }

  function testSaveSettings() {
    $mailchimp_list_id = 'MailchimpListsTestListA';
    $mailchimp_interest_groups = array(
      'MailchimpTestInterestGroupingA_MailchimpTestInterestGroupA',
      'MailchimpTestInterestGroupingA_MailchimpTestInterestGroupC',
    );
    $existing_mailchimp_sync_setting = CRM_CiviMailchimp_BAO_SyncSettingsTest::createTestGroupAndSyncSettings('Test Group testFindByContactIdNoSyncSettings', $mailchimp_list_id, $mailchimp_interest_groups);
    $params = array(
      'civicrm_group_id' => $existing_mailchimp_sync_setting->civicrm_group_id,
      'mailchimp_list_id' => 'MailchimpListsTestListB',
      'mailchimp_interest_groups' => NULL,
    );
    $mailchimp_sync_setting = CRM_CiviMailchimp_BAO_SyncSettings::saveSettings($params);
    $this->assertEquals($existing_mailchimp_sync_setting->id, $mailchimp_sync_setting->id);
    $this->assertEquals($existing_mailchimp_sync_setting->civicrm_group_id, $mailchimp_sync_setting->civicrm_group_id);
    $this->assertEquals('MailchimpListsTestListB', $mailchimp_sync_setting->mailchimp_list_id);
    $this->assertEmpty($mailchimp_sync_setting->mailchimp_interest_groups);
  }

  function testDeleteSettings() {
    $existing_mailchimp_sync_setting = self::createTestGroupAndSyncSettings('Test group testDeleteSettings');
    $mailchimp_sync_setting = CRM_CiviMailchimp_BAO_SyncSettings::deleteSettings($existing_mailchimp_sync_setting);
    $deleted_mailchimp_sync_setting = CRM_CiviMailchimp_BAO_SyncSettings::findByListId($mailchimp_sync_setting->mailchimp_list_id, FALSE);
    $this->assertEquals($existing_mailchimp_sync_setting->id, $mailchimp_sync_setting->id);
    $this->assertNull($deleted_mailchimp_sync_setting);
  }

  static function createTestSettings($group_id, $mailchimp_list_id = 'MailchimpListsTestListA', $mailchimp_interest_groups = array()) {
    $params = array(
      'civicrm_group_id' => $group_id,
      'mailchimp_list_id' => $mailchimp_list_id,
      'mailchimp_interest_groups' => $mailchimp_interest_groups,
    );
    $mailchimp_sync_setting = CRM_CiviMailchimp_BAO_SyncSettings::saveSettings($params);

    return $mailchimp_sync_setting;
  }

  static function createTestGroupAndSyncSettings($group_name, $mailchimp_list_id = 'MailchimpListsTestListA', $mailchimp_interest_groups = array()) {
    $group_params = array(
      'name' => $group_name,
      'title' => $group_name,
      'domain_id' => 1,
      'description' => 'New Test Group Created',
      'is_active' => 1,
      'visibility' => 'Public Pages',
      'group_type' => array(
        '1' => 1,
        '2' => 1,
      ),
    );
    $group = CRM_Contact_BAO_Group::create($group_params);
    $mailchimp_sync_setting = self::createTestSettings($group->id, $mailchimp_list_id, $mailchimp_interest_groups);

    return $mailchimp_sync_setting;
  }
}
