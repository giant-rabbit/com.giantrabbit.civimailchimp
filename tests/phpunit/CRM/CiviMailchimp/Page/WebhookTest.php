<?php

require_once 'CiviTest/CiviUnitTestCase.php';

/**
 * Tests for Mailchimp Webhooks.
 */
class CRM_CiviMailchimp_Page_WebhookTest extends CiviUnitTestCase {

  static function setUpBeforeClass() {
    // Use the Mailchimp API test class for all the tests.
    CRM_Core_BAO_Setting::setItem('CRM_MailchimpMock', 'CiviMailchimp Preferences', 'mailchimp_api_class');
  }

  function setUp() {
    // If your test manipulates any SQL tables, then you should truncate
    // them to ensure a consisting starting point for all tests
    // $this->quickCleanup(array('example_table_name'));
    parent::setUp();
  }

  function tearDown() {
    parent::tearDown();
  }


  function testMailchimpWebhookSubscribeExistingContact() {
    $sync_settings = CRM_CiviMailchimp_BAO_SyncSettingsTest::createTestGroupAndSyncSettings('test_group_mailchimp_webhook_subscribe_existing_contact');
    $sample_data = self::sampleRequestSubscribeOrProfileUpdate();
    $existing_contact = CRM_CiviMailchimp_Utils::createContactFromMailchimpRequest($sample_data);
    CRM_CiviMailchimp_Page_Webhook::mailchimpWebhookSubscribe($sample_data);
    $this->assertTrue(CRM_Contact_BAO_GroupContact::isContactInGroup($existing_contact->id, $sync_settings->civicrm_group_id));
  }

  function testMailchimpWebhookSubscribeNewContact() {
    $sync_settings = CRM_CiviMailchimp_BAO_SyncSettingsTest::createTestGroupAndSyncSettings('test_group_mailchimp_webhook_subscribe_new_contact');
    $sample_data = self::sampleRequestSubscribeOrProfileUpdate();
    CRM_CiviMailchimp_Page_Webhook::mailchimpWebhookSubscribe($sample_data);
    $new_contact = CRM_Contact_BAO_Contact::matchContactOnEmail($sample_data['email']);
    $this->assertTrue(CRM_Contact_BAO_GroupContact::isContactInGroup($new_contact->contact_id, $sync_settings->civicrm_group_id));
  }

  function testMailchimpWebhookUnsubscribe() {
    $sync_settings = CRM_CiviMailchimp_BAO_SyncSettingsTest::createTestGroupAndSyncSettings('test_group_mailchimp_webhook_unsubscribe');
    $sample_data = self::sampleRequestSubscribeOrProfileUpdate();
    $contact = CRM_CiviMailchimp_Utils::createContactFromMailchimpRequest($sample_data);
    CRM_Contact_BAO_GroupContact::addContactsToGroup(array($contact->id), $sync_settings->civicrm_group_id);
    CRM_CiviMailchimp_Page_Webhook::mailchimpWebhookUnsubscribe($sample_data);
    $this->assertFalse(CRM_Contact_BAO_GroupContact::isContactInGroup($contact->id, $sync_settings->civicrm_group_id));
  }

  function testMailchimpWebhookUpemail() {
    $sample_contact_data = self::sampleRequestSubscribeOrProfileUpdate();
    $sample_upemail_data = self::sampleRequestUpemail();
    $sample_contact_data['email'] = $sample_upemail_data['old_email'];
    $sample_contact_data['merges']['EMAIL'] = $sample_upemail_data['old_email'];
    $contact = CRM_CiviMailchimp_Utils::createContactFromMailchimpRequest($sample_contact_data);

    $sync_settings = CRM_CiviMailchimp_BAO_SyncSettingsTest::createTestGroupAndSyncSettings('test_group_mailchimp_webhook_upemail');
    CRM_Contact_BAO_GroupContact::addContactsToGroup(array($contact->id), $sync_settings->civicrm_group_id);
    CRM_CiviMailchimp_Page_Webhook::mailchimpWebhookUpemail($sample_upemail_data);

    $contact_details = CRM_Contact_BAO_Contact::getContactDetails($contact->id);
    $this->assertEquals($sample_upemail_data['new_email'], $contact_details[1]);
  }

  function testMailchimpWebhookProfile() {
    $sample_data = self::sampleRequestSubscribeOrProfileUpdate();
    $contact = CRM_CiviMailchimp_Utils::createContactFromMailchimpRequest($sample_data);
    $sync_settings = CRM_CiviMailchimp_BAO_SyncSettingsTest::createTestGroupAndSyncSettings('test_group_mailchimp_webhook_profile');
    CRM_Contact_BAO_GroupContact::addContactsToGroup(array($contact->id), $sync_settings->civicrm_group_id);

    $sample_new_data = self::sampleRequestSubscribeOrProfileUpdate();
    $sample_data['merges']['FNAME'] = $sample_new_data['merges']['FNAME'];
    $sample_data['merges']['LNAME'] = $sample_new_data['merges']['LNAME'];
    CRM_CiviMailchimp_Page_Webhook::mailchimpWebhookProfile($sample_data);

    $updated_contact = new CRM_Contact_BAO_Contact();
    $updated_contact->id = $contact->id;
    $updated_contact->find(TRUE);
    $this->assertEquals($updated_contact->first_name, $sample_new_data['merges']['FNAME']);
    $this->assertEquals($updated_contact->last_name, $sample_new_data['merges']['LNAME']);
  }

  function testMailchimpWebhookCleaned() {
    $sample_data = self::sampleRequestSubscribeOrProfileUpdate();
    $contact = CRM_CiviMailchimp_Utils::createContactFromMailchimpRequest($sample_data);
    $sync_settings = CRM_CiviMailchimp_BAO_SyncSettingsTest::createTestGroupAndSyncSettings('test_group_mailchimp_webhook_cleaned');
    CRM_Contact_BAO_GroupContact::addContactsToGroup(array($contact->id), $sync_settings->civicrm_group_id);
    $clean_request = self::getCleanRequestFromSampleProfileData($sample_data);
    CRM_CiviMailchimp_Page_Webhook::mailchimpWebhookCleaned($clean_request);
    $mailchimp_contact = CRM_CiviMailchimp_Utils::getContactById($contact->id);
    $this->assertEquals($mailchimp_contact->email[0]->on_hold, 1);
  }

  static function sampleRequestSubscribeOrProfileUpdate() {
    $rand = rand();
    return array(
      'email' => "civimailchimp_test+{$rand}@civimailchimp.org",
      'merges' => array(
        'EMAIL' => "civimailchimp_test+{$rand}@civimailchimp.org",
        'FNAME' => "Civi{$rand}",
        'LNAME' => "Mailchimp{$rand}",
      ),
      'list_id' => 'MailchimpListsTestListA',
    );
  }

  static function sampleRequestUnsubscribeOrCleaned() {
    $rand = rand();
    return array(
      'email' => "civimailchimp_test+{$rand}@civimailchimp.org",
      'list_id' => 'MailchimpListsTestListA',
    );
  }

  static function getCleanRequestFromSampleProfileData($profile_data) {
    return array(
      'email' => $profile_data['email'],
      'list_id' => $profile_data['list_id'],
    );
  }

  static function sampleRequestUpemail() {
    $rand = rand();
    return array(
      'new_email' => "civimailchimp_test_new+{$rand}@civimailchimp.org",
      'old_email' => "civimailchimp_test+{$rand}@civimailchimp.org",
      'list_id' => 'MailchimpListsTestListA',
    );
  }
}
