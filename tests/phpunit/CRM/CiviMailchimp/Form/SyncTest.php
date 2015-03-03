<?php

require_once 'CiviTest/CiviUnitTestCase.php';

class CRM_CiviMailchimp_Form_SyncTest extends CiviUnitTestCase {

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

  function testProcessForcedSync() {
    $mailchimp_export_url = __DIR__ . '/../../../sample_mailchimp_export.txt';
    $mailchimp_export_url = 'file:///' . realpath($mailchimp_export_url);
    $mailchimp_sync_setting = CRM_CiviMailchimp_BAO_SyncSettingsTest::createTestGroupAndSyncSettings('Test Group testProcessForcedSync');
    $params = CRM_CiviMailchimp_UtilsTest::sampleContactParams();
    $contact = CRM_Contact_BAO_Contact::create($params);
    $contact_ids = array($contact->id);
    CRM_Contact_BAO_GroupContact::addContactsToGroup($contact_ids, $mailchimp_sync_setting->civicrm_group_id);
    list($contacts, $mailchimp_members) = CRM_CiviMailchimp_Form_Sync::processForcedSync($mailchimp_sync_setting, $mailchimp_export_url);
    $this->assertCount(1, $contacts);
    $this->assertCount(3, $mailchimp_members);
  }

  function testForceCiviToMailchimpSync() {
    $mailchimp_sync_setting = CRM_CiviMailchimp_BAO_SyncSettingsTest::createTestGroupAndSyncSettings('Test Group testForceCiviToMailchimpSync');
    $params = CRM_CiviMailchimp_UtilsTest::sampleContactParams();
    $contact = CRM_Contact_BAO_Contact::create($params);
    $contact_ids = array($contact->id);
    CRM_Contact_BAO_GroupContact::addContactsToGroup($contact_ids, $mailchimp_sync_setting->civicrm_group_id);
    $contacts = CRM_CiviMailchimp_Form_Sync::forceCiviToMailchimpSync($mailchimp_sync_setting);
    $mailchimp_api_subscribe = civimailchimp_static('mailchimp_api_subscribe');
    $this->assertCount(1, $contacts);
    $this->assertTrue($mailchimp_api_subscribe);
    $this->assertEquals($contact->id, $contacts[$contact->id]['contact_id']);
  }

  function testForceMailchimpToCiviSync() {
    $mailchimp_export_url = __DIR__ . '/../../../sample_mailchimp_export.txt';
    $mailchimp_export_url = 'file:///' . realpath($mailchimp_export_url);
    $mailchimp_sync_setting = CRM_CiviMailchimp_BAO_SyncSettingsTest::createTestGroupAndSyncSettings('Test Group testForceMailchimpToCiviSync');
    $mailchimp_members = CRM_CiviMailchimp_Form_Sync::forceMailchimpToCiviSync($mailchimp_export_url, $mailchimp_sync_setting);
    $contacts = CRM_Contact_BAO_Group::getGroupContacts($mailchimp_sync_setting->civicrm_group_id);
    $this->assertCount(3, $mailchimp_members);
    $this->assertCount(3, $contacts);
  }
}
